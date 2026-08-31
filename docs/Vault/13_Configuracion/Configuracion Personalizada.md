# Configuración Personalizada por Cliente (Multitenant)

El sistema soporta múltiples empresas. Cada empresa tiene una carpeta dedicada dentro de la ruta raíz `/clientes_config/{nombre_carpeta}/`.

## Estructura del Archivo `config.php`

Dentro de la carpeta de cada cliente (ej. `/clientes_config/tallerLosMotores/`), debe existir un archivo `config.php` que retorne un array asociativo puro. 

**Ejemplo estricto para Cursor:**

```php

<?php

return [

    'empresa_id' => 1, // ID clave para cruzar con la base de datos

    'nombre_cliente' => 'Taller Los Motores',

    'apariencia' => [

        'color_principal' => '#2980b9', 

        'color_acento' => '#3498db',

        'sidebar_text' => '#ffffff'

    ],

    'sistema' => 'vehiculos' // Puede variar a 'motos', 'pesados' para habilitar módulos

];