"""
vehicle_vision — Servicio de visión para reconocimiento vehicular
Expone APIs para detectar tipo de vehículo y color usando YOLOv8 + OpenCV
"""

import io
import cv2
import numpy as np
from PIL import Image
from fastapi import FastAPI, UploadFile, File
from ultralytics import YOLO

app = FastAPI(title="Vehicle Vision API", version="1.0")

# Cargar modelo YOLOv8 nano (pre-entrenado en COCO)
model = YOLO("yolov8n.pt")

# Mapeo de clases COCO a nuestros tipos
COCO_CAR = 2
COCO_MOTORCYCLE = 3
COCO_BUS = 5
COCO_TRUCK = 7

VEHICLE_CLASSES = {COCO_CAR, COCO_MOTORCYCLE, COCO_BUS, COCO_TRUCK}

def map_vehicle_type(coco_class_id: int, box_width: int, box_height: int) -> str:
    """Mapa clases COCO + heurística de dimensiones a tipo de vehículo"""
    aspect = box_width / box_height if box_height > 0 else 1

    if coco_class_id == COCO_MOTORCYCLE:
        return "Motorcycle"
    elif coco_class_id == COCO_BUS:
        return "Bus"
    elif coco_class_id == COCO_TRUCK:
        # Pickup vs Truck: pickup más proporcionado
        return "Pickup" if aspect < 1.8 else "Truck"
    elif coco_class_id == COCO_CAR:
        # Car vs SUV vs Van según altura/anchura
        if aspect > 1.5:  # Muy ancho → Van
            return "Van"
        elif aspect < 1.1:  # Casi cuadrado → SUV
            return "SUV"
        else:
            return "Car"
    return "Unknown"

def detect_color(vehicle_crop: np.ndarray) -> dict:
    """Detecta el color dominante del vehículo usando HSV"""
    hsv = cv2.cvtColor(vehicle_crop, cv2.COLOR_RGB2HSV)

    # Definir rangos de color
    color_ranges = {
        "Rojo": [(0, 50, 50), (10, 255, 255)],
        "Rojo2": [(170, 50, 50), (180, 255, 255)],  # Rojo envuelve en HSV
        "Naranja": [(11, 50, 50), (25, 255, 255)],
        "Amarillo": [(26, 50, 50), (35, 255, 255)],
        "Verde": [(36, 50, 50), (85, 255, 255)],
        "Azul": [(86, 50, 50), (125, 255, 255)],
        "Gris": [(0, 0, 40), (180, 40, 200)],
        "Negro": [(0, 0, 0), (180, 255, 50)],
        "Blanco": [(0, 0, 200), (180, 30, 255)],
        "Marron": [(10, 50, 50), (20, 255, 150)],
    }

    total_pixels = vehicle_crop.shape[0] * vehicle_crop.shape[1]
    best_color = "Desconocido"
    best_count = 0

    for color_name, (lower, upper) in color_ranges.items():
        # Saltar Rojo2 (es parte del rojo)
        if color_name == "Rojo2":
            continue
        mask = cv2.inRange(hsv, np.array(lower), np.array(upper))
        count = cv2.countNonZero(mask)

        # Sumar Rojo2 al Rojo
        if color_name == "Rojo":
            mask2 = cv2.inRange(hsv, np.array([170, 50, 50]), np.array([180, 255, 255]))
            count += cv2.countNonZero(mask2)

        if count > best_count:
            best_count = count
            best_color = color_name

    confidence = round(best_count / total_pixels, 2) if total_pixels > 0 else 0
    return {"color": best_color, "confidence": min(confidence, 0.99)}


@app.post("/api/detect")
async def detect(image: UploadFile = File(...)):
    """Analiza una imagen: detecta vehículo, tipo y color"""
    contents = await image.read()
    img = Image.open(io.BytesIO(contents)).convert("RGB")
    img_np = np.array(img)

    # Ejecutar YOLOv8
    results = model(img_np)[0]

    vehicles = []
    for box in results.boxes:
        cls_id = int(box.cls[0])
        conf = float(box.conf[0])

        if cls_id not in VEHICLE_CLASSES:
            continue

        x1, y1, x2, y2 = map(int, box.xyxy[0])
        w, h = x2 - x1, y2 - y1

        vehicle_type = map_vehicle_type(cls_id, w, h)

        # Recortar vehículo para análisis de color
        crop = img_np[y1:y2, x1:x2]
        if crop.size == 0:
            continue

        color_info = detect_color(crop)

        vehicles.append({
            "type": vehicle_type,
            "color": color_info["color"],
            "colorConfidence": color_info["confidence"],
            "detectionConfidence": round(conf, 2),
            "box": {"x": x1, "y": y1, "w": w, "h": h}
        })

    # Ordenar por confianza descendente
    vehicles.sort(key=lambda v: v["detectionConfidence"], reverse=True)

    return {"success": True, "vehicles": vehicles}
