<?php
session_start();
require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$conn = obtenerConexion();
$idSucursal = $_SESSION['sucursal_id'] ?? 1;
$empresa_id = $_SESSION['empresa_id_usuario'] ?? 1;
$temaActual = $_SESSION['tema_preferido'] ?? 'claro';
$config = $_SESSION['cliente_config'] ?? [];
$apariencia = $config['apariencia'] ?? [];

$idPresupuesto = (int)($_GET['id'] ?? 0);
if (!$idPresupuesto) {
    header("Location: listar_presupuestos.php");
    exit;
}

// Obtener datos del presupuesto
$sql = "SELECT p.*, c.telefono as cli_telefono FROM presupuestos p LEFT JOIN clientes c ON p.IDCliente = c.IDCliente WHERE p.IDPresupuesto = ? AND p.IDSucursal = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$idPresupuesto, $idSucursal]);
$presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$presupuesto || $presupuesto['estado'] !== 'Aprobado') {
    header("Location: listar_presupuestos.php");
    exit;
}

$isRegistrado = !empty($presupuesto['IDCliente']) && !empty($presupuesto['IDVehiculo']);
$telefonoExistente = $isRegistrado ? $presupuesto['cli_telefono'] : $presupuesto['casual_telefono'];
$necesitaTelefono = empty(trim((string)$telefonoExistente));

// Obtener tipos de documento y personal para el formulario
$tiposDoc = $conn->query("SELECT IDTipoDocumento, tipoDocumento FROM tiposdocumentos WHERE estado = 'Activo'")->fetchAll(PDO::FETCH_ASSOC);
$personal = $conn->prepare("SELECT numeroDocumentoPersonal, nombre, apellido FROM personal WHERE empresa_id = ? AND sucursal_id = ? AND estado = 'Activo'");
$personal->execute([$empresa_id, $idSucursal]);
$personalList = $personal->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prioridad = (int)($_POST['prioridad'] ?? 7);
    $kmIngreso = (int)($_POST['km_ingreso'] ?? 0);
    $nivelComb = trim($_POST['nivel_combustible'] ?? '');
    $obsGeneral = trim($_POST['observacion_general'] ?? '');
    $docPersonal = trim($_POST['numeroDocumentoPersonal'] ?? '');
    $docPersonal = ($docPersonal === '') ? null : $docPersonal;
    
    $telefonoIngresado = trim($_POST['telefono_nuevo'] ?? '');
    
    $IDCliente = $presupuesto['IDCliente'];
    $IDVehiculo = $presupuesto['IDVehiculo'];
    
    $conn->beginTransaction();
    try {
        if ($necesitaTelefono && empty($telefonoIngresado)) {
            throw new Exception("Debe ingresar un número de teléfono (es obligatorio para crear la orden).");
        }

        if (!$isRegistrado) {
            // Necesitamos crear el cliente y el vehículo
            $tipoDoc = (int)($_POST['IDTipoDocumento']);
            $numDoc = trim($_POST['numeroDocumentoCliente'] ?? '');
            $email = trim($_POST['email'] ?? '');
            
            // Insertar cliente
            $telFinal = $necesitaTelefono ? $telefonoIngresado : $presupuesto['casual_telefono'];
            $sqlCli = "INSERT INTO clientes (numeroDocumentoCliente, IDTipoDocumento, nombre, apellido, email, telefono, empresa_id, sucursal_id, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Activo')";
            $stmtCli = $conn->prepare($sqlCli);
            $stmtCli->execute([$numDoc, $tipoDoc, $presupuesto['casual_nombre'], $presupuesto['casual_apellido'], $email, $telFinal, $empresa_id, $idSucursal]);
            $IDCliente = $conn->lastInsertId();
            
            // Insertar vehículo
            $color = trim($_POST['colorVehiculo'] ?? 'Sin Especificar');
            $anio = (int)($_POST['anioFabricacion'] ?? date('Y'));
            $numMotor = trim($_POST['numeroMotor'] ?? '');
            $numChasis = trim($_POST['numeroChasis'] ?? '');
            
            $sqlVeh = "INSERT INTO vehiculos (IDCliente, patente, marca, modelo, tipoVehiculo, colorVehiculo, anioFabricacion, numeroMotor, numeroChasis, empresa_id, sucursal_id, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Activo')";
            $stmtVeh = $conn->prepare($sqlVeh);
            $stmtVeh->execute([
                $IDCliente, $presupuesto['casual_patente'], $presupuesto['casual_marca'], $presupuesto['casual_modelo'], 
                $presupuesto['casual_tipo_vehiculo'], $color, $anio, $numMotor, $numChasis, $empresa_id, $idSucursal
            ]);
            $IDVehiculo = $conn->lastInsertId();
            $IDVehiculo = $conn->lastInsertId();
            
            // Actualizar presupuesto para enlazarlo permanentemente
            $conn->prepare("UPDATE presupuestos SET IDCliente = ?, IDVehiculo = ? WHERE IDPresupuesto = ?")->execute([$IDCliente, $IDVehiculo, $idPresupuesto]);
        } else {
            if ($necesitaTelefono) {
                $conn->prepare("UPDATE clientes SET telefono = ? WHERE IDCliente = ?")->execute([$telefonoIngresado, $IDCliente]);
            }
        }
        
        // Generar Número de Orden
        $stmtNum = $conn->prepare("SELECT MAX(numeroOrdenTrabajo) FROM registrosservicios WHERE sucursal_id = ? AND empresa_id = ? FOR UPDATE");
        $stmtNum->execute([$idSucursal, $empresa_id]);
        $maxNum = $stmtNum->fetchColumn();
        $nuevoNumOrden = $maxNum ? $maxNum + 1 : 1001;
        
        // Crear Orden (IDEstado = 1 es Pendiente)
        $sqlRegistro = "INSERT INTO registrosservicios (fechaRegistroServicio, IDVehiculo, observacionGeneral, prioridad, IDEstado, nivelCombustible, kilometrajeIngreso, sucursal_id, empresa_id, numeroOrdenTrabajo, numeroDocumentoPersonal) 
                        VALUES (CURDATE(), ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)";
        $stmtRegistro = $conn->prepare($sqlRegistro);
        $stmtRegistro->execute([$IDVehiculo, $obsGeneral, $prioridad, $nivelComb, $kmIngreso, $idSucursal, $empresa_id, $nuevoNumOrden, $docPersonal]);
        $idRegistro = $conn->lastInsertId();
        
        // Pasar detalles del presupuesto a la orden
        $stmtDetallesPresu = $conn->prepare("SELECT * FROM detalle_presupuestos WHERE IDPresupuesto = ?");
        $stmtDetallesPresu->execute([$idPresupuesto]);
        $detallesPresupuesto = $stmtDetallesPresu->fetchAll(PDO::FETCH_ASSOC);
        
        $sqlDetalle = "INSERT INTO detalleregistro (IDRegistroServicio, IDServicio, observacionRegistroServicio, costoServicio, sucursal_id, empresa_id) VALUES (?, ?, ?, ?, ?, ?)";
        $stmtDetalle = $conn->prepare($sqlDetalle);
        
        foreach ($detallesPresupuesto as $dp) {
            $idSrv = $dp['IDServicio'];
            $costo = $dp['precio_unitario'];
            $obs = trim($dp['descripcion_libre'] ?? '');
            
            if (!$idSrv && strpos($obs, '|||') !== false) {
                $parts = explode('|||', $obs);
                $obs = trim($parts[0]) . (isset($parts[1]) && trim($parts[1]) !== '' ? ' - ' . trim($parts[1]) : '');
            }
            if ($obs === '') {
                $obs = '-'; 
            }
            
            // Si es manual, IDServicio es nulo. Veremos si la tabla lo permite.
            $stmtDetalle->execute([$idRegistro, $idSrv, $obs, $costo, $idSucursal, $empresa_id]);
        }
        
        // Actualizar el presupuesto para registrar que ya fue convertido a esta orden
        $conn->prepare("UPDATE presupuestos SET IDOrdenTrabajo = ? WHERE IDPresupuesto = ?")->execute([$idRegistro, $idPresupuesto]);
        
        $conn->commit();
        
        $_SESSION['flash_mensaje'] = "Orden de Trabajo N° " . str_pad($nuevoNumOrden, 8, '0', STR_PAD_LEFT) . " generada a partir del Presupuesto #" . $idPresupuesto . " con éxito.";
        $_SESSION['flash_tipo'] = "exito";
        
        header("Location: ../inicio.php");
        exit;
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error al convertir el presupuesto a orden: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Convertir a Orden</title>
    <link rel="stylesheet" href="../CSS/modo_oscuro.css?v=<?php echo time(); ?>">
    <style>
        :root { 
            --color-primario: <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>; 
            --color-secundario: <?php echo htmlspecialchars($apariencia['color_secundario'] ?? '#e74c3c'); ?>; 
            --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>; 
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--color-fondo); margin: 0; display: flex; height: 100vh; overflow: hidden; color: #333; }
        
        .topbar { background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); flex-shrink: 0; z-index: 10; border-bottom: 1px solid #eef0f2; }
        .user-info { font-size: 13px; font-weight: 500; color: #666; }
        .btn-logout { color: #e74c3c; text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid #e74c3c; padding: 5px 15px; border-radius: 4px; transition: all 0.2s; }
        .btn-logout:hover { background: #e74c3c; color: #fff; }
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }
        
        .wrapper { background: #fff; max-width: 800px; margin: 0 auto; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h2 { border-bottom: 2px solid var(--color-primario); padding-bottom: 10px; margin-top: 0; margin-bottom: 20px; color: var(--color-primario); }
        
        .form-section { background: #f8f9fa; padding: 20px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 20px; }
        .form-section h3 { margin-top: 0; font-size: 16px; color: #444; border-bottom: 1px solid #ddd; padding-bottom: 8px; margin-bottom: 15px; }
        
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 200px; }
        .form-group label { display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #555; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
        
        .alert { background: #cce5ff; color: #004085; padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #b8daff; font-size: 14px; }
        .alert-error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        
        /* Personal Search Table Styles */
        .search-box { position: relative; }
        .search-results { position: absolute; background: #fff; border: 1px solid #ddd; width: 100%; max-height: 200px; overflow-y: auto; z-index: 1000; display: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .search-item { padding: 10px; border-bottom: 1px solid #eee; cursor: pointer; font-size: 13px; }
        .search-item:hover { background: #f8f9fa; color: var(--color-primario); }
        .client-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        .client-table th, .client-table td { border-bottom: 1px solid #eee; padding: 8px; text-align: left; }
        .client-table th { background: #f8f9fa; font-weight: bold; color: #555; }
        .client-table tr:hover td { background: #f1f5f9; cursor: pointer; }
        .pagination-container { display: flex; justify-content: center; gap: 5px; margin-top: 15px; }
        .page-btn { border: 1px solid #ddd; padding: 5px 10px; background: white; cursor: pointer; font-size: 12px; border-radius: 3px; font-weight: bold; color: var(--color-primario); }
        .page-btn.active { background: var(--color-primario); color: white; border-color: var(--color-primario); pointer-events: none; }
        .btn-global { background: var(--color-primario); color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        
        .form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-save { background: #28a745; color: white; padding: 10px 20px; font-size: 15px; font-weight: bold; border: none; border-radius: 4px; cursor: pointer; }
        .btn-cancel { background: #6c757d; color: white; padding: 10px 20px; font-size: 15px; font-weight: bold; text-decoration: none; border-radius: 4px; }
        
        /* Tema Oscuro Específico */
        body.tema-oscuro .wrapper { background: #27272a; color: #fff; border-color: #3f3f46; }
        body.tema-oscuro .form-section { background: #3f3f46; border-color: #52525b; }
        body.tema-oscuro .form-section h3 { color: #fff; border-bottom-color: #52525b; }
        body.tema-oscuro .form-group label { color: #ccc; }
        body.tema-oscuro .form-control { background: #18181b; color: #fff; border-color: #52525b; }
        body.tema-oscuro h2 { color: #fff; }
    </style>
</head>
<body class="<?php echo $temaActual === 'oscuro' ? 'tema-oscuro' : ''; ?>">
    <?php 
        $basePath = '../'; 
        include __DIR__ . '/../HTML/sidebar.php'; 
    ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../HTML/topbar.php'; ?>
        
        <main class="content-area">
            <div class="wrapper">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid var(--color-primario); padding-bottom: 15px; margin-bottom: 20px;">
                    <h2 style="margin:0; color: var(--color-primario);">Convertir Presupuesto #<?php echo str_pad($idPresupuesto, 8, '0', STR_PAD_LEFT); ?> a Orden de Trabajo</h2>
                    <a href="listar_presupuestos.php" style="background: #e2e8f0; color: #333; text-decoration: none; padding: 8px 15px; border-radius: 4px; font-weight: bold; font-size: 13px;">Volver</a>
                </div>
                
                <?php if($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
                
                <form method="POST">
                    <?php if (!$isRegistrado): ?>
                    <div class="alert">
                        <strong>¡Atención!</strong> Este presupuesto fue creado para un cliente ocasional. Para convertirlo en una Orden de Trabajo, es obligatorio registrar al cliente y su vehículo en el padrón del sistema.
                    </div>
                    
                    <div class="form-section">
                        <h3>Datos faltantes del Cliente</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Tipo de Documento</label>
                                <select name="IDTipoDocumento" class="form-control" required>
                                    <?php foreach($tiposDoc as $td): ?>
                                        <option value="<?php echo $td['IDTipoDocumento']; ?>"><?php echo htmlspecialchars($td['tipoDocumento']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Número de Documento (Opcional)</label>
                                <input type="text" name="numeroDocumentoCliente" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Correo Electrónico</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Datos faltantes del Vehículo</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Año de Fabricación</label>
                                <input type="number" name="anioFabricacion" class="form-control" value="<?php echo date('Y'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Color (Opcional)</label>
                                <input type="text" name="colorVehiculo" class="form-control">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Número de Motor (Opcional)</label>
                                <input type="text" name="numeroMotor" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Número de Chasis (Opcional)</label>
                                <input type="text" name="numeroChasis" class="form-control">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($necesitaTelefono): ?>
                    <div class="form-section">
                        <h3>Datos de Contacto Faltantes</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Teléfono (Obligatorio)</label>
                                <input type="text" name="telefono_nuevo" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-section">
                        <h3>Datos de la Orden de Trabajo</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Kilometraje de Ingreso</label>
                                <input type="number" name="km_ingreso" class="form-control" required value="0">
                            </div>
                            <div class="form-group">
                                <label>Nivel de Combustible</label>
                                <select name="nivel_combustible" class="form-control">
                                    <option value="Reserva">Reserva</option>
                                    <option value="1/4">1/4</option>
                                    <option value="1/2" selected>1/2</option>
                                    <option value="3/4">3/4</option>
                                    <option value="Lleno">Lleno</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Prioridad</label>
                                <select name="prioridad" class="form-control">
                                    <option value="7" selected>No Prioritario (Normal)</option>
                                    <option value="6">Prioritario (Urgente)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Personal Asignado (Opcional, Mín. 3 caracteres)</label>
                                <div class="search-box" style="display: flex; gap: 5px; position: relative;">
                                    <input type="text" id="buscar_personal_input"
                                        placeholder="Buscar empleado por nombre, apellido o DNI..."
                                        autocomplete="off" style="flex: 1; height: 38px; margin: 0; border: 1px solid #ccc; border-radius: 4px; padding: 10px; box-sizing: border-box;">
                                    <button type="button" id="btn_buscar_personal" class="btn-global btn-primario"
                                        style="padding: 0 15px; height: 38px; margin: 0;">Buscar</button>
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

                                <div id="badge_personal_selected"
                                    style="display: none; justify-content: space-between; align-items: center; margin-top: 10px; background: #e3f2fd; color: #004085; padding: 10px; border-radius: 6px; border: 1px solid #b8daff;">
                                    <div>
                                        <h4 style="margin: 0 0 5px 0; color: #004085; font-size: 13px;">Empleado Asignado:</h4>
                                        <span id="text_personal_selected" style="font-size: 14px;"></span>
                                    </div>
                                    <button type="button" onclick="deseleccionarPersonal()" title="Quitar asignación" style="background: #dc3545; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; line-height: 1; cursor: pointer; padding: 0;">✖</button>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Observaciones del Ingreso</label>
                                <textarea name="observacion_general" class="form-control" rows="3" style="resize:none;"><?php echo htmlspecialchars($presupuesto['notas_adicionales']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions" style="justify-content: center;">
                        <button type="submit" class="btn-save">Crear Orden de Trabajo</button>
                    </div>
                </form>
            </div>
            
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const empleados = <?php echo json_encode($personalList); ?>;
                    
                    // Lógica del Personal (Paginado y Búsqueda)
                    const inputPersonalBusqueda = document.getElementById('buscar_personal_input');
                    const btnBuscarPersonal = document.getElementById('btn_buscar_personal');
                    const resPersonal = document.getElementById('res_personal');
                    const tbodyPersonal = document.querySelector('#tabla_personal_paginada tbody');
                    const paginacionPersonal = document.getElementById('paginacion_personal');
                    const hiddenPersonalId = document.getElementById('hidden_personal_id');
                    const badgePersonalSelected = document.getElementById('badge_personal_selected');
                    const textPersonalSelected = document.getElementById('text_personal_selected');
                    const tablaContainer = document.getElementById('tabla_personal_container');

                    let empFiltrados = [...empleados];
                    let paginaEmp = 1;
                    const limitEmp = 5;

                    function renderizarPersonal() {
                        tbodyPersonal.innerHTML = '';
                        const inicio = (paginaEmp - 1) * limitEmp;
                        const fin = inicio + limitEmp;
                        const paginaDatos = empFiltrados.slice(inicio, fin);

                        if (paginaDatos.length === 0) {
                            tbodyPersonal.innerHTML = '<tr><td colspan="2" style="text-align:center; color:#888;">No hay empleados coincidentes.</td></tr>';
                            paginacionPersonal.innerHTML = '';
                            return;
                        }

                        paginaDatos.forEach(e => {
                            const tr = document.createElement('tr');
                            tr.style.cursor = 'pointer';
                            tr.innerHTML = `
                                <td>${e.numeroDocumentoPersonal}</td>
                                <td>${e.apellido}, ${e.nombre}</td>
                            `;
                            tr.onclick = () => seleccionarPersonalDirecto(e.numeroDocumentoPersonal, e.apellido, e.nombre);
                            tbodyPersonal.appendChild(tr);
                        });
                        renderPaginacionPersonal();
                    }

                    function renderPaginacionPersonal() {
                        paginacionPersonal.innerHTML = '';
                        const totalPaginas = Math.ceil(empFiltrados.length / limitEmp);
                        if (totalPaginas <= 1) return;

                        let start = Math.max(1, paginaEmp - 2);
                        let end = Math.min(totalPaginas, start + 4);
                        if (end - start < 4) start = Math.max(1, end - 4);

                        for (let i = start; i <= end; i++) {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = `page-btn ${i === paginaEmp ? 'active' : ''}`;
                            btn.textContent = i;
                            btn.onclick = () => { paginaEmp = i; renderizarPersonal(); };
                            paginacionPersonal.appendChild(btn);
                        }
                    }

                    function buscarPersonalRapido() {
                        const val = inputPersonalBusqueda.value.toLowerCase().trim();
                        if (val.length === 0) {
                            empFiltrados = [...empleados];
                        } else {
                            empFiltrados = empleados.filter(e =>
                                e.numeroDocumentoPersonal.toLowerCase().includes(val) ||
                                e.nombre.toLowerCase().includes(val) ||
                                e.apellido.toLowerCase().includes(val)
                            );
                        }
                        paginaEmp = 1;
                        renderizarPersonal();
                        resPersonal.style.display = 'none';
                    }

                    btnBuscarPersonal.addEventListener('click', buscarPersonalRapido);
                    inputPersonalBusqueda.addEventListener('keyup', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            buscarPersonalRapido();
                        } else {
                            buscarPersonalRapido();
                        }
                    });

                    window.seleccionarPersonalDirecto = function(doc, apellido, nombre) {
                        hiddenPersonalId.value = doc;
                        textPersonalSelected.innerHTML = `<strong>${doc}</strong> - ${apellido}, ${nombre}`;
                        badgePersonalSelected.style.display = 'flex';
                        tablaContainer.style.display = 'none';
                        inputPersonalBusqueda.value = '';
                        empFiltrados = [...empleados];
                        paginaEmp = 1;
                        renderizarPersonal();
                        resPersonal.style.display = 'none';
                    };

                    window.deseleccionarPersonal = function() {
                        hiddenPersonalId.value = '';
                        badgePersonalSelected.style.display = 'none';
                        tablaContainer.style.display = 'block';
                    };

                    // Inicializar
                    renderizarPersonal();
                });
            </script>
        </main>
    </div>
</body>
</html>
