<?php
/**
 * Lizzosoft Vehículos - Reporte de Productividad del Personal
 * Ubicación: lizzosoft_vehiculos/Reportes/reportePersonalProductividad.php
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

// AUTO-HEAL: Asegurar que existan la tabla de configuración y los campos de tiempo
try {
    $conexion->exec("CREATE TABLE IF NOT EXISTS `configuracion_horarios` (
        `IDConfig` int(11) NOT NULL AUTO_INCREMENT,
        `tipo_jornada` enum('continua','partida') DEFAULT 'continua',
        `hora_inicio_manana` time NOT NULL DEFAULT '08:00:00',
        `hora_fin_manana` time NOT NULL DEFAULT '13:00:00',
        `hora_inicio_tarde` time DEFAULT '14:00:00',
        `hora_fin_tarde` time DEFAULT '18:00:00',
        `dias_laborables` varchar(50) DEFAULT '1,2,3,4,5',
        `empresa_id` int(11) NOT NULL,
        `sucursal_id` int(11) NOT NULL,
        PRIMARY KEY (`IDConfig`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $conexion->exec("INSERT IGNORE INTO `configuracion_horarios` (`tipo_jornada`, `hora_inicio_manana`, `hora_fin_manana`, `hora_inicio_tarde`, `hora_fin_tarde`, `empresa_id`, `sucursal_id`) VALUES ('partida', '08:00:00', '13:00:00', '15:00:00', '19:00:00', $empresa_id, $sucursal_id)");

    try { $conexion->exec("ALTER TABLE registrosservicios ADD COLUMN fechaInicio DATETIME NULL"); } catch(Exception $e) {}
    try { $conexion->exec("ALTER TABLE registrosservicios ADD COLUMN fechaFin DATETIME NULL"); } catch(Exception $e) {}
} catch(Exception $e) {}

$defaultHasta = date('Y-m-d');
$defaultDesde = date('Y-m-d', strtotime('-1 month'));
$f_desde = trim($_GET['f_desde'] ?? $defaultDesde);
$f_hasta = trim($_GET['f_hasta'] ?? $defaultHasta);

if ($f_desde > $f_hasta) {
    $temp = $f_desde;
    $f_desde = $f_hasta;
    $f_hasta = $temp;
}

$confH = ['tipo_jornada' => 'continua', 'hora_inicio_manana' => '08:00:00', 'hora_fin_manana' => '17:00:00', 'dias_laborables' => '1,2,3,4,5'];
try {
    $stmtC = $conexion->prepare("SELECT * FROM configuracion_horarios WHERE empresa_id = ? AND sucursal_id = ? LIMIT 1");
    $stmtC->execute([$empresa_id, $sucursal_id]);
    if ($rowC = $stmtC->fetch(PDO::FETCH_ASSOC)) $confH = $rowC;
} catch (Exception $e) {}

function calcularHorasHabiles($inicio, $fin, $config) {
    if (!$inicio || !$fin) return 0;
    
    $start = new DateTime($inicio);
    $end = new DateTime($fin);
    if ($start >= $end) return 0;

    $dias_lab = explode(',', $config['dias_laborables']);
    $minutosTotales = 0;
    $current = clone $start;

    while ($current < $end) {
        $diaSemana = $current->format('N'); 
        if (in_array($diaSemana, $dias_lab)) {
            $dateStr = $current->format('Y-m-d');
            
            $mStart = new DateTime($dateStr . ' ' . $config['hora_inicio_manana']);
            $mEnd   = new DateTime($dateStr . ' ' . $config['hora_fin_manana']);
            
            $t_start = max($current, $mStart);
            $t_end   = min($end, $mEnd);
            
            if ($t_start < $t_end) {
                $minutosTotales += ($t_end->getTimestamp() - $t_start->getTimestamp()) / 60;
            }

            if ($config['tipo_jornada'] === 'partida' && !empty($config['hora_inicio_tarde']) && !empty($config['hora_fin_tarde'])) {
                $tardeStart = new DateTime($dateStr . ' ' . $config['hora_inicio_tarde']);
                $tardeEnd   = new DateTime($dateStr . ' ' . $config['hora_fin_tarde']);
                
                $t_start2 = max($current, $tardeStart);
                $t_end2   = min($end, $tardeEnd);

                if ($t_start2 < $t_end2) {
                    $minutosTotales += ($t_end2->getTimestamp() - $t_start2->getTimestamp()) / 60;
                }
            }
        }
        $current->modify('+1 day')->setTime(0, 0, 0); 
    }
    return round($minutosTotales / 60, 2); 
}

try {
    // Solo extrae Empleados que tengan Rol 2 o que sean mecánicos.
    $sql = "
        SELECT p.numeroDocumentoPersonal, p.nombre, p.apellido, p.IDUsuario,
               COUNT(DISTINCT rs.IDRegistroServicio) as ordenes_atendidas,
               IFNULL(SUM(dr.costoServicio), 0) as valor_generado
        FROM personal p
        LEFT JOIN usuarios u ON p.IDUsuario = u.IDUsuario
        LEFT JOIN roles r ON u.IDRol = r.IDRol
        LEFT JOIN registrosservicios rs ON p.numeroDocumentoPersonal = rs.numeroDocumentoPersonal 
              AND rs.empresa_id = p.empresa_id 
              AND rs.sucursal_id = p.sucursal_id
              AND rs.IDEstado IN (4, 5)
              AND DATE(rs.fechaRegistroServicio) BETWEEN :desde AND :hasta
        LEFT JOIN detalleregistro dr ON rs.IDRegistroServicio = dr.IDRegistroServicio
        WHERE p.empresa_id = :empresa_id AND p.sucursal_id = :sucursal_id AND p.estado = 'Activo'
        AND (u.IDUsuario IS NULL OR r.IDRol = 2 OR LOWER(r.nombreRol) LIKE '%mecánico%' OR LOWER(r.nombreRol) LIKE '%mecanico%')
        GROUP BY p.IDPersonal
        ORDER BY valor_generado DESC, ordenes_atendidas DESC
    ";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':empresa_id' => $empresa_id, ':sucursal_id' => $sucursal_id, ':desde' => $f_desde, ':hasta' => $f_hasta]);
    $reporteBase = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Paginar de a 5
    $limite = 5;
    $pagina = max(1, (int)($_GET['pagina'] ?? 1));
    $totalRegistros = count($reporteBase);
    $totalPaginas = ceil($totalRegistros / $limite);
    $offset = ($pagina - 1) * $limite;
    $reporteBasePaginado = array_slice($reporteBase, $offset, $limite);

    $stmtOTs = $conexion->prepare("SELECT numeroDocumentoPersonal, fechaInicio, fechaFin, fechaRegistroServicio FROM registrosservicios WHERE empresa_id = ? AND sucursal_id = ? AND IDEstado IN (4,5) AND DATE(fechaRegistroServicio) BETWEEN ? AND ?");
    $stmtOTs->execute([$empresa_id, $sucursal_id, $f_desde, $f_hasta]);
    $tiemposOTs = $stmtOTs->fetchAll(PDO::FETCH_ASSOC);

    $tiemposPorDoc = [];
    foreach ($tiemposOTs as $tot) {
        $doc = $tot['numeroDocumentoPersonal'];
        if (!isset($tiemposPorDoc[$doc])) $tiemposPorDoc[$doc] = ['horas_totales' => 0, 'cantidad' => 0];
        
        $hEfectivas = calcularHorasHabiles($tot['fechaInicio'] ?: $tot['fechaRegistroServicio'], $tot['fechaFin'] ?: date('Y-m-d H:i:s'), $confH);
        $tiemposPorDoc[$doc]['horas_totales'] += $hEfectivas;
        $tiemposPorDoc[$doc]['cantidad']++;
    }

    $reporteFinal = [];
    foreach ($reporteBasePaginado as $rb) {
        $doc = $rb['numeroDocumentoPersonal'];
        $promedioHoras = '-';
        if (!empty($rb['IDUsuario'])) {
            $promedioHoras = 0;
            if (isset($tiemposPorDoc[$doc]) && $tiemposPorDoc[$doc]['cantidad'] > 0) {
                $promedioHoras = round($tiemposPorDoc[$doc]['horas_totales'] / $tiemposPorDoc[$doc]['cantidad'], 1);
            }
        }
        $rb['tiempo_promedio'] = $promedioHoras;
        $reporteFinal[] = $rb;
    }

} catch (PDOException $e) {
    die("Error al generar el reporte.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productividad del Personal - <?php echo htmlspecialchars($config['nombre_empresa']); ?></title>
    <style>
        :root { --color-primario: <?php echo htmlspecialchars($apariencia['color_primario']); ?>; --color-secundario: <?php echo htmlspecialchars($apariencia['color_secundario']); ?>; --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo']); ?>; --sidebar-width: 270px; }
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
        .filter-container input { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; outline: none; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; text-align: left; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        th { background: #f8f9fa; padding: 14px; font-size: 13px; border-bottom: 2px solid #eaeaea; }
        td { padding: 14px; border-bottom: 1px solid #f1f1f1; font-size: 14px; }
        /* PAGINATION */
        .pagination-container { display: flex; justify-content: center; align-items: center; padding: 20px; gap: 5px; }
        .page-link { display: inline-flex; justify-content: center; align-items: center; min-width: 32px; height: 32px; padding: 0 10px; background: #fff; border: 1px solid #dee2e6; border-radius: 4px; color: var(--color-primario); text-decoration: none; font-size: 13px; font-weight: 500; transition: all 0.2s; }
        .page-link:hover:not(.active):not(.disabled) { background: #e9ecef; border-color: #dee2e6; color: var(--color-primario); }
        .page-link.active { background: var(--color-primario); border-color: var(--color-primario); color: #fff; cursor: default; }
        .page-link.disabled { color: #6c757d; pointer-events: none; background: #f8f9fa; border-color: #dee2e6; }
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
                <h1 style="margin: 0; font-size: 24px; color: var(--color-primario);">Productividad Operativa del Personal</h1>
                <p style="font-size: 13px; color: #666;">Nota: El cálculo de horas omite noches y fines de semana según la configuración de jornada laboral del sistema.</p>
            </div>

            <form method="GET" class="filter-container">
                <strong style="font-size: 13px;">Fechas de Órdenes Completadas:</strong>
                <input type="date" name="f_desde" value="<?php echo htmlspecialchars($f_desde); ?>" onchange="this.form.submit()">
                <span>hasta</span>
                <input type="date" name="f_hasta" value="<?php echo htmlspecialchars($f_hasta); ?>" onchange="this.form.submit()">
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Empleado / Mecánico</th>
                        <th style="text-align: center;">Órdenes Completadas</th>
                        <th style="text-align: center;">Tpo. Efectivo Promedio / OT</th>
                        <th style="text-align: right;">Ingresos Totales Generados</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reporteFinal)): ?>
                        <tr><td colspan="4" style="text-align:center; padding: 30px; color: #888;">No hay personal activo o no completaron órdenes en este periodo.</td></tr>
                    <?php else: ?>
                        <?php foreach($reporteFinal as $r): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($r['apellido'] . ', ' . $r['nombre']); ?></strong><br><span style="font-size:11px;color:#777;">DNI: <?php echo htmlspecialchars($r['numeroDocumentoPersonal']); ?></span></td>
                                <td style="text-align: center; font-weight: bold;"><?php echo $r['ordenes_atendidas']; ?></td>
                                <td style="text-align: center; color: #d97706;"><strong><?php echo $r['tiempo_promedio'] === '-' ? '<span style="color:#999;font-weight:normal;">No Registra</span>' : $r['tiempo_promedio'] . ' Horas'; ?></strong></td>
                                <td style="text-align: right; color: var(--color-primario); font-weight: bold; font-size: 15px;">$ <?php echo number_format($r['valor_generado'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalPaginas > 1): ?>
                <?php 
                    $queryParams = $_GET;
                    unset($queryParams['pagina']);
                    $baseUrlPaginacion = "?".http_build_query($queryParams)."&pagina="; 
                ?>
                <div class="pagination-container">
                    <?php if ($pagina > 1): ?>
                        <a href="<?php echo $baseUrlPaginacion . ($pagina - 1); ?>" class="page-link">&laquo; Anterior</a>
                    <?php else: ?>
                        <span class="page-link disabled">&laquo; Anterior</span>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                        <?php if ($i == $pagina): ?>
                            <span class="page-link active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo $baseUrlPaginacion . $i; ?>" class="page-link"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($pagina < $totalPaginas): ?>
                        <a href="<?php echo $baseUrlPaginacion . ($pagina + 1); ?>" class="page-link">Siguiente &raquo;</a>
                    <?php else: ?>
                        <span class="page-link disabled">Siguiente &raquo;</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        /* JS del sidebar movido a HTML/sidebar.php */
    </script>
</body>
</html>