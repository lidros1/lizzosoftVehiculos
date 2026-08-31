<?php
/**
 * Lizzosoft Vehículos - Padrón de Vehículos
 * Ubicación: lizzosoft_vehiculos/CRUD_Vehiculos/listar_vehiculos.php
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$config     = $_SESSION['cliente_config'];
$apariencia = $config['apariencia'];
$empresa_id = (int)$_SESSION['empresa_id'];
$sucursal_id= (int)$_SESSION['sucursal_id'];

// Validación de privilegios
$areas_permitidas = $_SESSION['areas_permitidas'] ?? [];
$es_admin         = (isset($_SESSION['IDRol']) && $_SESSION['IDRol'] == 1);

if (!in_array(8, $areas_permitidas) && !$es_admin) {
    die("<div style='padding:20px; font-family:Arial; color:#721c24; background:#f8d7da;'>Error: No tienes permisos para acceder a la gestión de Vehículos.</div>");
}

$funciones_permitidas = $_SESSION['funciones_permitidas'][8] ?? [];
$puede_crear = $es_admin || in_array(1, $funciones_permitidas);
$puede_editar = $es_admin || in_array(2, $funciones_permitidas);

$conexion = obtenerConexion();

// Configuración de Paginación y Búsqueda
$limite = isset($_GET['limite']) ? max(1, (int)$_GET['limite']) : 15;
$pagina = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($pagina - 1) * $limite;
$busqueda = trim($_GET['q'] ?? '');

$whereSql = "WHERE v.empresa_id = :empresa_id AND v.sucursal_id = :sucursal_id";
$params = [':empresa_id' => $empresa_id, ':sucursal_id' => $sucursal_id];

if ($busqueda !== '') {
    if (is_numeric($busqueda)) {
        $whereSql .= " AND c.numeroDocumentoCliente LIKE :q_num";
        $params[':q_num'] = $busqueda . '%';
    } else {
        $whereSql .= " AND (v.patente LIKE :q_str1 OR v.marca LIKE :q_str2 OR v.modelo LIKE :q_str3 OR c.nombre LIKE :q_str4 OR c.apellido LIKE :q_str5)";
        $paramStr = '%' . $busqueda . '%';
        $params[':q_str1'] = $paramStr;
        $params[':q_str2'] = $paramStr;
        $params[':q_str3'] = $paramStr;
        $params[':q_str4'] = $paramStr;
        $params[':q_str5'] = $paramStr;
    }
}

try {
    $stmtCount = $conexion->prepare("SELECT COUNT(*) FROM vehiculos v INNER JOIN clientes c ON v.IDCliente = c.IDCliente $whereSql");
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetchColumn();
    $totalPaginas = ceil($totalRegistros / $limite);

    $query = "
        SELECT 
            v.IDVehiculo, v.patente, v.anioFabricacion, v.colorVehiculo, v.estado, v.marca, v.modelo,
            c.nombre AS nombreCliente, c.apellido AS apellidoCliente, c.numeroDocumentoCliente
        FROM vehiculos v
        INNER JOIN clientes c ON v.IDCliente = c.IDCliente
        $whereSql
        ORDER BY v.patente ASC
        LIMIT :limite OFFSET :offset
    ";

    $stmt = $conexion->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al compilar el listado de vehículos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Padrón de Vehículos - <?php echo htmlspecialchars($config['nombre_empresa']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root { --color-primario: <?php echo htmlspecialchars($apariencia['color_primario']); ?>; --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo']); ?>; }
        .wrapper { margin: 0 auto; max-width: 1200px; }
        .header-box { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 15px 20px; border-radius: 6px; border: 1px solid #eef0f2; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        h2 { margin: 0; color: var(--color-primario); font-size: 22px; }
        
        .search-form { display: flex; gap: 10px; }
        .search-input { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; width: 250px; outline: none; }
        .search-input:focus { border-color: var(--color-primario); }
        .btn-search { background: var(--color-primario); color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-clear { background: #e2e8f0; color: #333; text-decoration: none; padding: 8px 15px; border-radius: 4px; font-size: 13px; font-weight: bold; display: flex; align-items: center; }

        .btn-add { background: var(--color-primario); color: white; text-decoration: none; padding: 10px 20px; border-radius: 4px; font-weight: bold; font-size: 14px; transition: opacity 0.2s; }
        .btn-add:hover { opacity: 0.85; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; text-align: left; }
        th { background: var(--color-fondo); color: #2c3e50; padding: 14px; font-weight: 600; border-bottom: 2px solid #eaeaea; }
        td { padding: 14px; border-bottom: 1px solid #f1f1f1; vertical-align: middle; }
        tr:hover { background-color: #fafbfc; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-inactive { background: #f8d7da; color: #721c24; }
        .btn-editar { background: #e2e8f0; color: #333; text-decoration: none; padding: 6px 12px; border-radius: 4px; font-weight: bold; font-size: 12px; transition: background 0.2s; }
        .btn-editar:hover { background: var(--color-primario); color: white; }
        .btn-return { display: inline-block; margin-top: 25px; color: #7f8c8d; text-decoration: none; font-weight: 600; font-size: 14px; }
        
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .page-link { padding: 8px 12px; border: 1px solid #ddd; background: white; color: var(--color-primario); text-decoration: none; border-radius: 4px; font-weight: bold; }
        .page-link:hover { background: var(--color-fondo); }
        .page-link.active { background: var(--color-primario); color: white; border-color: var(--color-primario); pointer-events: none; }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        body { margin: 0; background-color: var(--color-fondo); display: flex; height: 100vh; overflow: hidden; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
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
            <div class="wrapper" style="max-width: 1200px; margin: 0 auto;">
                <div class="header-box">
                    <h2>Padrón de Vehículos</h2>
                    
                    <form class="search-form" method="GET">
                        <input type="text" name="q" class="search-input" placeholder="Patente, Marca, Modelo, DNI..." value="<?php echo htmlspecialchars($busqueda); ?>">
                        <select name="limite" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; outline: none; margin-left: 10px;" onchange="this.form.submit()">
                            <option value="5" <?php echo $limite == 5 ? 'selected' : ''; ?>>5 / pág</option>
                            <option value="10" <?php echo $limite == 10 ? 'selected' : ''; ?>>10 / pág</option>
                            <option value="50" <?php echo $limite == 50 ? 'selected' : ''; ?>>50 / pág</option>
                        </select>
                        <button type="submit" class="btn-search">Buscar</button>
                        <?php if($busqueda !== ''): ?><a href="listar_vehiculos.php" class="btn-clear">Limpiar</a><?php endif; ?>
                    </form>

                    <div style="display: flex; gap: 10px;">
                        <?php if ($puede_crear): ?>
                        <a href="crear_vehiculo.php" class="btn-add">Registrar Vehículo</a>
                        <?php endif; ?>
                    </div>
                </div>

        <div class="table-card" style="background: #fff; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); border: 1px solid #eef0f2; overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Patente</th>
                        <th>Marca y Modelo</th>
                        <th>Año</th>
                        <th>Color</th>
                        <th>Propietario (Cliente)</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($vehiculos) === 0): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 40px; color: #777;">No se encontraron vehículos registrados.</td></tr>
                    <?php else: ?>
                        <?php foreach($vehiculos as $v): ?>
                            <?php $claseEstado = ($v['estado'] === 'Activo') ? 'badge-active' : 'badge-inactive'; ?>
                            <tr>
                                <td><strong style="color: var(--color-primario); font-size: 15px; letter-spacing: 1px;"><?php echo htmlspecialchars($v['patente']); ?></strong></td>
                                <td><?php echo htmlspecialchars(($v['marca'] ?? '') . ' ' . ($v['modelo'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(($v['anioFabricacion'] ?? 0) > 0 ? $v['anioFabricacion'] : '-'); ?></td>
                                <td><?php echo htmlspecialchars($v['colorVehiculo'] ?? ''); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars(($v['apellidoCliente'] ?? '') . ', ' . ($v['nombreCliente'] ?? '')); ?></strong><br>
                                    <span style="color: #777; font-size: 12px;">Doc: <?php echo htmlspecialchars($v['numeroDocumentoCliente'] ?? ''); ?></span>
                                </td>
                                <td><span class="badge <?php echo $claseEstado; ?>"><?php echo htmlspecialchars($v['estado'] ?? ''); ?></span></td>
                                <td>
                                    <?php if ($puede_editar): ?>
                                    <a href="editar_vehiculo.php?id=<?php echo $v['IDVehiculo']; ?>" class="btn-editar">Editar</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                    <a href="?p=<?php echo $i; ?>&q=<?php echo urlencode($busqueda); ?>&limite=<?php echo $limite; ?>" class="page-link <?php echo ($i === $pagina) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
            </div>
        </main>
    </div>

</body>
</html>