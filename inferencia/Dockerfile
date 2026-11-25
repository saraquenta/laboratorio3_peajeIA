# Imagen para RunPod (Serverless/Pod con imagen propia) o cualquier servidor con GPU NVIDIA.
#   docker build -t peaje-inferencia ./inferencia
#   docker run --gpus all -p 8000:8000 -e API_TOKEN=un_token_largo peaje-inferencia
FROM pytorch/pytorch:2.6.0-cuda12.4-cudnn9-runtime

ENV PYTHONUNBUFFERED=1 PIP_NO_CACHE_DIR=1 DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y --no-install-recommends git libgl1 libglib2.0-0 curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY requirements.txt .
RUN pip install -r requirements.txt

COPY app ./app
COPY diagnostico.py diagnostico_entorno.py descargar_modelos.py setup_runpod.sh start_runpod.sh ./
COPY pesos ./pesos
# Descarga YOLOE, YOLO11-seg y EasyOCR dentro de la imagen para que el arranque sea rapido
RUN python descargar_modelos.py || echo "Descarga diferida al primer arranque"

EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=5s CMD curl -fs http://localhost:8000/health || exit 1
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000", "--workers", "1"]
