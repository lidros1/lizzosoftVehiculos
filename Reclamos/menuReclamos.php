<?php
/**
 * Lizzosoft Vehículos - Gestión de Reclamos (Listado)
 * Ubicación: lizzosoft_vehiculos/Reclamos/menuReclamos.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$config = $_SESSION['cliente_config'];
$apariencia = $config['apariencia'];
$empresa_id = (int) $_SESSION['empresa_id'];
$sucursal_id = (int) $_SESSION['sucursal_id'];
$idRol = (int) $_SESSION['IDRol'];
$areas = $_SESSION['areas_permitidas'] ?? [];
$es_admin = in_array($idRol, [1, 2, 3]);
$temaActual = $_SESSION['tema_preferido'] ?? 'claro'; // Variable para Modo Oscuro

if (!in_array(7, $areas) && !$es_admin) {
    die("<div style='padding:20px; font-family:Arial; color:#721c24; background:#f8d7da;'>Error: No tienes permisos para acceder a la gestión de Reclamos.</div>");
}

$labelRodado = $config['labels']['vehiculo_plural'] ?? 'Vehículos';
$conexion = obtenerConexion();

$defaultHasta = date('Y-m-d');
$defaultDesde = date('Y-m-d', strtotime('-2 months'));

$busqueda = trim($_GET['buscar_reclamo'] ?? '');
$f_estado = trim($_GET['f_estado'] ?? '');
$f_fecha_desde = trim($_GET['f_fecha_desde'] ?? $defaultDesde);
$f_fecha_hasta = trim($_GET['f_fecha_hasta'] ?? $defaultHasta);

if (!strtotime($f_fecha_desde))
    $f_fecha_desde = $defaultDesde;
if (!strtotime($f_fecha_hasta))
    $f_fecha_hasta = $defaultHasta;

$whereR = "WHERE r.empresa_id = :empresa_id AND r.sucursal_id = :sucursal_id AND DATE(r.fechaReclamo) BETWEEN :f_fecha_desde AND :f_fecha_hasta";
$paramsR = [
    ':empresa_id' => $empresa_id,
    ':sucursal_id' => $sucursal_id,
    ':f_fecha_desde' => $f_fecha_desde,
    ':f_fecha_hasta' => $f_fecha_hasta
];

if ($f_estado !== '') {
    $whereR .= " AND r.estadoReclamo = :f_estado";
    $paramsR[':f_estado'] = $f_estado;
}

if ($busqueda !== '') {
    $terminos = array_filter(explode(' ', $busqueda));
    $indice = 0;
    foreach ($terminos as $termino) {
        $cadenaVirtual = "CONCAT_WS(' ', LPAD(CAST(rs.numeroOrdenTrabajo AS CHAR), 6, '0'), CAST(rs.numeroOrdenTrabajo AS CHAR), v.patente, v.marca, v.modelo, CAST(c.numeroDocumentoCliente AS CHAR), c.nombre, c.apellido)";
        $whereR .= " AND $cadenaVirtual LIKE :q_termino_$indice";
        $paramsR[":q_termino_$indice"] = '%' . $termino . '%';
        $indice++;
    }
}

$reclamos = [];
try {
    $sqlR = "
        SELECT 
            r.IDReclamo, r.fechaReclamo, 
            COALESCE(es.nombreEstadoSolicitud, r.estadoReclamo) AS estadoReclamo,
            r.observacionReclamo,
            rs.numeroOrdenTrabajo AS ot_original,
            rs_nuevo.numeroOrdenTrabajo AS ot_nueva,
            v.patente, 
            c.nombre AS nombreCliente, c.apellido AS apellidoCliente, c.numeroDocumentoCliente
        FROM reclamos r
        INNER JOIN registrosservicios rs ON r.IDRegistroServicioOriginal = rs.IDRegistroServicio
        INNER JOIN vehiculos v ON rs.IDVehiculo = v.IDVehiculo
        INNER JOIN clientes c ON v.IDCliente = c.IDCliente
        LEFT JOIN registrosservicios rs_nuevo ON rs_nuevo.IDVehiculo = v.IDVehiculo 
             AND rs_nuevo.observacionGeneral LIKE CONCAT('%[RECLAMO - Ref OT #', LPAD(CAST(rs.numeroOrdenTrabajo AS CHAR), 6, '0'), ']%')
        LEFT JOIN estadossolicitud es ON rs_nuevo.IDEstado = es.IDEstadoSolicitud
        $whereR
        ORDER BY r.fechaReclamo DESC
        LIMIT 100
    ";
    $stmtR = $conexion->prepare($sqlR);
    $stmtR->execute($paramsR);
    $reclamos = $stmtR->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorR = "Error SQL: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Reclamos - <?php echo htmlspecialchars($config['nombre_empresa']); ?></title>
    <style>
        :root {
            --color-primario:
                <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>
            ;
            --color-secundario:
                <?php echo htmlspecialchars($apariencia['color_secundario'] ?? '#e74c3c'); ?>
            ;
            --color-fondo:
                <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>
            ;
            --sidebar-width: 270px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            margin: 0;
            background-color: var(--color-fondo);
            color: #333;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* CSS del sidebar movido a HTML/sidebar.php */
        .main-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .topbar {
            background: #fff;
            height: 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 25px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.04);
            flex-shrink: 0;
            z-index: 10;
        }

        .user-info {
            font-size: 13px;
            font-weight: 500;
            color: #666;
        }

        .btn-logout {
            color: var(--color-secundario);
            text-decoration: none;
            font-weight: bold;
            font-size: 13px;
            border: 1px solid var(--color-secundario);
            padding: 5px 15px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            background: var(--color-secundario);
            color: #fff;
        }

        .content-area {
            padding: 30px;
            overflow-y: auto;
            flex-grow: 1;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .panel-title {
            margin: 0;
            font-size: 22px;
            color: var(--color-primario);
            font-weight: 600;
        }

        .filter-container {
            display: flex;
            gap: 10px;
            background: #fff;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #eef0f2;
            margin-bottom: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-container select,
        .filter-container input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 13px;
            outline: none;
        }

        .filter-container select:focus,
        .filter-container input:focus {
            border-color: var(--color-primario);
        }

        .filter-container input[type="text"] {
            width: 300px;
        }

        .btn-search {
            background: var(--color-primario);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            font-size: 13px;
        }

        .btn-clear {
            background: #e2e8f0;
            color: #333;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
            display: flex;
            align-items: center;
        }

        .btn-nuevo {
            background: #e74c3c;
            color: #fff;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 13px;
            transition: opacity 0.2s;
        }

        .btn-nuevo:hover {
            opacity: 0.9;
        }

        .tabs-container {
            display: flex;
            border-bottom: 2px solid #eef0f2;
            margin-bottom: 20px;
            gap: 5px;
            overflow-x: auto;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: 0.2s;
            white-space: nowrap;
            outline: none;
        }

        .tab-btn:hover {
            color: var(--color-primario);
            background: #f8f9fa;
        }

        .tab-btn.active {
            color: var(--color-primario);
            border-bottom-color: var(--color-primario);
        }

        .search-wrapper {
            position: relative;
            display: inline-block;
        }

        .table-card {
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
            overflow: hidden;
            border: 1px solid #eef0f2;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background: #f8f9fa;
            color: #444;
            padding: 14px 18px;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #eaeaea;
        }

        td {
            padding: 14px 18px;
            border-bottom: 1px solid #f1f1f1;
            font-size: 13px;
            color: #555;
            vertical-align: middle;
        }

        tr:hover {
            background-color: #fafbfc;
        }

        .badge-estado {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid transparent;
        }

        .est-1 {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        /* Pendiente */
        .est-2 {
            background: #fff3cd;
            color: #856404;
            border-color: #ffeeba;
        }

        /* En Proceso */
        .est-4 {
            background: #ffe8cc;
            color: #d97706;
            border-color: #ffd8a8;
        }

        /* Finalizado NE */
        .est-5 {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        /* Finalizado ENT */
        .action-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-accion {
            display: inline-block;
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            transition: all 0.15s;
            border: none;
            cursor: pointer;
        }

        .btn-ver {
            background: #f8f9fa;
            color: var(--color-primario);
            border: 1px solid var(--color-primario);
        }

        .btn-ver:hover {
            background: var(--color-primario);
            color: #fff;
        }

        .btn-editar {
            background: #e2e8f0;
            color: #333;
        }

        .btn-editar:hover {
            background: #cbd5e0;
        }
    </style>
    <!-- CARGA CSS MODO OSCURO -->
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
                <h1 class="panel-title">Gestión de Reclamos</h1>
                <a href="crear_reclamo.php" class="btn-nuevo">Nuevo Reclamo</a>
            </div>

            <form method="GET" action="menuReclamos.php" class="filter-container" id="filterForm"
                style="display: flex; flex-wrap: wrap; gap: 20px; justify-content: space-between; align-items: flex-end;">
                <input type="hidden" name="f_estado" id="input_f_estado"
                    value="<?php echo htmlspecialchars($f_estado); ?>">

                <!-- Izquierda: Filtros en columna -->
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <!-- Fila 1: Fechas -->
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <strong style="color:#555; font-size:13px; width: 60px;">Fechas:</strong>
                        <input type="date" name="f_fecha_desde" title="Fecha Desde" style="width: 120px;"
                            value="<?php echo htmlspecialchars($f_fecha_desde); ?>" onchange="this.form.submit()">
                        <span style="color:#555; font-size:13px;">hasta</span>
                        <input type="date" name="f_fecha_hasta" title="Fecha Hasta" style="width: 120px;"
                            value="<?php echo htmlspecialchars($f_fecha_hasta); ?>" onchange="this.form.submit()">
                    </div>
                </div>

                <!-- Derecha: Búsqueda, Limpiar -->
                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 2px;">
                    <div class="search-wrapper" style="width: 250px;">
                        <input type="text" name="buscar_reclamo" id="buscar_reclamo_input"
                            placeholder="Buscar Reclamo..." value="<?php echo htmlspecialchars($busqueda); ?>"
                            style="width: 100%; box-sizing: border-box; padding: 8px 10px; font-size: 13px;"
                            autocomplete="off">
                    </div>

                    <button type="submit" class="btn-search">Buscar</button>
                    <a href="menuReclamos.php" class="btn-clear" style="margin-left: 0;">Limpiar Filtros</a>
                </div>
            </form>

            <div class="tabs-container">
                <button class="tab-btn <?php echo ($f_estado === 'Pendiente') ? 'active' : ''; ?>"
                    onclick="cambiarSolapa('Pendiente')">Pendientes</button>
                <button class="tab-btn <?php echo ($f_estado === 'En Proceso') ? 'active' : ''; ?>"
                    onclick="cambiarSolapa('En Proceso')">En Proceso</button>
                <button class="tab-btn <?php echo ($f_estado === 'Finalizado-NE') ? 'active' : ''; ?>"
                    onclick="cambiarSolapa('Finalizado-NE')">Finalizado NE</button>
                <button class="tab-btn <?php echo ($f_estado === 'Finalizado-ENT') ? 'active' : ''; ?>"
                    onclick="cambiarSolapa('Finalizado-ENT')">Entregados</button>
                <button class="tab-btn <?php echo ($f_estado === '') ? 'active' : ''; ?>"
                    onclick="cambiarSolapa('')">Todos los Reclamos</button>
            </div>

            <?php if (isset($errorR)): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; font-size: 14px;">
                    <?php echo $errorR; ?></div>
            <?php else: ?>
                <div class="table-card">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha Reclamo</th>
                                <th>OT Original N°</th>
                                <th>Datos del Cliente</th>
                                <th>Patente</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($reclamos) === 0): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 35px; color: #888;">No se encontraron
                                        reclamos registrados.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reclamos as $rec): ?>
                                    <?php
                                    $claseEst = 'est-1';
                                    if (stripos($rec['estadoReclamo'], 'Proceso') !== false)
                                        $claseEst = 'est-2';
                                    if (stripos($rec['estadoReclamo'], 'Finalizado NE') !== false || stripos($rec['estadoReclamo'], 'Finalizado-NE') !== false)
                                        $claseEst = 'est-4';
                                    if (stripos($rec['estadoReclamo'], 'Finalizado ENT') !== false || stripos($rec['estadoReclamo'], 'Finalizado-ENT') !== false || stripos($rec['estadoReclamo'], 'Entregado') !== false)
                                        $claseEst = 'est-5';
                                    ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($rec['fechaReclamo'])); ?></td>
                                        <td>
                                            <span style="font-size: 11px; color: #888;">Orig: #<?php echo str_pad($rec['ot_original'], 8, '0', STR_PAD_LEFT); ?></span><br>
                                            <strong style="font-size: 14px; color: #e74c3c;">NUEVA: #<?php echo $rec['ot_nueva'] ? str_pad($rec['ot_nueva'], 8, '0', STR_PAD_LEFT) : 'N/A'; ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($rec['apellidoCliente'] . ', ' . $rec['nombreCliente']); ?></strong><br>
                                            <span style="font-size: 12px; color: #777;">DNI:
                                                <?php echo htmlspecialchars($rec['numeroDocumentoCliente']); ?></span>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($rec['patente']); ?></strong></td>
                                        <td><span
                                                class="badge-estado <?php echo $claseEst; ?>"><?php echo htmlspecialchars($rec['estadoReclamo'] ?: 'Pendiente'); ?></span>
                                        </td>
                                        <td>
                                            <div class="action-group">
                                                <a href="ver_reclamo.php?id=<?php echo $rec['IDReclamo']; ?>"
                                                    class="btn-accion btn-ver">Ver</a>
                                                <!-- NOTA: Este enlace apunta a editar_reclamo.php -->
                                                <a href="editar_reclamo.php?id=<?php echo $rec['IDReclamo']; ?>"
                                                    class="btn-accion btn-editar">Editar</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function cambiarSolapa(estado) {
            document.getElementById('input_f_estado').value = estado;
            document.getElementById('filterForm').submit();
        }
        /* JS del sidebar movido a HTML/sidebar.php */
    </script>
</body>

</html>