# 🚗 Plate Recognition — Sistema de Reconocimiento Vehicular

Sistema completo de reconocimiento vehicular con placa, marca, tipo y color. Todo corriendo en el VPS, sin depender de APIs externas de paga.

## 🏗️ Arquitectura

```
📁 plate_recognition/
│
├── 📄 docker-compose.yml          ← Orquesta servicios
├── 📁 web/                        ← PHP 8.2 (Puerto 8091)
│   ├── 📄 index.php               ← Frontend
│   └── 📁 api/
│       └── 📄 recognize.php       ← Orquestador
│
├── 📁 vehicle_vision/             ← Python (Puerto 5000)
│   ├── 📄 app.py                  → /api/detect (tipo + color)
│   └── 📁 models/
│       ├── 📄 yolov8n.pt          ← YOLOv8 (carga al inicio)
│       └── 📄 marca_modelo.onnx   ← Marca (entrenado en Colab)
│
├── 📁 plate_reader/               ← Python (Puerto 5001) 🆕
│   ├── 📄 app.py                  → /api/read-plate
│   └── 📁 models/
│       └── 📄 plate_detector.pt   ← Placa (entrenado en Colab)
│
└── 📁 training/                   ← Notebooks Colab
    ├── 📄 train_make_model.ipynb  ← Marca vehicular
    └── 📄 train_plate_detector.ipynb  ← Placa
```

## 🚀 Servicios

| Servicio | Puerto | Tecnología | Función |
|----------|--------|------------|---------|
| **web** | 8091 | PHP 8.2 | Frontend + orquestador |
| **vehicle_vision** | 5000 | Python FastAPI | Tipo + Color + Marca |
| **plate_reader** | 5001 | Python FastAPI | Placa (OCR propio) |

## 📋 Pipeline completo

```
Foto del vehículo
    │
    ▼
web/recognize.php
    │
    ├──▶ vehicle_vision → Tipo (Car/SUV/Pickup)
    ├──▶ vehicle_vision → Color (Rojo/Azul/Verde)
    ├──▶ vehicle_vision → Marca (Toyota/Kia/Hyundai)
    └──▶ plate_reader   → Placa (ABC-111)
    │
    ▼
Respuesta JSON: { placa, marca, color, tipo }
```

## 📊 Estado del proyecto

| Sprint | Descripción | Estado |
|--------|-------------|--------|
| **Sprint 1** | Infraestructura base + Placa (API externa) | ✅ |
| **Sprint 2** | Vehicle Vision: Tipo + Color (YOLOv8 + OpenCV) | ✅ |
| **Sprint 3** | Marca vehicular (Stanford Cars + Colab) | 🔄 Entrenando |
| **Sprint 4** | Plate Reader propio (reemplazar API) | 📋 Planificado |

## 🚀 Cómo probar

```
http://2.25.128.81:8091
```

Sube una foto de un vehículo y obtén placa, marca, color y tipo.

## 🧠 Entrenamiento en Colab

Los notebooks están en `training/`:
1. **Marca:** `train_make_model.ipynb`
2. **Placa:** `train_plate_detector.ipynb` (próximamente)

## 📁 Sprints

Ver `docs/sprints/` para el detalle de cada sprint.
