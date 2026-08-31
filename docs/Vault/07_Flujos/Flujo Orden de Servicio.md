# Flujo: Orden de Servicio

Ciclo de vida del mantenimiento del vehículo en la sucursal activa.

## Creación de la orden

- Validar sesión con `Login/verificar_sesion.php` al inicio.
- Insertar con `empresa_id` (y `sucursal_id`) de sesión.
- Etiquetas desde `$_SESSION['cliente_config']` — nunca hardcodear tipo de vehículo.

## Pasos

1. Buscar cliente y vehículo (AJAX).
2. Elegir servicios del catálogo ([[GestionServicios]]).
3. Asignar personal y estado (Pendiente, En Proceso, Finalizado-NE, Finalizado-ENT).
4. Guardar en `registrosservicios`.
5. Listar, editar, imprimir.
6. Al **Finalizado-NE**: notificar por correo con `Conexion/Mailer.php` (logo según empresa).

## Presupuesto y repuestos

- Al agregar insumos, MySQL descuenta stock vía trigger `reservar_stock_servicio` — no duplicar en PHP.
- Ver [[GestionStock]] y [[Entidades Principales]].

## Pantallas

| Pantalla | Archivo |
|----------|---------|
| Crear | `registrosServicios/crear_registroServicio.php` |
| Listar | `registrosServicios/listar_registrosServicios.php` |
| Editar | `registrosServicios/editar_registroServicio.php` |
| Imprimir | `registrosServicios/imprimir_registro.php` |
| Menú | `HTML/registrosServicios.php` |

## Endpoints AJAX (`crear_registroServicio`)

| Action | Descripción |
|--------|-------------|
| `buscar_cliente` | Clientes de la sucursal |
| `obtener_bicicletas` / vehículos | Unidades del cliente |
| `obtener_servicios_categoria` | Catálogo con costos |
| `obtener_estados_solicitud` | Estados permitidos |

## Relacionado

- [[GestionClientes]], [[GestionVehiculos]], [[GestionPersonal]]
- [[Reportes Multitenant]]

## Estado

`Implementado` (flujo principal)
