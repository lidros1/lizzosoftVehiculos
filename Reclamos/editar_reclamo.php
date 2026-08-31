<?php
/**
 * Lizzosoft Vehículos - Editar Reclamo
 * Ubicación: lizzosoft_vehiculos/Reclamos/editar_reclamo.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$config     = $_SESSION['cliente_config'];
$apariencia = $config['apariencia'];
$empresa_id = (int)$_SESSION['empresa_id'];
$sucursal_id= (int)$_SESSION['sucursal_id'];
$temaActual = $_SESSION['tema_preferido'] ?? 'claro'; // Variable para Modo Oscuro

$idReclamo = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idReclamo <= 0) { header("Location: menuReclamos.php"); exit; }

$conexion = obtenerConexion();
$mensaje = '';
$tipoMensaje = '';

// -------------------------------------------------------------
// PROCESAMIENTO POST: ACTUALIZACIÓN DEL RECLAMO
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_reclamo'])) {
    $obsGeneral = trim($_POST['observacionGeneral'] ?? '');
    
    // Arrays con los datos enviados por el usuario
    $serviciosSeleccionados = $_POST['servicios'] ?? [];
    $obsDetalles = $_POST['observaciones_srv'] ?? [];

    if (empty($serviciosSeleccionados)) {
        $mensaje = "Debe seleccionar al menos un servicio para mantener el reclamo abierto.";
        $tipoMensaje = "error";
    } else {
        try {
            $conexion->beginTransaction();

            // 1. Actualizar SOLO la observación general del reclamo (Estado no es editable)
            $stmtUpd = $conexion->prepare("UPDATE reclamos SET observacionReclamo = ? WHERE IDReclamo = ? AND empresa_id = ? AND sucursal_id = ?");
            $stmtUpd->execute([$obsGeneral, $idReclamo, $empresa_id, $sucursal_id]);

            // 2. Extraer los IDs de los servicios que YA están en la base de datos
            $stmtExist = $conexion->prepare("SELECT IDServicio FROM detalle_reclamos WHERE IDReclamo = ?");
            $stmtExist->execute([$idReclamo]);
            $currentServices = $stmtExist->fetchAll(PDO::FETCH_COLUMN);

            // 3. Motor de Diff: Calcular Altas, Bajas y Actualizaciones
            $toInsert = array_diff($serviciosSeleccionados, $currentServices);
            $toDelete = array_diff($currentServices, $serviciosSeleccionados);
            $toUpdate = array_intersect($serviciosSeleccionados, $currentServices);

            // EJECUTAR BAJAS (Servicios que se deseleccionaron)
            if (!empty($toDelete)) {
                $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
                $paramsDel = array_merge([$idReclamo], $toDelete);
                $stmtDel = $conexion->prepare("DELETE FROM detalle_reclamos WHERE IDReclamo = ? AND IDServicio IN ($placeholders)");
                $stmtDel->execute($paramsDel);
            }

            // EJECUTAR ALTAS (Servicios nuevos marcados)
            if (!empty($toInsert)) {
                $stmtIns = $conexion->prepare("INSERT INTO detalle_reclamos (IDReclamo, IDServicio, observacionDetalleReclamo) VALUES (?, ?, ?)");
                foreach ($toInsert as $idSrv) {
                    $stmtIns->execute([$idReclamo, $idSrv, trim($obsDetalles[$idSrv] ?? '')]);
                }
            }

            // EJECUTAR ACTUALIZACIONES (Servicios que ya estaban y siguen marcados)
            if (!empty($toUpdate)) {
                $stmtUpdDet = $conexion->prepare("UPDATE detalle_reclamos SET observacionDetalleReclamo = ? WHERE IDReclamo = ? AND IDServicio = ?");
                foreach ($toUpdate as $idSrv) {
                    $stmtUpdDet->execute([trim($obsDetalles[$idSrv] ?? ''), $idReclamo, $idSrv]);
                }
            }

            $stmtLog = $conexion->prepare("INSERT INTO logs_accesos (IDUsuario, nombreUsuario, accion, fecha_hora, empresa_id, sucursal_id) VALUES (?, ?, ?, NOW(), ?, ?)");
            $stmtLog->execute([$_SESSION['IDUsuario'], $_SESSION['nombreUsuario'], 'EDITAR_RECLAMO', $empresa_id, $sucursal_id]);

            $conexion->commit();
            $mensaje = "Reclamo actualizado exitosamente.";
            $tipoMensaje = "success";

        } catch (Exception $e) {
            $conexion->rollBack();
            $mensaje = "Error al actualizar el reclamo: " . $e->getMessage();
            $tipoMensaje = "error";
        }
    }
}

// -------------------------------------------------------------
// CARGA DE DATOS DEL RECLAMO PARA MOSTRAR EN LA VISTA
// -------------------------------------------------------------
try {
    // Datos maestros del Reclamo, la OT Original y el Cliente
    $stmtR = $conexion->prepare("
        SELECT r.*, rs.numeroOrdenTrabajo, rs.fechaRegistroServicio, 
               v.patente, c.nombre, c.apellido, c.numeroDocumentoCliente
        FROM reclamos r
        INNER JOIN registrosservicios rs ON r.IDRegistroServicioOriginal = rs.IDRegistroServicio
        INNER JOIN vehiculos v ON rs.IDVehiculo = v.IDVehiculo
        INNER JOIN clientes c ON v.IDCliente = c.IDCliente
        WHERE r.IDReclamo = ? AND r.empresa_id = ? AND r.sucursal_id = ?
    ");
    $stmtR->execute([$idReclamo, $empresa_id, $sucursal_id]);
    $reclamo = $stmtR->fetch(PDO::FETCH_ASSOC);

    if (!$reclamo) die("Reclamo no encontrado en el sistema o no pertenece a tu sucursal.");

    // Traer TODOS los servicios de la OT Original para permitir re-seleccionar
    $stmtDetOT = $conexion->prepare("
        SELECT dr.IDServicio, s.nombreServicio
        FROM detalleregistro dr
        INNER JOIN servicios s ON dr.IDServicio = s.IDServicio
        WHERE dr.IDRegistroServicio = ?
    ");
    $stmtDetOT->execute([$reclamo['IDRegistroServicioOriginal']]);
    $todosServiciosOT = $stmtDetOT->fetchAll(PDO::FETCH_ASSOC);

    // Mapear los servicios ACTUALMENTE asociados a este reclamo (para pre-marcarlos)
    $stmtDetRec = $conexion->prepare("
        SELECT IDServicio, observacionDetalleReclamo
        FROM detalle_reclamos
        WHERE IDReclamo = ?
    ");
    $stmtDetRec->execute([$idReclamo]);
    $serviciosReclamadosMap = [];
    while($row = $stmtDetRec->fetch(PDO::FETCH_ASSOC)) {
        $serviciosReclamadosMap[$row['IDServicio']] = $row['observacionDetalleReclamo'];
    }

} catch (Exception $e) {
    die("Error crítico al extraer datos de origen.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Reclamo - <?php echo htmlspecialchars($config['nombre_empresa']); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --color-primario: <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>; --color-secundario: <?php echo htmlspecialchars($apariencia['color_secundario'] ?? '#e74c3c'); ?>; --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>; --sidebar-width: 270px; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; background-color: var(--color-fondo); color: #333; display: flex; height: 100vh; overflow: hidden; }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar { background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); flex-shrink: 0; }
        .user-info { font-size: 13px; font-weight: 500; color: #666; }
        .btn-logout { color: var(--color-secundario); text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid var(--color-secundario); padding: 5px 15px; border-radius: 4px; transition: all 0.2s; }
        .btn-logout:hover { background: var(--color-secundario); color: #fff; }
        
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; max-width: 900px; margin-left: auto; margin-right: auto;}
        .panel-title { margin: 0; font-size: 22px; color: var(--color-primario); font-weight: 600; }
        
        .box { background: #fff; padding: 25px; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); border: 1px solid #eef0f2; margin-bottom: 20px; max-width: 900px; margin-left: auto; margin-right: auto;}
        
        .btn { background: var(--color-primario); color: white; border: none; padding: 12px 20px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; transition: opacity 0.2s;}
        .btn:hover { opacity: 0.9; }
        .btn-cancel { background: #e2e8f0; color: #333; }
        .btn-submit { background: #e74c3c; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 8px; font-size: 13px; color: #444; }
        .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; font-family: inherit; resize: vertical; min-height: 100px; box-sizing: border-box; }
        
        .srv-reclamo { display: flex; align-items: flex-start; gap: 15px; padding: 15px; border: 1px solid #eee; border-radius: 6px; margin-bottom: 15px; background: #fafafa; cursor: pointer; transition: border-color 0.2s;}
        .srv-reclamo:hover { border-color: var(--color-primario); }
        .srv-check { margin-top: 4px; transform: scale(1.2); cursor: pointer; }
        .srv-content { flex: 1; }
        .srv-title { font-weight: bold; color: var(--color-primario); font-size: 14px; margin-bottom: 8px; }
        .srv-textarea { width: 100%; height: 60px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; resize: none; font-size: 13px; font-family: inherit; box-sizing: border-box;}
        .srv-textarea:focus { border-color: var(--color-primario); outline: none; }
    </style>
    
    <link rel="stylesheet" href="../CSS/modo_oscuro.css?v=<?php echo time(); ?>">
</head>
<body class="<?php echo $temaActual === 'oscuro' ? 'tema-oscuro' : ''; ?>">

    <?php 
        $basePath = '../'; 
        include __DIR__ . '/../HTML/sidebar.php'; 
    ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../HTML/topbar.php'; ?>

        <main class="content-area">
        <div class="panel-header">
            <h1 class="panel-title">Editar Reclamo #<?php echo $idReclamo; ?></h1>
            <a href="menuReclamos.php" class="btn btn-cancel" style="padding: 8px 15px; font-size: 12px;">Volver</a>
        </div>

        <div class="box info-card" style="background: #fdfdfd; border-left: 4px solid var(--color-primario);">
            <h3 class="ic-value" style="margin-top: 0; font-size: 16px; color: var(--color-primario);">Referencia OT: #<?php echo str_pad($reclamo['numeroOrdenTrabajo'], 8, '0', STR_PAD_LEFT); ?></h3>
            <p class="ic-sub" style="margin: 0; font-size: 14px; color: #555;">
                <strong>Cliente:</strong> <?php echo htmlspecialchars($reclamo['apellido'] . ', ' . $reclamo['nombre']); ?> (DNI: <?php echo htmlspecialchars($reclamo['numeroDocumentoCliente']); ?>) <br> 
                <strong>Vehículo:</strong> <?php echo htmlspecialchars($reclamo['patente']); ?> <br>
                <strong>Fecha Original OT:</strong> <?php echo date('d/m/Y', strtotime($reclamo['fechaRegistroServicio'])); ?> <br>
                <strong>Estado del Reclamo:</strong> <span style="font-weight:bold; color:var(--color-secundario);"><?php echo htmlspecialchars($reclamo['estadoReclamo'] ?: 'Pendiente'); ?></span>
            </p>
        </div>

        <form method="POST">
            <div class="box">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Observación General del Problema</label>
                    <textarea name="observacionGeneral" required><?php echo htmlspecialchars($reclamo['observacionReclamo']); ?></textarea>
                </div>
            </div>

            <div class="box step-container">
                <h3 class="step-header" style="margin-top: 0; font-size: 16px; color: var(--color-primario); margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Servicios Realizados (Seleccione los que entran en reclamo)</h3>
                
                <?php if(empty($todosServiciosOT)): ?>
                    <p style="color: #e74c3c; font-size: 14px;">La orden original no posee servicios registrados.</p>
                <?php else: ?>
                    <?php foreach ($todosServiciosOT as $det): ?>
                        <?php 
                            $idSrv = $det['IDServicio'];
                            $estaReclamado = isset($serviciosReclamadosMap[$idSrv]);
                            $obsSrv = $estaReclamado ? $serviciosReclamadosMap[$idSrv] : '';
                        ?>
                        <label class="srv-reclamo" for="srv_<?php echo $idSrv; ?>">
                            <input type="checkbox" name="servicios[]" value="<?php echo $idSrv; ?>" id="srv_<?php echo $idSrv; ?>" class="srv-check" <?php echo $estaReclamado ? 'checked' : ''; ?>>
                            <div class="srv-content">
                                <div class="srv-title"><?php echo htmlspecialchars($det['nombreServicio']); ?></div>
                                <textarea name="observaciones_srv[<?php echo $idSrv; ?>]" class="srv-textarea" placeholder="Describa el problema específico con este servicio (opcional)..."><?php echo htmlspecialchars($obsSrv); ?></textarea>
                            </div>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div style="text-align: right; max-width: 900px; margin: 0 auto 30px auto;">
                <a href="menuReclamos.php" class="btn btn-cancel">Volver</a>
                <button type="submit" name="editar_reclamo" class="btn btn-submit">Guardar Cambios</button>
            </div>
        </form>

        </main>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            <?php if (!empty($mensaje)): ?>
                Swal.fire({
                    icon: '<?php echo $tipoMensaje; ?>',
                    title: '<?php echo $tipoMensaje === "success" ? "¡Éxito!" : "Atención"; ?>',
                    text: '<?php echo addslashes($mensaje); ?>',
                    confirmButtonColor: 'var(--color-primario)',
                    heightAuto: false, scrollbarPadding: false
                }).then(() => {
                    <?php if ($tipoMensaje === "success"): ?>
                        window.location.href = 'menuReclamos.php';
                    <?php endif; ?>
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>