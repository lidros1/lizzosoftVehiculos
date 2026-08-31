<?php
/**
 * Lizzosoft Vehículos - Layout Maestro y Hoja de Estilos Global
 * Actúa como la "cáscara" del sistema, inyectando estilos Multitenant 
 * e incluyendo el módulo solicitado mediante la URL.
 */

// 1. Validar la sesión y el muro de seguridad
require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

// 2. Extraer configuración dinámica del cliente (Variabilización UI)
$config = $_SESSION['cliente_config'];
$apariencia = $config['apariencia'];
$modulosActivos = $config['modulos'];
$areasPermitidas = $_SESSION['areas_permitidas'] ?? [];
$terminoVehiculo = $_SESSION['termino_vehiculo'] ?? 'Vehículo';

// 3. Controlador Frontal (Enrutador)
$moduloActual = $_GET['modulo'] ?? 'inicio';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config['nombre_empresa']); ?> - Sistema de Gestión</title>
    
    <style>
        :root {
            /* Variables Nativas inyectadas desde la configuración del cliente */
            --color-primario: <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>;
            --color-secundario: <?php echo htmlspecialchars($apariencia['color_secundario'] ?? '#e74c3c'); ?>;
            --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>;
            --color-texto: <?php echo htmlspecialchars($apariencia['color_texto'] ?? '#333333'); ?>;
            --color-borde: #dee2e6;
            --sidebar-width: 250px;
        }

        /* 1. RESET Y TIPOGRAFÍA BASE */
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--color-fondo);
            color: var(--color-texto);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* CSS del sidebar movido a HTML/sidebar.php */

        .main-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }
        .topbar {
            background-color: #ffffff;
            height: 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            z-index: 90;
        }
        .topbar-info {
            font-size: 15px;
            color: #555;
            font-weight: 500;
        }
        .topbar-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .topbar-user span {
            font-weight: bold;
            color: var(--color-primario);
        }

        /* Contenedor donde se inyectan las vistas */
        .content-area {
            padding: 25px;
            flex-grow: 1;
            overflow-y: auto;
            background-color: var(--color-fondo);
        }

        /* =======================================================================
           CLASES REUTILIZABLES PARA LOS CRUDS (Tablas, Botones, Formularios)
           ======================================================================= */
        
        /* Tarjetas (Contenedores blancos para formularios o listados) */
        .tarjeta {
            background: #ffffff;
            border-radius: 6px;
            padding: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .tarjeta-titulo {
            margin-top: 0;
            margin-bottom: 15px;
            color: var(--color-primario);
            font-size: 18px;
            border-bottom: 2px solid var(--color-fondo);
            padding-bottom: 10px;
        }

        /* Botones Globales */
        .btn-global {
            display: inline-block;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-global:hover { opacity: 0.85; }
        .btn-primario { background-color: var(--color-primario); color: #fff; }
        .btn-secundario { background-color: var(--color-secundario); color: #fff; }
        .btn-peligro { background-color: #e74c3c; color: #fff; }
        .btn-exito { background-color: #27ae60; color: #fff; }

        /* Formularios Globales */
        .form-global { width: 100%; max-width: 600px; }
        .form-grupo { margin-bottom: 15px; }
        .form-grupo label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #444;
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--color-borde);
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            border-color: var(--color-primario);
            outline: none;
        }

        /* Tablas Globales (Para Listar) */
        .tabla-contenedor {
            width: 100%;
            overflow-x: auto;
        }
        .tabla-global {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background-color: #fff;
            font-size: 14px;
        }
        .tabla-global th, .tabla-global td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--color-borde);
            text-align: left;
        }
        .tabla-global th {
            background-color: var(--color-primario);
            color: #ffffff;
            font-weight: 600;
            white-space: nowrap;
        }
        .tabla-global tr:hover {
            background-color: #f8f9fa;
        }

        /* Alertas / Mensajes */
        .alerta {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .alerta-exito { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alerta-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

    <?php 
        $basePath = '../'; 
        include __DIR__ . '/sidebar.php'; 
    ?>

    <div class="main-wrapper">
        
        <?php include __DIR__ . '/topbar.php'; ?>

        <main class="content-area">
            <?php
            // Ruteador nativo: Incluye el archivo correspondiente según el menú seleccionado.
            // Esto asegura que todas las vistas hereden el estilo y la seguridad superior.
            switch ($moduloActual) {
                case 'inicio':
                    // Inyectamos el archivo de dashboard dedicado
                    $rutaInicio = __DIR__ . '/inicio.php';
                    if (file_exists($rutaInicio)) require_once $rutaInicio;
                    else echo "<div class='tarjeta'><h2>Bienvenido a " . htmlspecialchars($config['nombre_empresa']) . "</h2><p>Seleccione una opción del menú lateral.</p></div>";
                    break;

                case 'ordenes':
                    require_once __DIR__ . '/../registrosServicios/listar_registrosServicios.php';
                    break;

                case 'reclamos':
                    if ($modulosActivos['modulo_reclamos']) {
                        require_once __DIR__ . '/../registrosServicios/listar_reclamos.php';
                    }
                    break;

                case 'vehiculos':
                    require_once __DIR__ . '/../configuracionSistema/areaVehiculos.php';
                    break;

                case 'clientes':
                    require_once __DIR__ . '/../configuracionSistema/areaClientes.php';
                    break;

                case 'stock':
                    if ($modulosActivos['modulo_stock']) {
                        require_once __DIR__ . '/../GestionStock/administrarProductos.php';
                    }
                    break;

                case 'personal':
                    require_once __DIR__ . '/../configuracionSistema/areaPersonal.php';
                    break;

                case 'config_sistema':
                    require_once __DIR__ . '/configuracionSistema.php';
                    break;

                case 'reportes':
                    if ($modulosActivos['modulo_reportes']) {
                        require_once __DIR__ . '/reportes.php';
                    }
                    break;

                default:
                    echo "<div class='alerta alerta-error'><strong>Error 404:</strong> El módulo solicitado no existe o no se encuentra disponible.</div>";
                    break;
            }
            ?>
        </main>
    </div>

</body>
</html>