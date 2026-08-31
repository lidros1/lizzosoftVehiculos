<?php
/**
 * Lizzosoft Vehículos - Edición de Vehículo (Sin imágenes)
 * Ubicación: lizzosoft_vehiculos/CRUD_Vehiculos/editar_vehiculo.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$config     = $_SESSION['cliente_config'];
$apariencia = $config['apariencia'];
$empresa_id = (int)$_SESSION['empresa_id_usuario'];
$sucursal_id= (int)$_SESSION['sucursal_id'];

$areas_permitidas = $_SESSION['areas_permitidas'] ?? [];
$es_admin         = (isset($_SESSION['IDRol']) && $_SESSION['IDRol'] == 1);
$funciones_permitidas = $_SESSION['funciones_permitidas'][8] ?? [];
if ((!in_array(8, $areas_permitidas) || !in_array(2, $funciones_permitidas)) && !$es_admin) {
    die("<div style='padding:20px; font-family:Arial; color:#721c24; background:#f8d7da;'>Error: No tienes permisos para editar Vehículos.</div>");
}

$conexion = obtenerConexion();
$mensaje = '';
$tipoMensaje = '';

$idVehiculo = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idVehiculo <= 0) { header("Location: listar_vehiculos.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patente      = trim(strtoupper($_POST['patente'] ?? ''));
    $tipoVehiculo = strip_tags(trim($_POST['tipo_vehiculo'] ?? ''));
    $marca        = strip_tags(trim($_POST['marca'] ?? ''));
    $modelo       = strip_tags(trim($_POST['modelo'] ?? ''));
    $anio         = (int)($_POST['anio'] ?? 0);
    $color        = strip_tags(trim($_POST['color'] ?? ''));
    $motor        = strip_tags(trim($_POST['motor'] ?? ''));
    $chasis       = strip_tags(trim($_POST['chasis'] ?? ''));
    $combustible  = strip_tags(trim($_POST['combustible'] ?? ''));
    $observacion  = strip_tags(trim($_POST['observacion'] ?? ''));
    $estado       = trim($_POST['estado'] ?? 'Activo');
    $anioMax      = (int)date('Y') + 1;

    if (empty($patente) || empty($tipoVehiculo) || empty($marca) || empty($modelo) || empty($color)) {
        $mensaje = "Patente, Tipo, Marca, Modelo y Color son campos obligatorios.";
        $tipoMensaje = "error";
    } elseif (preg_match('/[\s-]/', $patente)) {
        $mensaje = "La patente no debe contener espacios ni guiones.";
        $tipoMensaje = "error";
    } elseif (!preg_match('/^([A-Z]{3}[0-9]{3}|[A-Z]{2}[0-9]{3}[A-Z]{2})$/i', $patente)) {
        $mensaje = "Formato de patente inválido. Debe ser AAA000 o AA000AA.";
        $tipoMensaje = "error";
    } elseif ($anio > 0 && ($anio < 1900 || $anio > $anioMax)) {
        $mensaje = "El año de fabricación debe estar entre 1900 y $anioMax.";
        $tipoMensaje = "error";
    } else {
        try {
            $stmtCheckV = $conexion->prepare("SELECT IDVehiculo FROM vehiculos WHERE patente = ? AND empresa_id = ? AND IDVehiculo != ?");
            $stmtCheckV->execute([$patente, $empresa_id, $idVehiculo]);
            if ($stmtCheckV->fetch()) throw new Exception("La patente proporcionada ya pertenece a otro vehículo.");

            $sqlUpd = "UPDATE vehiculos SET patente=?, tipoVehiculo=?, marca=?, modelo=?, anioFabricacion=?, colorVehiculo=?, numeroMotor=?, numeroChasis=?, tipoCombustible=?, observacionVehiculo=?, estado=? WHERE IDVehiculo=? AND empresa_id=?";
            $stmtUpd = $conexion->prepare($sqlUpd);
            $stmtUpd->execute([$patente, $tipoVehiculo, $marca, $modelo, $anio, $color, $motor, $chasis, $combustible, $observacion, $estado, $idVehiculo, $empresa_id]);

            $stmtLog = $conexion->prepare("INSERT INTO logs_accesos (IDUsuario, nombreUsuario, accion, fecha_hora, empresa_id, sucursal_id) VALUES (?, ?, ?, NOW(), ?, ?)");
            $stmtLog->execute([$_SESSION['IDUsuario'], $_SESSION['nombreUsuario'], "Edicion de vehiculo: $patente", $empresa_id, $sucursal_id]);

            $mensaje = "Datos del vehículo actualizados de forma exitosa.";
            $tipoMensaje = "exito";
        } catch (Exception $e) {
            $mensaje = $e->getMessage();
            $tipoMensaje = "error";
        }
    }
}

$stmtData = $conexion->prepare("SELECT v.*, c.nombre, c.apellido, c.numeroDocumentoCliente FROM vehiculos v INNER JOIN clientes c ON v.IDCliente = c.IDCliente WHERE v.IDVehiculo = ? AND v.empresa_id = ?");
$stmtData->execute([$idVehiculo, $empresa_id]);
$vData = $stmtData->fetch(PDO::FETCH_ASSOC);

if (!$vData) { die("Acceso denegado o vehículo inexistente."); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Modificar Vehículo</title>
    <style>
        :root { --color-primario: <?php echo $apariencia['color_primario']; ?>; --color-fondo: <?php echo $apariencia['color_fondo']; ?>; }
        body { font-family: sans-serif; margin: 0; background: var(--color-fondo); display: flex; height: 100vh; overflow: hidden; color: #333; }
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        .wrapper { background: #fff; max-width: 900px; margin: 0 auto; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); border: 1px solid #eef0f2; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group.full { grid-column: 1 / -1; }
        
        input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }

        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #555; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; font-size: 14px; }
        input:focus, select:focus, textarea:focus { border-color: var(--color-primario); outline: none; }
        textarea { resize: none; min-height: 80px; }

        .btn-primario { background: var(--color-primario); color: white; padding: 12px 25px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-secundario { background: #e2e8f0; color: #333; text-decoration: none; padding: 10px 15px; border-radius: 4px; font-weight: bold; font-size: 13px; }
        .alerta { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; font-size: 14px; }
        .alerta-exito { background: #d4edda; color: #155724; }
        .alerta-error { background: #f8d7da; color: #721c24; }
        .info-cliente { background: #f8f9fa; padding: 15px; border-radius: 4px; border: 1px dashed #ccc; margin-bottom: 20px; }
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
            <div class="wrapper">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <h2 style="color: var(--color-primario); margin:0;">Edición Técnica de Vehículo</h2>
                    <a href="listar_vehiculos.php" style="color:var(--color-primario); text-decoration:none; font-weight:bold; font-size:14px; border:1px solid var(--color-primario); padding:6px 12px; border-radius:4px;">← Volver al Listado</a>
                </div>

        <?php if($mensaje): ?><div class="alerta <?php echo $tipoMensaje == 'exito' ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo $mensaje; ?></div><?php endif; ?>

        <div class="info-cliente" style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #eef0f2; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
            <strong>Titular Actual:</strong> <?php echo htmlspecialchars(($vData['apellido'] ?? '') . ', ' . ($vData['nombre'] ?? '')); ?> 
            (Doc: <?php echo htmlspecialchars($vData['numeroDocumentoCliente'] ?? ''); ?>) <br>
        </div>

        <form method="POST" style="background: #fff; padding: 30px; border-radius: 8px; border: 1px solid #eef0f2; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
            <div class="form-grid">
                <div class="form-group full"><label>Patente / Dominio *</label><input type="text" name="patente" required value="<?php echo htmlspecialchars($vData['patente'] ?? ''); ?>" style="font-size:18px; font-weight:bold; letter-spacing:2px; text-transform:uppercase;" placeholder=""></div>
                
                <div><label>Tipo de Vehículo *</label><input type="text" name="tipo_vehiculo" required value="<?php echo htmlspecialchars($vData['tipoVehiculo'] ?? ''); ?>" placeholder=""></div>
                <div><label>Color *</label><input type="text" name="color" required value="<?php echo htmlspecialchars($vData['colorVehiculo'] ?? ''); ?>" placeholder=""></div>
                <div><label>Marca *</label><input type="text" name="marca" required value="<?php echo htmlspecialchars($vData['marca'] ?? ''); ?>" placeholder=""></div>
                <div><label>Modelo *</label><input type="text" name="modelo" required value="<?php echo htmlspecialchars($vData['modelo'] ?? ''); ?>" placeholder=""></div>

                <div><label>Año</label><input type="number" name="anio" min="1900" max="2100" value="<?php echo htmlspecialchars(($vData['anioFabricacion'] ?? 0) > 0 ? $vData['anioFabricacion'] : ''); ?>" placeholder=""></div>
                <div><label>Combustible</label><input type="text" name="combustible" value="<?php echo htmlspecialchars($vData['tipoCombustible'] ?? ''); ?>" placeholder=""></div>
                <div><label>N° Motor</label><input type="text" name="motor" value="<?php echo htmlspecialchars($vData['numeroMotor'] ?? ''); ?>" placeholder=""></div>
                <div><label>N° Chasis / VIN</label><input type="text" name="chasis" value="<?php echo htmlspecialchars($vData['numeroChasis'] ?? ''); ?>" placeholder=""></div>
                
                <div class="form-group full">
                    <label>Estado del Vehículo *</label>
                    <select name="estado">
                        <option value="Activo" <?php echo (($vData['estado'] ?? '') === 'Activo') ? 'selected' : ''; ?>>Activo</option>
                        <option value="Inactivo" <?php echo (($vData['estado'] ?? '') === 'Inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>

                <div class="form-group full"><label>Observaciones Adicionales</label><textarea name="observacion" placeholder=""><?php echo htmlspecialchars($vData['observacionVehiculo'] ?? ''); ?></textarea></div>
            </div>
            
            <div style="text-align: right; margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
                <button type="submit" class="btn-primario">Actualizar Datos de Vehículo</button>
            </div>
        </form>
            </div>
        </main>
    </div>
</body>
</html>