<?php
/**
 * Lizzosoft Vehículos - Gestor de Correos Multitenant
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

// -------------------------------------------------------------------------
// FUNCIÓN CORE: Envío base consumiendo credenciales.php
// -------------------------------------------------------------------------
function enviarCorreoBase($emailDestino, $nombreCliente, $asunto, $mensajeHTML, $configEmpresa) {
    $mail = new PHPMailer(true);

    try {
        // Cargar credenciales seguras (protegidas por .gitignore)
        $credenciales = require __DIR__ . '/credenciales.php';

        $mail->isSMTP();
        $mail->Host       = $credenciales['mail_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $credenciales['mail_user']; 
        $mail->Password   = $credenciales['mail_pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $credenciales['mail_port']; 
        $mail->CharSet    = 'UTF-8';

        // Identidad Multitenant
        $nombreRemitente = $configEmpresa['nombre_empresa'] . ' - Sistema de Gestión';
        $mail->setFrom($credenciales['mail_user'], $nombreRemitente);
        $mail->addAddress($emailDestino, $nombreCliente);

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $mensajeHTML;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>', '</h1>', '</h2>'], "\n", $mensajeHTML));

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Logueamos el error de forma silenciosa para no romper la ejecución de la UI
        error_log("Error al enviar correo a {$emailDestino}: {$mail->ErrorInfo}");
        return false;
    }
}

// -------------------------------------------------------------------------
// PLANTILLAS DE CORREOS
// -------------------------------------------------------------------------

/**
 * 1. Aviso de Orden Finalizada (Estado Finalizado-NE)
 */
function enviarAvisoFinalizadoNE($emailDestino, $nombreCliente, $nroOrden, $vehiculo, $configEmpresa) {
    $asunto = "¡Tu vehículo está listo! - " . $configEmpresa['nombre_empresa'];
    
    // Extraemos el color primario del taller para el encabezado
    $colorPrincipal = $configEmpresa['apariencia']['color_primario'];
    
    $html = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;'>
        <div style='background-color: {$colorPrincipal}; padding: 20px; text-align: center; color: white;'>
            <h2 style='margin: 0;'>{$configEmpresa['nombre_empresa']}</h2>
        </div>
        <div style='padding: 20px; color: #333;'>
            <p>Hola <strong>{$nombreCliente}</strong>,</p>
            <p>Nos comunicamos para informarte que el trabajo en tu vehículo <strong>{$vehiculo}</strong> (Orden #{$nroOrden}) ya se encuentra <strong>FINALIZADO</strong>.</p>
            <p>Ya puedes pasar a retirarlo por nuestra sucursal dentro de los horarios de atención.</p>
            <br>
        </div>
    </div>";

    return enviarCorreoBase($emailDestino, $nombreCliente, $asunto, $html, $configEmpresa);
}

/**
 * 2. Recordatorio de Mantenimiento / Servicios Periódicos
 */
function enviarRecordatorioMantenimiento($emailDestino, $nombreCliente, $vehiculo, $tipoServicio, $configEmpresa) {
    $asunto = "Recordatorio de Mantenimiento: {$tipoServicio} - " . $configEmpresa['nombre_empresa'];
    
    $colorPrincipal = $configEmpresa['apariencia']['color_primario'];
    $colorSecundario = $configEmpresa['apariencia']['color_secundario'];
    
    $html = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;'>
        <div style='background-color: {$colorPrincipal}; padding: 20px; text-align: center; color: white;'>
            <h2 style='margin: 0;'>Aviso de Mantenimiento</h2>
        </div>
        <div style='padding: 20px; color: #333;'>
            <p>Hola <strong>{$nombreCliente}</strong>,</p>
            <p>Te escribimos desde <strong>{$configEmpresa['nombre_empresa']}</strong> para recordarte que es momento de realizar el servicio de <strong style='color: {$colorSecundario}'>{$tipoServicio}</strong> a tu vehículo <strong>{$vehiculo}</strong>.</p>
            <p>Mantener los servicios al día es vital para prolongar la vida útil de tu unidad y prevenir daños mayores.</p>
            <p>Por favor, comunícate con nosotros para agendar tu turno.</p>
            <br>
            <p>Saludos cordiales,<br>El equipo de {$configEmpresa['nombre_empresa']}</p>
        </div>
    </div>";

    return enviarCorreoBase($emailDestino, $nombreCliente, $asunto, $html, $configEmpresa);
}
?>