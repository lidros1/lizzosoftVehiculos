<?php
/**
 * Lizzosoft Vehículos - Conexión Procedural PDO Segura
 */

function obtenerConexion() {
    static $conexion = null;

    if ($conexion === null) {
        // Leemos las credenciales desde el archivo excluido en git
        $credenciales = require __DIR__ . '/credenciales.php';

        $dsn = "mysql:host=" . $credenciales['host'] . ";dbname=" . $credenciales['db'] . ";charset=utf8mb4";
        
        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, 
        ];

        try {
            $conexion = new PDO($dsn, $credenciales['user'], $credenciales['pass'], $opciones);
        } catch (\PDOException $e) {
            error_log("Error crítico BD: " . $e->getMessage());
            die("Error de conexión a la base de datos. Por favor contacte al soporte Lizzosoft.");
        }
    }

    return $conexion;
}
?>