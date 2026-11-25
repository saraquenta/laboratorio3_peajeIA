"""Configuracion del servidor de inferencia (todo por variables de entorno)."""
from __future__ import annotations

import os
import secrets
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent
DIR_PESOS = Path(os.environ.get("DIR_PESOS", BASE / "pesos"))


def _ruta(var: str, archivo: str) -> str:
    """Ruta a pesos propios: variable de entorno o archivo por defecto dentro de /pesos."""
    return os.environ.get(var) or str(DIR_PESOS / archivo)


PUERTO = int(os.environ.get("PORT", os.environ.get("PUERTO", "8000")))

# Token que el backend web envia en la cabecera X-API-Token (igual que en los notebooks del curso).
# Si no se define se genera uno aleatorio y se imprime al iniciar (copiarlo a INFERENCE_TOKEN en la web).
API_TOKEN = os.environ.get("API_TOKEN") or secrets.token_urlsafe(16)
TOKEN_GENERADO = "API_TOKEN" not in os.environ

# "real" carga los modelos; "mock" responde datos simulados (solo para probar la integracion sin GPU)
MODO = os.environ.get("INFERENCE_MODE", "real").lower()

# --- Pesos propios entrenados en /entrenamiento (si no existen se usa el metodo zero-shot YOLOE) ---
PESOS_PLACAS = _ruta("PLACAS_WEIGHTS", "placas_yolo.pt")            # M1: detector de placas
PESOS_VEHICULOS = _ruta("VEHICULOS_WEIGHTS", "vehiculos_seg.pt")    # M2: YOLO-seg 5 clases
PESOS_LLANTAS = _ruta("LLANTAS_WEIGHTS", "llantas_pose.pt")         # M3: YOLO-pose 5 keypoints
PESOS_YOLOE = os.environ.get("YOLOE_WEIGHTS", "yoloe-11l-seg.pt")    # zero-shot (se descarga solo)
PESOS_PASAJEROS = _ruta("PASAJEROS_WEIGHTS", "pasajeros_seg.pt")    # M5: YOLO11-seg propio (person)
PESOS_PERSONAS = os.environ.get("PERSONAS_WEIGHTS", "yolo11s-seg.pt")  # respaldo M5: COCO person (se descarga solo)

# --- Umbrales ---
CONF_VEHICULO = float(os.environ.get("CONF_VEHICULO", "0.30"))
CONF_LLANTA = float(os.environ.get("CONF_LLANTA", "0.15"))
CONF_PLACA = float(os.environ.get("CONF_PLACA", "0.20"))
CONF_PERSONA = float(os.environ.get("CONF_PERSONA", "0.35"))
IMGSZ = int(os.environ.get("IMGSZ", "960"))                 # YOLOE zero-shot (llantas y placas pequenas)
IMGSZ_PROPIO = int(os.environ.get("IMGSZ_PROPIO", "640"))   # = imgsz de entrenamiento de los notebooks 02-04
CONF_LLANTA_PROPIO = float(os.environ.get("CONF_LLANTA_PROPIO", "0.30"))
OCR_IDIOMAS = os.environ.get("OCR_IDIOMAS", "en").split(",")

# Tamano maximo del lado mayor de la imagen recibida (se reduce antes de inferir)
MAX_LADO = int(os.environ.get("MAX_LADO", "1600"))

POD_ID = os.environ.get("RUNPOD_POD_ID")
URL_PUBLICA = f"https://{POD_ID}-{PUERTO}.proxy.runpod.net" if POD_ID else os.environ.get("URL_PUBLICA", "")
