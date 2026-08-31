<?php
/**
 * Lizzosoft Vehículos - Reporte de Ingresos por Servicios
 * Ubicación: lizzosoft_vehiculos/Reportes/ingresosServicio.php
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$config     = $_SESSION['cliente_config'];
$apariencia = $config['apariencia'];
$empresa_id = (int)$_SESSION['empresa_id'];
$sucursal_id= (int)$_SESSION['sucursal_id'];
$idRol      = (int)$_SESSION['IDRol'];
$areas      = $_SESSION['areas_permitidas'] ?? [];
$labelRodado = $config['labels']['vehiculo_plural'] ?? 'Vehículos';

if (!in_array(2, $areas) && !in_array($idRol, [1, 3])) {
    die("<div style='padding:20px; color:#721c24; background:#f8d7da;'>Error: No tienes permisos para acceder a Reportes.</div>");
}

$conexion = obtenerConexion();

$defaultHasta = date('Y-m-t', strtotime('last day of last month'));
$defaultDesde = date('Y-m-01', strtotime('first day of last month'));

$defaultHasta2 = date('Y-m-t', strtotime('last day of -2 months'));
$defaultDesde2 = date('Y-m-01', strtotime('first day of -2 months'));

$f_desde = trim($_GET['f_desde'] ?? $defaultDesde);
$f_hasta = trim($_GET['f_hasta'] ?? $defaultHasta);

$f_desde2 = trim($_GET['f_desde2'] ?? $defaultDesde2);
$f_hasta2 = trim($_GET['f_hasta2'] ?? $defaultHasta2);

$f_servicio = trim($_GET['f_servicio'] ?? '');
$modo = trim($_GET['modo'] ?? '1');

function obtenerReporte($conexion, $desde, $hasta, $servicio, $empresa_id, $sucursal_id) {
    // Si la fecha desde es mayor a la fecha hasta, las invertimos automáticamente.
    if ($desde > $hasta) {
        $temp = $desde;
        $desde = $hasta;
        $hasta = $temp;
    }
    $whereSql = "WHERE rs.empresa_id = :emp_id AND rs.sucursal_id = :suc_id AND DATE(rs.fechaRegistroServicio) BETWEEN :desde AND :hasta";
    $params = [':emp_id' => $empresa_id, ':suc_id' => $sucursal_id, ':desde' => $desde, ':hasta' => $hasta];
    
    if ($servicio !== '') {
        $whereSql .= " AND s.IDServicio = :servicio";
        $params[':servicio'] = (int)$servicio;
    }
    
    $sql = "
        SELECT s.nombreServicio, COUNT(dr.IDDetalleregistro) AS cantidad_servicios, SUM(dr.costoServicio) AS total_ingresos
        FROM detalleregistro dr
        INNER JOIN servicios s ON dr.IDServicio = s.IDServicio
        INNER JOIN registrosservicios rs ON dr.IDRegistroServicio = rs.IDRegistroServicio
        $whereSql
        GROUP BY s.IDServicio, s.nombreServicio
        ORDER BY total_ingresos DESC
    ";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalFacturado = 0;
    $totalServicios = 0;
    $nombresChart = [];
    $totalesChart = [];

    foreach($reporte as $r) {
        $totalFacturado += $r['total_ingresos'];
        $totalServicios += $r['cantidad_servicios'];
        $nombresChart[] = $r['nombreServicio'];
        $totalesChart[] = $r['total_ingresos'];
    }
    
    return [
        'datos' => $reporte,
        'totalFacturado' => $totalFacturado,
        'totalServicios' => $totalServicios,
        'nombresChart' => $nombresChart,
        'totalesChart' => $totalesChart
    ];
}

try {
    // Extraer catálogo de servicios para el filtro
    $stmtS = $conexion->prepare("SELECT IDServicio, nombreServicio FROM servicios WHERE empresa_id = ? AND estado = 'Activo' ORDER BY nombreServicio ASC");
    $stmtS->execute([$empresa_id]);
    $listaServicios = $stmtS->fetchAll(PDO::FETCH_ASSOC);

    // Consulta de ambos reportes
    $reporte1 = obtenerReporte($conexion, $f_desde, $f_hasta, $f_servicio, $empresa_id, $sucursal_id);
    
    if ($modo === '2') {
        $reporte2 = obtenerReporte($conexion, $f_desde2, $f_hasta2, $f_servicio, $empresa_id, $sucursal_id);
    } else {
        $reporte2 = ['datos' => []];
    }

} catch (PDOException $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresos por Servicios - <?php echo htmlspecialchars($config['nombre_empresa']); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --color-primario: <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>; --color-secundario: <?php echo htmlspecialchars($apariencia['color_secundario'] ?? '#e74c3c'); ?>; --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>; --sidebar-width: 270px; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; background-color: var(--color-fondo); color: #333; display: flex; height: 100vh; overflow: hidden; }
        
        /* CSS del sidebar movido a HTML/sidebar.php */

        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar { background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); flex-shrink: 0; z-index: 10; }
        .user-info { font-size: 13px; font-weight: 500; color: #666; }
        .btn-logout { color: var(--color-secundario); text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid var(--color-secundario); padding: 5px 15px; border-radius: 4px; transition: all 0.2s; }
        .btn-logout:hover { background: var(--color-secundario); color: #fff; }
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        .panel-header { margin-bottom: 25px; }
        .filter-container { background: #fff; padding: 15px; border-radius: 6px; border: 1px solid #eef0f2; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .filter-container select, .filter-container input { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; outline: none; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; text-align: left; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        th { background: #f8f9fa; padding: 14px; font-size: 13px; border-bottom: 2px solid #eaeaea; }
        td { padding: 14px; border-bottom: 1px solid #f1f1f1; font-size: 14px; }
        .totales { background: var(--color-primario); color: white; font-weight: bold; }
        .grafico-container { background: #fff; border-radius: 6px; padding: 20px; border: 1px solid #eef0f2; margin-bottom: 20px; height: 300px; display: flex; justify-content: center; }
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
            <div class="panel-header">
                <h1 style="margin: 0; font-size: 24px; color: var(--color-primario);">Reporte de Ingresos por Servicios</h1>
            </div>

            <form method="GET" class="filter-container" style="flex-direction: column; align-items: flex-start;">
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div style="display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 6px; border: 1px solid #eef0f2;">
                        <strong style="font-size: 13px;">Modo de Vista:</strong>
                        <select name="modo" id="modo_select" style="max-width: 200px;" onchange="this.form.submit()">
                            <option value="1" <?php echo $modo === '1' ? 'selected' : ''; ?>>Un Período</option>
                            <option value="2" <?php echo $modo === '2' ? 'selected' : ''; ?>>Comparativa (2 Períodos)</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 6px; border: 1px solid #eef0f2;">
                        <strong style="font-size: 13px; color: var(--color-primario);">Período 1:</strong>
                        <input type="date" name="f_desde" value="<?php echo htmlspecialchars($f_desde); ?>" onchange="this.form.submit()">
                        <span>hasta</span>
                        <input type="date" name="f_hasta" value="<?php echo htmlspecialchars($f_hasta); ?>" onchange="this.form.submit()">
                    </div>
                    
                    <div id="panel_periodo2" style="display: <?php echo $modo === '2' ? 'flex' : 'none'; ?>; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 6px; border: 1px solid #eef0f2;">
                        <strong style="font-size: 13px; color: var(--color-secundario);">Período 2:</strong>
                        <input type="date" name="f_desde2" value="<?php echo htmlspecialchars($f_desde2); ?>" onchange="this.form.submit()">
                        <span>hasta</span>
                        <input type="date" name="f_hasta2" value="<?php echo htmlspecialchars($f_hasta2); ?>" onchange="this.form.submit()">
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; align-items: center; margin-top: 10px; width: 100%;">
                    <strong style="font-size: 13px;">Servicio Específico:</strong>
                    <select name="f_servicio" style="max-width: 250px;" onchange="this.form.submit()">
                        <option value="">Todos los Servicios</option>
                        <?php foreach($listaServicios as $srv): ?>
                            <option value="<?php echo $srv['IDServicio']; ?>" <?php echo $f_servicio == $srv['IDServicio'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($srv['nombreServicio']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <div style="display: grid; grid-template-columns: <?php echo $modo === '2' ? '1fr 1fr' : '1fr'; ?>; gap: 30px;">
                <!-- COLUMNA PERIODO 1 -->
                <div>
                    <h3 style="margin-top: 0; color: var(--color-primario); border-bottom: 2px solid var(--color-primario); padding-bottom: 10px;">Resultados Período 1</h3>
                    <?php if (!empty($reporte1['datos'])): ?>
                        <div class="grafico-container">
                            <canvas id="graficoIngresos1"></canvas>
                        </div>
                    <?php endif; ?>

                    <table>
                        <thead>
                            <tr>
                                <th>Servicio Operativo</th>
                                <th style="text-align: center;">Cant.</th>
                                <th style="text-align: right;">Ingresos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reporte1['datos'])): ?>
                                <tr><td colspan="3" style="text-align:center; padding: 30px; color: #888;">Sin ingresos.</td></tr>
                            <?php else: ?>
                                <?php foreach($reporte1['datos'] as $r): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($r['nombreServicio']); ?></strong></td>
                                        <td style="text-align: center;"><?php echo $r['cantidad_servicios']; ?></td>
                                        <td style="text-align: right; color: var(--color-primario); font-weight: bold;">$ <?php echo number_format($r['total_ingresos'], 2, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="totales">
                                    <td style="text-align: right;">TOTAL:</td>
                                    <td style="text-align: center;"><?php echo $reporte1['totalServicios']; ?></td>
                                    <td style="text-align: right; font-size: 15px;">$ <?php echo number_format($reporte1['totalFacturado'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($modo === '2'): ?>
                <!-- COLUMNA PERIODO 2 -->
                <div>
                    <h3 style="margin-top: 0; color: var(--color-secundario); border-bottom: 2px solid var(--color-secundario); padding-bottom: 10px;">Resultados Período 2</h3>
                    <?php if (!empty($reporte2['datos'])): ?>
                        <div class="grafico-container">
                            <canvas id="graficoIngresos2"></canvas>
                        </div>
                    <?php endif; ?>

                    <table>
                        <thead>
                            <tr>
                                <th>Servicio Operativo</th>
                                <th style="text-align: center;">Cant.</th>
                                <th style="text-align: right;">Ingresos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reporte2['datos'])): ?>
                                <tr><td colspan="3" style="text-align:center; padding: 30px; color: #888;">Sin ingresos.</td></tr>
                            <?php else: ?>
                                <?php foreach($reporte2['datos'] as $r): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($r['nombreServicio']); ?></strong></td>
                                        <td style="text-align: center;"><?php echo $r['cantidad_servicios']; ?></td>
                                        <td style="text-align: right; color: var(--color-secundario); font-weight: bold;">$ <?php echo number_format($r['total_ingresos'], 2, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="totales" style="background: var(--color-secundario);">
                                    <td style="text-align: right;">TOTAL:</td>
                                    <td style="text-align: center;"><?php echo $reporte2['totalServicios']; ?></td>
                                    <td style="text-align: right; font-size: 15px;">$ <?php echo number_format($reporte2['totalFacturado'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        /* JS del sidebar movido a HTML/sidebar.php */
        const esModoOscuro = document.body.classList.contains('tema-oscuro');
        Chart.defaults.color = esModoOscuro ? '#ffffff' : '#666';
        Chart.defaults.borderColor = esModoOscuro ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';

        <?php if (!empty($reporte1['datos'])): ?>
        const ctx1 = document.getElementById('graficoIngresos1').getContext('2d');
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($reporte1['nombresChart']); ?>,
                datasets: [{
                    label: 'Ingresos Período 1 ($)',
                    data: <?php echo json_encode($reporte1['totalesChart']); ?>,
                    backgroundColor: 'rgba(44, 62, 80, 0.6)',
                    borderColor: 'rgba(44, 62, 80, 1)',
                    borderWidth: 1
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
        <?php endif; ?>

        <?php if (!empty($reporte2['datos'])): ?>
        const ctx2 = document.getElementById('graficoIngresos2').getContext('2d');
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($reporte2['nombresChart']); ?>,
                datasets: [{
                    label: 'Ingresos Período 2 ($)',
                    data: <?php echo json_encode($reporte2['totalesChart']); ?>,
                    backgroundColor: 'rgba(231, 76, 60, 0.6)',
                    borderColor: 'rgba(231, 76, 60, 1)',
                    borderWidth: 1
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
        <?php endif; ?>
    </script>
</body>
</html>