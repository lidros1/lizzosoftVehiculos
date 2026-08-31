<?php
/**
 * Lizzosoft Vehículos - Cambio Dinámico de Sucursal
 * Ubicación: lizzosoft_vehiculos/Login/cambiar_sucursal.php
 */

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

if (!isset($_SESSION['IDUsuario']) || !isset($_SESSION['sucursales_admin_disponibles'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sucursal_id'])) {
    $nueva_sucursal_id = (int)$_POST['sucursal_id'];
    $datos_sucursal = null;

    // Verificar que la sucursal solicitada esté en las autorizadas
    foreach ($_SESSION['sucursales_admin_disponibles'] as $suc) {
        if ((int)$suc['id'] === $nueva_sucursal_id) {
            $datos_sucursal = $suc;
            break;
        }
    }

    if ($datos_sucursal) {
        // Actualizar variables de sesión con la nueva sucursal
        $_SESSION['cliente_config']['sucursal_id']     = $datos_sucursal['id'];
        $_SESSION['cliente_config']['nombre_sucursal'] = $datos_sucursal['nombre'];
        $_SESSION['cliente_config']['id_rubro']        = $datos_sucursal['IDRubro'];
        $_SESSION['cliente_config']['nombre_rubro']    = $datos_sucursal['nombreRubro'];

        $_SESSION['sucursal_id'] = $datos_sucursal['id'];
        $_SESSION['nombreRubro'] = $datos_sucursal['nombreRubro'];

        // Registrar el acceso en logs_accesos para la nueva sucursal
        try {
            if (function_exists('obtenerConexion')) {
                $conexion = obtenerConexion();
            } elseif (function_exists('Conectar')) {
                $conexion = Conectar();
            }
            
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $empresa_id = $_SESSION['cliente_config']['empresa_id'];
            $id_usuario = $_SESSION['IDUsuario'];
            
            $sqlLog = "INSERT INTO logs_accesos (id_usuario, accion, fecha, ip_address, sucursal_id, empresa_id) 
                       VALUES (?, 'Inicio de sesion', NOW(), ?, ?, ?)";
            $stmtLog = $conexion->prepare($sqlLog);
            $stmtLog->execute([$id_usuario, $ip_address, $nueva_sucursal_id, $empresa_id]);
        } catch (Exception $e) {
            // Ignorar error de log para no frenar el ruteo
        }

        header("Location: ../inicio.php");
        exit;
    }
}

// Si falla, volver al inicio sin cambiar nada
header("Location: ../inicio.php");
exit;
