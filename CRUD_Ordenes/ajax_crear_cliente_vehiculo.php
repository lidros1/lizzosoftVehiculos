<?php
/**
 * Lizzosoft Vehículos - AJAX Endpoint para crear Cliente y Vehículo desde Nueva Orden
 */
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

$empresa_id = (int)$_SESSION['empresa_id_usuario'];
$sucursal_id = (int)$_SESSION['sucursal_id'];

if (!$empresa_id || !$sucursal_id) {
    echo json_encode(['success' => false, 'mensaje' => 'Sesión inválida.']);
    exit;
}

$conexion = obtenerConexion();

$accion = $_POST['accion'] ?? '';

try {
    if ($accion === 'crear_cliente') {
        $c_tipo_doc = (int)($_POST['c_tipo_doc'] ?? 0);
        $c_doc      = trim($_POST['c_doc'] ?? '');
        $c_nombre   = trim($_POST['c_nombre'] ?? '');
        $c_apellido = trim($_POST['c_apellido'] ?? '');
        $c_tipo_tel = (int)($_POST['c_tipo_tel'] ?? 0);
        $c_tel      = (int)($_POST['c_tel'] ?? 0);
        $c_email    = trim($_POST['c_email'] ?? '');

        if (empty($c_nombre) || empty($c_apellido) || empty($c_tel) || empty($c_email)) {
            throw new Exception("Faltan datos obligatorios del cliente (Nombre, Apellido, Teléfono o Correo).");
        }
        
        if (!filter_var($c_email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $c_email)) {
            throw new Exception("El formato del correo electrónico ingresado es inválido (ejemplo: usuario@dominio.com).");
        }

        if (!empty($c_doc)) {
            $stmtCheckC = $conexion->prepare("SELECT IDCliente FROM clientes WHERE numeroDocumentoCliente = ? AND empresa_id = ? AND sucursal_id = ?");
            $stmtCheckC->execute([$c_doc, $empresa_id, $sucursal_id]);
            if ($stmtCheckC->fetch()) {
                throw new Exception("El N° de Documento ya se encuentra registrado para esta sucursal.");
            }
        }

        $c_doc_final = empty($c_doc) ? null : $c_doc;
        
        $sqlC = "INSERT INTO clientes (nombre, apellido, IDTipoDocumento, numeroDocumentoCliente, IDTipoNumeroTelefono, telefono, email, estado, empresa_id, sucursal_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'Activo', ?, ?)";
        $stmtC = $conexion->prepare($sqlC);
        $stmtC->execute([$c_nombre, $c_apellido, $c_tipo_doc, $c_doc_final, $c_tipo_tel, $c_tel, $c_email, $empresa_id, $sucursal_id]);
        $idClienteDestino = $conexion->lastInsertId();

        echo json_encode([
            'success' => true,
            'mensaje' => 'Cliente creado exitosamente.',
            'IDCliente' => $idClienteDestino
        ]);
        exit;

    } elseif ($accion === 'crear_vehiculo') {
        $idClienteDestino = (int)($_POST['id_cliente'] ?? 0);
        $v_patente  = trim(strtoupper($_POST['v_patente'] ?? ''));
        $v_tipo     = trim($_POST['v_tipo'] ?? '');
        $v_marca    = trim($_POST['v_marca'] ?? '');
        $v_modelo   = trim($_POST['v_modelo'] ?? '');
        $v_color    = trim($_POST['v_color'] ?? '');
        $v_combustible = trim($_POST['v_combustible'] ?? '');
        $v_motor    = trim($_POST['v_motor'] ?? '');
        $v_chasis   = trim($_POST['v_chasis'] ?? '');
        $v_anio     = (int)($_POST['v_anio'] ?? 0);
        $v_obs      = trim($_POST['v_obs'] ?? '');

        if ($idClienteDestino <= 0) {
            throw new Exception("Debe seleccionar o crear un cliente primero.");
        }

        if (empty($v_patente) || empty($v_tipo) || empty($v_marca) || empty($v_modelo) || empty($v_color)) {
            throw new Exception("Faltan datos obligatorios del vehículo.");
        }

        if (preg_match('/[\s-]/', $v_patente)) {
            throw new Exception("La patente no debe contener espacios ni guiones.");
        } elseif (!preg_match('/^([A-Z]{3}[0-9]{3}|[A-Z]{2}[0-9]{3}[A-Z]{2})$/i', $v_patente)) {
            throw new Exception("Formato de patente inválido. Debe ser AAA000 o AA000AA.");
        }

        $stmtCheckV = $conexion->prepare("SELECT IDVehiculo FROM vehiculos WHERE patente = ? AND empresa_id = ? AND sucursal_id = ?");
        $stmtCheckV->execute([$v_patente, $empresa_id, $sucursal_id]);
        if ($stmtCheckV->fetch()) {
            throw new Exception("La patente ya se encuentra registrada para esta sucursal.");
        }

        $sqlV = "INSERT INTO vehiculos (patente, tipoVehiculo, marca, modelo, colorVehiculo, anioFabricacion, tipoCombustible, numeroMotor, numeroChasis, observacionVehiculo, IDCliente, estado, empresa_id, sucursal_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Activo', ?, ?)";
        $stmtV = $conexion->prepare($sqlV);
        $stmtV->execute([
            $v_patente, $v_tipo, $v_marca, $v_modelo, $v_color, $v_anio, $v_combustible,
            $v_motor, $v_chasis, $v_obs, $idClienteDestino, $empresa_id, $sucursal_id
        ]);
        $idVehiculoDestino = $conexion->lastInsertId();

        echo json_encode([
            'success' => true,
            'mensaje' => 'Vehículo creado exitosamente.',
            'IDVehiculo' => $idVehiculoDestino
        ]);
        exit;
    } else {
        throw new Exception("Acción no válida.");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'mensaje' => $e->getMessage()]);
}
