"""Diagnostico del entorno: Python, torch, CUDA, GPU, ultralytics, easyocr, opencv y pesos disponibles.

    python diagnostico.py
"""
import importlib
import os
import platform
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))


def version(modulo):
    try:
        m = importlib.import_module(modulo)
        return getattr(m, "__version__", "instalado")
    except Exception as e:  # noqa: BLE001
        return f"NO INSTALADO ({type(e).__name__})"


print("=" * 60)
print("Python      :", platform.python_version(), "|", sys.executable)
print("SO          :", platform.platform())
print("torch       :", version("torch"))
try:
    import torch

    print("CUDA disp.  :", torch.cuda.is_available(), "| CUDA build:", torch.version.cuda)
    if torch.cuda.is_available():
        print("GPU         :", torch.cuda.get_device_name(0),
              f"({torch.cuda.get_device_properties(0).total_memory / 1e9:.1f} GB)")
except Exception:  # noqa: BLE001
    pass
for mod in ("torchvision", "ultralytics", "easyocr", "cv2", "numpy", "fastapi", "uvicorn", "clip"):
    print(f"{mod:<12}:", version(mod))

try:
    from app import config
    from app.validacion import kpt_shape_de, validar_llantas, validar_pasajeros, validar_placas, validar_vehiculos

    print("-" * 60)
    print("INFERENCE_MODE:", config.MODO)
    pesos = (("M1 placas", config.PESOS_PLACAS, validar_placas),
             ("M2 vehiculos", config.PESOS_VEHICULOS, validar_vehiculos),
             ("M3 llantas", config.PESOS_LLANTAS, validar_llantas),
             ("M5 pasajeros", config.PESOS_PASAJEROS, validar_pasajeros))
    for nombre, ruta, validar in pesos:
        if not os.path.exists(ruta):
            respaldo = config.PESOS_PERSONAS + " (COCO person)" if nombre.startswith("M5") else "zero-shot YOLOE"
            print(f"{nombre:<13}: {ruta} -> no encontrado -> se usara {respaldo}")
            continue
        try:
            from types import SimpleNamespace

            from ultralytics import YOLO

            m = YOLO(ruta)
            info = validar(SimpleNamespace(task=m.task, names=m.names, kpt_shape=kpt_shape_de(m)))
            print(f"{nombre:<13}: {ruta} -> OK task={m.task} {info}")
        except ImportError:
            print(f"{nombre:<13}: {ruta} -> encontrado (instala ultralytics para validarlo)")
        except Exception as e:  # noqa: BLE001
            print(f"{nombre:<13}: {ruta} -> NO ENCAJA: {e}")
    print("URL publica  :", config.URL_PUBLICA or "(no es un pod de RunPod)")
except Exception as e:  # noqa: BLE001
    print("No se pudo leer app/config.py:", e)
print("=" * 60)
