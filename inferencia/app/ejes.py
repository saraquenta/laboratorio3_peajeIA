"""M3: conteo de ejes a partir de keypoints de llantas.

Logica tomada del notebook del curso ``keypoints_llantas_yolo_detectron2_v3.ipynb``:
cada llanta tiene 5 keypoints ``[centro, contacto, superior, izquierda, derecha]`` con forma (5, 3).
Solo se cuentan las llantas del lado cercano a la camara y se agrupan por su posicion horizontal
(ruedas dobles = un solo eje). En vista frontal/posterior los ejes no son determinables.
"""
from __future__ import annotations

import numpy as np

KPT_NOMBRES = ["centro", "contacto", "superior", "izquierda", "derecha"]
FLIP_IDX = [0, 1, 2, 4, 3]

UMBRAL_CONTENCION = 0.7   # una deteccion contenida en otra (el aro dentro de la llanta) se descarta
UMBRAL_CERCANA = 0.65     # altura visible minima (respecto a la mayor) para considerar la llanta del lado cercano
SEPARACION_EJE = 0.6      # distancia minima en x entre ejes, en diametros

# Ejes por defecto cuando la vista no permite contarlos
EJES_POR_DEFECTO = {"moto": 2, "auto": 2, "minibus": 2, "bus": 2, "camion": 2}


def altura_visible(l: dict) -> float:
    return max(1.0, float(l["kps"][1, 1] - l["kps"][2, 1]))


def contenida(a, b) -> float:
    x1, y1 = max(a[0], b[0]), max(a[1], b[1])
    x2, y2 = min(a[2], b[2]), min(a[3], b[3])
    inter = max(0, x2 - x1) * max(0, y2 - y1)
    area = max(1e-6, (a[2] - a[0]) * (a[3] - a[1]))
    return inter / area


def limpiar_llantas(llantas: list[dict]) -> list[dict]:
    orden = sorted(llantas, key=lambda l: -(l["box"][2] - l["box"][0]) * (l["box"][3] - l["box"][1]))
    res = []
    for l in orden:
        if all(contenida(l["box"], r["box"]) < UMBRAL_CONTENCION for r in res):
            res.append(l)
    return res


def kps_de_mascara(mask: np.ndarray):
    """5 keypoints geometricos a partir de la mascara de una llanta (metodo zero-shot)."""
    import cv2

    cnts, _ = cv2.findContours(mask.astype(np.uint8), cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_NONE)
    if not cnts:
        return None
    c = max(cnts, key=cv2.contourArea)
    if cv2.contourArea(c) < 8:
        return None
    pts = c.reshape(-1, 2).astype(float)
    if len(pts) >= 5:
        (cx, cy), _, _ = cv2.fitEllipse(c)
    else:
        cx, cy = pts.mean(0)
    k = np.array([[cx, cy], pts[pts[:, 1].argmax()], pts[pts[:, 1].argmin()],
                  pts[pts[:, 0].argmin()], pts[pts[:, 0].argmax()]])
    return np.concatenate([k, np.ones((5, 1))], axis=1)


def llantas_del_vehiculo(box_vehiculo, llantas: list[dict]) -> list[dict]:
    """Llantas cuyo centro cae dentro del vehiculo y en su parte baja."""
    x1, y1, x2, y2 = box_vehiculo
    w, h = x2 - x1, y2 - y1
    res = []
    for l in limpiar_llantas(llantas):
        cx, cy = l["kps"][0, :2]
        if x1 - 0.05 * w <= cx <= x2 + 0.05 * w and y1 + 0.35 * h <= cy <= y2 + 0.10 * h:
            res.append(l)
    return res


def contar_ejes(box_vehiculo, llantas: list[dict]) -> tuple[int | None, str, str]:
    """Devuelve (ejes, vista, nota). ejes = None si no es determinable."""
    ls = llantas_del_vehiculo(box_vehiculo, llantas)
    if not ls:
        return None, "-", "Sin llantas detectadas"
    hmax = max(altura_visible(l) for l in ls)
    cercanas = [l for l in ls if altura_visible(l) >= UMBRAL_CERCANA * hmax]
    x1, y1, x2, y2 = box_vehiculo
    proporcion = (x2 - x1) / max(1.0, y2 - y1)
    if len(cercanas) == 2 and proporcion < 1.6:
        a, b = cercanas
        angostas = all((l["kps"][4, 0] - l["kps"][3, 0]) / altura_visible(l) < 0.65 for l in cercanas)
        misma_altura = abs(a["kps"][1, 1] - b["kps"][1, 1]) < 0.15 * hmax
        if angostas and misma_altura:
            return None, "frontal/posterior", "No determinable en esta vista"
    xs = sorted(float(l["kps"][0, 0]) for l in cercanas)
    diam = float(np.median([altura_visible(l) for l in cercanas]))
    ejes = 1 + sum(1 for p, q in zip(xs, xs[1:]) if q - p > SEPARACION_EJE * diam)
    redondez = float(np.median([(l["kps"][4, 0] - l["kps"][3, 0]) / altura_visible(l) for l in cercanas]))
    vista = "lateral" if redondez > 0.8 else "3/4"
    nota = ""
    if ejes < 2:
        ejes, nota = 2, "Minimo asumido (solo se detecto 1 eje)"
    return ejes, vista, nota
