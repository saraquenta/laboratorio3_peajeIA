# Guía de ejecución manual en RunPod (y Colab)

> Esta guía la ejecutas **tú** desde el navegador. No se necesita ninguna API key de RunPod ni SSH:
> todo se hace en la consola web de RunPod y en JupyterLab del Pod.

## Qué se sube al Pod

Solo la carpeta `inferencia/`:

```
inferencia/
├── app/                  ← código del endpoint (main.py, modelos.py, normalizacion.py, ejes.py, config.py, visual.py)
├── pesos/                ← (opcional) placas_yolo.pt · vehiculos_seg.pt · llantas_pose.pt
├── requirements.txt      ← dependencias fijadas (torch ya viene en la plantilla)
├── diagnostico.py        ← versión de Python, torch, CUDA, ultralytics
├── start_runpod.sh       ← instala y arranca en segundo plano
├── runpod_servidor.ipynb ← lo mismo, paso a paso en JupyterLab
└── cliente_prueba.py     ← prueba el endpoint desde cualquier PC
```

## Opción A — Notebook (recomendada para la demo en vivo)

1. Entra a <https://www.runpod.io/console/pods> → **Deploy**.
2. Elige una GPU (RTX A4000 / A5000 / 4090 alcanzan de sobra) y la plantilla **RunPod PyTorch 2.x**.
3. En **Edit Template / Pod**:
   - *Expose HTTP Ports*: `8888,8000` (8888 = Jupyter, 8000 = nuestra API).
   - *Environment Variables* (opcional pero recomendado): `API_TOKEN = <un texto largo y aleatorio>`.
     Así el token no cambia si el Pod se reinicia.
   - *Volume Disk*: 20 GB en `/workspace` (guarda dependencias y pesos entre reinicios).
4. **Deploy** y espera a que el Pod esté *Running* → **Connect** → **Jupyter Lab**.
5. Sube el notebook `inferencia/runpod_servidor.ipynb` (botón ⬆ de JupyterLab).
6. En la celda 1 cambia `REPO_URL` por la URL de tu repositorio público.
   *Si no quieres usar git:* comprime la carpeta `inferencia/` como `inferencia.zip`, súbela a `/workspace`
   y usa la opción B de la celda 2.
7. Ejecuta todas las celdas en orden. La celda 6 imprime:
   ```
   INFERENCE_URL=https://<POD_ID>-8000.proxy.runpod.net
   INFERENCE_TOKEN=<token>
   ```
8. Copia esos dos valores al `.env` de la web (o a **Estado → Configurar inferencia** en el panel, que los guarda
   en la base de datos sin tocar el servidor web).
9. En el panel web, la página **Estado** debe mostrar *Inferencia: en línea* con la GPU y los modelos cargados.

## Opción B — Terminal web del Pod (sin notebook)

En **Connect → Start Web Terminal**:

```bash
cd /workspace
git clone --depth 1 https://github.com/saraquenta/laboratorio3_peajeIA.git peaje   # o sube y descomprime inferencia.zip
cd /workspace/peaje/inferencia
export API_TOKEN="pon_aqui_un_token_largo"
bash start_runpod.sh
tail -f /workspace/inferencia.log      # Ctrl+C para salir del log (el servidor sigue corriendo)
```

Para detenerlo: `pkill -f "uvicorn app.main:app"`.

## Opción D — Los 3 entornos (venv, conda, mamba) — bono de entornos

```bash
cd /workspace/peaje/inferencia
apt-get update && apt-get install -y tmux && tmux new -s entornos
bash setup_runpod.sh todos            # 15-25 min: crea peaje-venv, peaje-conda y peaje-mamba
ls evidencias/                        # evidencia_entorno_runpod-venv|conda|mamba .txt y .json
# Ctrl+B y luego D para salir sin cortar el proceso; volver: tmux attach -t entornos
```

Servidor de producción desde el entorno que prefieras (en otra sesión de tmux para que siga vivo):

```bash
tmux new -s peaje
export API_TOKEN="<tu token largo>"
bash setup_runpod.sh servidor venv     # o: conda | mamba
```

## Opción C — Imagen Docker propia

```bash
docker build -t TU_USUARIO_DOCKERHUB/peaje-inferencia:1.0 ./inferencia
docker push TU_USUARIO_DOCKERHUB/peaje-inferencia:1.0
```
En RunPod crea un *Custom Template* con esa imagen, puerto HTTP `8000` y la variable `API_TOKEN`.

## Probar el endpoint desde tu PC

```bash
# health (no requiere token)
curl https://<POD_ID>-8000.proxy.runpod.net/health

# inferencia
curl -X POST https://<POD_ID>-8000.proxy.runpod.net/predict \
     -H "X-API-Token: <token>" -H "Content-Type: image/jpeg" --data-binary @auto.jpg

# o con el script
python cliente_prueba.py --url https://<POD_ID>-8000.proxy.runpod.net --token <token> --imagen auto.jpg --visual anotada.jpg
```

Respuesta (resumida):

```json
{
  "placa": "1234-ABC", "placa_normalizada": "1234ABC", "placa_valida": true, "confianza_ocr": 0.93,
  "categoria": "camion", "confianza_categoria": 0.88, "ejes": 3, "ejes_fuente": "keypoints",
  "pasajeros": 1, "pasajeros_fuente": "propio", "latencia_ms": 142.7,
  "detalles": { "vehiculo": {"caja": [..], "poligono": [[..]]}, "placa_caja": [..], "llantas": [..], "pasajeros_cajas": [..] },
  "modelos": { "M1_placas": "...", "M2_vehiculos": "...", "M3_llantas": "...", "M5_pasajeros": "..." }
}
```

## Pesos propios

Los notebooks 02–04 de `/entrenamiento` ya exportan con el nombre final (y lo copian a `MyDrive/peaje/pesos/`).
Súbelos **sin renombrar** a `/workspace/peaje/inferencia/pesos/` (arrastrándolos en JupyterLab o con los enlaces
de Drive de la celda 5 del notebook):

| Archivo | Tipo | Debe cumplir |
|---|---|---|
| `placas_yolo.pt` | YOLO11 detect | 1 clase (se trata como `placa`) |
| `vehiculos_seg.pt` | YOLO11-seg | clases `auto`, `bus`, `camion`, `minibus`, `moto` (por nombre; se aceptan variantes como `Camión`) |
| `llantas_pose.pt` | YOLO-pose | `kpt_shape [5, 3]` en orden `centro, contacto, superior, izquierda, derecha` |
| `pasajeros_seg.pt` (opcional) | YOLO11-seg | al menos una clase de persona (`person`, `persona`, `pasajero`…); si falta se usa `yolo11s-seg.pt` |

Comprueba con `python diagnostico.py` (dice `OK` o `NO ENCAJA` y por qué) y reinicia el servidor
(`pkill -f "uvicorn app.main:app"` y vuelve a ejecutar la celda 6 o `bash start_runpod.sh`).
`GET /health` → `modelos` indica `propio:` o `zero-shot:` para cada uno. Si un `.pt` no encaja, el servidor
no arranca a medias: `/health` muestra `"estado": "error"` con el motivo.

## Google Colab (alternativa gratuita)

Abre `inferencia/colab_servidor.ipynb` en Colab con GPU T4, cambia `REPO_URL` y ejecuta todo. Imprime una URL
`https://xxxx.trycloudflare.com` (cambia en cada sesión) y el token. Pégalos en **Estado → Configurar inferencia**.

## Problemas frecuentes

| Síntoma | Causa / solución |
|---|---|
| `502 Bad Gateway` en la URL pública | El servidor no escucha en `0.0.0.0` o el puerto 8000 no está en *Expose HTTP Ports*. |
| `/health` → `"estado": "cargando"` | Descargando YOLOE / EasyOCR (1–3 min la primera vez). |
| `/health` → `"estado": "error"` | Revisa `tail -n 50 /workspace/inferencia.log` (normalmente falta `libgl1`: `apt-get install -y libgl1`). |
| `401 Token invalido` | `INFERENCE_TOKEN` de la web distinto al `API_TOKEN` del Pod. |
| Latencia alta (> 1 s) | GPU compartida o imágenes muy grandes: la app ya envía JPEG de 960 px; baja `IMGSZ=640`. |
| Se pierde todo al reiniciar | Usa *Volume Disk* en `/workspace` y define `API_TOKEN` como variable del Pod. |

**Recuerda detener (Stop) o eliminar (Terminate) el Pod al terminar para no gastar crédito.**
