<?php
/**
 * Lizzosoft Vehículos - Alta de Orden de Trabajo (Rediseño Estético)
 * Ubicación: lizzosoft_vehiculos/CRUD_Ordenes/crear_ordenTrabajo.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$config = $_SESSION['cliente_config'];
$apariencia = $config['apariencia'];
$empresa_id = (int) $_SESSION['empresa_id_usuario'];
$sucursal_id = (int) $_SESSION['sucursal_id'];

$areas_permitidas = $_SESSION['areas_permitidas'] ?? [];
$es_admin = (isset($_SESSION['IDRol']) && $_SESSION['IDRol'] == 1);
$funciones_permitidas = $_SESSION['funciones_permitidas'][1] ?? [];
if ((!in_array(1, $areas_permitidas) || !in_array(1, $funciones_permitidas)) && !$es_admin) {
    die("<div style='padding:20px; font-family:Arial; color:#721c24; background:#f8d7da;'>Error: No tienes permisos para crear Registros de Servicios.</div>");
}

$conexion = obtenerConexion();
$mensaje = '';
$tipoMensaje = '';

if (isset($_SESSION['flash_mensaje'])) {
    $mensaje = $_SESSION['flash_mensaje'];
    $tipoMensaje = $_SESSION['flash_tipo'];
    unset($_SESSION['flash_mensaje']);
    unset($_SESSION['flash_tipo']);
}

try {
    $stmtClientes = $conexion->prepare("SELECT IDCliente, numeroDocumentoCliente, nombre, apellido, telefono FROM clientes WHERE empresa_id = ? AND sucursal_id = ? AND estado = 'Activo'");
    $stmtClientes->execute([$empresa_id, $sucursal_id]);
    $clientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);

    $stmtVehiculos = $conexion->prepare("SELECT IDVehiculo, IDCliente, patente, marca, modelo, numeroMotor, numeroChasis, anioFabricacion, colorVehiculo, tipoVehiculo FROM vehiculos WHERE empresa_id = ? AND sucursal_id = ? AND estado = 'Activo'");
    $stmtVehiculos->execute([$empresa_id, $sucursal_id]);
    $vehiculos = $stmtVehiculos->fetchAll(PDO::FETCH_ASSOC);

    $stmtServicios = $conexion->prepare("SELECT IDServicio, nombreServicio, costoServicio FROM servicios WHERE empresa_id = ? AND estado = 'Activo'");
    $stmtServicios->execute([$empresa_id]);
    $serviciosList = $stmtServicios->fetchAll(PDO::FETCH_ASSOC);

    $tiposDoc = $conexion->query("SELECT IDTipoDocumento, tipoDocumento FROM tiposdocumentos WHERE estado = 'Activo'")->fetchAll(PDO::FETCH_ASSOC);
    $tiposTel = $conexion->query("SELECT IDTipoNumeroTelefono, tipoNumeroTelefono FROM tiposnumerotelefono WHERE estado = 'Activo'")->fetchAll(PDO::FETCH_ASSOC);

    $stmtPersonal = $conexion->prepare("SELECT numeroDocumentoPersonal, nombre, apellido FROM personal WHERE empresa_id = ? AND estado = 'Activo'");
    $stmtPersonal->execute([$empresa_id]);
    $listaPersonal = $stmtPersonal->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "Error al cargar el catálogo de base de datos.";
    $tipoMensaje = "error";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idVehiculo = (int) ($_POST['id_vehiculo'] ?? 0);
    $prioridad = (int) ($_POST['prioridad'] ?? 7);

    // Datos técnicos ingresados
    $kmIngreso = (int) ($_POST['km_ingreso'] ?? 0);
    $nivelComb = strip_tags(trim($_POST['nivel_combustible'] ?? ''));
    $motorUpd = strip_tags(trim($_POST['upd_motor'] ?? ''));
    $chasisUpd = strip_tags(trim($_POST['upd_chasis'] ?? ''));
    $obsGeneral = strip_tags(trim($_POST['observacion_general'] ?? ''));

    $arrServicios = $_POST['servicios_id'] ?? [];
    $arrCostos = $_POST['servicios_costo'] ?? [];
    $arrObs = $_POST['servicios_obs'] ?? [];

    $docPersonal = trim($_POST['numeroDocumentoPersonal'] ?? '');
    $docPersonal = ($docPersonal === '') ? null : $docPersonal;

    if ($idVehiculo <= 0) {
        $mensaje = "Debe seleccionar un cliente y su respectivo vehículo.";
        $tipoMensaje = "error";
    } elseif (empty($arrServicios)) {
        $mensaje = "Debe asignar al menos un servicio a la orden de trabajo.";
        $tipoMensaje = "error";
    } elseif ($kmIngreso < 0) {
        $mensaje = "El kilometraje de ingreso no puede ser negativo.";
        $tipoMensaje = "error";
    } else {
        try {
            $conexion->beginTransaction();

            // 1. Actualizar datos técnicos en el padrón del vehículo
            $stmtUpdVeh = $conexion->prepare("UPDATE vehiculos SET numeroMotor = ?, numeroChasis = ? WHERE IDVehiculo = ? AND empresa_id = ?");
            $stmtUpdVeh->execute([$motorUpd, $chasisUpd, $idVehiculo, $empresa_id]);

            // 2. Generar Número de Orden
            $stmtNum = $conexion->prepare("SELECT MAX(numeroOrdenTrabajo) FROM registrosservicios WHERE sucursal_id = ? AND empresa_id = ? FOR UPDATE");
            $stmtNum->execute([$sucursal_id, $empresa_id]);
            $maxNum = $stmtNum->fetchColumn();
            $nuevoNumOrden = $maxNum ? $maxNum + 1 : 1001;

            // 3. Registrar Orden
            $sqlRegistro = "INSERT INTO registrosservicios (fechaRegistroServicio, IDVehiculo, observacionGeneral, prioridad, IDEstado, nivelCombustible, kilometrajeIngreso, sucursal_id, empresa_id, numeroOrdenTrabajo, numeroDocumentoPersonal) 
                            VALUES (CURDATE(), ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)";
            $stmtRegistro = $conexion->prepare($sqlRegistro);
            $stmtRegistro->execute([$idVehiculo, $obsGeneral, $prioridad, $nivelComb, $kmIngreso, $sucursal_id, $empresa_id, $nuevoNumOrden, $docPersonal]);

            $idRegistro = $conexion->lastInsertId();

            // 4. Detalle de Servicios
            $sqlDetalle = "INSERT INTO detalleregistro (IDRegistroServicio, IDServicio, observacionRegistroServicio, costoServicio, sucursal_id, empresa_id) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $stmtDetalle = $conexion->prepare($sqlDetalle);

            for ($i = 0; $i < count($arrServicios); $i++) {
                $idSrv = (int) $arrServicios[$i];
                $costo = (float) $arrCostos[$i];
                $obs = strip_tags(trim($arrObs[$i]));
                if ($idSrv > 0) {
                    $stmtDetalle->execute([$idRegistro, $idSrv, $obs, $costo, $sucursal_id, $empresa_id]);
                }
            }

            $stmtLog = $conexion->prepare("INSERT INTO logs_accesos (IDUsuario, nombreUsuario, accion, fecha_hora, empresa_id, sucursal_id) VALUES (?, ?, ?, NOW(), ?, ?)");
            $stmtLog->execute([$_SESSION['IDUsuario'], $_SESSION['nombreUsuario'], 'CREAR_ORDEN', $empresa_id, $sucursal_id]);

            $conexion->commit();

            $_SESSION['flash_mensaje'] = "Orden de Trabajo N° " . str_pad($nuevoNumOrden, 8, '0', STR_PAD_LEFT) . " generada y registrada con éxito.";
            $_SESSION['flash_tipo'] = "exito";

            header("Location: crear_ordenTrabajo.php");
            exit;

        } catch (Exception $e) {
            $conexion->rollBack();
            $mensaje = "Fallo en la base de datos: " . $e->getMessage();
            $tipoMensaje = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Nueva Orden de Trabajo</title>
    <style>
        :root {
            --color-primario:
                <?php echo $apariencia['color_primario']; ?>
            ;
            --color-fondo:
                <?php echo $apariencia['color_fondo']; ?>
            ;
            --border-color: #dee2e6;
        }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background-color: var(--color-fondo);
            margin: 0;
            color: #333;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* MAIN WRAPPER & TOPBAR */
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
            border-bottom: 1px solid #eef0f2;
        }

        .user-info {
            font-size: 13px;
            font-weight: 500;
            color: #666;
        }

        .btn-logout {
            color: #e74c3c;
            text-decoration: none;
            font-weight: bold;
            font-size: 13px;
            border: 1px solid #e74c3c;
            padding: 5px 15px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            background: #e74c3c;
            color: #fff;
        }

        .content-area {
            padding: 30px;
            overflow-y: auto;
            flex-grow: 1;
        }

        .wrapper {
            background: #fff;
            max-width: 1100px;
            margin: 0 auto;
            padding: 35px;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
        }

        .header-box {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid var(--color-fondo);
            padding-bottom: 15px;
            margin-bottom: 30px;
            align-items: center;
        }

        h2 {
            margin: 0;
            color: var(--color-primario);
            font-size: 24px;
        }

        .alerta {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            font-weight: bold;
            font-size: 14px;
        }

        .alerta-exito {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alerta-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .step-container {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 30px;
            overflow: hidden;
            background: #fff;
        }

        .step-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            font-weight: bold;
            color: var(--color-primario);
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .step-number {
            background: var(--color-primario);
            color: white;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 12px;
        }

        .step-body {
            padding: 25px;
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .form-group {
            flex: 1;
            min-width: 0;
        }

        label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 5px;
            color: #555;
        }

        input[type="text"],
        input[type="number"],
        input[type="email"],
        select,
        textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-family: inherit;
            font-size: 13px;
            background: #fafbfc;
            transition: border-color 0.2s;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--color-primario);
            outline: none;
            background: #fff;
        }

        textarea {
            resize: none;
            min-height: 80px;
        }

        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type=number] {
            -moz-appearance: textfield;
        }

        .search-box {
            position: relative;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #ccc;
            border-top: none;
            max-height: 250px;
            overflow-y: auto;
            z-index: 100;
            border-radius: 0 0 4px 4px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .search-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            font-size: 13px;
            color: #444;
        }

        .search-item:hover {
            background: var(--color-primario);
            color: white;
        }

        .btn-global {
            display: inline-block;
            padding: 12px 25px;
            font-size: 14px;
            font-weight: bold;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
        }

        .btn-primario {
            background: var(--color-primario);
            color: white;
        }

        .btn-primario:hover {
            opacity: 0.9;
        }

        .btn-secundario {
            background: #e2e8f0;
            color: #333;
            padding: 10px 15px;
            font-size: 13px;
        }

        .btn-add-service {
            background: #28a745;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            font-size: 13px;
            width: 100%;
        }

        .btn-add-service:hover {
            background: #218838;
        }

        .table-catalogo,
        .table-servicios {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: #fff;
            border: 1px solid var(--border-color);
            font-size: 13px;
        }

        .table-catalogo th,
        .table-catalogo td,
        .table-servicios th,
        .table-servicios td {
            border-bottom: 1px solid var(--border-color);
            padding: 8px;
            text-align: left;
            vertical-align: middle;
        }

        .table-catalogo th,
        .table-servicios th {
            background: #f8f9fa;
            font-weight: bold;
            text-transform: uppercase;
            color: #555;
            font-size: 11px;
        }

        .table-catalogo tbody tr:hover {
            background: #f1f5f9;
        }

        .btn-remove {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 11px;
        }

        .client-badge {
            background: #e3f2fd;
            color: #004085;
            padding: 12px 15px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid #b8daff;
            display: none;
            margin-top: 10px;
        }

        .total-box {
            font-size: 18px;
            font-weight: bold;
            text-align: right;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
            margin-top: 15px;
            border: 1px solid var(--border-color);
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 15px;
        }

        .page-btn {
            border: 1px solid #ddd;
            padding: 5px 10px;
            background: white;
            cursor: pointer;
            font-size: 12px;
            border-radius: 3px;
            font-weight: bold;
            color: var(--color-primario);
        }

        .page-btn.active {
            background: var(--color-primario);
            color: white;
            border-color: var(--color-primario);
            pointer-events: none;
        }

        .tabs-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #eee;
        }

        .tab-btn {
            padding: 10px 20px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            background: none;
            border-bottom: 3px solid transparent;
            color: #666;
        }

        .tab-btn.active {
            color: var(--color-primario);
            border-bottom: 3px solid var(--color-primario);
        }

        .client-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 13px;
        }

        .client-table th,
        .client-table td {
            border-bottom: 1px solid #eee;
            padding: 8px;
            text-align: left;
        }

        .client-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #555;
        }

        .client-table tr.cli-row:hover td {
            background: #f1f5f9;
            cursor: pointer;
        }

        .grid-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .grid-services {
            display: grid;
            grid-template-columns: 35% 65%;
            gap: 20px;
        }

        .card-datos {
            background: #e3f2fd;
            color: #004085;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #b8daff;
            display: none;
            position: relative;
        }

        .btn-close-card {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            font-weight: bold;
            line-height: 1;
            padding: 0;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-radius: 50%;
            border-top: 3px solid var(--color-primario);
            width: 16px;
            height: 16px;
            -webkit-animation: spin 1s linear infinite;
            animation: spin 1s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-right: 8px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
    <?php if (($temaActual ?? $_SESSION['tema_preferido'] ?? '') === 'oscuro'): ?>
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
                <div class="header-box">
                    <h2>Apertura de Orden de Trabajo</h2>
                    <a href="../inicio.php" class="btn-global btn-secundario">Volver</a>
                </div>

                <?php if ($mensaje): ?>
                    <div class="alerta <?php echo $tipoMensaje == 'exito' ? 'alerta-exito' : 'alerta-error'; ?>">
                        <?php echo $mensaje; ?></div><?php endif; ?>

                <form method="POST" id="formOT">
                    <div class="step-container">
                        <div class="step-header">
                            <div class="step-number">1</div> Identificación del Cliente y Vehículo
                        </div>
                        <div class="step-body">
                            <div class="tabs-container">
                                <button type="button" class="tab-btn active" id="tab_existente">Seleccionar
                                    Existente</button>
                                <button type="button" class="tab-btn" id="tab_nuevo">Crear Nuevo Cliente y
                                    Vehículo</button>
                            </div>

                            <div id="info_seleccion_activa" class="grid-2col"
                                style="margin-bottom: 15px; display: none;">
                                <div id="card_vehiculo_selected" class="card-datos">
                                    <button type="button" class="btn-close-card" onclick="deseleccionarVehiculo()"
                                        title="Quitar Vehículo">✖</button>
                                    <h4 style="margin:0 0 10px 0; color:#004085;">Vehículo Seleccionado</h4>
                                    <div id="card_vehiculo_content" style="font-size:14px;"></div>
                                    <input type="hidden" id="select_vehiculo" name="id_vehiculo" value="">
                                </div>

                                <div id="card_cliente_selected" class="card-datos">
                                    <h4 style="margin:0 0 10px 0; color:#004085;">Cliente Dueño</h4>
                                    <div id="card_cliente_content" style="font-size:14px;"></div>
                                    <input type="hidden" id="id_cliente_seleccionado" name="id_cliente" value="">
                                </div>
                            </div>

                            <div id="bloque_existente">
                                <div class="grid-2col">
                                    <div>
                                        <div class="form-group search-box" style="margin-bottom: 10px;">
                                            <label>Buscar Vehículo Registrado (Mínimo 3 caracteres)</label>
                                            <input type="text" id="buscar_vehiculo"
                                                placeholder="Escriba la patente, marca o modelo..." autocomplete="off">
                                            <div id="res_vehiculos" class="search-results"></div>
                                        </div>
                                        <div id="tabla_vehiculos_container">
                                            <table class="client-table" id="tabla_vehiculos_paginada">
                                                <thead>
                                                    <tr>
                                                        <th>Patente</th>
                                                        <th>Vehículo</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                            <div id="paginacion_vehiculos" class="pagination-container"></div>
                                        </div>
                                    </div>
                                    <div></div>
                                </div>
                            </div>

                            <div id="bloque_nuevo" style="display: none;">
                                <div class="grid-2col">
                                    <!-- NUEVO CLIENTE -->
                                    <div
                                        style="background: #fafbfc; padding: 15px; border: 1px solid #eee; border-radius: 6px;">
                                        <h4
                                            style="margin-top: 0; margin-bottom: 15px; color: var(--color-primario); font-size: 14px;">
                                            1. Nuevo Cliente</h4>
                                        <div id="form_nuevo_cliente">
                                            <div class="form-row">
                                                <div class="form-group" style="flex: 0 0 100px;">
                                                    <label>Tipo Doc.</label>
                                                    <select
                                                        id="c_tipo_doc"><?php foreach ($tiposDoc as $td)
                                                            echo "<option value='{$td['IDTipoDocumento']}'>{$td['tipoDocumento']}</option>"; ?></select>
                                                </div>
                                                <div class="form-group" style="flex: 1;"><label>N° Doc.</label><input
                                                        type="text" id="c_doc" autocomplete="off" placeholder="Opcional"></div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group"><label>Nombre *</label><input type="text"
                                                        id="c_nombre" maxlength="20"></div>
                                                <div class="form-group"><label>Apellido *</label><input type="text"
                                                        id="c_apellido" maxlength="20"></div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group" style="flex: 0 0 100px;">
                                                    <label>Tipo Tel.</label>
                                                    <select
                                                        id="c_tipo_tel"><?php foreach ($tiposTel as $tt)
                                                            echo "<option value='{$tt['IDTipoNumeroTelefono']}'>{$tt['tipoNumeroTelefono']}</option>"; ?></select>
                                                </div>
                                                <div class="form-group" style="flex: 1;"><label>Teléfono *</label><input
                                                        type="text" id="c_tel" autocomplete="off"></div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group"><label>Correo Electrónico *</label><input
                                                        type="email" id="c_email"></div>
                                            </div>
                                            <div style="text-align: right; margin-top: 10px;">
                                                <button type="button" id="btn_crear_c" class="btn-global btn-primario"
                                                    style="padding: 8px 15px;">Crear Cliente</button>
                                            </div>
                                            <div id="msg_crear_c"
                                                style="margin-top: 8px; display: none; padding: 8px; border-radius: 4px; font-weight: bold; font-size: 12px;">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- NUEVO VEHÍCULO -->
                                    <div
                                        style="background: #fafbfc; padding: 15px; border: 1px solid #eee; border-radius: 6px;">
                                        <h4
                                            style="margin-top: 0; margin-bottom: 15px; color: var(--color-primario); font-size: 14px;">
                                            2. Nuevo Vehículo Asociado</h4>
                                        <div id="form_nuevo_vehiculo" style="opacity: 0.5; pointer-events: none;">
                                            <div class="form-row">
                                                <div class="form-group" style="flex: 0 0 100px;"><label>Patente
                                                        *</label><input type="text" id="v_patente"
                                                        style="text-transform: uppercase;" maxlength="7"></div>
                                                <div class="form-group" style="flex: 1;"><label>Tipo *</label><input
                                                        type="text" id="v_tipo" placeholder="Ej: Auto"></div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group"><label>Marca *</label><input type="text"
                                                        id="v_marca"></div>
                                                <div class="form-group"><label>Modelo *</label><input type="text"
                                                        id="v_modelo"></div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group" style="flex: 2;"><label>Color *</label><input
                                                        type="text" id="v_color"></div>
                                                <div class="form-group" style="flex: 1;"><label>Año</label><input
                                                        type="number" id="v_anio"></div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group"><label>Tipo Combustible</label><input
                                                        type="text" id="v_combustible"></div>
                                                <div class="form-group"><label>N° Motor</label><input type="text"
                                                        id="v_motor"></div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group"><label>N° Chasis / VIN</label><input type="text"
                                                        id="v_chasis"></div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group"><label>Obs. Vehículo</label><textarea id="v_obs"
                                                        style="min-height: 60px;"></textarea></div>
                                            </div>
                                            <div style="text-align: right; margin-top: 10px;">
                                                <button type="button" id="btn_crear_v" class="btn-global btn-primario"
                                                    style="padding: 8px 15px;">Crear Vehículo</button>
                                            </div>
                                            <div id="msg_crear_v"
                                                style="margin-top: 8px; display: none; padding: 8px; border-radius: 4px; font-weight: bold; font-size: 12px;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="step-container" id="bloque_tecnico_vehiculo"
                        style="display: none; border-color: #ffeeba;">
                        <div class="step-header" style="background: #fff3cd; color: #856404; border-color: #ffeeba;">
                            <div class="step-number" style="background:#856404;">T</div> Verificación Técnica del
                            Vehículo
                        </div>
                        <div class="step-body">
                            <div class="form-row">
                                <div class="form-group"><label>Kilometraje Actual *</label><input type="number"
                                        name="km_ingreso" id="in_km" required value="<?php echo htmlspecialchars($_POST['km_ingreso'] ?? ''); ?>"></div>
                                <div class="form-group">
                                    <label>Nivel de Combustible *</label>
                                    <select name="nivel_combustible" required>
                                        <option value="Reserva">Reserva</option>
                                        <option value="1/4 Tanque">1/4 de Tanque</option>
                                        <option value="1/2 Tanque" selected>1/2 Tanque</option>
                                        <option value="3/4 Tanque">3/4 de Tanque</option>
                                        <option value="Lleno">Tanque Lleno</option>
                                    </select>
                                </div>
                                <div class="form-group"><label>N° de Motor</label><input type="text" name="upd_motor"
                                        id="in_motor" placeholder="Opcional" value="<?php echo htmlspecialchars($_POST['upd_motor'] ?? ''); ?>"></div>
                                <div class="form-group"><label>N° de Chasis / VIN</label><input type="text"
                                        name="upd_chasis" id="in_chasis" placeholder="Opcional" value="<?php echo htmlspecialchars($_POST['upd_chasis'] ?? ''); ?>"></div>
                            </div>
                        </div>
                    </div>

                    <div class="step-container">
                        <div class="step-header">
                            <div class="step-number">2</div> Asignación de Servicios
                        </div>
                        <div class="step-body">
                            <div class="grid-services">
                                <!-- Columna Izquierda: Catálogo -->
                                <div>
                                    <div class="form-group search-box" style="margin-bottom: 10px;">
                                        <label>Buscar en Catálogo de Servicios</label>
                                        <input type="text" id="buscar_servicio_input"
                                            placeholder="Filtrar servicios por nombre..." autocomplete="off">
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
                                    <div id="paginacion_catalogo" class="pagination-container" style="margin-top: 5px;">
                                    </div>
                                </div>

                                <!-- Columna Derecha: Asignados -->
                                <div>
                                    <h4
                                        style="margin-top: 0; margin-bottom: 5px; color: var(--color-primario); font-size: 13px;">
                                        Servicios Asignados a la Orden</h4>
                                    <table class="table-servicios" id="tabla_servicios_agregados"
                                        style="margin-top: 0;">
                                        <thead>
                                            <tr>
                                                <th style="width: 30%;">Servicio Seleccionado</th>
                                                <th style="width: 15%;">Costo Acordado ($)</th>
                                                <th style="width: 45%;">Detalles Adicionales del Procedimiento</th>
                                                <th style="width: 10%; text-align: center;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr id="row_vacia">
                                                <td colspan="4" style="text-align: center; color: #888; padding: 20px;">
                                                    No ha seleccionado ningún servicio para esta orden.</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <div class="total-box">
                                        Total: <span id="monto_total_display" style="color: var(--color-primario);">$
                                            0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="step-container">
                        <div class="step-header">
                            <div class="step-number">3</div> Configuraciones Finales
                        </div>
                        <div class="step-body">
                            <div class="form-row">
                                <div class="form-group" style="flex: 1;">
                                    <label>Nivel de Prioridad *</label>
                                    <select name="prioridad" required>
                                        <option value="7" selected>No Prioritario (Normal)</option>
                                        <option value="6">Prioritario (Urgente)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row" style="margin-top: 15px;">
                                <div class="form-group" style="flex: 1;">
                                    <label>Personal Asignado (Opcional, Mín. 3 caracteres)</label>
                                    <div class="search-box" style="display: flex; gap: 5px; position: relative;">
                                        <input type="text" id="buscar_personal_input"
                                            placeholder="Buscar empleado por nombre, apellido o DNI..."
                                            autocomplete="off" style="flex: 1; height: 40px; margin: 0;">
                                        <button type="button" id="btn_buscar_personal" class="btn-global btn-primario"
                                            style="padding: 0 15px; height: 40px; margin: 0;">Buscar</button>
                                        <div id="res_personal" class="search-results"
                                            style="top: 100%; left: 0; right: 0;"></div>
                                    </div>

                                    <div id="tabla_personal_container" style="margin-top: 10px;">
                                        <table class="client-table" id="tabla_personal_paginada">
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

                                    <input type="hidden" name="numeroDocumentoPersonal" id="hidden_personal_id"
                                        value="">

                                    <div id="badge_personal_selected" class="client-badge"
                                        style="display: none; justify-content: space-between; align-items: center; margin-top: 10px;">
                                        <div>
                                            <h4 style="margin: 0 0 5px 0; color: #004085; font-size: 13px;">Empleado
                                                Asignado:</h4>
                                            <span id="text_personal_selected"></span>
                                        </div>
                                        <button type="button" class="btn-remove" onclick="deseleccionarPersonal()"
                                            title="Quitar asignación"
                                            style="padding: 4px 8px; margin-left: 10px; border-radius: 50%; width: 24px; height: 24px; line-height: 1;">✖</button>
                                    </div>
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label>Observaciones Generales</label>
                                    <textarea name="observacion_general" placeholder=""
                                        style="height: 100%; min-height: 120px;"><?php echo htmlspecialchars($_POST['observacion_general'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="text-align: right; padding-bottom: 40px;">
                        <button type="submit" class="btn-global btn-primario"
                            style="font-size: 16px; padding: 15px 40px;">Procesar y Emitir Orden</button>
                    </div>
                </form>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const clientes = <?php echo json_encode($clientes); ?>;
                    const vehiculos = <?php echo json_encode($vehiculos); ?>;
                    const catalogoSrv = <?php echo json_encode($serviciosList); ?>;

                    // --- LÓGICA DE TABS ---
                    const tabExistente = document.getElementById('tab_existente');
                    const tabNuevo = document.getElementById('tab_nuevo');
                    const blqExistente = document.getElementById('bloque_existente');
                    const blqNuevo = document.getElementById('bloque_nuevo');

                    // Limitadores dinámicos para Tipo de Documento y Teléfono
                    const cTipoDoc = document.getElementById('c_tipo_doc');
                    const cDoc = document.getElementById('c_doc');
                    const cTipoTel = document.getElementById('c_tipo_tel');
                    const cTel = document.getElementById('c_tel');

                    function actualizarLimiteDoc(limpiar = true) {
                        const tipo = cTipoDoc.options[cTipoDoc.selectedIndex]?.text.toLowerCase() || '';
                        if (limpiar) cDoc.value = '';
                        if (tipo.includes('dni')) {
                            cDoc.maxLength = 8;
                            cDoc.oninput = function () { this.value = this.value.replace(/[^0-9]/g, '').slice(0, 8); };
                        } else if (tipo.includes('pasaporte')) {
                            cDoc.maxLength = 9;
                            cDoc.oninput = function () { this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 9); };
                        } else {
                            cDoc.maxLength = 15;
                            cDoc.oninput = function () { this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 15); };
                        }
                    }

                    function actualizarLimiteTel(limpiar = true) {
                        const tipo = cTipoTel.options[cTipoTel.selectedIndex]?.text.toLowerCase() || '';
                        if (limpiar) cTel.value = '';
                        if (tipo.includes('celular')) {
                            cTel.maxLength = 13;
                            cTel.oninput = function () { this.value = this.value.replace(/[^0-9]/g, '').slice(0, 13); };
                        } else if (tipo.includes('fijo')) {
                            cTel.maxLength = 11;
                            cTel.oninput = function () { this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11); };
                        } else {
                            cTel.maxLength = 15;
                            cTel.oninput = function () { this.value = this.value.replace(/[^0-9]/g, '').slice(0, 15); };
                        }
                    }

                    cTipoDoc.addEventListener('change', () => actualizarLimiteDoc(true));
                    cTipoTel.addEventListener('change', () => actualizarLimiteTel(true));
                    actualizarLimiteDoc(false);
                    actualizarLimiteTel(false);



                    tabExistente.addEventListener('click', () => {
                        tabExistente.classList.add('active'); tabNuevo.classList.remove('active');
                        if (hiddenIdCli.value && cardCli.style.display !== 'none') {
                            blqExistente.style.display = 'none';
                        } else {
                            blqExistente.style.display = 'block';
                        }
                        blqNuevo.style.display = 'none';
                    });
                    tabNuevo.addEventListener('click', () => {
                        tabNuevo.classList.add('active'); tabExistente.classList.remove('active');
                        blqNuevo.style.display = 'block'; blqExistente.style.display = 'none';
                        if (hiddenIdCli.value) deseleccionarCliente();
                    });

                    // --- LÓGICA DE VEHICULOS EXISTENTES (PAGINACIÓN Y BÚSQUEDA) ---
                    const inputVeh = document.getElementById('buscar_vehiculo');
                    const resVeh = document.getElementById('res_vehiculos');
                    const hiddenIdVeh = document.getElementById('select_vehiculo');
                    const hiddenIdCli = document.getElementById('id_cliente_seleccionado');
                    const bloqueTecnico = document.getElementById('bloque_tecnico_vehiculo');
                    const tbodyVehiculos = document.querySelector('#tabla_vehiculos_paginada tbody');
                    const paginacionVeh = document.getElementById('paginacion_vehiculos');

                    const cardVeh = document.getElementById('card_vehiculo_selected');
                    const cardVehContent = document.getElementById('card_vehiculo_content');
                    const cardCli = document.getElementById('card_cliente_selected');
                    const cardCliContent = document.getElementById('card_cliente_content');

                    let paginaVehActual = 1;
                    const limitVeh = 5;

                    function normalize(str) { return str ? str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase() : ''; }

                    function renderizarTablaVehiculos() {
                        tbodyVehiculos.innerHTML = '';
                        const inicio = (paginaVehActual - 1) * limitVeh;
                        const fin = inicio + limitVeh;
                        const paginaDatos = vehiculos.slice(inicio, fin);

                        if (paginaDatos.length === 0) {
                            tbodyVehiculos.innerHTML = '<tr><td colspan="2" style="text-align:center; color:#888;">No hay vehículos registrados.</td></tr>';
                            paginacionVeh.innerHTML = '';
                            return;
                        }

                        paginaDatos.forEach(v => {
                            const tr = document.createElement('tr');
                            tr.className = 'cli-row';
                            tr.innerHTML = `
                        <td><strong>${v.patente}</strong></td>
                        <td>${v.marca} ${v.modelo}</td>
                    `;
                            tr.onclick = () => seleccionarVehiculo(v);
                            tbodyVehiculos.appendChild(tr);
                        });
                        renderizarPaginacionVehiculos();
                    }

                    function renderizarPaginacionVehiculos() {
                        paginacionVeh.innerHTML = '';
                        const totalPaginas = Math.ceil(vehiculos.length / limitVeh);
                        if (totalPaginas <= 1) return;

                        let startPage = Math.max(1, paginaVehActual - 2);
                        let endPage = Math.min(totalPaginas, startPage + 4);
                        if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

                        if (startPage > 1) {
                            const btnFirst = document.createElement('button');
                            btnFirst.type = 'button'; btnFirst.className = 'page-btn'; btnFirst.textContent = '1..';
                            btnFirst.onclick = () => { paginaVehActual = 1; renderizarTablaVehiculos(); };
                            paginacionVeh.appendChild(btnFirst);
                        }

                        for (let i = startPage; i <= endPage; i++) {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = `page-btn ${i === paginaVehActual ? 'active' : ''}`;
                            btn.textContent = i;
                            btn.onclick = () => { paginaVehActual = i; renderizarTablaVehiculos(); };
                            paginacionVeh.appendChild(btn);
                        }

                        if (endPage < totalPaginas) {
                            const btnLast = document.createElement('button');
                            btnLast.type = 'button'; btnLast.className = 'page-btn'; btnLast.textContent = '..' + totalPaginas;
                            btnLast.onclick = () => { paginaVehActual = totalPaginas; renderizarTablaVehiculos(); };
                            paginacionVeh.appendChild(btnLast);
                        }
                    }

                    renderizarTablaVehiculos();

                    inputVeh.addEventListener('input', () => {
                        const val = normalize(inputVeh.value.trim());
                        resVeh.innerHTML = '';
                        if (val.length >= 3) {
                            const filtrados = vehiculos.filter(v =>
                                normalize(v.patente).includes(val) ||
                                normalize(v.marca).includes(val) ||
                                normalize(v.modelo).includes(val)
                            ).slice(0, 5);

                            if (filtrados.length > 0) {
                                filtrados.forEach(v => {
                                    const div = document.createElement('div');
                                    div.className = 'search-item';
                                    div.textContent = `${v.patente} - ${v.marca} ${v.modelo}`;
                                    div.onclick = () => seleccionarVehiculo(v);
                                    resVeh.appendChild(div);
                                });
                                resVeh.style.display = 'block';
                            } else {
                                resVeh.innerHTML = '<div class="search-item" style="color:#888;">Sin coincidencias.</div>';
                                resVeh.style.display = 'block';
                            }
                        } else {
                            resVeh.style.display = 'none';
                        }
                    });

                    document.addEventListener('click', (e) => {
                        if (!inputVeh.contains(e.target) && !resVeh.contains(e.target)) resVeh.style.display = 'none';
                    });

                    function seleccionarVehiculo(v) {
                        inputVeh.value = '';
                        hiddenIdVeh.value = v.IDVehiculo;

                        const clienteDueno = clientes.find(c => c.IDCliente == v.IDCliente);

                        if (clienteDueno) {
                            hiddenIdCli.value = clienteDueno.IDCliente;
                            cardCliContent.innerHTML = `<strong>Nombre:</strong> ${clienteDueno.apellido}, ${clienteDueno.nombre}<br><strong>DNI:</strong> ${clienteDueno.numeroDocumentoCliente}<br><strong>Teléfono:</strong> ${clienteDueno.telefono || 'N/A'}`;
                        } else {
                            hiddenIdCli.value = '';
                            cardCliContent.innerHTML = `<span style="color:red;">Cliente no encontrado o inactivo.</span>`;
                        }

                        cardVehContent.innerHTML = `<strong>Patente:</strong> ${v.patente}<br><strong>Vehículo:</strong> ${v.marca} ${v.modelo}<br><strong>Año:</strong> ${v.anioFabricacion || 'N/A'}`;

                        document.getElementById('info_seleccion_activa').style.display = 'grid';
                        cardVeh.style.display = 'block';
                        cardCli.style.display = 'block';

                        resVeh.style.display = 'none';
                        document.getElementById('bloque_existente').style.display = 'none';

                        document.getElementById('in_km').value = '';
                        document.getElementById('in_motor').value = v.numeroMotor || '';
                        document.getElementById('in_chasis').value = v.numeroChasis || '';
                        bloqueTecnico.style.display = 'block';
                    }

                    window.deseleccionarVehiculo = function () {
                        hiddenIdVeh.value = '';
                        hiddenIdCli.value = '';
                        document.getElementById('info_seleccion_activa').style.display = 'none';
                        cardVeh.style.display = 'none';
                        cardCli.style.display = 'none';

                        if (tabExistente.classList.contains('active')) {
                            document.getElementById('bloque_existente').style.display = 'block';
                        }

                        inputVeh.value = '';
                        bloqueTecnico.style.display = 'none';

                        document.getElementById('form_nuevo_cliente').style.display = 'block';
                        document.getElementById('form_nuevo_vehiculo').style.opacity = '0.5';
                        document.getElementById('form_nuevo_vehiculo').style.pointerEvents = 'none';
                    };

                    window.deseleccionarCliente = window.deseleccionarVehiculo;

                    // --- LÓGICA DE CREAR NUEVO CLIENTE Y VEHÍCULO ---
                    const btnCrearC = document.getElementById('btn_crear_c');
                    const btnCrearV = document.getElementById('btn_crear_v');

                    btnCrearC.addEventListener('click', () => {
                        const doc = document.getElementById('c_doc').value.trim();
                        const nom = document.getElementById('c_nombre').value.trim();
                        const ape = document.getElementById('c_apellido').value.trim();
                        const tel = document.getElementById('c_tel').value.trim();
                        const email = document.getElementById('c_email').value.trim();

                        if (!nom || !ape || !tel || !email) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Atención',
                                text: 'Por favor, complete todos los campos obligatorios del cliente (Nombre, Apellido, Teléfono, Correo).',
                                confirmButtonColor: 'var(--color-primario)'
                            });
                            return;
                        }

                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(email)) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Atención',
                                text: 'El correo electrónico ingresado no tiene un formato válido.',
                                confirmButtonColor: 'var(--color-primario)'
                            });
                            return;
                        }

                        btnCrearC.innerHTML = '<span class="spinner"></span> Creando...';
                        btnCrearC.disabled = true;
                        const msgBox = document.getElementById('msg_crear_c');
                        msgBox.style.display = 'none';

                        const formData = new FormData();
                        formData.append('accion', 'crear_cliente');
                        formData.append('c_tipo_doc', document.getElementById('c_tipo_doc').value);
                        formData.append('c_doc', doc);
                        formData.append('c_nombre', nom);
                        formData.append('c_apellido', ape);
                        formData.append('c_tipo_tel', document.getElementById('c_tipo_tel').value);
                        formData.append('c_tel', document.getElementById('c_tel').value);
                        formData.append('c_email', document.getElementById('c_email').value);

                        fetch('ajax_crear_cliente_vehiculo.php', { method: 'POST', body: formData })
                            .then(res => res.json())
                            .then(data => {
                                btnCrearC.innerHTML = 'Crear Cliente';
                                btnCrearC.disabled = false;
                                if (data.success) {
                                    const newCli = { IDCliente: data.IDCliente, numeroDocumentoCliente: doc, nombre: nom, apellido: ape };
                                    clientes.push(newCli);

                                    document.getElementById('form_nuevo_cliente').style.display = 'none';
                                    hiddenIdCli.value = data.IDCliente;
                                    cardCliContent.innerHTML = `<strong>Nombre:</strong> ${ape}, ${nom}<br><strong>DNI:</strong> ${doc}`;

                                    document.getElementById('info_seleccion_activa').style.display = 'grid';
                                    cardCli.style.display = 'block';

                                    document.getElementById('form_nuevo_vehiculo').style.opacity = '1';
                                    document.getElementById('form_nuevo_vehiculo').style.pointerEvents = 'auto';

                                    Swal.fire({ icon: 'success', title: '¡Cliente Creado!', text: data.mensaje, confirmButtonColor: '#28a745' });
                                } else {
                                    msgBox.className = 'alerta-error';
                                    msgBox.innerText = data.mensaje;
                                    msgBox.style.display = 'block';
                                }
                            }).catch(err => {
                                btnCrearC.innerHTML = 'Crear Cliente';
                                btnCrearC.disabled = false;
                                msgBox.className = 'alerta-error';
                                msgBox.innerText = 'Error de conexión.';
                                msgBox.style.display = 'block';
                            });
                    });

                    btnCrearV.addEventListener('click', () => {
                        const idCli = hiddenIdCli.value;
                        if (!idCli) { 
                            Swal.fire({
                                icon: 'warning',
                                title: 'Atención',
                                text: 'Debe crear el cliente primero.',
                                confirmButtonColor: 'var(--color-primario)'
                            });
                            return; 
                        }

                        const pat = document.getElementById('v_patente').value.trim().toUpperCase();
                        const tipo = document.getElementById('v_tipo').value.trim();
                        const mar = document.getElementById('v_marca').value.trim();
                        const mod = document.getElementById('v_modelo').value.trim();
                        const col = document.getElementById('v_color').value.trim();

                        if (!pat || !tipo || !mar || !mod || !col) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Atención',
                                text: 'Complete Patente, Tipo, Marca, Modelo y Color.',
                                confirmButtonColor: 'var(--color-primario)'
                            });
                            return;
                        }

                        btnCrearV.innerHTML = '<span class="spinner"></span> Creando...';
                        btnCrearV.disabled = true;
                        const msgBox = document.getElementById('msg_crear_v');
                        msgBox.style.display = 'none';

                        const formData = new FormData();
                        formData.append('accion', 'crear_vehiculo');
                        formData.append('id_cliente', idCli);
                        formData.append('v_patente', pat);
                        formData.append('v_tipo', tipo);
                        formData.append('v_marca', mar);
                        formData.append('v_modelo', mod);
                        formData.append('v_color', col);
                        formData.append('v_anio', document.getElementById('v_anio').value);
                        formData.append('v_combustible', document.getElementById('v_combustible').value);
                        formData.append('v_motor', document.getElementById('v_motor').value);
                        formData.append('v_chasis', document.getElementById('v_chasis').value);
                        formData.append('v_obs', document.getElementById('v_obs').value);

                        fetch('ajax_crear_cliente_vehiculo.php', { method: 'POST', body: formData })
                            .then(res => res.json())
                            .then(data => {
                                btnCrearV.innerHTML = 'Crear Vehículo';
                                btnCrearV.disabled = false;
                                if (data.success) {
                                    vehiculos.push({ IDVehiculo: data.IDVehiculo, IDCliente: idCli, patente: pat, marca: mar, modelo: mod, numeroMotor: document.getElementById('v_motor').value, numeroChasis: document.getElementById('v_chasis').value });

                                    cardVehContent.innerHTML = `<strong>Patente:</strong> ${pat}<br><strong>Vehículo:</strong> ${mar} ${mod}`;
                                    cardVeh.style.display = 'block';

                                    // Switch to select mode to finalize order
                                    tabExistente.click();

                                    // We need to auto-select this vehicle in the select
                                    selectVeh.innerHTML = `<option value="${data.IDVehiculo}">${pat} - ${mar} ${mod}</option>`;
                                    selectVeh.value = data.IDVehiculo;
                                    contSelectVeh.style.display = 'block';
                                    selectVeh.dispatchEvent(new Event('change'));

                                    // Clear inputs
                                    document.querySelectorAll('#bloque_nuevo input').forEach(inp => inp.value = '');
                                    document.getElementById('form_nuevo_cliente').style.display = 'block';
                                    document.getElementById('form_nuevo_vehiculo').style.opacity = '0.5';
                                    document.getElementById('form_nuevo_vehiculo').style.pointerEvents = 'none';

                                    Swal.fire({ icon: 'success', title: '¡Vehículo Creado!', text: data.mensaje, confirmButtonColor: '#28a745' });
                                } else {
                                    msgBox.className = 'alerta-error';
                                    msgBox.innerText = data.mensaje;
                                    msgBox.style.display = 'block';
                                }
                            }).catch(err => {
                                btnCrearV.innerHTML = 'Crear Vehículo';
                                btnCrearV.disabled = false;
                                msgBox.className = 'alerta-error';
                                msgBox.innerText = 'Error de conexión.';
                                msgBox.style.display = 'block';
                            });
                    });

                    // --- CATÁLOGO DE SERVICIOS Y TOTALES (5 ITEMS POR PÁGINA) ---
                    const tbodyCatalogo = document.querySelector('#tabla_catalogo tbody');
                    const inputSrvBusqueda = document.getElementById('buscar_servicio_input');
                    const paginacionCat = document.getElementById('paginacion_catalogo');
                    const tbodyAgregados = document.querySelector('#tabla_servicios_agregados tbody');

                    let srvFiltrados = [...catalogoSrv];
                    let paginaSrv = 1;
                    const limitSrv = 5; // Paginación de 5 en 5 solicitada
                    let serviciosAgregados = 0;

                    function renderizarCatalogo() {
                        tbodyCatalogo.innerHTML = '';
                        const inicio = (paginaSrv - 1) * limitSrv;
                        const fin = inicio + limitSrv;
                        const paginaDatos = srvFiltrados.slice(inicio, fin);

                        if (paginaDatos.length === 0) {
                            tbodyCatalogo.innerHTML = '<tr><td colspan="3" style="text-align:center; color:#888;">No hay servicios coincidentes.</td></tr>';
                            paginacionCat.innerHTML = '';
                            return;
                        }

                        paginaDatos.forEach(s => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                        <td><strong>${s.nombreServicio}</strong></td>
                        <td>$ ${parseFloat(s.costoServicio).toLocaleString('es-AR', { minimumFractionDigits: 2 })}</td>
                        <td style="text-align: center;">
                            <button type="button" class="btn-add-service" style="margin-top:0;" onclick="agregarServicio(${s.IDServicio}, '${s.nombreServicio.replace(/'/g, "\\'")}', ${s.costoServicio})">Agregar</button>
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

                    window.agregarServicio = function (id, nombre, costo) {
                        if (serviciosAgregados === 0) { document.getElementById('row_vacia').style.display = 'none'; }

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                    <td>
                        <strong style="color:var(--color-primario);">${nombre}</strong>
                        <input type="hidden" name="servicios_id[]" value="${id}">
                    </td>
                    <td><input type="number" step="0.01" name="servicios_costo[]" value="${costo}" required style="background:#fff;" oninput="calcularTotal()"></td>
                    <td><textarea name="servicios_obs[]" style="min-height: 45px; background:#fff; padding:8px;" placeholder=""></textarea></td>
                    <td style="text-align: center;"><button type="button" class="btn-remove" onclick="eliminarFila(this)" title="Quitar Servicio" style="border-radius: 50%; width: 24px; height: 24px; padding: 0; line-height: 1;">✖</button></td>
                `;
                        tbodyAgregados.appendChild(tr);
                        serviciosAgregados++;
                        calcularTotal();
                    };

                    window.eliminarFila = function (btn) {
                        btn.closest('tr').remove();
                        serviciosAgregados--;
                        if (serviciosAgregados === 0) { document.getElementById('row_vacia').style.display = 'table-row'; }
                        calcularTotal();
                    };

                    window.calcularTotal = function () {
                        let total = 0;
                        document.querySelectorAll('input[name="servicios_costo[]"]').forEach(input => {
                            total += parseFloat(input.value) || 0;
                        });
                        document.getElementById('monto_total_display').innerText = '$ ' + total.toLocaleString('es-AR', { minimumFractionDigits: 2 });
                    };

                    renderizarCatalogo();

                    document.getElementById('formOT').addEventListener('submit', (e) => {
                        if (serviciosAgregados === 0) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'warning',
                                title: 'Atención',
                                text: 'Debe incorporar al menos un servicio operativo a la orden.',
                                confirmButtonColor: 'var(--color-primario)'
                            });
                        }
                    });

                    // Paginación y Búsqueda de Personal
                    const personalList = <?php echo json_encode($listaPersonal); ?>;
                    const inputPersonal = document.getElementById('buscar_personal_input');
                    const resPersonal = document.getElementById('res_personal');
                    const tbodyPersonal = document.querySelector('#tabla_personal_paginada tbody');
                    const paginacionPersonal = document.getElementById('paginacion_personal');
                    const hiddenPersonal = document.getElementById('hidden_personal_id');
                    const badgePersonal = document.getElementById('badge_personal_selected');
                    const textPersonal = document.getElementById('text_personal_selected');
                    const btnBuscarPersonal = document.getElementById('btn_buscar_personal');

                    let paginaPersonalActual = 1;
                    const limitPersonal = 5;

                    function renderizarTablaPersonal() {
                        tbodyPersonal.innerHTML = '';
                        const inicio = (paginaPersonalActual - 1) * limitPersonal;
                        const fin = inicio + limitPersonal;
                        const paginaDatos = personalList.slice(inicio, fin);

                        if (paginaDatos.length === 0) {
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
                            btnLast.type = 'button'; btnLast.className = 'page-btn'; btnLast.textContent = '..' + totalPaginas;
                            btnLast.onclick = () => { paginaPersonalActual = totalPaginas; renderizarTablaPersonal(); };
                            paginacionPersonal.appendChild(btnLast);
                        }
                    }

                    renderizarTablaPersonal();

                    function procesarBusquedaPersonal() {
                        if (!inputPersonal) return;
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
                        if (inputPersonal && !inputPersonal.contains(e.target) && resPersonal && !resPersonal.contains(e.target)) resPersonal.style.display = 'none';
                    });

                    window.seleccionarPersonal = function (p) {
                        hiddenPersonal.value = p.numeroDocumentoPersonal;
                        textPersonal.textContent = `${p.apellido}, ${p.nombre} (DNI: ${p.numeroDocumentoPersonal})`;
                        badgePersonal.style.display = 'flex';
                        inputPersonal.value = '';
                        resPersonal.style.display = 'none';
                    };

                    window.deseleccionarPersonal = function () {
                        hiddenPersonal.value = '';
                        badgePersonal.style.display = 'none';
                        textPersonal.textContent = '';
                    };

                    document.addEventListener('click', (e) => {
                        if (inputPersonal && !inputPersonal.contains(e.target) && resPersonal && !resPersonal.contains(e.target)) resPersonal.style.display = 'none';
                    });
                });
            </script>
    </div>
    </main>
    </div>
</body>

</html>