"""Descarga previa de pesos (YOLOE, YOLO11-seg para pasajeros y modelos de EasyOCR)."""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from app import config  # noqa: E402

from ultralytics import YOLO, YOLOE  # noqa: E402

YOLOE(config.PESOS_YOLOE)
YOLO(config.PESOS_PERSONAS)
import easyocr  # noqa: E402

easyocr.Reader(config.OCR_IDIOMAS, gpu=False, verbose=False)
print("Modelos descargados")
