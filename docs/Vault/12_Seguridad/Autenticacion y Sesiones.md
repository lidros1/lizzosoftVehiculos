# Autenticación y Sesiones Multitenant

## Control de acceso

- Todo PHP (salvo login) debe validar sesión vía `Login/verificar_sesion.php` o `Auth::check()`.
- Contraseñas en `usuarios` con MD5 (migrar a `password_hash` pendiente).

## Flujo de inicio de sesión

1. Credenciales en `Login/login.php`.
2. Si aplica: `Login/seleccionar_sucursal.php`.
3. Cargar `clientes_config/{empresa}/config.php` → `$_SESSION['cliente_config']`.
4. Guardar `empresa_id`, `sucursal_id`, `IDUsuario`, `IDRol`, permisos (`cargarPermisos`).
5. Aplicar colores/etiquetas en `HTML/interfaz_global.php`.

## Parámetros

- Timeout inactividad: 12 h (`43200` s) en `Auth.php`.
- Verificación AJAX cada ~5 min en pantallas protegidas.

## Pantallas

`Login/index.php`, `login.php`, `dashboard.php`, `logout.php`, `HTML/inicio.php`

## Prevención de inyecciones

Solo prepared statements (MySQLi/PDO); prohibido concatenar variables en SQL.

## Relacionado

- [[Logs de Acceso]], [[Permisos Areas y Funciones]], [[GestionPersonal]]

## Tareas pendientes

- [ ] `password_hash`, `session_regenerate_id`, recuperación de contraseña por mail

## Estado

`Implementado` con deuda técnica en hash
