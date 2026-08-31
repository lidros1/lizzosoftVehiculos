# Flujo: Reclamos y Garantías

Registra la solicitud inicial del cliente (o garantía sobre un servicio previo) antes de abrir una orden formal.

## Reglas de negocio

- **Trazabilidad:** vincular reclamo a orden previa y `empresa_id` cuando aplique garantía.
- **Búsqueda:** modales solo muestran vehículos/órdenes de la empresa en sesión.
- **UI:** `HTML/menuReclamos.php` usa variables de `cliente_config` (colores y etiquetas), sin textos fijos.

## Pasos

1. Buscar cliente (AJAX).
2. Seleccionar vehículo.
3. Describir problema y guardar en estado pendiente.
4. Listar / editar reclamos.
5. Aprobar → [[Flujo Orden de Servicio]].

## Pantallas

| Pantalla | Archivo |
|----------|---------|
| Crear | `registrosServicios/crear_reclamo.php` |
| Listar | `registrosServicios/listar_reclamos.php` |
| Editar | `registrosServicios/editar_reclamo.php` |
| Menú | `HTML/menuReclamos.php` |

## Tareas pendientes

- [ ] Estados y transiciones del reclamo
- [ ] Conversión explícita reclamo → orden con FK
- [ ] Prepared statements en AJAX

## Estado

`Parcial`
