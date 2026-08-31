<?php
require_once '../Login/Auth.php';
$auth = new Auth();
if (!$auth->check()) {
    header("Location: ../Login/login.php");
    exit();
}
$termino = $_SESSION['termino_vehiculo'] ?? 'Vehículo';
$usaReclamos = $_SESSION['config_cliente']['modulos']['usa_reclamos'] ?? true;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Gestión de Registros</title>
    <link rel="stylesheet" href="../CSS/style.css" />
</head>

<body>
    <?php include 'interfaz_global.php'; ?>
    <div class="main-container">
        <h1 class="titulo">Gestión de Órdenes de <?= $termino ?>s</h1>
        <div class="menu-botones">
            <a href="../registrosServicios/crear_registroServicio.php" class="btn">Crear Orden de <?= $termino ?></a>
            <a href="../registrosServicios/listar_registrosServicios.php" class="btn">Listar Órdenes</a>
            <a href="../registrosServicios/seleccionar_ordenes.php" class="btn">Editar Orden</a>
            <?php if ($usaReclamos): ?>
                <a href="menuReclamos.php" class="btn">Reclamos</a>
            <?php endif; ?>
            <a href="../registrosServicios/serviciosPersonal.php" class="btn">Servicios por Personal</a>
        </div>
        <div class="botones-editar">
            <a href="inicio.php" class="btn volver">Volver</a>
        </div>
    </div>
</body>

</html>