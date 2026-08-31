# Módulo: Gestión de Clientes

Administra la cartera de clientes de cada sucursal/empresa.

## Lógica de negocio

- **Pertenencia:** `empresa_id` obligatorio; documento único por empresa (`unq_doc_cliente_empresa`).
- **Vinculación:** cada cliente puede tener uno o más [[GestionVehiculos]].
- **CRUD:** `CRUD_Clientes/` con prepared statements; filtrar por empresa/sucursal de sesión.

## Datos principales

- `IDCliente`, tipo y número de documento, nombre, apellido, teléfono, email
- `estado`, `empresa_id`, `sucursal_id`

## Archivos clave

| Archivo | Rol |
|---------|-----|
| `CRUD_Clientes/Cliente.php` | Lógica de negocio |
| `CRUD_Clientes/crear_cliente.php` | Alta |
| `CRUD_Clientes/editar_cliente.php` | Edición |
| `CRUD_Clientes/listar_clientes.php` | Listado |

## Relacionado

- [[Flujo Reclamos]], [[Flujo Orden de Servicio]]
- [[Configuracion del Sistema]] — área Clientes

## Tareas pendientes

- [ ] Validar permisos en POST, no solo en menú
- [ ] Exportar listado CSV/PDF

## Estado

`Implementado`
