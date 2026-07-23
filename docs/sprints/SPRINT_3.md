# 🚀 Sprint 3 — Marca Vehicular (Stanford Cars + Colab)

**Fecha:** 2026-07-22
**Estado:** 🔄 En progreso (entrenando en Colab)

## Objetivo
Entrenar modelo de clasificación por MARCA (49 clases) usando Stanford Cars dataset y YOLOv8cls en Google Colab.

## Actividades

| # | Actividad | Estado |
|---|-----------|--------|
| 1 | Crear notebook Colab con HuggingFace (sin Kaggle) | ✅ |
| 2 | Agrupar Stanford Cars por marca (49 clases vs 196 modelos) | ✅ |
| 3 | Entrenar YOLOv8cls en Colab con GPU (Tesla T4) | 🔄 ~30-40 min |
| 4 | Exportar modelo a ONNX para CPU | ⏳ Pendiente |
| 5 | Subir modelo al VPS e integrar en Vehicle Vision | ⏳ Pendiente |
| 6 | Actualizar frontend para mostrar marca | ⏳ Pendiente |

## Marcas cubiertas (~49)
Toyota, Kia, Hyundai, Chevrolet, Nissan, Honda, Mazda, Suzuki, Mitsubishi, Volkswagen, Ford, BMW, Mercedes-Benz, Audi, Subaru, Jeep, Volvo, Lexus, BMW, y 30 más.

## Notebook
```
training/train_brand_classifier.ipynb
```
Disponible en: https://github.com/jhsoft8705/plate_recognition

## Próximo Sprint
Plate Reader propio: entrenar detector de placas con YOLOv8 para reemplazar Plate Recognizer
