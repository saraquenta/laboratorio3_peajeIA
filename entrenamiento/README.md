# /entrenamiento — datos (Roboflow) y entrenamiento en Google Colab / RunPod

| # | Notebook | Modelo | Salida |
|---|---|---|---|
| 01 | [`01_preparacion_datos_roboflow.ipynb`](01_preparacion_datos_roboflow.ipynb) | Extracción de frames + auto-etiquetado YOLOE → zips para Roboflow | `dataset_vehiculos.zip`, `dataset_llantas.zip`, `dataset_placas.zip` |
| 02 | [`02_M2_segmentacion_vehiculos.ipynb`](02_M2_segmentacion_vehiculos.ipynb) | **M2** YOLO11-seg, 5 clases (auto, bus, camion, minibus, moto) | `vehiculos_seg.pt` |
| 03 | [`03_M3_keypoints_llantas_ejes.ipynb`](03_M3_keypoints_llantas_ejes.ipynb) | **M3** YOLO-pose, 5 keypoints por llanta → ejes | `llantas_pose.pt` |
| 04 | [`04_M1_placas_ocr.ipynb`](04_M1_placas_ocr.ipynb) | **M1** detector de placas + EasyOCR + normalización boliviana | `placas_yolo.pt` |
| 05 | [`05_M5_pasajeros_segmentacion.ipynb`](05_M5_pasajeros_segmentacion.ipynb) | **M5 (extra)** YOLO11-seg propio: personas (+ vehículos) → pasajeros dentro del vehículo | `pasajeros_seg.pt` (opcional) |
| 05b | [`05_extra_pasajeros.ipynb`](05_extra_pasajeros.ipynb) | Calibración de umbrales con el modelo COCO de respaldo (sin entrenar) | umbrales calibrados |

Cada notebook 02–05 (M5) termina con una celda **«Verificar contrato y exportar»** que comprueba el `.pt` igual que el
servidor (`inferencia/app/validacion.py`), lo descarga con su **nombre final** y lo copia a `MyDrive/peaje/pesos/`.

| Archivo | task | Clases / formato |
|---|---|---|
| `placas_yolo.pt` | detect | 1 clase (`single_cls=True`) |
| `vehiculos_seg.pt` | segment | `auto`, `bus`, `camion`, `minibus`, `moto` (por nombre) |
| `llantas_pose.pt` | pose | `llanta`, `kpt_shape [5, 3]` = `centro, contacto, superior, izquierda, derecha`, `flip_idx [0, 1, 2, 4, 3]` |
| `pasajeros_seg.pt` | segment | al menos una clase de persona (`persona`); si no existe el servidor usa `yolo11s-seg.pt` |

Copia los `.pt` a `inferencia/pesos/` (o al Pod). Sin ellos el servidor usa YOLOE zero-shot, así que el
sistema funciona antes de entrenar y mejora después.

## Flujo Roboflow

1. **Captura**: videos de 10–30 s por vehículo (lateral o 3/4 para ejes, frontal o trasera para placas), en distintas horas del día.
2. **Notebook 01**: extrae frames a 2 fps y genera etiquetas automáticas (YOLOE con prompts de texto).
3. **Roboflow** (<https://app.roboflow.com>): crea 3 proyectos
   - `peaje-vehiculos` → *Instance Segmentation* (clases `moto, auto, minibus, camion, bus`)
   - `peaje-llantas` → *Keypoint Detection* (clase `llanta`, esqueleto `centro, contacto, superior, izquierda, derecha` en ese orden)
   - `peaje-placas` → *Object Detection* (clase `placa`)
   Sube los zips, **corrige** etiquetas (Smart Polygon ayuda con las máscaras), genera una versión con
   split 70/20/10 y augmentations (flip horizontal *excepto en placas*, brillo ±25 %, blur ≤ 1 px).
4. **Exporta** cada versión en formato **YOLOv11** y anota `workspace`, `project` y `version`.
5. **Notebooks 02–04**: completa esos tres valores; la API key se lee de los *Secretos de Colab* (`ROBOFLOW_API_KEY`).

> Nunca escribas la API key de Roboflow en un notebook que se sube al repositorio.

## Entorno

- Colab: GPU T4, `ultralytics==8.4.218`, `roboflow==1.7.3`, `easyocr==1.7.2` (cada notebook los instala).
- RunPod: los mismos notebooks funcionan en JupyterLab de un Pod PyTorch (cambia `/content` por `/workspace`).
- Verifica versiones con `python ../inferencia/diagnostico.py`.

## Notebooks de referencia del docente

Los notebooks originales del curso (material del docente) se usaron como base y **no se publican** en este repositorio (están en `.gitignore`); se conservan sin modificar en la carpeta local del proyecto:

- `weekl_1_detectron2_v2.ipynb` — tareas de Detectron2 y API.
- `week_2_servidor_pose_runpod.ipynb` / `week_2_servidor_pose_colab_v2.ipynb` — patrón de servidor FastAPI + `X-API-Token` + proxy RunPod / túnel Cloudflare que sigue `/inferencia`.
- `week_3_segmentacion_vehiculos_yolo_detectron2_simplificado.ipynb` — base de M2 (clases auto/bus/minibús/camión y prompts YOLOE).
- `keypoints_llantas_yolo_detectron2_v3.ipynb` — base de M3 (5 keypoints por llanta y conteo de ejes).
