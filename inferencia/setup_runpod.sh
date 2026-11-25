#!/usr/bin/env bash
# =====================================================================================================
#  Entornos reproducibles en RunPod (bono "gestion de entornos venv / conda / mamba en produccion").
#
#  Uso (dentro del Pod, en /workspace/peaje/inferencia):
#     bash setup_runpod.sh venv      # crea /workspace/envs/peaje-venv con requirements-venv.txt
#     bash setup_runpod.sh conda     # crea el entorno peaje-conda con environment-conda.yml
#     bash setup_runpod.sh mamba     # crea el entorno peaje-mamba con environment-mamba.yml (mamba)
#     bash setup_runpod.sh todos     # los tres, uno tras otro (15-25 min)
#     bash setup_runpod.sh servidor venv|conda|mamba   # arranca uvicorn con ese entorno (puerto 8000)
#
#  Cada entorno deja su evidencia en evidencias/evidencia_entorno_runpod-<tipo>.txt y .json
#  (Python, torch, CUDA, GPU, ultralytics, onnxruntime, opencv y validacion de los .pt).
#  Conda y mamba se instalan con Miniforge en /workspace/miniforge3 (persiste en el volumen del Pod).
# =====================================================================================================
set -euo pipefail
cd "$(dirname "$0")"
ENVS=/workspace/envs
MF=/workspace/miniforge3
mkdir -p "$ENVS" evidencias

dependencias_sistema() {
  if ! dpkg -s libgl1 >/dev/null 2>&1; then
    apt-get update -qq && apt-get install -y -qq libgl1 libglib2.0-0 git curl > /dev/null || true
  fi
}

instalar_miniforge() {
  if [ ! -x "$MF/bin/conda" ]; then
    echo "== Instalando Miniforge (conda + mamba) en $MF =="
    curl -fsSL -o /tmp/miniforge.sh "https://github.com/conda-forge/miniforge/releases/latest/download/Miniforge3-Linux-x86_64.sh"
    bash /tmp/miniforge.sh -b -p "$MF"
  fi
  # shellcheck disable=SC1091
  source "$MF/etc/profile.d/conda.sh"
}

crear_venv() {
  echo "== [venv] creando $ENVS/peaje-venv =="
  python3 -m venv "$ENVS/peaje-venv"
  "$ENVS/peaje-venv/bin/pip" install -q --upgrade pip
  "$ENVS/peaje-venv/bin/pip" install -q -r requirements-venv.txt
  "$ENVS/peaje-venv/bin/python" diagnostico_entorno.py --etiqueta runpod-venv
}

crear_conda() {
  instalar_miniforge
  echo "== [conda] creando peaje-conda =="
  conda env remove -y -n peaje-conda >/dev/null 2>&1 || true
  conda env create -q -f environment-conda.yml
  conda run -n peaje-conda python diagnostico_entorno.py --etiqueta runpod-conda
}

crear_mamba() {
  instalar_miniforge
  echo "== [mamba] creando peaje-mamba =="
  "$MF/bin/mamba" env remove -y -n peaje-mamba >/dev/null 2>&1 || true
  "$MF/bin/mamba" env create -q -f environment-mamba.yml
  MAMBA_EXE="$MF/bin/mamba" "$MF/bin/mamba" run -n peaje-mamba python diagnostico_entorno.py --etiqueta runpod-mamba
}

servidor() {
  local tipo="${1:-venv}"
  export PORT="${PORT:-8000}"
  if [ -z "${API_TOKEN:-}" ]; then
    export API_TOKEN="$(python3 -c 'import secrets; print(secrets.token_urlsafe(24))')"
    echo "API_TOKEN no definido; se genero uno: ${API_TOKEN}"
  fi
  echo "======================================================================"
  echo " INFERENCE_URL   = https://${RUNPOD_POD_ID:-<POD_ID>}-${PORT}.proxy.runpod.net"
  echo " INFERENCE_TOKEN = ${API_TOKEN}"
  echo " Entorno         = ${tipo}"
  echo "======================================================================"
  case "$tipo" in
    venv)  exec "$ENVS/peaje-venv/bin/uvicorn" app.main:app --host 0.0.0.0 --port "$PORT" --workers 1 ;;
    conda) instalar_miniforge; exec conda run --no-capture-output -n peaje-conda uvicorn app.main:app --host 0.0.0.0 --port "$PORT" --workers 1 ;;
    mamba) instalar_miniforge; exec "$MF/bin/mamba" run -n peaje-mamba uvicorn app.main:app --host 0.0.0.0 --port "$PORT" --workers 1 ;;
    *) echo "Entorno desconocido: $tipo"; exit 1 ;;
  esac
}

dependencias_sistema
case "${1:-}" in
  venv)  crear_venv ;;
  conda) crear_conda ;;
  mamba) crear_mamba ;;
  todos) crear_venv; crear_conda; crear_mamba
         echo; echo "Evidencias:"; ls -1 evidencias/ ;;
  servidor) servidor "${2:-venv}" ;;
  *) sed -n '2,16p' "$0"; exit 1 ;;
esac
