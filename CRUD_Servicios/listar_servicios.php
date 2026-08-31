<?php
/**
 * Lizzosoft Vehículos - Directorio Simplificado de Servicios
 * Ubicación: lizzosoft_vehiculos/CRUD_Servicios/listar_servicios.php
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$config     = $_SESSION['cliente_config'];
$apariencia = $config['apariencia'];
$empresa_id = (int)$_SESSION['empresa_id'];

// Validación de privilegios (Área 6 = Servicios o Rol 1 = Admin)
$areas_permitidas = $_SESSION['areas_permitidas'] ?? [];
$es_admin         = (isset($_SESSION['IDRol']) && $_SESSION['IDRol'] == 1);

if (!in_array(6, $areas_permitidas) && !$es_admin) {
    die("<div style='padding:20px; font-family:Arial; color:#721c24; background:#f8d7da;'>Error: No tienes permisos para acceder a la gestión de Servicios.</div>");
}

$funciones_permitidas = $_SESSION['funciones_permitidas'][6] ?? [];
$puede_crear = $es_admin || in_array(1, $funciones_permitidas);
$puede_editar = $es_admin || in_array(2, $funciones_permitidas);

$conexion = obtenerConexion();

// Configuración de Paginación y Búsqueda
$limite = isset($_GET['limite']) ? max(1, (int)$_GET['limite']) : 15;
$pagina = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($pagina - 1) * $limite;
$busqueda = trim($_GET['q'] ?? '');

$whereSql = "WHERE s.empresa_id = :empresa_id";
$params = [':empresa_id' => $empresa_id];

// ALGORITMO DE BÚSQUEDA OPTIMIZADA POR TIPADO INDIZADO
if ($busqueda !== '') {
    if (is_numeric($busqueda)) {
        // Optimización: Si es numérico se restringe a buscar coincidencias por ID de registro usando el índice primario
        $whereSql .= " AND s.IDServicio = :q_num";
        $params[':q_num'] = (int)$busqueda;
    } else {
        // Si contiene letras busca exclusivamente sobre campos de texto indexados
        $whereSql .= " AND (s.nombreServicio LIKE :q_str1 OR s.descripcionServicio LIKE :q_str2)";
        $paramStr = '%' . $busqueda . '%';
        $params[':q_str1'] = $paramStr;
        $params[':q_str2'] = $paramStr;
    }
}

try {
    // 1. Conteo total optimizado
    $stmtCount = $conexion->prepare("SELECT COUNT(*) FROM servicios s $whereSql");
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetchColumn();
    $totalPaginas = ceil($totalRegistros / $limite);

    // 2. Extracción de registros parciales
    $query = "
        SELECT s.IDServicio, s.nombreServicio, s.descripcionServicio, s.costoServicio, s.estado
        FROM servicios s
        $whereSql
        ORDER BY s.nombreServicio ASC
        LIMIT :limite OFFSET :offset
    ";

    $stmt = $conexion->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Fallo crítico al compilar el catálogo de servicios: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Catálogo de Servicios - <?php echo htmlspecialchars($config['nombre_empresa']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --color-primario: <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>;
            --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>;
        }
        .wrapper { margin: 0 auto; max-width: 1200px; }
        .header-box { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 15px 20px; border-radius: 6px; border: 1px solid #eef0f2; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        h2 { margin: 0; color: var(--color-primario); font-size: 22px; }
        
        .search-form { display: flex; gap: 10px; }
        .search-input { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; width: 250px; }
        .btn-search { background: var(--color-primario); color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-clear { background: #e2e8f0; color: #333; text-decoration: none; padding: 8px 15px; border-radius: 4px; font-size: 13px; font-weight: bold; }

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
        .btn-return:hover { color: var(--color-primario); }
        
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .page-link { padding: 8px 12px; border: 1px solid #ddd; background: white; color: var(--color-primario); text-decoration: none; border-radius: 4px; font-weight: bold; }
        .page-link:hover { background: var(--color-fondo); }
        .page-link.active { background: var(--color-primario); color: white; border-color: var(--color-primario); pointer-events: none; }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: var(--color-fondo); display: flex; height: 100vh; overflow: hidden; }
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
                    <h2>Directorio de Servicios</h2>
                    
                    <form class="search-form" method="GET">
                        <input type="text" name="q" class="search-input" placeholder="Buscar por ID o Nombre..." value="<?php echo htmlspecialchars($busqueda); ?>">
                        <select name="limite" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; outline: none; margin-left: 10px;" onchange="this.form.submit()">
                            <option value="5" <?php echo $limite == 5 ? 'selected' : ''; ?>>5 / pág</option>
                            <option value="10" <?php echo $limite == 10 ? 'selected' : ''; ?>>10 / pág</option>
                            <option value="50" <?php echo $limite == 50 ? 'selected' : ''; ?>>50 / pág</option>
                        </select>
                        <button type="submit" class="btn-search">Buscar</button>
                        <?php if($busqueda !== ''): ?><a href="listar_servicios.php" class="btn-clear">Limpiar</a><?php endif; ?>
                    </form>

                    <div style="display: flex; gap: 10px;">
                        <?php if ($puede_crear): ?>
                        <a href="crear_servicio.php" class="btn-add">Agregar Nuevo Servicio</a>
                        <?php endif; ?>
                    </div>
                </div>

        <div class="table-card" style="background: #fff; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); border: 1px solid #eef0f2; overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Nombre del Servicio</th>
                        <th>Descripción</th>
                        <th>Costo Unitario</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($servicios) === 0): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 40px; color: #777;">No existen servicios registrados con los criterios indicados.</td></tr>
                    <?php else: ?>
                        <?php foreach($servicios as $s): ?>
                            <?php $claseEstado = ($s['estado'] === 'Activo') ? 'badge-active' : 'badge-inactive'; ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($s['nombreServicio']); ?></strong></td>
                                <td style="max-width: 350px; color: #555;"><?php echo htmlspecialchars($s['descripcionServicio'] ? $s['descripcionServicio'] : '-'); ?></td>
                                <td><strong style="color: var(--color-primario);">$<?php echo number_format($s['costoServicio'], 2, ',', '.'); ?></strong></td>
                                <td>
                                    <span class="badge <?php echo $claseEstado; ?>"><?php echo htmlspecialchars($s['estado']); ?></span>
                                </td>
                                <td>
                                    <?php if ($puede_editar): ?>
                                    <a href="editar_servicio.php?id=<?php echo $s['IDServicio']; ?>" class="btn-editar">Editar</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas >= 1): ?>
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