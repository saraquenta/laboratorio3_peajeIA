"""X-Anotar en /predict, /inferir (formato anidado) e /inferir-placa, en modo mock (sin GPU)."""
import base64
import os

os.environ["INFERENCE_MODE"] = "mock"
os.environ["API_TOKEN"] = "token-prueba"

import cv2  # noqa: E402
import numpy as np  # noqa: E402
from fastapi.testclient import TestClient  # noqa: E402

from app.main import app, formato_anidado  # noqa: E402

H = {"X-API-Token": "token-prueba", "Content-Type": "image/jpeg", "X-Mock-Placa": "4047-CKE"}


def _jpeg():
    return cv2.imencode(".jpg", np.full((240, 320, 3), 90, np.uint8))[1].tobytes()


def test_predict_con_anotacion_devuelve_jpeg_base64():
    with TestClient(app) as c:
        sin = c.post("/predict", content=_jpeg(), headers=H).json()
        assert "imagen_anotada_base64" not in sin
        con = c.post("/predict", content=_jpeg(), headers=H | {"X-Anotar": "1"}).json()
        b64 = con["imagen_anotada_base64"]
        assert b64.startswith("data:image/jpeg;base64,")
        img = cv2.imdecode(np.frombuffer(base64.b64decode(b64.split(",", 1)[1]), np.uint8), cv2.IMREAD_COLOR)
        assert img is not None and img.shape[:2] == (240, 320)


def test_inferir_formato_anidado():
    with TestClient(app) as c:
        r = c.post("/inferir", content=_jpeg(), headers=H)
        assert r.status_code == 200
        j = r.json()
        assert j["placa"]["texto"] == "4047CKE" and j["placa"]["formato_bolivia"] is True
        assert set(j) >= {"placa", "vehiculo", "ejes", "vista_vehiculo", "keypoints", "pasajeros", "imagen_anotada_base64",
                          "latencia_ms"}
        assert isinstance(j["pasajeros"]["conteo"], int)
        assert c.post("/inferir", content=_jpeg(), headers={"Content-Type": "image/jpeg"}).status_code == 401


def test_inferir_placa():
    with TestClient(app) as c:
        j = c.post("/inferir-placa", content=_jpeg(), headers=H).json()
        assert j["placa"]["texto"] == "4047CKE" and "latencia_ms" in j


def test_ejes_null_si_no_se_pudieron_contar():
    plano = {"placa": "x", "placa_normalizada": None, "placa_valida": False, "ejes": 2, "ejes_fuente": "por_defecto",
             "detalles": {"ancho": 100, "alto": 100, "vista": "frontal/posterior",
                          "vehiculo": {"caja": [0, 0, 50, 50], "poligono": [[0, 0], [50, 0], [50, 50], [0, 50]]}}}
    j = formato_anidado(plano)
    assert j["ejes"] is None and j["ejes_estimados"] == 2
    assert j["vehiculo"]["area_mascara"] == 0.25
