<?php
/**
 * Lizzosoft Vehículos - Motor Automático de Alertas y Reintentos
 * Ubicación: lizzosoft_vehiculos/CRUD_Alertas/procesar_alertas.php
 * * INSTRUCCIONES: Configurar este archivo en el CRON del servidor para ejecutarse cada hora.
 */

// Forzar zona horaria local para corregir el desfasaje del servidor
date_default_timezone_set('America/Argentina/Buenos_Aires');

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../Conexion/Conexion.php';
require_once __DIR__ . '/../Conexion/Mailer.php';

$conexion = obtenerConexion();
$is_ajax = isset($_GET['ajax']) ? true : false;
$id_alerta_filtrar = isset($_GET['id_alerta']) ? (int)$_GET['id_alerta'] : 0;
$simular = isset($_GET['simular']) ? true : false;

function ejecutarMotorAlertas($conexion, $simular = false, $id_alerta_filtrar = 0) {
    $hoy = new DateTime();
    $resumen = [
        'enviados' => 0,
        'ya_notificados' => 0,
        'fallidos' => 0,
        'programados' => 0,
        'sin_email' => 0
    ];
    
    $detalle_alertas = [];
    $output = "";
    if (php_sapi_name() === 'cli') {
        $output .= "=== INICIANDO PROCESAMIENTO DE ALERTAS (" . date('d/m/Y H:i:s') . ") ===\n\n";
    }

    try {
        if ($id_alerta_filtrar > 0) {
            $stmtAlertas = $conexion->prepare("SELECT * FROM alertas_servicios WHERE estado = 'Activo' AND IDAlerta = ?");
            $stmtAlertas->execute([$id_alerta_filtrar]);
        } else {
            $stmtAlertas = $conexion->prepare("SELECT * FROM alertas_servicios WHERE estado = 'Activo'");
            $stmtAlertas->execute();
        }

        $alertas = $stmtAlertas->fetchAll(PDO::FETCH_ASSOC);

        if (empty($alertas)) {
            $output .= "[INFO] No hay alertas activas configuradas en el sistema.\n";
            return ['status' => 'success', 'resumen' => $resumen, 'detalle_alertas' => [], 'output' => $output];
        }

        foreach ($alertas as $alerta) {
            $idAlerta = $alerta['IDAlerta'];
            $detalle_alertas[$idAlerta] = ['en_cola' => 0, 'enviados_recientes' => 0, 'programadas' => 0];

            $output .= "-------------------------------------------------------------------\n";
            $output .= "-> Evaluando Alerta ID: {$idAlerta} | '{$alerta['nombreAlerta']}' | Recordatorio: {$alerta['diasRecordatorio']} dias\n";
            $empresa_id = $alerta['empresa_id'];
            
            $stmtEmpresa = $conexion->prepare("SELECT nombre FROM empresas WHERE id = ?");
            $stmtEmpresa->execute([$empresa_id]);
            $nombreEmpresaReal = $stmtEmpresa->fetchColumn() ?: 'Taller Automotriz';

            $configEmpresa = [
                'nombre_empresa' => $nombreEmpresaReal,
                'apariencia' => [
                    'color_primario' => '#2c3e50',
                    'color_secundario' => '#e74c3c'
                ]
            ];

            $queryOTs = "
                SELECT v.IDVehiculo, v.patente, v.marca, v.modelo, c.nombre, c.apellido, c.email, 
                       MAX(COALESCE(rs.fechaFin, rs.fechaRegistroServicio)) AS ultimaFecha, s.nombreServicio
                FROM detalleregistro dr
                JOIN registrosservicios rs ON dr.IDRegistroServicio = rs.IDRegistroServicio
                JOIN vehiculos v ON rs.IDVehiculo = v.IDVehiculo
                JOIN clientes c ON v.IDCliente = c.IDCliente
                JOIN servicios s ON dr.IDServicio = s.IDServicio
                WHERE dr.IDServicio = ? 
                  AND rs.empresa_id = ? 
                  AND rs.IDEstado IN (4, 5)
                GROUP BY v.IDVehiculo
            ";
            $stmtOTs = $conexion->prepare($queryOTs);
            $stmtOTs->execute([$alerta['IDServicio'], $empresa_id]);
            $vehiculosEnCiclo = $stmtOTs->fetchAll(PDO::FETCH_ASSOC);

            if (empty($vehiculosEnCiclo)) {
                $output .= "   [Omitido] No existen vehículos con este servicio en estado Finalizado/Entregado.\n";
                continue;
            }

            foreach ($vehiculosEnCiclo as $veh) {
                if (empty($veh['email'])) {
                    $resumen['sin_email']++;
                    $output .= "   [Omitido] Vehículo {$veh['patente']} carece de un correo electrónico válido.\n";
                    continue;
                }

                $ultimaFecha = new DateTime($veh['ultimaFecha']);
                
                $fechaProyectada = clone $ultimaFecha;
                $fechaProyectada->modify("+" . $alerta['diasRecordatorio'] . " days");

                $stmtExito = $conexion->prepare("SELECT fechaEnvio FROM envios_alertas_log WHERE IDAlerta = ? AND IDVehiculo = ? AND fechaEnvio >= ? AND estadoEnvio = 'Enviado' LIMIT 1");
                $stmtExito->execute([$alerta['IDAlerta'], $veh['IDVehiculo'], $veh['ultimaFecha']]);
                $fechaEnvioExito = $stmtExito->fetchColumn();

                if ($fechaEnvioExito) {
                    $diasTranscurridos = (time() - strtotime($fechaEnvioExito)) / 86400;
                    if ($diasTranscurridos <= 30) {
                        $resumen['ya_notificados']++;
                        $detalle_alertas[$idAlerta]['enviados_recientes']++;
                    }
                    $output .= "   [OK] Vehículo {$veh['patente']} ya recibió correctamente esta alerta para su último servicio.\n";
                    continue; 
                }

                $stmtIntento = $conexion->prepare("SELECT fechaEnvio, estadoEnvio FROM envios_alertas_log WHERE IDAlerta = ? AND IDVehiculo = ? AND fechaEnvio >= ? ORDER BY fechaEnvio DESC LIMIT 1");
                $stmtIntento->execute([$alerta['IDAlerta'], $veh['IDVehiculo'], $veh['ultimaFecha']]);
                $ultimoIntento = $stmtIntento->fetch(PDO::FETCH_ASSOC);

                if ($ultimoIntento && $ultimoIntento['estadoEnvio'] === 'Fallido') {
                    $horasTranscurridas = (time() - strtotime($ultimoIntento['fechaEnvio'])) / 3600;
                    if ($horasTranscurridas < 1) {
                        $resumen['fallidos']++;
                        $output .= "   [Espera] Vehículo {$veh['patente']} falló hace menos de 1 hora. Reintento programado para la próxima ejecución.\n";
                        continue;
                    }
                }

                if ($fechaProyectada <= $hoy) {
                    $detalle_alertas[$idAlerta]['en_cola']++;
                    
                    if ($simular) {
                        $resumen['enviados']++; // Lo sumamos como "por enviar" en la simulación
                        $output .= "   [SIMULACIÓN] Se enviaría alerta a {$veh['patente']} (Cliente: {$veh['nombre']}).\n";
                        continue;
                    }

                    $output .= "   [ENVIANDO] Procesando envío para {$veh['patente']} (Cliente: {$veh['nombre']})... ";

                    $cuerpo = $alerta['plantillaMensaje'];
                    $cuerpo = str_replace('[CLIENTE_NOMBRE]', $veh['nombre'] ?? '', $cuerpo);
                    $cuerpo = str_replace('[CLIENTE_APELLIDO]', $veh['apellido'] ?? '', $cuerpo);
                    $cuerpo = str_replace('[VEHICULO_MARCA]', $veh['marca'] ?? '', $cuerpo);
                    $cuerpo = str_replace('[VEHICULO_MODELO]', $veh['modelo'] ?? '', $cuerpo);
                    $cuerpo = str_replace('[VEHICULO_PATENTE]', $veh['patente'] ?? '', $cuerpo);
                    $cuerpo = str_replace('[SERVICIO_NOMBRE]', $veh['nombreServicio'] ?? '', $cuerpo);
                    $cuerpo = str_replace('[FECHA_ULTIMO_SERVICIO]', date('d/m/Y', strtotime($veh['ultimaFecha'])), $cuerpo);

                    $htmlMensaje = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;'>
                        <div style='background-color: #2c3e50; padding: 20px; text-align: center; color: white;'>
                            <h2 style='margin: 0;'>" . htmlspecialchars($nombreEmpresaReal) . "</h2>
                        </div>
                        <div style='padding: 25px; color: #333; line-height: 1.6; font-size: 15px;'>
                            " . nl2br($cuerpo) . "
                        </div>
                    </div>";

                    $resultadoEnvio = enviarCorreoBase(
                        $veh['email'], 
                        $veh['nombre'] . ' ' . $veh['apellido'], 
                        $alerta['asuntoMensaje'], 
                        $htmlMensaje, 
                        $configEmpresa
                    );

                    $estadoLog = $resultadoEnvio ? 'Enviado' : 'Fallido';
                    $detalleLog = $resultadoEnvio ? 'Envío exitoso sin errores.' : 'Error SMTP: Conexión rechazada o credenciales inválidas.';

                    $fechaActualStr = date('Y-m-d H:i:s');
                    $stmtLog = $conexion->prepare("INSERT INTO envios_alertas_log (IDAlerta, IDVehiculo, fechaEnvio, estadoEnvio, detalle_error) VALUES (?, ?, ?, ?, ?)");
                    $stmtLog->execute([$alerta['IDAlerta'], $veh['IDVehiculo'], $fechaActualStr, $estadoLog, $detalleLog]);

                    if ($resultadoEnvio) {
                        $resumen['enviados']++;
                        $output .= "¡Éxito!\n";
                    } else {
                        $resumen['fallidos']++;
                        $output .= "[FALLO SMTP]\n";
                    }

                } else {
                    $resumen['programados']++;
                    $detalle_alertas[$idAlerta]['programadas']++;
                    $output .= "   [Programada] Vehículo {$veh['patente']} no cumple la fecha. Proyectado para: " . $fechaProyectada->format('d/m/Y H:i:s') . "\n";
                }
            }
        }
        
        $output .= "\n=== PROCESAMIENTO FINALIZADO ===\n";
        return ['status' => 'success', 'resumen' => $resumen, 'detalle_alertas' => $detalle_alertas, 'output' => $output];
        
    } catch (Exception $e) {
        $output .= "\n[ERROR CRÍTICO GLOBAL] " . $e->getMessage() . "\n";
        return ['status' => 'error', 'message' => $e->getMessage(), 'output' => $output];
    }
}

// Ejecución directa o vía API
if (!defined('INCLUIDO_COMO_LIBRERIA')) {
    $resultado = ejecutarMotorAlertas($conexion, $simular, $id_alerta_filtrar);

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit;
    } else {
        if (php_sapi_name() === 'cli') {
            echo "<pre>" . $resultado['output'] . "</pre>";
        } else {
            echo "<div style='font-family: monospace; background: #222; color: #0f0; padding: 20px; border-radius: 5px; height: 100vh; overflow-y: auto;'>";
            echo nl2br(htmlspecialchars($resultado['output']));
            echo "<br><br><a href='listar_alertas.php' style='display:inline-block; padding:10px 20px; background:#28a745; color:#fff; text-decoration:none; border-radius:4px;'>Volver a Alertas</a>";
            echo "</div>";
        }
    }
}
?>