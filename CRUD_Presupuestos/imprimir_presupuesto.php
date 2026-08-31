<?php
session_start();
require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$conn = obtenerConexion();
$idSucursal = $_SESSION['sucursal_id'] ?? 1;

$idPresupuesto = (int)($_GET['id'] ?? 0);
if (!$idPresupuesto) {
    die("ID Invalido.");
}

// Obtener datos del presupuesto
$sql = "
    SELECT p.*, c.nombre as cli_nom, c.apellido as cli_ape, c.numeroDocumentoCliente, c.email, c.telefono, v.patente, v.marca, v.modelo
    FROM presupuestos p
    LEFT JOIN clientes c ON p.IDCliente = c.IDCliente
    LEFT JOIN vehiculos v ON p.IDVehiculo = v.IDVehiculo
    WHERE p.IDPresupuesto = ? AND p.IDSucursal = ?
";
$stmt = $conn->prepare($sql);
$stmt->execute([$idPresupuesto, $idSucursal]);
$presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$presupuesto) {
    die("Presupuesto no encontrado.");
}

// Obtener empresa/sucursal (para el encabezado)
$sqlSuc = "SELECT s.nombre as nombreSucursal, s.direccion, s.telefono as telSuc, e.nombre as nombreEmpresa, e.cuit 
           FROM sucursales s 
           LEFT JOIN empresas e ON s.empresa_id = e.IDEmpresa 
           WHERE s.id = ?";
$stmtSuc = $conn->prepare($sqlSuc);
$stmtSuc->execute([$idSucursal]);
$sucursal = $stmtSuc->fetch(PDO::FETCH_ASSOC);

// Obtener detalles
$sqlDet = "
    SELECT d.*, s.nombreServicio 
    FROM detalle_presupuestos d
    LEFT JOIN servicios s ON d.IDServicio = s.IDServicio
    WHERE d.IDPresupuesto = ?
";
$stmtDet = $conn->prepare($sqlDet);
$stmtDet->execute([$idPresupuesto]);
$detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

$clienteStr = $presupuesto['IDCliente'] ? trim($presupuesto['cli_ape'] . ' ' . $presupuesto['cli_nom']) : trim(($presupuesto['casual_apellido']??'') . ' ' . ($presupuesto['casual_nombre']??''));
$docStr = $presupuesto['IDCliente'] ? $presupuesto['numeroDocumentoCliente'] : 'Consumidor Final';
$telStr = $presupuesto['IDCliente'] ? $presupuesto['telefono'] : ($presupuesto['casual_telefono'] ?: '-');

$vehiculoStr = $presupuesto['IDVehiculo'] ? $presupuesto['marca'] . " " . $presupuesto['modelo'] : ($presupuesto['casual_marca']??'') . ' ' . ($presupuesto['casual_modelo']??'');
$patenteStr = $presupuesto['IDVehiculo'] ? $presupuesto['patente'] : ($presupuesto['casual_patente'] ?: '-');

$fechaStr = date('d/m/Y', strtotime($presupuesto['fecha_creacion']));
$validezDias = $presupuesto['validez_dias'];
$fechaVencimiento = date('d/m/Y', strtotime($presupuesto['fecha_creacion'] . ' + ' . $validezDias . ' days'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Presupuesto #<?php echo str_pad($idPresupuesto, 6, '0', STR_PAD_LEFT); ?></title>
    <style>
        body { font-family: 'Arial', sans-serif; margin: 0; padding: 40px; color: #111; background: #fff; }
        .hoja { max-width: 800px; margin: 0 auto; background: #fff; }
        
        .header-top { text-align: center; margin-bottom: 30px; font-size: 14px; color: #333; }
        .header-top strong { font-size: 15px; color: #000; }
        
        .header-main { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 30px; }
        .header-left h1 { margin: 0; font-size: 28px; font-weight: 900; text-transform: uppercase; color: #111; }
        .header-left p { margin: 5px 0 0 0; font-size: 13px; color: #555; }
        
        .header-right { text-align: right; font-size: 13px; color: #333; }
        .header-right p { margin: 3px 0; }
        .header-right strong { color: #000; }

        .box { border: 1px solid #ccc; border-radius: 4px; margin-bottom: 30px; overflow: hidden; }
        .box-title { background: #f8f9fa; border-bottom: 1px solid #ccc; padding: 10px 15px; font-size: 11px; font-weight: bold; color: #333; text-transform: uppercase; letter-spacing: 0.5px; }
        .box-content { display: flex; padding: 15px; }
        .box-col { width: 50%; font-size: 13px; line-height: 1.6; }
        .box-col strong { display: inline-block; width: 90px; color: #000; }
        
        table.detalle { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 13px; }
        table.detalle th { border-bottom: 2px solid #000; padding: 10px 5px; text-align: left; font-size: 11px; font-weight: bold; text-transform: uppercase; color: #333; }
        table.detalle td { border-bottom: 1px solid #eee; padding: 12px 5px; color: #111; }
        table.detalle td strong { font-weight: bold; color: #000; }
        
        .total-row td { border-top: 2px solid #000 !important; border-bottom: 2px solid #000 !important; font-size: 16px; padding: 15px 5px !important; }
        
        .footer-condiciones { margin-top: 50px; font-size: 11px; text-align: center; color: #666; border-top: 1px dashed #ccc; padding-top: 20px; line-height: 1.5; }
        .footer-condiciones strong { color: #333; display: block; margin-top: 5px; }
        
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body <?php echo (isset($_GET['action']) && $_GET['action'] == 'print') ? 'onload="window.print()"' : ''; ?>>
    <div class="hoja">
        <div class="no-print" style="margin-bottom: 20px; text-align: right;">
            <button onclick="window.print()" style="padding:10px 20px; font-size:14px; cursor:pointer; background:#28a745; color:#fff; border:none; border-radius:4px; font-weight:bold;">Imprimir / Guardar PDF</button>
        </div>

        <div class="header-top">
            <strong><?php echo htmlspecialchars($sucursal['nombreEmpresa'] ?? 'Taller Los Motores'); ?></strong> &middot; Sucursal: <?php echo htmlspecialchars($sucursal['nombreSucursal'] ?? 'Sede Central Autos'); ?>
        </div>

        <div class="header-main">
            <div class="header-left">
                <h1>PRESUPUESTO DE SERVICIO</h1>
                <p>Presupuesto N° <?php echo str_pad($idPresupuesto, 6, '0', STR_PAD_LEFT); ?></p>
            </div>
            <div class="header-right">
                <p><strong>Fecha:</strong> <?php echo $fechaStr; ?></p>
                <p>Válido hasta: <?php echo $fechaVencimiento; ?></p>
            </div>
        </div>

        <div class="box">
            <div class="box-title">Datos del Cliente y Vehículo</div>
            <div class="box-content">
                <div class="box-col">
                    <p><strong>Cliente:</strong> <?php echo htmlspecialchars($clienteStr); ?></p>
                    <p><strong>Documento:</strong> <?php echo htmlspecialchars($docStr); ?></p>
                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($telStr); ?></p>
                </div>
                <div class="box-col">
                    <p><strong>Patente:</strong> <span style="text-transform: uppercase;"><?php echo htmlspecialchars($patenteStr); ?></span></p>
                    <p><strong>Vehículo:</strong> <?php echo htmlspecialchars($vehiculoStr); ?></p>
                </div>
            </div>
        </div>

        <table class="detalle">
            <thead>
                <tr>
                    <th width="75%">SERVICIO COTIZADO</th>
                    <th width="25%" style="text-align:right;">COSTO</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($detalles as $d): 
                    $nombreMostrar = $d['nombreServicio'];
                    $detalleMostrar = $d['descripcion_libre'];
                    if (!$d['IDServicio']) {
                        $parts = explode('|||', $d['descripcion_libre']);
                        if (count($parts) > 1) {
                            $nombreMostrar = $parts[0];
                            $detalleMostrar = $parts[1];
                        } else {
                            $nombreMostrar = 'Servicio Manual';
                            $detalleMostrar = $d['descripcion_libre'];
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
                
                <tr class="total-row">
                    <td style="text-align: right; font-weight: bold; font-size: 13px;">TOTAL COTIZADO</td>
                    <td style="text-align: right;"><strong>$ <?php echo number_format($presupuesto['total'], 2, ',', '.'); ?></strong></td>
                </tr>
            </tbody>
        </table>

        <div class="footer-condiciones">
            El presente presupuesto tiene una validez de <?php echo htmlspecialchars($validezDias); ?> días desde su emisión.<br>
            Los precios pueden estar sujetos a cambios luego de su vencimiento.<br>
            <strong>NO POSEE VALIDEZ COMO FACTURA NI COMPROBANTE.</strong>
        </div>
    </div>
</body>
</html>
