# Reportes, Consultas y Estadísticas

Acceso: área 2 → `HTML/reportes.php`. Filtrar por `empresa_id` y `sucursal_id`; prepared statements en filtros; gráficos con `color_principal` / `color_acento` de `cliente_config`.

## Reportes

| Reporte | Archivo | Base de datos |
|---------|---------|---------------|
| Estado de servicios | `reporteEstadoServicio.php` | `CALL sp_reporte_servicios_estado(?)`, vista `vista_servicios_por_estado` |
| Carga de trabajo | `cargaTrabajoPersonal.php` | `CALL sp_carga_trabajo_personal(fecha_ini, fecha_fin)` |
| Ingresos | `ingresosServicio.php` | `CALL sp_reporte_ingresos_servicios(...)` — suma `costoServicio` en `detalleregistro` |
| Productividad | `reportePersonalProductividad.php` | `CALL sp_reporte_personal_productividad(...)` |
| Accesos | `reporteAccesos.php` | `CALL sp_reporte_accesos(...)` → tabla `logs_accesos` |
| Inventario | `GestionStock/reportesInventario.php` | Ver [[GestionStock]] |

### Detalles operativos

- **Estado:** al pasar a `Finalizado-NE`, enviar mail al cliente con `Conexion/Mailer.php`.
- **Carga de trabajo:** incluye `patente`, `numeroChasis`, `kilometrajeIngreso`, `horasIngreso` según rubro.
- **Accesos:** alimentado también por trigger `tr_log_cambio_usuario`.

## Tareas globales

- [ ] Filtros reutilizables (fechas, sucursal)
- [ ] Export PDF/Excel
- [ ] Validar permisos área 2 en cada script

## Relacionado

- [[Logs de Acceso]], [[Flujo Orden de Servicio]]

## Estado

`Parcial`
