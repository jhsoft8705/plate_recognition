"""
vehicle_vision — Servicio de visión para reconocimiento vehicular
YOLOv8 + OpenCV: tipo, color, marca (ONNX) y placa (detector)
"""

import io
import cv2
import numpy as np
from PIL import Image
from fastapi import FastAPI, UploadFile, File
from ultralytics import YOLO
import os

app = FastAPI(title="Vehicle Vision API", version="2.0")

# ── Modelos ──
detection_model = YOLO("yolov8n.pt")  # YOLOv8 base (COCO)

# Marca (ONNX, entrenado en Colab)
brand_model_path = "models/marca_modelo.onnx"
brand_model = YOLO(brand_model_path) if os.path.exists(brand_model_path) else None

# Placa (detector entrenado en Colab)
plate_model_path = "models/plate_detector.pt"
plate_model = YOLO(plate_model_path) if os.path.exists(plate_model_path) else None

# Clases COCO para vehículos
COCO_CAR = 2
COCO_MOTORCYCLE = 3
COCO_BUS = 5
COCO_TRUCK = 7
VEHICLE_IDS = {COCO_CAR, COCO_MOTORCYCLE, COCO_BUS, COCO_TRUCK}


# ── Endpoints ──

@app.post("/api/detect")
async def detect_vehicle(image: UploadFile = File(...)):
    """Analiza un vehículo: tipo, color, marca y placa"""
    contents = await image.read()
    img = Image.open(io.BytesIO(contents)).convert("RGB")
    img_np = np.array(img)

    results = detection_model(img_np)[0]
    vehicles = []

    for box in results.boxes:
        cls_id = int(box.cls[0])
        conf = float(box.conf[0])

        if cls_id not in VEHICLE_IDS:
            continue

        x1, y1, x2, y2 = map(int, box.xyxy[0])
        w, h = x2 - x1, y2 - y1
        crop = img_np[y1:y2, x1:x2]
        if crop.size == 0:
            continue

        # Tipo
        vehicle_type = map_type(cls_id, w, h)

        # Color
        color_info = detect_color(crop)

        # Marca (si tenemos el modelo)
        brand = detect_brand(crop)

        vehicle = {
            "type": vehicle_type,
            "color": color_info["color"],
            "colorConfidence": round(color_info["confidence"], 2),
            "detectionConfidence": round(conf, 2),
            "brand": brand["brand"],
            "brandConfidence": brand["confidence"],
            "box": {"x": x1, "y": y1, "w": w, "h": h}
        }
        vehicles.append(vehicle)

    # Placa (endpoint separado)
    plate_result = {"plate": None, "confidence": 0, "ocr_pending": True}

    vehicles.sort(key=lambda v: v["detectionConfidence"], reverse=True)
    
    return {
        "success": True,
        "vehicles": vehicles,
        "plate": plate_result,
        "models": {
            "brand": brand_model is not None,
            "plate_detector": plate_model is not None
        }
    }


@app.post("/api/read-plate")
async def read_plate(image: UploadFile = File(...)):
    """Detecta y lee la placa (endpoint separado)"""
    contents = await image.read()
    img = Image.open(io.BytesIO(contents)).convert("RGB")
    img_np = np.array(img)

    # Usar el detector de placas si existe, si no YOLOv8 COCO
    model = plate_model if plate_model else detection_model
    results = model(img_np)[0]

    plates = []
    for box in results.boxes:
        conf = float(box.conf[0])
        x1, y1, x2, y2 = map(int, box.xyxy[0])
        plates.append({
            "plate": None,
            "confidence": round(conf, 2),
            "box": {"x": x1, "y": y1, "w": x2 - x1, "h": y2 - y1}
        })

    plates.sort(key=lambda p: p["confidence"], reverse=True)

    return {
        "success": True,
        "plates": plates,
        "model": "plate_detector" if plate_model else "yolov8n (COCO)"
    }


# ── Helpers ──

def map_type(cls_id: int, w: int, h: int) -> str:
    aspect = w / h if h > 0 else 1
    if cls_id == COCO_MOTORCYCLE: return "Motorcycle"
    if cls_id == COCO_BUS: return "Bus"
    if cls_id == COCO_TRUCK: return "Pickup" if aspect < 1.8 else "Truck"
    if cls_id == COCO_CAR:
        if aspect > 1.5: return "Van"
        if aspect < 1.1: return "SUV"
        return "Car"
    return "Unknown"


def detect_color(crop: np.ndarray) -> dict:
    import cv2
    hsv = cv2.cvtColor(crop, cv2.COLOR_RGB2HSV)
    ranges = {
        "Rojo":    [(0, 50, 50), (10, 255, 255)],
        "Naranja": [(11, 50, 50), (25, 255, 255)],
        "Amarillo":[(26, 50, 50), (35, 255, 255)],
        "Verde":   [(36, 50, 50), (85, 255, 255)],
        "Azul":    [(86, 50, 50), (125, 255, 255)],
        "Gris":    [(0, 0, 40), (180, 40, 200)],
        "Negro":   [(0, 0, 0), (180, 255, 50)],
        "Blanco":  [(0, 0, 200), (180, 30, 255)],
    }
    total = crop.shape[0] * crop.shape[1]
    best_color, best_count = "Desconocido", 0
    for name, (lower, upper) in ranges.items():
        mask = cv2.inRange(hsv, np.array(lower), np.array(upper))
        count = cv2.countNonZero(mask)
        if name == "Rojo":
            mask2 = cv2.inRange(hsv, np.array([170, 50, 50]), np.array([180, 255, 255]))
            count += cv2.countNonZero(mask2)
        if count > best_count:
            best_count, best_color = count, name
    return {"color": best_color, "confidence": min(best_count / total, 0.99)}


def detect_brand(crop: np.ndarray) -> dict:
    if not brand_model:
        return {"brand": None, "confidence": 0}
    try:
        results = brand_model(crop, verbose=False)
        probs = results[0].probs
        top1 = probs.top1
        conf = float(probs.top1conf)
        return {
            "brand": results[0].names[top1],
            "confidence": round(conf, 2)
        }
    except:
        return {"brand": None, "confidence": 0}
