<?php
/**
 * Lizzosoft Vehículos - Alta de Servicios (Estructura Simplificada)
 * Ubicación: lizzosoft_vehiculos/CRUD_Servicios/crear_servicio.php
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

// Validación de permisos (Área 6 = Servicios o Rol 1 = Admin)
$areas_permitidas = $_SESSION['areas_permitidas'] ?? [];
$es_admin         = (isset($_SESSION['IDRol']) && $_SESSION['IDRol'] == 1);

$funciones_permitidas = $_SESSION['funciones_permitidas'][6] ?? [];
if ((!in_array(6, $areas_permitidas) || !in_array(1, $funciones_permitidas)) && !$es_admin) {
    die("<div style='padding:20px; font-family:Arial; color:#721c24; background:#f8d7da;'>Error: No tienes permisos para crear Servicios.</div>");
}

$conexion = obtenerConexion();
$mensaje = '';
$tipoMensaje = '';

// Procesamiento del Formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nombre      = strip_tags(trim($_POST['nombre'] ?? ''));
    $descripcion = strip_tags(trim($_POST['descripcion'] ?? ''));
    $costo       = (float)($_POST['costo'] ?? 0);

    if (empty($nombre)) {
        $mensaje = "El nombre del servicio es un campo obligatorio.";
        $tipoMensaje = "error";
    } elseif ($costo < 0) {
        $mensaje = "El costo del servicio no puede ser negativo.";
        $tipoMensaje = "error";
    } elseif ($costo > 99999999.99) {
        $mensaje = "El costo ingresado excede el máximo permitido.";
        $tipoMensaje = "error";
    } else {
        try {
            // Verificar si el servicio ya existe en esta empresa
            $stmtCheck = $conexion->prepare("SELECT IDServicio FROM servicios WHERE nombreServicio = ? AND empresa_id = ?");
            $stmtCheck->execute([$nombre, $empresa_id]);
            
            if ($stmtCheck->fetch()) {
                throw new Exception("Ya existe un servicio registrado con este nombre en la base de datos.");
            }

            // Insertar el nuevo servicio con su costo
            $sqlInsert = "INSERT INTO servicios (nombreServicio, descripcionServicio, costoServicio, estado, empresa_id) 
                          VALUES (?, ?, ?, 'Activo', ?)";
            $stmtInsert = $conexion->prepare($sqlInsert);
            $stmtInsert->execute([$nombre, $descripcion, $costo, $empresa_id]);

            $idNuevoServicio = $conexion->lastInsertId();

            // Auditoría (Log)
            $stmtLog = $conexion->prepare("INSERT INTO logs_accesos (IDUsuario, nombreUsuario, accion, fecha_hora, empresa_id, sucursal_id) VALUES (?, ?, ?, NOW(), ?, ?)");
            $logAccion = "Alta de nuevo servicio: $nombre (ID:$idNuevoServicio)";
            $stmtLog->execute([$_SESSION['IDUsuario'], $_SESSION['nombreUsuario'], $logAccion, $empresa_id, $sucursal_id]);

            $mensaje = "El servicio ha sido registrado exitosamente.";
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
    <title>Registrar Servicio - <?php echo htmlspecialchars($config['nombre_empresa']); ?></title>
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
        
        .container { max-width: 800px; margin: 0 auto; }
        
        .tarjeta { background: #ffffff; border-radius: 8px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); border: 1px solid #eef0f2; }
        .tarjeta-titulo { color: var(--color-primario); margin-top: 0; font-size: 22px; border-bottom: 2px solid var(--color-fondo); padding-bottom: 15px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;}
        
        .alerta { padding: 15px; border-radius: 4px; margin-bottom: 25px; font-size: 14px; font-weight: 500; }
        .alerta-exito { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alerta-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .form-grid { display: grid; grid-template-columns: 1fr; gap: 20px; }

        .form-grupo { margin-bottom: 5px; }
        .form-grupo label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid var(--color-borde); border-radius: 4px; font-size: 14px; box-sizing: border-box; transition: border-color 0.2s; background-color: #fafbfc; font-family: inherit; }
        .form-control:focus { border-color: var(--color-primario); outline: none; background-color: #fff; box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.1); }
        textarea.form-control { resize: none; min-height: 100px; }

        .btn-global { display: inline-block; padding: 12px 25px; font-size: 14px; font-weight: bold; text-align: center; text-decoration: none; border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; }
        .btn-primario { background-color: var(--color-primario); color: #fff; }
        .btn-primario:hover { background-color: #1a252f; }
        .btn-secundario { background-color: #e2e8f0; color: #4a5568; padding: 8px 15px; font-size: 13px; }
        .btn-secundario:hover { background-color: #cbd5e0; }

        .form-actions { margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
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
                    <h2 class="tarjeta-titulo" style="border:none; margin:0; padding:0;">Crear Servicio</h2>
                    <a href="listar_servicios.php" style="color:var(--color-primario); text-decoration:none; font-weight:bold; font-size:14px; border:1px solid var(--color-primario); padding:6px 12px; border-radius:4px;">← Volver al Listado</a>
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
                            <label>Nombre del Servicio *</label>
                            <input type="text" name="nombre" class="form-control" required placeholder="Ej: Cambio de Aceite Sintético" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
                        </div>

                        <div class="form-grupo">
                            <label>Costo del Servicio (Moneda Local) *</label>
                            <input type="number" step="0.01" min="0" name="costo" class="form-control" required placeholder="0.00" value="<?php echo htmlspecialchars($_POST['costo'] ?? ''); ?>">
                        </div>

                        <div class="form-grupo">
                            <label>Descripción y Detalles</label>
                            <textarea name="descripcion" class="form-control" placeholder="Detalles de la mano de obra, insumos involucrados u observaciones para el cliente..."><?php echo htmlspecialchars($_POST['descripcion'] ?? ''); ?></textarea>
                        </div>

                    </div>

                    <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid var(--color-fondo); text-align: right; width: 100%;">
                        <button type="submit" class="btn-global btn-primario" style="padding: 12px 35px; font-size: 16px; background-color: var(--color-primario); color: white; border: none; border-radius: 4px; cursor: pointer;">Guardar Servicio</button>
                    </div>
                </div>
            </form>
        </main>
    </div>
</body>
</html>