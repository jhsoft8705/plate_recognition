FROM python:3.11-slim

WORKDIR /app

# Instalar dependencias del sistema para OpenCV
RUN apt-get update && apt-get install -y \
    libgl1 \
    libglib2.0-0 \
    wget \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .

# Instalar CPU-only PyTorch primero (sin CUDA, ahorra ~4GB)
RUN pip install --no-cache-dir torch==2.5.1 --index-url https://download.pytorch.org/whl/cpu && \
    pip install --no-cache-dir torchvision==0.20.1 --index-url https://download.pytorch.org/whl/cpu

# Luego el resto de dependencias
RUN pip install --no-cache-dir -r requirements.txt

# Descargar modelo YOLOv8 nano
RUN python3 -c "from ultralytics import YOLO; YOLO('yolov8n.pt')"

COPY . .

EXPOSE 5000

CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "5000"]
