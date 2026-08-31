<?php
/**
 * Lizzosoft - Enrutador Principal de Subdominios
 * Ubicación: Raíz del servidor (public_html / web / www)
 * Función: Captura el subdominio y redirige a la carpeta del cliente.
 */

$host = strtolower($_SERVER['HTTP_HOST']);
$dominioPrincipal = 'lizzosoft.com.ar';

// Verificamos si están entrando por un subdominio (y no por el dominio principal)
if ($host !== $dominioPrincipal && $host !== "www.$dominioPrincipal") {
    
    // Capturamos la primera parte de la URL (Ej: extrae 'tallerlosmotores' de tallerlosmotores.lizzosoft.com.ar)
    if (preg_match('/^([a-z0-9-]+)\.lizzosoft\.com\.ar$/', $host, $matches)) {
        $subdominio = $matches[1];

        // -------------------------------------------------------------------------
        // RUTAS FÍSICAS (Adaptadas a tu estructura real)
        // -------------------------------------------------------------------------
        // Ruta 1: Sistema de Vehículos (Carpeta: /clientes/tallerlosmotores/)
        $rutaVehiculos = "clientes/" . $subdominio . "/";
        
        // Ruta 2: Sistema de Bicicletas (Mantenemos tu lógica original por si la usas)
        $rutaBicicletas = "clientes_bicicletas/" . $subdominio . "/";

        // Comprobamos si la carpeta del cliente existe en Vehículos
        if (is_dir($rutaVehiculos)) {
            // Redirigimos al cliente a su carpeta (la cual ejecutará su propio index.php para inyectar colores y mandarlo al login)
            header("Location: https://$dominioPrincipal/$rutaVehiculos", true, 301);
            exit;
            
        // Comprobamos si existe en Bicicletas
        } elseif (is_dir($rutaBicicletas)) {
            header("Location: https://$dominioPrincipal/$rutaBicicletas", true, 301);
            exit;
            
        // Si alguien inventa un subdominio que no existe, le mostramos este error
        } else {
            http_response_code(404);
            die("
                <div style='font-family: Arial; text-align: center; margin-top: 100px; color: #333;'>
                    <h1 style='color: #e74c3c;'>Error 404 - Taller No Encontrado</h1>
                    <p>El taller <strong>'$subdominio'</strong> no está registrado o fue dado de baja en nuestros sistemas.</p>
                    <p>Por favor, verifique la dirección web ingresada.</p>
                </div>
            ");
        }
    }
} else {
    // Si entran directamente a www.lizzosoft.com.ar (Landing page de tu empresa)
    echo "
        <div style='font-family: Arial; text-align: center; margin-top: 100px;'>
            <h1>Bienvenido a Lizzosoft</h1>
            <p>Sistemas de Gestión para Talleres</p>
        </div>
    ";
}
?>