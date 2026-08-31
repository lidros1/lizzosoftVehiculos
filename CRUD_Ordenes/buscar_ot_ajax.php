<?php
require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

header('Content-Type: application/json');

$empresa_id = (int)$_SESSION['empresa_id'];
$sucursal_id = (int)$_SESSION['sucursal_id'];
$busqueda = trim($_GET['q'] ?? '');

if (strlen($busqueda) < 3) {
    echo json_encode([]);
    exit;
}

$conexion = obtenerConexion();

$terminos = array_filter(explode(' ', $busqueda));
$whereOT = "WHERE rs.empresa_id = :emp_id AND rs.sucursal_id = :suc_id";
$params = [':emp_id' => $empresa_id, ':suc_id' => $sucursal_id];

$indice = 0;
foreach ($terminos as $termino) {
    $cadenaVirtual = "CONCAT_WS(' ', LPAD(CAST(rs.numeroOrdenTrabajo AS CHAR), 6, '0'), CAST(rs.numeroOrdenTrabajo AS CHAR), v.patente, v.marca, v.modelo, CAST(c.numeroDocumentoCliente AS CHAR), c.nombre, c.apellido, es.nombreEstadoSolicitud)";
    $whereOT .= " AND $cadenaVirtual LIKE :q_$indice";
    $params[":q_$indice"] = '%' . $termino . '%';
    $indice++;
}

try {
    $sql = "SELECT rs.IDRegistroServicio, rs.numeroOrdenTrabajo, rs.IDEstado, v.patente, c.nombre, c.apellido, es.nombreEstadoSolicitud
            FROM registrosservicios rs
            JOIN vehiculos v ON rs.IDVehiculo = v.IDVehiculo
            JOIN clientes c ON v.IDCliente = c.IDCliente
            JOIN estadossolicitud es ON rs.IDEstado = es.IDEstadoSolicitud
            $whereOT
            ORDER BY rs.numeroOrdenTrabajo DESC
            LIMIT 5";

    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($resultados);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
