<?php
/**
 * Lizzosoft Vehículos - Pantalla de Acceso Completa (Multitenant Blindado)
 * Ubicación: lizzosoft_vehiculos/Login/login.php
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ini_set('session.cookie_lifetime', 43200);
ini_set('session.gc_maxlifetime', 43200);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

if (isset($_SESSION['IDUsuario']) && isset($_SESSION['cliente_config']['empresa_id']) && isset($_SESSION['cliente_config']['sucursal_id'])) {
    header("Location: ../inicio.php");
    exit;
}

$error = '';

// =========================================================================================
// SINCRONIZACIÓN Y AUTO-REPARACIÓN DE CONTEXTO (Evita persistencia de cookies viejas)
// =========================================================================================
if (isset($_SESSION['cliente_config']['empresa_id'])) {
    $idEmpresaActiva = (int) $_SESSION['cliente_config']['empresa_id'];
    $nombreCarpetaInferred = '';
    if ($idEmpresaActiva === 1) {
        $nombreCarpetaInferred = 'garbuioMotorService';
    }

    if (!empty($nombreCarpetaInferred)) {
        setcookie('lizzosoft_client_folder', $nombreCarpetaInferred, time() + (365 * 24 * 60 * 60), '/', '', false, true);
    }
} else {
    // Forzado estático para entorno de producción de un solo cliente
    $subdominio_detectado = 'garbuioMotorService';

    setcookie('lizzosoft_client_folder', $subdominio_detectado, time() + (365 * 24 * 60 * 60), '/', '', false, true);
    $ruta_config_cliente = __DIR__ . "/../clientes_config/" . $subdominio_detectado . "/config.php";

    if (file_exists($ruta_config_cliente)) {
        $_SESSION['cliente_config'] = require $ruta_config_cliente;
    } else {
        // FALLBACK: Si el archivo no existe en el servidor en vivo, cargamos la configuración de Garbuio directamente.
        $_SESSION['cliente_config'] = [
            'empresa_id' => 1,
            'nombre_empresa' => 'Garbuio Motor-Service',
            'cuit_empresa' => '00-00000000-0',
            'apariencia' => [
                'color_primario' => '#2c3e50',
                'color_secundario' => '#e74c3c',
                'color_fondo' => '#f4f6f9',
                'color_texto' => '#333333'
            ],
            'labels' => [
                'vehiculo_singular' => 'Vehículo',
                'vehiculo_plural' => 'Vehículos'
            ]
        ];
    }
}

// =========================================================================================
// PROCESAMIENTO DEL FORMULARIO DE ACCESO CON FILTRADO DEFENSIVO
// =========================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave = trim($_POST['clave'] ?? '');

    if ($usuario !== '' && $clave !== '') {
        try {
            if (function_exists('obtenerConexion')) {
                $conexion = obtenerConexion();
            } elseif (function_exists('Conectar')) {
                $conexion = Conectar();
            } else {
                throw new Exception("No se encontró el archivo de conexión del sistema.");
            }

            $idEmpresaConfig = (int) $_SESSION['cliente_config']['empresa_id'];

            // Se incluye u.tema_preferido en la consulta
            $query = "
                SELECT 
                    u.IDUsuario, u.nombreUsuario, u.contraseñaUsuario, u.tema_preferido, u.sucursal_id, u.empresa_id, u.IDRol, 
                    r.nombreRol, s.IDRubro, rub.nombreRubro
                FROM usuarios u
                LEFT JOIN roles r ON u.IDRol = r.IDRol
                LEFT JOIN sucursales s ON u.sucursal_id = s.id AND s.empresa_id = u.empresa_id
                LEFT JOIN rubros rub ON s.IDRubro = rub.IDRubro
                WHERE u.nombreUsuario = ? 
                  AND u.empresa_id = ? 
                  AND u.estado = 'Activo'
                LIMIT 1
            ";

            $stmt = $conexion->prepare($query);
            $stmt->execute([$usuario, $idEmpresaConfig]);
            $usuarioData = $stmt->fetch();

            if ($usuarioData && $usuarioData['contraseñaUsuario'] === md5($clave)) {

                $_SESSION['IDUsuario'] = $usuarioData['IDUsuario'];
                $_SESSION['nombreUsuario'] = $usuarioData['nombreUsuario'];
                $_SESSION['tema_preferido'] = $usuarioData['tema_preferido'] ?? 'claro'; // SE GUARDA EN SESIÓN
                $_SESSION['sucursal_id'] = $usuarioData['sucursal_id'];
                $_SESSION['usuario_sucursal'] = $usuarioData['sucursal_id'];
                $_SESSION['empresa_id'] = $usuarioData['empresa_id'];
                $_SESSION['empresa_id_usuario'] = $usuarioData['empresa_id'];
                $_SESSION['sucursal_base'] = $usuarioData['sucursal_id'];
                $_SESSION['IDRol'] = $usuarioData['IDRol'];
                $_SESSION['nombreRol'] = $usuarioData['nombreRol'] ?? 'Sin Rol';
                $_SESSION['nombreRubro'] = $usuarioData['nombreRubro'] ?? $_SESSION['cliente_config']['nombre_rubro'] ?? 'General';
                $_SESSION['ultimo_acceso'] = time();

                $termino = 'Vehículo';
                $rubroNom = strtolower($_SESSION['nombreRubro']);
                if (str_contains($rubroNom, 'moto')) {
                    $termino = 'Moto';
                } elseif (str_contains($rubroNom, 'camion') || str_contains($rubroNom, 'camión')) {
                    $termino = 'Camión';
                } elseif (str_contains($rubroNom, 'trafic')) {
                    $termino = 'Trafic';
                }
                $_SESSION['termino_vehiculo'] = $termino;

                $areas = [];
                $funciones = [];
                $funcionesReportes = [];
                $funcionesStock = [];

                $stmtA = $conexion->prepare("SELECT IDAreaSistema, IDFuncion FROM permisosusuarios WHERE IDUsuario = ? AND estado = 'Activo'");
                $stmtA->execute([$usuarioData['IDUsuario']]);
                while ($row = $stmtA->fetch()) {
                    $areas[$row['IDAreaSistema']] = true;
                    $funciones[$row['IDAreaSistema']][] = $row['IDFuncion'];
                }

                // Mapeo automático de áreas y funciones implícitas para Roles Base
                if ($usuarioData['IDRol'] == 2) { // Mecánico
                    $areas[1] = true;
                    $funciones[1] = [1, 2, 3]; // Órdenes
                    $areas[5] = true;
                    $funciones[5] = [3];       // Clientes (Solo ver)
                    $areas[6] = true;
                    $funciones[6] = [3];       // Servicios (Solo ver)
                    $areas[8] = true;
                    $funciones[8] = [1, 2, 3]; // Vehículos
                    $areas[9] = true;
                    $funciones[9] = [3];       // Stock (Solo ver)
                    $areas[10] = true;
                    $funciones[10] = [1, 2, 3]; // Tareas/Turnos
                } elseif ($usuarioData['IDRol'] == 3) { // Administrativo / Recepción
                    $areas[1] = true;
                    $funciones[1] = [1, 2, 3, 13]; // Órdenes
                    $areas[5] = true;
                    $funciones[5] = [1, 2, 3]; // Clientes
                    $areas[6] = true;
                    $funciones[6] = [1, 2, 3]; // Servicios
                    $areas[8] = true;
                    $funciones[8] = [1, 2, 3]; // Vehículos
                    $areas[9] = true;
                    $funciones[9] = [1, 2, 3]; // Stock
                    $areas[10] = true;
                    $funciones[10] = [1, 2, 3]; // Tareas/Turnos
                    $areas[2] = true; // Reportes
                }

                $stmtR = $conexion->prepare("SELECT IDAreaSistema, IDReporte FROM permisos_reportes WHERE IDUsuario = ? AND estado = 'Activo'");
                $stmtR->execute([$usuarioData['IDUsuario']]);
                while ($row = $stmtR->fetch()) {
                    $areas[$row['IDAreaSistema']] = true;
                    $funcionesReportes[$row['IDAreaSistema']][] = $row['IDReporte'];
                }

                $_SESSION['areas_permitidas'] = array_keys($areas);
                $_SESSION['funciones_permitidas'] = $funciones;
                $_SESSION['funciones_reportes_permitidas'] = $funcionesReportes;
                $_SESSION['funciones_stock_permitidas'] = $funcionesStock;

                $stmtUpdate = $conexion->prepare("UPDATE usuarios SET fechaUltimoAcceso = NOW() WHERE IDUsuario = ?");
                $stmtUpdate->execute([$usuarioData['IDUsuario']]);

                header("Location: seleccionar_sucursal.php");
                exit;
            } else {
                $error = "Nombre de usuario o contraseña incorrectos, o la cuenta se encuentra suspendida.";
            }
        } catch (Exception $e) {
            $error = "Fallo de ejecución: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, complete los campos de usuario y contraseña.";
    }
}

$apariencia = $_SESSION['cliente_config']['apariencia'] ?? ['color_primario' => '#2c3e50', 'color_fondo' => '#f4f6f9'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema -
        <?php echo htmlspecialchars($_SESSION['cliente_config']['nombre_empresa'] ?? 'Lizzosoft'); ?></title>
    <style>
        :root {
            --color-primario:
                <?php echo htmlspecialchars($apariencia['color_primario']); ?>
            ;
            --color-fondo:
                <?php echo htmlspecialchars($apariencia['color_fondo']); ?>
            ;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: var(--color-fondo);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-container {
            background: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 420px;
            box-sizing: border-box;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h2 {
            margin: 0;
            color: var(--color-primario);
            font-size: 26px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #34495e;
            font-size: 14px;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cccccc;
            border-radius: 4px;
            font-size: 15px;
            box-sizing: border-box;
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background-color: var(--color-primario);
            color: #ffffff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 12px;
            border-radius: 4px;
            font-size: 14px;
            margin-bottom: 20px;
            text-align: center;
            line-height: 1.4;
        }

        .recuperar-link {
            text-align: center;
            margin-top: 15px;
        }

        .recuperar-link a {
            color: var(--color-primario);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .recuperar-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 25px 20px;
                width: 90%;
            }
            .login-header h2 {
                font-size: 22px;
            }
            .btn-submit {
                font-size: 15px;
            }
        }
    </style>

    <?php if (isset($_SESSION['tema_preferido']) && $_SESSION['tema_preferido'] === 'oscuro'): ?>
        <link rel="stylesheet" href="../CSS/modo_oscuro.css">
    <?php endif; ?>
</head>

<body
    class="<?php echo (isset($_SESSION['tema_preferido']) && $_SESSION['tema_preferido'] === 'oscuro') ? 'tema-oscuro' : ''; ?>">
    <div class="login-container">
        <div class="login-header">
            <h2><?php echo htmlspecialchars($_SESSION['cliente_config']['nombre_empresa'] ?? 'Lizzosoft Vehículos'); ?>
            </h2>
            <p style="margin:5px 0 0 0; color:#777; font-size:14px;">Panel de Acceso</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['cliente_config']['empresa_id'])): ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="usuario">Nombre de Usuario</label>
                    <input type="text" id="usuario" name="usuario" required autocomplete="off"
                        placeholder="Ingrese su usuario">
                </div>
                <div class="form-group">
                    <label for="clave">Contraseña</label>
                    <input type="password" id="clave" name="clave" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn-submit">Ingresar al Taller</button>

                <div class="recuperar-link">
                    <a href="recuperar.php" title="Funcionalidad en desarrollo">¿Olvidaste tu contraseña?</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>