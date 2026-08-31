<?php
/**
 * Lizzosoft Vehículos - Crear Reclamo
 * Ubicación: lizzosoft_vehiculos/Reclamos/crear_reclamo.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$config     = $_SESSION['cliente_config'];
$apariencia = $config['apariencia'];
$empresa_id = (int)$_SESSION['empresa_id'];
$sucursal_id= (int)$_SESSION['sucursal_id'];
$temaActual = $_SESSION['tema_preferido'] ?? 'claro'; // Variable para Modo Oscuro
$conexion = obtenerConexion();

// -------------------------------------------------------------
// PROCESAMIENTO POST: CREACIÓN DEL RECLAMO Y LA NUEVA OT
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_reclamo'])) {
    $idOTOriginal = (int)$_POST['id_ot'];
    $obsGeneral = trim($_POST['observacionGeneral'] ?? '');
    $serviciosSeleccionados = $_POST['servicios'] ?? [];
    
    if (empty($serviciosSeleccionados)) {
        $error = "Debe seleccionar al menos un servicio para ingresar el reclamo.";
    } else {
        try {
            $conexion->beginTransaction();

            // 1. Obtener datos de la OT Original
            $stmtOrig = $conexion->prepare("SELECT IDVehiculo, numeroOrdenTrabajo, kilometrajeIngreso FROM registrosservicios WHERE IDRegistroServicio = ?");
            $stmtOrig->execute([$idOTOriginal]);
            $otOriginal = $stmtOrig->fetch(PDO::FETCH_ASSOC);

            // 2. Insertar en tabla Reclamos con estado inicial "Pendiente"
            $stmtR = $conexion->prepare("INSERT INTO reclamos (IDRegistroServicioOriginal, observacionReclamo, estadoReclamo, sucursal_id, empresa_id) VALUES (?, ?, 'Pendiente', ?, ?)");
            $stmtR->execute([$idOTOriginal, $obsGeneral, $sucursal_id, $empresa_id]);
            $idReclamo = $conexion->lastInsertId();

            // 3. Generar Nueva Orden de Trabajo Prioritaria
            $stmtMax = $conexion->prepare("SELECT MAX(numeroOrdenTrabajo) FROM registrosservicios WHERE empresa_id = ?");
            $stmtMax->execute([$empresa_id]);
            $maxOT = (int)$stmtMax->fetchColumn();
            $nuevaOTNum = $maxOT > 0 ? $maxOT + 1 : 1000;

            // Almacenamos el tag [RECLAMO] para marcarlo en la tabla del inicio
            $obsNewOT = "[RECLAMO - Ref OT #" . str_pad($otOriginal['numeroOrdenTrabajo'], 8, '0', STR_PAD_LEFT) . "] " . $obsGeneral;
            
            $stmtOTNuevo = $conexion->prepare("INSERT INTO registrosservicios (fechaRegistroServicio, IDVehiculo, kilometrajeIngreso, observacionGeneral, prioridad, IDEstado, sucursal_id, empresa_id, numeroOrdenTrabajo) VALUES (CURDATE(), ?, ?, ?, 6, 1, ?, ?, ?)");
            $stmtOTNuevo->execute([$otOriginal['IDVehiculo'], $otOriginal['kilometrajeIngreso'], $obsNewOT, $sucursal_id, $empresa_id, $nuevaOTNum]);
            $idOTNueva = $conexion->lastInsertId();

            // 4. Mapear servicios seleccionados en detalles
            foreach ($serviciosSeleccionados as $idServicio) {
                $idSrv = (int)$idServicio;
                $obsSrv = trim($_POST['observaciones_srv'][$idSrv] ?? '');
                
                // Detalle exclusivo del Reclamo
                $stmtDR = $conexion->prepare("INSERT INTO detalle_reclamos (IDReclamo, IDServicio, observacionDetalleReclamo) VALUES (?, ?, ?)");
                $stmtDR->execute([$idReclamo, $idSrv, $obsSrv]);

                // Detalle Registro para la nueva OT (Costo 0 por ser reclamo/garantía)
                $obsDReg = "RECLAMO/GARANTIA: " . $obsSrv;
                $stmtDReg = $conexion->prepare("INSERT INTO detalleregistro (IDRegistroServicio, IDServicio, observacionRegistroServicio, costoServicio, sucursal_id, empresa_id) VALUES (?, ?, ?, 0, ?, ?)");
                $stmtDReg->execute([$idOTNueva, $idSrv, $obsDReg, $sucursal_id, $empresa_id]);
            }

            $stmtLog = $conexion->prepare("INSERT INTO logs_accesos (IDUsuario, nombreUsuario, accion, fecha_hora, empresa_id, sucursal_id) VALUES (?, ?, ?, NOW(), ?, ?)");
            $stmtLog->execute([$_SESSION['IDUsuario'], $_SESSION['nombreUsuario'], 'CREAR_RECLAMO', $empresa_id, $sucursal_id]);

            $conexion->commit();
            header("Location: menuReclamos.php?exito=1");
            exit;

        } catch (Exception $e) {
            $conexion->rollBack();
            $error = "Error crítico al registrar el reclamo: " . $e->getMessage();
        }
    }
}

// -------------------------------------------------------------
// BÚSQUEDA DE OT PARA RECLAMAR
// -------------------------------------------------------------
$busqueda = trim($_GET['q'] ?? '');
$ordenesEncontradas = [];

if ($busqueda !== '') {
    $whereBusqueda = "WHERE rs.empresa_id = :empresa_id AND rs.sucursal_id = :sucursal_id AND rs.IDEstado IN (3, 4, 5)"; // Solo OTs finalizadas
    $paramsBusq = [':empresa_id' => $empresa_id, ':sucursal_id' => $sucursal_id];

    $terminos = array_filter(explode(' ', $busqueda));
    $indice = 0;
    foreach ($terminos as $termino) {
        $cadenaVirtual = "CONCAT_WS(' ', LPAD(CAST(rs.numeroOrdenTrabajo AS CHAR), 6, '0'), CAST(rs.numeroOrdenTrabajo AS CHAR), v.patente, v.marca, v.modelo, CAST(c.numeroDocumentoCliente AS CHAR), c.nombre, c.apellido)";
        $whereBusqueda .= " AND $cadenaVirtual LIKE :q_termino_$indice";
        $paramsBusq[":q_termino_$indice"] = '%' . $termino . '%';
        $indice++;
    }

    $sqlBusq = "
        SELECT rs.IDRegistroServicio, rs.numeroOrdenTrabajo, rs.fechaRegistroServicio,
               v.patente, c.nombre AS nombreCliente, c.apellido AS apellidoCliente, c.numeroDocumentoCliente
        FROM registrosservicios rs
        INNER JOIN vehiculos v ON rs.IDVehiculo = v.IDVehiculo
        INNER JOIN clientes c ON v.IDCliente = c.IDCliente
        $whereBusqueda
        ORDER BY rs.fechaRegistroServicio DESC LIMIT 20
    ";
    $stmtBusq = $conexion->prepare($sqlBusq);
    $stmtBusq->execute($paramsBusq);
    $ordenesEncontradas = $stmtBusq->fetchAll(PDO::FETCH_ASSOC);
}

// -------------------------------------------------------------
// CARGAR OT SELECCIONADA
// -------------------------------------------------------------
$idSeleccionado = isset($_GET['id_ot']) ? (int)$_GET['id_ot'] : 0;
$otSeleccionada = null;
$detallesOT = [];

if ($idSeleccionado > 0) {
    $stmtS = $conexion->prepare("
        SELECT rs.IDRegistroServicio, rs.numeroOrdenTrabajo, rs.fechaRegistroServicio,
               v.patente, c.nombre, c.apellido
        FROM registrosservicios rs
        INNER JOIN vehiculos v ON rs.IDVehiculo = v.IDVehiculo
        INNER JOIN clientes c ON v.IDCliente = c.IDCliente
        WHERE rs.IDRegistroServicio = ? AND rs.empresa_id = ?
    ");
    $stmtS->execute([$idSeleccionado, $empresa_id]);
    $otSeleccionada = $stmtS->fetch(PDO::FETCH_ASSOC);

    if ($otSeleccionada) {
        $stmtDet = $conexion->prepare("
            SELECT dr.IDServicio, s.nombreServicio
            FROM detalleregistro dr
            INNER JOIN servicios s ON dr.IDServicio = s.IDServicio
            WHERE dr.IDRegistroServicio = ?
        ");
        $stmtDet->execute([$idSeleccionado]);
        $detallesOT = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Reclamo - <?php echo htmlspecialchars($config['nombre_empresa']); ?></title>
    <style>
        :root { --color-primario: <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>; --color-secundario: <?php echo htmlspecialchars($apariencia['color_secundario'] ?? '#e74c3c'); ?>; --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>; --sidebar-width: 270px; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; background-color: var(--color-fondo); color: #333; display: flex; height: 100vh; overflow: hidden; }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar { background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); flex-shrink: 0; }
        .user-info { font-size: 13px; font-weight: 500; color: #666; }
        .btn-logout { color: var(--color-secundario); text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid var(--color-secundario); padding: 5px 15px; border-radius: 4px; transition: all 0.2s; }
        .btn-logout:hover { background: var(--color-secundario); color: #fff; }
        
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; max-width: 900px; margin-left: auto; margin-right: auto;}
        .panel-title { margin: 0; font-size: 22px; color: var(--color-primario); font-weight: 600; }
        
        .box { background: #fff; padding: 25px; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); border: 1px solid #eef0f2; margin-bottom: 20px; max-width: 900px; margin-left: auto; margin-right: auto;}
        .search-bar { display: flex; gap: 10px; margin-bottom: 10px; }
        .search-bar input { flex: 1; padding: 12px 15px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; outline: none; }
        .search-bar input:focus { border-color: var(--color-primario); }
        .btn { background: var(--color-primario); color: white; border: none; padding: 12px 20px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; }
        .btn-cancel { background: #e2e8f0; color: #333; }
        .btn-submit { background: #e74c3c; }
        
        table { width: 100%; border-collapse: collapse; text-align: left; margin-top: 15px; }
        th { background: #f8f9fa; color: #444; padding: 12px 15px; font-size: 13px; border-bottom: 2px solid #eaeaea; }
        td { padding: 12px 15px; border-bottom: 1px solid #f1f1f1; font-size: 13px; color: #555; vertical-align: middle; }
        .row-ot:hover { background-color: #fafbfc; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 8px; font-size: 13px; color: #444; }
        .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; font-family: inherit; resize: vertical; min-height: 80px; box-sizing: border-box; }
        
        .srv-reclamo { display: flex; align-items: flex-start; gap: 15px; padding: 15px; border: 1px solid #eee; border-radius: 6px; margin-bottom: 15px; background: #fafafa; }
        .srv-check { margin-top: 4px; transform: scale(1.2); }
        .srv-content { flex: 1; }
        .srv-title { font-weight: bold; color: var(--color-primario); font-size: 14px; margin-bottom: 8px; }
        .srv-textarea { width: 100%; height: 60px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; resize: none; font-size: 12px; font-family: inherit; box-sizing: border-box;}
    </style>
    
    <link rel="stylesheet" href="../CSS/modo_oscuro.css?v=<?php echo time(); ?>">
</head>
<body class="<?php echo $temaActual === 'oscuro' ? 'tema-oscuro' : ''; ?>">

    <?php 
        $basePath = '../'; 
        include __DIR__ . '/../HTML/sidebar.php'; 
    ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../HTML/topbar.php'; ?>

        <main class="content-area">
        <div class="panel-header">
            <h1 class="panel-title">Ingresar Nuevo Reclamo</h1>
            <a href="menuReclamos.php" class="btn btn-cancel" style="padding: 8px 15px; font-size: 12px;">Volver</a>
        </div>

        <?php if (isset($error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; font-weight: bold; max-width: 900px; margin-left: auto; margin-right: auto;"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!$otSeleccionada): ?>
            <div class="box">
                <p style="font-size: 14px; color: #666; margin-top: 0;"><strong>Paso 1:</strong> Busque y seleccione la Orden de Trabajo original sobre la cual se aplicará la garantía/reclamo.</p>
                <form method="GET" class="search-bar">
                    <input type="text" name="q" placeholder="Buscar por N° OT, Patente, DNI o Nombre del Cliente..." value="<?php echo htmlspecialchars($busqueda); ?>" required>
                    <button type="submit" class="btn">Buscar Orden</button>
                </form>

                <?php if ($busqueda !== ''): ?>
                    <?php if (count($ordenesEncontradas) === 0): ?>
                        <p style="color: #e74c3c; font-size: 14px; margin-top: 15px;">No se encontraron órdenes finalizadas que coincidan con la búsqueda.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>N° Orden</th>
                                    <th>Fecha Original</th>
                                    <th>Cliente</th>
                                    <th>Patente</th>
                                    <th style="text-align: right;">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ordenesEncontradas as $oe): ?>
                                <tr class="row-ot">
                                    <td><strong>#<?php echo str_pad($oe['numeroOrdenTrabajo'], 8, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($oe['fechaRegistroServicio'])); ?></td>
                                    <td><?php echo htmlspecialchars($oe['apellidoCliente'] . ', ' . $oe['nombreCliente'] . ' (DNI: ' . $oe['numeroDocumentoCliente'] . ')'); ?></td>
                                    <td><?php echo htmlspecialchars($oe['patente']); ?></td>
                                    <td style="text-align: right;">
                                        <a href="crear_reclamo.php?id_ot=<?php echo $oe['IDRegistroServicio']; ?>" class="btn" style="padding: 6px 12px; font-size: 12px;">Seleccionar</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="box" style="background: #fdfdfd; border-left: 4px solid var(--color-primario);">
                <h3 style="margin-top: 0; font-size: 16px; color: var(--color-primario);">Orden Original Seleccionada: #<?php echo str_pad($otSeleccionada['numeroOrdenTrabajo'], 8, '0', STR_PAD_LEFT); ?></h3>
                <p style="margin: 0; font-size: 14px; color: #555;">
                    <strong>Cliente:</strong> <?php echo htmlspecialchars($otSeleccionada['apellido'] . ', ' . $otSeleccionada['nombre']); ?> | 
                    <strong>Vehículo:</strong> <?php echo htmlspecialchars($otSeleccionada['patente']); ?> |
                    <strong>Fecha de la Orden:</strong> <?php echo date('d/m/Y', strtotime($otSeleccionada['fechaRegistroServicio'])); ?>
                </p>
            </div>

            <form method="POST">
                <input type="hidden" name="id_ot" value="<?php echo $otSeleccionada['IDRegistroServicio']; ?>">
                
                <div class="box">
                    <div class="form-group">
                        <label>Observación General del Reclamo (Motivo del ingreso)</label>
                        <textarea name="observacionGeneral" placeholder="Describa brevemente el problema reportado por el cliente de forma general..." required><?php echo htmlspecialchars($_POST['observacionGeneral'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="box">
                    <h3 style="margin-top: 0; font-size: 16px; color: var(--color-primario); margin-bottom: 15px;">Servicios Realizados (Seleccione los que entran en reclamo)</h3>
                    
                    <?php if(empty($detallesOT)): ?>
                        <p style="color: #e74c3c; font-size: 14px;">La orden original no posee servicios registrados.</p>
                    <?php else: ?>
                        <?php foreach ($detallesOT as $det): ?>
                            <label class="srv-reclamo" for="srv_<?php echo $det['IDServicio']; ?>">
                                <input type="checkbox" name="servicios[]" value="<?php echo $det['IDServicio']; ?>" id="srv_<?php echo $det['IDServicio']; ?>" class="srv-check" <?php echo (isset($_POST['servicios']) && is_array($_POST['servicios']) && in_array($det['IDServicio'], $_POST['servicios'])) ? 'checked' : ''; ?>>
                                <div class="srv-content">
                                    <div class="srv-title"><?php echo htmlspecialchars($det['nombreServicio']); ?></div>
                                    <textarea name="observaciones_srv[<?php echo $det['IDServicio']; ?>]" class="srv-textarea" placeholder="Describa el problema específico con este servicio (opcional)..."><?php echo htmlspecialchars($_POST['observaciones_srv'][$det['IDServicio']] ?? ''); ?></textarea>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div style="text-align: right; max-width: 900px; margin: 0 auto;">
                    <a href="crear_reclamo.php" class="btn btn-cancel">Cambiar Orden</a>
                    <button type="submit" name="crear_reclamo" class="btn btn-submit">Confirmar y Generar OT Prioritaria</button>
                </div>
            </form>
        <?php endif; ?>

        </main>
    </div>
    
    <script>
        /* JS del sidebar movido a HTML/sidebar.php */
    </script>
</body>
</html>