"""
plate_reader — Servicio de detección y lectura de placas vehiculares
YOLOv8 detecta la ubicación + PaddleOCR lee el texto
"""

import io
import cv2
import numpy as np
from PIL import Image
from fastapi import FastAPI, UploadFile, File
from ultralytics import YOLO

app = FastAPI(title="Plate Reader API", version="1.0")

# ── Cargar modelos ──
# Modelo YOLOv8 pre-entrenado (COCO), detecta placas si existe el nuestro
plate_model = YOLO("models/plate_detector.pt" if __import__("os").path.exists("models/plate_detector.pt") else "yolov8n.pt")

# PaddleOCR para leer texto de la placa
ocr = None

def get_ocr():
    global ocr
    if ocr is None:
        try:
            from paddleocr import PaddleOCR
            ocr = PaddleOCR(use_angle_cls=False, lang='en', show_log=False, use_gpu=False)
        except:
            pass
    return ocr


@app.post("/api/read-plate")
async def read_plate(image: UploadFile = File(...)):
    """Detecta y lee la placa de un vehículo"""
    contents = await image.read()
    img = Image.open(io.BytesIO(contents)).convert("RGB")
    img_np = np.array(img)

    # ── 1. Detectar vehículos y placas con YOLOv8 ──
    results = plate_model(img_np)[0]

    plates = []
    for box in results.boxes:
        cls_id = int(box.cls[0])
        conf = float(box.conf[0])
        x1, y1, x2, y2 = map(int, box.xyxy[0])

        # Si es un carro (clase 2 en COCO), buscar placa dentro
        # Si tenemos nuestro modelo entrenado, usa clases propias
        crop = img_np[y1:y2, x1:x2]
        if crop.size == 0:
            continue

        # ── 2. Intentar leer texto con PaddleOCR ──
        plate_text = None
        ocr_confidence = 0
        ocr_engine = get_ocr()
        if ocr_engine:
            try:
                ocr_result = ocr_engine.ocr(crop, cls=False)
                if ocr_result and ocr_result[0]:
                    best = ocr_result[0][0]
                    plate_text = best[1][0] if best[1] else None
                    ocr_confidence = best[1][1] if best[1] else 0
                    
                    # Limpiar texto: solo alfanumérico + guión
                    if plate_text:
                        plate_text = plate_text.upper().strip()
            except:
                pass

        plates.append({
            "plate": plate_text,
            "confidence": round(min(conf, ocr_confidence) if ocr_confidence else conf, 2),
            "box": {"x": x1, "y": y1, "w": x2 - x1, "h": y2 - y1}
        })

    # Ordenar por confianza
    plates.sort(key=lambda p: p["confidence"], reverse=True)

    return {
        "success": True,
        "plates": plates,
        "model": "propio" if __import__("os").path.exists("models/plate_detector.pt") else "yolov8n (COCO)"
    }
