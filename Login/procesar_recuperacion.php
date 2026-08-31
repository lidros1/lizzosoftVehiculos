<?php
session_start();

// REGLA DE ARQUITECTURA: Zona horaria obligatoria
date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once '../Conexion/Conexion.php'; 
require_once '../Conexion/Mailer.php'; 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_input = trim($_POST['usuario'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $empresa_id = $_SESSION['cliente_config']['empresa_id'] ?? null; 
    
    // Obtenemos la configuración completa para pasarla a Mailer.php
    $configEmpresa = $_SESSION['cliente_config'] ?? null;

    if (empty($usuario_input) || empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'Por favor, ingrese su usuario y correo electrónico.']);
        exit;
    }

    if (empty($empresa_id) || empty($configEmpresa)) {
        echo json_encode(['status' => 'error', 'message' => 'Error de entorno: Configuración de empresa no encontrada.']);
        exit;
    }

    try {
        $conexion = function_exists('obtenerConexion') ? obtenerConexion() : Conectar();
        
        // DOBLE VALIDACIÓN: Validamos Usuario y Correo para evitar envíos no autorizados
        $stmt = $conexion->prepare("SELECT IDUsuario, nombreUsuario, email FROM usuarios WHERE nombreUsuario = ? AND email = ? AND empresa_id = ? AND estado = 'Activo'");
        $stmt->execute([$usuario_input, $email, $empresa_id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            $codigo = sprintf("%06d", mt_rand(1, 999999));
            $expiracion = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $updateStmt = $conexion->prepare("UPDATE usuarios SET codigo_recuperacion = ?, expiracion_codigo = ? WHERE IDUsuario = ? AND empresa_id = ?");
            $updateStmt->execute([$codigo, $expiracion, $usuario['IDUsuario'], $empresa_id]);

            $nombre_empresa = $configEmpresa['nombre_empresa'] ?? 'Lizzosoft';
            $color_primario = $configEmpresa['apariencia']['color_primario'] ?? '#0056b3';
            
            $asunto = "Código de Recuperación - " . $nombre_empresa;
            
            $cuerpoHTML = "
                <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #e4e4e4; border-radius: 8px;'>
                    <div style='background-color: " . $color_primario . "; padding: 15px; text-align: center; border-radius: 6px 6px 0 0;'>
                        <h2 style='color: #ffffff; margin: 0;'>" . htmlspecialchars($nombre_empresa) . "</h2>
                    </div>
                    <div style='padding: 20px;'>
                        <p>Hola <strong>" . htmlspecialchars($usuario['nombreUsuario']) . "</strong>,</p>
                        <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en nuestro sistema de gestión de talleres.</p>
                        <div style='background-color: #f8f9fa; padding: 15px; text-align: center; border-radius: 6px; margin: 20px 0; border: 1px dashed #ccc;'>
                            <span style='font-size: 32px; font-weight: bold; letter-spacing: 8px; color: " . $color_primario . ";'>" . $codigo . "</span>
                        </div>
                        <p style='font-size: 13px; color: #666; text-align: center;'>Este código es válido por los próximos 15 minutos. Si no has solicitado este cambio, puedes ignorar este mensaje de forma segura.</p>
                    </div>
                </div>
            ";

            // CORRECCIÓN: Llamada a la función exacta de tu Mailer.php con los 5 parámetros requeridos
            $envioExitoso = enviarCorreoBase(
                $usuario['email'],         // Destino
                $usuario['nombreUsuario'], // Nombre del Cliente/Usuario
                $asunto,                   // Asunto
                $cuerpoHTML,               // Cuerpo
                $configEmpresa             // Array de configuración inyectado
            );

            if ($envioExitoso) {
                echo json_encode(['status' => 'success', 'message' => 'Código de verificación enviado con éxito a tu correo electrónico.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'El código fue generado, pero el servidor de correo SMTP falló al enviarlo. Revisa las credenciales.']);
            }
        } else {
            echo json_encode(['status' => 'success', 'message' => 'Si los datos coinciden con nuestra base, se ha enviado un código de verificación.']);
        }

    } catch (Exception $e) {
        error_log("Error crítico en procesar_recuperacion.php: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error interno en el servidor al procesar la solicitud.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método de petición no permitido.']);
}
?>