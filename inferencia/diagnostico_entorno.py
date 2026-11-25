"""Evidencia de entorno reproducible (bono de gestion de entornos venv / conda / mamba).

Imprime y GUARDA en evidencias/evidencia_entorno_<etiqueta>.txt y .json:
SO, Python, ejecutable, entorno activo (venv/conda/mamba), PyTorch, CUDA, GPU, Ultralytics, ONNXRuntime, OpenCV,
EasyOCR, NumPy, FastAPI y la validacion de los pesos propios (placas_yolo.pt, vehiculos_seg.pt, llantas_pose.pt,
pasajeros_seg.pt) contra el contrato del servidor.

    python diagnostico_entorno.py --etiqueta runpod-venv
"""
from __future__ import annotations

import argparse
import datetime as dt
import importlib
import json
import os
import platform
import sys

BASE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, BASE)


def version(modulo: str) -> str:
    try:
        m = importlib.import_module(modulo)
        return str(getattr(m, "__version__", "instalado"))
    except Exception as e:  # noqa: BLE001
        return f"NO INSTALADO ({type(e).__name__})"


def tipo_entorno() -> str:
    if os.environ.get("CONDA_DEFAULT_ENV"):
        prefijo = os.environ.get("CONDA_PREFIX", "")
        gestor = "mamba" if "mamba" in prefijo.lower() or os.environ.get("MAMBA_EXE") else "conda"
        return f"{gestor}:{os.environ['CONDA_DEFAULT_ENV']}"
    if sys.prefix != getattr(sys, "base_prefix", sys.prefix):
        return f"venv:{sys.prefix}"
    return "sistema (sin entorno aislado)"


def recolectar() -> dict:
    d = {"fecha": dt.datetime.now().isoformat(timespec="seconds"), "so": platform.platform(),
         "python": platform.python_version(), "ejecutable": sys.executable, "entorno": tipo_entorno(),
         "runpod_pod_id": os.environ.get("RUNPOD_POD_ID")}
    try:
        import torch

        d.update(torch=torch.__version__, cuda_build=torch.version.cuda, cuda_disponible=torch.cuda.is_available())
        if torch.cuda.is_available():
            p = torch.cuda.get_device_properties(0)
            d.update(gpu=torch.cuda.get_device_name(0), gpu_memoria_gb=round(p.total_memory / 1e9, 1))
    except Exception as e:  # noqa: BLE001
        d.update(torch=f"NO INSTALADO ({type(e).__name__})", cuda_disponible=False)
    for nombre, mod in (("torchvision", "torchvision"), ("ultralytics", "ultralytics"), ("onnxruntime", "onnxruntime"),
                        ("opencv", "cv2"), ("easyocr", "easyocr"), ("numpy", "numpy"), ("fastapi", "fastapi"),
                        ("uvicorn", "uvicorn"), ("clip", "clip")):
        d[nombre] = version(mod)

    pesos = {}
    try:
        from types import SimpleNamespace

        from app import config
        from app.validacion import kpt_shape_de, validar_llantas, validar_pasajeros, validar_placas, validar_vehiculos

        d["inference_mode"] = config.MODO
        for clave, ruta, validar in (("M1_placas", config.PESOS_PLACAS, validar_placas),
                                     ("M2_vehiculos", config.PESOS_VEHICULOS, validar_vehiculos),
                                     ("M3_llantas", config.PESOS_LLANTAS, validar_llantas),
                                     ("M5_pasajeros", config.PESOS_PASAJEROS, validar_pasajeros)):
            if not os.path.exists(ruta):
                pesos[clave] = {"archivo": os.path.basename(ruta), "estado": "no encontrado (respaldo)"}
                continue
            try:
                from ultralytics import YOLO

                m = YOLO(ruta)
                info = validar(SimpleNamespace(task=m.task, names=m.names, kpt_shape=kpt_shape_de(m)))
                pesos[clave] = {"archivo": os.path.basename(ruta), "estado": "OK", "task": m.task,
                                "detalle": json.loads(json.dumps(info, default=str))}
            except ImportError:
                pesos[clave] = {"archivo": os.path.basename(ruta), "estado": "encontrado (instala ultralytics para validarlo)"}
            except Exception as e:  # noqa: BLE001
                pesos[clave] = {"archivo": os.path.basename(ruta), "estado": f"NO ENCAJA: {e}"}
    except Exception as e:  # noqa: BLE001
        pesos["error"] = str(e)
    d["pesos"] = pesos
    return d


def main():
    p = argparse.ArgumentParser()
    p.add_argument("--etiqueta", default="local", help="p. ej. runpod-venv, runpod-conda, runpod-mamba, colab")
    p.add_argument("--dir", default=os.path.join(BASE, "evidencias"))
    a = p.parse_args()
    d = recolectar()
    d["etiqueta"] = a.etiqueta
    lineas = ["=" * 64, f" Evidencia de entorno: {a.etiqueta}", "=" * 64]
    for k, v in d.items():
        if k != "pesos":
            lineas.append(f"{k:<16}: {v}")
    lineas.append("-" * 64)
    for k, v in d["pesos"].items():
        lineas.append(f"{k:<16}: {v.get('archivo', '') if isinstance(v, dict) else ''} -> "
                      f"{v.get('estado') if isinstance(v, dict) else v}")
    lineas.append("=" * 64)
    texto = "\n".join(lineas)
    print(texto)
    os.makedirs(a.dir, exist_ok=True)
    base = os.path.join(a.dir, f"evidencia_entorno_{a.etiqueta}")
    open(base + ".txt", "w", encoding="utf-8").write(texto + "\n")
    json.dump(d, open(base + ".json", "w", encoding="utf-8"), indent=2, ensure_ascii=False, default=str)
    print(f"Guardado: {base}.txt y {base}.json")


if __name__ == "__main__":
    main()
