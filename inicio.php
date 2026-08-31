<?php
/**
 * Lizzosoft Vehículos - Dashboard Principal
 * Ubicación: lizzosoft_vehiculos/inicio.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/Login/verificar_sesion.php';
require_once __DIR__ . '/Conexion/Conexion.php';
require_once __DIR__ . '/Conexion/Mailer.php'; 

$config     = $_SESSION['cliente_config'];
$apariencia = $config['apariencia'];
$empresa_id = (int)$_SESSION['empresa_id'];
$sucursal_id= (int)$_SESSION['sucursal_id'];
$idRol      = (int)$_SESSION['IDRol'];
$idUsuario  = (int)($_SESSION['IDUsuario'] ?? 0);
$areas      = $_SESSION['areas_permitidas'] ?? [];
$temaActual = $_SESSION['tema_preferido'] ?? 'claro';

$labelRodado = $config['labels']['vehiculo_plural'] ?? 'Vehículos';
$conexion = obtenerConexion();

// -------------------------------------------------------------
// VALIDACIÓN DE ROLES Y VISIBILIDAD DE ÓRDENES
// -------------------------------------------------------------
$verTodas = false;
$esMecanico = false;
$docUsuarioActual = '';

$funciones_permitidas_ot = $_SESSION['funciones_permitidas'][1] ?? [];
$es_admin = ($idRol === 1 || $idRol === 3);
$puede_crear_ot = $es_admin || in_array(1, $funciones_permitidas_ot);
$puede_editar_ot = $es_admin || in_array(2, $funciones_permitidas_ot);

try {
    $stmtPers = $conexion->prepare("SELECT numeroDocumentoPersonal FROM personal WHERE IDUsuario = ? AND empresa_id = ? AND estado = 'Activo'");
    $stmtPers->execute([$idUsuario, $empresa_id]);
    $docUsuarioActual = $stmtPers->fetchColumn();

    $stmtRol = $conexion->prepare("SELECT nombreRol FROM roles WHERE IDRol = ?");
    $stmtRol->execute([$idRol]);
    $nombreRol = strtolower(trim((string)$stmtRol->fetchColumn()));

    if ($idRol === 1 || $idRol === 3) {
        $verTodas = true;
    } elseif ($idRol === 2 || strpos($nombreRol, 'mecánico') !== false || strpos($nombreRol, 'mecanico') !== false) {
        $esMecanico = true;
        $verTodas = false;
    } elseif ($idRol === 4 || strpos($nombreRol, 'especial') !== false) {
        $stmtPerm = $conexion->prepare("SELECT 1 FROM permisosusuarios WHERE IDUsuario = ? AND IDAreaSistema = 1 AND IDFuncion = 10 AND estado = 'Activo'");
        $stmtPerm->execute([$idUsuario]);
        $verTodas = $stmtPerm->fetchColumn() ? true : false;
    }
    
    $puedeCancelarOT = false;
    if ($idRol === 1 || $idRol === 3) {
        $puedeCancelarOT = true;
    } elseif ($idRol === 4) {
        $stmtPerm = $conexion->prepare("SELECT 1 FROM permisosusuarios WHERE IDUsuario = ? AND IDAreaSistema = 1 AND IDFuncion = 13 AND estado = 'Activo'");
        $stmtPerm->execute([$idUsuario]);
        $puedeCancelarOT = $stmtPerm->fetchColumn() ? true : false;
    }
} catch (Exception $e) {}


try { $conexion->exec("UPDATE registrosservicios SET IDEstado = 4 WHERE IDEstado = 3"); } catch (Exception $e) {}

// Transición de Estados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_estado'])) {
    $idOT = (int)$_POST['id_orden'];
    $nuevoEst = (int)$_POST['nuevo_estado'];
    
    if ($idOT > 0 && $nuevoEst >= 1 && $nuevoEst <= 8) {
        try {
            $motivoQuery = "";
            $motivoParams = [];
            
            if ($nuevoEst === 8 && isset($_POST['motivo_anulacion'])) {
                $motivoQuery = ", motivoAnulacion = ?";
                $motivoParams[] = trim($_POST['motivo_anulacion']);
            }
            
            if (!$verTodas) {
                $sqlU = "UPDATE registrosservicios SET IDEstado = ? $motivoQuery WHERE IDRegistroServicio = ? AND empresa_id = ? AND sucursal_id = ? AND numeroDocumentoPersonal = ?";
                $paramsU = array_merge([$nuevoEst], $motivoParams, [$idOT, $empresa_id, $sucursal_id, $docUsuarioActual]);
            } else {
                $sqlU = "UPDATE registrosservicios SET IDEstado = ? $motivoQuery WHERE IDRegistroServicio = ? AND empresa_id = ? AND sucursal_id = ?";
                $paramsU = array_merge([$nuevoEst], $motivoParams, [$idOT, $empresa_id, $sucursal_id]);
            }
            
            $stmtU = $conexion->prepare($sqlU);
            $stmtU->execute($paramsU);

            if ($nuevoEst === 2) {
                $conexion->prepare("UPDATE registrosservicios SET fechaInicio = NOW() WHERE IDRegistroServicio = ? AND fechaInicio IS NULL")->execute([$idOT]);
            } elseif ($nuevoEst === 4 || $nuevoEst === 5) {
                $conexion->prepare("UPDATE registrosservicios SET fechaFin = NOW() WHERE IDRegistroServicio = ? AND fechaFin IS NULL")->execute([$idOT]);
            }

            if ($stmtU->rowCount() > 0 && $nuevoEst === 4) {
                $stmtMail = $conexion->prepare("
                    SELECT c.email, c.nombre, v.patente, rs.numeroOrdenTrabajo 
                    FROM registrosservicios rs 
                    JOIN vehiculos v ON rs.IDVehiculo = v.IDVehiculo 
                    JOIN clientes c ON v.IDCliente = c.IDCliente 
                    WHERE rs.IDRegistroServicio = ?
                ");
                $stmtMail->execute([$idOT]);
                $clienteMail = $stmtMail->fetch(PDO::FETCH_ASSOC);

                if ($clienteMail && !empty($clienteMail['email'])) {
                    $nroOrdenStr = str_pad($clienteMail['numeroOrdenTrabajo'], 8, '0', STR_PAD_LEFT);
                    enviarAvisoFinalizadoNE($clienteMail['email'], $clienteMail['nombre'], $nroOrdenStr, $clienteMail['patente'], $config);
                }
            }
            $queryString = $_SERVER['QUERY_STRING'] ?? '';
            header("Location: inicio.php" . ($queryString ? '?' . $queryString : ''));
            exit;
        } catch (PDOException $e) {
            $errorOT = "Error al actualizar el estado de la orden.";
        }
    }
}

$defaultHasta = date('Y-m-d');
$defaultDesde = date('Y-m-d', strtotime('-3 months'));

$busquedaOT    = trim($_GET['buscar_ot'] ?? '');
$f_estado      = trim($_GET['f_estado'] ?? '1');
$f_prio        = trim($_GET['f_prio'] ?? '');
$f_fecha_desde = trim($_GET['f_fecha_desde'] ?? $defaultDesde);
$f_fecha_hasta = trim($_GET['f_fecha_hasta'] ?? $defaultHasta);

if (!strtotime($f_fecha_desde)) $f_fecha_desde = $defaultDesde;
if (!strtotime($f_fecha_hasta)) $f_fecha_hasta = $defaultHasta;

$limite = (isset($_GET['limite']) && in_array((int)$_GET['limite'], [5, 10, 50])) ? (int)$_GET['limite'] : 5;
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$offset = ($pagina - 1) * $limite;

$whereOT = "WHERE rs.empresa_id = :empresa_id AND rs.sucursal_id = :sucursal_id";
$paramsOT = [':empresa_id' => $empresa_id, ':sucursal_id' => $sucursal_id];

if (!$verTodas) {
    if ($docUsuarioActual) {
        $whereOT .= " AND rs.numeroDocumentoPersonal = :doc_personal";
        $paramsOT[':doc_personal'] = $docUsuarioActual;
    } else {
        $whereOT .= " AND 1 = 0"; 
    }
}

$whereOT .= " AND DATE(rs.fechaRegistroServicio) BETWEEN :f_fecha_desde AND :f_fecha_hasta";
$paramsOT[':f_fecha_desde'] = $f_fecha_desde;
$paramsOT[':f_fecha_hasta'] = $f_fecha_hasta;

if ($f_estado === 'todas') {
    // Excluir canceladas del tablero principal para que no se trabaje sobre ellas
    $whereOT .= " AND rs.IDEstado != 8";
} elseif ($f_estado !== '') {
    $whereOT .= " AND rs.IDEstado = :f_estado";
    $paramsOT[':f_estado'] = (int)$f_estado;
}

if ($f_prio !== '') {
    $whereOT .= " AND rs.prioridad = :f_prio";
    $paramsOT[':f_prio'] = (int)$f_prio;
}

if ($busquedaOT !== '') {
    $terminos = array_filter(explode(' ', $busquedaOT));
    $indice = 0;
    foreach ($terminos as $termino) {
        $cadenaVirtual = "CONCAT_WS(' ', LPAD(CAST(rs.numeroOrdenTrabajo AS CHAR), 6, '0'), CAST(rs.numeroOrdenTrabajo AS CHAR), v.patente, v.marca, v.modelo, CAST(c.numeroDocumentoCliente AS CHAR), c.nombre, c.apellido)";
        $whereOT .= " AND $cadenaVirtual LIKE :q_termino_$indice";
        $paramsOT[":q_termino_$indice"] = '%' . $termino . '%';
        $indice++;
    }
}

$ordenes = [];
$totalPaginas = 1;

try {
    $sqlCount = "SELECT COUNT(*) FROM registrosservicios rs INNER JOIN vehiculos v ON rs.IDVehiculo = v.IDVehiculo INNER JOIN clientes c ON v.IDCliente = c.IDCliente $whereOT";
    $stmtCount = $conexion->prepare($sqlCount);
    $stmtCount->execute($paramsOT);
    $totalRegistros = $stmtCount->fetchColumn();
    $totalPaginas = ceil($totalRegistros / $limite);

    $sqlOT = "SELECT rs.IDRegistroServicio, rs.numeroOrdenTrabajo, rs.fechaRegistroServicio, rs.prioridad, rs.IDEstado, rs.observacionGeneral, es.nombreEstadoSolicitud, v.patente, c.nombre AS nombreCliente, c.apellido AS apellidoCliente, c.numeroDocumentoCliente 
              FROM registrosservicios rs 
              INNER JOIN vehiculos v ON rs.IDVehiculo = v.IDVehiculo 
              INNER JOIN clientes c ON v.IDCliente = c.IDCliente 
              INNER JOIN estadossolicitud es ON rs.IDEstado = es.IDEstadoSolicitud 
              $whereOT 
              ORDER BY 
                  CASE WHEN rs.observacionGeneral LIKE '%[RECLAMO%' AND rs.IDEstado IN (1, 2, 4) THEN 0 ELSE 1 END ASC,
                  rs.prioridad ASC, rs.fechaRegistroServicio ASC, rs.numeroOrdenTrabajo ASC 
              LIMIT $limite OFFSET $offset";
    $stmtOT = $conexion->prepare($sqlOT);
    $stmtOT->execute($paramsOT);
    $ordenes = $stmtOT->fetchAll(PDO::FETCH_ASSOC);

    if (isset($_GET['export']) && $_GET['export'] === 'excel') {
        $whereExport = "WHERE rs.empresa_id = :empresa_id AND rs.sucursal_id = :sucursal_id AND DATE(rs.fechaRegistroServicio) BETWEEN :f_fecha_desde AND :f_fecha_hasta";
        $paramsExport = [
            ':empresa_id' => $empresa_id,
            ':sucursal_id' => $sucursal_id,
            ':f_fecha_desde' => $f_fecha_desde,
            ':f_fecha_hasta' => $f_fecha_hasta
        ];

        if (!$verTodas && $docUsuarioActual) {
            $whereExport .= " AND rs.numeroDocumentoPersonal = :doc_personal";
            $paramsExport[':doc_personal'] = $docUsuarioActual;
        }

        $sqlExport = "SELECT rs.IDRegistroServicio, rs.numeroOrdenTrabajo, rs.fechaRegistroServicio, rs.prioridad, 
                             es.nombreEstadoSolicitud, v.patente, v.marca, v.modelo, v.anioFabricacion, 
                             c.nombre, c.apellido, c.numeroDocumentoCliente, 
                             rs.kilometrajeIngreso, rs.nivelCombustible, rs.observacionGeneral
                      FROM registrosservicios rs 
                      INNER JOIN vehiculos v ON rs.IDVehiculo = v.IDVehiculo 
                      INNER JOIN clientes c ON v.IDCliente = c.IDCliente 
                      INNER JOIN estadossolicitud es ON rs.IDEstado = es.IDEstadoSolicitud 
                      $whereExport 
                      ORDER BY rs.fechaRegistroServicio DESC, rs.numeroOrdenTrabajo DESC";
        $stmtExp = $conexion->prepare($sqlExport);
        $stmtExp->execute($paramsExport);
        $dataExp = $stmtExp->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="Ordenes_De_Trabajo.xls"');
        
        echo "<html xmlns:x=\"urn:schemas-microsoft-com:office:excel\">";
        echo "<head><meta charset=\"UTF-8\"></head>";
        echo "<body>";
        echo "<table border='1' style='font-family: Arial, sans-serif; border-collapse: collapse; font-size: 12px;'>";
        echo "<thead>";
        echo "<tr style='background-color: #2c3e50; color: #ffffff; font-weight: bold;'>";
        echo "<th style='padding: 8px;'>Orden N°</th>";
        echo "<th style='padding: 8px;'>Fecha Ingreso</th>";
        echo "<th style='padding: 8px;'>Estado</th>";
        echo "<th style='padding: 8px;'>Prioridad</th>";
        echo "<th style='padding: 8px;'>Cliente</th>";
        echo "<th style='padding: 8px;'>DNI</th>";
        echo "<th style='padding: 8px;'>Vehículo (Pat, Mar, Mod, Año)</th>";
        echo "<th style='padding: 8px;'>Km Ingreso</th>";
        echo "<th style='padding: 8px;'>Combustible</th>";
        echo "<th style='padding: 8px;'>Observación General</th>";
        echo "<th style='padding: 8px;'>Servicios Asignados</th>";
        echo "<th style='padding: 8px;'>Total Servicios</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";

        $stmtServ = $conexion->prepare("
            SELECT s.nombreServicio, d.costoServicio, d.observacionRegistroServicio 
            FROM detalleregistro d 
            INNER JOIN servicios s ON d.IDServicio = s.IDServicio 
            WHERE d.IDRegistroServicio = ?
        ");

        foreach($dataExp as $row) {
            $prio = ($row['prioridad'] == 6) ? "Prioritario" : "Normal";
            
            // Obtener servicios
            $stmtServ->execute([$row['IDRegistroServicio']]);
            $servicios = $stmtServ->fetchAll(PDO::FETCH_ASSOC);
            $listaServicios = [];
            $totalCosto = 0;
            foreach ($servicios as $s) {
                $totalCosto += $s['costoServicio'];
                $obs = $s['observacionRegistroServicio'] ? " (Obs: " . $s['observacionRegistroServicio'] . ")" : "";
                $listaServicios[] = "• " . $s['nombreServicio'] . " - $" . number_format($s['costoServicio'], 2, ',', '.') . $obs;
            }
            $textoServicios = implode("<br>", $listaServicios);
            if (empty($textoServicios)) $textoServicios = "Sin servicios";

            echo "<tr>";
            echo "<td style='vertical-align: top; text-align: center; font-weight: bold;'>#" . str_pad($row['numeroOrdenTrabajo'], 8, '0', STR_PAD_LEFT) . "</td>";
            echo "<td style='vertical-align: top; text-align: center;'>" . date('d/m/Y', strtotime($row['fechaRegistroServicio'])) . "</td>";
            echo "<td style='vertical-align: top; text-align: center;'>" . htmlspecialchars($row['nombreEstadoSolicitud']) . "</td>";
            echo "<td style='vertical-align: top; text-align: center;'>" . $prio . "</td>";
            echo "<td style='vertical-align: top;'>" . htmlspecialchars($row['apellido'] . ', ' . $row['nombre']) . "</td>";
            echo "<td style='vertical-align: top;'>" . htmlspecialchars($row['numeroDocumentoCliente']) . "</td>";
            echo "<td style='vertical-align: top;'>" . htmlspecialchars($row['patente'] . " - " . $row['marca'] . " " . $row['modelo'] . " (" . $row['anioFabricacion'] . ")") . "</td>";
            echo "<td style='vertical-align: top; text-align: right;'>" . number_format($row['kilometrajeIngreso'], 0, ',', '.') . " km</td>";
            echo "<td style='vertical-align: top; text-align: center;'>" . htmlspecialchars($row['nivelCombustible']) . "</td>";
            echo "<td style='vertical-align: top; width: 250px;'>" . htmlspecialchars($row['observacionGeneral']) . "</td>";
            echo "<td style='vertical-align: top; width: 300px;'>" . $textoServicios . "</td>";
            echo "<td style='vertical-align: top; text-align: right; font-weight: bold;'>$" . number_format($totalCosto, 2, ',', '.') . "</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
        echo "</body></html>";
        exit;
    }
} catch (PDOException $e) { $errorOT = "No se pudieron cargar las órdenes de trabajo."; }

$queryData = $_GET;
unset($queryData['pagina']); 
$queryString = http_build_query($queryData);
$baseUrlPaginacion = "inicio.php?" . ($queryString ? $queryString . '&' : '') . "pagina=";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Principal - <?php echo htmlspecialchars($config['nombre_empresa']); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { 
            --color-primario: <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>; 
            --color-secundario: <?php echo htmlspecialchars($apariencia['color_secundario'] ?? '#e74c3c'); ?>; 
            --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>; 
            --sidebar-width: 270px; 
        }
        
        body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; background-color: var(--color-fondo); color: #333; display: flex; height: 100vh; overflow: hidden; }
        
        /* CSS del sidebar movido a HTML/sidebar.php */
        
        /* MAIN WRAPPER & TOPBAR */
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar { background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); flex-shrink: 0; z-index: 10; }
        .user-info { font-size: 13px; font-weight: 500; color: #666; }
        .btn-logout { color: var(--color-secundario); text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid var(--color-secundario); padding: 5px 15px; border-radius: 4px; transition: all 0.2s; }
        .btn-logout:hover { background: var(--color-secundario); color: #fff; }
        
        /* CONTENT AREA & FILTERS */
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .panel-title { margin: 0; font-size: 22px; color: var(--color-primario); font-weight: 600; }
        .filter-container { display: flex; gap: 10px; background: #fff; padding: 15px; border-radius: 6px; border: 1px solid #eef0f2; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
        .filter-container select, .filter-container input { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; outline: none; }
        .filter-container select:focus, .filter-container input:focus { border-color: var(--color-primario); }
        .filter-container input[type="text"] { width: 300px; }
        .btn-search { background: var(--color-primario); color: white; border: none; padding: 8px 15px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 13px; }
        .btn-clear { background: #e2e8f0; color: #333; text-decoration: none; padding: 8px 15px; border-radius: 4px; font-size: 13px; font-weight: bold; display: flex; align-items: center; }
        .btn-nuevo { background: var(--color-primario); color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 4px; font-weight: bold; font-size: 13px; transition: opacity 0.2s; }
        .btn-nuevo:hover { opacity: 0.9; }
        
        /* TABLE STYLES */
        .table-card { background: #fff; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); overflow: hidden; border: 1px solid #eef0f2; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f8f9fa; color: #444; padding: 14px 18px; font-weight: 600; font-size: 13px; border-bottom: 2px solid #eaeaea; }
        td { padding: 14px 18px; border-bottom: 1px solid #f1f1f1; font-size: 13px; color: #555; vertical-align: middle; }
        tr:hover { background-color: #fafbfc; }
        
        /* BADGES */
        .badge-prioridad { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .prio-alta { background: #f8d7da; color: #721c24; }
        .prio-normal { background: #e2e3e5; color: #383d41; }
        .badge-estado { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; border: 1px solid transparent; }
        .est-1 { background: #f8d7da; color: #721c24; border-color: #f5c6cb; } 
        .est-2 { background: #fff3cd; color: #856404; border-color: #ffeeba; } 
        .est-4 { background: #ffe8cc; color: #d97706; border-color: #ffd8a8; } 
        .est-5 { background: #d4edda; color: #155724; border-color: #c3e6cb; } 
        .badge-reclamo { display: inline-block; background: #e74c3c; color: white; padding: 3px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; margin-bottom: 5px; }
        
        /* ACTIONS */
        .action-group { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-accion { display: inline-block; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: bold; transition: all 0.15s; border: none; cursor: pointer; }
        .btn-ver { background: #f8f9fa; color: var(--color-primario); border: 1px solid var(--color-primario); }
        .btn-ver:hover { background: var(--color-primario); color: #fff; }
        .btn-editar { background: #e2e8f0; color: #333; }
        .btn-editar:hover { background: #cbd5e0; }
        .btn-estado-avanzar { background: #28a745; color: white; } 
        .btn-estado-avanzar:hover { background: #218838; }
        .btn-estado-finalizar { background: #007bff; color: white; } 
        .btn-estado-finalizar:hover { background: #0069d9; }
        .btn-estado-entregar { background: #6f42c1; color: white; } 
        .btn-estado-entregar:hover { background: #5a32a3; }
        
        /* PAGINATION */
        .pagination-container { display: flex; justify-content: center; align-items: center; padding: 20px; gap: 5px; }
        .page-link { display: inline-flex; justify-content: center; align-items: center; min-width: 32px; height: 32px; padding: 0 10px; background: #fff; border: 1px solid #dee2e6; border-radius: 4px; color: var(--color-primario); text-decoration: none; font-size: 13px; font-weight: 500; transition: all 0.2s; }
        .page-link:hover:not(.active):not(.disabled) { background: #e9ecef; border-color: #dee2e6; color: var(--color-primario); }
        .page-link.active { background: var(--color-primario); border-color: var(--color-primario); color: #fff; cursor: default; }
        .page-link.disabled { color: #6c757d; pointer-events: none; background: #f8f9fa; border-color: #dee2e6; }
        /* TABS */
        .tabs-container { display: flex; border-bottom: 2px solid #eef0f2; margin-bottom: 20px; gap: 5px; overflow-x: auto; }
        .tab-btn { background: none; border: none; padding: 12px 20px; font-size: 14px; font-weight: 600; color: #666; cursor: pointer; border-bottom: 3px solid transparent; transition: 0.2s; white-space: nowrap; outline: none; }
        .tab-btn:hover { color: var(--color-primario); background: #f8f9fa; }
        .tab-btn.active { color: var(--color-primario); border-bottom-color: var(--color-primario); }
        
        /* DROPDOWN MENU */
        .dropdown { position: relative; display: inline-block; }
        .dropdown-content { display: none; position: absolute; right: 0; top: 100%; margin-top: 5px; background-color: #fff; min-width: 220px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.1); border-radius: 6px; z-index: 100; border: 1px solid #eee; overflow: hidden; }
        .dropdown-content a { color: #333; padding: 12px 16px; text-decoration: none; display: block; font-size: 13px; border-bottom: 1px solid #f1f1f1; }
        .dropdown-content a:hover { background-color: #f8f9fa; color: var(--color-primario); }
        .show-dropdown { display: block; }

        /* AUTOCOMPLETE */
        .search-wrapper { position: relative; display: inline-block; }
        .autocomplete-results { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ccc; border-radius: 0 0 6px 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 100; max-height: 300px; overflow-y: auto; display: none; }
        .autocomplete-item { padding: 10px; border-bottom: 1px solid #f1f1f1; font-size: 12px; cursor: pointer; display: flex; flex-direction: column; }
        .autocomplete-item:hover { background: #f8f9fa; }
        .ac-title { font-weight: bold; color: var(--color-primario); font-size: 13px; }
        .ac-subtitle { color: #666; }
    </style>

    <link rel="stylesheet" href="CSS/modo_oscuro.css?v=<?php echo time(); ?>">

</head>
<body class="<?php echo $temaActual === 'oscuro' ? 'tema-oscuro' : ''; ?>">

    <?php 
        $basePath = ''; 
        include __DIR__ . '/HTML/sidebar.php'; 
    ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/HTML/topbar.php'; ?>

        <main class="content-area" id="main-content-area">
            <div class="panel-header">
                <h1 class="panel-title">Tablero de Órdenes de Trabajo</h1>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" onclick="exportarExcel()" class="btn-nuevo" style="background: #28a745; border:none; cursor:pointer;">Exportar Excel</button>
                    <?php if ($puede_crear_ot): ?>
                        <a href="CRUD_Ordenes/crear_ordenTrabajo.php" class="btn-nuevo" style="text-decoration:none;">Crear Nueva Orden</a>
                    <?php endif; ?>
                </div>
            </div>

            <form method="GET" action="inicio.php" class="filter-container" id="filterForm" style="display: flex; flex-wrap: wrap; gap: 20px; justify-content: space-between; align-items: flex-end;">
                <input type="hidden" name="f_estado" id="input_f_estado" value="<?php echo htmlspecialchars($f_estado); ?>">
                
                <!-- Izquierda: Filtros en columna -->
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <!-- Fila 1: Fechas -->
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <strong style="color:#555; font-size:13px; width: 60px;">Fechas:</strong>
                        <input type="date" name="f_fecha_desde" title="Fecha Desde" style="width: 120px;" value="<?php echo htmlspecialchars($f_fecha_desde); ?>" onchange="this.form.submit()">
                        <span style="color:#555; font-size:13px;">hasta</span>
                        <input type="date" name="f_fecha_hasta" title="Fecha Hasta" style="width: 120px;" value="<?php echo htmlspecialchars($f_fecha_hasta); ?>" onchange="this.form.submit()">
                    </div>
                    
                    <!-- Fila 2: Prioridades y Limite -->
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <strong style="color:#555; font-size:13px; width: 60px;">Filtros:</strong>
                        <select name="f_prio" onchange="this.form.submit()" style="width: 145px;">
                            <option value="">Prioridades (Todas)</option>
                            <option value="6" <?php echo $f_prio == '6' ? 'selected' : ''; ?>>Prioritario (Urgente)</option>
                            <option value="7" <?php echo $f_prio == '7' ? 'selected' : ''; ?>>No Prioritario (Normal)</option>
                        </select>

                        <select name="limite" onchange="this.form.submit()">
                            <option value="5" <?php echo $limite == 5 ? 'selected' : ''; ?>>5 / pág</option>
                            <option value="10" <?php echo $limite == 10 ? 'selected' : ''; ?>>10 / pág</option>
                            <option value="50" <?php echo $limite == 50 ? 'selected' : ''; ?>>50 / pág</option>
                        </select>
                    </div>
                </div>

                <!-- Derecha: Búsqueda, Limpiar -->
                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 2px;">
                    <div class="search-wrapper" style="width: 250px;">
                        <input type="text" name="buscar_ot" id="buscar_ot_input" placeholder="Buscar Orden, Patente o Nombre..." value="<?php echo htmlspecialchars($busquedaOT); ?>" style="width: 100%; box-sizing: border-box; padding: 8px 10px; font-size: 13px;" autocomplete="off">
                        <div id="autocomplete-box" class="autocomplete-results"></div>
                    </div>
                    
                    <button type="submit" class="btn-search">Buscar</button>
                    <a href="inicio.php" class="btn-clear" style="margin-left: 0;">Limpiar Filtros</a>
                </div>
            </form>

            <div class="tabs-container">
                <button class="tab-btn <?php echo ($f_estado === '1') ? 'active' : ''; ?>" onclick="cambiarSolapa('1')">Pendientes</button>
                <button class="tab-btn <?php echo ($f_estado === '2') ? 'active' : ''; ?>" onclick="cambiarSolapa('2')">En Proceso</button>
                <button class="tab-btn <?php echo ($f_estado === '4') ? 'active' : ''; ?>" onclick="cambiarSolapa('4')">Finalizado NE</button>
                <button class="tab-btn <?php echo ($f_estado === '5') ? 'active' : ''; ?>" onclick="cambiarSolapa('5')">Entregados</button>
                <button class="tab-btn <?php echo ($f_estado === 'todas') ? 'active' : ''; ?>" onclick="cambiarSolapa('todas')">Todas Órdenes Trabajo</button>
            </div>

            <?php if (isset($errorOT)): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; font-size: 14px;"><?php echo $errorOT; ?></div>
            <?php else: ?>
                <div class="table-card">
                    <table>
                        <thead>
                            <tr>
                                <th>Orden N°</th>
                                <th>Fecha Ingreso</th>
                                <th>Datos del Cliente</th>
                                <th>Patente</th>
                                <th>Prioridad</th>
                                <th>Estado de Orden</th>
                                <th>Acciones y Flujo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($ordenes) === 0): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 35px; color: #888;">
                                        <?php if (!$verTodas): ?>
                                            No tienes órdenes de trabajo asignadas a tu nombre.
                                        <?php else: ?>
                                            No se encontraron órdenes de trabajo para los filtros aplicados.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ordenes as $ot): ?>
                                    <?php 
                                        $idEst = (int)$ot['IDEstado'];
                                        $claseEst = 'est-1';
                                        if ($idEst === 2) $claseEst = 'est-2';
                                        if ($idEst === 4) $claseEst = 'est-4';
                                        if ($idEst === 5) $claseEst = 'est-5';

                                        $prioClass = ((int)$ot['prioridad'] === 6) ? 'prio-alta' : 'prio-normal';
                                        $prioText  = ((int)$ot['prioridad'] === 6) ? 'Prioritario' : 'No Prioritario';
                                        
                                        $esReclamo = strpos($ot['observacionGeneral'], '[RECLAMO') !== false;
                                        
                                        $formAction = "inicio.php?" . htmlspecialchars($_SERVER['QUERY_STRING'] ?? '');
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if($esReclamo): ?><span class="badge-reclamo">RECLAMO</span><br><?php endif; ?>
                                            <strong style="font-size: 15px; color: var(--color-primario);">#<?php echo str_pad($ot['numeroOrdenTrabajo'], 8, '0', STR_PAD_LEFT); ?></strong>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($ot['fechaRegistroServicio'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($ot['apellidoCliente'] . ', ' . $ot['nombreCliente']); ?></strong><br>
                                            <span style="font-size: 12px; color: #777;">DNI: <?php echo htmlspecialchars($ot['numeroDocumentoCliente']); ?></span>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($ot['patente']); ?></strong></td>
                                        <td><span class="badge-prioridad <?php echo $prioClass; ?>"><?php echo $prioText; ?></span></td>
                                        <td><span class="badge-estado <?php echo $claseEst; ?>"><?php echo htmlspecialchars($ot['nombreEstadoSolicitud']); ?></span></td>
                                        <td>
                                            <div class="action-group">
                                                <a href="CRUD_Ordenes/ver_orden.php?id=<?php echo $ot['IDRegistroServicio']; ?>" class="btn-accion btn-ver">Ver</a>
                                                
                                                <?php if ($puede_editar_ot): ?>
                                                    <a href="CRUD_Ordenes/editar_ordenTrabajo.php?id=<?php echo $ot['IDRegistroServicio']; ?>" class="btn-accion btn-editar">Editar</a>
                                                <?php endif; ?>

                                                <?php if ($idEst === 1): // Pendiente ?>
                                                    <form method="POST" action="<?php echo $formAction; ?>" style="display:inline;" onsubmit="confirmarAccion(event, '¿Iniciar Servicios?', '¿Confirma que comenzará a trabajar en los servicios de esta orden?', this);">
                                                        <input type="hidden" name="action_estado" value="1">
                                                        <input type="hidden" name="id_orden" value="<?php echo $ot['IDRegistroServicio']; ?>">
                                                        <input type="hidden" name="nuevo_estado" value="2">
                                                        <button type="submit" class="btn-accion btn-estado-avanzar">Iniciar Servicios</button>
                                                    </form>
                                                    <?php if ($puedeCancelarOT): ?>
                                                        <button type="button" class="btn-accion" style="background:#dc3545; color:white;" onclick="confirmarCancelacion(<?php echo $ot['IDRegistroServicio']; ?>)">Cancelar Orden</button>
                                                    <?php endif; ?>
                                                <?php elseif ($idEst === 2): // En Proceso ?>
                                                    <form method="POST" action="<?php echo $formAction; ?>" style="display:inline;" onsubmit="confirmarAccion(event, '¿Marcar como Listo?', '¿Confirma que todos los servicios fueron completados? Se enviará un aviso al cliente.', this);">
                                                        <input type="hidden" name="action_estado" value="1">
                                                        <input type="hidden" name="id_orden" value="<?php echo $ot['IDRegistroServicio']; ?>">
                                                        <input type="hidden" name="nuevo_estado" value="4">
                                                        <button type="submit" class="btn-accion btn-estado-finalizar">Marcar Listo</button>
                                                    </form>
                                                <?php elseif ($idEst === 4): // Finalizado NE ?>
                                                    <form method="POST" action="<?php echo $formAction; ?>" style="display:inline;" onsubmit="confirmarAccion(event, '¿Entregar Vehículo?', '¿Confirma que el vehículo fue retirado y entregado al cliente de manera definitiva?', this);">
                                                        <input type="hidden" name="action_estado" value="1">
                                                        <input type="hidden" name="id_orden" value="<?php echo $ot['IDRegistroServicio']; ?>">
                                                        <input type="hidden" name="nuevo_estado" value="5">
                                                        <button type="submit" class="btn-accion btn-estado-entregar">Entregar Vehículo</button>
                                                    </form>
                                                <?php elseif ($idEst === 5): // Entregado ?>
                                                    <span style="font-size: 12px; font-weight: bold; color: #28a745; padding: 6px;">Ciclo Completo</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPaginas > 1): ?>
                    <div class="pagination-container">
                        <?php if ($pagina > 1): ?>
                            <a href="<?php echo $baseUrlPaginacion . ($pagina - 1); ?>" class="page-link">&laquo; Anterior</a>
                        <?php else: ?>
                            <span class="page-link disabled">&laquo; Anterior</span>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <?php if ($i == $pagina): ?>
                                <span class="page-link active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="<?php echo $baseUrlPaginacion . $i; ?>" class="page-link"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($pagina < $totalPaginas): ?>
                            <a href="<?php echo $baseUrlPaginacion . ($pagina + 1); ?>" class="page-link">Siguiente &raquo;</a>
                        <?php else: ?>
                            <span class="page-link disabled">Siguiente &raquo;</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </main>
    </div>

    <script>
        (function() {
            const savedScroll = sessionStorage.getItem('scrollPosition_OT');
            if (savedScroll !== null) {
                const el = document.getElementById('main-content-area');
                if (el) el.scrollTop = parseInt(savedScroll, 10);
                sessionStorage.removeItem('scrollPosition_OT');
            }
        })();

        function confirmarAccion(event, titulo, texto, formElement) {
            event.preventDefault();
            Swal.fire({
                title: titulo,
                text: texto,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#dc3545',
                confirmButtonText: 'Sí, confirmar',
                cancelButtonText: 'Cancelar',
                heightAuto: false, 
                scrollbarPadding: false 
            }).then((result) => {
                if (result.isConfirmed) {
                    const contentArea = document.getElementById('main-content-area');
                    if (contentArea) {
                        sessionStorage.setItem('scrollPosition_OT', contentArea.scrollTop);
                    }
                    formElement.submit();
                }
            });
        }

        function confirmarCancelacion(idOrden) {
            Swal.fire({
                title: 'Cancelar Orden de Trabajo',
                text: 'Escribe el motivo del por qué se cancela esta orden (Opcional):',
                input: 'textarea',
                inputAttributes: {
                    style: 'resize: none;'
                },
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, Cancelar',
                cancelButtonText: 'Volver'
            }).then((result) => {
                if (result.isConfirmed) {
                    let form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'inicio.php?' + window.location.search.replace('?', '');
                    
                    let act = document.createElement('input');
                    act.type = 'hidden';
                    act.name = 'action_estado';
                    act.value = '1';
                    
                    let id_ot = document.createElement('input');
                    id_ot.type = 'hidden';
                    id_ot.name = 'id_orden';
                    id_ot.value = idOrden;
                    
                    let nw_est = document.createElement('input');
                    nw_est.type = 'hidden';
                    nw_est.name = 'nuevo_estado';
                    nw_est.value = '8'; // 8 = Anulada
                    
                    let motivo = document.createElement('input');
                    motivo.type = 'hidden';
                    motivo.name = 'motivo_anulacion';
                    motivo.value = result.value;
                    
                    form.appendChild(act);
                    form.appendChild(id_ot);
                    form.appendChild(nw_est);
                    form.appendChild(motivo);
                    document.body.appendChild(form);
                    
                    const contentArea = document.getElementById('main-content-area');
                    if (contentArea) {
                        sessionStorage.setItem('scrollPosition_OT', contentArea.scrollTop);
                    }
                    
                    form.submit();
                }
            });
        }

        /* --- NUEVAS FUNCIONALIDADES --- */

        function toggleDropdown() {
            document.getElementById("nuevoDropdown").classList.toggle("show-dropdown");
        }

        window.onclick = function(event) {
            if (!event.target.matches('.btn-nuevo') && !event.target.closest('.btn-nuevo')) {
                var dropdowns = document.getElementsByClassName("dropdown-content");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show-dropdown')) {
                        openDropdown.classList.remove('show-dropdown');
                    }
                }
            }
        }

        function cambiarSolapa(estado) {
            document.getElementById('input_f_estado').value = estado;
            // Al cambiar solapa, volvemos a la página 1
            let form = document.getElementById('filterForm');
            let inputPagina = document.createElement('input');
            inputPagina.type = 'hidden';
            inputPagina.name = 'pagina';
            inputPagina.value = '1';
            form.appendChild(inputPagina);
            form.submit();
        }

        function exportarExcel() {
            let form = document.getElementById('filterForm');
            let url = new URL(form.action, window.location.href);
            let params = new URLSearchParams(new FormData(form));
            params.set('export', 'excel');
            window.location.href = url.pathname + '?' + params.toString();
        }

        // Live Search Autocomplete
        const searchInput = document.getElementById('buscar_ot_input');
        const acBox = document.getElementById('autocomplete-box');
        let acTimeout = null;

        if (searchInput && acBox) {
            searchInput.addEventListener('keyup', function() {
                clearTimeout(acTimeout);
                const val = this.value.trim();
                if (val.length >= 3) {
                    acTimeout = setTimeout(() => {
                        fetch('CRUD_Ordenes/buscar_ot_ajax.php?q=' + encodeURIComponent(val))
                        .then(res => res.json())
                        .then(data => {
                            acBox.innerHTML = '';
                            if (data.length > 0) {
                                data.forEach(item => {
                                    let div = document.createElement('div');
                                    div.className = 'autocomplete-item';
                                    div.innerHTML = `<span class="ac-title">#${String(item.numeroOrdenTrabajo).padStart(6, '0')} - ${item.patente}</span>
                                                     <span class="ac-subtitle">${item.apellido}, ${item.nombre} | ${item.nombreEstadoSolicitud}</span>`;
                                    div.onclick = () => {
                                        window.location.href = 'inicio.php?buscar_ot=' + encodeURIComponent(String(item.numeroOrdenTrabajo).padStart(6, '0')) + '&f_estado=' + item.IDEstado;
                                    };
                                    acBox.appendChild(div);
                                });
                                acBox.style.display = 'block';
                            } else {
                                acBox.innerHTML = '<div class="autocomplete-item"><span class="ac-subtitle">Sin resultados...</span></div>';
                                acBox.style.display = 'block';
                            }
                        });
                    }, 300);
                } else {
                    acBox.style.display = 'none';
                }
            });

            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !acBox.contains(e.target)) {
                    acBox.style.display = 'none';
                }
            });
        }
        
        /* JS del sidebar movido a HTML/sidebar.php */
    </script>
</body>
</html>