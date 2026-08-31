# Plan de desarrollo y checklist

## Fases sugeridas

### Fase 1 — Fundamentos Multitenant

1. Completar [[Entidades Principales]] validando el script `.sql` con los Triggers y Procedimientos.

2. Implementar carga de `clientes_config/{empresa}/config.php` en sesión.

3. Checklist de seguridad y aislamiento por `empresa_id` en [[Autenticacion y Sesiones]].

### Fase 2 — Entidades maestras

1. [[GestionClientes]] — CRUD con filtro obligatorio de empresa.

2. [[GestionVehiculos]] — Validación de patente única por empresa e inputs dinámicos según rubro (Motos, Autos, Camiones).

3. [[GestionUsuarios]] y [[GestionPersonal]] — Creación dual usando SP `sp_crear_personal_usuario`.

4. [[GestionMarcasModelos]] — Dependencia de cascada Marca -> Modelo filtrado por tenant.

### Fase 3 — Operación del taller

1. [[Flujo Orden de Servicio]] — Estados automáticos vía Trigger `tr_actualizar_estado_orden`.

2. Integración [[GestionStock]] — Descuento automático de repuestos vía Trigger `reservar_stock_servicio`.

3. [[Flujo Reclamos y Garantias]] — Vinculación a orden de servicio original.

### Fase 4 — Reportes y cierre

1. Homogeneizar [[Reportes Multitenant]] consumiendo los Procedimientos Almacenados.

2. Pruebas de aislamiento de datos entre sucursales y empresas.

3. Entrega: manual de usuario + diagrama ER actualizado.

### Prioridad inmediata

| Prioridad | Tarea |

|-----------|-------|

| Alta | Carga dinámica de variables CSS desde `config.php` en `interfaz_global.php` |

| Alta | CRUD Vehículos adaptativo según `IDRubro` |

| Media | Testing de Triggers de Stock al generar órdenes |

| Baja | Export PDF en reportes financieros |

---

## Checklist de implementación

Marcar `[x]` al completar.

### Infraestructura y Arquitectura

- [ ] Script `.sql` con vistas, procedures y triggers en el repositorio.

- [ ] Carpetas `clientes_config/` creadas para entornos de prueba (Ej: Taller Los Motores).

- [ ] Variables de entorno para credenciales (no hardcode en `Conexion.php`).

### Seguridad y Multitenencia

- [ ] Clave `empresa_id` validada en POST/GET de absolutamente todos los CRUDs.

- [ ] `MD5` / hash en usuarios nuevos (alineado con la BD actual).

- [ ] Trigger de Logs `tr_log_cambio_usuario`) validado en edición de datos.

### Módulos CRUD

- [ ] [[GestionClientes]] — Listado aislado por empresa.

- [ ] [[GestionVehiculos]] — Ocultar/Mostrar campos (ej. Cilindrada vs Ejes) leyendo sesión.

- [ ] [[GestionMarcasModelos]] — ABM completado.

- [ ] [[GestionServicios]] — Precios y categorización por rubro.

### Taller (operativo)

- [ ] [[Flujo Orden de Servicio]] — UI adaptativa para Recepción de Vehículo.

- [ ] Cambio de estado a `Finalizado-NE` dispara email al cliente (vía PHPMailer).

- [ ] [[Flujo Reclamos y Garantias]] — Búsqueda de historial restringida a la empresa.

### Stock

- [ ] Inserción en `detalle_servicios` dispara correctamente el trigger de inventario.

- [ ] Módulo de transferencias entre sucursales `transferencias_stock`) operativo.

### Reportes

- [ ] Consumo del SP `sp_carga_trabajo_personal` en reporte de mecánicos.

- [ ] Consumo del SP `sp_reporte_ingresos_servicios` para finanzas.

- [ ] Gráficos renderizados usando los colores `color_principal` del taller.

### UI / Frontend

- [ ] Layout `interfaz_global.php` inyectando colores y logo corporativo de la sesión.

- [ ] Modo oscuro / Responsive validado.