<?php
session_start();

// --- Procesamiento AJAX para Búsqueda ---
if (isset($_GET['ajax_search'])) {
    require_once __DIR__ . '/../Conexion/Conexion.php';
    header('Content-Type: application/json');
    $sucursal_id = (int)($_SESSION['sucursal_id'] ?? 1);
    $busqueda = trim($_GET['q'] ?? '');
    if (strlen($busqueda) < 3) {
        echo json_encode([]);
        exit;
    }
    $conexion = obtenerConexion();
    $terminos = array_filter(explode(' ', $busqueda));
    $where = "WHERE p.IDSucursal = :suc_id";
    $params = [':suc_id' => $sucursal_id];
    $indice = 0;
    foreach ($terminos as $termino) {
        $cadenaVirtual = "CONCAT_WS(' ', LPAD(CAST(p.IDPresupuesto AS CHAR), 6, '0'), CAST(p.IDPresupuesto AS CHAR), v.patente, p.casual_patente, c.nombre, c.apellido, p.casual_nombre, p.casual_apellido, CAST(c.numeroDocumentoCliente AS CHAR))";
        $where .= " AND $cadenaVirtual LIKE :q_$indice";
        $params[":q_$indice"] = '%' . $termino . '%';
        $indice++;
    }
    try {
        $sql = "SELECT p.IDPresupuesto, p.estado, 
                       IFNULL(v.patente, p.casual_patente) as patente, 
                       IFNULL(c.nombre, p.casual_nombre) as nombre, 
                       IFNULL(c.apellido, p.casual_apellido) as apellido
                FROM presupuestos p
                LEFT JOIN vehiculos v ON p.IDVehiculo = v.IDVehiculo
                LEFT JOIN clientes c ON p.IDCliente = c.IDCliente
                $where
                ORDER BY p.IDPresupuesto DESC
                LIMIT 5";
        $stmt = $conexion->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// --- Procesamiento de cambio de estado (Nativo PHP sin AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_cambiar_estado'])) {
    require_once __DIR__ . '/../Conexion/Conexion.php';
    $conn = obtenerConexion();
    
    $idUpdate = (int) $_POST['idPresupuesto'];
    $nuevoEstado = $_POST['nuevo_estado'];

    try {
        $stmtUpd = $conn->prepare("UPDATE presupuestos SET estado = ? WHERE IDPresupuesto = ?");
        $stmtUpd->execute([$nuevoEstado, $idUpdate]);
    } catch (Throwable $e) {
        $_SESSION['db_error_estado'] = $e->getMessage();
    }

    $query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: listar_presupuestos.php" . $query);
    exit;
}
// ---------------------------------------------------------------

require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$conn = obtenerConexion();
$idSucursal = $_SESSION['sucursal_id'] ?? 1;
$temaActual = $_SESSION['tema_preferido'] ?? 'claro';
$config = $_SESSION['cliente_config'] ?? [];
$apariencia = $config['apariencia'] ?? [];

$defaultHasta = date('Y-m-d');
$defaultDesde = date('Y-m-d', strtotime('-3 months'));

$limit = (isset($_GET['limite']) && in_array((int) $_GET['limite'], [5, 10, 50])) ? (int) $_GET['limite'] : 5;
$pagina = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
$offset = ($pagina - 1) * $limit;

$f_estado = $_GET['f_estado'] ?? 'Todos';
$f_busqueda = trim($_GET['f_busqueda'] ?? '');
$f_fecha_desde = trim($_GET['f_fecha_desde'] ?? $defaultDesde);
$f_fecha_hasta = trim($_GET['f_fecha_hasta'] ?? $defaultHasta);

if (!strtotime($f_fecha_desde)) $f_fecha_desde = $defaultDesde;
if (!strtotime($f_fecha_hasta)) $f_fecha_hasta = $defaultHasta;

$whereParams = ["p.IDSucursal = ?"];
$values = [$idSucursal];

$whereParams[] = "DATE(p.fecha_creacion) BETWEEN ? AND ?";
$values[] = $f_fecha_desde;
$values[] = $f_fecha_hasta;

if ($f_estado !== 'Todos') {
    $whereParams[] = "p.estado = ?";
    $values[] = $f_estado;
}

if ($f_busqueda !== '') {
    $terminos = array_filter(explode(' ', $f_busqueda));
    foreach ($terminos as $termino) {
        $cadenaVirtual = "CONCAT_WS(' ', LPAD(CAST(p.IDPresupuesto AS CHAR), 6, '0'), CAST(p.IDPresupuesto AS CHAR), v.patente, p.casual_patente, c.nombre, c.apellido, p.casual_nombre, p.casual_apellido, CAST(c.numeroDocumentoCliente AS CHAR))";
        $whereParams[] = "$cadenaVirtual LIKE ?";
        $values[] = '%' . $termino . '%';
    }
}

$whereClause = implode(" AND ", $whereParams);

$sqlCount = "SELECT COUNT(*) as total FROM presupuestos p 
             LEFT JOIN clientes c ON p.IDCliente = c.IDCliente
             LEFT JOIN vehiculos v ON p.IDVehiculo = v.IDVehiculo
             LEFT JOIN registrosservicios r ON p.IDOrdenTrabajo = r.IDRegistroServicio
             WHERE $whereClause";
$stmt = $conn->prepare($sqlCount);
$stmt->execute($values);
$totalFilas = $stmt->fetch()['total'];
$totalPaginas = ceil($totalFilas / $limit);

$sql = "
    SELECT p.*, c.nombre as cli_nom, c.apellido as cli_ape, c.numeroDocumentoCliente, v.patente, r.numeroOrdenTrabajo as num_orden_real
    FROM presupuestos p
    LEFT JOIN clientes c ON p.IDCliente = c.IDCliente
    LEFT JOIN vehiculos v ON p.IDVehiculo = v.IDVehiculo
    LEFT JOIN registrosservicios r ON p.IDOrdenTrabajo = r.IDRegistroServicio
    WHERE $whereClause
    ORDER BY p.fecha_creacion DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $conn->prepare($sql);
$stmt->execute($values);
$presupuestos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Presupuestos</title>
    <?php if($temaActual === 'oscuro'): ?>
    <link rel="stylesheet" href="../CSS/modo_oscuro.css?v=<?php echo time(); ?>">
    <?php endif; ?>
    <style>
        :root {
            --color-primario: <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>;
            --color-secundario: <?php echo htmlspecialchars($apariencia['color_secundario'] ?? '#e74c3c'); ?>;
            --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>;
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--color-fondo); margin: 0; display: flex; height: 100vh; overflow: hidden; color: #333; }
        .topbar { background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.04); flex-shrink: 0; z-index: 10; }
        .user-info { font-size: 13px; font-weight: 500; color: #666; }
        .btn-logout { color: var(--color-secundario); text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid var(--color-secundario); padding: 5px 15px; border-radius: 4px; transition: all 0.2s; }
        .btn-logout:hover { background: var(--color-secundario); color: #fff; }
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; background-color: var(--color-fondo); }
        .wrapper-content { max-width: 1200px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); }
        .header-box { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--color-primario); padding-bottom: 15px; margin-bottom: 20px; }
        .header-box h2 { margin: 0; color: #333; font-size: 22px; }
        .btn { padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; text-decoration: none; font-size: 13px; border: none; }
        .btn-add { background: var(--color-primario); color: white; padding: 10px 20px; font-size: 14px; transition: 0.2s; }
        .btn-add:hover { opacity: 0.9; }
        .filter-container { display: flex; gap: 10px; background: #fff; padding: 15px; border-radius: 6px; border: 1px solid #eef0f2; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
        .filter-container select, .filter-container input[type="date"], .search-wrapper input[type="text"] { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; outline: none; }
        .filter-container select:focus, .filter-container input:focus, .search-wrapper input:focus { border-color: var(--color-primario); }
        .btn-search { background: #343a40; color: white; border: none; padding: 8px 15px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 13px; }
        .btn-clear { background: #e2e8f0; color: #333; text-decoration: none; padding: 8px 15px; border-radius: 4px; font-size: 13px; font-weight: bold; display: flex; align-items: center; }
        .search-wrapper { position: relative; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
        th { background: #f8f9fa; color: #444; padding: 14px; font-weight: 600; border-bottom: 2px solid #eaeaea; }
        td { padding: 14px; border-bottom: 1px solid #f1f1f1; vertical-align: middle; }
        tr:hover { background-color: #fafbfc; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-pendiente { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .badge-aprobado { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .badge-rechazado { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .badge-vencido { background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
        .action-group { display: flex; gap: 5px; }
        .btn-ver { background: #e2e8f0; color: #333; }
        .btn-ver:hover { background: #cbd5e0; }
        .btn-editar { background: #f8f9fa; color: var(--color-primario); border: 1px solid var(--color-primario); }
        .btn-editar:hover { background: var(--color-primario); color: #fff; }
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .page-link { padding: 8px 12px; background: #fff; border: 1px solid #ddd; text-decoration: none; color: #333; border-radius: 4px; font-size: 13px; }
        .page-link.active { background: var(--color-primario); color: #fff; border-color: var(--color-primario); }
        .page-link:hover:not(.active) { background: #f1f1f1; }
        
        body.tema-oscuro .filter-container { background: #27272a; border-color: #3f3f46; }
        body.tema-oscuro .filter-container select, body.tema-oscuro .filter-container input[type="date"], body.tema-oscuro .search-wrapper input[type="text"] { background: #3f3f46; color: #fff; border-color: #52525b; }
        body.tema-oscuro .btn-ver, body.tema-oscuro .btn-clear { background: #3f3f46; color: #fff; }
        body.tema-oscuro .btn-ver:hover, body.tema-oscuro .btn-clear:hover { background: #52525b; }
        body.tema-oscuro .page-link { background: #27272a; color: #fff; border-color: #3f3f46; }
        body.tema-oscuro .page-link:hover:not(.active) { background: #3f3f46; }
    </style>
</head>
<body class="<?php echo $temaActual === 'oscuro' ? 'tema-oscuro' : ''; ?>">
    <?php
    $basePath = '../';
    include __DIR__ . '/../HTML/sidebar.php';
    ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../HTML/topbar.php'; ?>
        <main class="content-area">
            <div class="wrapper-content">
                <div class="header-box">
                    <h2>Gestión de Presupuestos</h2>
                    <a href="crear_presupuesto.php" class="btn btn-add">Nuevo Presupuesto</a>
                </div>
                <form method="GET" action="listar_presupuestos.php" class="filter-container" id="filterForm" style="display: flex; flex-wrap: wrap; gap: 20px; justify-content: space-between; align-items: flex-end;">
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <strong style="color:#555; font-size:13px; width: 60px;">Fechas:</strong>
                            <input type="date" name="f_fecha_desde" title="Fecha Desde" style="width: 120px;" value="<?php echo htmlspecialchars($f_fecha_desde); ?>" onchange="this.form.submit()">
                            <span style="color:#555; font-size:13px;">hasta</span>
                            <input type="date" name="f_fecha_hasta" title="Fecha Hasta" style="width: 120px;" value="<?php echo htmlspecialchars($f_fecha_hasta); ?>" onchange="this.form.submit()">
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <strong style="color:#555; font-size:13px; width: 60px;">Filtros:</strong>
                            <select name="f_estado" onchange="this.form.submit()" style="width: 145px;">
                                <option value="Todos" <?php echo $f_estado === 'Todos' ? 'selected' : ''; ?>>Estado: Todos</option>
                                <option value="Pendiente" <?php echo $f_estado === 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="Aprobado" <?php echo $f_estado === 'Aprobado' ? 'selected' : ''; ?>>Aprobado</option>
                                <option value="Rechazado" <?php echo $f_estado === 'Rechazado' ? 'selected' : ''; ?>>Rechazado</option>
                                <option value="Vencido" <?php echo $f_estado === 'Vencido' ? 'selected' : ''; ?>>Vencido</option>
                            </select>
                            <select name="limite" onchange="this.form.submit()">
                                <option value="5" <?php echo $limit == 5 ? 'selected' : ''; ?>>5 / pág</option>
                                <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10 / pág</option>
                                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 / pág</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 2px;">
                        <div class="search-wrapper" style="width: 250px;">
                            <input type="text" name="f_busqueda" id="buscar_presu_input" placeholder="Buscar cliente, vehículo o Nº..." value="<?php echo htmlspecialchars($f_busqueda); ?>" style="width: 100%; box-sizing: border-box; padding: 8px 10px; font-size: 13px;" autocomplete="off">
                            <div id="autocomplete-box" class="autocomplete-results" style="position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #ccc; z-index:100; max-height:300px; overflow-y:auto; display:none; border-radius:0 0 6px 6px;"></div>
                        </div>
                        <button type="submit" class="btn-search">Buscar</button>
                        <a href="listar_presupuestos.php" class="btn-clear" style="margin-left: 0;">Limpiar Filtros</a>
                    </div>
                </form>

                <table>
                    <thead>
                        <tr>
                            <th>Nº</th>
                            <th>Vencimiento</th>
                            <th>Cliente</th>
                            <th>Patente</th>
                            <th>Total</th>
                            <th>Estado Presupuesto</th>
                            <th>Orden Asignada</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($presupuestos) === 0): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #777;">No hay presupuestos registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($presupuestos as $p):
                                if ($p['IDCliente']) {
                                    $clienteStr = trim($p['cli_ape'] . ', ' . $p['cli_nom']);
                                    if (!empty($p['numeroDocumentoCliente'])) {
                                        $clienteStr .= ' (DNI: ' . $p['numeroDocumentoCliente'] . ')';
                                    }
                                } else {
                                    $clienteStr = trim(($p['casual_apellido'] ?? '') . ' ' . ($p['casual_nombre'] ?? ''));
                                }

                                $vehiculoStr = $p['IDVehiculo'] ? $p['patente'] : ($p['casual_patente'] ?? '');
                                
                                $fechaVencimiento = date('d/m/Y', strtotime($p['fecha_creacion'] . " + {$p['validez_dias']} days"));
                                ?>
                                <tr>
                                    <td><strong>#<?php echo str_pad($p['IDPresupuesto'], 8, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td><?php echo $fechaVencimiento; ?></td>
                                    <td><?php echo htmlspecialchars($clienteStr); ?></td>
                                    <td><?php echo htmlspecialchars($vehiculoStr); ?></td>
                                    <td style="font-weight: bold; color: var(--color-primario);">$<?php echo number_format($p['total'], 2, ',', '.'); ?></td>
                                    <td>
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="form_cambiar_estado" value="1">
                                            <input type="hidden" name="idPresupuesto" value="<?php echo $p['IDPresupuesto']; ?>">
                                            <?php
                                                $colorSelect = ''; $bgSelect = '';
                                                if ($p['estado'] == 'Pendiente') { $colorSelect = '#856404'; $bgSelect = '#fff3cd'; }
                                                if ($p['estado'] == 'Aprobado') { $colorSelect = '#155724'; $bgSelect = '#d4edda'; }
                                                if ($p['estado'] == 'Rechazado') { $colorSelect = '#721c24'; $bgSelect = '#f8d7da'; }
                                                if ($p['estado'] == 'Vencido') { $colorSelect = '#d35400'; $bgSelect = '#fdebd0'; }
                                            ?>
                                            <select name="nuevo_estado" onchange="this.form.submit()" class="search-input" style="padding: 4px 12px; font-size: 13px; width: auto; font-weight: bold; border: 1px solid <?php echo $colorSelect; ?>; color: <?php echo $colorSelect; ?>; background-color: <?php echo $bgSelect; ?>; border-radius: 20px; outline: none; cursor: pointer; text-align: center; text-align-last: center;">
                                                <option value="Pendiente" style="background-color: #fff3cd; color: #856404;" <?php echo $p['estado'] == 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                                <option value="Aprobado" style="background-color: #d4edda; color: #155724;" <?php echo $p['estado'] == 'Aprobado' ? 'selected' : ''; ?>>Aprobado</option>
                                                <option value="Rechazado" style="background-color: #f8d7da; color: #721c24;" <?php echo $p['estado'] == 'Rechazado' ? 'selected' : ''; ?>>Rechazado</option>
                                                <option value="Vencido" style="background-color: #fdebd0; color: #d35400;" <?php echo $p['estado'] == 'Vencido' ? 'selected' : ''; ?>>Vencido</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <?php if (!empty($p['IDOrdenTrabajo']) && !empty($p['num_orden_real'])): ?>
                                            <span style="background: #e3f2fd; color: #004085; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                                                Nº <?php echo str_pad($p['num_orden_real'], 8, '0', STR_PAD_LEFT); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-group">
                                            <a href="ver_presupuesto.php?id=<?php echo $p['IDPresupuesto']; ?>" class="btn btn-ver">Ver</a>
                                            <a href="editar_presupuesto.php?id=<?php echo $p['IDPresupuesto']; ?>" class="btn btn-editar">Editar</a>
                                            <?php if ($p['estado'] === 'Aprobado' && empty($p['IDOrdenTrabajo'])): ?>
                                                <a href="presupuesto_a_orden.php?id=<?php echo $p['IDPresupuesto']; ?>" class="btn" style="background: #28a745; color: white; padding: 6px 10px; font-size: 12px; border: none; font-weight: bold; text-decoration: none; border-radius: 4px;">Convertir a Orden</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($totalPaginas > 1): ?>
                    <div class="pagination">
                        <?php
                        $queryParams = $_GET;
                        for ($i = 1; $i <= $totalPaginas; $i++):
                            $queryParams['pagina'] = $i;
                            $link = '?' . http_build_query($queryParams);
                            $active = ($i === $pagina) ? 'active' : '';
                            ?>
                            <a href="<?php echo $link; ?>" class="page-link <?php echo $active; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script>
        const searchInput = document.getElementById('buscar_presu_input');
        const acBox = document.getElementById('autocomplete-box');
        let acTimeout = null;

        if (searchInput && acBox) {
            searchInput.addEventListener('keyup', function () {
                clearTimeout(acTimeout);
                const val = this.value.trim();
                if (val.length >= 3) {
                    acTimeout = setTimeout(() => {
                        fetch('listar_presupuestos.php?ajax_search=1&q=' + encodeURIComponent(val))
                            .then(res => res.json())
                            .then(data => {
                                acBox.innerHTML = '';
                                if (data.length > 0) {
                                    data.forEach(item => {
                                        let div = document.createElement('div');
                                        div.className = 'autocomplete-item';
                                        div.style.padding = '10px';
                                        div.style.borderBottom = '1px solid #f1f1f1';
                                        div.style.cursor = 'pointer';
                                        div.innerHTML = `<span style="font-weight:bold; color:var(--color-primario); display:block;">#${String(item.IDPresupuesto).padStart(6, '0')} - ${item.patente}</span>
                                                     <span style="color:#666; font-size:12px; display:block;">${item.apellido}, ${item.nombre} | ${item.estado}</span>`;
                                        div.onmouseover = () => div.style.background = '#f8f9fa';
                                        div.onmouseout = () => div.style.background = 'transparent';
                                        div.onclick = () => {
                                            window.location.href = 'listar_presupuestos.php?f_busqueda=' + encodeURIComponent(String(item.IDPresupuesto).padStart(6, '0')) + '&f_estado=' + item.estado;
                                        };
                                        acBox.appendChild(div);
                                    });    
                                    acBox.style.display = 'block';
                                } else {
                                    acBox.innerHTML = '<div style="padding:10px;"><span style="color:#666; font-size:12px;">Sin resultados...</span></div>';
                                    acBox.style.display = 'block';
                                }
                            });
                    }, 300);
                } else {
                    acBox.style.display = 'none';
                }
            });

            document.addEventListener('click', function (e) {
                if (!searchInput.contains(e.target) && !acBox.contains(e.target)) {
                    acBox.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
