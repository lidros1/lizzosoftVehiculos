# Conexión a BD y Sistema de Notificaciones

## Base de Datos `Conexion/Conexion.php`)

- Archivo centralizado que gestiona el pool de conexiones a MySQL. 

- La conexión debe configurarse con charset `utf8mb4` para soportar caracteres especiales.

## Envíos de Correo `Conexion/Mailer.php`)

- **Librería:** Se utiliza exclusivamente PHPMailer `Conexion/PHPMailer/`).

- **Automatización:** Este script es invocado principalmente por el módulo de Órdenes de Servicio. Cuando una orden cambia de estado a `Finalizado-NE` (Finalizado - No Entregado), el sistema ejecuta el Mailer para enviar un correo de aviso al cliente indicando que su vehículo está listo para retirar.

- El logotipo del correo debe leerse desde la variable dinámica de la empresa cargada en el archivo `config.php`.