# 🚀 Sprint 1 — Plate Reader Propio (YOLOv8 + OCR)

**Fecha:** 2026-07-23
**Estado:** 🔄 En progreso

## Objetivo
Crear nuestro propio servicio de detección y lectura de placas vehiculares usando YOLOv8 + PaddleOCR. Reemplazar la dependencia de Plate Recognizer (free tier: 500 fotos/mes).

## ¿Qué vamos a hacer?

| Paso | Qué | Dónde |
|------|-----|-------|
| 1 | Crear contenedor `plate_reader` con FastAPI | `plate_reader/app.py` |
| 2 | Preparar notebook para entrenar detector de placas en Colab | `training/train_plate_detector.ipynb` |
| 3 | Entrenar YOLOv8 para encontrar la placa en la foto | En Colab con GPU (~2h) |
| 4 | Configurar PaddleOCR para leer el texto de la placa | Ya pre-entrenado |
| 5 | Subir modelo al VPS y activarlo | `plate_reader/models/plate_detector.pt` |
| 6 | Integrar en el pipeline (reemplazar Plate Recognizer) | `web/api/recognize.php` |

## Arquitectura

```
📁 plate_reader/
├── 📄 Dockerfile
├── 📄 requirements.txt
├── 📄 app.py                  ← API: /api/read-plate
├── 📁 models/
│   ├── 📄 plate_detector.pt   ← YOLOv8 (entrenado en Colab)
│   └── 📄 best.onnx           ← Versión ONNX para CPU
└── 📁 training/
    └── 📄 train_plate_detector.ipynb  ← Notebook Colab
```

## Plazo estimado
- **Hoy:** Crear estructura + Docker + API base
- **Mañana:** Subir dataset + notebook Colab listo para entrenar
- **Cuando entrenes (~2h):** Subir modelo al VPS y activar
