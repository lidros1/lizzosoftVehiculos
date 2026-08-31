<?php
/**
 * Lizzosoft Vehículos - Componente Topbar
 */

// Si no está definida la variable $basePath, asumimos un nivel de profundidad (../)
// Esto asegura que la ruta a los scripts de login funcione desde cualquier carpeta
if (!isset($basePath)) {
    $basePath = '../';
}
?>
<style>
.topbar { background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); flex-shrink: 0; z-index: 90; border-bottom: 1px solid #eef0f2; }
.user-info { font-size: 15px; color: #555; font-weight: 500; display: flex; align-items: center; }
.user-info strong { color: var(--color-primario, #2c3e50); }
.btn-logout { color: var(--color-secundario, #e74c3c); text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid var(--color-secundario, #e74c3c); padding: 5px 15px; border-radius: 4px; transition: all 0.2s; white-space: nowrap; }
.btn-logout:hover { background: var(--color-secundario, #e74c3c); color: #fff; }
</style>
<header class="topbar">
    <?php if (isset($mostrarVolver) && $mostrarVolver): ?>
        <div class="topbar-left" style="display: flex; align-items: center; gap: 15px;">
            <a href="<?php echo $basePath; ?>inicio.php" class="btn-volver" style="display: flex; align-items: center; gap: 5px; color: #fff; background: var(--color-primario); padding: 8px 15px; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: bold; transition: opacity 0.2s;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Volver
            </a>
            <div class="user-info">
    <?php else: ?>
        <div class="user-info">
    <?php endif; ?>
        Sucursal: 
        <?php if (!empty($_SESSION['sucursales_admin_disponibles']) && count($_SESSION['sucursales_admin_disponibles']) > 1): ?>
            <form action="<?php echo $basePath; ?>Login/cambiar_sucursal.php" method="POST" style="display:inline-block; margin: 0 5px;">
                <select name="sucursal_id" onchange="this.form.submit()" style="padding: 2px 5px; border-radius: 4px; border: 1px solid #ccc; font-size: 13px; background-color: #f8f9fa; cursor: pointer; outline: none;">
                    <?php foreach ($_SESSION['sucursales_admin_disponibles'] as $suc): ?>
                        <option value="<?php echo $suc['id']; ?>" <?php echo ($suc['id'] == $_SESSION['sucursal_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($suc['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php else: ?>
            <strong><?php echo htmlspecialchars($_SESSION['cliente_config']['nombre_sucursal'] ?? ''); ?></strong> 
        <?php endif; ?>
        | Operador: <strong><?php echo htmlspecialchars($_SESSION['nombreUsuario'] ?? ''); ?></strong>
    </div>
    <?php if (isset($mostrarVolver) && $mostrarVolver): ?>
        </div> <!-- cierra topbar-left -->
    <?php endif; ?>
    <a href="<?php echo $basePath; ?>Login/logout.php" class="btn-logout">Cerrar Sesión</a>
</header>
