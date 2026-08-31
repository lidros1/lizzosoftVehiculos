# Módulo: Gestión de Personal

Administra empleados y vincula usuarios del sistema.

## Relación de tablas

- `personal` + `usuarios` (`IDUsuario` opcional para acceso)
- Restricción `unq_doc_empresa_personal` — documento único por empresa
- Áreas y funciones asignadas al usuario

## Alta transaccional

Usar `CALL sp_crear_personal_usuario(...)` para crear empleado + usuario; verifica `nombreUsuario` único por `empresa_id`.

## Carga de trabajo

Consultar `vista_carga_trabajo_personal` o `CALL sp_carga_trabajo_personal` — no armar JOINs complejos en PHP.

## Archivos clave

- `CRUD_Personal/Personal.php`
- `CRUD_Personal/crear_personal.php`, `editar_personal.php`, `listar_personal.php`
- `CRUD_Personal/areas_funciones_personal.php`

## Relacionado

- [[Autenticacion y Sesiones]], [[Permisos Areas y Funciones]]
- [[Flujo Orden de Servicio]], [[Reportes Multitenant]]

## Estado

`Implementado`
