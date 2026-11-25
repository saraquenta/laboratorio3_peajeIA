# Resumen técnico — Sistema Inteligente de Control de Peaje (IAM-811)

> Plantilla corta para rellenar con tus métricas reales (no reemplaza el video). Exportable a PDF si la subes a Drive.

**Autor(a):** ____ · **Modalidad:** individual + 4 puntos adicionales · **Fecha:** ____

## 1. Datos

| Modelo | Fuente (Roboflow / propio) | Imágenes train / valid / test | Clases |
|---|---|---|---|
| M1 placas (detección) | | | `placa` (1 clase) |
| M2 vehículos (segmentación) | | | `auto`, `bus`, `camion`, `minibus`, `moto` |
| M3 llantas (keypoints) | | | `llanta` · 5 kp: centro, contacto, superior, izquierda, derecha |
| M5 pasajeros (segmentación, extra) | | | `persona` (+ vehículos) |

Fotos propias de placas bolivianas: **19** (precargadas como primeras evidencias en la base de datos, 18/11/2026).

## 2. Métricas de entrenamiento (Colab T4)

| Modelo | Base | Épocas | imgsz | mAP50 | mAP50-95 | Otras |
|---|---|---|---|---|---|---|
| M1 `placas_yolo.pt` | yolo11s | 80 | 640 | **0.995** (caja) | **0.707** | Exactitud OCR: ___ % · CER: ___ |
| M2 `vehiculos_seg.pt` | yolo11s-seg | 40 | 640 | **0.861** caja / **0.859** máscara | **0.764** / **0.683** | Matriz de confusión: captura |
| M3 `llantas_pose.pt` | yolo11s-pose | 40 | 640 | **0.914** caja / **0.923** pose | **0.880** / **0.848** | Exactitud de ejes: ___ % · MAE: ___ |
| M5 `pasajeros_seg.pt` | yolo11s-seg | | 640 | | | MAE de conteo: ___ |

*(Los valores en negrita se leyeron de los `.pt` entregados; completa los demás con la salida de los notebooks.)*

## 3. Inferencia en producción (RunPod)

| Dato | Valor |
|---|---|
| GPU del Pod | |
| URL pública | `https://<POD_ID>-8000.proxy.runpod.net` |
| Latencia media `/predict` (ms) | |
| Entornos creados | venv ☐ · conda ☐ · mamba ☐ (evidencias en `evidencias/`) |

## 4. Pruebas de extremo a extremo

| Prueba | Resultado |
|---|---|
| Login Google (docente administrador) / cuenta no autorizada → Acceso denegado | |
| Paso registrado desde la app → panel actualizado sin recargar | |
| Placa no registrada → alerta + LED rojo + buzzer | |
| Agente: 4 estados + "¿Quisiste decir…?" | |
| Captura manual de vehículo sin placa → alerta `placa_ilegible` | |
| Pasajeros contados y guardados en `pasos.pasajeros` | |

## 5. Limitaciones y mejoras

- 
