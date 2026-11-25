#!/usr/bin/env bash
# Arranque del servidor de inferencia dentro de un Pod de RunPod (plantilla PyTorch).
# Uso (en la terminal web del Pod):
#   cd /workspace/peaje/inferencia
#   export API_TOKEN="pon_aqui_un_token_largo"     # el mismo que pondras en INFERENCE_TOKEN de la web
#   bash start_runpod.sh
set -euo pipefail
cd "$(dirname "$0")"

echo "== Instalando dependencias (solo tarda la primera vez) =="
apt-get update -qq && apt-get install -y -qq libgl1 libglib2.0-0 git > /dev/null || true
pip install -q -r requirements.txt

echo "== Diagnostico =="
python diagnostico.py

export PORT="${PORT:-8000}"
if [ -z "${API_TOKEN:-}" ]; then
  export API_TOKEN="$(python -c 'import secrets; print(secrets.token_urlsafe(24))')"
  echo "API_TOKEN no definido; se genero uno: ${API_TOKEN}"
fi
echo "${API_TOKEN}" > /workspace/peaje_api_token.txt 2>/dev/null || true

if [ -n "${RUNPOD_POD_ID:-}" ]; then
  echo "======================================================================"
  echo " INFERENCE_URL   = https://${RUNPOD_POD_ID}-${PORT}.proxy.runpod.net"
  echo " INFERENCE_TOKEN = ${API_TOKEN}"
  echo " (expone el puerto ${PORT} como HTTP en la configuracion del Pod)"
  echo "======================================================================"
fi

# nohup para que siga corriendo al cerrar la terminal web; log en /workspace/inferencia.log
nohup uvicorn app.main:app --host 0.0.0.0 --port "${PORT}" --workers 1 > /workspace/inferencia.log 2>&1 &
echo "Servidor iniciado (PID $!). Ver log:  tail -f /workspace/inferencia.log"
sleep 5
curl -s "http://localhost:${PORT}/health" || true
echo
