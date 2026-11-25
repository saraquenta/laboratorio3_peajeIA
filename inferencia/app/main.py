"""Endpoint unificado de inferencia del Sistema Inteligente de Control de Peaje.

    GET  /health            estado del servidor y de los modelos (sin token)
    POST /predict           imagen JPEG/PNG -> JSON plano (placa, placa_normalizada, confianza_ocr, categoria, ejes,
                            pasajeros, latencia_ms). Con la cabecera "X-Anotar: 1" agrega imagen_anotada_base64.
    POST /predict/visual    igual que /predict pero devuelve la imagen anotada (JPEG)
    POST /inferir           mismo analisis en formato anidado (placa{}, vehiculo{}, ejes, keypoints, pasajeros{},
                            imagen_anotada_base64)
    POST /inferir-placa     solo M1 + OCR (consulta rapida por foto)

La imagen puede llegar como cuerpo crudo (Content-Type: image/jpeg), como multipart (campo ``file``
o ``imagen``) o como JSON ``{"imagen_base64": "..."}``. Autenticacion: cabecera ``X-API-Token``.

Ejecutar:  uvicorn app.main:app --host 0.0.0.0 --port 8000
"""
from __future__ import annotations

import base64
import binascii
import threading
import time
from contextlib import asynccontextmanager

import numpy as np
from fastapi import FastAPI, Header, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import Response
from starlette.concurrency import run_in_threadpool

from . import config
from .modelos import Pipeline, PipelineMock

pipeline = PipelineMock() if config.MODO == "mock" else Pipeline()
INICIO = time.time()


def _cargar_en_segundo_plano():
    try:
        pipeline.cargar()
        print(f"[inferencia] Modelos listos en {time.time() - INICIO:.1f} s ({pipeline.dispositivo})", flush=True)
    except Exception as e:  # el error queda visible en /health
        print("[inferencia] ERROR al cargar modelos:", e, flush=True)


@asynccontextmanager
async def _ciclo_de_vida(_app):
    print("=" * 70, flush=True)
    print("[inferencia] Modo:", config.MODO, flush=True)
    if config.URL_PUBLICA:
        print("[inferencia] URL publica (INFERENCE_URL):", config.URL_PUBLICA, flush=True)
    if config.TOKEN_GENERADO:
        print("[inferencia] API_TOKEN generado (copialo a INFERENCE_TOKEN):", config.API_TOKEN, flush=True)
    print("=" * 70, flush=True)
    threading.Thread(target=_cargar_en_segundo_plano, daemon=True).start()
    yield


app = FastAPI(title="Peaje inteligente - inferencia", version="1.0.0", lifespan=_ciclo_de_vida,
              description="M1 placas+OCR, M2 segmentacion de vehiculos, M3 keypoints de llantas (ejes) y pasajeros.")
app.add_middleware(CORSMiddleware, allow_origins=["*"], allow_methods=["*"], allow_headers=["*"])


def _decodificar(datos: bytes) -> np.ndarray:
    import cv2

    img = cv2.imdecode(np.frombuffer(datos, np.uint8), cv2.IMREAD_COLOR)
    if img is None:
        raise HTTPException(status_code=400, detail="No se pudo decodificar la imagen (envie JPEG o PNG)")
    return img


async def _leer_imagen(request: Request) -> bytes:
    tipo = request.headers.get("content-type", "")
    if tipo.startswith("multipart/form-data"):
        form = await request.form()
        archivo = form.get("file") or form.get("imagen") or form.get("image")
        if archivo is None or not hasattr(archivo, "read"):
            raise HTTPException(status_code=400, detail="Falta el campo 'file' (o 'imagen') en el formulario")
        return await archivo.read()
    if tipo.startswith("application/json"):
        cuerpo = await request.json()
        b64 = (cuerpo or {}).get("imagen_base64", "")
        if "," in b64[:100]:
            b64 = b64.split(",", 1)[1]  # data:image/jpeg;base64,...
        try:
            return base64.b64decode(b64)
        except (binascii.Error, ValueError):
            raise HTTPException(status_code=400, detail="imagen_base64 invalida")
    return await request.body()


def _verificar_token(token: str | None):
    if token != config.API_TOKEN:
        raise HTTPException(status_code=401, detail="Token invalido (cabecera X-API-Token)")


def _verificar_listo():
    if not pipeline.listo:
        detalle = pipeline.error or "Los modelos se estan cargando, reintente en unos segundos"
        raise HTTPException(status_code=503, detail=detalle)


async def _inferir(request: Request, x_mock_placa: str | None):
    datos = await _leer_imagen(request)
    if not datos:
        raise HTTPException(status_code=400, detail="Cuerpo vacio: envie una imagen")
    img = _decodificar(datos)
    t0 = time.perf_counter()
    if isinstance(pipeline, PipelineMock):
        res = await run_in_threadpool(pipeline.predecir, img, x_mock_placa)
    else:
        res = await run_in_threadpool(pipeline.predecir, img)
    res["latencia_ms"] = round((time.perf_counter() - t0) * 1000, 1)  # incluye la espera del lock
    return img, res


@app.get("/")
def raiz():
    return {"servicio": "peaje-inferencia", "docs": "/docs", "health": "/health"}


@app.get("/health")
def health():
    import platform

    info = {"estado": "ok" if pipeline.listo else ("error" if pipeline.error else "cargando"),
            "pasajeros_fuente": getattr(pipeline, "pasajeros_fuente", "mock"),
            "modo": config.MODO, "dispositivo": str(pipeline.dispositivo), "modelos": pipeline.info,
            "python": platform.python_version(), "uptime_s": round(time.time() - INICIO, 1)}
    if pipeline.error:
        info["error"] = pipeline.error
    try:
        import torch

        info["torch"] = torch.__version__
        info["cuda"] = torch.cuda.is_available()
    except ImportError:
        pass
    return info


def _anotada_b64(img, res) -> str:
    """Evidencia anotada (mascara del vehiculo, placa, keypoints de llantas y cajas de pasajeros; sin recortes de rostros)."""
    import cv2

    from .visual import dibujar

    ok, jpg = cv2.imencode(".jpg", dibujar(img, res), [cv2.IMWRITE_JPEG_QUALITY, 80])
    return "data:image/jpeg;base64," + base64.b64encode(jpg.tobytes()).decode("ascii")


@app.post("/predict")
async def predict(request: Request, x_api_token: str | None = Header(default=None),
                  x_mock_placa: str | None = Header(default=None), x_anotar: str | None = Header(default=None)):
    _verificar_token(x_api_token)
    _verificar_listo()
    img, res = await _inferir(request, x_mock_placa)
    if x_anotar == "1":
        res["imagen_anotada_base64"] = await run_in_threadpool(_anotada_b64, img, res)
    return res


def _area_poligono(pts) -> float:
    if not pts or len(pts) < 3:
        return 0.0
    a = 0.0
    for (x1, y1), (x2, y2) in zip(pts, pts[1:] + pts[:1]):
        a += x1 * y2 - x2 * y1
    return abs(a) / 2


def formato_anidado(res: dict) -> dict:
    """Convierte la salida plana de /predict al formato anidado de /inferir."""
    d = res.get("detalles") or {}
    v = d.get("vehiculo") or {}
    area_img = max(1.0, float(d.get("ancho") or 1) * float(d.get("alto") or 1))
    ejes_contados = res.get("ejes_fuente") == "keypoints"
    return {
        "placa": {"texto": res.get("placa_normalizada"), "texto_crudo": res.get("placa"),
                  "formato_bolivia": bool(res.get("placa_valida")), "confianza": res.get("confianza_ocr"),
                  "bbox": d.get("placa_caja")},
        "vehiculo": {"categoria": res.get("categoria"), "confianza": res.get("confianza_categoria"),
                     "area_mascara": round(_area_poligono(v.get("poligono")) / area_img, 3) if v.get("poligono") else None,
                     "bbox": v.get("caja")},
        # null si la vista (frontal/posterior) no permite contar: el backend usa los ejes registrados o la tarifa base
        "ejes": res.get("ejes") if ejes_contados else None,
        "ejes_estimados": res.get("ejes"),
        "vista_vehiculo": d.get("vista") or "-",
        "keypoints": [l["keypoints"][0] for l in d.get("llantas", [])],   # centro de cada llanta [x, y, conf]
        "pasajeros": {"conteo": res.get("pasajeros", 0), "confianza_promedio": d.get("pasajeros_confianza"),
                      "fuente": res.get("pasajeros_fuente")},
        "latencia_ms": res.get("latencia_ms"),
        "modelos": res.get("modelos"),
    }


@app.post("/inferir")
async def inferir(request: Request, x_api_token: str | None = Header(default=None),
                  x_mock_placa: str | None = Header(default=None)):
    _verificar_token(x_api_token)
    _verificar_listo()
    img, res = await _inferir(request, x_mock_placa)
    salida = formato_anidado(res)
    salida["imagen_anotada_base64"] = await run_in_threadpool(_anotada_b64, img, res)
    return salida


@app.post("/inferir-placa")
async def inferir_placa(request: Request, x_api_token: str | None = Header(default=None),
                        x_mock_placa: str | None = Header(default=None)):
    _verificar_token(x_api_token)
    _verificar_listo()
    datos = await _leer_imagen(request)
    if not datos:
        raise HTTPException(status_code=400, detail="Cuerpo vacio: envie una imagen")
    img = _decodificar(datos)
    if isinstance(pipeline, PipelineMock):
        return await run_in_threadpool(pipeline.predecir_placa, img, x_mock_placa)
    return await run_in_threadpool(pipeline.predecir_placa, img)


@app.post("/predict/visual")
async def predict_visual(request: Request, x_api_token: str | None = Header(default=None),
                         x_mock_placa: str | None = Header(default=None)):
    import cv2

    from .visual import dibujar

    _verificar_token(x_api_token)
    _verificar_listo()
    img, res = await _inferir(request, x_mock_placa)
    ok, jpg = cv2.imencode(".jpg", dibujar(img, res), [cv2.IMWRITE_JPEG_QUALITY, 85])
    return Response(content=jpg.tobytes(), media_type="image/jpeg",
                    headers={"X-Resultado-Placa": str(res.get("placa_normalizada") or ""),
                             "X-Resultado-Categoria": str(res.get("categoria") or ""),
                             "X-Resultado-Ejes": str(res.get("ejes") or ""),
                             "X-Resultado-Pasajeros": str(res.get("pasajeros") or 0),
                             "X-Pasajeros-Fuente": str(res.get("pasajeros_fuente") or "")})
