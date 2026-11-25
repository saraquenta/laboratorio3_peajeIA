"""Pipeline unificado: M1 (placa + OCR), M2 (segmentacion de vehiculos), M3 (keypoints de llantas -> ejes)
y extra de pasajeros. Un solo frame entra, un solo JSON sale.

Cada modelo usa pesos propios si existen (entrenados con los notebooks de /entrenamiento) y, si no,
recurre a YOLOE con vocabulario abierto (zero-shot), el mismo enfoque de los notebooks del curso.
"""
from __future__ import annotations

import hashlib
import os
import threading
import time

import numpy as np

from . import config
from .ejes import EJES_POR_DEFECTO, contar_ejes, kps_de_mascara
from .normalizacion import normalizar_placa
from .validacion import (CATEGORIAS, kpt_shape_de, normalizar_clase, validar_llantas, validar_pasajeros,
                         validar_placas, validar_vehiculos)


# prompt de texto YOLOE -> clase interna (mismo esquema que el notebook de segmentacion)
PROMPTS_VEHICULO = {"car": "auto", "sedan": "auto", "suv": "auto", "pickup truck": "auto",
                    "minibus": "minibus", "van": "minibus",
                    "bus": "bus",
                    "truck": "camion", "cargo truck": "camion",
                    "motorcycle": "moto", "motorbike": "moto"}
PROMPTS_LLANTA = ["wheel", "tire"]
PROMPTS_PLACA = ["license plate"]

def _iou(a, b) -> float:
    x1, y1 = max(a[0], b[0]), max(a[1], b[1])
    x2, y2 = min(a[2], b[2]), min(a[3], b[3])
    inter = max(0, x2 - x1) * max(0, y2 - y1)
    ua = (a[2] - a[0]) * (a[3] - a[1]) + (b[2] - b[0]) * (b[3] - b[1]) - inter
    return inter / ua if ua > 0 else 0.0


def _fraccion_dentro(a, b) -> float:
    """Fraccion de la caja a contenida en la caja b."""
    x1, y1 = max(a[0], b[0]), max(a[1], b[1])
    x2, y2 = min(a[2], b[2]), min(a[3], b[3])
    inter = max(0, x2 - x1) * max(0, y2 - y1)
    return inter / max(1e-6, (a[2] - a[0]) * (a[3] - a[1]))


def _nms(items, umbral=0.6):
    items = sorted(items, key=lambda d: -d["conf"])
    res = []
    for d in items:
        if all(_iou(d["box"], r["box"]) < umbral for r in res):
            res.append(d)
    return res


def _area(b) -> float:
    return max(0.0, b[2] - b[0]) * max(0.0, b[3] - b[1])


class Pipeline:
    def __init__(self):
        self.listo = False
        self.error = None
        self.dispositivo = "cpu"
        self.info = {}
        self._lock = threading.Lock()
        self._yoloe = self._seg = self._pose = self._placas = self._personas = self._ocr = None
        self._idx_personas = [0]          # clase person de COCO en el respaldo yolo11s-seg.pt
        self.pasajeros_fuente = "coco"    # "propio" cuando se carga pesos/pasajeros_seg.pt
        self.ultima_conf_pasajeros = None  # confianza promedio de las personas contadas en el ultimo frame

    # ------------------------------------------------------------------ carga
    def cargar(self):
        try:
            from types import SimpleNamespace

            import torch
            from ultralytics import YOLO

            def contrato(m):
                return SimpleNamespace(task=m.task, names=m.names, kpt_shape=kpt_shape_de(m))

            self.dispositivo = "cuda:0" if torch.cuda.is_available() else "cpu"
            usa_zero_shot = False

            if os.path.exists(config.PESOS_VEHICULOS):
                self._seg = YOLO(config.PESOS_VEHICULOS)
                v = validar_vehiculos(contrato(self._seg))  # error claro si el .pt no encaja
                self.info["M2_vehiculos"] = "propio:" + os.path.basename(config.PESOS_VEHICULOS)
                self.info["M2_clases"] = v["clases"]
                if v["advertencia"]:
                    self.info["M2_advertencia"] = v["advertencia"]
            else:
                usa_zero_shot = True
                self.info["M2_vehiculos"] = "zero-shot:" + config.PESOS_YOLOE

            if os.path.exists(config.PESOS_LLANTAS):
                self._pose = YOLO(config.PESOS_LLANTAS)
                validar_llantas(contrato(self._pose))
                self.info["M3_llantas"] = "propio:" + os.path.basename(config.PESOS_LLANTAS) + " (5 kp)"
            else:
                usa_zero_shot = True
                self.info["M3_llantas"] = "zero-shot:" + config.PESOS_YOLOE + " + keypoints geometricos"

            if os.path.exists(config.PESOS_PLACAS):
                self._placas = YOLO(config.PESOS_PLACAS)
                validar_placas(contrato(self._placas))
                self.info["M1_placas"] = "propio:" + os.path.basename(config.PESOS_PLACAS) + " + EasyOCR"
            else:
                usa_zero_shot = True
                self.info["M1_placas"] = "zero-shot:" + config.PESOS_YOLOE + " + EasyOCR"

            if usa_zero_shot:
                from ultralytics import YOLOE

                nombres = list(PROMPTS_VEHICULO) + PROMPTS_LLANTA + PROMPTS_PLACA
                m = YOLOE(config.PESOS_YOLOE)
                try:
                    m.set_classes(nombres, m.get_text_pe(nombres))
                except (AttributeError, TypeError):
                    m.set_classes(nombres)
                self._yoloe = m

            if os.path.exists(config.PESOS_PASAJEROS):
                self._personas = YOLO(config.PESOS_PASAJEROS)
                p = validar_pasajeros(contrato(self._personas))
                self._idx_personas = p["indices_persona"]
                self.pasajeros_fuente = "propio"
                self.info["M5_pasajeros"] = "propio:" + os.path.basename(config.PESOS_PASAJEROS)
                self.info["M5_clases_contadas"] = p["clases_persona"]
            else:
                self._personas = YOLO(config.PESOS_PERSONAS)
                self.info["M5_pasajeros"] = "respaldo:" + config.PESOS_PERSONAS + " (clase person de COCO)"

            import easyocr

            self._ocr = easyocr.Reader(config.OCR_IDIOMAS, gpu=self.dispositivo.startswith("cuda"), verbose=False)

            # Calentamiento: la primera inferencia en GPU es lenta
            self.predecir(np.zeros((480, 640, 3), np.uint8))
            self.listo = True
        except Exception as e:  # se reporta en /health
            self.error = f"{type(e).__name__}: {e}"
            raise

    # ------------------------------------------------------------ utilidades
    def _yolo_a_dets(self, r, mapa, con_mascara=True):
        dets = []
        if r.boxes is None or len(r.boxes) == 0:
            return dets
        clases = r.boxes.cls.cpu().numpy().astype(int)
        confs = r.boxes.conf.cpu().numpy()
        cajas = r.boxes.xyxy.cpu().numpy()
        polis = r.masks.xy if (con_mascara and r.masks is not None) else [None] * len(clases)
        for c, s, b, p in zip(clases, confs, cajas, polis):
            etiqueta = mapa(r.names[int(c)])
            if etiqueta is None:
                continue
            dets.append({"clase": etiqueta, "conf": float(s), "box": [float(v) for v in b], "poly": p})
        return dets

    def _zero_shot(self, img):
        r = self._yoloe.predict(img, conf=min(config.CONF_LLANTA, config.CONF_PLACA, config.CONF_VEHICULO),
                                imgsz=config.IMGSZ, device=self.dispositivo, verbose=False)[0]

        def mapa(n):
            if n in PROMPTS_VEHICULO:
                return PROMPTS_VEHICULO[n]
            if n in PROMPTS_LLANTA:
                return "llanta"
            if n in PROMPTS_PLACA:
                return "placa"
            return None

        return self._yolo_a_dets(r, mapa)

    # ----------------------------------------------------------- M2 vehiculos
    def _vehiculos(self, img, zs):
        if self._seg is not None:
            r = self._seg.predict(img, conf=config.CONF_VEHICULO, imgsz=config.IMGSZ_PROPIO,
                                  device=self.dispositivo, verbose=False)[0]
            dets = self._yolo_a_dets(r, normalizar_clase)
        else:
            dets = [d for d in zs if d["clase"] in CATEGORIAS and d["conf"] >= config.CONF_VEHICULO]
        return _nms(dets, 0.7)

    # -------------------------------------------------------- M3 llantas/ejes
    def _llantas(self, img, zs):
        llantas = []
        if self._pose is not None:
            r = self._pose.predict(img, conf=config.CONF_LLANTA_PROPIO, imgsz=config.IMGSZ_PROPIO,
                                   device=self.dispositivo, verbose=False)[0]
            if r.keypoints is not None and len(r.boxes):
                xy = r.keypoints.xy.cpu().numpy()
                kc = r.keypoints.conf.cpu().numpy() if r.keypoints.conf is not None else np.ones(xy.shape[:2])
                for b, s, p, c in zip(r.boxes.xyxy.cpu().numpy(), r.boxes.conf.cpu().numpy(), xy, kc):
                    llantas.append({"conf": float(s), "box": b.tolist(),
                                    "kps": np.concatenate([p, c[:, None]], 1)})
        else:
            import cv2

            H, W = img.shape[:2]
            for d in zs:
                if d["clase"] != "llanta" or d["poly"] is None or len(d["poly"]) < 3:
                    continue
                m = np.zeros((H, W), np.uint8)
                cv2.fillPoly(m, [d["poly"].astype(np.int32)], 1)
                k = kps_de_mascara(m)
                if k is not None:
                    k[:, 2] = d["conf"]
                    llantas.append({"conf": d["conf"], "box": d["box"], "kps": k})
        return _nms(llantas, 0.5)

    # ------------------------------------------------------------ M1 placas
    def _placas_boxes(self, img, zs):
        if self._placas is not None:
            r = self._placas.predict(img, conf=config.CONF_PLACA, imgsz=config.IMGSZ_PROPIO,
                                     device=self.dispositivo, verbose=False)[0]
            return self._yolo_a_dets(r, lambda n: "placa", con_mascara=False)
        return [d for d in zs if d["clase"] == "placa" and d["conf"] >= config.CONF_PLACA]

    def _ocr_recorte(self, recorte):
        """OCR sobre el recorte de la placa con dos preprocesados; devuelve el mejor candidato."""
        import cv2

        if recorte is None or recorte.size == 0:
            return None
        h = recorte.shape[0]
        escala = max(1.0, 120.0 / max(1, h))
        rec = cv2.resize(recorte, None, fx=escala, fy=escala, interpolation=cv2.INTER_CUBIC)
        gris = cv2.cvtColor(rec, cv2.COLOR_BGR2GRAY)
        clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8)).apply(gris)
        _, binaria = cv2.threshold(clahe, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
        mejor = None
        for variante in (clahe, binaria):
            res = self._ocr.readtext(variante, allowlist="ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-",
                                     detail=1, paragraph=False)
            if not res:
                continue
            alto_max = max(abs(p[0][2][1] - p[0][0][1]) for p in res)
            # descarta textos pequenos (p. ej. "BOLIVIA" del marco) y ordena de izquierda a derecha
            trozos = sorted([p for p in res if abs(p[0][2][1] - p[0][0][1]) >= 0.5 * alto_max],
                            key=lambda p: p[0][0][0])
            texto = "".join(p[1] for p in trozos)
            conf = float(np.mean([p[2] for p in trozos])) if trozos else 0.0
            norm = normalizar_placa(texto)
            puntaje = conf + (0.5 if norm.valida else 0.0) - 0.05 * norm.correcciones
            if mejor is None or puntaje > mejor["puntaje"]:
                mejor = {"texto": texto, "conf": conf, "norm": norm, "puntaje": puntaje}
        return mejor

    def _leer_placa(self, img, placas, vehiculo):
        H, W = img.shape[:2]
        candidatas = placas
        if vehiculo is not None:
            dentro = [p for p in placas if _fraccion_dentro(p["box"], vehiculo["box"]) > 0.6]
            candidatas = dentro or placas
        candidatas = sorted(candidatas, key=lambda p: -p["conf"])[:2]
        mejor, caja = None, None
        for p in candidatas:
            x1, y1, x2, y2 = p["box"]
            mx, my = 0.06 * (x2 - x1), 0.12 * (y2 - y1)
            rec = img[max(0, int(y1 - my)):min(H, int(y2 + my)), max(0, int(x1 - mx)):min(W, int(x2 + mx))]
            r = self._ocr_recorte(rec)
            if r and (mejor is None or r["puntaje"] > mejor["puntaje"]):
                mejor, caja = r, p
        # Respaldo: sin detector de placa, OCR en la mitad inferior del vehiculo
        if mejor is None and vehiculo is not None:
            x1, y1, x2, y2 = [int(v) for v in vehiculo["box"]]
            rec = img[max(0, y1 + (y2 - y1) // 2):min(H, y2), max(0, x1):min(W, x2)]
            mejor = self._ocr_recorte(rec)
        return mejor, caja

    # -------------------------------------------------------- extra pasajeros
    def _pasajeros(self, img, vehiculo):
        if vehiculo is None:
            return 0, []
        imgsz = config.IMGSZ_PROPIO if self.pasajeros_fuente == "propio" else 640
        r = self._personas.predict(img, conf=config.CONF_PERSONA, classes=self._idx_personas, imgsz=imgsz,
                                   device=self.dispositivo, verbose=False)[0]
        if r.boxes is None or len(r.boxes) == 0:
            return 0, []
        umbral = 0.3 if vehiculo["clase"] == "moto" else 0.6  # en moto el conductor sobresale de la caja
        cajas = [b.tolist() for b in r.boxes.xyxy.cpu().numpy()]
        confs = r.boxes.conf.cpu().numpy().tolist() if getattr(r.boxes, "conf", None) is not None else [None] * len(cajas)
        pares = [(b, c) for b, c in zip(cajas, confs) if _fraccion_dentro(b, vehiculo["box"]) >= umbral]
        dentro = [b for b, _ in pares]
        validas = [c for _, c in pares if c is not None]
        self.ultima_conf_pasajeros = round(float(np.mean(validas)), 3) if validas else None
        # Solo se devuelven cajas (sin recortes de rostros ni reconocimiento facial)
        return len(dentro), [[round(v, 1) for v in b] for b in dentro]

    # ------------------------------------------------------- solo placa (M1)
    def predecir_placa(self, img) -> dict:
        """M1 + OCR sin vehiculo/ejes/pasajeros: consulta rapida por foto desde el agente."""
        t0 = time.perf_counter()
        with self._lock:
            zs = self._zero_shot(img) if (self._yoloe is not None and self._placas is None) else []
            placas = self._placas_boxes(img, zs)
            lectura, caja = self._leer_placa(img, placas, None)
        norm = lectura["norm"] if lectura else normalizar_placa("")
        return {"placa": {"texto": norm.normalizada or None, "texto_crudo": lectura["texto"] if lectura else None,
                          "formato_bolivia": norm.valida, "confianza": round(lectura["conf"], 3) if lectura else 0.0,
                          "bbox": [round(v, 1) for v in caja["box"]] if caja else None},
                "latencia_ms": round((time.perf_counter() - t0) * 1000, 1)}

    # ------------------------------------------------------------- principal
    def predecir(self, img) -> dict:
        import cv2

        t0 = time.perf_counter()
        H, W = img.shape[:2]
        escala = 1.0
        if max(H, W) > config.MAX_LADO:
            escala = config.MAX_LADO / max(H, W)
            img = cv2.resize(img, None, fx=escala, fy=escala, interpolation=cv2.INTER_AREA)

        with self._lock:  # una inferencia a la vez sobre la GPU
            zs = self._zero_shot(img) if self._yoloe is not None else []
            vehiculos = self._vehiculos(img, zs)
            principal = max(vehiculos, key=lambda v: _area(v["box"])) if vehiculos else None
            llantas = self._llantas(img, zs)
            placas = self._placas_boxes(img, zs)
            lectura, caja_placa = self._leer_placa(img, placas, principal)
            n_pasajeros, cajas_pasajeros = self._pasajeros(img, principal)

        ejes, vista, nota, fuente = None, "-", "", "sin_vehiculo"
        if principal is not None:
            ejes, vista, nota = contar_ejes(principal["box"], llantas)
            fuente = "keypoints"
            if ejes is None:
                ejes, fuente = EJES_POR_DEFECTO.get(principal["clase"], 2), "por_defecto"

        norm = lectura["norm"] if lectura else normalizar_placa("")
        poligono = None
        if principal is not None and principal.get("poly") is not None and len(principal["poly"]) >= 3:
            c = cv2.approxPolyDP(principal["poly"].astype(np.float32).reshape(-1, 1, 2), 3.0, True)
            poligono = [[round(float(x) / escala, 1), round(float(y) / escala, 1)] for x, y in c.reshape(-1, 2)]

        def esc(b):
            return [round(v / escala, 1) for v in b] if b is not None else None

        return {
            "placa": lectura["texto"] if lectura else None,
            "placa_normalizada": norm.normalizada or None,
            "placa_valida": norm.valida,
            "formato_placa": norm.formato,
            "confianza_ocr": round(lectura["conf"], 3) if lectura else 0.0,
            "categoria": principal["clase"] if principal else None,
            "confianza_categoria": round(principal["conf"], 3) if principal else 0.0,
            "ejes": ejes,
            "ejes_fuente": fuente,
            "pasajeros": n_pasajeros,
            "pasajeros_fuente": self.pasajeros_fuente,
            "latencia_ms": round((time.perf_counter() - t0) * 1000, 1),
            "detalles": {
                "ancho": W, "alto": H,
                "vehiculo": {"caja": esc(principal["box"]), "poligono": poligono} if principal else None,
                "vehiculos_detectados": len(vehiculos),
                "placa_caja": esc(caja_placa["box"]) if caja_placa else None,
                "llantas": [{"caja": esc(l["box"]), "keypoints": [[round(float(x) / escala, 1), round(float(y) / escala, 1),
                                                                    round(float(c), 3)] for x, y, c in l["kps"]]}
                            for l in llantas],
                "vista": vista, "nota_ejes": nota,
                "pasajeros_cajas": [esc(b) for b in cajas_pasajeros],
                "pasajeros_confianza": self.ultima_conf_pasajeros,
            },
            "modelos": self.info,
        }


class PipelineMock:
    """Respuestas simuladas y deterministas (misma imagen -> misma salida). Solo para pruebas de integracion."""

    PLACAS = ["1234ABC", "2345BCD", "3456CDF", "852KLM", "4567DFG", "9999ZZZ"]

    def __init__(self):
        self.listo = True
        self.error = None
        self.dispositivo = "mock"
        self.info = {"modo": "mock (sin modelos)"}

    def cargar(self):
        pass

    def predecir(self, img, placa_forzada: str | None = None) -> dict:
        t0 = time.perf_counter()
        h = int(hashlib.md5(img.tobytes()[:200000]).hexdigest(), 16)
        placa = placa_forzada or self.PLACAS[h % len(self.PLACAS)]
        cat = CATEGORIAS[(h >> 8) % len(CATEGORIAS)]
        norm = normalizar_placa(placa)
        ejes = 3 if cat == "camion" and (h >> 4) % 2 else 2
        return {
            "placa": placa, "placa_normalizada": norm.normalizada, "placa_valida": norm.valida,
            "formato_placa": norm.formato, "confianza_ocr": 0.9,
            "categoria": cat, "confianza_categoria": 0.85, "ejes": ejes, "ejes_fuente": "mock",
            "pasajeros": 1 + (h >> 12) % 3,
            "pasajeros_fuente": "mock",
            "latencia_ms": round((time.perf_counter() - t0) * 1000, 1),
            "detalles": {"ancho": int(img.shape[1]), "alto": int(img.shape[0])},
            "modelos": self.info,
        }

    def predecir_placa(self, img, placa_forzada: str | None = None) -> dict:
        r = self.predecir(img, placa_forzada)
        return {"placa": {"texto": r["placa_normalizada"], "texto_crudo": r["placa"], "formato_bolivia": r["placa_valida"],
                          "confianza": r["confianza_ocr"], "bbox": None}, "latencia_ms": r["latencia_ms"]}
