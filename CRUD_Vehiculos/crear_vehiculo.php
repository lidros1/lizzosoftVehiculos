<?php
/**
 * Lizzosoft Vehículos - Alta de Vehículo (Sin imágenes)
 * Ubicación: lizzosoft_vehiculos/CRUD_Vehiculos/crear_vehiculo.php
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
if ((!in_array(8, $areas_permitidas) || !in_array(1, $funciones_permitidas)) && !$es_admin) {
    die("<div style='padding:20px; font-family:Arial; color:#721c24; background:#f8d7da;'>Error: No tienes permisos para crear Vehículos.</div>");
}

$conexion = obtenerConexion();
$mensaje = '';
$tipoMensaje = '';

try {
    $tiposDoc = $conexion->query("SELECT IDTipoDocumento, tipoDocumento FROM tiposdocumentos WHERE estado = 'Activo'")->fetchAll(PDO::FETCH_ASSOC);
    $tiposTel = $conexion->query("SELECT IDTipoNumeroTelefono, tipoNumeroTelefono FROM tiposnumerotelefono WHERE estado = 'Activo'")->fetchAll(PDO::FETCH_ASSOC);

    $clientesStmt = $conexion->prepare("SELECT IDCliente, numeroDocumentoCliente, nombre, apellido FROM clientes WHERE empresa_id = ? AND sucursal_id = ? AND estado = 'Activo' ORDER BY apellido, nombre");
    $clientesStmt->execute([$empresa_id, $sucursal_id]);
    $clientesExistentes = $clientesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "Fallo en la carga de catálogos base.";
    $tipoMensaje = "error";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $modoCliente = $_POST['modo_cliente'] ?? 'existente';

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
    $anioMax      = (int)date('Y') + 1;

    if (empty($patente) || empty($tipoVehiculo) || empty($marca) || empty($modelo) || empty($color)) {
        $mensaje = "Complete todos los campos obligatorios del vehículo.";
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
            $conexion->beginTransaction();

            if ($modoCliente === 'existente') {
                $idClienteDestino = (int)($_POST['id_cliente_existente'] ?? 0);
                if ($idClienteDestino <= 0) {
                    throw new Exception("Debe seleccionar un cliente de la lista.");
                }
            } else {
                $c_doc   = trim($_POST['c_doc'] ?? '');
                $c_nom   = strip_tags(trim($_POST['c_nombre'] ?? ''));
                $c_ape   = strip_tags(trim($_POST['c_apellido'] ?? ''));
                $c_email = trim($_POST['c_email'] ?? '');
                $c_tipo_doc = (int)($_POST['c_tipo_doc'] ?? 1);

                if (empty($c_nom) || empty($c_ape)) {
                    throw new Exception("Faltan datos del cliente (Nombre, Apellido).");
                }
                if (!preg_match("/^[\p{L}\s'\-\.]+$/u", $c_nom) || !preg_match("/^[\p{L}\s'\-\.]+$/u", $c_ape)) {
                    throw new Exception("El nombre y apellido del cliente solo pueden contener letras, espacios y guiones.");
                }
                if (!empty($c_doc) && $c_tipo_doc === 1 && !preg_match('/^[0-9]{8}$/', $c_doc)) {
                    throw new Exception("El DNI del cliente debe contener exactamente 8 dígitos numéricos.");
                }
                if (!empty($c_doc) && $c_tipo_doc === 2 && !preg_match('/^[A-Za-z0-9]{5,9}$/', $c_doc)) {
                    throw new Exception("El Pasaporte del cliente debe contener entre 5 y 9 caracteres alfanuméricos.");
                }
                $c_tel = trim($_POST['c_tel'] ?? '');
                if (!empty($c_tel) && !preg_match('/^\+?[0-9]{10,15}$/', $c_tel)) {
                    throw new Exception("El teléfono del cliente debe tener entre 10 y 15 dígitos.");
                }
                if (empty($c_email) || !filter_var($c_email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $c_email)) {
                    throw new Exception("El correo electrónico del cliente es inválido (ejemplo: usuario@dominio.com).");
                }

                $stmtCheckC = $conexion->prepare("SELECT IDCliente FROM clientes WHERE numeroDocumentoCliente = ? AND empresa_id = ? AND sucursal_id = ?");
                $stmtCheckC->execute([$c_doc, $empresa_id, $sucursal_id]);

                if ($stmtCheckC->fetch()) {
                    throw new Exception("El documento del cliente ya existe en el padrón.");
                }

                $sqlC = "INSERT INTO clientes (nombre, apellido, IDTipoDocumento, numeroDocumentoCliente, IDTipoNumeroTelefono, telefono, email, estado, empresa_id, sucursal_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'Activo', ?, ?)";
                $stmtC = $conexion->prepare($sqlC);
                $stmtC->execute([$c_nom, $c_ape, (int)$_POST['c_tipo_doc'], $c_doc, (int)$_POST['c_tipo_tel'], (int)($_POST['c_tel'] ?? 0), $c_email, $empresa_id, $sucursal_id]);
                $idClienteDestino = $conexion->lastInsertId();
            }

            $stmtCheckV = $conexion->prepare("SELECT IDVehiculo FROM vehiculos WHERE patente = ? AND empresa_id = ? AND sucursal_id = ?");
            $stmtCheckV->execute([$patente, $empresa_id, $sucursal_id]);

            if ($stmtCheckV->fetch()) {
                throw new Exception("La patente indicada ya se encuentra registrada en el sistema para esta sucursal.");
            }

            $sqlV = "INSERT INTO vehiculos (patente, numeroMotor, numeroChasis, anioFabricacion, colorVehiculo, tipoCombustible, observacionVehiculo, IDCliente, tipoVehiculo, marca, modelo, estado, sucursal_id, empresa_id) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Activo', ?, ?)";
            $stmtV = $conexion->prepare($sqlV);
            $stmtV->execute([$patente, $motor, $chasis, $anio, $color, $combustible, $observacion, $idClienteDestino, $tipoVehiculo, $marca, $modelo, $sucursal_id, $empresa_id]);

            $stmtLog = $conexion->prepare("INSERT INTO logs_accesos (IDUsuario, nombreUsuario, accion, fecha_hora, empresa_id, sucursal_id) VALUES (?, ?, ?, NOW(), ?, ?)");
            $stmtLog->execute([$_SESSION['IDUsuario'], $_SESSION['nombreUsuario'], "Alta de vehiculo: $patente", $empresa_id, $sucursal_id]);

            $conexion->commit();
            $mensaje = "Vehículo registrado correctamente.";
            $tipoMensaje = "exito";
            $_POST = [];

        } catch (Exception $e) {
            $conexion->rollBack();
            $mensaje = $e->getMessage();
            $tipoMensaje = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Vehículo</title>
    <style>
        :root { --color-primario: <?php echo $apariencia['color_primario']; ?>; --color-fondo: <?php echo $apariencia['color_fondo']; ?>; }
        body { font-family: sans-serif; background: var(--color-fondo); color: #333; margin: 0; display: flex; height: 100vh; overflow: hidden; }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        
        .wrapper { max-width: 1200px; margin: 0 auto; }
        .flex-container { display: flex; gap: 30px; flex-wrap: wrap; }
        .panel { flex: 1; min-width: 300px; border: 1px solid #eef0f2; padding: 25px; border-radius: 8px; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        
        input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }

        label { display: block; font-size: 11px; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; color: #666; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        input:focus, select:focus, textarea:focus { border-color: var(--color-primario); outline: none; }
        textarea { resize: none; min-height: 80px; }
        
        .btn-primario { background: var(--color-primario); color: white; padding: 12px 20px; border: none; cursor: pointer; border-radius: 4px; font-weight: bold; font-size: 14px; }
        .btn-secundario { background: #e2e8f0; color: #333; text-decoration: none; padding: 10px 15px; border-radius: 4px; font-weight: bold; font-size: 13px; }
        .alerta { padding: 10px; margin-bottom: 15px; font-size: 13px; font-weight: bold; border-radius: 4px; }
        .alerta-exito { background: #d4edda; color: #155724; }
        .alerta-error { background: #f8d7da; color: #721c24; }
        
        .client-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
        .client-table td { padding: 8px; border-bottom: 1px solid #eee; cursor: pointer; }
        .client-table tr:hover td { background: #f1f5f9; }
        .client-table tr.selected td { background: var(--color-primario); color: white; }
        .page-btn { border: 1px solid #ddd; padding: 4px 8px; background: white; cursor: pointer; font-size: 12px; border-radius: 3px; }
        .page-btn.active { background: var(--color-primario); color: white; border-color: var(--color-primario); pointer-events: none; }
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
                    <h2 style="color: var(--color-primario); margin:0;">Registrar Nuevo Vehículo</h2>
                    <a href="listar_vehiculos.php" style="color:var(--color-primario); text-decoration:none; font-weight:bold; font-size:14px; border:1px solid var(--color-primario); padding:6px 12px; border-radius:4px;">← Volver al Listado</a>
                </div>
        
        <?php if($mensaje): ?><div class="alerta <?php echo $tipoMensaje == 'exito' ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo $mensaje; ?></div><?php endif; ?>
        
        <form method="POST" id="formVehiculo">
            <div class="flex-container">
                <div class="panel">
                    <h3>1. Titular</h3>
                    <div style="margin-bottom:15px;">
                        <label style="display: inline-block; margin-right: 15px; cursor: pointer; text-transform: none;"><input type="radio" name="modo_cliente" value="existente" id="r_existente" checked> Existente</label>
                        <label style="display: inline-block; cursor: pointer; text-transform: none;"><input type="radio" name="modo_cliente" value="nuevo" id="r_nuevo"> Nuevo</label>
                    </div>
                    
                    <div id="bloque_existente">
                        <input type="hidden" name="id_cliente_existente" id="id_cliente_seleccionado">
                        <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                            <input type="text" id="buscador" placeholder="Buscar por nombre, apellido o doc..." style="flex: 1; padding: 8px; margin-bottom: 0;">
                            <button type="button" id="btn_buscar_cliente" class="btn-secundario" style="cursor: pointer; padding: 8px 15px;">Buscar</button>
                        </div>
                        <div style="max-height: 350px; overflow-y: auto; border: 1px solid #eee;">
                            <table class="client-table" id="tabla_clientes">
                                <tbody></tbody>
                            </table>
                        </div>
                        <div id="paginacion_clientes" style="display: flex; justify-content: center; gap: 5px; margin-top: 15px;"></div>
                    </div>
                    
                    <div id="bloque_nuevo" style="display:none;">
                        <div style="margin-bottom:10px;">
                            <label>Tipo Documento</label>
                            <select name="c_tipo_doc"><?php foreach($tiposDoc as $td) echo "<option value='{$td['IDTipoDocumento']}'>{$td['tipoDocumento']}</option>"; ?></select>
                        </div>
                        <div style="margin-bottom:10px;">
                            <label>N° Documento *</label>
                            <input type="text" name="c_doc" pattern="[0-9]+" title="Solo números" id="req_doc" placeholder="" value="<?php echo htmlspecialchars($_POST['c_doc'] ?? ''); ?>">
                        </div>
                        <div style="margin-bottom:10px;">
                            <label>Nombre *</label>
                            <input type="text" name="c_nombre" id="req_nom" placeholder="" value="<?php echo htmlspecialchars($_POST['c_nombre'] ?? ''); ?>">
                        </div>
                        <div style="margin-bottom:10px;">
                            <label>Apellido *</label>
                            <input type="text" name="c_apellido" id="req_ape" placeholder="" value="<?php echo htmlspecialchars($_POST['c_apellido'] ?? ''); ?>">
                        </div>
                        <div style="margin-bottom:10px;">
                            <label>Tipo Teléfono</label>
                            <select name="c_tipo_tel"><?php foreach($tiposTel as $tt) echo "<option value='{$tt['IDTipoNumeroTelefono']}'>{$tt['tipoNumeroTelefono']}</option>"; ?></select>
                        </div>
                        <div style="margin-bottom:10px;">
                            <label>Teléfono</label>
                            <input type="tel" name="c_tel" pattern="[0-9+\-\s]+" title="Solo números, guiones y espacios" placeholder="" value="<?php echo htmlspecialchars($_POST['c_tel'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Correo Electrónico</label>
                            <input type="email" name="c_email" placeholder="" value="<?php echo htmlspecialchars($_POST['c_email'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h3>2. Datos Técnicos</h3>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                        <div style="grid-column: 1 / -1;"><label>Patente / Dominio *</label><input type="text" name="patente" required style="font-size:16px; font-weight:bold; text-transform:uppercase;" placeholder="" value="<?php echo htmlspecialchars($_POST['patente'] ?? ''); ?>"></div>
                        <div><label>Tipo de Vehículo *</label><input type="text" name="tipo_vehiculo" required placeholder="" value="<?php echo htmlspecialchars($_POST['tipo_vehiculo'] ?? ''); ?>"></div>
                        <div><label>Color *</label><input type="text" name="color" required placeholder="" value="<?php echo htmlspecialchars($_POST['color'] ?? ''); ?>"></div>
                        <div><label>Marca *</label><input type="text" name="marca" required placeholder="" value="<?php echo htmlspecialchars($_POST['marca'] ?? ''); ?>"></div>
                        <div><label>Modelo *</label><input type="text" name="modelo" required placeholder="" value="<?php echo htmlspecialchars($_POST['modelo'] ?? ''); ?>"></div>
                        <div><label>Año</label><input type="number" name="anio" min="1900" max="2100" placeholder="" value="<?php echo htmlspecialchars($_POST['anio'] ?? ''); ?>"></div>
                        <div><label>Combustible</label><input type="text" name="combustible" placeholder="" value="<?php echo htmlspecialchars($_POST['combustible'] ?? ''); ?>"></div>
                        <div><label>N° Motor</label><input type="text" name="motor" placeholder="" value="<?php echo htmlspecialchars($_POST['motor'] ?? ''); ?>"></div>
                        <div><label>N° Chasis / VIN</label><input type="text" name="chasis" placeholder="" value="<?php echo htmlspecialchars($_POST['chasis'] ?? ''); ?>"></div>
                        <div style="grid-column: 1 / -1;"><label>Observaciones Adicionales</label><textarea name="observacion" placeholder=""><?php echo htmlspecialchars($_POST['observacion'] ?? ''); ?></textarea></div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn-primario" style="margin-top:20px; width:100%;">Procesar Ingreso de Vehículo</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const clientes = <?php echo json_encode($clientesExistentes); ?>;
        const tbody = document.querySelector('#tabla_clientes tbody');
        const hiddenId = document.getElementById('id_cliente_seleccionado');
        const buscador = document.getElementById('buscador');
        const r_existente = document.getElementById('r_existente');
        const r_nuevo = document.getElementById('r_nuevo');
        const blq_existente = document.getElementById('bloque_existente');
        const blq_nuevo = document.getElementById('bloque_nuevo');
        const paginacionDiv = document.getElementById('paginacion_clientes');

        function toggleCliente() {
            if (r_nuevo.checked) {
                blq_existente.style.display = 'none'; blq_nuevo.style.display = 'block';
                document.getElementById('req_doc').required = true;
                document.getElementById('req_nom').required = true;
                document.getElementById('req_ape').required = true;
            } else {
                blq_existente.style.display = 'block'; blq_nuevo.style.display = 'none';
                document.getElementById('req_doc').required = false;
                document.getElementById('req_nom').required = false;
                document.getElementById('req_ape').required = false;
            }
        }
        r_existente.onchange = toggleCliente;
        r_nuevo.onchange = toggleCliente;

        let clientesFiltrados = [...clientes];
        let paginaActual = 1;
        const limit = 10;

        function renderizarTabla() {
            tbody.innerHTML = '';
            const inicio = (paginaActual - 1) * limit;
            const fin = inicio + limit;
            const paginaDatos = clientesFiltrados.slice(inicio, fin);

            if(paginaDatos.length === 0) { tbody.innerHTML = '<tr><td colspan="2" style="text-align:center;">No hay resultados</td></tr>'; return; }
            
            paginaDatos.forEach(c => {
                const tr = document.createElement('tr');
                if (hiddenId.value == c.IDCliente) tr.classList.add('selected');
                tr.innerHTML = `<td>${c.numeroDocumentoCliente}</td><td><strong>${c.apellido}</strong>, ${c.nombre}</td>`;
                tr.onclick = () => {
                    document.querySelectorAll('tr').forEach(r => r.classList.remove('selected'));
                    tr.classList.add('selected');
                    hiddenId.value = c.IDCliente;
                };
                tbody.appendChild(tr);
            });
            renderizarPaginacion();
        }

        function renderizarPaginacion() {
            paginacionDiv.innerHTML = '';
            const totalPaginas = Math.ceil(clientesFiltrados.length / limit);
            if (totalPaginas <= 1) return;
            for (let i = 1; i <= totalPaginas; i++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `page-btn ${i === paginaActual ? 'active' : ''}`;
                btn.textContent = i;
                btn.onclick = () => { paginaActual = i; renderizarTabla(); };
                paginacionDiv.appendChild(btn);
            }
        }

        const normalizeString = (str) => {
            return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
        };

        document.getElementById('btn_buscar_cliente').onclick = () => {
            const val = normalizeString(buscador.value);
            clientesFiltrados = clientes.filter(c => 
                normalizeString(c.nombre).includes(val) || 
                normalizeString(c.apellido).includes(val) || 
                c.numeroDocumentoCliente.toString().includes(val)
            );
            paginaActual = 1;
            renderizarTabla();
        };

        buscador.onkeypress = (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('btn_buscar_cliente').click();
            }
        };
        
        document.getElementById('formVehiculo').onsubmit = function(e) {
            if (r_existente.checked && !hiddenId.value) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Atención',
                    text: 'Debe seleccionar un cliente de la tabla.',
                    confirmButtonColor: 'var(--color-primario)'
                });
            }
        };

        renderizarTabla();
    </script>
    </main>
</div>
</body>
</html>