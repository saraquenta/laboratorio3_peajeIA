# Pesos propios (no se suben a Git)

Copia aquí los `best.pt` que generan los notebooks de `/entrenamiento` (o súbelos al Pod en `/workspace/peaje/inferencia/pesos/`):

| Archivo | Notebook que lo genera | Variable para cambiar la ruta |
|---|---|---|
| `placas_yolo.pt` | `04_M1_placas_ocr.ipynb` | `PLACAS_WEIGHTS` |
| `vehiculos_seg.pt` | `02_M2_segmentacion_vehiculos.ipynb` | `VEHICULOS_WEIGHTS` |
| `llantas_pose.pt` | `03_M3_keypoints_llantas_ejes.ipynb` | `LLANTAS_WEIGHTS` |
| `pasajeros_seg.pt` (opcional) | `05_M5_pasajeros_segmentacion.ipynb` | `PASAJEROS_WEIGHTS` |

Si un archivo no existe, el servidor usa el método **zero-shot** (YOLOE con prompts de texto) para ese modelo (y `yolo11s-seg.pt` de COCO para pasajeros),
así que el sistema funciona desde el primer día y mejora al entrenar.
