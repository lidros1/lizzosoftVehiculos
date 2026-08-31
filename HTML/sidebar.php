<?php
/**
 * Lizzosoft Vehículos - Componente Sidebar Global
 */
$basePath = isset($basePath) ? $basePath : '';

// Asegurar que las variables necesarias estén definidas por si no lo estuvieran
$config     = $_SESSION['cliente_config'] ?? [];
$idRol      = (int)($_SESSION['IDRol'] ?? 0);
$areas      = $_SESSION['areas_permitidas'] ?? [];
$labelRodado = $config['labels']['vehiculo_plural'] ?? 'Vehículos';

// Determinar el script actual para marcar el menú activo
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$temaActual = $_SESSION['tema_preferido'] ?? 'claro';
?>
<?php if ($temaActual === 'oscuro'): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($basePath); ?>CSS/modo_oscuro.css?v=<?php echo time(); ?>">
    <script>document.documentElement.classList.add("tema-oscuro"); document.body.classList.add("tema-oscuro");</script>
<?php endif; ?>
<style>
    /* SIDEBAR */
    .sidebar { width: var(--sidebar-width, 270px); background-color: var(--color-primario, #2c3e50); color: #fff; display: flex; flex-direction: column; flex-shrink: 0; transition: width 0.3s ease; overflow-y: auto; z-index: 1000; border-right: 1px solid rgba(0,0,0,0.1); }
    .sidebar::-webkit-scrollbar { width: 5px; } 
    .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 5px; }
    .sidebar.collapsed { width: 70px; }
    .sidebar-toggle-bar { padding: 15px; display: flex; justify-content: flex-end; align-items: center; background-color: rgba(0,0,0,0.1); }
    .sidebar.collapsed .sidebar-toggle-bar { justify-content: center; }
    .btn-toggle-sidebar { background: transparent; border: none; color: #fff; cursor: pointer; padding: 5px; display: flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.2s; }
    .btn-toggle-sidebar:hover { background: rgba(255,255,255,0.15); }
    .sidebar-header { padding: 15px 20px; background-color: rgba(0,0,0,0.05); text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05); white-space: nowrap; transition: opacity 0.2s; }
    .sidebar.collapsed .sidebar-header { opacity: 0; padding: 0; height: 0; border: none; }
    .sidebar-header h3 { margin: 0; font-size: 16px; color: #fff; }
    .nav-menu { padding: 10px 0; flex-grow: 1; }
    .nav-title { padding: 15px 20px 8px 20px; font-size: 10px; text-transform: uppercase; color: rgba(255,255,255,0.4); font-weight: bold; white-space: nowrap; }
    .sidebar.collapsed .nav-title { display: none; }
    .nav-link { display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; color: rgba(255,255,255,0.85); text-decoration: none; font-size: 14px; border-left: 3px solid transparent; transition: all 0.2s ease; cursor: pointer; white-space: nowrap; }
    .nav-link:hover, .nav-link.active { background-color: rgba(255,255,255,0.08); color: #fff; border-left-color: var(--color-secundario, #e74c3c); }
    .nav-content { display: flex; align-items: center; }
    .nav-icon { margin-right: 12px; display: flex; align-items: center; justify-content: center; width: 20px; }
    .sidebar.collapsed .nav-text { display: none; }
    .sidebar.collapsed .menu-arrow { display: none; }
    .sidebar.collapsed .nav-link { justify-content: center; padding: 15px 0; }
    .sidebar.collapsed .nav-icon { margin-right: 0; transform: scale(1.1); }
    
    .sub-menu { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-in-out; background-color: rgba(0,0,0,0.12); }
    .sub-menu.open { max-height: 800px; }
    .sub-menu .nav-link { padding-left: 45px; font-size: 13px; }
    .menu-arrow { transition: transform 0.3s ease; display: flex; align-items: center; } 
    .menu-arrow.open { transform: rotate(180deg); }
</style>

<aside class="sidebar collapsed" id="sidebar">
    <div class="sidebar-toggle-bar">
        <button type="button" class="btn-toggle-sidebar" id="sidebarToggle">
            <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
    </div>
    <div class="sidebar-header">
        <h3>Lizzosoft System</h3>
        <p><?php echo htmlspecialchars($config['nombre_empresa'] ?? 'Empresa'); ?></p>
    </div>

    <nav class="nav-menu">
        
        <div class="nav-title">Cuenta</div>
        <a class="nav-link submenu-trigger">
            <div class="nav-content">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </span>
                <span class="nav-text">Cuenta</span>
            </div>
            <span class="menu-arrow">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </span>
        </a>
        <div class="sub-menu">
            <a href="<?php echo $basePath; ?>Perfil/perfil.php" class="nav-link">
                <div class="nav-content">
                    <span class="nav-text">Perfil</span>
                </div>
            </a>
        </div>

        <div class="nav-title">Operaciones</div>
        
        <?php if ((in_array($idRol, [1, 2, 3]) || in_array(1, $areas)) && ($config['modulos']['modulo_ordenes'] ?? true)): ?>
            <a href="<?php echo $basePath; ?>inicio.php" class="nav-link <?php echo ($currentScript == 'inicio.php') ? 'active' : ''; ?>">
                <div class="nav-content">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                    </span>
                    <span class="nav-text">Órdenes de Trabajo</span>
                </div>
            </a>
        <?php endif; ?>

        <?php if ((in_array($idRol, [1, 2, 3]) || in_array(1, $areas)) && ($config['modulos']['modulo_presupuestos'] ?? true)): ?>
            <a href="<?php echo $basePath; ?>CRUD_Presupuestos/listar_presupuestos.php" class="nav-link <?php echo ($currentScript == 'listar_presupuestos.php') ? 'active' : ''; ?>">
                <div class="nav-content">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="6" width="20" height="12" rx="2"></rect>
                            <circle cx="12" cy="12" r="2"></circle>
                            <path d="M6 12h.01M18 12h.01"></path>
                        </svg>
                    </span>
                    <span class="nav-text">Presupuestos</span>
                </div>
            </a>
        <?php endif; ?>

        <?php if ((in_array($idRol, [1, 2, 3]) || in_array(7, $areas)) && ($config['modulos']['modulo_reclamos'] ?? true)): ?>
            <a href="<?php echo $basePath; ?>Reclamos/menuReclamos.php" class="nav-link <?php echo ($currentScript == 'menuReclamos.php') ? 'active' : ''; ?>">
                <div class="nav-content">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                    </span>
                    <span class="nav-text">Gestión de Reclamos</span>
                </div>
            </a>
        <?php endif; ?>

        <?php if ((in_array($idRol, [1, 2, 3]) || in_array(9, $areas)) && ($config['modulos']['modulo_alertas'] ?? true)): ?>
            <a href="<?php echo $basePath; ?>CRUD_Alertas/listar_alertas.php" class="nav-link <?php echo ($currentScript == 'listar_alertas.php') ? 'active' : ''; ?>">
                <div class="nav-content">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                    </span>
                    <span class="nav-text">Gestión de Alertas</span>
                </div>
            </a>
        <?php endif; ?>

        <?php if ((in_array($idRol, [1, 3]) || in_array(2, $areas)) && ($config['modulos']['modulo_reportes'] ?? true)): ?>
            <div class="nav-title">Análisis</div>
            <a class="nav-link submenu-trigger">
                <div class="nav-content">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                    </span>
                    <span class="nav-text">Reportes y Estadísticas</span>
                </div>
                <span class="menu-arrow">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </span>
            </a>
            <div class="sub-menu">
                <a href="<?php echo $basePath; ?>Reportes/ingresosServicio.php" class="nav-link <?php echo ($currentScript == 'ingresosServicio.php') ? 'active' : ''; ?>"><div class="nav-content"><span class="nav-text">Ingresos por Servicios</span></div></a>
                <a href="<?php echo $basePath; ?>Reportes/reportePersonalProductividad.php" class="nav-link <?php echo ($currentScript == 'reportePersonalProductividad.php') ? 'active' : ''; ?>"><div class="nav-content"><span class="nav-text">Productividad del Personal</span></div></a>
                <a href="<?php echo $basePath; ?>Reportes/reporteAccesos.php" class="nav-link <?php echo ($currentScript == 'reporteAccesos.php') ? 'active' : ''; ?>"><div class="nav-content"><span class="nav-text">Accesos y Auditoría</span></div></a>
            </div>
        <?php endif; ?>

        <?php if (in_array($idRol, [1, 2]) || count(array_intersect([4, 5, 6, 8], $areas)) > 0): ?>
            <div class="nav-title">Administración</div>
            <a class="nav-link submenu-trigger">
                <div class="nav-content">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                        </svg>
                    </span>
                    <span class="nav-text">Configuración Sistema</span>
                </div>
                <span class="menu-arrow">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </span>
            </a>
            <div class="sub-menu">
                <?php if (in_array($idRol, [1]) || in_array(4, $areas)): ?><a href="<?php echo $basePath; ?>CRUD_Personal/listar_personal.php" class="nav-link"><div class="nav-content"><span class="nav-text">Personal y Usuarios</span></div></a><?php endif; ?>
                <?php if (in_array($idRol, [1, 2, 3]) || in_array(5, $areas)): ?><a href="<?php echo $basePath; ?>CRUD_Clientes/listar_clientes.php" class="nav-link"><div class="nav-content"><span class="nav-text">Clientes</span></div></a><?php endif; ?>
                <?php if (in_array($idRol, [1, 2, 3]) || in_array(6, $areas)): ?><a href="<?php echo $basePath; ?>CRUD_Servicios/listar_servicios.php" class="nav-link"><div class="nav-content"><span class="nav-text">Servicios</span></div></a><?php endif; ?>
                <?php if (in_array($idRol, [1, 2, 3]) || in_array(8, $areas)): ?><a href="<?php echo $basePath; ?>CRUD_Vehiculos/listar_vehiculos.php" class="nav-link"><div class="nav-content"><span class="nav-text"><?php echo htmlspecialchars($labelRodado); ?></span></div></a><?php endif; ?>
            </div>
        <?php endif; ?>

    </nav>
    <script>
        // Verificación automática diaria de alertas en segundo plano
        (function() {
            try {
                const lastCheck = localStorage.getItem('last_alert_check');
                const today = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
                
                if (lastCheck !== today) {
                    // No se revisó hoy, ejecutar en segundo plano de forma silenciosa
                    fetch('<?php echo $basePath; ?>CRUD_Alertas/procesar_alertas.php?ajax=1')
                        .then(r => r.json())
                        .then(data => {
                            // Guardar que hoy ya se procesó para no saturar el servidor en cada recarga
                            localStorage.setItem('last_alert_check', today);
                            console.log("Alertas automáticas procesadas para el día de hoy:", today);
                        })
                        .catch(e => console.error("Error al procesar alertas automáticas:", e));
                }
            } catch (e) {
                console.error("No se pudo iniciar el cron local", e);
            }
        })();
    </script>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                if (sidebar.classList.contains('collapsed')) {
                    document.querySelectorAll('.sidebar .sub-menu.open').forEach(menu => {
                        menu.classList.remove('open');
                        const arrow = menu.previousElementSibling.querySelector('.menu-arrow');
                        if(arrow) arrow.classList.remove('open');
                    });
                }
            });
        }

        document.querySelectorAll('.sidebar .submenu-trigger').forEach(trigger => {
            // Eliminar event listeners anteriores si existieran duplicados
            const newTrigger = trigger.cloneNode(true);
            trigger.parentNode.replaceChild(newTrigger, trigger);
            
            newTrigger.addEventListener('click', function(e) {
                e.preventDefault();
                if (sidebar.classList.contains('collapsed')) sidebar.classList.remove('collapsed');
                
                const subMenu = this.nextElementSibling;
                const arrow = this.querySelector('.menu-arrow');

                if (subMenu.classList.contains('open')) {
                    subMenu.classList.remove('open');
                    if (arrow) arrow.classList.remove('open');
                } else {
                    subMenu.classList.add('open');
                    if (arrow) arrow.classList.add('open');
                }
            });
        });
    });
</script>
