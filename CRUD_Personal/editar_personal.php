<?php
/**
 * Lizzosoft Vehículos - Edición de Personal y Usuarios (Permisos Dinámicos)
 * Ubicación: lizzosoft_vehiculos/CRUD_Personal/editar_personal.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$config           = $_SESSION['cliente_config'];
$apariencia       = $config['apariencia'];
$empresa_id       = $_SESSION['empresa_id_usuario'];
$sucursal_id      = $_SESSION['sucursal_id'];

$areas_permitidas = $_SESSION['areas_permitidas'] ?? [];
$es_admin         = (isset($_SESSION['IDRol']) && $_SESSION['IDRol'] == 1);

$funciones_permitidas = $_SESSION['funciones_permitidas'][4] ?? [];
if ((!in_array(4, $areas_permitidas) || !in_array(2, $funciones_permitidas)) && !$es_admin) {
    die("<div style='padding:20px; font-family:Arial; color:#721c24; background:#f8d7da;'>Error: No tienes permisos para editar Personal.</div>");
}

$conexion = obtenerConexion();
$mensaje = '';
$tipoMensaje = '';

$idPersonal = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idPersonal <= 0) { header("Location: listar_personal.php"); exit; }

$estructura_permisos = [
    'Órdenes de Trabajo' => [
        'tipo' => 'simple', 'area_id' => 1,
        'funciones' => [1 => 'Crear OT', 2 => 'Editar OT', 3 => 'Ver OT']
    ],
    'Gestión de Reclamos' => [
        'tipo' => 'simple', 'area_id' => 7,
        'funciones' => [1 => 'Crear Reclamo', 2 => 'Editar Reclamo', 3 => 'Ver Reclamo']
    ],
    'Gestión de Alertas' => [
        'tipo' => 'simple', 'area_id' => 9,
        'funciones' => [1 => 'Crear Alerta', 2 => 'Editar Alerta', 3 => 'Ver Alerta']
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
} catch (PDOException $e) {
    $mensaje = "Error al cargar los catálogos del sistema.";
    $tipoMensaje = "error";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idTipoDoc   = (int)($_POST['id_tipo_documento'] ?? 1);
    $dni         = trim($_POST['dni'] ?? '');
    $nombre      = strip_tags(trim($_POST['nombre'] ?? ''));
    $apellido    = strip_tags(trim($_POST['apellido'] ?? ''));
    $idTipoTel   = (int)($_POST['id_tipo_telefono'] ?? 1);
    $telefono    = trim($_POST['telefono'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $estadoPers  = trim($_POST['estado_personal'] ?? 'Activo');

    $usuario     = trim($_POST['usuario'] ?? '');
    $claveActual = trim($_POST['clave_actual'] ?? '');
    $claveNueva  = trim($_POST['clave_nueva'] ?? '');
    $confirmarClaveNueva = trim($_POST['confirmar_clave_nueva'] ?? '');
    $idRol       = !empty($_POST['id_rol']) ? (int)$_POST['id_rol'] : null;
    $crearUsuarioNuevo = isset($_POST['crear_usuario']) && $_POST['crear_usuario'] === '1';

    if (!empty($claveNueva) && $claveNueva !== $confirmarClaveNueva) {
        $mensaje = "Error: Las contraseñas no coinciden.";
        $tipoMensaje = "error";
    } elseif (empty($dni) || empty($nombre) || empty($apellido)) {
        $mensaje = "Error: El Documento, el Nombre y el Apellido son campos obligatorios.";
        $tipoMensaje = "error";
    } elseif (!preg_match("/^[\p{L}\s'\-\.]+$/u", $nombre) || !preg_match("/^[\p{L}\s'\-\.]+$/u", $apellido)) {
        $mensaje = "Error: El Nombre y Apellido solo pueden contener letras, espacios y guiones.";
        $tipoMensaje = "error";
    } elseif ($idTipoDoc === 1 && !preg_match('/^[0-9]{8}$/', $dni)) {
        $mensaje = "Error: El DNI debe contener exactamente 8 dígitos numéricos.";
        $tipoMensaje = "error";
    } elseif ($idTipoDoc === 2 && !preg_match('/^[A-Za-z0-9]{5,9}$/', $dni)) {
        $mensaje = "Error: El Pasaporte debe contener entre 5 y 9 caracteres alfanuméricos.";
        $tipoMensaje = "error";
    } elseif (!empty($telefono) && !preg_match('/^\+?[0-9]{10,15}$/', $telefono)) {
        $mensaje = "Error: El teléfono debe tener entre 10 y 15 dígitos, opcionalmente con prefijo + internacional.";
        $tipoMensaje = "error";
    } else {
        try {
            $conexion->beginTransaction();

            // Evitar DNI duplicado
            $stmtCheckDni = $conexion->prepare("SELECT IDPersonal FROM personal WHERE numeroDocumentoPersonal = ? AND empresa_id = ? AND IDPersonal != ?");
            $stmtCheckDni->execute([$dni, $empresa_id, $idPersonal]);
            if ($stmtCheckDni->fetch()) {
                throw new Exception("El número de documento ingresado ya está asignado a otro miembro del personal.");
            }

            // Validar Estado del Usuario Vinculado (si lo tiene)
            $stmtGetUsuario = $conexion->prepare("SELECT IDUsuario FROM personal WHERE IDPersonal = ? AND empresa_id = ?");
            $stmtGetUsuario->execute([$idPersonal, $empresa_id]);
            $idUsuarioVinculado = $stmtGetUsuario->fetchColumn();

            // -----------------------------------------------------------------
            // CASO A: EL EMPLEADO YA TENÍA USUARIO ASIGNADO (EDITAR)
            // -----------------------------------------------------------------
            if ($idUsuarioVinculado) {
                if (!empty($usuario)) {
                    $stmtCheckUser = $conexion->prepare("SELECT IDUsuario FROM usuarios WHERE nombreUsuario = ? AND empresa_id = ? AND IDUsuario != ?");
                    $stmtCheckUser->execute([$usuario, $empresa_id, $idUsuarioVinculado]);
                    if ($stmtCheckUser->fetch()) throw new Exception("El nombre de usuario ya se encuentra registrado para otra cuenta.");
                }

                if (!empty($claveNueva)) {
                    if (empty($claveActual)) throw new Exception("Debe ingresar la contraseña actual para confirmar el cambio de clave.");
                    $stmtPass = $conexion->prepare("SELECT contraseñaUsuario FROM usuarios WHERE IDUsuario = ?");
                    $stmtPass->execute([$idUsuarioVinculado]);
                    $passBD = $stmtPass->fetchColumn();
                    if (md5($claveActual) !== $passBD) throw new Exception("La contraseña actual ingresada es incorrecta.");
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) throw new Exception("El formato del correo electrónico ingresado es inválido (ejemplo: usuario@dominio.com).");

                    $sqlUpdUser = "UPDATE usuarios SET nombreUsuario = ?, contraseñaUsuario = ?, IDRol = ?, estado = ?, email = ? WHERE IDUsuario = ? AND empresa_id = ?";
                    $stmtUpdUser->execute([$usuario, md5($claveNueva), $idRol, $estadoPers, $email, $idUsuarioVinculado, $empresa_id]);
                } else {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) throw new Exception("El formato del correo electrónico ingresado es inválido (ejemplo: usuario@dominio.com).");
                    $sqlUpdUser = "UPDATE usuarios SET nombreUsuario = ?, IDRol = ?, estado = ?, email = ? WHERE IDUsuario = ? AND empresa_id = ?";
                    $stmtUpdUser = $conexion->prepare($sqlUpdUser);
                    $stmtUpdUser->execute([$usuario, $idRol, $estadoPers, $email, $idUsuarioVinculado, $empresa_id]);
                }

                $stmtDel = $conexion->prepare("DELETE FROM permisosusuarios WHERE IDUsuario = ?");
                $stmtDel->execute([$idUsuarioVinculado]);
                
                if ($idRol === 4 && isset($_POST['permisos'])) {
                    $sqlPerm = "INSERT INTO permisosusuarios (IDAreaSistema, IDFuncion, IDUsuario, estado) VALUES (?, ?, ?, 'Activo')";
                    $stmtPerm = $conexion->prepare($sqlPerm);
                    foreach ($_POST['permisos'] as $areaID => $funcionesSeleccionadas) {
                        foreach ($funcionesSeleccionadas as $funcID) {
                            $stmtPerm->execute([$areaID, $funcID, $idUsuarioVinculado]);
                        }
                    }
                }

            // -----------------------------------------------------------------
            // CASO B: EL EMPLEADO NO TENÍA USUARIO Y SE SOLICITA CREAR UNO NUEVO
            // -----------------------------------------------------------------
            } elseif (!$idUsuarioVinculado && $crearUsuarioNuevo) {
                if (empty($usuario) || empty($claveNueva) || empty($idRol) || empty($email)) {
                    throw new Exception("Debe completar Correo, Rol, Usuario y Contraseña para habilitar la cuenta.");
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
                    throw new Exception("El formato del correo electrónico ingresado es inválido (ejemplo: usuario@dominio.com).");
                }

                $stmtCheckUser = $conexion->prepare("SELECT IDUsuario FROM usuarios WHERE nombreUsuario = ? AND empresa_id = ?");
                $stmtCheckUser->execute([$usuario, $empresa_id]);
                if ($stmtCheckUser->fetch()) throw new Exception("El nombre de usuario '{$usuario}' ya se encuentra registrado.");

                $sqlUser = "INSERT INTO usuarios (nombreUsuario, contraseñaUsuario, email, fechaCreacion, fechaUltimoAcceso, estado, sucursal_id, empresa_id, IDRol) VALUES (?, ?, ?, CURDATE(), NOW(), 'Activo', ?, ?, ?)";
                $stmtInsUser = $conexion->prepare($sqlUser);
                $stmtInsUser->execute([$usuario, md5($claveNueva), $email, $sucursal_id, $empresa_id, $idRol]);
                $idUsuarioVinculado = $conexion->lastInsertId(); // Lo reasignamos para que la tabla 'personal' lo guarde abajo

                if ($idRol === 4 && isset($_POST['permisos'])) {
                    $sqlPerm = "INSERT INTO permisosusuarios (IDAreaSistema, IDFuncion, IDUsuario, estado) VALUES (?, ?, ?, 'Activo')";
                    $stmtPerm = $conexion->prepare($sqlPerm);
                    foreach ($_POST['permisos'] as $areaID => $funcionesSeleccionadas) {
                        foreach ($funcionesSeleccionadas as $funcID) {
                            $stmtPerm->execute([$areaID, $funcID, $idUsuarioVinculado]);
                        }
                    }
                }
            }

            // -----------------------------------------------------------------
            // FINALMENTE, ACTUALIZAMOS LOS DATOS DEL EMPLEADO (AMBOS CASOS)
            // -----------------------------------------------------------------
            $telefonoFinal = empty($telefono) ? 0 : (int)$telefono;
            $usuarioQueryVal = $idUsuarioVinculado ? $idUsuarioVinculado : null;

            $sqlPers = "UPDATE personal SET numeroDocumentoPersonal = ?, nombre = ?, apellido = ?, telefono = ?, IDUsuario = ?, IDTipoDocumento = ?, IDTipoNumeroTelefono = ?, estado = ? WHERE IDPersonal = ? AND empresa_id = ?";
            $stmtInsPers = $conexion->prepare($sqlPers);
            $stmtInsPers->execute([
                $dni, $nombre, $apellido, $telefonoFinal, $usuarioQueryVal, $idTipoDoc, $idTipoTel, $estadoPers, $idPersonal, $empresa_id
            ]);

            $stmtLog = $conexion->prepare("INSERT INTO logs_accesos (IDUsuario, nombreUsuario, accion, fecha_hora, empresa_id, sucursal_id) VALUES (?, ?, ?, NOW(), ?, ?)");
            $stmtLog->execute([$_SESSION['IDUsuario'], $_SESSION['nombreUsuario'], "Modificacion de datos de personal: $apellido, $nombre", $empresa_id, $sucursal_id]);

            $conexion->commit();
            $mensaje = "Los cambios se guardaron con éxito.";
            $tipoMensaje = "exito";

        } catch (Exception $e) {
            $conexion->rollBack();
            $mensaje = $e->getMessage();
            $tipoMensaje = "error";
        }
    }
}

// Cargar Datos Actualizados para Pintar el Formulario
try {
    $stmtData = $conexion->prepare("SELECT p.*, u.nombreUsuario, u.IDRol, u.email FROM personal p LEFT JOIN usuarios u ON p.IDUsuario = u.IDUsuario WHERE p.IDPersonal = ? AND p.empresa_id = ?");
    $stmtData->execute([$idPersonal, $empresa_id]);
    $empleado = $stmtData->fetch();

    if (!$empleado) {
        die("<div style='padding:20px; font-family:Arial; color:#721c24; background:#f8d7da;'>Error: No se encontró el registro del personal solicitado.</div>");
    }

    $permisosUsuario = [];
    if (!empty($empleado['IDUsuario'])) {
        $stmtPerms = $conexion->prepare("SELECT IDAreaSistema, IDFuncion FROM permisosusuarios WHERE IDUsuario = ?");
        $stmtPerms->execute([$empleado['IDUsuario']]);
        while ($row = $stmtPerms->fetch(PDO::FETCH_ASSOC)) {
            $permisosUsuario[$row['IDAreaSistema']][] = $row['IDFuncion'];
        }
    }

    function tienePermiso($areaId, $funcId, $permisosUsuario) {
        return isset($permisosUsuario[$areaId]) && in_array($funcId, $permisosUsuario[$areaId]);
    }

} catch (PDOException $e) {
    die("Error al consultar los datos del personal.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Personal - <?php echo htmlspecialchars($config['nombre_empresa']); ?></title>
    <style>
        :root { --color-primario: <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>; --color-secundario: <?php echo htmlspecialchars($apariencia['color_secundario'] ?? '#e74c3c'); ?>; --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>; --color-texto: <?php echo htmlspecialchars($apariencia['color_texto'] ?? '#333333'); ?>; --color-borde: #dee2e6; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: var(--color-fondo); color: var(--color-texto); display: flex; height: 100vh; overflow: hidden; }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        
        .container { max-width: 1100px; margin: 0 auto; }
        .tarjeta { background: #ffffff; border-radius: 8px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); border: 1px solid #eef0f2; }
        .tarjeta-titulo { color: var(--color-primario); margin-top: 0; font-size: 24px; border-bottom: 2px solid var(--color-fondo); padding-bottom: 15px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;}
        .alerta { padding: 15px; border-radius: 4px; margin-bottom: 25px; font-size: 15px; }
        .alerta-exito { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alerta-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-grupo { margin-bottom: 18px; }
        .form-grupo label { display: block; margin-bottom: 6px; font-weight: 500; color: #444; font-size: 14px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid var(--color-borde); border-radius: 4px; font-size: 14px; box-sizing: border-box; transition: border-color 0.2s; }
        .form-control:focus { border-color: var(--color-primario); outline: none; }
        .btn-global { display: inline-block; padding: 10px 20px; font-size: 15px; font-weight: bold; text-align: center; text-decoration: none; border: none; border-radius: 4px; cursor: pointer; transition: opacity 0.2s; }
        .btn-global:hover { opacity: 0.85; }
        .btn-primario { background-color: var(--color-primario); color: #fff; }
        .btn-secundario { background-color: #7f8c8d; color: #fff; }
        .flex-container { display: flex; flex-wrap: wrap; gap: 30px; }
        .panel { flex: 1; min-width: 320px; border: 1px solid var(--color-borde); border-radius: 6px; padding: 25px; background-color: #fdfdfd; box-sizing: border-box; }
        .panel h3 { margin-top: 0; color: var(--color-primario); border-bottom: 2px solid var(--color-fondo); padding-bottom: 10px; font-size: 18px; }
        .info-cambio { font-size: 12px; color: #666; margin-top: 4px; display: block; }

        /* Estilos Permisos Jerárquicos */
        .panel-permisos { display: none; margin-top: 20px; padding-top: 15px; border-top: 1px dashed var(--color-borde); }
        .permiso-grupo { background: #fff; border: 1px solid var(--color-borde); border-radius: 4px; margin-bottom: 12px; padding: 15px; }
        .grupo-titulo { font-weight: bold; color: var(--color-primario); margin-bottom: 12px; font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .sub-grupo { margin-left: 10px; margin-bottom: 15px; background: #fafafa; border: 1px solid #eee; border-radius: 4px; padding: 10px; }
        .sub-titulo { font-size: 13px; font-weight: 600; color: #444; margin-bottom: 10px; border-bottom: 1px dashed #ddd; padding-bottom: 5px; }
        .funciones-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; }
        .funcion-item { font-size: 12px; color: #555; display: flex; align-items: center; gap: 6px; cursor: pointer; }
        .funcion-item input { margin: 0; transform: scale(1.1); }
        .chk-grupo { transform: scale(1.1); cursor: pointer; }

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
            <div class="container">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <h2 class="tarjeta-titulo" style="border:none; margin:0; padding:0;">Editar Personal</h2>
                    <a href="listar_personal.php" style="color:var(--color-primario); text-decoration:none; font-weight:bold; font-size:14px; border:1px solid var(--color-primario); padding:6px 12px; border-radius:4px;">← Volver al Listado</a>
                </div>

                <?php if($mensaje): ?>
                    <div class="alerta alerta-<?php echo $tipoMensaje === 'exito' ? 'exito' : 'error'; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
                <?php endif; ?>

            <form method="POST" action="">
                <div class="flex-container">
                    <div class="panel">
                        <h3>1. Datos del Personal</h3>
                            <label>Tipo de Documento</label>
                            <select name="id_tipo_documento" class="form-control">
                                <?php foreach(($tiposDoc ?? []) as $td): ?>
                                    <option value="<?php echo $td['IDTipoDocumento']; ?>" <?php echo ($empleado['IDTipoDocumento'] == $td['IDTipoDocumento']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($td['tipoDocumento']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-grupo">
                            <label>Número de Documento *</label>
                            <input type="number" name="dni" class="form-control" required placeholder="..." value="<?php echo htmlspecialchars($empleado['numeroDocumentoPersonal'] ?? ''); ?>">
                        </div>

                        <div class="form-grupo">
                            <label>Nombre *</label>
                            <input type="text" name="nombre" class="form-control" required placeholder="..." value="<?php echo htmlspecialchars($empleado['nombre'] ?? ''); ?>">
                        </div>

                        <div class="form-grupo">
                            <label>Apellido *</label>
                            <input type="text" name="apellido" class="form-control" required placeholder="..." value="<?php echo htmlspecialchars($empleado['apellido'] ?? ''); ?>">
                        </div>

                        <div style="display: flex; gap: 15px;">
                            <div class="form-grupo" style="flex: 1;">
                                <label>Tipo Teléfono</label>
                                <select name="id_tipo_telefono" class="form-control">
                                    <?php foreach(($tiposTel ?? []) as $tt): ?>
                                        <option value="<?php echo $tt['IDTipoNumeroTelefono']; ?>" <?php echo ($empleado['IDTipoNumeroTelefono'] == $tt['IDTipoNumeroTelefono']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($tt['tipoNumeroTelefono']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-grupo" style="flex: 2;">
                                <label>Teléfono</label>
                                <input type="tel" name="telefono" pattern="[0-9+\-\s]+" title="Solo se permiten números, espacios, guiones y el símbolo +" class="form-control" placeholder="..." value="<?php echo htmlspecialchars($empleado['telefono'] > 0 ? $empleado['telefono'] : ''); ?>">
                            </div>
                        </div>

                        <div class="form-grupo">
                            <label>Estado del Colaborador *</label>
                            <select name="estado_personal" class="form-control">
                                <option value="Activo" <?php echo ($empleado['estado'] === 'Activo') ? 'selected' : ''; ?>>Activo</option>
                                <option value="Inactivo" <?php echo ($empleado['estado'] === 'Inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <div class="panel" style="background-color: #f8f9fa;">
                        <h3>2. Datos de Usuario</h3>
                        
                        <?php if (empty($empleado['IDUsuario'])): ?>
                            <div style="background-color: #fff; padding: 20px; border: 1px solid var(--color-borde); border-radius: 4px;">
                                <label style="display: flex; align-items: center; gap: 10px; font-weight: bold; margin-bottom: 20px; cursor: pointer; color: var(--color-secundario);">
                                    <input type="checkbox" name="crear_usuario" value="1" id="chkUsuarioNuevo" style="width: 18px; height: 18px;">
                                    Crear y vincular nueva cuenta de usuario
                                </label>
                                
                                <div id="camposUsuarioNuevo" style="display: none;">
                                    <div class="form-grupo">
                                        <label>Correo Electrónico *</label>
                                        <input type="email" name="email" class="form-control input-nuevo-usr" placeholder="...">
                                    </div>
                                    <div class="form-grupo">
                                        <label>Asignar Rol Base *</label>
                                        <select name="id_rol" id="selectRol" class="form-control input-nuevo-usr">
                                            <option value="">-- Seleccione un Rol --</option>
                                            <?php foreach(($roles ?? []) as $r): ?>
                                                <option value="<?php echo $r['IDRol']; ?>"><?php echo htmlspecialchars($r['nombreRol']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
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
                                        <input type="text" name="usuario" class="form-control input-nuevo-usr" placeholder="...">
                                    </div>
                                    <div style="display: flex; gap: 15px;">
                                        <div class="form-grupo" style="flex: 1;">
                                            <label>Contraseña de Acceso *</label>
                                            <input type="password" name="clave_nueva" class="form-control input-nuevo-usr" placeholder="...">
                                        </div>
                                        <div class="form-grupo" style="flex: 1;">
                                            <label>Confirmar Contraseña *</label>
                                            <input type="password" name="confirmar_clave_nueva" class="form-control input-nuevo-usr" placeholder="...">
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php else: ?>
                            <div style="background-color: #fff; padding: 20px; border: 1px solid var(--color-borde); border-radius: 4px;">
                                <div class="form-grupo">
                                    <label>Correo Electrónico *</label>
                                    <input type="email" name="email" class="form-control" required placeholder="..." value="<?php echo htmlspecialchars($empleado['email'] ?? ''); ?>">
                                </div>

                                <div class="form-grupo">
                                    <label>Rol Asignado *</label>
                                    <select name="id_rol" id="selectRol" class="form-control">
                                        <?php foreach(($roles ?? []) as $r): ?>
                                            <option value="<?php echo $r['IDRol']; ?>" <?php echo ($empleado['IDRol'] == $r['IDRol']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['nombreRol']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
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
                                                            <?php $checkStr = tienePermiso($seccion['area_id'], $fId, $permisosUsuario) ? 'checked' : ''; ?>
                                                            <label class="funcion-item"><input type="checkbox" name="permisos[<?php echo $seccion['area_id']; ?>][]" value="<?php echo $fId; ?>" <?php echo $checkStr; ?>> <?php echo htmlspecialchars($fNombre); ?></label>
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
                                                                    <?php $checkStr = tienePermiso($sub_area['area_id'], $fId, $permisosUsuario) ? 'checked' : ''; ?>
                                                                    <label class="funcion-item"><input type="checkbox" name="permisos[<?php echo $sub_area['area_id']; ?>][]" value="<?php echo $fId; ?>" <?php echo $checkStr; ?>> <?php echo htmlspecialchars($fNombre); ?></label>
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
                                    <input type="text" name="usuario" class="form-control" required placeholder="..." value="<?php echo htmlspecialchars($empleado['nombreUsuario'] ?? ''); ?>">
                                </div>
                                
                                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px dashed var(--color-borde);">
                                    <h4 style="margin: 0 0 15px 0; font-size: 14px; color: var(--color-primario);">Modificar Contraseña</h4>
                                    <div class="form-grupo">
                                        <label>Contraseña Actual</label>
                                        <input type="password" name="clave_actual" class="form-control" placeholder="...">
                                        <span class="info-change">Requerida únicamente si va a ingresar una nueva contraseña.</span>
                                    </div>
                                    <div style="display: flex; gap: 15px;">
                                        <div class="form-grupo" style="flex: 1;">
                                            <label>Nueva Contraseña</label>
                                            <input type="password" name="clave_nueva" class="form-control" placeholder="...">
                                            <span class="info-change">Deje vacío si desea mantener la contraseña actual.</span>
                                        </div>
                                        <div class="form-grupo" style="flex: 1;">
                                            <label>Confirmar Nueva Contraseña</label>
                                            <input type="password" name="confirmar_clave_nueva" class="form-control" placeholder="...">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid var(--color-fondo); text-align: right;">
                    <button type="submit" class="btn-global btn-primario" style="padding: 12px 35px; font-size: 16px; background-color: var(--color-primario); color: white; border: none; border-radius: 4px; cursor: pointer;">Guardar Cambios</button>
                </div>
            </form>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectRol = document.getElementById('selectRol');
            const panelPermisos = document.getElementById('panelPermisos');
            const chkUsuarioNuevo = document.getElementById('chkUsuarioNuevo');
            const camposUsuarioNuevo = document.getElementById('camposUsuarioNuevo');
            const inputsNuevoUsr = document.querySelectorAll('.input-nuevo-usr');

            // Toggle para cuando el empleado NO tenía usuario
            if (chkUsuarioNuevo) {
                chkUsuarioNuevo.addEventListener('change', function() {
                    if (this.checked) {
                        camposUsuarioNuevo.style.display = 'block';
                        inputsNuevoUsr.forEach(i => i.setAttribute('required', 'required'));
                    } else {
                        camposUsuarioNuevo.style.display = 'none';
                        inputsNuevoUsr.forEach(i => i.removeAttribute('required'));
                    }
                });
            }

            if (selectRol && panelPermisos) {
                function conmutarRolEspecial() {
                    if (selectRol.value === "4") panelPermisos.style.display = 'block';
                    else panelPermisos.style.display = 'none';
                }
                selectRol.addEventListener('change', conmutarRolEspecial);
                conmutarRolEspecial();
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
        });
    </script>
</body>
</html>