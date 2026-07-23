# 🚀 Sprint 2 — Vehicle Vision: Tipo + Color (YOLOv8 + OpenCV)

**Fecha:** 2026-07-22
**Estado:** ✅ Completado

## Objetivo
Crear servicio propio de visión artificial que detecte tipo de vehículo y color, sin depender de APIs externas.

## Actividades

| # | Actividad | Estado |
|---|-----------|--------|
| 1 | Crear contenedor Python con FastAPI + YOLOv8 | ✅ |
| 2 | Detección de tipo: Car, SUV, Pickup, Moto, Bus, Truck | ✅ |
| 3 | Detección de color con HSV: Rojo, Azul, Verde, Blanco, Negro, etc. | ✅ |
| 4 | Integrar con PHP (recognize.php llama a vehicle_vision) | ✅ |
| 5 | Optimizar Docker: CPU-only PyTorch (~200MB vs ~4GB) | ✅ |

## Entregables
- **API interna:** `vehicle_vision:5000/api/detect` → tipo + color
- **Tipos detectados:** Car, SUV, Pickup, Motorcycle, Bus, Truck, Van
- **Colores detectados:** 10 colores (Rojo, Azul, Verde, Amarillo, etc.)
- **Velocidad:** ~100ms por foto en CPU

## Próximo Sprint
Entrenar modelo de Marca (49 marcas) con Stanford Cars + YOLOv8cls
