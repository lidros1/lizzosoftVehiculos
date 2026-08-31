<?php
/**
 * Lizzosoft Vehículos - Crear Alerta
 * Ubicación: lizzosoft_vehiculos/CRUD_Alertas/crear_alerta.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../Login/verificar_sesion.php';
require_once __DIR__ . '/../Conexion/Conexion.php';

$config = $_SESSION['cliente_config'];
$apariencia = $config['apariencia'];
$empresa_id = (int) $_SESSION['empresa_id'];
$sucursal_id = (int) $_SESSION['sucursal_id'];

$conexion = obtenerConexion();

// Obtener catálogo de servicios
$stmtSrv = $conexion->prepare("SELECT IDServicio, nombreServicio FROM servicios WHERE estado = 'Activo' AND empresa_id = ? ORDER BY nombreServicio ASC");
$stmtSrv->execute([$empresa_id]);
$servicios = $stmtSrv->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombreAlerta']);
    $idSrv = (int) $_POST['IDServicio'];

    // Lógica para Días/Meses
    $cantidadTiempo = (int) ($_POST['cantidad_tiempo'] ?? 0);
    $tipoTiempo = $_POST['tipo_tiempo'] ?? 'dias';

    // Si elige meses, multiplicamos la cantidad por 30 días
    $dias = ($tipoTiempo === 'meses') ? ($cantidadTiempo * 30) : $cantidadTiempo;

    $asunto = trim($_POST['asuntoMensaje']);
    $texto = trim($_POST['plantillaMensaje']);

    if ($idSrv <= 0) {
        $error = "Debe seleccionar un servicio asociado desde el buscador.";
    } elseif ($dias <= 0) {
        $error = "La cantidad de tiempo debe ser mayor a 0.";
    } else {
        try {
            $sql = "INSERT INTO alertas_servicios (nombreAlerta, IDServicio, diasRecordatorio, asuntoMensaje, plantillaMensaje, sucursal_id, empresa_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([$nombre, $idSrv, $dias, $asunto, $texto, $sucursal_id, $empresa_id]);
            $nuevaAlertaID = $conexion->lastInsertId();

            // Ejecutar procesamiento instantáneo para enviar correos atrasados de esta alerta
            if (!defined('INCLUIDO_COMO_LIBRERIA')) {
                define('INCLUIDO_COMO_LIBRERIA', true);
            }
            require_once __DIR__ . '/procesar_alertas.php';
            ejecutarMotorAlertas($conexion, false, $nuevaAlertaID);

            header("Location: listar_alertas.php?exito=1");
            exit;
        } catch (Exception $e) {
            $error = "Error al crear la alerta: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Nueva Alerta</title>
    <style>
        :root {
            --cp:
                <?php echo htmlspecialchars($apariencia['color_primario']); ?>
            ;
            --cf:
                <?php echo htmlspecialchars($apariencia['color_fondo']); ?>
            ;
            --bc: #dee2e6;
        }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: var(--cf);
            margin: 0;
            color: #333;
        }

        .wrapper {
            background: #fff;
            max-width: 900px;
            margin: 0 auto;
            padding: 35px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .header-box {
            border-bottom: 2px solid var(--cf);
            padding-bottom: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
        }

        h2 {
            margin: 0;
            color: var(--cp);
            font-size: 22px;
        }

        .btn {
            padding: 10px 20px;
            font-size: 13px;
            font-weight: bold;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-cancel {
            background: #e2e8f0;
            color: #333;
        }

        .btn-submit {
            background: var(--cp);
            color: #fff;
        }

        .btn-search {
            background: var(--cp);
            color: white;
            padding: 12px 20px;
            margin-left: 5px;
        }

        .btn-select {
            background: #28a745;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
        }

        .btn-select:hover {
            background: #218838;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 13px;
            color: #444;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--bc);
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: var(--cp);
            outline: none;
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        /* Ocultar flechas del input number */
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type=number] {
            -moz-appearance: textfield;
        }

        .params-box {
            background: #f8f9fa;
            border: 1px dashed var(--bc);
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 10px;
        }

        .param-btn {
            background: #fff;
            border: 1px solid #ccc;
            padding: 4px 8px;
            font-size: 11px;
            margin: 3px;
            cursor: pointer;
            border-radius: 3px;
            transition: 0.2s;
        }

        .param-btn:hover {
            background: var(--cp);
            color: white;
            border-color: var(--cp);
        }

        /* Estilos Tabla Buscador */
        .search-container {
            background: #fafafa;
            border: 1px solid #eee;
            padding: 15px;
            border-radius: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 13px;
            background: #fff;
        }

        th,
        td {
            border-bottom: 1px solid var(--bc);
            padding: 12px;
            text-align: left;
        }

        th {
            background: #f1f5f9;
            color: #555;
            text-transform: uppercase;
            font-size: 11px;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 15px;
        }

        .page-btn {
            border: 1px solid #ddd;
            background: #fff;
            padding: 6px 12px;
            cursor: pointer;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            color: var(--cp);
        }

        .page-btn.active {
            background: var(--cp);
            color: #fff;
            border-color: var(--cp);
            pointer-events: none;
        }

        .badge-select {
            background: #e8f4fd;
            color: #004085;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #b8daff;
            display: none;
            align-items: center;
            justify-content: space-between;
            font-size: 14px;
        }

        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar { background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); flex-shrink: 0; z-index: 10; }
        .user-info { font-size: 13px; font-weight: 500; color: #666; }
        .btn-logout { color: var(--color-secundario); text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid var(--color-secundario); padding: 5px 15px; border-radius: 4px; transition: all 0.2s; }
        .btn-logout:hover { background: var(--color-secundario); color: #fff; }

        .content-area { padding: 30px; overflow-y: auto; flex-grow: 1; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: var(--color-fondo); margin: 0; display: flex; height: 100vh; overflow: hidden; color: #333; }
        :root { 
            --color-primario: <?php echo htmlspecialchars($apariencia['color_primario'] ?? '#2c3e50'); ?>; 
            --color-secundario: <?php echo htmlspecialchars($apariencia['color_secundario'] ?? '#e74c3c'); ?>; 
            --color-fondo: <?php echo htmlspecialchars($apariencia['color_fondo'] ?? '#f4f6f9'); ?>; 
            --sidebar-width: 270px;
            --cp: var(--color-primario);
            --cf: var(--color-fondo);
            --bc: #dee2e6;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <?php 
        $basePath = '../'; 
        include __DIR__ . '/../HTML/sidebar.php'; 
    ?>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../HTML/topbar.php'; ?>
        <main class="content-area">
            <div class="wrapper">
        <div class="header-box">
            <h2>Crear Nueva Alerta</h2>
            <a href="listar_alertas.php" class="btn btn-cancel">Volver</a>
        </div>

        <?php if (isset($error)): ?>
            <div
                style="background:#f8d7da;color:#721c24;padding:15px;margin-bottom:20px; border-radius:4px; font-weight:bold;">
                <?php echo $error; ?>
            </div><?php endif; ?>

        <form method="POST" id="formAlerta">
            <div style="display: flex; gap: 20px; margin-bottom: 25px;">
                <div class="form-group" style="flex: 2; margin-bottom:0;">
                    <label>Nombre Identificatorio de la Alerta</label>
                    <input type="text" name="nombreAlerta" class="form-control" required
                        placeholder="Ej: Recordatorio Service 10.000km">
                </div>
                <div class="form-group" style="flex: 1; margin-bottom:0;">
                    <label>Tiempo de Transcurso</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="number" name="cantidad_tiempo" class="form-control" required min="1"
                            placeholder="Ej: 6" style="flex: 1;">
                        <select name="tipo_tiempo" class="form-control" style="flex: 1;">
                            <option value="meses">Meses</option>
                            <option value="dias">Días</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Servicio Disparador (Se basará en la última OT de este servicio)</label>

                <div id="servicio_seleccionado_box" class="badge-select">
                    <div>
                        <strong>Servicio vinculado:</strong> <span id="srv_nombre_display"
                            style="font-size: 15px; margin-left: 5px;"></span>
                    </div>
                    <button type="button" class="btn btn-cancel" onclick="cambiarServicio()">Modificar Servicio</button>
                </div>

                <div id="buscador_container" class="search-container">
                    <div style="display: flex; align-items: center;">
                        <input type="text" id="buscar_servicio" placeholder="Buscar servicio por nombre..."
                            class="form-control" autocomplete="off">
                        <button type="button" id="btn_buscar_srv" class="btn btn-search">Buscar</button>
                    </div>

                    <table id="tabla_servicios">
                        <thead>
                            <tr>
                                <th>Nombre del Servicio Operativo</th>
                                <th style="width: 100px; text-align: center;">Acción</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <div id="paginacion_servicios" class="pagination"></div>
                </div>

                <input type="hidden" name="IDServicio" id="IDServicio" required>
            </div>

            <div class="form-group">
                <label>Asunto del Correo</label>
                <input type="text" name="asuntoMensaje" class="form-control" required
                    placeholder="Ej: Es hora del service de tu [VEHICULO_MARCA]">
            </div>

            <div class="form-group">
                <label>Cuerpo del Mensaje (Plantilla)</label>
                <div class="params-box">
                    <strong style="font-size: 12px; color:#555;">Variables Dinámicas (Haz clic para
                        insertar):</strong><br>
                    <button type="button" class="param-btn" onclick="insertParam('[CLIENTE_NOMBRE]')">Nombre</button>
                    <button type="button" class="param-btn"
                        onclick="insertParam('[CLIENTE_APELLIDO]')">Apellido</button>
                    <button type="button" class="param-btn" onclick="insertParam('[VEHICULO_MARCA]')">Marca</button>
                    <button type="button" class="param-btn" onclick="insertParam('[VEHICULO_MODELO]')">Modelo</button>
                    <button type="button" class="param-btn" onclick="insertParam('[VEHICULO_PATENTE]')">Patente</button>
                    <button type="button" class="param-btn" onclick="insertParam('[SERVICIO_NOMBRE]')">Nombre
                        Servicio</button>
                    <button type="button" class="param-btn" onclick="insertParam('[FECHA_ULTIMO_SERVICIO]')">Fecha
                        Último Servicio</button>
                </div>
                <textarea name="plantillaMensaje" id="cuerpoMsg" class="form-control" required
                    placeholder="Hola [CLIENTE_NOMBRE], te recordamos que..."></textarea>
            </div>

            <div style="text-align: right; border-top: 1px solid var(--bc); padding-top: 20px;">
                <button type="submit" class="btn btn-submit" style="padding: 12px 30px; font-size: 15px;">Guardar y
                    Activar Alerta</button>
            </div>
        </form>
    </div>

    <script>
        // Inserción de parámetros en la plantilla
        function insertParam(param) {
            const txt = document.getElementById('cuerpoMsg');
            const start = txt.selectionStart;
            const end = txt.selectionEnd;
            txt.value = txt.value.substring(0, start) + param + txt.value.substring(end);
            txt.focus();
            txt.selectionEnd = start + param.length;
        }

        // Lógica del Buscador Paginado
        const servicios = <?php echo json_encode($servicios); ?>;
        let srvFiltrados = [...servicios];
        let paginaActual = 1;
        const limit = 7;
        const tbody = document.querySelector('#tabla_servicios tbody');
        const pagDiv = document.getElementById('paginacion_servicios');

        function renderTabla() {
            tbody.innerHTML = '';
            const inicio = (paginaActual - 1) * limit;
            const fin = inicio + limit;
            const paginaDatos = srvFiltrados.slice(inicio, fin);

            if (paginaDatos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="2" style="text-align:center; padding:20px; color:#777;">No se encontraron servicios que coincidan con la búsqueda.</td></tr>';
                pagDiv.innerHTML = '';
                return;
            }

            paginaDatos.forEach(s => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td><strong style="color:var(--cp);">${s.nombreServicio}</strong></td>
                <td style="text-align:center;">
                    <button type="button" class="btn-select" onclick="seleccionarServicio(${s.IDServicio}, '${s.nombreServicio.replace(/'/g, "\\'")}')">Seleccionar</button>
                </td>
            `;
                tbody.appendChild(tr);
            });
            renderPaginacion();
        }

        function renderPaginacion() {
            pagDiv.innerHTML = '';
            const totalPaginas = Math.ceil(srvFiltrados.length / limit);
            if (totalPaginas <= 1) return;

            for (let i = 1; i <= totalPaginas; i++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `page-btn ${i === paginaActual ? 'active' : ''}`;
                btn.textContent = i;
                btn.onclick = () => { paginaActual = i; renderTabla(); };
                pagDiv.appendChild(btn);
            }
        }

        document.getElementById('btn_buscar_srv').onclick = () => {
            const val = document.getElementById('buscar_servicio').value.toLowerCase().trim();
            srvFiltrados = servicios.filter(s => s.nombreServicio.toLowerCase().includes(val));
            paginaActual = 1;
            renderTabla();
        };

        window.seleccionarServicio = function (id, nombre) {
            document.getElementById('IDServicio').value = id;
            document.getElementById('srv_nombre_display').textContent = nombre;
            document.getElementById('servicio_seleccionado_box').style.display = 'flex';
            document.getElementById('buscador_container').style.display = 'none';
        };

        window.cambiarServicio = function () {
            document.getElementById('IDServicio').value = '';
            document.getElementById('servicio_seleccionado_box').style.display = 'none';
            document.getElementById('buscador_container').style.display = 'block';
        };

        // Validación antes del envío
        document.getElementById('formAlerta').addEventListener('submit', function (e) {
            if (!document.getElementById('IDServicio').value) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Atención',
                    text: 'Debe seleccionar un servicio desde la tabla buscadora.',
                    confirmButtonColor: 'var(--cp)'
                });
            }
        });

        // Render Inicial
        renderTabla();
    </script>
        </main>
    </div>
</body>

</html>