<?php
/**
 * Lizzosoft Vehículos - Logout con Auditoría
 * Ubicación: lizzosoft_vehiculos/Login/logout.php
 */
session_start();

require_once __DIR__ . '/../Conexion/Conexion.php';

if (isset($_SESSION['IDUsuario']) && isset($_SESSION['nombreUsuario'])) {
    try {
        $conexion = obtenerConexion();
        $sucursal_id = $_SESSION['sucursal_id'] ?? 1;
        $empresa_id = $_SESSION['empresa_id'] ?? 1;
        
        $stmt = $conexion->prepare("INSERT INTO logs_accesos (IDUsuario, nombreUsuario, accion, sucursal_id, empresa_id) VALUES (?, ?, 'Cierre de sesión manual', ?, ?)");
        $stmt->execute([$_SESSION['IDUsuario'], $_SESSION['nombreUsuario'], $sucursal_id, $empresa_id]);
    } catch(Exception $e) {
        // Ignorar falla de base de datos para no bloquear la salida del usuario
    }
}

// Destruir por completo los datos de sesión y redireccionar al login base
session_unset();
session_destroy();
header("Location: login.php");
exit;