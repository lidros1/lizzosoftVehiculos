<?php
/**
 * Lizzosoft Vehículos - Selector de Sucursales Estricto
 * Ubicación: lizzosoft_vehiculos/Login/seleccionar_sucursal.php
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

if (!isset($_SESSION['IDUsuario']) || !isset($_SESSION['cliente_config']['empresa_id'])) {
    header("Location: login.php");
    exit;
}

if (function_exists('obtenerConexion')) {
    $conexion = obtenerConexion();
} elseif (function_exists('Conectar')) {
    $conexion = Conectar();
}

$empresa_id = (int)$_SESSION['cliente_config']['empresa_id'];
$rol_id     = (int)$_SESSION['IDRol'];
$error      = '';

// -----------------------------------------------------------------------------------------
// REGLA OPERATIVA DE RUTEO DIRECTO (Roles no administrativos)
// -----------------------------------------------------------------------------------------
if ($rol_id !== 1) {
    $sucursal_automatica = (int)$_SESSION['sucursal_base'];

    try {
        $sqlOp = "SELECT s.id, s.nombre, s.IDRubro, r.nombreRubro 
                  FROM sucursales s
                  INNER JOIN rubros r ON s.IDRubro = r.IDRubro
                  WHERE s.id = ? AND s.empresa_id = ? AND (s.estado = 'Activo' OR s.estado = 'Active')
                  LIMIT 1";
        
        $stmtOp = $conexion->prepare($sqlOp);
        $stmtOp->execute([$sucursal_automatica, $empresa_id]);
        $datos_sucursal = $stmtOp->fetch();

        if ($datos_sucursal) {
            $_SESSION['cliente_config']['sucursal_id']     = $datos_sucursal['id'];
            $_SESSION['cliente_config']['nombre_sucursal'] = $datos_sucursal['nombre'];
            $_SESSION['cliente_config']['id_rubro']        = $datos_sucursal['IDRubro'];
            $_SESSION['cliente_config']['nombre_rubro']    = $datos_sucursal['nombreRubro'];

            $_SESSION['sucursal_id'] = $datos_sucursal['id'];
            $_SESSION['nombreRubro'] = $datos_sucursal['nombreRubro'];

            header("Location: ../inicio.php");
            exit;
        } else {
            session_unset();
            session_destroy();
            die("<div style='padding:30px; font-family:Arial; color:#721c24; background:#f8d7da; text-align:center;'>
                    <h3>Acceso denegado</h3>
                    <p>Sus privilegios operativos no coinciden con la empresa de esta URL.</p>
                    <a href='login.php'>Volver al Login</a>
                 </div>");
        }
    } catch (PDOException $e) {
        $error = "Error interno de validación operativa.";
    }
}

// -----------------------------------------------------------------------------------------
// REGLA ADMINISTRATIVA MULTITALLER
// -----------------------------------------------------------------------------------------
try {
    $sqlAdmin = "SELECT s.id, s.nombre, s.IDRubro, r.nombreRubro 
                 FROM sucursales s
                 INNER JOIN rubros r ON s.IDRubro = r.IDRubro
                 WHERE s.empresa_id = ? AND (s.estado = 'Activo' OR s.estado = 'Active')
                 ORDER BY s.nombre ASC";
    
    $stmtAdmin = $conexion->prepare($sqlAdmin);
    $stmtAdmin->execute([$empresa_id]);
    $sucursales = $stmtAdmin->fetchAll();

    if (count($sucursales) === 1) {
        $datos_sucursal = $sucursales[0];

        // Guardar lista por si en el futuro se agregan sucursales
        $_SESSION['sucursales_admin_disponibles'] = $sucursales;

        $_SESSION['cliente_config']['sucursal_id']     = $datos_sucursal['id'];
        $_SESSION['cliente_config']['nombre_sucursal'] = $datos_sucursal['nombre'];
        $_SESSION['cliente_config']['id_rubro']        = $datos_sucursal['IDRubro'];
        $_SESSION['cliente_config']['nombre_rubro']    = $datos_sucursal['nombreRubro'];

        $_SESSION['sucursal_id'] = $datos_sucursal['id'];
        $_SESSION['nombreRubro'] = $datos_sucursal['nombreRubro'];

        header("Location: ../inicio.php");
        exit;
    }

} catch (PDOException $e) {
    $error = "Error al conectar con la base de datos de sucursales.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sucursal_id']) && empty($error)) {
    $sucursal_seleccionada = (int)$_POST['sucursal_id'];
    
    $datos_sucursal = null;
    foreach ($sucursales as $suc) {
        if ($suc['id'] === $sucursal_seleccionada) {
            $datos_sucursal = $suc;
            break;
        }
    }

    if ($datos_sucursal) {
        // Guardar la lista para el desplegable global de la topbar
        $_SESSION['sucursales_admin_disponibles'] = $sucursales;

        $_SESSION['cliente_config']['sucursal_id']     = $datos_sucursal['id'];
        $_SESSION['cliente_config']['nombre_sucursal'] = $datos_sucursal['nombre'];
        $_SESSION['cliente_config']['id_rubro']        = $datos_sucursal['IDRubro'];
        $_SESSION['cliente_config']['nombre_rubro']    = $datos_sucursal['nombreRubro'];

        $_SESSION['sucursal_id'] = $datos_sucursal['id'];
        $_SESSION['nombreRubro'] = $datos_sucursal['nombreRubro'];

        header("Location: ../inicio.php");
        exit;
    } else {
        $error = "La sucursal seleccionada no pertenece a sus registros autorizados.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar Sucursal - Lizzosoft Vehículos</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: #fff; padding: 35px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); width: 100%; max-width: 400px; box-sizing: border-box; }
        h2 { margin: 0 0 15px 0; color: #2c3e50; font-size: 22px; font-weight: 600; text-align: left; }
        select { width: 100%; padding: 12px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 4px; font-size: 15px; background-color: #fafafa; }
        select:focus { border-color: #2c3e50; outline: none; }
        .btn { width: 100%; padding: 12px; background-color: #2c3e50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        .error { color: #e74c3c; margin-bottom: 15px; font-size: 14px; font-weight: 500; text-align: left; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Seleccionar Sucursal</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <select name="sucursal_id" required>
                <option value="">-- Seleccione una opción --</option>
                <?php foreach (($sucursales ?? []) as $suc): ?>
                    <?php 
                    // INTERCEPCIÓN DE TEXTO DE LA BD: Si dice "Autos y Camionetas", lo cambiamos por la etiqueta del config.php
                    $rubroDisplay = $suc['nombreRubro'];
                    if (str_contains(strtolower($rubroDisplay), 'auto') || str_contains(strtolower($rubroDisplay), 'camioneta')) {
                        $rubroDisplay = $_SESSION['cliente_config']['labels']['vehiculo_plural'] ?? 'Vehículos';
                    }
                    ?>
                    <option value="<?php echo $suc['id']; ?>">
                        <?php echo htmlspecialchars($suc['nombre'] . " [" . $rubroDisplay . "]"); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn">Ingresar</button>
        </form>
    </div>
</body>
</html>