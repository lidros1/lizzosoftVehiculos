<?php
/**
 * Lizzosoft Vehículos - Reporte de Accesos y Auditoría
 * Ubicación: lizzosoft_vehiculos/Reportes/reporteAccesos.php
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

$defaultHasta = date('Y-m-d');
$defaultDesde = date('Y-m-d', strtotime('-7 days'));

$f_desde = trim($_GET['f_desde'] ?? $defaultDesde);
$f_hasta = trim($_GET['f_hasta'] ?? $defaultHasta);
$usuario = trim($_GET['usuario'] ?? '');
$f_accion = trim($_GET['f_accion'] ?? '');

if ($f_desde > $f_hasta) {
    $temp = $f_desde;
    $f_desde = $f_hasta;
    $f_hasta = $temp;
}

$where = "WHERE empresa_id = :empresa_id AND sucursal_id = :sucursal_id AND DATE(fecha_hora) BETWEEN :desde AND :hasta";
$params = [
    ':empresa_id' => $empresa_id,
    ':sucursal_id' => $sucursal_id,
    ':desde' => $f_desde,
    ':hasta' => $f_hasta
];

if ($usuario !== '') {
    $where .= " AND nombreUsuario LIKE :usr";
    $params[':usr'] = '%' . $usuario . '%';
}
if ($f_accion !== '') {
    $where .= " AND accion LIKE :accion";
    $params[':accion'] = $f_accion . '%';
}

try {
    $conexion->exec("UPDATE logs_accesos SET accion = REPLACE(accion, 'Cambio crítico: contraseña', 'Cambio de contraseña')");
    $conexion->exec("UPDATE logs_accesos SET accion = REPLACE(accion, 'Inicio de sesión exitoso', 'Inicio de sesion')");
    $conexion->exec("UPDATE logs_accesos SET accion = REPLACE(accion, 'Inicio de sesión', 'Inicio de sesion')");
    $conexion->exec("UPDATE logs_accesos SET accion = REPLACE(accion, 'Alta de nuevo servicio simplificado', 'Alta de nuevo servicio')");
    $conexion->exec("UPDATE logs_accesos SET accion = REPLACE(accion, 'Modificacion de servicio simplificado', 'Edicion de servicio')");
    $conexion->exec("UPDATE logs_accesos SET accion = REPLACE(accion, 'Vinculación de nueva cuenta de usuario al empleado ID', 'Vinculacion de cuenta: ID Empleado')");
    $conexion->exec("DROP TRIGGER IF EXISTS tr_log_cambio_usuario");
    $conexion->exec("CREATE TRIGGER tr_log_cambio_usuario AFTER UPDATE ON usuarios FOR EACH ROW BEGIN IF OLD.passwordUsuario != NEW.passwordUsuario THEN INSERT INTO logs_accesos (IDUsuario, nombreUsuario, accion, fecha_hora, empresa_id) VALUES (NEW.IDUsuario, NEW.nombreUsuario, 'Cambio de contraseña: ', NOW(), NEW.empresa_id); END IF; END;");
} catch(Exception $e) {}

try {
    // Obtener lista de acciones disponibles genéricas (cortando en los dos puntos si los hay)
    $stmtAcciones = $conexion->prepare("SELECT DISTINCT TRIM(SUBSTRING_INDEX(accion, ':', 1)) AS accion_generica FROM logs_accesos WHERE empresa_id = ? ORDER BY accion_generica ASC");
    $stmtAcciones->execute([$empresa_id]);
    $acciones_disponibles = $stmtAcciones->fetchAll(PDO::FETCH_COLUMN);

    $sql = "SELECT nombreUsuario, accion, fecha_hora FROM logs_accesos $where ORDER BY fecha_hora DESC LIMIT 200";
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al generar el reporte.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accesos y Seguridad - <?php echo htmlspecialchars($config['nombre_empresa']); ?></title>
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
        .filter-container input, .filter-container select { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; outline: none; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; text-align: left; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        th { background: #f8f9fa; padding: 14px; font-size: 13px; border-bottom: 2px solid #eaeaea; }
        td { padding: 14px; border-bottom: 1px solid #f1f1f1; font-size: 14px; }
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
                <h1 style="margin: 0; font-size: 24px; color: var(--color-primario);">Registro de Accesos y Auditoría</h1>
                <p style="font-size: 13px; color: #666;">Muestra las últimas 200 acciones realizadas por los usuarios en el sistema.</p>
            </div>

            <form method="GET" class="filter-container">
                <input type="date" name="f_desde" value="<?php echo htmlspecialchars($f_desde); ?>" onchange="this.form.submit()">
                <span>hasta</span>
                <input type="date" name="f_hasta" value="<?php echo htmlspecialchars($f_hasta); ?>" onchange="this.form.submit()">
                <input type="text" name="usuario" placeholder="Filtrar por Usuario..." value="<?php echo htmlspecialchars($usuario); ?>" style="width: 200px;">
                <select name="f_accion" onchange="this.form.submit()" style="width: 220px;">
                    <option value="">Todas las acciones...</option>
                    <?php foreach($acciones_disponibles as $act): ?>
                        <option value="<?php echo htmlspecialchars($act); ?>" <?php echo $f_accion === $act ? 'selected' : ''; ?>><?php echo htmlspecialchars($act); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" style="background:var(--color-primario); color:white; border:none; padding:9px 15px; border-radius:4px; font-weight:bold; cursor:pointer;">Buscar</button>
            </form>

            <table>
                <thead>
                    <tr>
                        <th style="width: 15%;">Fecha y Hora</th>
                        <th style="width: 20%;">Usuario Implicado</th>
                        <th style="width: 65%;">Acción Registrada en el Sistema</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="3" style="text-align:center; padding: 30px; color: #888;">No hay registros para los filtros indicados.</td></tr>
                    <?php else: ?>
                        <?php foreach($logs as $l): ?>
                            <tr>
                                <td><span style="font-size: 12px; color:#555;"><?php echo date('d/m/Y H:i:s', strtotime($l['fecha_hora'])); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($l['nombreUsuario']); ?></strong></td>
                                <td>
                                    <?php 
                                        $actStr = $l['accion'];
                                        $badgeBg = '#e2e3e5'; $badgeCol = '#383d41'; // default gris
                                        if (strpos($actStr, 'CREAR') !== false || stripos($actStr, 'Alta') !== false) { $badgeBg = '#d1fae5'; $badgeCol = '#065f46'; } // verde
                                        elseif (strpos($actStr, 'EDITAR') !== false || stripos($actStr, 'Edicion') !== false || stripos($actStr, 'Modificacion') !== false) { $badgeBg = '#dbeafe'; $badgeCol = '#1e40af'; } // azul
                                        elseif (strpos($actStr, 'Cierre de sesión') !== false || strpos($actStr, 'RESTABLECER') !== false) { $badgeBg = '#fee2e2'; $badgeCol = '#991b1b'; } // rojo
                                    ?>
                                    <span style="font-size: 11px; font-weight: bold; padding: 4px 8px; border-radius: 4px; background: <?php echo $badgeBg; ?>; color: <?php echo $badgeCol; ?>; border: 1px solid rgba(0,0,0,0.05); text-transform: uppercase;">
                                        <?php echo htmlspecialchars($actStr); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </main>
    </div>

    <script>
        /* JS del sidebar movido a HTML/sidebar.php */
    </script>
</body>
</html>