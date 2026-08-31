# Permisos: Áreas y Funciones (RBAC)

El sistema restringe acciones por empresa mediante áreas y funciones.

## Estructura

| Tabla | Rol |
|-------|-----|
| `roles` | Administrador, Mecánico, Recepción |
| `areassistema` | Módulos UI (Vehículos, Servicios, Reportes, Stock…) |
| `funciones` | Crear, Editar, Eliminar, Listar |
| `permisosusuarios` | Cruce usuario ↔ área ↔ función |

En sesión: `areas_permitidas`, `funciones_permitidas`, `funciones_stock_permitidas`.

## Menú principal (`HTML/inicio.php`)

| ID (ej.) | UI | Destino |
|----------|-----|---------|
| 1 | Órdenes de trabajo | `HTML/registrosServicios.php` |
| 2 | Reportes | `HTML/reportes.php` |
| 3 | Configuración | `HTML/configuracionSistema.php` |
| 9 | Stock | `HTML/gestionStock.php` |

## Regla para la UI

Antes de mostrar botones Crear/Editar, validar permiso en `permisosusuarios` (no solo ocultar en menú).

## Stock (área 9)

`funciones_stock_permitidas[9]`: productos (1), proveedores (3), movimientos (4), etc.

## Archivos clave

- `Login/Auth.php` — `cargarPermisos()`
- `CRUD_Personal/Personal.php` — asignación al crear personal

## Relacionado

- [[Configuracion del Sistema]]

## Estado

`Parcial`
