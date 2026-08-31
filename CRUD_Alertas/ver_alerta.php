<?php
/**
 * Lizzosoft Vehículos - Proyección de Alerta
 * Ubicación: lizzosoft_vehiculos/CRUD_Alertas/ver_alerta.php
 */

// Forzar zona horaria local
date_default_timezone_set('America/Argentina/Buenos_Aires');

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$config     = $_SESSION['cliente_config'];
$apariencia = $config['apariencia'];
$empresa_id = (int)$_SESSION['empresa_id'];

$idAlerta = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idAlerta <= 0) { header("Location: listar_alertas.php"); exit; }

$conexion = obtenerConexion();

$stmtA = $conexion->prepare("
    SELECT a.*, s.nombreServicio 
    FROM alertas_servicios a 
    INNER JOIN servicios s ON a.IDServicio = s.IDServicio 
    WHERE a.IDAlerta = ? AND a.empresa_id = ?
");
$stmtA->execute([$idAlerta, $empresa_id]);
$alerta = $stmtA->fetch(PDO::FETCH_ASSOC);
if (!$alerta) die("Alerta no encontrada.");

// Consulta alineada estrictamente a procesar_alertas.php
$queryEnvios = "
    SELECT v.IDVehiculo, v.patente, v.marca, v.modelo, c.nombre, c.apellido, c.email, 
           MAX(COALESCE(rs.fechaFin, rs.fechaRegistroServicio)) AS ultimaFecha
    FROM detalleregistro dr
    JOIN registrosservicios rs ON dr.IDRegistroServicio = rs.IDRegistroServicio
    JOIN vehiculos v ON rs.IDVehiculo = v.IDVehiculo
    JOIN clientes c ON v.IDCliente = c.IDCliente
    WHERE dr.IDServicio = :id_srv 
      AND rs.empresa_id = :emp_id
      AND rs.IDEstado IN (4, 5)
    GROUP BY v.IDVehiculo
";
$stmtEnvios = $conexion->prepare($queryEnvios);
$stmtEnvios->execute([
    ':id_srv' => $alerta['IDServicio'],
    ':emp_id' => $empresa_id
]);
$clientesEnCiclo = $stmtEnvios->fetchAll(PDO::FETCH_ASSOC);

// ---- LOGICA DE PROCESAMIENTO, FILTRADO Y ORDENAMIENTO EN PHP ----
$filtro_estado = $_GET['filtro_estado'] ?? 'Todas';
$filtro_fecha = $_GET['filtro_fecha'] ?? '';
$pagina_actual = max(1, (int)($_GET['p'] ?? 1));
$items_por_pagina = isset($_GET['limite']) ? (int)$_GET['limite'] : 5;

$hoy = new DateTime();
$filas_procesadas = [];

foreach($clientesEnCiclo as $p) {
    $ultimaFecha = new DateTime($p['ultimaFecha']);
    
    $fechaProyectada = clone $ultimaFecha;
    $fechaProyectada->modify("+" . $alerta['diasRecordatorio'] . " days");

    $stmtExito = $conexion->prepare("SELECT fechaEnvio FROM envios_alertas_log WHERE IDAlerta = ? AND IDVehiculo = ? AND fechaEnvio >= ? AND estadoEnvio = 'Enviado' LIMIT 1");
    $stmtExito->execute([$idAlerta, $p['IDVehiculo'], $p['ultimaFecha']]);
    $fueEnviado = $stmtExito->fetchColumn();

    $stmtIntento = $conexion->prepare("SELECT fechaEnvio, estadoEnvio, detalle_error FROM envios_alertas_log WHERE IDAlerta = ? AND IDVehiculo = ? AND fechaEnvio >= ? ORDER BY fechaEnvio DESC LIMIT 1");
    $stmtIntento->execute([$idAlerta, $p['IDVehiculo'], $p['ultimaFecha']]);
    $ultimoIntento = $stmtIntento->fetch(PDO::FETCH_ASSOC);

    $detalleIntento = "";
    $timestampOrden = 0;

    if ($fueEnviado) {
        $estadoBade = "badge-success";
        $estadoTexto = "Enviada";
        $tipoEstado = "Enviada";
        $fechaEnvioRealStr = date('d/m/Y', strtotime($fueEnviado));
        $timestampOrden = strtotime($fueEnviado);
    } else {
        $estadoBade = "badge-warning";
        $fechaEnvioRealStr = "—";
        $timestampOrden = $fechaProyectada->getTimestamp();

        if ($fechaProyectada <= $hoy) {
            $estadoTexto = "Pendiente (En cola)";
            $tipoEstado = "Pendiente";
        } else {
            $estadoTexto = "Pendiente (Programada)";
            $tipoEstado = "Pendiente";
        }
        
        if ($ultimoIntento && $ultimoIntento['estadoEnvio'] === 'Fallido') {
            $detalleIntento = "<br><span style='font-size:10px; color:#e74c3c; font-weight:normal;'>Último fallo: " . date('d/m/Y H:i', strtotime($ultimoIntento['fechaEnvio'])) . "</span>";
        }
    }

    if ($filtro_estado !== 'Todas' && $tipoEstado !== $filtro_estado) {
        continue; 
    }
    
    if (!empty($filtro_fecha)) {
        $fechaServicioDate = date('Y-m-d', strtotime($p['ultimaFecha']));
        if ($fechaServicioDate !== $filtro_fecha) {
            continue;
        }
    }

    $filas_procesadas[] = [
        'cliente' => $p['nombre'] . ' ' . $p['apellido'],
        'email' => $p['email'],
        'vehiculo' => $p['marca'] . ' ' . $p['modelo'] . ' - ' . $p['patente'],
        'ultimaFechaStr' => date('d/m/Y', strtotime($p['ultimaFecha'])),
        'fechaProyectadaStr' => $fechaProyectada->format('d/m/Y'),
        'fechaEnvioRealStr' => $fechaEnvioRealStr,
        'estadoBade' => $estadoBade,
        'estadoTexto' => $estadoTexto,
        'detalleIntento' => $detalleIntento,
        'tipoEstado' => $tipoEstado,
        'timestampOrden' => $timestampOrden
    ];
}

// Ordenar: Pendiente En cola > Pendiente Programada > Enviada. Luego, por urgencia (las más viejas primero si son pendientes).
usort($filas_procesadas, function($a, $b) {
    $pesoA = ($a['estadoTexto'] === 'Pendiente (En cola)') ? 1 : (($a['estadoTexto'] === 'Pendiente (Programada)') ? 2 : 3);
    $pesoB = ($b['estadoTexto'] === 'Pendiente (En cola)') ? 1 : (($b['estadoTexto'] === 'Pendiente (Programada)') ? 2 : 3);
    
    if ($pesoA !== $pesoB) return $pesoA - $pesoB;
    
    if ($pesoA === 1 || $pesoA === 2) {
        return $a['timestampOrden'] - $b['timestampOrden']; // ASC (más viejo primero)
    }
    return $b['timestampOrden'] - $a['timestampOrden']; // DESC (enviadas más recientes primero)
});

// Paginar
$total_items = count($filas_procesadas);
$total_paginas = max(1, ceil($total_items / $items_por_pagina));
if ($pagina_actual > $total_paginas) $pagina_actual = $total_paginas;

$indice_inicio = ($pagina_actual - 1) * $items_por_pagina;
$filas_pagina = array_slice($filas_procesadas, $indice_inicio, $items_por_pagina);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proyección de Alerta - <?php echo htmlspecialchars($config['nombre_empresa'] ?? 'Taller'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { 
            --color-primario: <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>; 
            --color-secundario: <?php echo htmlspecialchars($apariencia['color_secundario'] ?? '#e74c3c'); ?>; 
            --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>; 
            --sidebar-width: 270px;
            --cp: var(--color-primario);
            --cf: var(--color-fondo);
            --bc: #dee2e6;
        }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: var(--color-fondo); margin: 0; display: flex; height: 100vh; overflow: hidden; color: #333; }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar { background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); flex-shrink: 0; z-index: 10; }
        .user-info { font-size: 13px; font-weight: 500; color: #666; }
        .btn-logout { color: var(--color-secundario); text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid var(--color-secundario); padding: 5px 15px; border-radius: 4px; transition: all 0.2s; }
        .btn-logout:hover { background: var(--color-secundario); color: #fff; }

        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        .wrapper { background: #fff; max-width: 1100px; margin: 0 auto; padding: 35px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .header-box { border-bottom: 2px solid var(--cf); padding-bottom: 15px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;}
        h2 { margin: 0; color: var(--cp); font-size: 22px; }
        .btn { padding: 8px 15px; font-size: 13px; font-weight: bold; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display:inline-block; }
        .btn-cancel { background: #e2e8f0; color: #333; transition: 0.2s; }
        .btn-cancel:hover { background: #d0d7de; }
        .btn-submit { background: var(--cp); color: #fff; transition: 0.2s; }
        .btn-submit:hover { background: #1a252f; }
        .info-card { background: #f8f9fa; border: 1px solid var(--bc); border-radius: 8px; padding: 18px; border-left: 5px solid var(--cp); margin-bottom: 20px; }
        .ic-title { font-size: 12px; text-transform: uppercase; color: #777; font-weight: bold; margin-bottom: 8px; }
        .ic-value { font-size: 15px; color: #333; }
        
        table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
        th { background: var(--cf); color: #2c3e50; padding: 12px; border-bottom: 2px solid #eaeaea; }
        td { padding: 14px 12px; border-bottom: 1px solid #f1f1f1; vertical-align: middle; }
        tr:hover { background-color: #fafbfc; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .badge-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .badge-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .badge-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <?php 
        $basePath = '../'; 
        include __DIR__ . '/../HTML/sidebar.php'; 
    ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../HTML/topbar.php'; ?>

        <main class="content-area">
            <div class="wrapper">
                <div class="header-box">
                    <h2>Reporte y Proyección: <?php echo htmlspecialchars($alerta['nombreAlerta']); ?></h2>
                    <div>
                        <a href="listar_alertas.php" class="btn btn-cancel">Volver</a>
                    </div>
                </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="info-card">
            <div class="ic-title">Configuración de Disparo</div>
            <div class="ic-value"><strong>Servicio:</strong> <?php echo htmlspecialchars($alerta['nombreServicio']); ?></div>
            <?php 
                $dias = $alerta['diasRecordatorio'];
                if ($dias % 30 == 0 && $dias >= 30) {
                    $meses = $dias / 30;
                    $textoTiempo = $meses . ($meses == 1 ? ' mes' : ' meses');
                } else {
                    $textoTiempo = $dias . ($dias == 1 ? ' día' : ' días');
                }
            ?>
            <div class="ic-value" style="margin-top: 5px;"><strong>Se activará:</strong> a los <?php echo $textoTiempo; ?> posteriores al servicio.</div>
        </div>
        <div class="info-card" style="border-left-color: #f39c12;">
            <div class="ic-title">Vista Previa de Plantilla</div>
            <div class="ic-value" style="font-size: 13px; font-family: monospace; background:#fff; padding:10px; border:1px solid #ddd; border-radius:4px; max-height: 100px; overflow-y: auto;">
                <strong>Asunto:</strong> <?php echo htmlspecialchars($alerta['asuntoMensaje']); ?><br>
                <?php echo nl2br(htmlspecialchars($alerta['plantillaMensaje'])); ?>
            </div>
        </div>
    </div>

    <div style="display:flex; justify-content: space-between; align-items:center; margin-top:30px; border-bottom: 2px solid var(--cf); padding-bottom:10px; margin-bottom:15px;">
        <h3 style="margin:0; color: var(--cp);">Clientes en el ciclo de esta alerta</h3>
        <form method="GET" style="display:flex; gap:10px; align-items:center; background:#f8f9fa; padding:8px 15px; border-radius:6px; border:1px solid var(--bc); flex-wrap:wrap;">
            <input type="hidden" name="id" value="<?php echo $idAlerta; ?>">
            
            <strong style="font-size:13px; color:#555;">Mostrar:</strong>
            <select name="limite" onchange="this.form.submit()" style="padding:6px; border-radius:4px; border:1px solid #ccc; font-size:13px; outline:none;">
                <option value="5" <?php echo $items_por_pagina===5?'selected':''; ?>>5 por pág.</option>
                <option value="10" <?php echo $items_por_pagina===10?'selected':''; ?>>10 por pág.</option>
                <option value="50" <?php echo $items_por_pagina===50?'selected':''; ?>>50 por pág.</option>
            </select>

            <strong style="font-size:13px; color:#555;">Fecha:</strong>
            <input type="date" name="filtro_fecha" value="<?php echo htmlspecialchars($filtro_fecha); ?>" onchange="this.form.submit()" style="padding:6px; border-radius:4px; border:1px solid #ccc; font-size:13px; outline:none;">

            <strong style="font-size:13px; color:#555;">Estado:</strong>
            <select name="filtro_estado" onchange="this.form.submit()" style="padding:6px; border-radius:4px; border:1px solid #ccc; font-size:13px; outline:none;">
                <option value="Todas" <?php echo $filtro_estado==='Todas'?'selected':''; ?>>Todas</option>
                <option value="Pendiente" <?php echo $filtro_estado==='Pendiente'?'selected':''; ?>>Solo Pendientes</option>
                <option value="Enviada" <?php echo $filtro_estado==='Enviada'?'selected':''; ?>>Solo Enviadas</option>
            </select>
            
            <?php if (!empty($filtro_fecha) || $filtro_estado !== 'Todas'): ?>
                <a href="ver_alerta.php?id=<?php echo $idAlerta; ?>" class="btn btn-cancel" style="padding: 6px 10px; font-size:12px;">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Cliente y Email</th>
                <th>Vehículo (Patente)</th>
                <th>Último Servicio</th>
                <th>Fecha de Alerta Proyectada</th>
                <th>Fecha Envío</th>
                <th>Estado Actual</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($filas_procesadas)): ?>
                <tr><td colspan="6" style="text-align: center; padding: 20px; color:#777;">No se encontraron registros bajo este filtro.</td></tr>
            <?php else: ?>
                <?php foreach($filas_pagina as $f): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($f['cliente']); ?></strong><br><span style="color:#666;font-size:11px;"><?php echo htmlspecialchars($f['email']); ?></span></td>
                    <td><?php echo htmlspecialchars($f['vehiculo']); ?></td>
                    <td><?php echo $f['ultimaFechaStr']; ?></td>
                    <td><strong><?php echo $f['fechaProyectadaStr']; ?></strong></td>
                    <td style="color: #28a745; font-weight: bold;"><?php echo $f['fechaEnvioRealStr']; ?></td>
                    <td><span class="badge <?php echo $f['estadoBade']; ?>"><?php echo $f['estadoTexto']; ?></span><?php echo $f['detalleIntento']; ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_paginas > 1): ?>
    <div style="margin-top: 20px; display: flex; justify-content: center; gap: 5px;">
        <?php for($i=1; $i<=$total_paginas; $i++): ?>
            <a href="?id=<?php echo $idAlerta; ?>&filtro_estado=<?php echo urlencode($filtro_estado); ?>&filtro_fecha=<?php echo urlencode($filtro_fecha); ?>&limite=<?php echo $items_por_pagina; ?>&p=<?php echo $i; ?>" class="btn <?php echo ($i === $pagina_actual) ? 'btn-submit' : 'btn-cancel'; ?>" style="padding: 5px 12px;">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>