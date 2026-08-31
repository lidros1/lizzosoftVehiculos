<?php
/**
 * Lizzosoft Vehículos - Fichero de Inicialización de Autenticación
 * Ubicación: lizzosoft_vehiculos/Login/Auth.php
 */

// Garantizar que la sesión exista siempre que se requiera lógica de autenticación
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Función auxiliar para verificar roles de manera rápida en las vistas
 * @param int|array $rolesPermitidos
 * @return bool
 */
function verificarRolAutorizado($rolesPermitidos) {
    if (!isset($_SESSION['IDRol'])) {
        return false;
    }
    
    if (is_array($rolesPermitidos)) {
        return in_array($_SESSION['IDRol'], $rolesPermitidos);
    }
    
    return $_SESSION['IDRol'] == $rolesPermitidos;
}