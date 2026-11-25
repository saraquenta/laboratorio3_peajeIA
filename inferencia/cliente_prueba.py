"""Envia una imagen al endpoint de inferencia y muestra el JSON (y opcionalmente guarda la imagen anotada).

    python cliente_prueba.py --url https://<POD_ID>-8000.proxy.runpod.net --token <API_TOKEN> --imagen auto.jpg
    python cliente_prueba.py --url ... --token ... --imagen auto.jpg --visual salida.jpg
"""
import argparse
import json
import sys

import requests

p = argparse.ArgumentParser()
p.add_argument("--url", required=True, help="INFERENCE_URL (sin / final)")
p.add_argument("--token", required=True, help="API_TOKEN del servidor")
p.add_argument("--imagen", required=True)
p.add_argument("--visual", help="ruta donde guardar la imagen anotada")
a = p.parse_args()

url = a.url.rstrip("/")
print("health:", requests.get(url + "/health", timeout=30).json())
datos = open(a.imagen, "rb").read()
h = {"X-API-Token": a.token, "Content-Type": "image/jpeg"}
r = requests.post(url + "/predict", data=datos, headers=h, timeout=120)
if r.status_code != 200:
    sys.exit(f"Error {r.status_code}: {r.text}")
print(json.dumps(r.json(), indent=2, ensure_ascii=False))
if a.visual:
    v = requests.post(url + "/predict/visual", data=datos, headers=h, timeout=120)
    open(a.visual, "wb").write(v.content)
    print("Imagen anotada guardada en", a.visual)
