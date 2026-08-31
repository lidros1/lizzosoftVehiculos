<?php
/**
 * Lizzosoft Vehículos - Muro de Seguridad (Middleware de Sesión)
 * Ubicación: lizzosoft_vehiculos/Login/verificar_sesion.php
 */

// Configurar tiempo de vida largo de la cookie de sesión antes de que sea inicializada
ini_set('session.cookie_lifetime', 43200);
ini_set('session.gc_maxlifetime', 43200);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si la sesión no tiene el identificador único del usuario, destruye el entorno y rebota
if (!isset($_SESSION['IDUsuario']) || !isset($_SESSION['cliente_config']['empresa_id'])) {
    header("Location: /lizzosoft_vehiculos/Login/login.php");
    exit;
}

// DETALLE SOLICITADO: Control estricto de expiración tras cumplirse exactamente las 12 horas
$limiteInactividad = 12 * 60 * 60; // 12 horas en segundos
if (isset($_SESSION['ultimo_acceso']) && (time() - $_SESSION['ultimo_acceso'] > $limiteInactividad)) {
    // Si ya pasaron las 12 horas, limpiamos datos de usuario pero resguardamos el taller
    $config_respaldo = $_SESSION['cliente_config'] ?? null;
    $_SESSION = [];
    if ($config_respaldo) {
        $_SESSION['cliente_config'] = $config_respaldo;
    }
    header("Location: /lizzosoft_vehiculos/Login/login.php?timeout=1");
    exit;
}

// Si no ha expirado el plazo, renovamos la marca de tiempo para autorizar la navegación continua
$_SESSION['ultimo_acceso'] = time();