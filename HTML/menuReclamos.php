<?php
session_start();
require_once '../Login/Auth.php';

$auth = new Auth();
if (!$auth->check() || !isset($_SESSION['config_cliente'])) {
    header("Location: ../Login/login.php");
    exit();
}

$usaReclamos = $_SESSION['config_cliente']['modulos']['usa_reclamos'] ?? true;
if (!$usaReclamos) {
    header("Location: ../inicio.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reclamos</title>
    <link rel="stylesheet" href="../CSS/style.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <?php include 'interfaz_global.php'; ?>
    <div class="main-container">
        <h1 class="titulo">Reclamos</h1>

        <div class="menu-botones">
            <a href="../registrosServicios/crear_reclamo.php" class="btn">Crear Reclamo</a>
            <a href="../registrosServicios/editar_reclamo.php" class="btn">Editar Reclamo</a>
            <a href="../registrosServicios/listar_reclamos.php" class="btn">Listar Reclamos</a>
        </div>

        <div class="botones-editar" style="margin-top: 30px;">
            <a href="registrosServicios.php" class="btn volver">Volver</a>
        </div>
    </div>

    <script>
        setInterval(function () {
            fetch('../Login/verificar_sesion.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.sesion_activa) {
                        localStorage.setItem('lastPageBeforeTimeout', window.location.href);
                        Swal.fire({
                            icon: 'warning',
                            title: 'Sesión expirada',
                            text: 'Su sesión ha expirado por inactividad (12 horas). Será redirigido al login.',
                            confirmButtonColor: 'var(--color-primario)'
                        }).then(() => {
                            window.location.href = '../Login/logout.php?timeout=1';
                        });
                    }
                })
                .catch(error => {
                    console.error('Error verificando sesión:', error);
                    localStorage.setItem('lastPageBeforeTimeout', window.location.href);
                    window.location.href = '../Login/logout.php?timeout=1';
                });
        }, 300000);

        let actividadUsuario = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        actividadUsuario.forEach(function (evento) {
            document.addEventListener(evento, function () {
                fetch('../Login/verificar_sesion.php', { method: 'POST' });
            }, true);
        });
    </script>
</body>

</html>