<?php
/**
 * Lizzosoft Vehículos - Punto de Entrada Dinámico
 * Ubicación: clientes/clientes_lizzosoft_vehiculos/motosExpress/index.php
 */

// 1. Mostrar errores en pantalla (Modo Diagnóstico temporal)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Inicialización segura de sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Limpieza de variables para evitar cruce de datos con otros talleres
session_unset();

// 4. Carga de la configuración específica de esta empresa en la sesión
$_SESSION['cliente_config'] = require __DIR__ . '/config.php';

// 5. Redirección Absoluta Dinámica (Infalible para servidores estrictos)
// Detecta HTTP o HTTPS
$protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
// Detecta el dominio actual (ej: test.lizzosoft.com.ar o www.lizzosoft.com.ar)
$host = $_SERVER['HTTP_HOST']; 

// Arma la ruta exacta hacia la carpeta raíz del sistema
$urlDestino = $protocolo . "://" . $host . "/lizzosoft_vehiculos/Login/login.php";

header("Location: " . $urlDestino);
exit;