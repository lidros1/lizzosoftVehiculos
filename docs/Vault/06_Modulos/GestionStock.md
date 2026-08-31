# Módulo: Gestión de Stock e Inventario

## Arquitectura de tablas

1. `productos` — catálogo maestro por `empresa_id`
2. `stock_sucursal` — cantidad por `sucursal_id` y `producto_id`
3. `movimientos_stock` — historial de entradas, salidas y transferencias

## Acceso en UI

- Área 9 → `HTML/gestionStock.php`
- Permisos: `funciones_stock_permitidas`

| Pantalla | Archivo |
|----------|---------|
| Productos | `GestionStock/administrarProductos.php` |
| Proveedores | `GestionStock/gestionProveedores.php` |
| Movimientos | `GestionStock/movimientosStock.php` |
| Transferencias | `GestionStock/transferenciasStock.php` |
| Alertas | `GestionStock/alertasStock.php` |
| Reportes inventario | `GestionStock/reportesInventario.php` |

## Aislamiento multitenant

- Listados con `WHERE empresa_id = ?`
- **No programar** descuento manual al usar repuesto en una orden: el trigger `reservar_stock_servicio` en `detalle_servicios` lo hace en MySQL

## Relacionado

- [[Flujo Orden de Servicio]], [[Permisos Areas y Funciones]]

## Estado

`Parcial` — CRUD operativo; validar triggers en entorno de prueba
