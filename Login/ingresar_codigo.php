<?php
session_start();

// REGLA DE ARQUITECTURA: Zona horaria obligatoria
date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once '../Conexion/Conexion.php';

$empresa_id = $_SESSION['cliente_config']['empresa_id'] ?? null;
$color_primario = $_SESSION['cliente_config']['color_primario'] ?? '#0056b3';
$nombre_empresa = $_SESSION['cliente_config']['nombre_empresa'] ?? 'Lizzosoft';

// Capturamos ambos parámetros de la URL para inyectarlos en el form
$email_solicitud = trim($_GET['email'] ?? '');
$usuario_solicitud = trim($_GET['usuario'] ?? '');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $usuario_input = trim($_POST['usuario'] ?? ''); // CORRECCIÓN: Validamos cuenta exacta
    $codigo = trim($_POST['codigo'] ?? '');
    $nueva_password = $_POST['nueva_password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';

    if (empty($email) || empty($usuario_input) || empty($codigo) || empty($nueva_password) || empty($confirmar_password)) {
        $error = "Por favor, complete todos los campos del formulario.";
    } elseif ($nueva_password !== $confirmar_password) {
        $error = "Las nuevas contraseñas ingresadas no coinciden.";
    } elseif (strlen($nueva_password) < 6) {
        $error = "La contraseña de seguridad debe contener al menos 6 caracteres.";
    } elseif (!$empresa_id) {
        $error = "Error de entorno: Identificador de empresa no válido.";
    } else {
        try {
            $conexion = function_exists('obtenerConexion') ? obtenerConexion() : Conectar();
            
            // CORRECCIÓN CLAVE: El filtro ahora exige nombreUsuario para evitar que correos duplicados fallen
            $stmt = $conexion->prepare("SELECT IDUsuario, nombreUsuario, codigo_recuperacion, expiracion_codigo FROM usuarios WHERE nombreUsuario = ? AND email = ? AND empresa_id = ? AND estado = 'Activo'");
            $stmt->execute([$usuario_input, $email, $empresa_id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                $fecha_actual = date('Y-m-d H:i:s');
                
                if ($usuario['codigo_recuperacion'] === null || $usuario['codigo_recuperacion'] !== $codigo) {
                    $error = "El código de verificación ingresado es incorrecto.";
                } elseif ($usuario['expiracion_codigo'] < $fecha_actual) {
                    $error = "El código ha expirado debido al límite de tiempo. Solicite uno nuevo.";
                } else {
                    $password_hasheada = md5($nueva_password);
                    
                    $updateStmt = $conexion->prepare("UPDATE usuarios SET contraseñaUsuario = ?, codigo_recuperacion = NULL, expiracion_codigo = NULL WHERE IDUsuario = ? AND empresa_id = ?");
                    $updateStmt->execute([$password_hasheada, $usuario['IDUsuario'], $empresa_id]);

                    $stmtLog = $conexion->prepare("INSERT INTO logs_accesos (IDUsuario, nombreUsuario, accion, empresa_id) VALUES (?, ?, 'RESTABLECER_PASSWORD', ?)");
                    $stmtLog->execute([$usuario['IDUsuario'], $usuario['nombreUsuario'], $empresa_id]);

                    $success = "Tu contraseña ha sido actualizada con éxito. Ya puedes ingresar al sistema.";
                }
            } else {
                $error = "No se pudo verificar la cuenta solicitada. Los datos han expirado o son inválidos.";
            }
        } catch (Exception $e) {
            error_log("Error crítico en ingresar_codigo.php: " . $e->getMessage());
            $error = "Ocurrió un error inesperado en el servidor.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - <?php echo htmlspecialchars($nombre_empresa); ?></title>
    <style>
        :root {
            --color-primario: <?php echo htmlspecialchars($color_primario); ?>;
        }
        body { 
            font-family: Arial, sans-serif; 
            background-color: #f4f6f9; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
        }
        .contenedor-codigo { 
            background: #fff; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
            width: 100%; 
            max-width: 400px; 
        }
        .contenedor-codigo h2 { 
            color: var(--color-primario); 
            text-align: center; 
            margin-bottom: 20px; 
        }
        .form-group { 
            margin-bottom: 15px; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 5px; 
            color: #333; 
        }
        .form-group input { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            box-sizing: border-box; 
        }
        .botones {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }
        .btn { 
            width: 100%; 
            padding: 10px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 16px; 
            text-align: center;
            text-decoration: none;
            box-sizing: border-box;
        }
        .btn-primary { 
            background-color: var(--color-primario); 
            color: white; 
        }
        .btn-primary:hover { 
            opacity: 0.9; 
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="contenedor-codigo">
        <h2>Nueva Contraseña</h2>
        
        <form action="ingresar_codigo.php?email=<?php echo urlencode($email_solicitud); ?>&usuario=<?php echo urlencode($usuario_solicitud); ?>" method="POST">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email_solicitud ? $email_solicitud : ($_POST['email'] ?? '')); ?>">
            <input type="hidden" name="usuario" value="<?php echo htmlspecialchars($usuario_solicitud ? $usuario_solicitud : ($_POST['usuario'] ?? '')); ?>">
            
            <div class="form-group">
                <label for="codigo">Código de Verificación (6 dígitos)</label>
                <input type="text" id="codigo" name="codigo" maxlength="6" pattern="\d{6}" placeholder="Ej. 123456" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="nueva_password">Nueva Contraseña</label>
                <input type="password" id="nueva_password" name="nueva_password" required>
            </div>
            
            <div class="form-group">
                <label for="confirmar_password">Confirmar Nueva Contraseña</label>
                <input type="password" id="confirmar_password" name="confirmar_password" required>
            </div>
            
            <div class="botones">
                <button type="submit" class="btn btn-primary">Actualizar Contraseña</button>
                <a href="login.php" class="btn btn-secondary">Volver</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            <?php if (!empty($error)): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Validación Incorrecta',
                    text: '<?php echo addslashes($error); ?>',
                    confirmButtonColor: 'var(--color-primario)',
                    heightAuto: false,
                    scrollbarPadding: false
                });
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                Swal.fire({
                    icon: 'success',
                    title: '¡Proceso Completado!',
                    text: '<?php echo addslashes($success); ?>',
                    confirmButtonColor: 'var(--color-primario)',
                    heightAuto: false,
                    scrollbarPadding: false
                }).then(() => {
                    window.location.href = 'login.php';
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>