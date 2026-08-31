<?php
session_start();
require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$conn = obtenerConexion();
$idSucursal = $_SESSION['sucursal_id'] ?? 1;
$temaActual = $_SESSION['tema_preferido'] ?? 'claro';
$config = $_SESSION['cliente_config'] ?? [];
$apariencia = $config['apariencia'] ?? [];

$idPresupuesto = (int)($_GET['id'] ?? 0);
if (!$idPresupuesto) {
    header("Location: listar_presupuestos.php");
    exit;
}

// Obtener datos del presupuesto
$sql = "SELECT p.*, v.patente, v.marca as veh_marca, v.modelo as veh_modelo, c.nombre as cli_nom, c.apellido as cli_ape, c.numeroDocumentoCliente, c.telefono, c.email 
        FROM presupuestos p 
        LEFT JOIN vehiculos v ON p.IDVehiculo = v.IDVehiculo 
        LEFT JOIN clientes c ON p.IDCliente = c.IDCliente 
        WHERE p.IDPresupuesto = ? AND p.IDSucursal = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$idPresupuesto, $idSucursal]);
$presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$presupuesto) {
    header("Location: listar_presupuestos.php");
    exit;
}

// Obtener empresa/sucursal (para el encabezado del PDF)
$sqlSuc = "SELECT s.nombre as nombreSucursal, s.direccion, s.telefono as telSuc, e.nombre as nombreEmpresa, e.cuit 
           FROM sucursales s 
           LEFT JOIN empresas e ON s.empresa_id = e.id 
           WHERE s.id = ?";
$stmtSuc = $conn->prepare($sqlSuc);
$stmtSuc->execute([$idSucursal]);
$sucursal = $stmtSuc->fetch(PDO::FETCH_ASSOC);

// Obtener detalles
$sqlDet = "SELECT dp.*, s.nombreServicio 
           FROM detalle_presupuestos dp
           LEFT JOIN servicios s ON dp.IDServicio = s.IDServicio
           WHERE dp.IDPresupuesto = ?";
$stmtDet = $conn->prepare($sqlDet);
$stmtDet->execute([$idPresupuesto]);
$detallesGuardados = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

$isRegistrado = !empty($presupuesto['IDCliente']) && !empty($presupuesto['IDVehiculo']);

$clienteStr = $isRegistrado ? trim($presupuesto['cli_ape'] . ' ' . $presupuesto['cli_nom']) : trim(($presupuesto['casual_apellido']??'') . ' ' . ($presupuesto['casual_nombre']??''));
$docStr = $isRegistrado ? $presupuesto['numeroDocumentoCliente'] : 'Consumidor Final';
$telStr = $isRegistrado ? $presupuesto['telefono'] : ($presupuesto['casual_telefono'] ?: '-');

$vehiculoStr = $isRegistrado ? $presupuesto['veh_marca'] . " " . $presupuesto['veh_modelo'] : ($presupuesto['casual_marca']??'') . ' ' . ($presupuesto['casual_modelo']??'');
$patenteStr = $isRegistrado ? $presupuesto['patente'] : ($presupuesto['casual_patente'] ?: '-');

$fechaStr = date('d/m/Y', strtotime($presupuesto['fecha_creacion']));
$validezDias = $presupuesto['validez_dias'];
$fechaVencimiento = date('d/m/Y', strtotime($presupuesto['fecha_creacion'] . ' + ' . $validezDias . ' days'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Presupuesto #<?php echo str_pad($idPresupuesto, 8, '0', STR_PAD_LEFT); ?></title>
    <link rel="stylesheet" href="../CSS/modo_oscuro.css?v=<?php echo time(); ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root { 
            --color-primario: <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>; 
            --color-secundario: <?php echo htmlspecialchars($apariencia['color_secundario'] ?? '#e74c3c'); ?>; 
            --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>; 
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--color-fondo); margin: 0; display: flex; height: 100vh; overflow: hidden; color: #333; }
        
        .topbar { background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); flex-shrink: 0; z-index: 10; }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; background-color: var(--color-fondo); }
        .wrapper-content { max-width: 1200px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        
        .header-presupuesto { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--color-primario); padding-bottom: 15px; margin-bottom: 20px; }
        .header-presupuesto h2 { margin: 0; color: var(--color-primario); }
        
        .form-section { background: #f8f9fa; padding: 15px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 20px; }
        .form-section h3 { margin-top: 0; font-size: 16px; color: #444; border-bottom: 1px solid #ddd; padding-bottom: 8px; }
        
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 200px; position: relative; }
        .form-group label { display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #555; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box; background-color: #e9ecef; color: #495057; }
        
        .table-servicios { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; background: #fff; }
        .table-servicios th, .table-servicios td { border-bottom: 1px solid #eee; padding: 8px; text-align: left; vertical-align: middle; }
        .table-servicios th { background: #f8f9fa; font-weight: bold; color: #555; font-size: 12px; text-transform: uppercase; }
        
        .total-box { font-size: 18px; font-weight: bold; text-align: right; padding: 15px; background: #f8f9fa; border-radius: 4px; margin-top: 15px; border: 1px solid #e2e8f0; }

        .btn-action { display: inline-block; background: var(--color-primario); color: #fff; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 13px; margin-left: 10px; cursor: pointer; border: none; }
        .btn-action:hover { opacity: 0.9; }
        .btn-back { background: #e2e8f0; color: #333; }
        .btn-back:hover { background: #cbd5e0; }
        
        /* Tema Oscuro Específico */
        body.tema-oscuro .wrapper-content { background: #27272a; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
        body.tema-oscuro .form-section { background: #27272a; border-color: #3f3f46; }
        body.tema-oscuro .form-section h3 { color: #fff; border-bottom-color: #3f3f46; }
        body.tema-oscuro .form-group label { color: #ddd; }
        body.tema-oscuro .form-control { background: #3f3f46; color: #eee; border-color: #52525b; }
        body.tema-oscuro .table-servicios th { background: #3f3f46; color: #fff; border-bottom-color: #52525b; }
        body.tema-oscuro .table-servicios td { border-bottom-color: #52525b; background: #27272a; color: #fff; }
        body.tema-oscuro .total-box { background: #27272a; color: #fff; border-color: #3f3f46; }

        /* ESTILOS PARA EL PDF Y LA IMPRESIÓN */
        #pdf-container { display: none; }
        .pdf-content { font-family: 'Arial', sans-serif; color: #111; background: #fff; width: 800px; padding: 40px; box-sizing: border-box; }
        .pdf-header-top { text-align: center; margin-bottom: 30px; font-size: 14px; color: #333; }
        .pdf-header-top strong { font-size: 15px; color: #000; }
        .pdf-header-main { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 30px; }
        .pdf-header-left h1 { margin: 0; font-size: 28px; font-weight: 900; text-transform: uppercase; color: #111; line-height: 1; }
        .pdf-header-left p { margin: 5px 0 0 0; font-size: 13px; color: #555; }
        .pdf-header-right { text-align: right; font-size: 13px; color: #333; }
        .pdf-header-right p { margin: 3px 0; }
        .pdf-header-right strong { color: #000; }
        .pdf-box { border: 1px solid #ccc; border-radius: 4px; margin-bottom: 30px; overflow: hidden; }
        .pdf-box-title { background: #f8f9fa; border-bottom: 1px solid #ccc; padding: 10px 15px; font-size: 11px; font-weight: bold; color: #333; text-transform: uppercase; letter-spacing: 0.5px; }
        .pdf-box-content { display: flex; padding: 15px; }
        .pdf-box-col { width: 50%; font-size: 13px; line-height: 1.6; }
        .pdf-box-col p { margin: 0 0 5px 0; }
        .pdf-box-col strong { display: inline-block; width: 90px; color: #000; }
        table.pdf-detalle { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 13px; }
        table.pdf-detalle th { border-bottom: 2px solid #000; padding: 10px 5px; text-align: left; font-size: 11px; font-weight: bold; text-transform: uppercase; color: #333; }
        table.pdf-detalle td { border-bottom: 1px solid #eee; padding: 12px 5px; color: #111; }
        table.pdf-detalle td strong { font-weight: bold; color: #000; }
        .pdf-total-row td { border-top: 2px solid #000 !important; border-bottom: 2px solid #000 !important; font-size: 16px; padding: 15px 5px !important; }
        .pdf-footer { margin-top: 50px; font-size: 11px; text-align: center; color: #666; border-top: 1px dashed #ccc; padding-top: 20px; line-height: 1.5; }
        .pdf-footer strong { color: #333; display: block; margin-top: 5px; }

        @media print {
            .no-print, .main-wrapper > header, .sidebar { display: none !important; }
            body, .main-wrapper, .content-area { background: #fff !important; margin: 0 !important; padding: 0 !important; overflow: visible !important; height: auto !important; width: 100% !important; }
            .wrapper-content { display: none !important; }
            #pdf-container { display: flex !important; justify-content: center !important; width: 100% !important; margin: 0 !important; padding: 0 !important; background: #fff !important; }
            .pdf-content { margin: 0 auto !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="<?php echo $temaActual === 'oscuro' ? 'tema-oscuro' : ''; ?>">
    <?php 
        $basePath = '../'; 
        include __DIR__ . '/../HTML/sidebar.php'; 
    ?>
    <div class="main-wrapper">
        <header class="no-print">
            <?php include __DIR__ . '/../HTML/topbar.php'; ?>
        </header>
        
        <main class="content-area">
            <div class="wrapper-content">
                <div class="header-presupuesto">
                    <h2 style="display: flex; align-items: center; gap: 15px;">
                        Ver Presupuesto #<?php echo str_pad($idPresupuesto, 8, '0', STR_PAD_LEFT); ?>
                        <?php
                            $e_color = ''; $e_bg = '';
                            $currE = $presupuesto['estado'] ?? 'Pendiente';
                            if($currE=='Pendiente'){ $e_color='#856404'; $e_bg='#fff3cd'; }
                            if($currE=='Aprobado'){ $e_color='#155724'; $e_bg='#d4edda'; }
                            if($currE=='Rechazado'){ $e_color='#721c24'; $e_bg='#f8d7da'; }
                            if($currE=='Vencido'){ $e_color='#d35400'; $e_bg='#fdebd0'; }
                        ?>
                        <span style="font-size: 13px; font-weight: bold; text-transform: uppercase; padding: 4px 10px; border-radius: 4px; color: <?php echo $e_color; ?>; background-color: <?php echo $e_bg; ?>; border: 1px solid <?php echo $e_color; ?>;">
                            <?php echo $currE; ?>
                        </span>
                    </h2>
                    
                    <div style="display: flex; align-items: center;" class="no-print">
                        <span style="font-size: 13px; color: #666; margin-right: 15px; background: #eee; padding: 5px 10px; border-radius: 4px;"><strong>Creado por:</strong> <?php echo htmlspecialchars($presupuesto['creado_por'] ?? 'Desconocido'); ?></span>
                        <button onclick="descargarPDFDirecto()" class="btn-action" style="background: #17a2b8;">Descargar</button>
                        <button onclick="window.print()" class="btn-action" style="background: #28a745;">Imprimir</button>
                        <a href="listar_presupuestos.php" class="btn-action btn-back">Volver</a>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Datos Generales</h3>
                    <div class="form-row">
                        <div class="form-group" style="flex: 0 0 200px;">
                            <label>Validez (Días)</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($presupuesto['validez_dias'] ?? 15); ?> días" readonly>
                        </div>
                        <div class="form-group" style="flex: 0 0 200px;">
                            <label>Estado del Presupuesto</label>
                            <input type="text" class="form-control" style="font-weight: bold; color: <?php echo $e_color; ?>; background-color: <?php echo $e_bg; ?>; border-color: <?php echo $e_color; ?>;" value="<?php echo $currE; ?>" readonly>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Datos del Cliente y Vehículo</h3>
                    
                    <div id="box_manual">
                        <h4 style="margin-bottom:10px; color:#555; border-bottom:1px solid #eee; padding-bottom:5px;">Datos del Cliente</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nombre</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($isRegistrado ? $presupuesto['cli_nom'] : ($presupuesto['casual_nombre'] ?? '')); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Apellido</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($isRegistrado ? $presupuesto['cli_ape'] : ($presupuesto['casual_apellido'] ?? '')); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Teléfono</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($isRegistrado ? $presupuesto['telefono'] : ($presupuesto['casual_telefono'] ?? '-')); ?>" readonly>
                            </div>
                        </div>
                        
                        <h4 style="margin-bottom:10px; margin-top:20px; color:#555; border-bottom:1px solid #eee; padding-bottom:5px;">Datos del Vehículo</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Patente</label>
                                <input type="text" class="form-control" style="text-transform:uppercase;" value="<?php echo htmlspecialchars($isRegistrado ? $presupuesto['patente'] : ($presupuesto['casual_patente'] ?? '')); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Tipo</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($presupuesto['casual_tipo_vehiculo'] ?? 'No especificado'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Marca</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($isRegistrado ? $presupuesto['veh_marca'] : ($presupuesto['casual_marca'] ?? '')); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Modelo</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($isRegistrado ? $presupuesto['veh_modelo'] : ($presupuesto['casual_modelo'] ?? '')); ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Servicios Asignados al Presupuesto</h3>
                    <table class="table-servicios">
                        <thead>
                            <tr>
                                <th style="width: 80%;">SERVICIO / DESCRIPCIÓN</th>
                                <th style="width: 20%; text-align: center;">COSTO ($)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($detallesGuardados)): ?>
                            <tr>
                                <td colspan="2" style="text-align: center; color: #888; padding: 20px;">No hay servicios asignados.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($detallesGuardados as $d): 
                                    $nombreMostrar = $d['nombreServicio'];
                                    $detalleMostrar = $d['descripcion_libre'];
                                    if (!$d['IDServicio']) {
                                        $parts = explode('|||', $d['descripcion_libre'] ?? '');
                                        if (count($parts) > 1) {
                                            $nombreMostrar = $parts[0];
                                            $detalleMostrar = $parts[1];
                                        } else {
                                            $nombreMostrar = 'Servicio Manual';
                                            $detalleMostrar = $d['descripcion_libre'] ?? '';
                                        }
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($nombreMostrar); ?></strong>
                                        <?php if($detalleMostrar): ?>
                                            <br><span style="color: #666; font-size: 13px;"><?php echo nl2br(htmlspecialchars($detalleMostrar)); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center; font-weight:bold;">
                                        $<?php echo number_format($d['precio_unitario'], 2, '.', ''); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <div class="total-box">
                        Total: $<span><?php echo number_format($presupuesto['total'], 2, '.', ''); ?></span>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Notas Adicionales</h3>
                    <textarea class="form-control" rows="3" style="resize: none;" readonly><?php echo htmlspecialchars($presupuesto['notas_adicionales'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <!-- SECCIÓN IMPRIMIBLE Y PDF (Oculta en pantalla normal) -->
            <div id="pdf-container">
                <div class="pdf-content">
                    <div class="pdf-header-top">
                        <strong><?php echo htmlspecialchars($sucursal['nombreEmpresa'] ?? 'Taller Los Motores'); ?></strong> &middot; Sucursal: <?php echo htmlspecialchars($sucursal['nombreSucursal'] ?? 'Sede Central Autos'); ?>
                    </div>

                    <div class="pdf-header-main">
                        <div class="pdf-header-left">
                            <h1>PRESUPUESTO DE SERVICIO</h1>
                            <p>Presupuesto N° <?php echo str_pad($idPresupuesto, 8, '0', STR_PAD_LEFT); ?></p>
                        </div>
                        <div class="pdf-header-right">
                            <p><strong>Fecha:</strong> <?php echo $fechaStr; ?></p>
                            <p>Válido hasta: <?php echo $fechaVencimiento; ?></p>
                        </div>
                    </div>

                    <div class="pdf-box">
                        <div class="pdf-box-title">Datos del Cliente y Vehículo</div>
                        <div class="pdf-box-content">
                            <div class="pdf-box-col">
                                <p><strong>Cliente:</strong> <?php echo htmlspecialchars($clienteStr); ?></p>
                                <p><strong>Documento:</strong> <?php echo htmlspecialchars($docStr); ?></p>
                                <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($telStr); ?></p>
                            </div>
                            <div class="pdf-box-col">
                                <p><strong>Patente:</strong> <span style="text-transform: uppercase;"><?php echo htmlspecialchars($patenteStr); ?></span></p>
                                <p><strong>Vehículo:</strong> <?php echo htmlspecialchars($vehiculoStr); ?></p>
                            </div>
                        </div>
                    </div>

                    <table class="pdf-detalle">
                        <thead>
                            <tr>
                                <th width="75%">SERVICIO COTIZADO</th>
                                <th width="25%" style="text-align:right;">COSTO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($detallesGuardados as $d): 
                                $nombreMostrar = $d['nombreServicio'];
                                $detalleMostrar = $d['descripcion_libre'];
                                if (!$d['IDServicio']) {
                                    $parts = explode('|||', $d['descripcion_libre'] ?? '');
                                    if (count($parts) > 1) {
                                        $nombreMostrar = $parts[0];
                                        $detalleMostrar = $parts[1];
                                    } else {
                                        $nombreMostrar = 'Servicio Manual';
                                        $detalleMostrar = $d['descripcion_libre'] ?? '';
                                    }
                                }
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($nombreMostrar); ?></strong>
                                        <?php if(!empty(trim($detalleMostrar ?? ''))): ?>
                                            <br><span style="font-size:12px; color:#555;"><?php echo htmlspecialchars($detalleMostrar); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;">$ <?php echo number_format($d['precio_unitario'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <tr class="pdf-total-row">
                                <td style="text-align: right; font-weight: bold; font-size: 13px;">TOTAL COTIZADO</td>
                                <td style="text-align: right;"><strong>$ <?php echo number_format($presupuesto['total'], 2, ',', '.'); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="pdf-footer">
                        El presente presupuesto tiene una validez de <?php echo htmlspecialchars($validezDias); ?> días desde su emisión.<br>
                        Los precios pueden estar sujetos a cambios luego de su vencimiento.<br>
                        <strong>NO POSEE VALIDEZ COMO FACTURA NI COMPROBANTE.</strong>
                    </div>
                </div>
            </div>

        </main>
    </div>
    
    <script>
    function descargarPDFDirecto() {
        const container = document.getElementById('pdf-container');
        const content = container.querySelector('.pdf-content');
        
        container.style.display = 'flex';
        container.style.position = 'absolute';
        container.style.top = '-9999px';
        container.style.left = '-9999px';

        const opt = {
            margin:       0,
            filename:     'Presupuesto_<?php echo str_replace(' ', '_', $clienteStr); ?>_<?php echo str_pad($idPresupuesto, 8, '0', STR_PAD_LEFT); ?>.pdf',
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

        html2pdf().set(opt).from(content).save().then(() => {
            container.style.display = '';
            container.style.position = '';
            container.style.top = '';
            container.style.left = '';
        });
    }
    </script>
</body>
</html>
