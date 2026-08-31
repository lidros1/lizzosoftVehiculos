<?php
/**
 * Lizzosoft Vehículos - Ver Reclamo
 * Ubicación: lizzosoft_vehiculos/Reclamos/ver_reclamo.php
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

try {
    $stmtR = $conexion->prepare("
        SELECT r.*, rs.numeroOrdenTrabajo, rs.fechaRegistroServicio, 
               v.patente, v.marca, v.modelo,
               c.nombre, c.apellido, c.numeroDocumentoCliente, c.telefono, td.tipoDocumento
        FROM reclamos r
        INNER JOIN registrosservicios rs ON r.IDRegistroServicioOriginal = rs.IDRegistroServicio
        INNER JOIN vehiculos v ON rs.IDVehiculo = v.IDVehiculo
        INNER JOIN clientes c ON v.IDCliente = c.IDCliente
        INNER JOIN tiposdocumentos td ON c.IDTipoDocumento = td.IDTipoDocumento
        WHERE r.IDReclamo = ? AND r.empresa_id = ? AND r.sucursal_id = ? AND rs.sucursal_id = ?
    ");
    $stmtR->execute([$idReclamo, $empresa_id, $sucursal_id, $sucursal_id]);
    $reclamo = $stmtR->fetch(PDO::FETCH_ASSOC);

    if (!$reclamo) die("Reclamo no encontrado en el sistema.");

    $stmtDet = $conexion->prepare("
        SELECT dr.*, s.nombreServicio
        FROM detalle_reclamos dr
        INNER JOIN servicios s ON dr.IDServicio = s.IDServicio
        WHERE dr.IDReclamo = ?
    ");
    $stmtDet->execute([$idReclamo]);
    $serviciosReclamados = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error crítico al extraer datos de origen.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha de Reclamo</title>
    <style>
        :root { --cp: <?php echo htmlspecialchars($apariencia['color_primario']); ?>; --cf: <?php echo htmlspecialchars($apariencia['color_fondo']); ?>; --bc: #dee2e6; --sidebar-width: 270px;}
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: var(--cf); margin: 0; color: #333; display: flex; height: 100vh; overflow: hidden; }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar { background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); flex-shrink: 0; }
        .user-info { font-size: 13px; font-weight: 500; color: #666; }
        .btn-logout { color: #e74c3c; text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid #e74c3c; padding: 5px 15px; border-radius: 4px; transition: all 0.2s; }
        .btn-logout:hover { background: #e74c3c; color: #fff; }
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        
        .wrapper { background: #fff; max-width: 1100px; margin: 0 auto; padding: 35px; border-radius: 10px; box-shadow: 0 8px 20px rgba(0,0,0,.06); }

        .header-box { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--cf); padding-bottom: 15px; margin-bottom: 25px; flex-wrap: wrap; gap: 12px; }
        h2 { margin: 0; color: #e74c3c; font-size: 24px; }

        .btn { display: inline-block; padding: 10px 22px; font-size: 13px; font-weight: bold; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; text-align: center; }
        .btn-back  { background: #e2e8f0; color: #333; }
        
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .info-card { background: #f8f9fa; border: 1px solid var(--bc); border-radius: 8px; padding: 18px; border-left: 5px solid var(--cp); }
        .ic-title { font-size: 11px; text-transform: uppercase; color: #777; font-weight: bold; margin-bottom: 8px; letter-spacing: .5px; }
        .ic-value { font-size: 16px; color: #333; font-weight: 700; }
        .ic-sub   { font-size: 13px; color: #666; margin-top: 6px; }

        .step-container { border: 1px solid var(--bc); border-radius: 8px; margin-bottom: 25px; overflow: hidden; }
        .step-header { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid var(--bc); font-weight: bold; color: var(--cp); font-size: 15px; display: flex; align-items: center; gap: 10px; }
        .step-body { padding: 25px; }

        .field-value { width: 100%; padding: 12px; border: 1px solid var(--bc); border-radius: 6px; box-sizing: border-box; font-family: inherit; font-size: 14px; background: #fafbfc; color: #333; line-height: 1.6; white-space: pre-wrap; margin-bottom: 15px; }

        table { width: 100%; border-collapse: collapse; border: 1px solid var(--bc); font-size: 13px; }
        th { background: #f8f9fa; font-weight: bold; text-transform: uppercase; color: #555; font-size: 12px; padding: 12px; border-bottom: 1px solid var(--bc); text-align: left; }
        td { padding: 14px 12px; border-bottom: 1px solid var(--bc); vertical-align: top; }
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

<div class="wrapper table-card">
    <div class="header-box">
        <h2>Ficha de Reclamo #<?php echo $idReclamo; ?></h2>
        <a href="menuReclamos.php" class="btn btn-back">Volver</a>
    </div>

    <div class="info-grid">
        <div class="info-card">
            <div class="ic-title">Titular de la Orden</div>
            <div class="ic-value"><?php echo htmlspecialchars($reclamo['apellido'] . ', ' . $reclamo['nombre']); ?></div>
            <div class="ic-sub"><?php echo htmlspecialchars($reclamo['tipoDocumento']); ?>: <?php echo htmlspecialchars($reclamo['numeroDocumentoCliente']); ?></div>
        </div>
        <div class="info-card">
            <div class="ic-title">Vehículo</div>
            <div class="ic-value"><?php echo htmlspecialchars($reclamo['patente']); ?></div>
            <div class="ic-sub"><?php echo htmlspecialchars(($reclamo['marca'] ?? '') . ' ' . ($reclamo['modelo'] ?? '')); ?></div>
        </div>
        <div class="info-card" style="border-left-color:#e74c3c;">
            <div class="ic-title">Estado del Reclamo</div>
            <div class="ic-value" style="color:#e74c3c;"><?php echo htmlspecialchars($reclamo['estadoReclamo'] ?: 'Pendiente'); ?></div>
            <div class="ic-sub">Registrado: <?php echo date('d/m/Y H:i', strtotime($reclamo['fechaReclamo'])); ?></div>
        </div>
    </div>

    <div class="step-container info-card" style="padding:0; border-left:none;">
        <div class="step-header">Detalle del Problema (Observación General)</div>
        <div class="step-body">
            <div class="field-value"><?php echo htmlspecialchars($reclamo['observacionReclamo']); ?></div>
            <span style="font-size: 12px; color: #666;"><strong>Referencia OT Original:</strong> #<?php echo str_pad($reclamo['numeroOrdenTrabajo'], 8, '0', STR_PAD_LEFT); ?> (Generada el <?php echo date('d/m/Y', strtotime($reclamo['fechaRegistroServicio'])); ?>)</span>
        </div>
    </div>

    <div class="step-container info-card" style="padding:0; border-left:none;">
        <div class="step-header">Servicios Sometidos a Garantía / Revisión</div>
        <div class="step-body">
            <?php if (empty($serviciosReclamados)): ?>
                <p style="color:#888;font-size:14px;">No hay servicios específicos seleccionados en este reclamo.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width:30%;">Servicio Operativo</th>
                            <th style="width:70%;">Observación Específica del Cliente / Taller</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($serviciosReclamados as $srv): ?>
                        <tr>
                            <td><strong style="color:var(--cp);"><?php echo htmlspecialchars($srv['nombreServicio']); ?></strong></td>
                            <td style="color:#555;"><?php echo nl2br(htmlspecialchars($srv['observacionDetalleReclamo'] ?: 'Sin observaciones específicas.')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
        </main>
    </div>
    
    <script>
        /* JS del sidebar movido a HTML/sidebar.php */
    </script>
</body>
</html>