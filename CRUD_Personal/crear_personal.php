<?php
/**
 * Lizzosoft Vehículos - Alta de Personal y Usuarios
 * Ubicación: lizzosoft_vehiculos/CRUD_Personal/crear_personal.php
 */

require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$config           = $_SESSION['cliente_config'];
$apariencia       = $config['apariencia'];
$empresa_id       = $_SESSION['empresa_id_usuario'];
$sucursal_id      = $_SESSION['sucursal_id'];

$areas_permitidas = $_SESSION['areas_permitidas'] ?? [];
$es_admin         = (isset($_SESSION['IDRol']) && $_SESSION['IDRol'] == 1);

$funciones_permitidas = $_SESSION['funciones_permitidas'][4] ?? [];
if ((!in_array(4, $areas_permitidas) || !in_array(1, $funciones_permitidas)) && !$es_admin) {
    die("<div style='padding:20px; font-family:Arial; color:#721c24; background:#f8d7da;'>Error: No tienes permisos para crear Personal.</div>");
}

$conexion = obtenerConexion();
$mensaje = '';
$tipoMensaje = '';

$estructura_permisos = [
    'Órdenes de Trabajo' => [
        'tipo' => 'simple', 'area_id' => 1,
        'funciones' => [1 => 'Crear OT', 2 => 'Editar OT', 3 => 'Ver OT', 13 => 'Cancelar OT'] // La función 10 se procesa por el radio button de configuración
    ],
    'Gestión de Reclamos' => [
        'tipo' => 'simple', 'area_id' => 7,
        'funciones' => [1 => 'Crear Reclamo', 2 => 'Editar Reclamo', 3 => 'Ver Reclamo']
    ],
    'Gestión de Alertas' => [
        'tipo' => 'simple', 'area_id' => 9,
        'funciones' => [1 => 'Crear Alerta', 2 => 'Editar Alerta', 3 => 'Ver Alerta']
    ],
    'Gestión de Presupuestos' => [
        'tipo' => 'simple', 'area_id' => 10,
        'funciones' => [1 => 'Crear', 2 => 'Editar', 3 => 'Ver']
    ],
    'Configuración del Sistema' => [
        'tipo' => 'agrupado',
        'sub_areas' => [
            'Personal'  => ['area_id' => 4, 'funciones' => [1 => 'Crear Personal', 2 => 'Editar Personal', 3 => 'Ver Personal']],
            'Clientes'  => ['area_id' => 5, 'funciones' => [1 => 'Crear Cliente', 2 => 'Editar Cliente', 3 => 'Ver Cliente']],
            'Servicios' => ['area_id' => 6, 'funciones' => [1 => 'Crear Servicio', 2 => 'Editar Servicio', 3 => 'Ver Servicios']],
            'Vehículos' => ['area_id' => 8, 'funciones' => [1 => 'Crear Vehículo', 2 => 'Editar Vehículo', 3 => 'Ver Vehículo']]
        ]
    ],
    'Reportes y Estadísticas' => [
        'tipo' => 'simple', 'area_id' => 2,
        'funciones' => [
            4 => 'Reporte Ingresos por Servicios', 
            5 => 'Reporte Accesos y Seguridad', 
            6 => 'Reporte Productividad del Personal'
        ]
    ]
];

try {
    $stmtRoles = $conexion->query("SELECT IDRol, nombreRol FROM roles WHERE estado = 'Activo'");
    $roles = $stmtRoles->fetchAll();
    $stmtDoc = $conexion->query("SELECT IDTipoDocumento, tipoDocumento FROM tiposdocumentos WHERE estado = 'Activo'");
    $tiposDoc = $stmtDoc->fetchAll();
    $stmtTel = $conexion->query("SELECT IDTipoNumeroTelefono, tipoNumeroTelefono FROM tiposnumerotelefono WHERE estado = 'Activo'");
    $tiposTel = $stmtTel->fetchAll();

    $stmtSinUsr = $conexion->prepare("SELECT IDPersonal, nombre, apellido, numeroDocumentoPersonal FROM personal WHERE IDUsuario IS NULL AND empresa_id = ? AND estado = 'Activo' ORDER BY apellido ASC");
    $stmtSinUsr->execute([$empresa_id]);
    $empleadosSinUsuario = $stmtSinUsr->fetchAll();

} catch (PDOException $e) {
    $mensaje = "Error al inicializar los datos del formulario.";
    $tipoMensaje = "error";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $modoIngreso = $_POST['modo_ingreso'] ?? 'nuevo';
    
    $idTipoDoc = (int)($_POST['id_tipo_documento'] ?? 1);
    $dni       = trim($_POST['dni'] ?? '');
    $nombre    = strip_tags(trim($_POST['nombre'] ?? ''));
    $apellido  = strip_tags(trim($_POST['apellido'] ?? ''));
    $idTipoTel = (int)($_POST['id_tipo_telefono'] ?? 1);
    $telefono  = trim($_POST['telefono'] ?? '');
    $email     = trim($_POST['email'] ?? '');

    $crearUsuario = isset($_POST['crear_usuario']) && $_POST['crear_usuario'] === '1';
    $idRol        = !empty($_POST['id_rol']) ? (int)$_POST['id_rol'] : null;
    $usuario      = trim($_POST['usuario'] ?? '');
    $clave        = trim($_POST['clave'] ?? '');
    $confirmar_clave = trim($_POST['confirmar_clave'] ?? '');

    try {
        if ($modoIngreso === 'existente' || $crearUsuario) {
            if ($clave !== $confirmar_clave) {
                throw new Exception("Las contraseñas no coinciden.");
            }
        }

        $conexion->beginTransaction();
        $idUsuarioInsertado = null;

        if ($modoIngreso === 'existente') {
            $idPersonalExistente = (int)($_POST['id_personal_existente'] ?? 0);
            
            if ($idPersonalExistente <= 0 || empty($usuario) || empty($clave) || empty($idRol) || empty($email)) {
                throw new Exception("Debe seleccionar un empleado y completar todos los datos de usuario.");
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
                throw new Exception("El formato del correo electrónico ingresado es inválido (ejemplo: usuario@dominio.com).");
            }

            $stmtCheck = $conexion->prepare("SELECT IDUsuario FROM personal WHERE IDPersonal = ? AND empresa_id = ?");
            $stmtCheck->execute([$idPersonalExistente, $empresa_id]);
            if ($stmtCheck->fetchColumn()) {
                throw new Exception("El empleado seleccionado ya posee una cuenta de usuario vinculada.");
            }

            $stmtCheckUser = $conexion->prepare("SELECT IDUsuario FROM usuarios WHERE nombreUsuario = ? AND empresa_id = ?");
            $stmtCheckUser->execute([$usuario, $empresa_id]);
            if ($stmtCheckUser->fetch()) {
                throw new Exception("El nombre de usuario '{$usuario}' ya se encuentra registrado.");
            }

            $sqlUser = "INSERT INTO usuarios (nombreUsuario, contraseñaUsuario, email, fechaCreacion, fechaUltimoAcceso, estado, sucursal_id, empresa_id, IDRol) VALUES (?, ?, ?, CURDATE(), NOW(), 'Activo', ?, ?, ?)";
            $stmtInsUser = $conexion->prepare($sqlUser);
            $stmtInsUser->execute([$usuario, md5($clave), $email, $sucursal_id, $empresa_id, $idRol]);
            $idUsuarioInsertado = $conexion->lastInsertId();

            if ($idRol === 4) {
                // Inyectar el permiso de Visibilidad Global de Órdenes si se seleccionó la opción correspondiente
                if (isset($_POST['visibilidad_ot']) && $_POST['visibilidad_ot'] === 'todas') {
                    $_POST['permisos'][1][] = 10;
                }

                if (isset($_POST['permisos'])) {
                    $sqlPerm = "INSERT INTO permisosusuarios (IDAreaSistema, IDFuncion, IDUsuario, estado) VALUES (?, ?, ?, 'Activo')";
                    $stmtPerm = $conexion->prepare($sqlPerm);
                    foreach ($_POST['permisos'] as $areaID => $funcionesSeleccionadas) {
                        foreach ($funcionesSeleccionadas as $funcID) {
                            $stmtPerm->execute([$areaID, $funcID, $idUsuarioInsertado]);
                        }
                    }
                }
            }

            $stmtUpdPers = $conexion->prepare("UPDATE personal SET IDUsuario = ? WHERE IDPersonal = ? AND empresa_id = ?");
            $stmtUpdPers->execute([$idUsuarioInsertado, $idPersonalExistente, $empresa_id]);

            $mensaje = "Cuenta de usuario creada y vinculada correctamente al empleado seleccionado.";
            $logAccion = "Vinculacion de cuenta: ID Empleado $idPersonalExistente";

        } else {
            if (empty($dni) || empty($nombre) || empty($apellido)) {
                throw new Exception("Error: El Documento, el Nombre y el Apellido son campos obligatorios.");
            }
            if (!preg_match("/^[\p{L}\s'\-\.]+$/u", $nombre) || !preg_match("/^[\p{L}\s'\-\.]+$/u", $apellido)) {
                throw new Exception("Error: El Nombre y Apellido solo pueden contener letras, espacios y guiones.");
            }
            if ($idTipoDoc === 1 && !preg_match('/^[0-9]{8}$/', $dni)) {
                throw new Exception("Error: El DNI debe contener exactamente 8 dígitos numéricos.");
            }
            if ($idTipoDoc === 2 && !preg_match('/^[A-Za-z0-9]{5,9}$/', $dni)) {
                throw new Exception("Error: El Pasaporte debe contener entre 5 y 9 caracteres alfanuméricos.");
            }
            if (!empty($telefono) && !preg_match('/^\+?[0-9]{10,15}$/', $telefono)) {
                throw new Exception("Error: El teléfono debe tener entre 10 y 15 dígitos, opcionalmente con prefijo + internacional.");
            }

            if ($crearUsuario) {
                if (empty($usuario) || empty($clave) || empty($idRol) || empty($email)) {
                    throw new Exception("El Correo, Rol, Usuario y Contraseña son obligatorios al crear un acceso.");
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
                    throw new Exception("El formato del correo electrónico ingresado es inválido (ejemplo: usuario@dominio.com).");
                }

                $stmtCheckUser = $conexion->prepare("SELECT IDUsuario FROM usuarios WHERE nombreUsuario = ? AND empresa_id = ?");
                $stmtCheckUser->execute([$usuario, $empresa_id]);
                if ($stmtCheckUser->fetch()) {
                    throw new Exception("El nombre de usuario '{$usuario}' ya se encuentra registrado.");
                }

                $sqlUser = "INSERT INTO usuarios (nombreUsuario, contraseñaUsuario, email, fechaCreacion, fechaUltimoAcceso, estado, sucursal_id, empresa_id, IDRol) VALUES (?, ?, ?, CURDATE(), NOW(), 'Activo', ?, ?, ?)";
                $stmtInsUser = $conexion->prepare($sqlUser);
                $stmtInsUser->execute([$usuario, md5($clave), $email, $sucursal_id, $empresa_id, $idRol]);
                $idUsuarioInsertado = $conexion->lastInsertId();

                if ($idRol === 4) {
                    if (isset($_POST['visibilidad_ot']) && $_POST['visibilidad_ot'] === 'todas') {
                        $_POST['permisos'][1][] = 10;
                    }
                    
                    if (isset($_POST['permisos'])) {
                        $sqlPerm = "INSERT INTO permisosusuarios (IDAreaSistema, IDFuncion, IDUsuario, estado) VALUES (?, ?, ?, 'Activo')";
                        $stmtPerm = $conexion->prepare($sqlPerm);
                        foreach ($_POST['permisos'] as $areaID => $funcionesSeleccionadas) {
                            foreach ($funcionesSeleccionadas as $funcID) {
                                $stmtPerm->execute([$areaID, $funcID, $idUsuarioInsertado]);
                            }
                        }
                    }
                }
            }

            $stmtCheckDni = $conexion->prepare("SELECT IDPersonal FROM personal WHERE numeroDocumentoPersonal = ? AND empresa_id = ?");
            $stmtCheckDni->execute([$dni, $empresa_id]);
            if ($stmtCheckDni->fetch()) {
                throw new Exception("El número de documento '{$dni}' ya está asignado a un miembro de este taller.");
            }

            $telefonoFinal = empty($telefono) ? 0 : (int)$telefono;

            $sqlPers = "INSERT INTO personal (numeroDocumentoPersonal, nombre, apellido, telefono, IDUsuario, IDTipoDocumento, IDTipoNumeroTelefono, estado, sucursal_id, empresa_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'Activo', ?, ?)";
            $stmtInsPers = $conexion->prepare($sqlPers);
            $stmtInsPers->execute([
                $dni, $nombre, $apellido, $telefonoFinal, $idUsuarioInsertado, $idTipoDoc, $idTipoTel, $sucursal_id, $empresa_id
            ]);

            $mensaje = "El registro del personal se guardó con éxito.";
            $logAccion = $crearUsuario ? "Alta de personal y usuario asignado: $apellido, $nombre" : "Alta de personal sin credenciales: $apellido, $nombre";
        }

        $stmtLog = $conexion->prepare("INSERT INTO logs_accesos (IDUsuario, nombreUsuario, accion, fecha_hora, empresa_id, sucursal_id) VALUES (?, ?, ?, NOW(), ?, ?)");
        $stmtLog->execute([$_SESSION['IDUsuario'], $_SESSION['nombreUsuario'], $logAccion, $empresa_id, $sucursal_id]);

        $conexion->commit();
        $tipoMensaje = "exito";
        $_POST = [];
        
        $stmtSinUsr->execute([$empresa_id]);
        $empleadosSinUsuario = $stmtSinUsr->fetchAll();

    } catch (Exception $e) {
        $conexion->rollBack();
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Personal - <?php echo htmlspecialchars($config['nombre_empresa']); ?></title>
    <style>
        :root { --color-primario: <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>; --color-secundario: <?php echo htmlspecialchars($apariencia['color_secundario'] ?? '#e74c3c'); ?>; --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>; --color-texto: <?php echo htmlspecialchars($apariencia['color_texto'] ?? '#333333'); ?>; --color-borde: #dee2e6; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: var(--color-fondo); color: var(--color-texto); display: flex; height: 100vh; overflow: hidden; }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        
        .container { max-width: 1100px; margin: 0 auto; padding: 0 20px; box-sizing: border-box; }
        .tarjeta { background: #ffffff; border-radius: 8px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .tarjeta-titulo { color: var(--color-primario); margin-top: 0; font-size: 24px; border-bottom: 2px solid var(--color-fondo); padding-bottom: 15px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;}
        .alerta { padding: 15px; border-radius: 4px; margin-bottom: 25px; font-size: 15px; }
        .alerta-exito { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alerta-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .radio-modo-container { background: #fdfdfd; padding: 15px 20px; border-radius: 6px; border: 1px solid var(--color-primario); margin-bottom: 25px; display: flex; gap: 30px; align-items: center; }
        .radio-modo-container label { font-weight: bold; color: var(--color-primario); cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 15px;}
        .radio-modo-container input { transform: scale(1.2); }

        .form-grupo { margin-bottom: 18px; }
        .form-grupo label { display: block; margin-bottom: 6px; font-weight: 500; color: #444; font-size: 14px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid var(--color-borde); border-radius: 4px; font-size: 14px; box-sizing: border-box; transition: border-color 0.2s; }
        .form-control:focus { border-color: var(--color-primario); outline: none; }
        .btn-global { display: inline-block; padding: 10px 20px; font-size: 15px; font-weight: bold; text-align: center; text-decoration: none; border: none; border-radius: 4px; cursor: pointer; transition: opacity 0.2s; }
        .btn-global:hover { opacity: 0.85; }
        .btn-primario { background-color: var(--color-primario); color: #fff; }
        .btn-secundario { background-color: #7f8c8d; color: #fff; }
        .flex-container { display: flex; flex-wrap: wrap; gap: 30px; align-items: flex-start;}
        .panel { flex: 1; min-width: 320px; border: 1px solid #eef0f2; border-radius: 6px; padding: 25px; background-color: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.02); box-sizing: border-box; }
        .panel h3 { margin-top: 0; color: var(--color-primario); border-bottom: 2px solid var(--color-fondo); padding-bottom: 10px; font-size: 18px; }
        .opcional-text { font-size: 12px; color: #888; font-weight: normal; }

        .panel-permisos { display: none; margin-top: 20px; padding-top: 15px; border-top: 1px dashed var(--color-borde); }
        .permiso-grupo { background: #fff; border: 1px solid var(--color-borde); border-radius: 4px; margin-bottom: 12px; padding: 15px; }
        .grupo-titulo { font-weight: bold; color: var(--color-primario); margin-bottom: 12px; font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .sub-grupo { margin-left: 10px; margin-bottom: 15px; background: #fafafa; border: 1px solid #eee; border-radius: 4px; padding: 10px; }
        .sub-titulo { font-size: 13px; font-weight: 600; color: #444; margin-bottom: 10px; border-bottom: 1px dashed #ddd; padding-bottom: 5px; }
        .funciones-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; }
        .funcion-item { font-size: 12px; color: #555; display: flex; align-items: center; gap: 6px; cursor: pointer; }
        .funcion-item input { margin: 0; transform: scale(1.1); }
        .chk-grupo { transform: scale(1.1); cursor: pointer; }
        
        .config-visibilidad { display: none; background: #e8f4fd; padding: 15px; border-radius: 4px; border: 1px solid #b8daff; margin-bottom: 20px; }
        .config-visibilidad label { font-weight: bold; color: #004085; font-size: 13px; display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px; text-transform: none; }
        
        /* Ocultar flechas en input number */
        input[type="number"]::-webkit-inner-spin-button, 
        input[type="number"]::-webkit-outer-spin-button { 
            -webkit-appearance: none; 
            margin: 0; 
        }
        input[type="number"] { -moz-appearance: textfield; }
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
            <div class="container" style="max-width: 1200px; margin: 0 auto;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <h2 class="tarjeta-titulo" style="border:none; margin:0; padding:0;">Crear Personal</h2>
                    <a href="listar_personal.php" style="color:var(--color-primario); text-decoration:none; font-weight:bold; font-size:14px; border:1px solid var(--color-primario); padding:6px 12px; border-radius:4px;">← Volver al Listado</a>
                </div>

            <?php if($mensaje): ?>
                <div class="alerta alerta-<?php echo $tipoMensaje; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                
                <div class="radio-modo-container">
                    <span style="color: #666; font-size: 14px; margin-right: 10px;">Modo de Registro:</span>
                    <label><input type="radio" name="modo_ingreso" value="nuevo" <?php echo (!isset($_POST['modo_ingreso']) || $_POST['modo_ingreso'] === 'nuevo') ? 'checked' : ''; ?> onchange="toggleModo()"> Crear Nuevo Empleado</label>
                    <label><input type="radio" name="modo_ingreso" value="existente" <?php echo (isset($_POST['modo_ingreso']) && $_POST['modo_ingreso'] === 'existente') ? 'checked' : ''; ?> onchange="toggleModo()"> Vincular Cuenta a Empleado Existente</label>
                </div>

                <div class="flex-container">
                    
                    <div class="panel">
                        <h3>1. Datos del Personal</h3>
                        
                        <div id="bloqueNuevoEmpleado">
                            <span class="opcional-text" style="display:block; margin-bottom:15px;">Los campos marcados con (*) son obligatorios.</span>
                            <div class="form-grupo">
                                <label>Tipo de Documento</label>
                                <select name="id_tipo_documento" class="form-control input-nuevo-pers">
                                    <?php foreach(($tiposDoc ?? []) as $td): ?>
                                        <option value="<?php echo $td['IDTipoDocumento']; ?>"><?php echo htmlspecialchars($td['tipoDocumento']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-grupo">
                                <label>DNI / Número de Documento *</label>
                                <input type="number" name="dni" class="form-control input-nuevo-pers" placeholder="..." value="<?php echo htmlspecialchars($_POST['dni'] ?? ''); ?>">
                            </div>
                            <div class="form-grupo">
                                <label>Nombre *</label>
                                <input type="text" name="nombre" class="form-control input-nuevo-pers" placeholder="..." value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
                            </div>
                            <div class="form-grupo">
                                <label>Apellido *</label>
                                <input type="text" name="apellido" class="form-control input-nuevo-pers" placeholder="..." value="<?php echo htmlspecialchars($_POST['apellido'] ?? ''); ?>">
                            </div>
                            <div style="display: flex; gap: 15px;">
                                <div class="form-grupo" style="flex: 1;">
                                    <label>Tipo Teléfono</label>
                                    <select name="id_tipo_telefono" class="form-control input-nuevo-pers">
                                        <?php foreach(($tiposTel ?? []) as $tt): ?>
                                            <option value="<?php echo $tt['IDTipoNumeroTelefono']; ?>"><?php echo htmlspecialchars($tt['tipoNumeroTelefono']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-grupo" style="flex: 2;">
                                    <label>Teléfono <span class="opcional-text">(Opcional)</span></label>
                                    <input type="tel" name="telefono" pattern="[0-9+\-\s]+" title="Solo se permiten números, espacios, guiones y el símbolo +" class="form-control" placeholder="..." value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div id="bloqueEmpleadoExistente" style="display: none;">
                            <?php if (empty($empleadosSinUsuario)): ?>
                                <div style="padding: 15px; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 4px; font-size: 14px;">
                                    Actualmente <strong>todos los empleados activos</strong> tienen una cuenta de usuario vinculada. No hay personal pendiente de asignación.
                                </div>
                            <?php else: ?>
                                <div class="form-grupo">
                                    <label>Seleccione el Empleado *</label>
                                    <select name="id_personal_existente" id="id_personal_existente" class="form-control">
                                        <option value="">-- Seleccione un empleado sin cuenta --</option>
                                        <?php foreach($empleadosSinUsuario as $su): ?>
                                            <option value="<?php echo $su['IDPersonal']; ?>"><?php echo htmlspecialchars($su['apellido'] . ', ' . $su['nombre'] . ' (DNI: ' . $su['numeroDocumentoPersonal'] . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <p style="font-size: 13px; color: #555; background: #f9f9f9; padding: 10px; border-radius: 4px; border: 1px dashed #ccc;">
                                    Al seleccionar un empleado existente, es <strong>obligatorio</strong> completar los datos del panel derecho para crearle su acceso al sistema.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="panel" style="background-color: #f8f9fa;" id="panelUsuarioMaster">
                        <h3>2. Crear Usuario <br><span class="opcional-text" id="textoOpcionalUsuario">Gestión de credenciales de ingreso al panel.</span></h3>
                        
                        <label id="labelCheckUsuario" style="display: flex; align-items: center; gap: 10px; font-weight: bold; margin-bottom: 20px; cursor: pointer; color: var(--color-secundario); padding: 10px; background: #fff; border: 1px solid var(--color-borde); border-radius: 4px; box-sizing: border-box;">
                            <input type="checkbox" name="crear_usuario" value="1" id="chkUsuario" style="width: 18px; height: 18px;" <?php echo isset($_POST['crear_usuario']) ? 'checked' : ''; ?>>
                            Habilitar cuenta de usuario
                        </label>
                        
                        <div id="camposUsuario" style="display: none; background-color: #fff; padding: 20px; border: 1px solid var(--color-borde); border-radius: 4px;">
                            
                            <div class="form-grupo">
                                <label>Correo Electrónico *</label>
                                <input type="email" name="email" class="form-control input-usuario" placeholder="..." value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>

                            <div class="form-grupo">
                                <label>Asignar Rol Base *</label>
                                <select name="id_rol" id="selectRol" class="form-control input-usuario">
                                    <option value="">-- Seleccione un Rol --</option>
                                    <?php foreach(($roles ?? []) as $r): ?>
                                        <option value="<?php echo $r['IDRol']; ?>"><?php echo htmlspecialchars($r['nombreRol']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="config-visibilidad" id="configVisibilidad">
                                <span style="display:block; font-size:12px; font-weight:bold; color:#004085; text-transform:uppercase; margin-bottom:10px;">Visibilidad de Órdenes de Trabajo</span>
                                <label><input type="radio" name="visibilidad_ot" value="todas" checked> Permitir visualizar y gestionar el tablero global (Todas las órdenes)</label>
                                <label><input type="radio" name="visibilidad_ot" value="propias"> Restringir a "Mis Órdenes Asignadas" (Al igual que un Mecánico)</label>
                            </div>

                            <div id="panelPermisos" class="panel-permisos">
                                <h4 style="margin-top:0; color:var(--color-secundario); font-size:13px; margin-bottom: 15px;">Seleccione los Permisos Específicos:</h4>
                                <div class="permisos-wrapper" style="max-height: 280px; overflow-y: auto; padding-right: 5px;">
                                    <?php foreach ($estructura_permisos as $nombre_seccion => $seccion): ?>
                                        <?php if ($seccion['tipo'] === 'simple'): ?>
                                            <div class="permiso-grupo">
                                                <div class="grupo-titulo"><label><input type="checkbox" class="chk-grupo" style="margin-right:8px;"> <?php echo htmlspecialchars($nombre_seccion); ?></label></div>
                                                <div class="funciones-grid">
                                                    <?php foreach ($seccion['funciones'] as $fId => $fNombre): ?>
                                                        <label class="funcion-item"><input type="checkbox" name="permisos[<?php echo $seccion['area_id']; ?>][]" value="<?php echo $fId; ?>"> <?php echo htmlspecialchars($fNombre); ?></label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php elseif ($seccion['tipo'] === 'agrupado'): ?>
                                            <div class="permiso-grupo">
                                                <div class="grupo-titulo"><?php echo htmlspecialchars($nombre_seccion); ?></div>
                                                <?php foreach ($seccion['sub_areas'] as $sub_nombre => $sub_area): ?>
                                                    <div class="sub-grupo">
                                                        <div class="sub-titulo"><label><input type="checkbox" class="chk-grupo" style="margin-right:8px;"> <?php echo htmlspecialchars($sub_nombre); ?></label></div>
                                                        <div class="funciones-grid" style="margin-left: 20px;">
                                                            <?php foreach ($sub_area['funciones'] as $fId => $fNombre): ?>
                                                                <label class="funcion-item"><input type="checkbox" name="permisos[<?php echo $sub_area['area_id']; ?>][]" value="<?php echo $fId; ?>"> <?php echo htmlspecialchars($fNombre); ?></label>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="form-grupo" style="margin-top: 15px;">
                                <label>Nombre de Usuario *</label>
                                <input type="text" name="usuario" class="form-control input-usuario" placeholder="..." value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>">
                            </div>
                            
                            <div style="display: flex; gap: 15px;">
                                <div class="form-grupo" style="flex: 1;">
                                    <label>Contraseña de Acceso *</label>
                                    <input type="password" name="clave" class="form-control input-usuario" placeholder="...">
                                </div>
                                <div class="form-grupo" style="flex: 1;">
                                    <label>Confirmar Contraseña *</label>
                                    <input type="password" name="confirmar_clave" class="form-control input-usuario" placeholder="...">
                                </div>
                            </div>

                            <p style="font-size: 12px; color: #666; margin-top: 15px; border-top: 1px dashed #ccc; padding-top: 10px; margin-bottom: 0;">Nota: La cuenta será vinculada a la sucursal activa: <strong><?php echo htmlspecialchars($config['nombre_sucursal']); ?></strong>.</p>
                        </div>
                    </div>

                </div>

                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid var(--color-fondo); text-align: right;">
                    <button type="submit" class="btn-global btn-primario" style="padding: 12px 35px; font-size: 16px; background-color: var(--color-primario); color: white; border: none; border-radius: 4px; cursor: pointer;">Guardar y Registrar</button>
                </div>
            </form>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chkUsuario = document.getElementById('chkUsuario');
            const camposUsuario = document.getElementById('camposUsuario');
            const inputsUsuario = document.querySelectorAll('.input-usuario');
            
            const selectRol = document.getElementById('selectRol');
            const panelPermisos = document.getElementById('panelPermisos');
            const configVisibilidad = document.getElementById('configVisibilidad');

            const radioModos = document.querySelectorAll('input[name="modo_ingreso"]');
            const bloqueNuevo = document.getElementById('bloqueNuevoEmpleado');
            const bloqueExistente = document.getElementById('bloqueEmpleadoExistente');
            const inputsNuevoPers = document.querySelectorAll('.input-nuevo-pers');
            const selectExistente = document.getElementById('id_personal_existente');
            const labelCheckUsuario = document.getElementById('labelCheckUsuario');

            function conmutarUsuario() {
                if (chkUsuario.checked) {
                    camposUsuario.style.display = 'block';
                    inputsUsuario.forEach(input => input.setAttribute('required', 'required'));
                } else {
                    camposUsuario.style.display = 'none';
                    inputsUsuario.forEach(input => input.removeAttribute('required'));
                }
            }

            function conmutarRolEspecial() {
                if (selectRol.value === "4") {
                    panelPermisos.style.display = 'block';
                    configVisibilidad.style.display = 'block';
                } else {
                    panelPermisos.style.display = 'none';
                    configVisibilidad.style.display = 'none';
                }
            }

            window.toggleModo = function() {
                const modo = document.querySelector('input[name="modo_ingreso"]:checked').value;
                if (modo === 'nuevo') {
                    bloqueNuevo.style.display = 'block';
                    bloqueExistente.style.display = 'none';
                    inputsNuevoPers.forEach(input => input.setAttribute('required', 'required'));
                    selectExistente.removeAttribute('required');
                    
                    labelCheckUsuario.style.display = 'flex';
                    chkUsuario.disabled = false;
                    conmutarUsuario();
                } else {
                    bloqueNuevo.style.display = 'none';
                    bloqueExistente.style.display = 'block';
                    inputsNuevoPers.forEach(input => input.removeAttribute('required'));
                    
                    if (document.querySelector('#id_personal_existente option').length > 1) {
                        selectExistente.setAttribute('required', 'required');
                    }

                    labelCheckUsuario.style.display = 'none';
                    chkUsuario.checked = true;
                    chkUsuario.disabled = false; 
                    conmutarUsuario();
                }
            }

            document.querySelectorAll('.chk-grupo').forEach(chk => {
                chk.addEventListener('change', function() {
                    let wrapper = this.closest('.sub-grupo');
                    if (!wrapper) wrapper = this.closest('.permiso-grupo');
                    if (wrapper) {
                        wrapper.querySelectorAll('.funciones-grid input[type="checkbox"]').forEach(f => f.checked = this.checked);
                    }
                });
            });

            chkUsuario.addEventListener('change', conmutarUsuario);
            selectRol.addEventListener('change', conmutarRolEspecial);
            
            toggleModo();
            conmutarRolEspecial();
        });
    </script>
</body>
</html>