<?php
session_start();

// REGLA DE ARQUITECTURA: Zona horaria obligatoria
date_default_timezone_set('America/Argentina/Buenos_Aires');

$color_primario = $_SESSION['cliente_config']['color_primario'] ?? '#0056b3';
$nombre_empresa = $_SESSION['cliente_config']['nombre_empresa'] ?? 'Lizzosoft';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - <?php echo htmlspecialchars($nombre_empresa); ?></title>
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
        .contenedor-recuperar { 
            background: #fff; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
            width: 100%; 
            max-width: 400px; 
        }
        .contenedor-recuperar h2 { 
            color: var(--color-primario); 
            text-align: center; 
            margin-bottom: 15px; 
        }
        .contenedor-recuperar p {
            font-size: 14px;
            color: #555;
            line-height: 1.5;
            text-align: center;
            margin-bottom: 20px;
        }
        .form-group { 
            margin-bottom: 20px; 
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
    <div class="contenedor-recuperar">
        <h2>Recuperar Contraseña</h2>
        <p>Por seguridad, ingresa tu Nombre de Usuario y tu Correo Electrónico para recibir el código de verificación.</p>
        
        <form id="form-recuperar">
            <div class="form-group">
                <label for="usuario">Nombre de Usuario</label>
                <input type="text" id="usuario" name="usuario" required autofocus>
            </div>

            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="botones">
                <button type="submit" class="btn btn-primary" id="btn-enviar">Enviar Código</button>
                <a href="login.php" class="btn btn-secondary">Volver</a>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('form-recuperar').addEventListener('submit', function(e) {
            e.preventDefault();
            const usuario = document.getElementById('usuario').value;
            const email = document.getElementById('email').value;
            const btnEnviar = document.getElementById('btn-enviar');
            
            btnEnviar.disabled = true;
            btnEnviar.innerText = "Enviando...";

            fetch('procesar_recuperacion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'usuario=' + encodeURIComponent(usuario) + '&email=' + encodeURIComponent(email)
            })
            .then(response => response.json())
            .then(data => {
                btnEnviar.disabled = false;
                btnEnviar.innerText = "Enviar Código";

                Swal.fire({
                    icon: data.status,
                    title: data.status === 'success' ? 'Código Enviado' : 'Atención',
                    text: data.message,
                    confirmButtonColor: 'var(--color-primario)',
                    heightAuto: false,
                    scrollbarPadding: false
                }).then(() => {
                    if (data.status === 'success') {
                        // CORRECCIÓN: Ahora pasamos también el usuario por la URL para evitar choques en el paso 2
                        window.location.href = 'ingresar_codigo.php?email=' + encodeURIComponent(email) + '&usuario=' + encodeURIComponent(usuario);
                    }
                });
            })
            .catch(error => {
                btnEnviar.disabled = false;
                btnEnviar.innerText = "Enviar Código";
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudo procesar la solicitud en este momento.',
                    confirmButtonColor: 'var(--color-primario)',
                    heightAuto: false,
                    scrollbarPadding: false
                });
            });
        });
    </script>
</body>
</html>