# Guía para grabar el video (8–12 min, sin informe PDF)

> El guion detallado y actualizado está en [`GUIA_PASO_A_PASO_POST_DESARROLLO.md`](../GUIA_PASO_A_PASO_POST_DESARROLLO.md) (Paso 10). Esta tabla es el resumen.

**Preparación (antes de grabar):** Pod de RunPod encendido con `/health` en `ok`, web publicada, sesión de Google
abierta, app instalada, 3–4 vehículos reales o fotos impresas (uno registrado con saldo, uno sin saldo, uno no
registrado, un camión de 3+ ejes), ESP32 conectado. Pantalla dividida: panel web + teléfono (scrcpy o cámara).

| Min | Escena | Qué mostrar / decir |
|---|---|---|
| 0:00–0:45 | **Presentación** | Problema (peaje manual, evasión), objetivo y equipo. Diagrama de `docs/arquitectura.md`. Regla "nada en local". |
| 0:45–2:30 | **Datos y entrenamiento** | Notebook 01 (frames + auto-etiquetado YOLOE) → proyecto Roboflow corregido → notebooks 02 (M2 seg, mAP y matriz de confusión), 03 (M3 keypoints de llantas → ejes), 04 (M1 placa + OCR, exactitud/CER). |
| 2:30–3:45 | **Inferencia en RunPod** | Pod creado a mano, `runpod_servidor.ipynb`: diagnóstico (Python, torch, CUDA, ultralytics), arranque, `GET /health`, `POST /predict` con una imagen y el JSON (`placa`, `placa_normalizada`, `confianza_ocr`, `categoria`, `ejes`, `pasajeros`, `latencia_ms`). Imagen anotada de `/predict/visual`. |
| 3:45–4:45 | **Login y roles** | `https://subdominio` (candado HTTPS) → *Ingresar con Google* como docente (administrador). Correo no autorizado → *Acceso denegado*. Lista blanca y rol operador. |
| 4:45–6:30 | **Modo Caseta (en vivo)** | App → Modo Caseta 2–5 fps. Pasa el auto registrado: placa, categoría, ejes, pasajeros, tarifa, estado verde. En paralelo el **panel** suma pasos/recaudación sin recargar y el **LED verde** se enciende. |
| 6:30–7:30 | **Alertas** | Vehículo no registrado → alerta "posible evasión", **LED rojo + buzzer**, alerta en el panel. Vehículo sin saldo → `saldo_insuficiente`. Gestionar la alerta (Atender). |
| 7:30–8:15 | **Ejes y tarifas** | Camión de 3+ ejes: keypoints de llantas, ejes detectados y tarifa con eje adicional. Página Tarifas. |
| 8:15–9:30 | **Agente** | Burbuja de chat y Modo Consulta: `4047CKE` (Registrada y con paso), placa sin pasos (Registrada sin paso), `5751PDE` (Identificada no registrada), placa inexistente (No existe), error de OCR `2874A3G` (3↔B) y `4047CKF` y "¿Quisiste decir…?", foto borrosa → pide otra foto. |
| 9:30–10:30 | **Web y API** | CRUD de vehículos, historial de pasos con evidencia, página Estado, `docs/openapi.yaml` en Swagger Editor y una llamada con Postman. |
| 10:30–11:30 | **Extras y cierre** | `requirements-venv.txt`, `environment-conda.yml`, `environment-mamba.yml` y evidencias de `diagnostico_entorno.py`, conteo de pasajeros (sin rostros), hardware, limitaciones y mejoras. |

**Consejos:** graba en 1080p; ensaya una vez; muestra la URL pública del Pod en pantalla; si la red falla, ten un
video corto de respaldo de la caseta; apaga el Pod al terminar.
