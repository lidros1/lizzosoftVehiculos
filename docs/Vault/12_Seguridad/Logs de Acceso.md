# Auditoría y Logs de Seguridad

Todo evento crítico del sistema queda registrado para auditoría mediante procesos automatizados en la base de datos.

## Registro Automatizado (Triggers)

- **Cero código PHP:** Para auditar cambios de contraseñas no se debe escribir lógica extra en PHP. La base de datos cuenta con el trigger `tr_log_cambio_usuario` atado a la tabla `usuarios`.

- Al detectar un cambio en la clave o el estado de un usuario, el trigger inserta automáticamente el evento en la tabla `logs_accesos`.

## Reporte de Accesos

- El panel de auditoría (ver `Reportes Multitenant.md`) consume directamente estos registros para listar quién inició sesión, desde qué sucursal y a qué hora.