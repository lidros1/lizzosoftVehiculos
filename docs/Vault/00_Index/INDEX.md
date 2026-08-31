# Panel de Control - Lizzosoft Vehículos

## Documentación Técnica

- [[Arquitectura General]]

- [[Estructura Multitenant]]

- [[Entidades Principales]]

## Módulos del Sistema

- [[GestionClientes]]

- [[GestionVehiculos]]

- [[GestionMarcasModelos]]

- [[GestionServicios]]

- [[GestionStock]]

- [[GestionPersonal]]

- [[GestionUsuarios]]

- [[Configuracion del Sistema]]

- [[Configuracion Personalizada]]

- [[Reportes Multitenant]]

## Flujos y Operaciones

- [[Flujo Orden de Servicio]]

- [[Flujo Reclamos y Garantias]]

- [[Carga de Configuracion]]

## Frontend

- [[Interfaz Dinamica]]

## Seguridad (Carpeta 12)

- [[Autenticacion y Sesiones]]

- [[Permisos Areas y Funciones]]

- [[Conexion y Mailer]]

- [[Logs de Acceso]]

## Planificación (Carpeta 09)

- [[Plan y Checklist]]

## Reglas de Desarrollo

- [[AGENTS]]

---

## Convención de estado en cada nota

- `Implementado` — existe en el repo y funciona en producción local.

- `Parcial` — hay código pero faltan validaciones, permisos o refactor.

- `Por codificar` — documentado pero sin implementar o solo maqueta.

## Mapa de carpetas del Vault

| Carpeta | Contenido |

|---------|-----------|

| `00_Index` | Este índice |

| `01_Arquitectura` | Stack, directorios y multitenencia |

| `04_BaseDatos` | Tablas, relaciones, triggers y procedures |

| `06_Modulos` | CRUD, reportes, configuración global |

| `07_Flujos` | Reclamos y órdenes de trabajo |

| `12_Seguridad` | Login, permisos RBAC, logs de auditoría |

| `03_Frontend` | UI, navegación y carga de CSS dinámico |

| `09_Decisiones` | Plan y checklist de implementación |

| `11_IA` | Reglas estrictas para asistentes de código |