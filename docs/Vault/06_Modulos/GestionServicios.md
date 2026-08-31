# Módulo: Catálogo de Servicios

Define tipos de trabajo del taller por categoría, con descripción y costo. **No** confundir con [[Flujo Orden de Servicio]] (órdenes en taller).

## Datos principales

- `servicios`, `servicioscategoria`, `categoriasservicios`
- `empresa_id` en todas las operaciones

## Reglas

- Nombre de servicio único por categoría y empresa
- Transacción al crear servicio + relación categoría

## Archivos clave

- `CRUD_Servicios/Servicios.php`
- `CRUD_Servicios/crear_servicio.php`, `editar_servicio.php`, `listar_servicios.php`
- `configuracionSistema/areaServicios.php`

## Relacionado

- [[Flujo Orden de Servicio]] — selección de ítems al crear orden
- [[Reportes Multitenant]]

## Estado

`Implementado`
