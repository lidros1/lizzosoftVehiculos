<?php
session_start();
require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$conn = obtenerConexion();
$idSucursal = $_SESSION['sucursal_id'] ?? 1;
$temaActual = $_SESSION['tema_preferido'] ?? 'claro';
$config = $_SESSION['cliente_config'] ?? [];
$apariencia = $config['apariencia'] ?? [];

$vehiculoSingular = $config['labels']['vehiculo_singular'] ?? 'Vehículo';

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_creacion = date('Y-m-d H:i:s');
    $validez_dias = (int)($_POST['validez_dias'] ?? 15);
    $notas_adicionales = strip_tags($_POST['notas_adicionales'] ?? '');
    
    $tipo_registro = $_POST['tipo_registro'] ?? 'manual';
    
    $IDCliente = null;
    $IDVehiculo = null;
    $casual_nombre = null;
    $casual_apellido = null;
    $casual_telefono = null;
    $casual_patente = null;
    $casual_tipo_vehiculo = null;
    $casual_marca = null;
    $casual_modelo = null;

    if ($tipo_registro === 'registrado') {
        $IDCliente = !empty($_POST['IDCliente']) ? (int)$_POST['IDCliente'] : null;
        $IDVehiculo = !empty($_POST['IDVehiculo']) ? (int)$_POST['IDVehiculo'] : null;
    } else {
        $casual_nombre = strip_tags(trim($_POST['casual_nombre'] ?? ''));
        $casual_apellido = strip_tags(trim($_POST['casual_apellido'] ?? ''));
        $casual_telefono = trim($_POST['casual_telefono'] ?? '');
        $casual_patente = trim(strtoupper(strip_tags($_POST['casual_patente'] ?? '')));
        $casual_tipo_vehiculo = strip_tags(trim($_POST['casual_tipo_vehiculo'] ?? ''));
        $casual_marca = strip_tags(trim($_POST['casual_marca'] ?? ''));
        $casual_modelo = strip_tags(trim($_POST['casual_modelo'] ?? ''));
    }

    $detalles = $_POST['detalles'] ?? [];
    
    if (empty($detalles)) {
        $error = "Debe agregar al menos un ítem al presupuesto.";
    } else {
        $conn->beginTransaction();
        try {
            $total = 0;
            
            $creado_por = $_SESSION['nombreUsuario'] ?? 'Desconocido';
            $sqlPresu = "INSERT INTO presupuestos (IDSucursal, fecha_creacion, validez_dias, IDCliente, IDVehiculo, total, notas_adicionales, casual_nombre, casual_apellido, casual_telefono, casual_patente, casual_tipo_vehiculo, casual_marca, casual_modelo, creado_por) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sqlPresu);
            $stmt->execute([
                $idSucursal, $fecha_creacion, $validez_dias, $IDCliente, $IDVehiculo, $notas_adicionales,
                $casual_nombre, $casual_apellido, $casual_telefono, $casual_patente, $casual_tipo_vehiculo, $casual_marca, $casual_modelo, $creado_por
            ]);
            $idPresupuesto = $conn->lastInsertId();
            
            $sqlDet = "INSERT INTO detalle_presupuestos (IDPresupuesto, IDServicio, descripcion_libre, precio_unitario) VALUES (?, ?, ?, ?)";
            $stmtDet = $conn->prepare($sqlDet);
            
            foreach ($detalles as $d) {
                $tipo = $d['tipo'];
                $IDServicio = null;
                $desc_libre = null;
                
                $desc_libre = strip_tags($d['descripcion_libre'] ?? '');
                
                if ($tipo === 'catalogo') {
                    $IDServicio = (int)$d['IDServicio'];
                } else {
                    // Para manuales, podemos guardar "Nombre|||Detalle" si hay detalle, o solo nombre
                    $nombre_manual = strip_tags($d['nombre_manual'] ?? '');
                    $detalle_manual = strip_tags($d['descripcion_libre'] ?? '');
                    if (!empty($detalle_manual)) {
                        $desc_libre = $nombre_manual . '|||' . $detalle_manual;
                    } else {
                        $desc_libre = $nombre_manual;
                    }
                }
                
                $precio = (float)$d['precio_unitario'];
                $total += $precio;
                
                $stmtDet->execute([$idPresupuesto, $IDServicio, $desc_libre, $precio]);
            }
            
            $sqlUpdateTotal = "UPDATE presupuestos SET total = ? WHERE IDPresupuesto = ?";
            $stmtTotal = $conn->prepare($sqlUpdateTotal);
            $stmtTotal->execute([$total, $idPresupuesto]);
            
            $conn->commit();
            header("Location: listar_presupuestos.php");
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error al guardar: " . $e->getMessage();
        }
    }
}

// Cargar catálogos para JS
$empresa_id = $_SESSION['empresa_id_usuario'] ?? 1;

$stmtVehiculos = $conn->prepare("
    SELECT v.IDVehiculo, v.IDCliente, v.patente, v.marca, v.modelo, c.nombre, c.apellido, c.numeroDocumentoCliente 
    FROM vehiculos v 
    JOIN clientes c ON v.IDCliente = c.IDCliente 
    WHERE v.empresa_id = ? AND v.sucursal_id = ? AND v.estado = 'Activo'
");
$stmtVehiculos->execute([$empresa_id, $idSucursal]);
$vehiculos = $stmtVehiculos->fetchAll(PDO::FETCH_ASSOC);

$servicios = $conn->query("SELECT IDServicio, nombreServicio, costoServicio as precioBase FROM servicios WHERE empresa_id = $empresa_id AND estado = 'Activo'")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Presupuesto</title>
    <link rel="stylesheet" href="../CSS/modo_oscuro.css?v=<?php echo time(); ?>">
    <style>
        :root { 
            --color-primario: <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>; 
            --color-secundario: <?php echo htmlspecialchars($apariencia['color_secundario'] ?? '#e74c3c'); ?>; 
            --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>; 
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--color-fondo); margin: 0; display: flex; height: 100vh; overflow: hidden; color: #333; }
        
        .topbar { background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); flex-shrink: 0; z-index: 10; }
        .user-info { font-size: 13px; font-weight: 500; color: #666; }
        .btn-logout { color: var(--color-secundario); text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid var(--color-secundario); padding: 5px 15px; border-radius: 4px; transition: all 0.2s; }
        .btn-logout:hover { background: var(--color-secundario); color: #fff; }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; background-color: var(--color-fondo); }
        .wrapper-content { max-width: 1200px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        
        h2 { border-bottom: 2px solid var(--color-primario); padding-bottom: 10px; margin-top: 0; }
        
        .form-section { background: #f8f9fa; padding: 15px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 20px; }
        .form-section h3 { margin-top: 0; font-size: 16px; color: #444; border-bottom: 1px solid #ddd; padding-bottom: 8px; }
        
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 200px; position: relative; }
        .form-group label { display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #555; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
        
        /* Eliminar flechas de inputs numéricos */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }
        
        .radio-group { display: flex; gap: 15px; margin-bottom: 10px; }
        .radio-group label { font-weight: normal; font-size: 14px; cursor: pointer; }
        
        .client-table, .table-catalogo, .table-servicios { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; background: #fff; }
        .client-table th, .client-table td, .table-catalogo th, .table-catalogo td, .table-servicios th, .table-servicios td { border-bottom: 1px solid #eee; padding: 8px; text-align: left; vertical-align: middle; }
        .client-table th, .table-catalogo th, .table-servicios th { background: #f8f9fa; font-weight: bold; color: #555; font-size: 12px; text-transform: uppercase; }
        .client-table tr:hover td, .table-catalogo tbody tr:hover td { background: #f1f5f9; cursor: pointer; }
        
        .pagination-container { display: flex; justify-content: center; gap: 5px; margin-top: 10px; }
        .page-btn { border: 1px solid #ddd; padding: 4px 10px; background: white; cursor: pointer; font-size: 12px; border-radius: 3px; font-weight: bold; color: var(--color-primario); }
        .page-btn.active { background: var(--color-primario); color: white; border-color: var(--color-primario); pointer-events: none; }
        
        .grid-services { display: grid; grid-template-columns: 3fr 7fr; gap: 20px; }
        
        .btn-add-service { background: #28a745; color: white; padding: 6px 12px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 12px; }
        .btn-add-service:hover { background: #218838; }
        
        .btn-remove { background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 11px; }
        
        .btn-add-manual { background: var(--color-primario); color: white; padding: 8px 15px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 12px; margin-bottom: 10px; }
        
        .total-box { font-size: 18px; font-weight: bold; text-align: right; padding: 15px; background: #f8f9fa; border-radius: 4px; margin-top: 15px; border: 1px solid #e2e8f0; }
        
        .form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-save { background: #28a745; color: white; padding: 10px 20px; font-size: 15px; font-weight: bold; border: none; border-radius: 4px; cursor: pointer; }
        .btn-cancel { background: #6c757d; color: white; padding: 10px 20px; font-size: 15px; font-weight: bold; text-decoration: none; border-radius: 4px; }
        
        /* Tema Oscuro Específico */
        body.tema-oscuro .form-section { background: #27272a; border-color: #3f3f46; }
        body.tema-oscuro .form-section h3 { color: #fff; border-bottom-color: #3f3f46; }
        body.tema-oscuro .form-group label, body.tema-oscuro .radio-group label { color: #ddd; }
        body.tema-oscuro .form-control { background: #3f3f46; color: #fff; border-color: #52525b; }
        body.tema-oscuro .client-table th, body.tema-oscuro .table-catalogo th, body.tema-oscuro .table-servicios th { background: #3f3f46; color: #fff; border-bottom-color: #52525b; }
        body.tema-oscuro .client-table td, body.tema-oscuro .table-catalogo td, body.tema-oscuro .table-servicios td { border-bottom-color: #52525b; background: #27272a; color: #fff; }
        body.tema-oscuro .client-table tr:hover td, body.tema-oscuro .table-catalogo tbody tr:hover td { background: #3f3f46; }
        body.tema-oscuro .total-box { background: #27272a; color: #fff; border-color: #3f3f46; }
        body.tema-oscuro .page-btn { background: #3f3f46; border-color: #52525b; color: #fff; }
        body.tema-oscuro .page-btn.active { background: var(--color-primario); }
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
            <div class="wrapper-content">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid var(--color-primario); padding-bottom: 15px; margin-bottom: 20px;">
                    <h2 style="margin:0; color: var(--color-primario);">Crear Presupuesto</h2>
                    <a href="listar_presupuestos.php" style="background: #e2e8f0; color: #333; text-decoration: none; padding: 8px 15px; border-radius: 4px; font-weight: bold; font-size: 13px;">Volver</a>
                </div>
                
                <?php if($error): ?><div style="background:#f8d7da; color:#721c24; padding:10px; border-radius:4px; margin-bottom:15px;"><?php echo $error; ?></div><?php endif; ?>
                
                <form method="POST" id="formPresupuesto" onsubmit="return validarFormulario()">
                    
                    <div class="form-section">
                        <h3>Datos Generales</h3>
                        <div class="form-row">
                            <div class="form-group" style="flex: 0 0 200px;">
                                <label>Validez (Días)</label>
                                <input type="number" name="validez_dias" class="form-control" value="15" min="1" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Datos del Cliente y Vehículo</h3>
                        <div class="radio-group">
                            <label><input type="radio" name="tipo_registro" value="manual" checked onchange="toggleRegistro()"> Cliente/Vehículo Ocasional</label>
                            <label><input type="radio" name="tipo_registro" value="registrado" onchange="toggleRegistro()"> Buscar Existente</label>
                        </div>
                        
                        <!-- Bloque Búsqueda de Existentes -->
                        <div id="box_registrado" style="display:none; margin-top:15px;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Buscar Vehículo Registrado</label>
                                    <input type="text" id="buscador_vehiculo" class="form-control" placeholder="Escriba la patente, marca o modelo..." autocomplete="off">
                                </div>
                            </div>
                            
                            <table class="client-table" id="tabla_vehiculos">
                                <thead>
                                    <tr>
                                        <th style="width:15%">Patente</th>
                                        <th style="width:40%">Vehículo</th>
                                        <th style="width:45%">Cliente</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                            <div id="paginacion_vehiculos" class="pagination-container"></div>
                            
                            <input type="hidden" name="IDVehiculo" id="IDVehiculo">
                            <input type="hidden" name="IDCliente" id="IDCliente">
                            
                            <div id="vehiculo_seleccionado" style="margin-top:15px; padding:15px; background:rgba(0,0,0,0.05); border-radius:4px; display:none; border:1px solid var(--color-primario);">
                                <div style="display:flex; justify-content:space-between;">
                                    <div>
                                        <h4 style="margin:0 0 5px 0; color:var(--color-primario);">Selección Confirmada</h4>
                                        <strong>Vehículo:</strong> <span id="lblVeh"></span><br>
                                        <strong>Cliente:</strong> <span id="lblCli"></span>
                                    </div>
                                    <button type="button" class="btn-remove" onclick="deseleccionarVehiculo()">Cambiar</button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bloque Ocasional/Manual -->
                        <div id="box_manual">
                            <h4 style="margin-bottom:10px; color:#555; border-bottom:1px solid #eee; padding-bottom:5px;">Datos del Cliente</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nombre</label>
                                    <input type="text" name="casual_nombre" class="form-control" placeholder="">
                                </div>
                                <div class="form-group">
                                    <label>Apellido</label>
                                    <input type="text" name="casual_apellido" class="form-control" placeholder="">
                                </div>
                                <div class="form-group">
                                    <label>Teléfono (Opcional)</label>
                                    <input type="text" name="casual_telefono" class="form-control" placeholder="">
                                </div>
                            </div>
                            
                            <h4 style="margin-bottom:10px; margin-top:20px; color:#555; border-bottom:1px solid #eee; padding-bottom:5px;">Datos del Vehículo</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Patente</label>
                                    <input type="text" name="casual_patente" class="form-control" style="text-transform:uppercase;" placeholder="">
                                </div>
                                <div class="form-group">
                                    <label>Tipo</label>
                                    <?php if(in_array(strtolower($vehiculoSingular), ['moto', 'camión', 'camion', 'trafic'])): ?>
                                        <input type="text" name="casual_tipo_vehiculo" class="form-control" value="<?php echo htmlspecialchars($vehiculoSingular); ?>" readonly style="background:#e9ecef; cursor:not-allowed;">
                                    <?php else: ?>
                                        <select name="casual_tipo_vehiculo" class="form-control">
                                            <option value="Auto">Auto</option>
                                            <option value="Camioneta">Camioneta</option>
                                        </select>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Marca</label>
                                    <input type="text" name="casual_marca" class="form-control" placeholder="">
                                </div>
                                <div class="form-group">
                                    <label>Modelo</label>
                                    <input type="text" name="casual_modelo" class="form-control" placeholder="">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Detalle de servicios</h3>
                        <div class="grid-services">
                            <!-- Catálogo (Izquierda) -->
                            <div>
                                <div class="form-group" style="margin-bottom: 10px;">
                                    <label>Buscar en Catálogo de Servicios</label>
                                    <input type="text" id="buscador_servicios" class="form-control" placeholder="Filtrar servicios por nombre..." autocomplete="off">
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
                            
                            <!-- Asignados (Derecha) -->
                            <div style="min-width: 0;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 5px; flex-wrap: wrap; gap: 10px;">
                                    <h4 style="margin:0; color:var(--color-primario); font-size: 14px;">Servicios Asignados al Presupuesto</h4>
                                    <button type="button" class="btn-add-manual" style="margin:0;" onclick="agregarServicioManual()">+ Añadir Servicio Manual</button>
                                </div>
                                
                                <div id="servicios_asignados_container" style="overflow-x: hidden;">
                                    <table class="table-servicios" id="tabla_asignados" style="margin-top:0; table-layout: fixed; width: 100%;">
                                        <thead>
                                            <tr>
                                                <th style="width: 30%;">SERVICIO SELECCIONADO</th>
                                                <th style="width: 15%; text-align: center;">COSTO ($)</th>
                                                <th style="width: 45%;">DETALLES ADICIONALES DEL PROCEDIMIENTO</th>
                                                <th style="width: 10%; text-align: center;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr id="row_vacia"><td colspan="4" style="text-align: center; color: #888; padding: 20px;">No ha seleccionado ningún servicio.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="total-box">
                                    Total: $<span id="lblTotal">0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Notas Adicionales</h3>
                        <textarea name="notas_adicionales" class="form-control" rows="3" style="resize: none;"></textarea>
                    </div>

                    <div class="form-actions" style="justify-content: center;">
                        <button type="submit" class="btn-save">Guardar Presupuesto</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        const vehiculosDB = <?php echo json_encode($vehiculos); ?>;
        const serviciosDB = <?php echo json_encode($servicios); ?>;
        let rowIndex = 0;

        // --- Lógica del Registro Ocasional vs Existente ---
        function toggleRegistro() {
            const val = document.querySelector('input[name="tipo_registro"]:checked').value;
            if (val === 'manual') {
                document.getElementById('box_manual').style.display = 'block';
                document.getElementById('box_registrado').style.display = 'none';
            } else {
                document.getElementById('box_manual').style.display = 'none';
                document.getElementById('box_registrado').style.display = 'block';
                renderizarVehiculos(); // Cargar la tabla al instante
            }
        }

        // --- Paginación y Filtrado de Vehículos Existentes ---
        let pagVehiculo = 1;
        const limitVeh = 5;
        let vehiculosFiltrados = vehiculosDB;

        document.getElementById('buscador_vehiculo').addEventListener('input', function() {
            const q = this.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            vehiculosFiltrados = vehiculosDB.filter(v => {
                const searchStr = `${v.patente} ${v.marca} ${v.modelo} ${v.nombre} ${v.apellido} ${v.numeroDocumentoCliente}`.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                return searchStr.includes(q);
            });
            pagVehiculo = 1;
            renderizarVehiculos();
        });

        function renderizarVehiculos() {
            const tbody = document.querySelector('#tabla_vehiculos tbody');
            tbody.innerHTML = '';
            
            const start = (pagVehiculo - 1) * limitVeh;
            const end = start + limitVeh;
            const pagina = vehiculosFiltrados.slice(start, end);

            if(pagina.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; color:#888;">No se encontraron vehículos.</td></tr>';
            } else {
                pagina.forEach(v => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong>${v.patente}</strong></td>
                        <td>${v.marca} ${v.modelo}</td>
                        <td>${v.nombre} ${v.apellido} (${v.numeroDocumentoCliente})</td>
                    `;
                    tr.onclick = () => seleccionarVehiculo(v);
                    tbody.appendChild(tr);
                });
            }
            renderPaginacionVehiculos();
        }

        function renderPaginacionVehiculos() {
            const container = document.getElementById('paginacion_vehiculos');
            container.innerHTML = '';
            const totalPaginas = Math.ceil(vehiculosFiltrados.length / limitVeh);
            if (totalPaginas <= 1) return;

            let startP = Math.max(1, pagVehiculo - 2);
            let endP = Math.min(totalPaginas, startP + 4);
            if(endP - startP < 4) startP = Math.max(1, endP - 4);

            for(let i = startP; i <= endP; i++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `page-btn ${i === pagVehiculo ? 'active' : ''}`;
                btn.textContent = i;
                btn.onclick = () => { pagVehiculo = i; renderizarVehiculos(); };
                container.appendChild(btn);
            }
        }

        function seleccionarVehiculo(v) {
            document.getElementById('IDVehiculo').value = v.IDVehiculo;
            document.getElementById('IDCliente').value = v.IDCliente;
            document.getElementById('lblVeh').innerText = `${v.patente} - ${v.marca} ${v.modelo}`;
            document.getElementById('lblCli').innerText = `${v.nombre} ${v.apellido} (${v.numeroDocumentoCliente})`;
            
            document.getElementById('tabla_vehiculos').style.display = 'none';
            document.getElementById('paginacion_vehiculos').style.display = 'none';
            document.getElementById('buscador_vehiculo').style.display = 'none';
            document.getElementById('buscador_vehiculo').previousElementSibling.style.display = 'none'; // label
            document.getElementById('vehiculo_seleccionado').style.display = 'block';
        }

        function deseleccionarVehiculo() {
            document.getElementById('IDVehiculo').value = '';
            document.getElementById('IDCliente').value = '';
            document.getElementById('tabla_vehiculos').style.display = 'table';
            document.getElementById('paginacion_vehiculos').style.display = 'flex';
            document.getElementById('buscador_vehiculo').style.display = 'block';
            document.getElementById('buscador_vehiculo').previousElementSibling.style.display = 'block';
            document.getElementById('vehiculo_seleccionado').style.display = 'none';
            document.getElementById('buscador_vehiculo').value = '';
            vehiculosFiltrados = vehiculosDB;
            pagVehiculo = 1;
            renderizarVehiculos();
        }

        // --- Paginación y Filtrado del Catálogo de Servicios ---
        let pagServicio = 1;
        const limitSrv = 5;
        let serviciosFiltrados = serviciosDB;

        document.getElementById('buscador_servicios').addEventListener('input', function() {
            const q = this.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            serviciosFiltrados = serviciosDB.filter(s => s.nombreServicio.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").includes(q));
            pagServicio = 1;
            renderizarCatalogo();
        });

        function renderizarCatalogo() {
            const tbody = document.querySelector('#tabla_catalogo tbody');
            tbody.innerHTML = '';
            
            const start = (pagServicio - 1) * limitSrv;
            const end = start + limitSrv;
            const pagina = serviciosFiltrados.slice(start, end);

            if(pagina.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; color:#888;">No se encontraron servicios.</td></tr>';
            } else {
                pagina.forEach(s => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong>${s.nombreServicio}</strong></td>
                        <td>$ ${parseFloat(s.precioBase).toLocaleString('es-AR', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                        <td style="text-align:center;">
                            <button type="button" class="btn-add-service" onclick="agregarServicioCatalogo(${s.IDServicio}, '${s.nombreServicio.replace(/'/g,"\\'").replace(/"/g,"&quot;")}', ${s.precioBase})">Agregar</button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }
            renderPaginacionCatalogo();
        }

        function renderPaginacionCatalogo() {
            const container = document.getElementById('paginacion_catalogo');
            container.innerHTML = '';
            const totalPaginas = Math.ceil(serviciosFiltrados.length / limitSrv);
            if (totalPaginas <= 1) return;

            let startP = Math.max(1, pagServicio - 2);
            let endP = Math.min(totalPaginas, startP + 4);
            if(endP - startP < 4) startP = Math.max(1, endP - 4);

            for(let i = startP; i <= endP; i++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `page-btn ${i === pagServicio ? 'active' : ''}`;
                btn.textContent = i;
                btn.onclick = () => { pagServicio = i; renderizarCatalogo(); };
                container.appendChild(btn);
            }
        }

        // --- Gestión de Servicios Asignados ---
        function revisarFilaVacia() {
            const tbody = document.querySelector('#tabla_asignados tbody');
            const rowVacia = document.getElementById('row_vacia');
            const filasActivas = tbody.querySelectorAll('tr.fila-servicio');
            if (filasActivas.length > 0) {
                if (rowVacia) rowVacia.style.display = 'none';
            } else {
                if (rowVacia) rowVacia.style.display = 'table-row';
            }
        }

        function recalcularTotal() {
            let sum = 0;
            document.querySelectorAll('.input-costo').forEach(input => {
                sum += (parseFloat(input.value) || 0);
            });
            document.getElementById('lblTotal').innerText = sum.toFixed(2);
        }

        function agregarServicioCatalogo(id, nombre, costo) {
            const tbody = document.querySelector('#tabla_asignados tbody');
            const tr = document.createElement('tr');
            tr.className = 'fila-servicio';
            tr.innerHTML = `
                <td style="padding-right: 10px;">
                    <input type="hidden" name="detalles[${rowIndex}][tipo]" value="catalogo">
                    <input type="hidden" name="detalles[${rowIndex}][IDServicio]" value="${id}">
                    <strong style="color:var(--color-primario);">${nombre}</strong>
                </td>
                <td style="padding-right: 10px;">
                    <input type="number" class="form-control input-costo" name="detalles[${rowIndex}][precio_unitario]" value="${parseFloat(costo)}" step="0.01" oninput="recalcularTotal()" style="width: 100%; box-sizing: border-box; text-align: right; background: #fff;">
                </td>
                <td style="padding-right: 10px;">
                    <input type="text" class="form-control" name="detalles[${rowIndex}][descripcion_libre]" placeholder="Detalles adicionales del procedimiento..." style="font-size: 13px; padding: 8px; width: 100%; box-sizing: border-box; background: #fff;">
                </td>
                <td style="text-align:center;">
                    <button type="button" class="btn-remove" onclick="this.closest('tr').remove(); revisarFilaVacia(); recalcularTotal();" title="Quitar Servicio" style="border-radius: 50%; width: 24px; height: 24px; padding: 0; line-height: 1;">✖</button>
                </td>
            `;
            tbody.appendChild(tr);
            rowIndex++;
            revisarFilaVacia();
            recalcularTotal();
        }

        function agregarServicioManual() {
            const tbody = document.querySelector('#tabla_asignados tbody');
            const tr = document.createElement('tr');
            tr.className = 'fila-servicio';
            tr.innerHTML = `
                <td style="padding-right: 10px;">
                    <input type="hidden" name="detalles[${rowIndex}][tipo]" value="manual">
                    <input type="text" class="form-control" name="detalles[${rowIndex}][nombre_manual]" placeholder="Nombre del servicio manual..." required style="font-size: 13px; width: 100%; box-sizing: border-box; font-weight: bold; color: var(--color-primario); background: #fff;">
                </td>
                <td style="padding-right: 10px;">
                    <input type="number" class="form-control input-costo" name="detalles[${rowIndex}][precio_unitario]" value="0" step="0.01" oninput="recalcularTotal()" style="width: 100%; box-sizing: border-box; text-align: right; background: #fff;">
                </td>
                <td style="padding-right: 10px;">
                    <input type="text" class="form-control" name="detalles[${rowIndex}][descripcion_libre]" placeholder="Detalles adicionales del procedimiento..." style="font-size: 13px; padding: 8px; width: 100%; box-sizing: border-box; background: #fff;">
                </td>
                <td style="text-align:center;">
                    <button type="button" class="btn-remove" onclick="this.closest('tr').remove(); revisarFilaVacia(); recalcularTotal();" title="Quitar Servicio" style="border-radius: 50%; width: 24px; height: 24px; padding: 0; line-height: 1;">✖</button>
                </td>
            `;
            tbody.appendChild(tr);
            rowIndex++;
            revisarFilaVacia();
            recalcularTotal();
        }

        function validarFormulario() {
            const tbody = document.querySelector('#tabla_asignados tbody');
            const filasActivas = tbody.querySelectorAll('tr.fila-servicio');
            if (filasActivas.length === 0) {
                alert("Debes asignar al menos un servicio al presupuesto.");
                return false;
            }
            
            const isManual = document.querySelector('input[name="tipo_registro"]:checked').value === 'manual';
            if (!isManual) {
                const idVeh = document.getElementById('IDVehiculo').value;
                if (!idVeh) {
                    alert("Debe buscar y seleccionar un vehículo existente.");
                    return false;
                }
            }
            return true;
        }

        // Init
        window.onload = function() {
            renderizarCatalogo();
        };
    </script>
</body>
</html>
