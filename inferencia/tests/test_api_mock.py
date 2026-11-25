"""Prueba del contrato JSON del endpoint en modo mock (no requiere GPU ni modelos)."""
import os

os.environ["INFERENCE_MODE"] = "mock"
os.environ["API_TOKEN"] = "token-prueba"

import cv2  # noqa: E402
import numpy as np  # noqa: E402
from fastapi.testclient import TestClient  # noqa: E402

from app.main import app  # noqa: E402

CAMPOS = {"placa", "placa_normalizada", "confianza_ocr", "categoria", "ejes", "pasajeros", "latencia_ms"}


def _jpeg():
    img = np.full((240, 320, 3), 127, np.uint8)
    return cv2.imencode(".jpg", img)[1].tobytes()


def test_health():
    with TestClient(app) as c:
        r = c.get("/health")
        assert r.status_code == 200 and r.json()["estado"] == "ok"


def test_token_obligatorio():
    with TestClient(app) as c:
        assert c.post("/predict", content=_jpeg()).status_code == 401


def test_predict_cuerpo_crudo_y_multipart():
    with TestClient(app) as c:
        h = {"X-API-Token": "token-prueba", "Content-Type": "image/jpeg", "X-Mock-Placa": "1234-ABC"}
        r = c.post("/predict", content=_jpeg(), headers=h)
        assert r.status_code == 200
        j = r.json()
        assert CAMPOS <= set(j)
        assert j["placa_normalizada"] == "1234ABC"
        r2 = c.post("/predict", files={"file": ("f.jpg", _jpeg(), "image/jpeg")},
                    headers={"X-API-Token": "token-prueba"})
        assert r2.status_code == 200 and CAMPOS <= set(r2.json())


def test_imagen_invalida():
    with TestClient(app) as c:
        r = c.post("/predict", content=b"no-es-imagen",
                   headers={"X-API-Token": "token-prueba", "Content-Type": "image/jpeg"})
        assert r.status_code == 400
