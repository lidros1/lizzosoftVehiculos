<?php
/**
 * Lizzosoft Vehículos - Alta de Clientes
 * Ubicación: lizzosoft_vehiculos/CRUD_Clientes/crear_cliente.php
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$config           = $_SESSION['cliente_config'];
$apariencia       = $config['apariencia'];
$empresa_id       = (int)$_SESSION['empresa_id_usuario'];
$sucursal_id      = (int)$_SESSION['sucursal_id'];

// Validación de permisos
$areas_permitidas = $_SESSION['areas_permitidas'] ?? [];
$es_admin         = (isset($_SESSION['IDRol']) && $_SESSION['IDRol'] == 1);

$funciones_permitidas = $_SESSION['funciones_permitidas'][5] ?? [];
if ((!in_array(5, $areas_permitidas) || !in_array(1, $funciones_permitidas)) && !$es_admin) {
    die("<div style='padding:20px; font-family:Arial; color:#721c24; background:#f8d7da;'>Error: No tienes permisos para registrar Clientes.</div>");
}

$conexion = obtenerConexion();
$mensaje = '';
$tipoMensaje = '';

// Carga de catálogos
try {
    $stmtDoc = $conexion->query("SELECT IDTipoDocumento, tipoDocumento FROM tiposdocumentos WHERE estado = 'Activo'");
    $tiposDoc = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);

    $stmtTel = $conexion->query("SELECT IDTipoNumeroTelefono, tipoNumeroTelefono FROM tiposnumerotelefono WHERE estado = 'Activo'");
    $tiposTel = $stmtTel->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "Error al inicializar los catálogos. Contacte a soporte.";
    $tipoMensaje = "error";
}

// Procesamiento del Formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $idTipoDoc = (int)($_POST['id_tipo_documento'] ?? 1);
    $documento = trim($_POST['documento'] ?? '');
    // strip_tags elimina cualquier intento de inyección HTML/JS en campos de texto
    $nombre    = strip_tags(trim($_POST['nombre'] ?? ''));
    $apellido  = strip_tags(trim($_POST['apellido'] ?? ''));
    $idTipoTel = (int)($_POST['id_tipo_telefono'] ?? 1);
    $telefono  = trim($_POST['telefono'] ?? '');
    $email     = trim($_POST['email'] ?? '');

    if (empty($nombre) || empty($apellido)) {
        $mensaje = "El Nombre y el Apellido son campos obligatorios.";
        $tipoMensaje = "error";
    } elseif (!preg_match("/^[\p{L}\s'\-\.]+$/u", $nombre) || !preg_match("/^[\p{L}\s'\-\.]+$/u", $apellido)) {
        $mensaje = "El Nombre y Apellido solo pueden contener letras, espacios y guiones.";
        $tipoMensaje = "error";
    } elseif (!empty($documento) && $idTipoDoc === 1 && !preg_match('/^[0-9]{8}$/', $documento)) {
        $mensaje = "El DNI debe contener exactamente 8 dígitos numéricos.";
        $tipoMensaje = "error";
    } elseif (!empty($documento) && $idTipoDoc === 2 && !preg_match('/^[A-Za-z0-9]{5,9}$/', $documento)) {
        $mensaje = "El Pasaporte debe contener entre 5 y 9 caracteres alfanuméricos.";
        $tipoMensaje = "error";
    } elseif (!empty($telefono) && !preg_match('/^\+?[0-9]{10,15}$/', $telefono)) {
        $mensaje = "El teléfono debe tener entre 10 y 15 dígitos numéricos, opcionalmente con prefijo + internacional (ej: 02616963625 o +54902616963625).";
        $tipoMensaje = "error";
    } elseif (empty($email)) {
        $mensaje = "El correo electrónico es obligatorio.";
        $tipoMensaje = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
        $mensaje = "El formato del correo electrónico ingresado es inválido (ejemplo: usuario@dominio.com).";
        $tipoMensaje = "error";
    } else {
        try {
            // Verificar duplicidad de documento en la misma sucursal solo si se ingresó uno
            if (!empty($documento)) {
                $stmtCheckDni = $conexion->prepare("SELECT IDCliente FROM clientes WHERE numeroDocumentoCliente = ? AND empresa_id = ? AND sucursal_id = ?");
                $stmtCheckDni->execute([$documento, $empresa_id, $sucursal_id]);
                if ($stmtCheckDni->fetch()) {
                    throw new Exception("El número de documento ingresado ya pertenece a un cliente registrado en el sistema para esta sucursal.");
                }
            }

            $telefonoFinal = empty($telefono) ? 0 : (int)$telefono;
            $documentoFinal = empty($documento) ? null : $documento;

            $sqlCliente = "INSERT INTO clientes (nombre, apellido, IDTipoDocumento, numeroDocumentoCliente, IDTipoNumeroTelefono, telefono, email, estado, empresa_id, sucursal_id) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'Activo', ?, ?)";
            $stmtIns = $conexion->prepare($sqlCliente);
            $stmtIns->execute([
                $nombre, $apellido, $idTipoDoc, $documentoFinal, $idTipoTel, $telefonoFinal, $email, $empresa_id, $sucursal_id
            ]);

            // Registro en Logs
            $stmtLog = $conexion->prepare("INSERT INTO logs_accesos (IDUsuario, nombreUsuario, accion, fecha_hora, empresa_id, sucursal_id) VALUES (?, ?, ?, NOW(), ?, ?)");
            $logAccion = "Alta de nuevo cliente: $apellido, $nombre";
            $stmtLog->execute([$_SESSION['IDUsuario'], $_SESSION['nombreUsuario'], $logAccion, $empresa_id, $sucursal_id]);

            $mensaje = "El cliente ha sido registrado exitosamente.";
            $tipoMensaje = "exito";

            // Limpiar formulario tras éxito
            $_POST = [];

        } catch (Exception $e) {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Cliente - <?php echo htmlspecialchars($config['nombre_empresa']); ?></title>
    <style>
        :root {
            --color-primario: <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>;
            --color-secundario: <?php echo htmlspecialchars($apariencia['color_secundario'] ?? '#e74c3c'); ?>;
            --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>;
            --color-texto: <?php echo htmlspecialchars($apariencia['color_texto'] ?? '#333333'); ?>;
            --color-borde: #dee2e6;
        }

        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: var(--color-fondo); color: var(--color-texto); display: flex; height: 100vh; overflow: hidden; }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        
        .container { max-width: 900px; margin: 0 auto; }
        
        .tarjeta { background: #ffffff; border-radius: 8px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); border: 1px solid #eef0f2; }
        .tarjeta-titulo { color: var(--color-primario); margin-top: 0; font-size: 22px; border-bottom: 2px solid var(--color-fondo); padding-bottom: 15px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;}
        
        .alerta { padding: 15px; border-radius: 4px; margin-bottom: 25px; font-size: 14px; font-weight: 500; }
        .alerta-exito { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alerta-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }

        .form-grupo { margin-bottom: 20px; }
        .form-grupo.full-width { grid-column: 1 / -1; }
        .form-grupo label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid var(--color-borde); border-radius: 4px; font-size: 14px; box-sizing: border-box; transition: border-color 0.2s; background-color: #fafbfc; }
        .form-control:focus { border-color: var(--color-primario); outline: none; background-color: #fff; box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.1); }

        .btn-global { display: inline-block; padding: 12px 25px; font-size: 14px; font-weight: bold; text-align: center; text-decoration: none; border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; }
        .btn-primario { background-color: var(--color-primario); color: #fff; }
        .btn-primario:hover { background-color: #1a252f; }
        .btn-secundario { background-color: #e2e8f0; color: #4a5568; padding: 8px 15px; font-size: 13px; }
        .btn-secundario:hover { background-color: #cbd5e0; }

        .form-actions { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 15px; align-items: center; }
        .text-requerido { font-size: 12px; color: #888; }

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
                    <h2 class="tarjeta-titulo" style="border:none; margin:0; padding:0;">Registrar Cliente</h2>
                    <a href="listar_clientes.php" style="color:var(--color-primario); text-decoration:none; font-weight:bold; font-size:14px; border:1px solid var(--color-primario); padding:6px 12px; border-radius:4px;">← Volver al Listado</a>
                </div>

            <?php if($mensaje): ?>
                <div class="alerta alerta-<?php echo $tipoMensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="tarjeta">
                    <div class="form-grid">
                        
                        <div class="form-grupo">
                            <label>Tipo de Documento</label>
                            <select name="id_tipo_documento" class="form-control">
                                <?php foreach(($tiposDoc ?? []) as $td): ?>
                                    <option value="<?php echo $td['IDTipoDocumento']; ?>" <?php echo (isset($_POST['id_tipo_documento']) && $_POST['id_tipo_documento'] == $td['IDTipoDocumento']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($td['tipoDocumento']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-grupo">
                            <label>Número de Documento</label>
                            <input type="text" name="documento" pattern="[0-9]+" title="Solo números" class="form-control" placeholder="Opcional" value="<?php echo htmlspecialchars($_POST['documento'] ?? ''); ?>">
                        </div>

                        <div class="form-grupo">
                            <label>Nombre *</label>
                            <input type="text" name="nombre" class="form-control" required placeholder="..." value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
                        </div>

                        <div class="form-grupo">
                            <label>Apellido *</label>
                            <input type="text" name="apellido" class="form-control" required placeholder="..." value="<?php echo htmlspecialchars($_POST['apellido'] ?? ''); ?>">
                        </div>

                        <div class="form-grupo">
                            <label>Tipo de Teléfono</label>
                            <select name="id_tipo_telefono" class="form-control">
                                <?php foreach(($tiposTel ?? []) as $tt): ?>
                                    <option value="<?php echo $tt['IDTipoNumeroTelefono']; ?>" <?php echo (isset($_POST['id_tipo_telefono']) && $_POST['id_tipo_telefono'] == $tt['IDTipoNumeroTelefono']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tt['tipoNumeroTelefono']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-grupo">
                            <label>Número de Teléfono</label>
                            <input type="tel" name="telefono" pattern="[0-9+\-\s]+" title="Solo números, guiones y espacios" class="form-control" placeholder="..." value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>">
                        </div>

                        <div class="form-grupo full-width">
                            <label>Correo Electrónico</label>
                            <input type="email" name="email" class="form-control" placeholder="..." value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>

                    </div>

                    <div class="form-actions">
                        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid var(--color-fondo); text-align: right; width: 100%;">
                            <button type="submit" class="btn-global btn-primario" style="padding: 12px 35px; font-size: 16px; background-color: var(--color-primario); color: white; border: none; border-radius: 4px; cursor: pointer;">Guardar y Registrar Cliente</button>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>
</body>
</html>