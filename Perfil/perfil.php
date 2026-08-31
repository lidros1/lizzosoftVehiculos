<?php
/**
 * Lizzosoft Vehículos - Módulo "Mi Perfil" (Rediseñado y Optimizado)
 * Ubicación: lizzosoft_vehiculos/Perfil/perfil.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$config     = $_SESSION['cliente_config'];
$apariencia = $config['apariencia'];
$empresa_id = (int)$_SESSION['empresa_id'];
$idUsuario  = (int)($_SESSION['IDUsuario'] ?? 0);
$idRol      = (int)($_SESSION['IDRol'] ?? 0); // Requerido para validar si es Administrador
$temaActual = $_SESSION['tema_preferido'] ?? 'claro';

$conexion = obtenerConexion();
$mensaje = '';
$tipoMensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // Solo un Administrador (Rol 1) debería poder cambiar el nombre de usuario
    if ($accion === 'cambiar_usuario' && $idRol === 1) {
        $nuevo_usuario = trim($_POST['nuevo_usuario'] ?? '');
        if (!empty($nuevo_usuario)) {
            $stmtVal = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE nombreUsuario = ? AND empresa_id = ? AND IDUsuario != ?");
            $stmtVal->execute([$nuevo_usuario, $empresa_id, $idUsuario]);
            if ($stmtVal->fetchColumn() > 0) {
                $mensaje = "El nombre de usuario ya está en uso en esta empresa.";
                $tipoMensaje = "error";
            } else {
                $conexion->prepare("UPDATE usuarios SET nombreUsuario = ? WHERE IDUsuario = ?")->execute([$nuevo_usuario, $idUsuario]);
                $_SESSION['nombreUsuario'] = $nuevo_usuario;
                $mensaje = "Nombre de usuario actualizado con éxito.";
                $tipoMensaje = "success";
            }
        }
    } elseif ($accion === 'cambiar_password') {
        $pass_actual = $_POST['pass_actual'] ?? '';
        $pass_nueva = $_POST['pass_nueva'] ?? '';
        $pass_confirmar = $_POST['pass_confirmar'] ?? '';

        $stmtPass = $conexion->prepare("SELECT contraseñaUsuario FROM usuarios WHERE IDUsuario = ?");
        $stmtPass->execute([$idUsuario]);
        $hashActual = $stmtPass->fetchColumn();

        if (md5($pass_actual) !== $hashActual) {
            $mensaje = "La contraseña actual es incorrecta.";
            $tipoMensaje = "error";
        } elseif ($pass_nueva !== $pass_confirmar) {
            $mensaje = "Las nuevas contraseñas no coinciden.";
            $tipoMensaje = "error";
        } elseif (strlen($pass_nueva) < 6) {
            $mensaje = "La nueva contraseña debe tener al menos 6 caracteres.";
            $tipoMensaje = "error";
        } else {
            $conexion->prepare("UPDATE usuarios SET contraseñaUsuario = ? WHERE IDUsuario = ?")->execute([md5($pass_nueva), $idUsuario]);
            $mensaje = "Contraseña actualizada por seguridad.";
            $tipoMensaje = "success";
        }
    } elseif ($accion === 'cambiar_tema') {
        $nuevoTema = ($_POST['tema_preferido'] === 'oscuro') ? 'oscuro' : 'claro';
        $conexion->prepare("UPDATE usuarios SET tema_preferido = ? WHERE IDUsuario = ?")->execute([$nuevoTema, $idUsuario]);
        $_SESSION['tema_preferido'] = $nuevoTema;
        header("Location: perfil.php?tema_actualizado=1");
        exit;
    } elseif ($accion === 'actualizar_personal') {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $documento = trim($_POST['numeroDocumentoPersonal'] ?? '');

        if (!empty($nombre) && !empty($apellido)) {
            $stmtUpd = $conexion->prepare("UPDATE personal SET nombre = ?, apellido = ?, telefono = ?, numeroDocumentoPersonal = ? WHERE IDUsuario = ?");
            $stmtUpd->execute([$nombre, $apellido, $telefono, $documento, $idUsuario]);
            $mensaje = "Información personal actualizada exitosamente.";
            $tipoMensaje = "success";
        } else {
            $mensaje = "El nombre y el apellido son obligatorios.";
            $tipoMensaje = "error";
        }
    }
}

// Obtener datos del personal actual
$datosPersonal = null;
if ($idUsuario > 0) {
    $stmtPers = $conexion->prepare("SELECT * FROM personal WHERE IDUsuario = ? LIMIT 1");
    $stmtPers->execute([$idUsuario]);
    $datosPersonal = $stmtPers->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - <?php echo htmlspecialchars($config['nombre_empresa']); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { 
            --color-primario: <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>; 
            --color-secundario: <?php echo htmlspecialchars($apariencia['color_secundario'] ?? '#e74c3c'); ?>; 
            --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>; 
            --sidebar-width: 270px;
        }
        
        body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; background-color: var(--color-fondo); color: #333; display: flex; height: 100vh; overflow: hidden; }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; width: 100%; }
        
        .topbar { background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); flex-shrink: 0; z-index: 10; }
        .topbar-left { display: flex; align-items: center; gap: 20px; }
        
        /* BOTÓN VOLVER EXPLÍCITO Y CLARO */
        .btn-volver {
            display: flex; align-items: center; gap: 8px; background: var(--color-primario); color: #fff; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 13px; transition: opacity 0.2s;
        }
        .btn-volver:hover { opacity: 0.9; color: #fff; }
        
        .user-info { font-size: 13px; font-weight: 500; color: #666; }
        .btn-logout { color: var(--color-secundario); text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid var(--color-secundario); padding: 5px 15px; border-radius: 4px; transition: all 0.2s; }
        .btn-logout:hover { background: var(--color-secundario); color: #fff; }
        
        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        .panel-header { margin-bottom: 30px; }
        .panel-title { margin: 0; font-size: 24px; color: var(--color-primario); font-weight: 600; }
        
        .perfil-wrapper { display: flex; gap: 30px; max-width: 1000px; align-items: flex-start; flex-wrap: wrap; margin: 0 auto; }
        
        .perfil-sidebar-card { flex: 1; min-width: 260px; background: #fff; border-radius: 8px; padding: 30px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid #eef0f2; text-align: center; }
        .avatar-circle { width: 90px; height: 90px; border-radius: 50%; background-color: var(--color-primario); color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px auto; }
        .perfil-sidebar-card h2 { margin: 0 0 5px 0; font-size: 20px; color: var(--color-primario); }
        .perfil-sidebar-card p { margin: 0 0 20px 0; color: #777; font-size: 14px; }
        .info-divider { height: 1px; background: #eef0f2; margin: 20px 0; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; font-size: 14px; border-bottom: 1px solid #f9f9f9; }
        .info-row:last-child { border-bottom: none; }
        .info-row span { color: #666; font-weight: 500; }
        .info-row strong { color: #333; }

        .perfil-main-content { flex: 2; min-width: 320px; display: flex; flex-direction: column; gap: 25px; }
        
        .panel-perfil { background: #fff; border-radius: 8px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid #eef0f2; }
        .panel-perfil h3 { display: flex; align-items: center; gap: 10px; margin-top: 0; color: var(--color-primario); font-size: 17px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        
        .form-row { display: flex; gap: 15px; flex-wrap: wrap; }
        .form-group { margin-bottom: 15px; flex: 1; min-width: 200px; }
        .form-group label { display: block; font-size: 13px; font-weight: bold; margin-bottom: 8px; color: #555; }
        .input-perfil { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box; background: #fdfdfd; }
        
        .btn-guardar { background: var(--color-primario); color: #fff; border: none; padding: 12px 20px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 14px; transition: opacity 0.2s; display: inline-block; margin-top: 10px; }
        .btn-guardar:hover { opacity: 0.9; }
    </style>

    <link rel="stylesheet" href="../CSS/modo_oscuro.css?v=<?php echo time(); ?>">

</head>
<body class="<?php echo $temaActual === 'oscuro' ? 'tema-oscuro' : ''; ?>">
    
    <?php 
        $basePath = '../'; 
        include __DIR__ . '/../HTML/sidebar.php'; 
    ?>

    <div class="main-wrapper">
        <?php 
            $basePath = '../';
            include __DIR__ . '/../HTML/topbar.php'; 
        ?>

        <main class="content-area">
            <div class="perfil-wrapper">
                
                <div class="perfil-sidebar-card panel-perfil">
                    <div class="avatar-circle">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <h2><?php echo htmlspecialchars($_SESSION['nombreUsuario']); ?></h2>
                    <p>
                        <?php 
                        if ($idRol === 1) echo "Administrador Principal";
                        elseif ($idRol === 2) echo "Personal Mecánico";
                        elseif ($idRol === 3) echo "Administrativo";
                        else echo "Operador del Sistema";
                        ?>
                    </p>
                    
                    <div class="info-divider"></div>
                    
                    <div class="info-row">
                        <span>Sucursal Activa</span>
                        <strong><?php echo htmlspecialchars($config['nombre_sucursal']); ?></strong>
                    </div>
                    <div class="info-row">
                        <span>Tema Actual</span>
                        <strong style="text-transform: capitalize;"><?php echo htmlspecialchars($temaActual); ?></strong>
                    </div>
                </div>

                <div class="perfil-main-content">
                    
                    <!-- Preferencia Visual Temporalmente Deshabilitada -->
                    <div class="panel-perfil" style="opacity: 0.6; pointer-events: none;">
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="5"></circle>
                                <line x1="12" y1="1" x2="12" y2="3"></line>
                                <line x1="12" y1="21" x2="12" y2="23"></line>
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                <line x1="1" y1="12" x2="3" y2="12"></line>
                                <line x1="21" y1="12" x2="23" y2="12"></line>
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                            </svg>
                            Preferencia Visual <span style="font-size: 11px; background: #eee; padding: 2px 6px; border-radius: 4px; margin-left: 10px; color: #666;">(Próximamente)</span>
                        </h3>
                        <form method="POST">
                            <input type="hidden" name="accion" value="cambiar_tema">
                            <div class="form-group">
                                <label>Tema de la Interfaz</label>
                                <select name="tema_preferido" class="input-perfil" disabled>
                                    <option value="claro" <?php echo $temaActual === 'claro' ? 'selected' : ''; ?>>Modo Claro</option>
                                    <option value="oscuro" <?php echo $temaActual === 'oscuro' ? 'selected' : ''; ?>>Modo Oscuro</option>
                                </select>
                            </div>
                        </form>
                    </div>

                    <?php if ($datosPersonal): ?>
                    <div class="panel-perfil">
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            Información Personal
                        </h3>
                        <form method="POST">
                            <input type="hidden" name="accion" value="actualizar_personal">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nombre</label>
                                    <input type="text" name="nombre" class="input-perfil" value="<?php echo htmlspecialchars($datosPersonal['nombre'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Apellido</label>
                                    <input type="text" name="apellido" class="input-perfil" value="<?php echo htmlspecialchars($datosPersonal['apellido'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Teléfono</label>
                                    <input type="number" name="telefono" class="input-perfil" value="<?php echo htmlspecialchars($datosPersonal['telefono'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>N° de Documento</label>
                                    <input type="number" name="numeroDocumentoPersonal" class="input-perfil" value="<?php echo htmlspecialchars($datosPersonal['numeroDocumentoPersonal'] ?? ''); ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn-guardar">Guardar Cambios</button>
                        </form>
                    </div>
                    <?php endif; ?>

                    <?php if ($idRol === 1): ?>
                    <div class="panel-perfil">
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 20h9"></path>
                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                            </svg>
                            Modificar Credenciales
                        </h3>
                        <form method="POST">
                            <input type="hidden" name="accion" value="cambiar_usuario">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Usuario Actual</label>
                                    <input type="text" class="input-perfil" value="<?php echo htmlspecialchars($_SESSION['nombreUsuario']); ?>" disabled>
                                </div>
                                <div class="form-group">
                                    <label>Nuevo Usuario</label>
                                    <input type="text" name="nuevo_usuario" class="input-perfil" placeholder="Escribe el nuevo nombre" required>
                                </div>
                            </div>
                            <button type="submit" class="btn-guardar">Actualizar Nombre de Usuario</button>
                        </form>
                    </div>
                    <?php endif; ?>

                    <div class="panel-perfil">
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            Seguridad y Contraseña
                        </h3>
                        <form method="POST">
                            <input type="hidden" name="accion" value="cambiar_password">
                            
                            <div class="form-group">
                                <label>Contraseña Actual</label>
                                <input type="password" name="pass_actual" class="input-perfil" placeholder="Ingresa tu contraseña actual" required>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nueva Contraseña</label>
                                    <input type="password" name="pass_nueva" class="input-perfil" placeholder="Mínimo 6 caracteres" required minlength="6">
                                </div>
                                <div class="form-group">
                                    <label>Confirmar Nueva Contraseña</label>
                                    <input type="password" name="pass_confirmar" class="input-perfil" placeholder="Repite la nueva contraseña" required minlength="6">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-guardar">Cambiar Contraseña</button>
                        </form>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            <?php if (!empty($mensaje)): ?>
                Swal.fire({
                    icon: '<?php echo $tipoMensaje; ?>',
                    title: '<?php echo $tipoMensaje === "success" ? "¡Éxito!" : "Atención"; ?>',
                    text: '<?php echo addslashes($mensaje); ?>',
                    confirmButtonColor: 'var(--color-primario)',
                    heightAuto: false, scrollbarPadding: false
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>