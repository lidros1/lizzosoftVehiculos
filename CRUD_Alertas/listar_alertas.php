<?php
/**
 * Lizzosoft Vehículos - Catálogo de Alertas
 * Ubicación: lizzosoft_vehiculos/CRUD_Alertas/listar_alertas.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$config     = $_SESSION['cliente_config'];
$apariencia = $config['apariencia'];
$empresa_id = (int)$_SESSION['empresa_id'];
$idRol      = (int)$_SESSION['IDRol'];
$areas      = $_SESSION['areas_permitidas'] ?? [];

$conexion = obtenerConexion();
$busqueda = trim($_GET['q'] ?? '');
$f_estado = trim($_GET['f_estado'] ?? 'Todas');
$f_servicio = (int)($_GET['f_servicio'] ?? 0);

$whereSql = "WHERE a.empresa_id = :empresa_id";
$params = [':empresa_id' => $empresa_id];

if ($f_estado !== 'Todas') {
    $whereSql .= " AND a.estado = :f_estado";
    $params[':f_estado'] = $f_estado;
}

if ($f_servicio > 0) {
    $whereSql .= " AND a.IDServicio = :f_servicio";
    $params[':f_servicio'] = $f_servicio;
}

// Motor de Búsqueda Avanzada (Match Múltiple)
if ($busqueda !== '') {
    $terminos = array_filter(explode(' ', $busqueda));
    $indice = 0;
    
    foreach ($terminos as $termino) {
        $cadenaVirtual = "CONCAT_WS(' ', a.nombreAlerta, s.nombreServicio)";
        $whereSql .= " AND $cadenaVirtual LIKE :q_termino_$indice";
        $params[":q_termino_$indice"] = '%' . $termino . '%';
        $indice++;
    }
}

$limit = (isset($_GET['limite']) && in_array((int) $_GET['limite'], [5, 10, 50])) ? (int) $_GET['limite'] : 10;
$pagina = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
$offset = ($pagina - 1) * $limit;

try {
    $sqlCount = "
        SELECT COUNT(*) as total
        FROM alertas_servicios a
        INNER JOIN servicios s ON a.IDServicio = s.IDServicio
        $whereSql
    ";
    $stmtC = $conexion->prepare($sqlCount);
    $stmtC->execute($params);
    $totalFilas = $stmtC->fetch()['total'];
    $totalPaginas = ceil($totalFilas / $limit);

    $query = "
        SELECT a.*, s.nombreServicio 
        FROM alertas_servicios a
        INNER JOIN servicios s ON a.IDServicio = s.IDServicio
        $whereSql
        ORDER BY a.nombreAlerta ASC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $conexion->prepare($query);
    $stmt->execute($params);
    $alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener servicios para el filtro
    $stmtSrv = $conexion->query("SELECT IDServicio, nombreServicio FROM servicios ORDER BY nombreServicio ASC");
    $serviciosFiltro = $stmtSrv->fetchAll(PDO::FETCH_ASSOC);

    // MODO LIBRERIA: Incluir procesar_alertas.php sin ejecutarlo, y llamar la simulación global
    define('INCLUIDO_COMO_LIBRERIA', true);
    require_once __DIR__ . '/procesar_alertas.php';
    
    // Obtenemos los conteos en modo simulación (false=no enviar, false=no filtrar 1 alerta, simular=true)
    $resultadoStats = ejecutarMotorAlertas($conexion, true, 0);
    $detalleAlertas = $resultadoStats['detalle_alertas'] ?? [];

} catch (PDOException $e) {
    die("Error al cargar alertas: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Alertas - <?php echo htmlspecialchars($config['nombre_empresa']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { 
            --color-primario: <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>; 
            --color-secundario: <?php echo htmlspecialchars($apariencia['color_secundario'] ?? '#e74c3c'); ?>; 
            --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>; 
            --sidebar-width: 270px; 
        }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: var(--color-fondo); margin: 0; display: flex; height: 100vh; overflow: hidden; color: #333; }
        
        /* CSS del sidebar movido a HTML/sidebar.php */

        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar { background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); flex-shrink: 0; z-index: 10; }
        .user-info { font-size: 13px; font-weight: 500; color: #666; }
        .btn-logout { color: var(--color-secundario); text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid var(--color-secundario); padding: 5px 15px; border-radius: 4px; transition: all 0.2s; }
        .btn-logout:hover { background: var(--color-secundario); color: #fff; }

        /* Contenido Principal */
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        .wrapper-content { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin: 0 auto; max-width: 1200px; }
        .header-box { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--color-fondo); padding-bottom: 15px; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        h2 { margin: 0; color: var(--color-primario); font-size: 22px; }
        
        .search-form { display: flex; gap: 10px; }
        .search-input { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; width: 300px; }
        .btn { padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; text-decoration: none; font-size: 13px; border: none; }
        .btn-search { background: var(--color-primario); color: white; }
        .btn-clear { background: #e2e8f0; color: #333; display: flex; align-items: center;}
        .btn-add { background: var(--color-primario); color: white; padding: 10px 20px; font-size: 14px; transition: 0.2s; }
        .btn-add:hover { opacity: 0.9; }
        .btn-process { background: #28a745; color: white; padding: 10px 20px; font-size: 14px; transition: 0.2s; margin-left: 10px; }
        .btn-process:hover { background: #218838; }
        
        table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
        th { background: #f8f9fa; color: #444; padding: 14px; font-weight: 600; border-bottom: 2px solid #eaeaea; }
        td { padding: 14px; border-bottom: 1px solid #f1f1f1; vertical-align: middle; }
        tr:hover { background-color: #fafbfc; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-inactive { background: #f8d7da; color: #721c24; }
        
        .action-group { display: flex; gap: 8px; }
        .btn-ver { background: #f8f9fa; color: var(--color-primario); border: 1px solid var(--color-primario); }
        .btn-ver:hover { background: var(--color-primario); color: #fff; }
        .btn-editar { background: #e2e8f0; color: #333; }
        .btn-editar:hover { background: #cbd5e0; }
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
            <div class="wrapper-content">
                <div class="header-box" style="border-bottom:none; margin-bottom:10px;">
                    <h2>Gestión de Alertas y Recordatorios</h2>
                    <div>
                        <a href="crear_alerta.php" class="btn btn-add">Nueva Alerta</a>
                    </div>
                </div>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 25px; border: 1px solid #dee2e6;">
                    <form class="search-form" method="GET" style="margin: 0; flex-wrap: wrap;">
                        <select name="limite" class="search-input" style="width: auto;" onchange="this.form.submit()">
                            <option value="5" <?php echo $limit===5?'selected':''; ?>>5 por pág.</option>
                            <option value="10" <?php echo $limit===10?'selected':''; ?>>10 por pág.</option>
                            <option value="50" <?php echo $limit===50?'selected':''; ?>>50 por pág.</option>
                        </select>
                        <select name="f_estado" class="search-input" style="width: auto;">
                            <option value="Todas" <?php echo $f_estado==='Todas'?'selected':''; ?>>Estado: Todos</option>
                            <option value="Activo" <?php echo $f_estado==='Activo'?'selected':''; ?>>Activas</option>
                            <option value="Inactivo" <?php echo $f_estado==='Inactivo'?'selected':''; ?>>Inactivas</option>
                        </select>

                        <select name="f_servicio" class="search-input" style="width: auto; max-width: 250px;">
                            <option value="0">Todos los servicios</option>
                            <?php foreach($serviciosFiltro as $s): ?>
                                <option value="<?php echo $s['IDServicio']; ?>" <?php echo $f_servicio==$s['IDServicio']?'selected':''; ?>><?php echo htmlspecialchars($s['nombreServicio']); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <input type="text" name="q" class="search-input" style="flex-grow: 1; min-width: 200px;" placeholder="Buscar alerta o servicio..." value="<?php echo htmlspecialchars($busqueda); ?>">
                        <button type="submit" class="btn btn-search">Filtrar / Buscar</button>
                        <?php if($busqueda !== '' || $f_estado !== 'Todas' || $f_servicio > 0): ?><a href="listar_alertas.php" class="btn btn-clear">Limpiar</a><?php endif; ?>
                    </form>
                </div>
                
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre de la Alerta</th>
                                <th>Servicio Asociado</th>
                                <th>Tiempo de Activación</th>
                                <th>Estado</th>
                                <th>Conteo Alerta</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($alertas) === 0): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 40px; color: #777;">No existen alertas registradas o que coincidan con la búsqueda.</td></tr>
                            <?php else: ?>
                                <?php foreach($alertas as $a): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($a['nombreAlerta']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($a['nombreServicio']); ?></td>
                                        <?php 
                                        $dias = $a['diasRecordatorio'];
                                        if ($dias % 30 == 0 && $dias >= 30) {
                                            $meses = $dias / 30;
                                            $textoTiempo = $meses . ($meses == 1 ? ' mes' : ' meses');
                                        } else {
                                            $textoTiempo = $dias . ($dias == 1 ? ' día' : ' días');
                                        }
                                        ?>
                                        <td>A los <strong><?php echo htmlspecialchars($textoTiempo); ?></strong> del servicio</td>
                                        <td>
                                            <span class="badge <?php echo ($a['estado'] === 'Activo') ? 'badge-active' : 'badge-inactive'; ?>"><?php echo htmlspecialchars($a['estado']); ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $stats = $detalleAlertas[$a['IDAlerta']] ?? ['en_cola' => 0, 'programadas' => 0];
                                            ?>
                                            <div style="font-size: 12px; color: #555;">
                                                <strong>Programadas:</strong> <?php echo $stats['programadas']; ?><br>
                                                <strong>En Cola:</strong> <?php echo $stats['en_cola']; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-group">
                                                <a href="ver_alerta.php?id=<?php echo $a['IDAlerta']; ?>" class="btn btn-ver">Proyección</a>
                                                <a href="editar_alerta.php?id=<?php echo $a['IDAlerta']; ?>" class="btn btn-editar">Editar</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($totalPaginas > 1): ?>
                    <div style="display:flex; justify-content:center; gap:5px; margin-top:20px;">
                        <?php
                        $queryParams = $_GET;
                        for ($i = 1; $i <= $totalPaginas; $i++):
                            $queryParams['pagina'] = $i;
                            $link = '?' . http_build_query($queryParams);
                            $active = ($i === $pagina) ? 'background: var(--color-primario); color: white;' : 'background: white; color: var(--color-primario);';
                            ?>
                                    <a href="<?php echo $link; ?>" style="padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; border-radius: 4px; font-weight: bold; <?php echo $active; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        /* JS del sidebar movido a HTML/sidebar.php */
    </script>
</body>
</html>