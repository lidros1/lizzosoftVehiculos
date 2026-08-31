<?php
/**
 * Lizzosoft Vehículos - Visualizar y Exportar Orden de Trabajo
 * Ubicación: lizzosoft_vehiculos/CRUD_Ordenes/ver_orden.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$config     = $_SESSION['cliente_config'];
$apariencia = $config['apariencia'];
$empresa_id = (int)$_SESSION['empresa_id_usuario'];

$idRegistro = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idRegistro <= 0) { header("Location: ../inicio.php"); exit; }

$conexion = obtenerConexion();

try {
    $stmtOT = $conexion->prepare("
        SELECT rs.*, es.nombreEstadoSolicitud,
               v.patente, v.marca, v.modelo, v.colorVehiculo, v.anioFabricacion,
               v.numeroMotor, v.numeroChasis,
               c.nombre, c.apellido, c.numeroDocumentoCliente, c.telefono,
               td.tipoDocumento,
               p.nombre AS emp_nombre, p.apellido AS emp_apellido
        FROM registrosservicios rs
        INNER JOIN vehiculos v   ON rs.IDVehiculo = v.IDVehiculo
        INNER JOIN clientes c    ON v.IDCliente   = c.IDCliente
        INNER JOIN tiposdocumentos td ON c.IDTipoDocumento = td.IDTipoDocumento
        INNER JOIN estadossolicitud es ON rs.IDEstado = es.IDEstadoSolicitud
        LEFT  JOIN personal p    ON rs.numeroDocumentoPersonal = p.numeroDocumentoPersonal
                                 AND p.empresa_id = rs.empresa_id
        WHERE rs.IDRegistroServicio = ? AND rs.empresa_id = ?
    ");
    $stmtOT->execute([$idRegistro, $empresa_id]);
    $orden = $stmtOT->fetch(PDO::FETCH_ASSOC);

    if (!$orden) die("Ficha de orden no localizada en el sistema.");

    $stmtDet = $conexion->prepare("
        SELECT dr.*, s.nombreServicio
        FROM detalleregistro dr
        INNER JOIN servicios s ON dr.IDServicio = s.IDServicio
        WHERE dr.IDRegistroServicio = ?
    ");
    $stmtDet->execute([$idRegistro]);
    $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    $totalOrden = 0;
    foreach ($detalles as $d) { $totalOrden += $d['costoServicio']; }

    $rubroEmpresa = '';
    try {
        $stmtR = $conexion->prepare("
            SELECT r.nombreRubro FROM empresas e
            INNER JOIN rubros r ON e.IDRubro = r.IDRubro
            WHERE e.empresa_id = ?
        ");
        $stmtR->execute([$empresa_id]);
        $rubroEmpresa = $stmtR->fetchColumn() ?: '';
    } catch (Exception $e) { $rubroEmpresa = ''; }

    $stmtSuc = $conexion->prepare("SELECT COUNT(*) FROM sucursales WHERE empresa_id = ?");
    $stmtSuc->execute([$empresa_id]);
    $total_sucursales = (int)$stmtSuc->fetchColumn();

} catch (Exception $e) {
    die("Error crítico al extraer datos de origen.");
}

$apellidoLimpio = preg_replace('/[^A-Za-z0-9]/', '', $orden['apellido']);
$nombreLimpio   = preg_replace('/[^A-Za-z0-9]/', '', $orden['nombre']);
$nombreArchivo  = $apellidoLimpio . '_' . $nombreLimpio . '_' . $orden['numeroOrdenTrabajo'] . '_' . $orden['numeroDocumentoCliente'];
$numeroOrdenPad = str_pad($orden['numeroOrdenTrabajo'], 8, '0', STR_PAD_LEFT);
$fechaFormateada = date('d/m/Y', strtotime($orden['fechaRegistroServicio']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden #<?php echo $numeroOrdenPad; ?></title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        /* ══════════════════════════════════════════
           VISTA INTERNA (WEB)
        ══════════════════════════════════════════ */
        :root { --cp: <?php echo htmlspecialchars($apariencia['color_primario']); ?>; --cf: <?php echo htmlspecialchars($apariencia['color_fondo']); ?>; --bc: #dee2e6; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: var(--cf); margin: 0; color: #333; display: flex; height: 100vh; overflow: hidden; }

        /* MAIN WRAPPER & TOPBAR */
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar { background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); flex-shrink: 0; z-index: 10; border-bottom: 1px solid #eef0f2; }
        .user-info { font-size: 13px; font-weight: 500; color: #666; }
        .btn-logout { color: #e74c3c; text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid #e74c3c; padding: 5px 15px; border-radius: 4px; transition: all 0.2s; }
        .btn-logout:hover { background: #e74c3c; color: #fff; }
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }

        .wrapper { background: #fff; max-width: 1100px; margin: 0 auto; padding: 35px; border-radius: 10px; box-shadow: 0 8px 20px rgba(0,0,0,.06); }

        .header-box { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--cf); padding-bottom: 15px; margin-bottom: 25px; flex-wrap: wrap; gap: 12px; }
        h2 { margin: 0; color: var(--cp); font-size: 24px; }

        .btn { display: inline-block; padding: 10px 22px; font-size: 13px; font-weight: bold; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; text-align: center; }
        .btn-back  { background: #e2e8f0; color: #333; }
        .btn-pdf   { background: #fff; color: #0d6efd; border: 1px solid #0d6efd; }
        .btn-pdf:hover { background: #e8f0fe; }
        .btn-print { background: var(--cp); color: #fff; }
        .btn-print:hover { opacity: .9; }
        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; }

        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .info-card { background: #f8f9fa; border: 1px solid var(--bc); border-radius: 8px; padding: 18px; border-left: 5px solid var(--cp); }
        .ic-title { font-size: 11px; text-transform: uppercase; color: #777; font-weight: bold; margin-bottom: 8px; letter-spacing: .5px; }
        .ic-value { font-size: 16px; color: #333; font-weight: 700; }
        .ic-sub   { font-size: 13px; color: #666; margin-top: 6px; }

        .step-container { border: 1px solid var(--bc); border-radius: 8px; margin-bottom: 25px; overflow: hidden; }
        .step-header { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid var(--bc); font-weight: bold; color: var(--cp); font-size: 15px; display: flex; align-items: center; gap: 10px; }
        .step-num { background: var(--cp); color: #fff; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 12px; flex-shrink: 0; }
        .step-body { padding: 25px; }

        .field-row { display: flex; gap: 25px; margin-bottom: 15px; flex-wrap: wrap; }
        .field-group { flex: 1; min-width: 200px; }
        .field-label { display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 6px; color: #555; }
        .field-value { width: 100%; padding: 12px; border: 1px solid var(--bc); border-radius: 6px; box-sizing: border-box; font-family: inherit; font-size: 14px; background: #fafbfc; color: #333; min-height: 44px; display: flex; align-items: center; }
        .field-value.ml { align-items: flex-start; min-height: 80px; line-height: 1.6; white-space: pre-wrap; }

        .table-srv { width: 100%; border-collapse: collapse; border: 1px solid var(--bc); font-size: 13px; }
        .table-srv th { background: #f8f9fa; font-weight: bold; text-transform: uppercase; color: #555; font-size: 12px; padding: 12px; border-bottom: 1px solid var(--bc); text-align: left; }
        .table-srv td { padding: 14px 12px; border-bottom: 1px solid var(--bc); vertical-align: top; }
        .tr { text-align: right !important; }
        .total-box { font-size: 18px; font-weight: bold; text-align: right; padding: 15px; background: #f8f9fa; border-radius: 4px; margin-top: 15px; border: 1px solid var(--bc); }
        .total-box span { color: var(--cp); }

        /* ══════════════════════════════════════════
           ESTILOS PARA EL PDF Y LA IMPRESIÓN
        ══════════════════════════════════════════ */
        .pdf-container { display: none; }

        .pdf-content { 
            font-family: Helvetica, Arial, sans-serif; 
            font-size: 13px; color: #222; background: #fff; 
            width: 210mm; /* Ancho fijo para garantizar consistencia del render */
            padding: 18mm 20mm; 
            box-sizing: border-box; 
        }

        .pdf-empresa-bar { text-align: center; margin-bottom: 18px; font-size: 13px; color: #444; }
        .pdf-empresa-bar strong { font-size: 15px; color: #111; }
        .pdf-header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 14px; border-bottom: 2px solid #222; margin-bottom: 22px; }
        .pdf-header h1 { font-size: 28px; font-weight: 900; text-transform: uppercase; color: #111; line-height: 1; margin:0; }
        .pdf-header .subtitulo { font-size: 12px; color: #555; margin-top: 5px; }
        .pdf-header .fecha { font-size: 13px; font-weight: bold; }
        .pdf-seccion { border: 1px solid #ccc; border-radius: 3px; padding: 14px 16px; margin-bottom: 20px; }
        .pdf-seccion-titulo { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 6px; margin-bottom: 12px; letter-spacing: 0.4px; }
        .pdf-datos-grid { display: flex; gap: 30px; }
        .pdf-datos-col { flex: 1; }
        .pdf-dato { margin-bottom: 5px; margin-top:0; }
        .pdf-dato strong { color: #111; }
        .pdf-tabla-wrapper { border: 1px solid #ccc; border-radius: 3px; overflow: hidden; margin-bottom: 20px; }
        table.pdf-tabla { width: 100%; border-collapse: collapse; }
        table.pdf-tabla th { text-align: left; padding: 10px 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; background: #f4f4f4; border-top: 2px solid #222; border-bottom: 1px solid #ccc; color: #333; }
        table.pdf-tabla th.r, table.pdf-tabla td.r { text-align: right; }
        table.pdf-tabla td { padding: 11px 12px; border-bottom: 1px solid #e8e8e8; font-size: 13px; vertical-align: top; }
        table.pdf-tabla tr:last-child td { border-bottom: none; }
        .pdf-total-row { display: flex; justify-content: flex-end; gap: 20px; padding: 12px; border-top: 2px solid #222; }
        .pdf-total-label { font-size: 13px; font-weight: bold; text-transform: uppercase; }
        .pdf-total-valor { font-size: 15px; font-weight: 900; min-width: 130px; text-align: right; }
        hr.sep { border: none; border-top: 1px dashed #bbb; margin: 22px 0 16px; }
        .pdf-disclaimer { text-align: center; font-size: 11px; color: #777; line-height: 1.6; }

        @media print {
            @page { size: A4 portrait; margin: 0; }
            body, html { background: #fff !important; padding: 0 !important; margin: 0 !important; height: auto !important; overflow: visible !important; }
            .wrapper, .topbar, .sidebar, aside { display: none !important; } /* Ocultar UI web */
            .main-wrapper, .content-area { padding: 0 !important; margin: 0 !important; overflow: visible !important; display: block !important; width: 100% !important; }
            .pdf-container { display: flex !important; justify-content: center !important; width: 100% !important; margin: 0 !important; padding: 0 !important; background: #fff !important; }
            .pdf-content { margin: 0 auto !important; box-shadow: none !important; }
        }
    </style>
    <?php if(($temaActual ?? $_SESSION['tema_preferido'] ?? '') === 'oscuro'): ?>
        <link rel="stylesheet" href="../CSS/modo_oscuro.css?v=<?php echo time(); ?>">
    <?php endif; ?>
</head>
<body class="<?php echo (($temaActual ?? $_SESSION['tema_preferido'] ?? '') === 'oscuro') ? 'tema-oscuro' : ''; ?>">
    <?php 
        $basePath = '../'; 
        include __DIR__ . '/../HTML/sidebar.php'; 
    ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../HTML/topbar.php'; ?>

        <main class="content-area">
<div class="wrapper">
    <div class="header-box">
        <h2 class="page-title">Detalle de Orden de Trabajo #<?php echo str_pad($orden['numeroOrdenTrabajo'], 8, '0', STR_PAD_LEFT); ?></h2>
        <?php 
            $origen = $_GET['from'] ?? '';
            $idClienteVolver = $_GET['id_cliente'] ?? '';
            if ($origen === 'historial_cliente' && !empty($idClienteVolver)) {
                $linkVolver = "../CRUD_Clientes/historial_cliente.php?id=" . urlencode($idClienteVolver);
            } else {
                $linkVolver = "../inicio.php";
            }
        ?>
        <div class="btn-group">
            <a href="<?php echo $linkVolver; ?>" class="btn btn-back">Volver</a>
            <button onclick="descargarPDFDirecto()" class="btn btn-pdf">↓ Descargar PDF</button>
            <button onclick="window.print()" class="btn btn-print">🖨 Imprimir</button>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-card">
            <div class="ic-title">Titular de la Orden</div>
            <div class="ic-value"><?php echo htmlspecialchars($orden['apellido'] . ', ' . $orden['nombre']); ?></div>
            <div class="ic-sub"><?php echo htmlspecialchars($orden['tipoDocumento']); ?>: <?php echo htmlspecialchars($orden['numeroDocumentoCliente']); ?></div>
        </div>
        <div class="info-card">
            <div class="ic-title">Vehículo Asignado</div>
            <div class="ic-value"><?php echo htmlspecialchars($orden['patente']); ?></div>
            <div class="ic-sub"><?php echo htmlspecialchars(($orden['marca'] ?? '') . ' ' . ($orden['modelo'] ?? '')); ?></div>
        </div>
        <div class="info-card" style="border-left-color:#28a745;">
            <div class="ic-title">Estado de Operación</div>
            <div class="ic-value" style="color:#28a745;"><?php echo htmlspecialchars($orden['nombreEstadoSolicitud']); ?></div>
            <div class="ic-sub">Ingreso: <?php echo $fechaFormateada; ?></div>
        </div>
    </div>

    <div class="step-container" style="border-color:#ffeeba;">
        <div class="step-header" style="background:#fff3cd;color:#856404;border-color:#ffeeba;">
            <div class="step-num" style="background:#856404;">T</div>
            Verificación Técnica del Vehículo
        </div>
        <div class="step-body">
            <div class="field-row">
                <div class="field-group">
                    <span class="field-label">Kilometraje al Ingreso</span>
                    <div class="field-value"><?php echo number_format((int)($orden['kilometrajeIngreso'] ?? 0), 0, ',', '.'); ?> km</div>
                </div>
                <div class="field-group">
                    <span class="field-label">Nivel de Combustible</span>
                    <div class="field-value"><?php echo htmlspecialchars($orden['nivelCombustible'] ?? '-'); ?></div>
                </div>
                <div class="field-group">
                    <span class="field-label">N° de Motor</span>
                    <div class="field-value"><?php echo htmlspecialchars($orden['numeroMotor'] ?: '-'); ?></div>
                </div>
                <div class="field-group">
                    <span class="field-label">N° de Chasis / VIN</span>
                    <div class="field-value"><?php echo htmlspecialchars($orden['numeroChasis'] ?: '-'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="step-container">
        <div class="step-header">
            <div class="step-num">1</div>
            Configuraciones Generales
        </div>
        <div class="step-body">
            <div class="field-row">
                <div class="field-group" style="flex:1;">
                    <span class="field-label">Nivel de Prioridad</span>
                    <div class="field-value"><?php echo $orden['prioridad'] == 6 ? 'Prioritario (Urgente)' : 'No Prioritario (Normal)'; ?></div>
                </div>
                <div class="field-group" style="flex:1.5;">
                    <span class="field-label">Personal Asignado</span>
                    <div class="field-value">
                        <?php if (!empty($orden['emp_apellido'])): ?>
                            <?php echo htmlspecialchars($orden['emp_apellido'] . ', ' . $orden['emp_nombre']); ?>
                        <?php else: ?>
                            <span style="color:#aaa;">No Asignado (Libre)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="field-row">
                <div class="field-group" style="flex:3;">
                    <span class="field-label">Observaciones Generales del Ingreso</span>
                    <div class="field-value ml"><?php echo htmlspecialchars($orden['observacionGeneral'] ?: 'Sin observaciones registradas.'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="step-container">
        <div class="step-header">
            <div class="step-num">2</div>
            Servicios Registrados en la Orden
        </div>
        <div class="step-body">
            <?php if (empty($detalles)): ?>
                <p style="color:#888;font-size:14px;">No hay servicios registrados en esta orden.</p>
            <?php else: ?>
                <table class="table-srv">
                    <thead>
                        <tr>
                            <th style="width:40%;">Servicio Operativo</th>
                            <th class="tr" style="width:20%;">Costo</th>
                            <th style="width:40%;">Detalle del Procedimiento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalles as $det): ?>
                        <tr>
                            <td><strong style="color:var(--cp);"><?php echo htmlspecialchars($det['nombreServicio']); ?></strong></td>
                            <td class="tr">$ <?php echo number_format($det['costoServicio'], 2, ',', '.'); ?></td>
                            <td style="color:#555;"><?php echo nl2br(htmlspecialchars($det['observacionRegistroServicio'] ?? '-')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="total-box">
                    MONTO TOTAL DE LA ORDEN: <span>$ <?php echo number_format($totalOrden, 2, ',', '.'); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="pdf-container" id="pdf-container">
    <div class="pdf-content">
        <?php if ($total_sucursales > 1 || !empty($rubroEmpresa)): ?>
        <div class="pdf-empresa-bar">
            <strong><?php echo htmlspecialchars($config['nombre_empresa']); ?></strong>
            <?php if (!empty($rubroEmpresa)): ?>&nbsp;·&nbsp;<?php echo htmlspecialchars($rubroEmpresa); ?><?php endif; ?>
            <?php if ($total_sucursales > 1): ?>&nbsp;·&nbsp;Sucursal: <?php echo htmlspecialchars($config['nombre_sucursal']); ?><?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="pdf-header">
            <div>
                <h1>Detalle de Servicio</h1>
                <div class="subtitulo">Comprobante de Ingreso - Orden N° <?php echo $orden['numeroOrdenTrabajo']; ?></div>
            </div>
            <div class="fecha"><strong>Fecha:</strong> <?php echo $fechaFormateada; ?></div>
        </div>

        <div class="pdf-seccion">
            <div class="pdf-seccion-titulo">Datos del Cliente y Vehículo</div>
            <div class="pdf-datos-grid">
                <div class="pdf-datos-col">
                    <p class="pdf-dato"><strong>Cliente:</strong> <?php echo htmlspecialchars($orden['apellido'] . ' ' . $orden['nombre']); ?></p>
                    <p class="pdf-dato"><strong><?php echo htmlspecialchars($orden['tipoDocumento']); ?>:</strong> <?php echo htmlspecialchars($orden['numeroDocumentoCliente']); ?></p>
                </div>
                <div class="pdf-datos-col">
                    <p class="pdf-dato"><strong>Patente:</strong> <?php echo htmlspecialchars($orden['patente']); ?></p>
                </div>
            </div>
        </div>

        <div class="pdf-tabla-wrapper">
            <table class="pdf-tabla">
                <thead>
                    <tr>
                        <th style="width:70%;">Servicio a Realizar</th>
                        <th class="r" style="width:30%;">Costo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $det): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($det['nombreServicio']); ?></strong></td>
                        <td class="r">$ <?php echo number_format($det['costoServicio'], 2, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="pdf-total-row">
                <span class="pdf-total-label">Total a Abonar</span>
                <span class="pdf-total-valor">$ <?php echo number_format($totalOrden, 2, ',', '.'); ?></span>
            </div>
        </div>

        <hr class="sep">
        <div class="pdf-disclaimer">
            Este documento es un comprobante de los servicios a realizar en el vehículo.<br>
            <strong>NO POSEE VALIDEZ COMO FACTURA NI COMPROBANTE.</strong>
        </div>
    </div>
</div>

<script>
function descargarPDFDirecto() {
    const container = document.getElementById('pdf-container');
    const content = container.querySelector('.pdf-content');
    
    // Hacemos el contenedor visible temporalmente para que html2pdf lo pueda capturar,
    // pero lo posicionamos fuera de la pantalla para que el usuario no vea un parpadeo en la web.
    container.style.display = 'block';
    container.style.position = 'absolute';
    container.style.top = '-9999px';
    container.style.left = '-9999px';

    const opt = {
        margin:       0,
        filename:     '<?php echo $nombreArchivo; ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { 
            scale: 2, 
            useCORS: true,
            onclone: function(clonedDoc) {
                clonedDoc.body.classList.remove('tema-oscuro');
            }
        },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    // Genera el PDF y al terminar la descarga, restaura los estilos para ocultarlo de nuevo
    html2pdf().set(opt).from(content).save().then(() => {
        container.style.display = '';
        container.style.position = '';
        container.style.top = '';
        container.style.left = '';
    });
}
</script>
        </div>
    </main>
</div>
</body>
</html>