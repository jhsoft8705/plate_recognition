# 🚀 Sprint 4 — Plate Reader Propio (YOLOv8 + OCR)

**Fecha:** 2026-07-23
**Estado:** 📋 Planificado

## Objetivo
Entrenar nuestro propio detector de placas con YOLOv8 + PaddleOCR para reemplazar Plate Recognizer (ahorro ~$50/mes).

## Actividades

| # | Actividad | Estado |
|---|-----------|--------|
| 1 | Crear contenedor `plate_reader` (Python + FastAPI) | ⏳ Pendiente |
| 2 | Preparar dataset para entrenamiento (CCRL / AOLP) | ⏳ Pendiente |
| 3 | Entrenar YOLOv8 para detección de placas en Colab | ⏳ Pendiente |
| 4 | Fine-tuning PaddleOCR para placas Perú (con guión) | ⏳ Pendiente |
| 5 | Subir modelo al VPS e integrar en el pipeline | ⏳ Pendiente |
| 6 | Reemplazar llamada a Plate Recognizer por el nuestro | ⏳ Pendiente |

## Arquitectura
```
plate_reader:5001/api/read-plate
  Input:  imagen.jpg
  Output: { plate: "ABC-111", confidence: 0.97, box: {...} }
```

## Dependencias
- YOLOv8 para detectar ubicación de la placa
- PaddleOCR para leer el texto
- Ambos corren en CPU en el VPS (~1-2s por foto)

## Objetivo final
Sistema 100% propio: Placa + Marca + Tipo + Color, sin depender de APIs externas.
