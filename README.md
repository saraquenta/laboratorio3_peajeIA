# Sistema Inteligente de Control de Peaje — IAM-811

**Machine Learning y Aprendizaje Profundo · Laboratorio Final de Hito** · entrega individual con los 4 puntos adicionales.

Una caseta de peaje que, con la cámara del celular, **lee placas bolivianas** (M1 detección + OCR), **clasifica el
vehículo** por segmentación de instancias (M2: auto, bus, camión, minibús, moto), **cuenta sus ejes** con keypoints
de llantas (M3) y **cuenta pasajeros** visibles (M5, sin reconocimiento facial). Cobra la tarifa del saldo del vehículo,
genera **alertas** de evasión, actualiza un **panel web en tiempo real**, responde consultas con un **agente
conversacional** que solo usa datos reales de la BD y avisa con un **ESP32** (LED azul/verde/rojo + buzzer).
**Todo corre en la nube**: nada depende de la PC.

```
 App Flutter (Google Sign-In) ──frames 2–5 fps──►  Web Laravel (subdominio HTTPS)  ──X-API-Token──►  FastAPI en RunPod (GPU)
 Modo Caseta / Modo Consulta                       API REST · BD · panel · agente                    M1 · M2 · M3 · M5
                                                     │      │            ▲
                    navegador (admin / operador) ◄───┘      │ Pusher     └── ESP32 (GET /hardware/ultimo-evento, HTTPS)
                                                            ▼
                                                     Gemini (redacción del agente, opcional)
```

Arquitectura completa: [`docs/arquitectura.md`](docs/arquitectura.md) · **Guía para dejarlo funcionando:
[`GUIA_PASO_A_PASO_POST_DESARROLLO.md`](GUIA_PASO_A_PASO_POST_DESARROLLO.md)**

## Enlaces de la entrega

| Recurso | Enlace |
|---|---|
| Repositorio (público) | https://github.com/saraquenta/laboratorio3_peajeIA |
| Panel web (subdominio HTTPS) | _completar_ |
| Servidor de inferencia (RunPod) | `https://<POD_ID>-8000.proxy.runpod.net/health` — _completar_ |
| Carpeta de Google Drive (video, APK, BD, capturas, datasets) | _completar_ |
| Datasets de Roboflow | _completar_ |

## Estructura

| Carpeta | Contenido |
|---|---|
| [`/entrenamiento`](entrenamiento/README.md) | Notebooks de Colab: 01 datos/Roboflow, 02 **M2** segmentación, 03 **M3** keypoints → ejes, 04 **M1** placas + OCR, 05 **M5** pasajeros |
| [`/inferencia`](inferencia/README.md) | FastAPI unificado (`/predict`, `/inferir`, `/inferir-placa`, `/health`), validación de pesos, entornos venv/conda/mamba, `setup_runpod.sh`, Dockerfile, notebooks RunPod/Colab, [guía RunPod](inferencia/GUIA_RUNPOD.md) |
| [`/web`](web/README.md) | Laravel 13: panel en tiempo real, CRUD, pasos con evidencia anotada, alertas, agente (página + burbuja), estado, API REST v1, Google OAuth + lista blanca, Dockerfile para Render |
| [`/movil`](movil/README.md) | Flutter: Google Sign-In, Modo Caseta (2–5 fps + captura manual), Modo Consulta (texto/foto) |
| [`/hardware`](hardware/README.md) | ESP32 de 3 LEDs + buzzer (`secrets.h.example`, diagrama), simulador de consola y variante Arduino |
| [`/docs`](docs) | [`openapi.yaml`](docs/openapi.yaml), [`postman_collection.json`](docs/postman_collection.json), [`arquitectura.md`](docs/arquitectura.md), [`despliegue.md`](docs/despliegue.md), [`guia_video.md`](docs/guia_video.md), [`plantilla_informe_tecnico.md`](docs/plantilla_informe_tecnico.md) |
| [`/recursos`](recursos/README.md) | Base de datos exportada (`.sql` + `.json`) |
| raíz | `README.md`, `.env.example`, `render.yaml`, guía paso a paso |

## Pesos entrenados (no se suben a Git: van a Drive y al Pod)

| Archivo | Modelo | task | Métricas (validación) |
|---|---|---|---|
| `placas_yolo.pt` | M1 YOLO11s | detect, 1 clase | mAP50 0.995 · mAP50-95 0.707 |
| `vehiculos_seg.pt` | M2 YOLO11s-seg | segment: auto, bus, camion, minibus, moto | mAP50 máscara 0.859 · mAP50-95 0.683 |
| `llantas_pose.pt` | M3 YOLO11s-pose | pose, `kpt_shape [5, 3]` | mAP50 pose 0.923 · mAP50-95 0.848 |
| `pasajeros_seg.pt` (opcional) | M5 YOLO11-seg | segment con clase persona | respaldo automático: `yolo11s-seg.pt` (COCO) |

Ruta en el Pod: `/workspace/peaje/inferencia/pesos/`. El servidor valida cada `.pt` al arrancar (`app/validacion.py`).

## Requisitos de la plataforma

| Requisito | Dónde |
|---|---|
| Nada en local; `INFERENCE_URL` intercambiable RunPod ↔ Colab | `web/config/peaje.php`, página **Estado** (cambia la URL sin redeploy) |
| JSON: `placa`, `placa_normalizada`, `confianza_ocr`, `categoria`, `ejes`, `pasajeros`, `latencia_ms` | `inferencia/app/main.py` → `POST /predict` |
| M1 placas bolivianas + OCR + normalización (`O↔0`, `I↔1`, `B↔8`, `S↔5`, `3↔8`) | `inferencia/app/modelos.py`, `normalizacion.py`, notebook 04 |
| M2 segmentación (≥ 3 categorías) + Roboflow + Colab | notebooks 01–02, tabla `tarifas` |
| M3 keypoints de llantas → ejes → tarifa por eje | `inferencia/app/ejes.py`, notebook 03 |
| BD con usuarios, casetas, tarifas, pasos (`pasajeros`, `imagen_url`), alertas; ≥ 20 vehículos; exportable | `web/database/`, `php artisan peaje:exportar-bd`, `/recursos` |
| Fotos de placas asignadas como primeras evidencias | 19 fotos reales en `web/database/seeders/evidencias/` |
| Panel en tiempo real sin recargar | `web/public/js/panel.js` (Pusher canal privado + sondeo de respaldo) |
| Google OAuth, roles, lista blanca, docente administrador | `AuthWebController`, `GoogleAuthService`, `DatabaseSeeder` |
| API REST mínima + documentación | `web/routes/api.php`, `docs/openapi.yaml`, `docs/postman_collection.json` |
| Agente: solo datos reales, 4 estados, "¿Quisiste decir…?", foto ilegible → pedir otra | `ConsultaPlacaService`, `AgenteService` (Gemini/Claude opcional) |
| App Flutter: Google Sign-In, Caseta 2–5 fps, Consulta + APK | `movil/` |
| **Extra** entornos venv / conda / mamba en producción | `inferencia/requirements-venv.txt`, `environment-conda.yml`, `environment-mamba.yml`, `setup_runpod.sh`, `diagnostico_entorno.py` |
| **Extra** inferencia en RunPod | `inferencia/GUIA_RUNPOD.md`, `runpod_servidor.ipynb` |
| **Extra** pasajeros por segmentación (sin rostros) | M5: notebook 05, `pasajeros_seg.pt`, `pasos.pasajeros` |
| **Extra** hardware de alertas | `hardware/esp32_alerta_peaje/` |

## Recrear los entornos (bono de entornos)

```bash
# venv (pip)
python3.11 -m venv peaje-venv && source peaje-venv/bin/activate
pip install -r inferencia/requirements-venv.txt
python inferencia/diagnostico_entorno.py --etiqueta venv

# conda
conda env create -f inferencia/environment-conda.yml && conda activate peaje-conda
python inferencia/diagnostico_entorno.py --etiqueta conda

# mamba
mamba env create -f inferencia/environment-mamba.yml && mamba activate peaje-mamba
python inferencia/diagnostico_entorno.py --etiqueta mamba

# En RunPod, los tres de una vez (deja evidencias en inferencia/evidencias/):
bash inferencia/setup_runpod.sh todos
```

| Servicio | Versiones fijadas |
|---|---|
| Inferencia | Python 3.11 · torch 2.6.0 (cu124) · `ultralytics==8.4.218` · `easyocr==1.7.2` · `opencv-python(-headless)==4.14.0.94` · `fastapi==0.165.0` |
| Entrenamiento | Colab T4 · `ultralytics==8.4.218` · `roboflow==1.7.3` |
| Web | PHP ≥ 8.3 · Laravel 13 · Sanctum 4 · Socialite 5 · `pusher-php-server` 7 (`web/composer.lock`) |
| Móvil | Flutter 3.50 · Dart 3.14 · `google_sign_in` 7.2 · `camera` 0.12 (`movil/pubspec.lock`) |

## Pruebas automáticas

```powershell
cd inferencia; .\.venv\Scripts\python -m pytest -q     # 41 pruebas
cd web; php artisan test                               # 47 pruebas
cd movil; flutter analyze; flutter test
```

## Seguridad y privacidad

- Login exclusivamente con Google; lista blanca en `usuarios` (`email_google`, `activo`); el docente es administrador.
- Secretos solo en `.env`, variables del Pod, Secretos de Colab y `secrets.h` (todos en `.gitignore`).
- Pasajeros: solo conteo y cajas rellenas opacas en la evidencia; sin reconocimiento facial ni recortes de rostros.
- Los notebooks del docente usados como referencia no se publican en este repositorio.
