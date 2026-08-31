<?php
/**
 * Lizzosoft Vehículos - Historial del Cliente
 * Ubicación: lizzosoft_vehiculos/CRUD_Clientes/historial_cliente.php
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
$labelRodado = $config['labels']['vehiculo_plural'] ?? 'Vehículos';

// Validación de Permisos (Área 5 = Clientes)
$es_admin = in_array($idRol, [1, 2, 3]);
if (!in_array(5, $areas) && !$es_admin && $idRol !== 4) {
    die("<div style='padding:20px; font-family:Arial; color:#721c24; background:#f8d7da;'>Error: No tienes permisos para acceder a la gestión de Clientes.</div>");
}

$idCliente = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idCliente <= 0) { header("Location: listar_clientes.php"); exit; }

$conexion = obtenerConexion();

// Capturar filtros y paginación
$f_vehiculo = trim($_GET['f_vehiculo'] ?? '');
$f_busqueda = trim($_GET['f_busqueda'] ?? '');
$f_fecha_desde = trim($_GET['f_fecha_desde'] ?? '');
$f_fecha_hasta = trim($_GET['f_fecha_hasta'] ?? '');
$limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;
$pagina = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($pagina - 1) * $limite;

try {
    // 1. Obtener Datos del Cliente
    $stmtC = $conexion->prepare("
        SELECT c.*, td.tipoDocumento 
        FROM clientes c
        LEFT JOIN tiposdocumentos td ON c.IDTipoDocumento = td.IDTipoDocumento
        WHERE c.IDCliente = ? AND c.empresa_id = ?
    ");
    $stmtC->execute([$idCliente, $empresa_id]);
    $cliente = $stmtC->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) die("Cliente no encontrado.");

    // 2. Obtener Vehículos Asociados para la tabla y el filtro
    $stmtV = $conexion->prepare("SELECT * FROM vehiculos WHERE IDCliente = ? AND empresa_id = ? AND estado = 'Activo'");
    $stmtV->execute([$idCliente, $empresa_id]);
    $vehiculos = $stmtV->fetchAll(PDO::FETCH_ASSOC);

    // 3. Preparar Consulta Base para el Historial
    $whereH = "WHERE v.IDCliente = ? AND rs.empresa_id = ?";
    $paramsH = [$idCliente, $empresa_id];

    if ($f_vehiculo !== '') {
        $whereH .= " AND v.IDVehiculo = ?";
        $paramsH[] = (int)$f_vehiculo;
    }
    
    if ($f_busqueda !== '') {
        $whereH .= " AND (v.patente LIKE ? OR rs.numeroOrdenTrabajo LIKE ?)";
        $paramsH[] = "%" . $f_busqueda . "%";
        $paramsH[] = "%" . $f_busqueda . "%";
    }

    if ($f_fecha_desde !== '') {
        $whereH .= " AND DATE(rs.fechaRegistroServicio) >= ?";
        $paramsH[] = $f_fecha_desde;
    }

    if ($f_fecha_hasta !== '') {
        $whereH .= " AND DATE(rs.fechaRegistroServicio) <= ?";
        $paramsH[] = $f_fecha_hasta;
    }

    // 4. Calcular el total de páginas para la paginación
    $sqlCount = "
        SELECT COUNT(*) 
        FROM registrosservicios rs
        INNER JOIN vehiculos v ON rs.IDVehiculo = v.IDVehiculo
        $whereH
    ";
    $stmtCount = $conexion->prepare($sqlCount);
    $stmtCount->execute($paramsH);
    $totalRegistrosH = $stmtCount->fetchColumn();
    $totalPaginasH = ceil($totalRegistrosH / $limite);

    // 5. Obtener los registros de la página actual
    $sqlH = "
        SELECT 
            rs.IDRegistroServicio, rs.numeroOrdenTrabajo, rs.fechaRegistroServicio, rs.observacionGeneral, rs.IDEstado,
            es.nombreEstadoSolicitud,
            v.patente, v.marca, v.modelo
        FROM registrosservicios rs
        INNER JOIN vehiculos v ON rs.IDVehiculo = v.IDVehiculo
        LEFT JOIN estadossolicitud es ON rs.IDEstado = es.IDEstadoSolicitud
        $whereH
        ORDER BY rs.fechaRegistroServicio DESC, rs.numeroOrdenTrabajo DESC
        LIMIT " . (int)$limite . " OFFSET " . (int)$offset;
        
    $stmtH = $conexion->prepare($sqlH);
    $stmtH->execute($paramsH);
    $historial = $stmtH->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de base de datos al cargar el historial: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial Cliente - <?php echo htmlspecialchars($config['nombre_empresa']); ?></title>
    <style>
        :root { 
            --color-primario: <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>; 
            --color-secundario: <?php echo htmlspecialchars($apariencia['color_secundario'] ?? '#e74c3c'); ?>; 
            --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>; 
        }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; background-color: var(--color-fondo); color: #333; display: flex; height: 100vh; overflow: hidden; }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; width: 100%; }
        .user-info { font-size: 14px; }
        .btn-logout { color: #fff; text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid #fff; padding: 5px 15px; border-radius: 4px; transition: all 0.2s; }
        .btn-logout:hover { background: #fff; color: var(--color-primario); }
        
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        .wrapper-card { max-width: 1200px; margin: 0 auto; }
        .header-box { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 15px 20px; border-radius: 6px; border: 1px solid #eef0f2; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .header-box h2 { margin: 0; color: var(--color-primario); font-size: 24px; }
        
        .btn-cancel { background: #e2e8f0; color: #333; padding: 10px 20px; font-size: 13px; font-weight: bold; text-decoration: none; border-radius: 4px; transition: 0.2s; display: inline-block;}
        .btn-cancel:hover { background: #d0d7de; }

        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .info-card { background: #f8f9fa; border: 1px solid #eef0f2; border-radius: 6px; padding: 18px; border-left: 4px solid var(--color-primario); }
        .ic-title { font-size: 11px; text-transform: uppercase; color: #777; font-weight: bold; margin-bottom: 8px; }
        .ic-value { font-size: 16px; color: #333; font-weight: 700; }
        .ic-sub { font-size: 13px; color: #666; margin-top: 6px; display: block; }

        .filter-container { background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #eef0f2; margin-bottom: 20px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filter-container select, .filter-container input[type="text"], .filter-container input[type="date"] { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; outline: none; font-size: 13px; }
        .filter-container select { min-width: 200px; }
        .filter-container select:focus, .filter-container input[type="text"]:focus, .filter-container input[type="date"]:focus { border-color: var(--color-primario); }

        h3 { color: var(--color-primario); border-bottom: 2px solid #eee; padding-bottom: 8px; margin-top: 30px; margin-bottom: 15px; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; margin-bottom: 20px; }
        th { background: #f8f9fa; color: #444; padding: 12px 15px; font-weight: 600; border-bottom: 2px solid #eaeaea; }
        td { padding: 12px 15px; border-bottom: 1px solid #f1f1f1; vertical-align: middle; }
        tr:hover { background-color: #fafbfc; }

        .badge-estado { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; border: 1px solid transparent; display: inline-block;}
        .est-1 { background: #f8d7da; color: #721c24; border-color: #f5c6cb; } 
        .est-2 { background: #fff3cd; color: #856404; border-color: #ffeeba; } 
        .est-4 { background: #ffe8cc; color: #d97706; border-color: #ffd8a8; } 
        .est-5 { background: #d4edda; color: #155724; border-color: #c3e6cb; } 

        .btn-accion { display: inline-block; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: bold; border: 1px solid var(--color-primario); color: var(--color-primario); transition: 0.2s; }
        .btn-accion:hover { background: var(--color-primario); color: #fff; }
        .btn-buscar { background-color: var(--color-primario); color: white; border: none; padding: 8px 20px; border-radius: 4px; font-size: 13px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-buscar:hover { opacity: 0.9; }

        /* Estilos Paginación */
        .pagination { display: flex; gap: 6px; margin-top: 20px; justify-content: center; }
        .page-link { padding: 8px 14px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: var(--color-primario); font-size: 13px; font-weight: bold; transition: 0.2s; }
        .page-link:hover { background: #f1f1f1; }
        .page-link.active { background: var(--color-primario); color: white; border-color: var(--color-primario); pointer-events: none; }
        
        #tableContainer { transition: opacity 0.3s ease; }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        body { margin: 0; background-color: var(--color-fondo); display: flex; height: 100vh; overflow: hidden; }
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
            <div class="wrapper-card">
                <div class="header-box">
                    <h2>Historial de Servicios del Cliente</h2>
                    <div>
                        <a href="listar_clientes.php" class="btn-cancel">Volver al Listado</a>
                    </div>
                </div>

                <div class="client-info-card" style="background: #fff; padding: 20px; border-radius: 6px; border: 1px solid #eef0f2; margin-bottom: 20px;">
                    <div style="font-size: 18px; color: var(--color-primario); font-weight: bold; margin-bottom: 5px;">
                        <?php echo htmlspecialchars($cliente['apellido'] . ', ' . $cliente['nombre']); ?>
                    </div>
                    <span class="ic-sub"><?php echo htmlspecialchars($cliente['tipoDocumento']); ?>: <strong><?php echo htmlspecialchars($cliente['numeroDocumentoCliente']); ?></strong></span>
                </div>

                <h3><?php echo htmlspecialchars($labelRodado); ?> Registrados</h3>
                <?php if (empty($vehiculos)): ?>
                    <p style="color:#777; font-size:14px; margin-bottom: 30px;">Este cliente no tiene vehículos activos asociados.</p>
                <?php else: ?>
                    <div style="background: #fff; border-radius: 6px; border: 1px solid #eef0f2; overflow: hidden; margin-bottom: 20px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Patente</th>
                                    <th>Marca y Modelo</th>
                                    <th>Chasis</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vehiculos as $v): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($v['patente']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($v['marca'] . ' ' . $v['modelo']); ?></td>
                                    <td><span style="font-family: monospace; color:#555;"><?php echo htmlspecialchars($v['numeroChasis'] ?: '-'); ?></span></td>
                                    <td><a href="../CRUD_Vehiculos/ver.php?id=<?php echo $v['IDVehiculo']; ?>" class="btn-accion">Ver Ficha</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 8px; margin-top: 30px; margin-bottom: 15px;">
                    <h3 style="border: none; margin: 0; padding: 0;">Historial de Órdenes de Trabajo</h3>
                </div>

                <form method="GET" class="filter-container" id="filterForm">
                    <input type="hidden" name="id" value="<?php echo $idCliente; ?>">
                    
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <strong style="font-size: 13px; color: #555;">Filtrar por Vehículo:</strong>
                        <select name="f_vehiculo" id="f_vehiculo_select">
                            <option value="">Todos los vehículos</option>
                            <?php foreach($vehiculos as $v): ?>
                                <option value="<?php echo $v['IDVehiculo']; ?>" <?php echo ($f_vehiculo == $v['IDVehiculo']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($v['patente'] . ' - ' . $v['marca'] . ' ' . $v['modelo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: flex; align-items: center; gap: 10px;">
                        <strong style="font-size: 13px; color: #555;">Fechas:</strong>
                        <input type="date" name="f_fecha_desde" id="f_fecha_desde" value="<?php echo htmlspecialchars($f_fecha_desde); ?>" max="<?php echo date('Y-m-d'); ?>" title="Desde">
                        <span style="font-size: 13px; color: #555;">-</span>
                        <input type="date" name="f_fecha_hasta" id="f_fecha_hasta" value="<?php echo htmlspecialchars($f_fecha_hasta); ?>" max="<?php echo date('Y-m-d'); ?>" title="Hasta">
                    </div>

                    <div style="display: flex; align-items: center; gap: 10px;">
                        <strong style="font-size: 13px; color: #555;">Mostrar:</strong>
                        <select name="limite" id="f_limite_select">
                            <option value="5" <?php echo ($limite == 5) ? 'selected' : ''; ?>>5 / pág</option>
                            <option value="10" <?php echo ($limite == 10) ? 'selected' : ''; ?>>10 / pág</option>
                            <option value="50" <?php echo ($limite == 50) ? 'selected' : ''; ?>>50 / pág</option>
                        </select>
                    </div>

                    <div style="display: flex; align-items: center; gap: 10px;">
                        <strong style="font-size: 13px; color: #555;">Buscar (Patente / Orden):</strong>
                        <input type="text" name="f_busqueda" id="f_busqueda_input" value="<?php echo htmlspecialchars($f_busqueda); ?>">
                    </div>

                    <button type="submit" class="btn-buscar">Buscar</button>
                </form>

                <div id="tableContainer">
                    <?php if (empty($historial)): ?>
                        <p style="color:#777; font-size:14px;">No hay registros de servicio asociados a los filtros seleccionados.</p>
                    <?php else: ?>
                        <div style="background: #fff; border-radius: 6px; border: 1px solid #eef0f2; overflow: hidden; margin-top: 20px;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Orden N°</th>
                                        <th>Fecha</th>
                                        <th>Vehículo</th>
                                        <th>Estado</th>
                                        <th>Observaciones</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($historial as $h): ?>
                                        <?php 
                                            $idEst = (int)$h['IDEstado'];
                                            $claseEst = 'est-1';
                                            if ($idEst === 2) $claseEst = 'est-2';
                                            if ($idEst === 4) $claseEst = 'est-4';
                                            if ($idEst === 5) $claseEst = 'est-5';
                                            
                                            $esReclamo = strpos($h['observacionGeneral'], '[RECLAMO') !== false;
                                        ?>
                                    <tr>
                                        <td><strong>#<?php echo str_pad($h['numeroOrdenTrabajo'], 8, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td><?php echo date('d/m/Y', strtotime($h['fechaRegistroServicio'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars($h['patente']); ?></strong></td>
                                        <td><span class="badge-estado <?php echo $claseEst; ?>"><?php echo htmlspecialchars($h['nombreEstadoSolicitud']); ?></span></td>
                                        <td style="max-width:250px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                            <?php if($esReclamo): ?><span style="color:#e74c3c; font-weight:bold; font-size:11px;">[RECLAMO]</span> <?php endif; ?>
                                            <?php echo htmlspecialchars($h['observacionGeneral']); ?>
                                        </td>
                                        <td><a href="../CRUD_Ordenes/ver_orden.php?id=<?php echo $h['IDRegistroServicio']; ?>&from=historial_cliente&id_cliente=<?php echo $idCliente; ?>" class="btn-accion">Ver OT</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPaginasH > 1): ?>
                            <div class="pagination">
                                <?php for ($i = 1; $i <= $totalPaginasH; $i++): ?>
                                    <a href="?id=<?php echo $idCliente; ?>&f_vehiculo=<?php echo urlencode($f_vehiculo); ?>&f_busqueda=<?php echo urlencode($f_busqueda); ?>&f_fecha_desde=<?php echo urlencode($f_fecha_desde); ?>&f_fecha_hasta=<?php echo urlencode($f_fecha_hasta); ?>&limite=<?php echo $limite; ?>&p=<?php echo $i; ?>" class="page-link <?php echo ($i === $pagina) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>

                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('filterForm');
            const tableContainer = document.getElementById('tableContainer');

            function fetchResults(queryString) {
                // Añadimos un pequeño efecto de opacidad para dar feedback visual fluido
                tableContainer.style.opacity = '0.5';

                fetch(window.location.pathname + '?' + queryString)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newTableContainer = doc.getElementById('tableContainer');
                        
                        if (newTableContainer) {
                            tableContainer.innerHTML = newTableContainer.innerHTML;
                            // Actualiza la URL sin recargar para conservar estado de filtros en el navegador
                            window.history.pushState({}, '', '?' + queryString);
                        }
                    })
                    .catch(err => console.error('Error al actualizar:', err))
                    .finally(() => {
                        tableContainer.style.opacity = '1';
                    });
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                const searchParams = new URLSearchParams(formData);
                fetchResults(searchParams.toString());
            });

            // Hacer que los selects funcionen de inmediato
            const selectVehiculo = document.getElementById('f_vehiculo_select');
            const selectLimite = document.getElementById('f_limite_select');
            
            function onSelectChange() {
                const formData = new FormData(form);
                const searchParams = new URLSearchParams(formData);
                fetchResults(searchParams.toString());
            }

            if (selectVehiculo) selectVehiculo.addEventListener('change', onSelectChange);
            if (selectLimite) selectLimite.addEventListener('change', onSelectChange);

            // Filtros por fecha independientes
            const inputFechaDesde = document.getElementById('f_fecha_desde');
            const inputFechaHasta = document.getElementById('f_fecha_hasta');
            
            function onFechaChange() {
                const formData = new FormData(form);
                const searchParams = new URLSearchParams(formData);
                fetchResults(searchParams.toString());
            }

            if (inputFechaDesde) inputFechaDesde.addEventListener('change', onFechaChange);
            if (inputFechaHasta) inputFechaHasta.addEventListener('change', onFechaChange);

            // Restaurar búsqueda si se borra el texto de búsqueda
            const inputBusqueda = document.getElementById('f_busqueda_input');
            if (inputBusqueda) {
                inputBusqueda.addEventListener('input', function() {
                    if (this.value.trim() === '') {
                        const formData = new FormData(form);
                        const searchParams = new URLSearchParams(formData);
                        fetchResults(searchParams.toString());
                    }
                });
            }

            // Interceptar paginación
            document.addEventListener('click', function(e) {
                if (e.target.matches('.page-link') && !e.target.classList.contains('active')) {
                    e.preventDefault();
                    const url = new URL(e.target.href);
                    fetchResults(url.searchParams.toString());
                }
            });
        });
    </script>
</body>
</html>