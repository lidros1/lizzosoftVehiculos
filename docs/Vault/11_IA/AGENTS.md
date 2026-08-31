# Reglas IA

## Estándares de Código y Multitenencia

- Utilizar PHP nativo estructurado por módulos.

- **Variabilización UI:** ¡Prohibido hardcodear! Toda etiqueta HTML (ej. "Auto" o "Moto") y colores deben leerse de `$_SESSION['cliente_config']`.

- **Aislamiento de Datos:** Todo `INSERT`, `UPDATE` o `SELECT` debe incluir obligatoriamente el filtro por `empresa_id` de la sesión actual.

- Mantener la lógica de conexión centralizada en `Conexion.php`.

- Utilizar consultas SQL preparadas (Prepared Statements) con PDO o MySQLi para evitar inyecciones.

- Nomenclatura: usar camelCase para variables de JavaScript y snake_case para nombres de tablas y columnas en BD.

## Seguridad y Core

- La seguridad y validación de sesiones debe requerirse al inicio de los scripts mediante `Login/verificar_sesion.php`.

- **Triggers y SPs:** No programar en PHP cálculos o lógicas que ya estén resueltas en los Procedimientos Almacenados (Reportes) o Triggers (Descuento de Stock y Logs) de la base de datos MySQL.

## Estructura de Interfaz

- Separar las vistas lógicas: usar `HTML/interfaz_global.php` para el layout general, cargando allí las variables CSS dinámicas del cliente.

- Estilos base centralizados en `CSS/style.css`.

- Evitar duplicar código HTML; modularizar los menús usando condicionales según el rubro de la empresa.

## Herramientas Externas

- Para el envío de notificaciones automáticas (ej. Cambio de estado de Orden), utilizar exclusivamente la librería PHPMailer ubicada en el directorio `Conexion/PHPMailer/`.