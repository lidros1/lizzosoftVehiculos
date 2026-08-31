<?php
/**
 * Lizzosoft Vehículos - Plantilla Base de Configuración de Cliente
 * 
 * Instrucciones: Copiar este archivo y renombrarlo como 'config.php' 
 * dentro de la carpeta correspondiente a la nueva empresa cliente 
 * (ej. clientes_config/nombreNuevaEmpresa/config.php).
 */

return [
    // --- Identificación de Aislamiento Multitenant ---
    'empresa_id' => 1, // REEMPLAZAR con el ID de la empresa en la base de datos
    'nombre_empresa' => 'Garbuio Motor-Service',
    'cuit_empresa' => '00-00000000-0',

    // --- Identidad Visual (Tematización) ---
    'apariencia' => [
        'color_primario' => '#2c3e50', // Color principal (menús, botones primarios)
        'color_secundario' => '#e74c3c', // Color para destacados, links activos
        'color_fondo' => '#f4f6f9', // Color de fondo principal
        'color_texto' => '#333333'  // Color de fuente principal
    ],

    // --- Etiquetas Dinámicas de Texto (Adaptación al Rubro) ---
    'labels' => [
        'vehiculo_singular' => 'Vehículo',
        'vehiculo_plural' => 'Vehículos'
    ],

    // --- Módulos Contratados (Control de Acceso / Feature Flags) ---
    // Colocar en 'false' para deshabilitar y ocultar el módulo de la interfaz
    'modulos' => [
        'modulo_ordenes' => true, // Gestión de Órdenes de Trabajo
        'modulo_presupuestos' => true, // Cotizaciones y Presupuestos
        'modulo_reclamos' => true, // Trazabilidad y Gestión de Reclamos
        'modulo_alertas' => true, // Panel de Alertas Automáticas y Recordatorios
        'modulo_reportes' => true  // Análisis y Reportes Gerenciales
    ]
];
