<?php
/**
 * Lizzosoft Vehículos - Edición Estética de Orden de Trabajo (Con Personal Asignado)
 * Ubicación: lizzosoft_vehiculos/CRUD_Ordenes/editar_ordenTrabajo.php
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
$funciones_permitidas = $_SESSION['funciones_permitidas'][1] ?? [];
if ((!in_array(1, $areas_permitidas) || !in_array(2, $funciones_permitidas)) && !$es_admin) {
    die("<div style='padding:20px; font-family:Arial; color:#721c24; background:#f8d7da;'>Error: No tienes permisos para editar Órdenes de Trabajo.</div>");
}

$conexion = obtenerConexion();
$mensaje = '';
$tipoMensaje = '';

$idRegistro = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idRegistro <= 0) { header("Location: ../inicio.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prioridad  = (int)($_POST['prioridad'] ?? 7);
    $obsGeneral = strip_tags(trim($_POST['observacion_general'] ?? ''));
    
    $kmIngreso  = (int)($_POST['km_ingreso'] ?? 0);
    $nivelComb  = strip_tags(trim($_POST['nivel_combustible'] ?? ''));
    $motorUpd   = strip_tags(trim($_POST['upd_motor'] ?? ''));
    $chasisUpd  = strip_tags(trim($_POST['upd_chasis'] ?? ''));
    
    // Tratamiento de empleado asignado
    $docPersonal = trim($_POST['numeroDocumentoPersonal'] ?? '');
    $docPersonal = ($docPersonal === '') ? null : $docPersonal;

    if ($kmIngreso < 0) {
        $mensaje = "El kilometraje de ingreso no puede ser negativo.";
        $tipoMensaje = "error";
    } else {

    $updIds     = $_POST['upd_det_id'] ?? [];
    $updCostos  = $_POST['upd_costo'] ?? [];
    $updObs     = $_POST['upd_obs'] ?? [];

    $newIds     = $_POST['new_srv_id'] ?? [];
    $newCostos  = $_POST['new_costo'] ?? [];
    $newObs     = $_POST['new_obs'] ?? [];

    $delIds     = $_POST['del_det_id'] ?? [];

    try {
        $conexion->beginTransaction();

        $sqlReg = "UPDATE registrosservicios SET observacionGeneral = ?, prioridad = ?, nivelCombustible = ?, kilometrajeIngreso = ?, numeroDocumentoPersonal = ? WHERE IDRegistroServicio = ? AND empresa_id = ?";
        $stmtReg = $conexion->prepare($sqlReg);
        $stmtReg->execute([$obsGeneral, $prioridad, $nivelComb, $kmIngreso, $docPersonal, $idRegistro, $empresa_id]);

        $stmtVehId = $conexion->prepare("SELECT IDVehiculo FROM registrosservicios WHERE IDRegistroServicio = ?");
        $stmtVehId->execute([$idRegistro]);
        $vehId = $stmtVehId->fetchColumn();

        if ($vehId) {
            $stmtUpdVeh = $conexion->prepare("UPDATE vehiculos SET numeroMotor = ?, numeroChasis = ? WHERE IDVehiculo = ? AND empresa_id = ?");
            $stmtUpdVeh->execute([$motorUpd, $chasisUpd, $vehId, $empresa_id]);
        }

        $stmtUpdDet = $conexion->prepare("UPDATE detalleregistro SET costoServicio = ?, observacionRegistroServicio = ? WHERE IDDetalleregistro = ? AND IDRegistroServicio = ?");
        for ($i = 0; $i < count($updIds); $i++) {
            $detId = (int)$updIds[$i];
            if ($detId > 0) {
                $stmtUpdDet->execute([(float)$updCostos[$i], strip_tags(trim($updObs[$i])), $detId, $idRegistro]);
            }
        }

        $stmtDelDet = $conexion->prepare("DELETE FROM detalleregistro WHERE IDDetalleregistro = ? AND IDRegistroServicio = ?");
        for ($i = 0; $i < count($delIds); $i++) {
            $delId = (int)$delIds[$i];
            if ($delId > 0) {
                $stmtDelDet->execute([$delId, $idRegistro]);
            }
        }

        $stmtInsDet = $conexion->prepare("INSERT INTO detalleregistro (IDRegistroServicio, IDServicio, observacionRegistroServicio, costoServicio, sucursal_id, empresa_id) VALUES (?, ?, ?, ?, ?, ?)");
        for ($i = 0; $i < count($newIds); $i++) {
            $srvId = (int)$newIds[$i];
            if ($srvId > 0) {
                $stmtInsDet->execute([$idRegistro, $srvId, strip_tags(trim($newObs[$i])), (float)$newCostos[$i], $sucursal_id, $empresa_id]);
            }
        }

        $stmtLog = $conexion->prepare("INSERT INTO logs_accesos (IDUsuario, nombreUsuario, accion, fecha_hora, empresa_id, sucursal_id) VALUES (?, ?, ?, NOW(), ?, ?)");
        $stmtLog->execute([$_SESSION['IDUsuario'], $_SESSION['nombreUsuario'], 'EDITAR_ORDEN', $empresa_id, $sucursal_id]);

        $conexion->commit();
        $mensaje = "Actualización de Orden de Trabajo procesada exitosamente.";
        $tipoMensaje = "exito";

    } catch (Exception $e) {
        $conexion->rollBack();
        $mensaje = "Error operativo: " . $e->getMessage();
        $tipoMensaje = "error";
    }
}
}
try {
    $stmtOT = $conexion->prepare("
        SELECT rs.*, v.patente, v.marca, v.modelo, v.numeroMotor, v.numeroChasis, c.nombre, c.apellido, c.numeroDocumentoCliente, es.nombreEstadoSolicitud
        FROM registrosservicios rs
        INNER JOIN vehiculos v ON rs.IDVehiculo = v.IDVehiculo
        INNER JOIN clientes c ON v.IDCliente = c.IDCliente
        INNER JOIN estadossolicitud es ON rs.IDEstado = es.IDEstadoSolicitud
        WHERE rs.IDRegistroServicio = ? AND rs.empresa_id = ?
    ");
    $stmtOT->execute([$idRegistro, $empresa_id]);
    $orden = $stmtOT->fetch(PDO::FETCH_ASSOC);

    if (!$orden) die("Ficha de orden no localizada en el sistema.");

    $stmtDet = $conexion->prepare("
        SELECT dr.*, s.nombreServicio 
        FROM detalleregistro dr
        INNER JOIN servicios s ON dr.IDServicio = s.IDServicio
        WHERE dr.IDRegistroServicio = ?
    ");
    $stmtDet->execute([$idRegistro]);
    $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    $stmtServicios = $conexion->prepare("SELECT IDServicio, nombreServicio, costoServicio FROM servicios WHERE empresa_id = ? AND estado = 'Activo'");
    $stmtServicios->execute([$empresa_id]);
    $catalogoSrv = $stmtServicios->fetchAll(PDO::FETCH_ASSOC);

    $stmtPersonal = $conexion->prepare("SELECT numeroDocumentoPersonal, nombre, apellido FROM personal WHERE empresa_id = ? AND estado = 'Activo'");
    $stmtPersonal->execute([$empresa_id]);
    $listaPersonal = $stmtPersonal->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error crítico al extraer datos de origen.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Modificación de Orden #<?php echo str_pad($orden['numeroOrdenTrabajo'], 8, '0', STR_PAD_LEFT); ?></title>
    <style>
        :root { --color-primario: <?php echo $apariencia['color_primario']; ?>; --color-fondo: <?php echo $apariencia['color_fondo']; ?>; --border-color: #dee2e6; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: var(--color-fondo); margin: 0; color: #333; display: flex; height: 100vh; overflow: hidden; }
        
        /* MAIN WRAPPER & TOPBAR */
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar { background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); flex-shrink: 0; z-index: 10; border-bottom: 1px solid #eef0f2; }
        .user-info { font-size: 13px; font-weight: 500; color: #666; }
        .btn-logout { color: #e74c3c; text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid #e74c3c; padding: 5px 15px; border-radius: 4px; transition: all 0.2s; }
        .btn-logout:hover { background: #e74c3c; color: #fff; }
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }

        .wrapper { background: #fff; max-width: 1100px; margin: 0 auto; padding: 35px; border-radius: 10px; box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
        .header-box { display: flex; justify-content: space-between; border-bottom: 2px solid var(--color-fondo); padding-bottom: 15px; margin-bottom: 25px; align-items: center; }
        h2 { margin: 0; color: var(--color-primario); font-size: 24px; }
        .alerta { padding: 15px; border-radius: 6px; margin-bottom: 25px; font-weight: bold; font-size: 14px; }
        .alerta-exito { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alerta-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .info-card { background: #f8f9fa; border: 1px solid var(--border-color); border-radius: 8px; padding: 18px; border-left: 5px solid var(--color-primario); }
        .info-card-title { font-size: 11px; text-transform: uppercase; color: #777; font-weight: bold; margin-bottom: 8px; letter-spacing: 0.5px; }
        .info-card-value { font-size: 16px; color: #333; font-weight: 700; }

        .step-container { border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 30px; overflow: hidden; background: #fff; }
        .step-header { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid var(--border-color); font-weight: bold; color: var(--color-primario); font-size: 15px; display: flex; align-items: center; gap: 10px; }
        .step-number { background: var(--color-primario); color: white; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 12px; }
        .step-body { padding: 25px; }
        
        .form-row { display: flex; gap: 25px; margin-bottom: 15px; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 200px; }
        label { display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 8px; color: #555; }
        input[type="text"], input[type="number"], select, textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-family: inherit; font-size: 14px; background: #fafbfc; transition: all 0.2s; }
        input:focus, select:focus, textarea:focus { border-color: var(--color-primario); outline: none; background: #fff; }
        textarea { resize: none; min-height: 80px; }

        input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }

        .btn-global { display: inline-block; padding: 12px 25px; font-size: 14px; font-weight: bold; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; text-align: center; }
        .btn-primario { background: var(--color-primario); color: white; }
        .btn-primario:hover { opacity: 0.9; }
        .btn-secundario { background: #e2e8f0; color: #333; padding: 10px 20px; font-size: 13px; }
        .btn-add-service { background: #28a745; color: white; padding: 8px 12px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 12px; }
        .btn-add-service:hover { background: #218838; }

        .table-catalogo, .table-servicios { width: 100%; border-collapse: collapse; margin-top: 15px; background: #fff; border: 1px solid var(--border-color); font-size: 13px; }
        .table-catalogo th, .table-servicios th { background: #f8f9fa; font-weight: bold; text-transform: uppercase; color: #555; font-size: 11px; padding: 12px; text-align: left; }
        .table-catalogo td, .table-servicios td { border-bottom: 1px solid var(--border-color); padding: 12px; }
        .btn-remove { background: #dc3545; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 12px; }
        .btn-remove:hover { background: #c82333; }

        .grid-services { display: grid; grid-template-columns: 35% 65%; gap: 20px; }

        .total-box { font-size: 18px; font-weight: bold; text-align: right; padding: 15px; background: #f8f9fa; border-radius: 4px; margin-top: 15px; border: 1px solid var(--border-color); }
        .pagination-container { display: flex; justify-content: center; gap: 5px; margin-top: 15px; }
        .page-btn { border: 1px solid #ddd; padding: 5px 10px; background: white; cursor: pointer; font-size: 12px; border-radius: 3px; font-weight: bold; color: var(--color-primario); }
        .page-btn.active { background: var(--color-primario); color: white; border-color: var(--color-primario); pointer-events: none; }
        .search-box { position: relative; }
        .search-results { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ccc; border-top: none; max-height: 250px; overflow-y: auto; z-index: 100; border-radius: 0 0 4px 4px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: none; }
        .search-item { padding: 10px 15px; cursor: pointer; border-bottom: 1px solid #eee; font-size: 13px; color: #444; }
        .search-item:hover { background: var(--color-primario); color: white; }
        .client-badge { background: #e3f2fd; color: #004085; padding: 12px 15px; border-radius: 4px; font-size: 13px; font-weight: 500; border: 1px solid #b8daff; }
        .client-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        .client-table th, .client-table td { border-bottom: 1px solid #eee; padding: 8px; text-align: left; }
        .client-table th { background: #f8f9fa; font-weight: bold; color: #555; }
        .client-table tr.cli-row:hover td { background: #f1f5f9; cursor: pointer; }
    </style>
    <?php if(($temaActual ?? $_SESSION['tema_preferido'] ?? '') === 'oscuro'): ?>
        <link rel="stylesheet" href="../CSS/modo_oscuro.css?v=<?php echo time(); ?>">
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        <div class="header-box">
            <h2>Modificación de Orden #<?php echo str_pad($orden['numeroOrdenTrabajo'], 8, '0', STR_PAD_LEFT); ?></h2>
            <div>
                <a href="ver_orden.php?id=<?php echo $idRegistro; ?>" class="btn-global" style="background:#fff; color:var(--color-primario); border:1px solid var(--color-primario); margin-right:10px;">Ver Orden</a>
                <a href="../inicio.php" class="btn-global btn-secundario">Volver</a>
            </div>
        </div>

        <?php if($mensaje): ?><div class="alerta <?php echo $tipoMensaje == 'exito' ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo $mensaje; ?></div><?php endif; ?>

        <div class="info-grid">
            <div class="info-card">
                <div class="info-card-title">Titular de la Orden</div>
                <div class="info-card-value"><?php echo htmlspecialchars($orden['apellido'] . ', ' . $orden['nombre']); ?></div>
                <div style="font-size: 13px; color: #666; margin-top: 6px;">DNI: <?php echo htmlspecialchars($orden['numeroDocumentoCliente']); ?></div>
            </div>
            <div class="info-card">
                <div class="info-card-title">Vehículo Asignado</div>
                <div class="info-card-value"><?php echo htmlspecialchars($orden['patente']); ?></div>
                <div style="font-size: 13px; color: #666; margin-top: 6px;"><?php echo htmlspecialchars($orden['marca'] . ' ' . $orden['modelo']); ?></div>
            </div>
            <div class="info-card" style="border-left-color: #28a745;">
                <div class="info-card-title">Estado de Operación</div>
                <div class="info-card-value" style="color: #28a745;"><?php echo htmlspecialchars($orden['nombreEstadoSolicitud']); ?></div>
                <div style="font-size: 13px; color: #666; margin-top: 6px;">Ingreso Oficial: <?php echo date('d/m/Y', strtotime($orden['fechaRegistroServicio'])); ?></div>
            </div>
        </div>

        <form method="POST">
            <div class="step-container" style="border-color: #ffeeba;">
                <div class="step-header" style="background: #fff3cd; color: #856404; border-color: #ffeeba;"><div class="step-number" style="background:#856404;">T</div> Verificación Técnica del Vehículo</div>
                <div class="step-body">
                    <div class="form-row">
                        <div class="form-group"><label>Kilometraje Actual *</label><input type="number" name="km_ingreso" value="<?php echo htmlspecialchars($orden['kilometrajeIngreso'] ?? 0); ?>" required></div>
                        <div class="form-group">
                            <label>Nivel de Combustible *</label>
                            <select name="nivel_combustible" required>
                                <?php $niveles = ['Reserva', '1/4 Tanque', '1/2 Tanque', '3/4 Tanque', 'Lleno']; ?>
                                <?php foreach($niveles as $niv): ?>
                                    <option value="<?php echo $niv; ?>" <?php echo (($orden['nivelCombustible'] ?? '') == $niv) ? 'selected' : ''; ?>><?php echo $niv; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>N° de Motor</label><input type="text" name="upd_motor" value="<?php echo htmlspecialchars($orden['numeroMotor'] ?? ''); ?>"></div>
                        <div class="form-group"><label>N° de Chasis / VIN</label><input type="text" name="upd_chasis" value="<?php echo htmlspecialchars($orden['numeroChasis'] ?? ''); ?>"></div>
                    </div>
                </div>
            </div>

            <div class="step-container">
                <div class="step-header"><div class="step-number">1</div> Configuraciones Generales</div>
                <div class="step-body">
                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <label>Nivel de Prioridad *</label>
                            <select name="prioridad" required>
                                <option value="7" <?php echo $orden['prioridad']==7 ? 'selected':''; ?>>No Prioritario (Normal)</option>
                                <option value="6" <?php echo $orden['prioridad']==6 ? 'selected':''; ?>>Prioritario (Urgente)</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 2;">
                            <label>Observaciones Generales del Ingreso</label>
                            <textarea name="observacion_general" placeholder=""><?php echo htmlspecialchars($orden['observacionGeneral'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="form-row" style="margin-top: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label>Personal Asignado (Mecánico/Responsable)</label>
                            <div class="search-box" style="display: flex; gap: 5px; position: relative;">
                                <input type="text" id="buscar_personal_input" placeholder="Buscar empleado por nombre, apellido o DNI..." autocomplete="off" style="flex: 1; height: 40px; margin: 0;">
                                <button type="button" id="btn_buscar_personal" class="btn-global btn-primario" style="padding: 0 15px; height: 40px; margin: 0;">Buscar</button>
                                <div id="res_personal" class="search-results" style="top: 100%; left: 0; right: 0;"></div>
                            </div>
                            
                            <div id="tabla_personal_container" style="margin-top: 10px;">
                                <table class="client-table" id="tabla_personal_paginada" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th style="width: 30%;">DNI</th>
                                            <th>Empleado</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                                <div id="paginacion_personal" class="pagination-container"></div>
                            </div>
                            
                            <?php 
                                $empActivoDoc = $orden['numeroDocumentoPersonal'] ?? '';
                                $empActivoNombre = 'No hay personal asignado a esta orden.';
                                $mostrarBtnQuitar = 'none';
                                $colorFondoBadge = '#f8f9fa';
                                $colorTextoBadge = '#6c757d';
                                $bordeBadge = '#e2e3e5';

                                if ($empActivoDoc) {
                                    foreach($listaPersonal as $emp) {
                                        if ($emp['numeroDocumentoPersonal'] == $empActivoDoc) {
                                            $empActivoNombre = "{$emp['apellido']}, {$emp['nombre']} (DNI: {$emp['numeroDocumentoPersonal']})";
                                            $mostrarBtnQuitar = 'inline-block';
                                            $colorFondoBadge = '#e3f2fd';
                                            $colorTextoBadge = '#004085';
                                            $bordeBadge = '#b8daff';
                                            break;
                                        }
                                    }
                                }
                            ?>
                            <input type="hidden" name="numeroDocumentoPersonal" id="hidden_personal_id" value="<?php echo htmlspecialchars($empActivoDoc); ?>">
                            
                            <div id="badge_personal_selected" class="client-badge" style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px; width: 100%; background: <?php echo $colorFondoBadge; ?>; color: <?php echo $colorTextoBadge; ?>; border-color: <?php echo $bordeBadge; ?>;">
                                <div>
                                    <h4 id="titulo_personal_selected" style="margin: 0 0 5px 0; color: inherit; font-size: 13px;">Empleado Asignado:</h4>
                                    <span id="text_personal_selected"><?php echo htmlspecialchars($empActivoNombre); ?></span>
                                </div>
                                <button type="button" id="btn_remove_personal" class="btn-remove" onclick="deseleccionarPersonal()" title="Quitar asignación" style="display: <?php echo $mostrarBtnQuitar; ?>; padding: 4px 8px; margin-left: 10px; border-radius: 50%; width: 24px; height: 24px; line-height: 1;">✖</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="step-container">
                <div class="step-header"><div class="step-number">2</div> Asignación de Servicios</div>
                <div class="step-body">
                    <div class="grid-services">
                        <!-- Columna Izquierda: Catálogo -->
                        <div>
                            <div class="form-group search-box" style="margin-bottom: 15px;">
                                <label style="font-size: 11px; font-weight: bold; color: #555; text-transform: uppercase;">Buscar en Catálogo de Servicios</label>
                                <input type="text" id="buscar_servicio_input" placeholder="Filtrar servicios por nombre..." autocomplete="off">
                            </div>

                            <table class="table-catalogo" id="tabla_catalogo">
                                <thead>
                                    <tr>
                                        <th style="width: 50%;">Servicio Disponible</th>
                                        <th style="width: 30%;">Costo Base</th>
                                        <th style="width: 20%; text-align: center;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                            <div id="paginacion_catalogo" class="pagination-container" style="margin-top: 5px;"></div>
                        </div>

                        <!-- Columna Derecha: Asignados -->
                        <div>
                            <h4 style="margin-top: 0; margin-bottom: 5px; color: var(--color-primario); font-size: 13px;">Servicios Asignados a la Orden</h4>
                            <table class="table-servicios" id="tabla_servicios" style="margin-top: 0;">
                                <thead>
                                    <tr>
                                        <th style="width: 40%;">Servicio Seleccionado</th>
                                        <th style="width: 20%;">Costo Acordado ($)</th>
                                        <th style="width: 30%;">Detalles Adicionales del Procedimiento</th>
                                        <th style="width: 10%; text-align: center;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($detalles as $det): ?>
                                        <tr>
                                            <td>
                                                <strong style="color: var(--color-primario);"><?php echo htmlspecialchars($det['nombreServicio']); ?></strong>
                                                <input type="hidden" name="upd_det_id[]" value="<?php echo $det['IDDetalleregistro']; ?>">
                                            </td>
                                            <td><input type="number" step="0.01" name="upd_costo[]" value="<?php echo htmlspecialchars($det['costoServicio']); ?>" required style="background: #fff;" oninput="calcularTotal()"></td>
                                            <td><textarea name="upd_obs[]" style="min-height: 45px; background: #fff; padding: 8px;"><?php echo htmlspecialchars($det['observacionRegistroServicio'] ?? ''); ?></textarea></td>
                                            <td style="text-align: center;">
                                                <button type="button" class="btn-remove" onclick="eliminarServicioExistente(this, <?php echo $det['IDDetalleregistro']; ?>)">Quitar</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <div class="total-box">
                                MONTO TOTAL DE LA ORDEN: <span id="monto_total_display" style="color: var(--color-primario);">$ 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="text-align: right; padding-bottom: 40px; margin-top: 30px;">
                <button type="submit" class="btn-global btn-primario" style="font-size: 16px; padding: 15px 40px;">Guardar Actualización de la Orden</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const catalogoSrv = <?php echo json_encode($catalogoSrv); ?>;
            const tbodyCatalogo = document.querySelector('#tabla_catalogo tbody');
            const inputSrvBusqueda = document.getElementById('buscar_servicio_input');
            const paginacionCat = document.getElementById('paginacion_catalogo');
            
            let srvFiltrados = [...catalogoSrv];
            let paginaSrv = 1;
            const limitSrv = 5;

            function renderizarCatalogo() {
                tbodyCatalogo.innerHTML = '';
                const inicio = (paginaSrv - 1) * limitSrv;
                const fin = inicio + limitSrv;
                const paginaDatos = srvFiltrados.slice(inicio, fin);

                if(paginaDatos.length === 0) {
                    tbodyCatalogo.innerHTML = '<tr><td colspan="3" style="text-align:center; color:#888;">No hay servicios coincidentes.</td></tr>';
                    paginacionCat.innerHTML = '';
                    return;
                }
                
                paginaDatos.forEach(s => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong>${s.nombreServicio}</strong></td>
                        <td>$ ${parseFloat(s.costoServicio).toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                        <td style="text-align: center;">
                            <button type="button" class="btn-add-service" style="margin-top:0;" onclick="agregarServicioNuevo(${s.IDServicio}, '${s.nombreServicio.replace(/'/g, "\\'")}', ${s.costoServicio})">Agregar</button>
                        </td>
                    `;
                    tbodyCatalogo.appendChild(tr);
                });
                renderizarPaginacionSrv();
            }

            function renderizarPaginacionSrv() {
                paginacionCat.innerHTML = '';
                const totalPaginas = Math.ceil(srvFiltrados.length / limitSrv);
                if (totalPaginas <= 1) return;
                
                for (let i = 1; i <= totalPaginas; i++) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = `page-btn ${i === paginaSrv ? 'active' : ''}`;
                    btn.textContent = i;
                    btn.onclick = () => { paginaSrv = i; renderizarCatalogo(); };
                    paginacionCat.appendChild(btn);
                }
            }

            inputSrvBusqueda.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase().trim();
                srvFiltrados = catalogoSrv.filter(s => s.nombreServicio.toLowerCase().includes(term));
                paginaSrv = 1;
                renderizarCatalogo();
            });

            window.agregarServicioNuevo = function(id, nombre, costo) {
                const tbody = document.querySelector('#tabla_servicios tbody');
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <strong style="color: #28a745;">${nombre} (Nuevo)</strong>
                        <input type="hidden" name="new_srv_id[]" value="${id}">
                    </td>
                    <td><input type="number" step="0.01" name="new_costo[]" value="${costo}" required style="background:#fff;" oninput="calcularTotal()"></td>
                    <td><textarea name="new_obs[]" style="min-height: 45px; background:#fff; padding:8px;" placeholder=""></textarea></td>
                    <td style="text-align: center;"><button type="button" class="btn-remove" onclick="eliminarFilaNueva(this)">Quitar</button></td>
                `;
                tbody.appendChild(tr);
                calcularTotal();
            };

            window.eliminarFilaNueva = function(btn) {
                btn.closest('tr').remove();
                calcularTotal();
            };

            window.eliminarServicioExistente = function(btn, detId) {
                Swal.fire({
                    title: '¿Quitar servicio?',
                    text: "Este servicio se eliminará de la orden al guardar.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, quitar',
                    cancelButtonText: 'Cancelar',
                    heightAuto: false,
                    scrollbarPadding: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'del_det_id[]';
                        input.value = detId;
                        btn.closest('form').appendChild(input);
                        btn.closest('tr').remove();
                        calcularTotal();
                    }
                });
            };

            window.calcularTotal = function() {
                let total = 0;
                document.querySelectorAll('input[name="upd_costo[]"], input[name="new_costo[]"]').forEach(input => {
                    total += parseFloat(input.value) || 0;
                });
                document.getElementById('monto_total_display').innerText = '$ ' + total.toLocaleString('es-AR', {minimumFractionDigits: 2});
            };

            renderizarCatalogo();
            calcularTotal();

            // Paginación y Búsqueda de Personal
            const personalList = <?php echo json_encode($listaPersonal); ?>;
            const inputPersonal = document.getElementById('buscar_personal_input');
            const resPersonal = document.getElementById('res_personal');
            const tbodyPersonal = document.querySelector('#tabla_personal_paginada tbody');
            const paginacionPersonal = document.getElementById('paginacion_personal');
            const hiddenPersonal = document.getElementById('hidden_personal_id');
            const badgePersonal = document.getElementById('badge_personal_selected');
            const textPersonal = document.getElementById('text_personal_selected');
            const btnRemovePersonal = document.getElementById('btn_remove_personal');
            const btnBuscarPersonal = document.getElementById('btn_buscar_personal');

            let paginaPersonalActual = 1;
            const limitPersonal = 5;

            function renderizarTablaPersonal() {
                tbodyPersonal.innerHTML = '';
                const inicio = (paginaPersonalActual - 1) * limitPersonal;
                const fin = inicio + limitPersonal;
                const paginaDatos = personalList.slice(inicio, fin);

                if(paginaDatos.length === 0) {
                    tbodyPersonal.innerHTML = '<tr><td colspan="2" style="text-align:center; color:#888;">No hay personal registrado.</td></tr>';
                    paginacionPersonal.innerHTML = '';
                    return;
                }
                
                paginaDatos.forEach(p => {
                    const tr = document.createElement('tr');
                    tr.className = 'cli-row';
                    tr.innerHTML = `
                        <td><strong>${p.numeroDocumentoPersonal}</strong></td>
                        <td>${p.apellido}, ${p.nombre}</td>
                    `;
                    tr.onclick = () => seleccionarPersonal(p);
                    tbodyPersonal.appendChild(tr);
                });
                renderizarPaginacionPersonal();
            }

            function renderizarPaginacionPersonal() {
                paginacionPersonal.innerHTML = '';
                const totalPaginas = Math.ceil(personalList.length / limitPersonal);
                if (totalPaginas <= 1) return;
                
                let startPage = Math.max(1, paginaPersonalActual - 2);
                let endPage = Math.min(totalPaginas, startPage + 4);
                if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

                if (startPage > 1) {
                    const btnFirst = document.createElement('button');
                    btnFirst.type = 'button'; btnFirst.className = 'page-btn'; btnFirst.textContent = '1..';
                    btnFirst.onclick = () => { paginaPersonalActual = 1; renderizarTablaPersonal(); };
                    paginacionPersonal.appendChild(btnFirst);
                }

                for (let i = startPage; i <= endPage; i++) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = `page-btn ${i === paginaPersonalActual ? 'active' : ''}`;
                    btn.textContent = i;
                    btn.onclick = () => { paginaPersonalActual = i; renderizarTablaPersonal(); };
                    paginacionPersonal.appendChild(btn);
                }

                if (endPage < totalPaginas) {
                    const btnLast = document.createElement('button');
                    btnLast.type = 'button'; btnLast.className = 'page-btn'; btnLast.textContent = '..'+totalPaginas;
                    btnLast.onclick = () => { paginaPersonalActual = totalPaginas; renderizarTablaPersonal(); };
                    paginacionPersonal.appendChild(btnLast);
                }
            }

            renderizarTablaPersonal();

            function procesarBusquedaPersonal() {
                if(!inputPersonal) return;
                const val = String(inputPersonal.value || '').trim().toLowerCase();
                resPersonal.innerHTML = '';
                
                if (val.length >= 3) {
                    const filtrados = personalList.filter(p => 
                        String(p.nombre || '').toLowerCase().includes(val) || 
                        String(p.apellido || '').toLowerCase().includes(val) || 
                        String(p.numeroDocumentoPersonal || '').toLowerCase().includes(val)
                    ).slice(0, 5);

                    if (filtrados.length > 0) {
                        filtrados.forEach(p => {
                            const div = document.createElement('div');
                            div.className = 'search-item';
                            div.textContent = `${p.numeroDocumentoPersonal} - ${p.apellido}, ${p.nombre}`;
                            div.onclick = () => seleccionarPersonal(p);
                            resPersonal.appendChild(div);
                        });
                        resPersonal.style.display = 'block';
                    } else {
                        resPersonal.innerHTML = '<div class="search-item" style="color:#888;">Sin coincidencias.</div>';
                        resPersonal.style.display = 'block';
                    }
                } else {
                    resPersonal.style.display = 'none';
                }
            }

            if (inputPersonal) {
                inputPersonal.addEventListener('input', procesarBusquedaPersonal);
            }
            if (btnBuscarPersonal) {
                btnBuscarPersonal.addEventListener('click', procesarBusquedaPersonal);
            }

            document.addEventListener('click', (e) => {
                if(inputPersonal && !inputPersonal.contains(e.target) && resPersonal && !resPersonal.contains(e.target)) resPersonal.style.display = 'none';
            });

            window.seleccionarPersonal = function(p) {
                hiddenPersonal.value = p.numeroDocumentoPersonal;
                textPersonal.textContent = `${p.apellido}, ${p.nombre} (DNI: ${p.numeroDocumentoPersonal})`;
                badgePersonal.style.background = '#e3f2fd';
                badgePersonal.style.color = '#004085';
                badgePersonal.style.borderColor = '#b8daff';
                btnRemovePersonal.style.display = 'inline-block';
                
                inputPersonal.value = '';
                resPersonal.style.display = 'none';
            };

            window.deseleccionarPersonal = function() {
                hiddenPersonal.value = '';
                textPersonal.textContent = 'No hay personal asignado a esta orden.';
                badgePersonal.style.background = '#f8f9fa';
                badgePersonal.style.color = '#6c757d';
                badgePersonal.style.borderColor = '#e2e3e5';
                btnRemovePersonal.style.display = 'none';
            };
        });
    </script>
            </div>
        </main>
    </div>
</body>
</html>