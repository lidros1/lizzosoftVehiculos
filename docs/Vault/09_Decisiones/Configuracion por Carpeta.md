# Decisión: Configuración Basada en Archivos PHP

## Contexto

[cite_start]Se decidió usar una estructura de carpetas en `clientes_config/` para cada cliente.

## Razón

1. **Rendimiento:** Cargar un archivo PHP con constantes es más rápido que realizar múltiples consultas a la base de datos para cada etiqueta de la interfaz.

2. **Personalización Extrema:** Permite subir archivos de assets (logos, CSS específico) por cliente de forma aislada.