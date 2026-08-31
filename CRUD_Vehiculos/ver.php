<?php
/**
 * Lizzosoft Vehículos - Ficha de Vehículo (Solo Lectura)
 * Ubicación: lizzosoft_vehiculos/CRUD_Vehiculos/ver.php
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

// Permisos: Vehículos (8) o Clientes (5) ya que se entra desde el historial del cliente.
if (!in_array(8, $areas_permitidas) && !in_array(5, $areas_permitidas) && !$es_admin) {
    die("<div style='padding:20px; font-family:Arial; color:#721c24; background:#f8d7da;'>Error: No tienes permisos para acceder a esta ficha.</div>");
}

$conexion = obtenerConexion();

$idVehiculo = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idVehiculo <= 0) { header("Location: listar_vehiculos.php"); exit; }

$stmtData = $conexion->prepare("SELECT v.*, c.nombre, c.apellido, c.numeroDocumentoCliente FROM vehiculos v INNER JOIN clientes c ON v.IDCliente = c.IDCliente WHERE v.IDVehiculo = ? AND v.empresa_id = ?");
$stmtData->execute([$idVehiculo, $empresa_id]);
$vData = $stmtData->fetch(PDO::FETCH_ASSOC);

if (!$vData) { die("Acceso denegado o vehículo inexistente."); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha Técnica de Vehículo</title>
    <style>
        :root { --color-primario: <?php echo $apariencia['color_primario']; ?>; --color-fondo: <?php echo $apariencia['color_fondo']; ?>; }
        body { font-family: sans-serif; background: var(--color-fondo); margin: 0; padding: 20px; color: #333; }
        .wrapper { background: #fff; max-width: 900px; margin: 0 auto; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border: 1px solid #eaeaea; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group.full { grid-column: 1 / -1; }
        
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #555; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; font-size: 14px; background-color: #f8f9fa; color: #555; cursor: default; }
        input:focus, textarea:focus { outline: none; }
        textarea { resize: none; min-height: 80px; }

        .btn-secundario { background: #e2e8f0; color: #333; text-decoration: none; padding: 10px 15px; border-radius: 4px; font-weight: bold; font-size: 13px; display: inline-block; cursor: pointer; border: none; }
        .btn-secundario:hover { background: #d0d7de; }
        .info-cliente { background: #f8f9fa; padding: 15px; border-radius: 4px; border: 1px dashed #ccc; margin-bottom: 20px; }
    </style>
    <?php if(($temaActual ?? $_SESSION['tema_preferido'] ?? '') === 'oscuro'): ?>
        <link rel="stylesheet" href="../CSS/modo_oscuro.css?v=<?php echo time(); ?>">
    <?php endif; ?>
</head>
<body class="<?php echo (($temaActual ?? $_SESSION['tema_preferido'] ?? '') === 'oscuro') ? 'tema-oscuro' : ''; ?>">
    <div class="wrapper">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
            <h2 style="margin: 0; color: var(--color-primario);">Ficha Técnica de Vehículo</h2>
            <button onclick="window.history.back()" class="btn-secundario">Volver</button>
        </div>

        <div class="info-cliente">
            <strong>Titular Actual:</strong> <?php echo htmlspecialchars(($vData['apellido'] ?? '') . ', ' . ($vData['nombre'] ?? '')); ?> 
            (Doc: <?php echo htmlspecialchars($vData['numeroDocumentoCliente'] ?? ''); ?>) <br>
        </div>

        <div class="form-grid">
            <div class="form-group full"><label>Patente / Dominio</label><input type="text" readonly value="<?php echo htmlspecialchars($vData['patente'] ?? ''); ?>" style="font-size:18px; font-weight:bold; letter-spacing:2px; text-transform:uppercase;"></div>
            
            <div><label>Tipo de Vehículo</label><input type="text" readonly value="<?php echo htmlspecialchars($vData['tipoVehiculo'] ?? ''); ?>"></div>
            <div><label>Color</label><input type="text" readonly value="<?php echo htmlspecialchars($vData['colorVehiculo'] ?? ''); ?>"></div>
            <div><label>Marca</label><input type="text" readonly value="<?php echo htmlspecialchars($vData['marca'] ?? ''); ?>"></div>
            <div><label>Modelo</label><input type="text" readonly value="<?php echo htmlspecialchars($vData['modelo'] ?? ''); ?>"></div>

            <div><label>Año</label><input type="text" readonly value="<?php echo htmlspecialchars(($vData['anioFabricacion'] ?? 0) > 0 ? $vData['anioFabricacion'] : ''); ?>"></div>
            <div><label>Combustible</label><input type="text" readonly value="<?php echo htmlspecialchars($vData['tipoCombustible'] ?? ''); ?>"></div>
            <div><label>N° Motor</label><input type="text" readonly value="<?php echo htmlspecialchars($vData['numeroMotor'] ?? ''); ?>"></div>
            <div><label>N° Chasis / VIN</label><input type="text" readonly value="<?php echo htmlspecialchars($vData['numeroChasis'] ?? ''); ?>"></div>
            
            <div class="form-group full">
                <label>Estado del Vehículo</label>
                <input type="text" readonly value="<?php echo htmlspecialchars($vData['estado'] ?? ''); ?>">
            </div>

            <div class="form-group full"><label>Observaciones Adicionales</label><textarea readonly><?php echo htmlspecialchars($vData['observacionVehiculo'] ?? ''); ?></textarea></div>
        </div>
    </div>
</body>
</html>
