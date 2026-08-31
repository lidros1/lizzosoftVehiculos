# Módulo: Gestión de Vehículos

Administra vehículos de clientes y sus atributos (tipos, marcas, modelos) por empresa.

## Variabilización por rubro

Formularios y etiquetas según `clientes_config/{cliente}/config.php` y `IDRubro` de la sucursal. Vista adaptativa: `HTML/registrosServicios.php`.

| Rubro | Campos destacados |
|-------|-------------------|
| Motos | Cilindrada, tipo de motor |
| Autos / camionetas | Combustible, puertas |
| Pesados (camiones / trafics) | Carga máxima, ejes |

**Regla UI:** no hardcodear "Moto" o "Camión"; usar `$_SESSION['cliente_config']` / etiquetas del config.

## Marcas, modelos y tipos

Tablas: `marcasvehiculos`, `modelosvehiculos`, `tiposvehiculos`.

- Filtrar siempre por `empresa_id`
- Modelo requiere `IDMarcaVehiculo` válido
- Carga en cascada marca → modelo (Fetch/AJAX)

Menús de administración: `configuracionSistema/menuMarcas.php`, `menuModelos.php`, `menuTipos.php`

## Datos principales

- `IDVehiculo`, `patente`, `IDCliente`, `colorVehiculo`, `numeroChasis`, `kilometrajeIngreso`, `horasIngreso`
- `IDTipoVehiculo`, `IDMarcaVehiculo`, `IDModeloVehiculo`, `empresa_id`, `sucursal_id`

## Archivos clave

- `CRUD_Bicicletas/` o módulo vehículos equivalente en el repo activo
- `configuracionSistema/areaBicicletas.php` (o área vehículos)
- `clientes_config/{cliente}/config.php`

## Relacionado

- [[GestionClientes]], [[Flujo Orden de Servicio]], [[Configuracion del Sistema]]

## Tareas pendientes

- [ ] Patente/serie única por empresa
- [ ] Fotos o adjuntos del vehículo

## Estado

`Implementado`
