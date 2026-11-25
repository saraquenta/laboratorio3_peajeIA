"""Dibuja el resultado sobre el frame (para /predict/visual y para el video de demostracion)."""
from __future__ import annotations

import numpy as np

COLORES = {"auto": (219, 152, 52), "bus": (60, 76, 231), "minibus": (113, 204, 46),
           "camion": (18, 156, 243), "moto": (182, 89, 155)}  # BGR
KPT_COLORES = [(255, 255, 255), (0, 0, 255), (255, 200, 0), (0, 255, 0), (255, 0, 255)]


def dibujar(img_bgr: np.ndarray, r: dict) -> np.ndarray:
    import cv2

    out = img_bgr.copy()
    g = max(2, int(round(max(out.shape[:2]) / 450)))
    d = r.get("detalles") or {}
    v = d.get("vehiculo")
    col = COLORES.get(r.get("categoria") or "", (200, 200, 200))
    if v and v.get("poligono"):
        capa = out.copy()
        cv2.fillPoly(capa, [np.array(v["poligono"], np.int32)], col)
        out = cv2.addWeighted(capa, 0.35, out, 0.65, 0)
    if v and v.get("caja"):
        x1, y1, x2, y2 = map(int, v["caja"])
        cv2.rectangle(out, (x1, y1), (x2, y2), col, g)
        txt = f"{r.get('categoria')} | ejes {r.get('ejes')} | pasajeros {r.get('pasajeros')}"
        cv2.putText(out, txt, (x1 + 4, max(20, y1 - 8)), cv2.FONT_HERSHEY_SIMPLEX, g * 0.35, col, max(1, g // 2), cv2.LINE_AA)
    if d.get("placa_caja"):
        x1, y1, x2, y2 = map(int, d["placa_caja"])
        cv2.rectangle(out, (x1, y1), (x2, y2), (0, 255, 255), g)
        cv2.putText(out, str(r.get("placa_normalizada") or "?"), (x1, y2 + 14 * g), cv2.FONT_HERSHEY_SIMPLEX,
                    g * 0.4, (0, 255, 255), max(1, g // 2), cv2.LINE_AA)
    for l in d.get("llantas", []):
        for i, (x, y, c) in enumerate(l["keypoints"]):
            if c > 0.05:
                cv2.circle(out, (int(x), int(y)), g + 2, KPT_COLORES[i % 5], -1, cv2.LINE_AA)
    cajas_p = d.get("pasajeros_cajas", [])
    if cajas_p:  # privacidad: relleno OPACO sobre cada persona (no se ve el rostro) y solo el conteo
        capa = out.copy()
        for b in cajas_p:
            x1, y1, x2, y2 = map(int, b)
            cv2.rectangle(capa, (x1, y1), (x2, y2), (255, 200, 0), -1)
        out = cv2.addWeighted(capa, 0.85, out, 0.15, 0)
        cv2.putText(out, f"pasajeros: {len(cajas_p)}", (10, 30 * g), cv2.FONT_HERSHEY_SIMPLEX, g * 0.4,
                    (255, 200, 0), max(1, g // 2), cv2.LINE_AA)
    for i, l in enumerate(d.get("llantas", []), 1):  # etiqueta K1..Kn en el centro de cada llanta
        x, y, _ = l["keypoints"][0]
        cv2.putText(out, f"K{i}", (int(x) + 6, int(y) - 6), cv2.FONT_HERSHEY_SIMPLEX, g * 0.3, (255, 255, 255), 1, cv2.LINE_AA)
    return out
