# Configuración del Sistema

## Función

Punto de entrada para administrar entidades operativas y parámetros del sistema: personal, usuarios, catálogo de servicios, clientes y atributos de los vehículos.

## Acceso

- Menú principal → Área correspondiente → `HTML/configuracionSistema.php`

- Validación estricta por `permisosusuarios` cruzando `IDUsuario` y `IDAreaSistema`.

## Sub-áreas y Mapeo de CRUDs

| Área | Archivo menú | CRUD principal |

|------|--------------|----------------|

| Personal / Usuarios | `configuracionSistema/areaPersonal.php` | `CRUD_Personal/` y `CRUD_Usuarios/` |

| Servicios | `configuracionSistema/areaServicios.php` | `CRUD_Servicios/` |

| Clientes | `configuracionSistema/areaClientes.php` | `CRUD_Clientes/` |

| Vehículos | `configuracionSistema/areaVehiculos.php` | `CRUD_Vehiculos/` |

## Atributos de Vehículos (Taxonomía)

Gestión de catálogos dependientes según el rubro de la sucursal:

- `configuracionSistema/menuTiposVehiculos.php`

- `configuracionSistema/menuMarcas.php`

- `configuracionSistema/menuModelos.php`

- Estos interactúan directamente con las reglas de [[GestionMarcasModelos]].

## Patrón de cada sub-área

1. Verificar permisos en la BD (crear/editar/listar) para el usuario activo.

2. Enlazar a los scripts operativos dentro de `CRUD_*`.

3. Inyectar en cada petición SQL la variable `$_SESSION['cliente_config']['empresa_id']`.

4. Botón volver a `configuracionSistema.php`.

## Estado

`Por codificar`

## Relacionado

- [[Permisos Areas y Funciones]]

- [[Configuracion Personalizada]] (Para la UI y carpetas físicas del cliente).