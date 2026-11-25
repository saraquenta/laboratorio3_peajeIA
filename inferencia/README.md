# /inferencia — endpoint unificado (M1 + M2 + M3 + pasajeros)

Servidor FastAPI que recibe un frame y devuelve en un solo JSON la placa (OCR + normalización boliviana),
la categoría del vehículo (segmentación de instancias), el número de ejes (keypoints de llantas) y los
pasajeros visibles. Se ejecuta en **RunPod** o **Google Colab** (nunca en la PC local en producción).

- Guía manual paso a paso: **[GUIA_RUNPOD.md](GUIA_RUNPOD.md)**
- Notebooks: [`runpod_servidor.ipynb`](runpod_servidor.ipynb) · [`colab_servidor.ipynb`](colab_servidor.ipynb)

## Endpoints

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| GET | `/health` | — | estado (`ok` / `cargando` / `error`), GPU, versiones y modelos activos |
| POST | `/predict` | `X-API-Token` | imagen (cuerpo crudo JPEG/PNG, multipart `file` o JSON `imagen_base64`) → JSON |
| POST | `/predict/visual` | `X-API-Token` | igual, pero devuelve el JPEG anotado (para el video) |

## Arquitectura del pipeline

```
frame ─► (redimensiona a 1600 px máx.)
        ├─ YOLOE zero-shot (una sola pasada: vehículos + llantas + placa)  ← solo si falta algún peso propio
        ├─ M2  vehiculos_seg.pt (YOLO11-seg, 5 clases)  → vehículo principal (el de mayor área)
        ├─ M3  llantas_pose.pt (YOLO-pose, 5 kp)        → llantas → ejes (lado cercano, ruedas dobles = 1 eje)
        ├─ M1  placas_yolo.pt → recorte → EasyOCR (CLAHE + Otsu) → normalización boliviana (O↔0, I↔1, B↔8, S↔5)
        └─ Extra yolo11s-seg (person) → personas dentro de la caja del vehículo (sin recortes de rostro)
```

## Entorno y pruebas locales (sin GPU)

```powershell
cd inferencia
python -m venv .venv
.\.venv\Scripts\python -m pip install -r requirements-test.txt
.\.venv\Scripts\python -m pytest -q             # normalización, ejes y contrato JSON (modo mock)
.\.venv\Scripts\python diagnostico.py
```

`INFERENCE_MODE=mock` responde datos simulados sin cargar modelos (la cabecera `X-Mock-Placa` fuerza una placa).
Sirve únicamente para probar la integración; la demo usa `INFERENCE_MODE=real` en RunPod/Colab.

## Variables de entorno

| Variable | Defecto | Uso |
|---|---|---|
| `API_TOKEN` | aleatorio | token de `X-API-Token` (= `INFERENCE_TOKEN` de la web) |
| `INFERENCE_MODE` | `real` | `real` o `mock` |
| `PLACAS_WEIGHTS`, `VEHICULOS_WEIGHTS`, `LLANTAS_WEIGHTS` | `pesos/*.pt` | pesos propios |
| `YOLOE_WEIGHTS` | `yoloe-11l-seg.pt` | modelo zero-shot |
| `PASAJEROS_WEIGHTS` | `pesos/pasajeros_seg.pt` | M5 pasajeros (propio) |
| `PERSONAS_WEIGHTS` | `yolo11s-seg.pt` | respaldo de pasajeros (COCO person) |
| `CONF_VEHICULO`, `CONF_LLANTA`, `CONF_PLACA`, `CONF_PERSONA` | 0.30 / 0.15 / 0.20 / 0.35 | umbrales |
| `IMGSZ` | 960 | resolución de YOLOE zero-shot |
| `IMGSZ_PROPIO` | 640 | resolución de los pesos propios (= `imgsz` de entrenamiento en los notebooks 02–04) |
| `CONF_LLANTA_PROPIO` | 0.30 | umbral de `llantas_pose.pt` |

## Contrato de los pesos propios

`app/validacion.py` lo comprueba al arrancar (y `python diagnostico.py` lo reporta):

| Archivo | task | Clases | Extra |
|---|---|---|---|
| `pesos/placas_yolo.pt` | detect | 1 (cualquier nombre) | OCR con EasyOCR en el servidor |
| `pesos/vehiculos_seg.pt` | segment | `auto`, `bus`, `camion`, `minibus`, `moto` (por nombre, orden libre) | variantes aceptadas: `Camión`, `van`, `trufi`… |
| `pesos/llantas_pose.pt` | pose | 1 (`llanta`) | `kpt_shape [5, 3]`: `centro, contacto, superior, izquierda, derecha`; `flip_idx [0, 1, 2, 4, 3]` |
| `pesos/pasajeros_seg.pt` (opcional) | segment | al menos 1 clase de persona; las demás se ignoran | respaldo: `yolo11s-seg.pt` (COCO person). `/predict` devuelve `pasajeros_fuente`: `propio` o `coco` |
