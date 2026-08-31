-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 13-08-2026 a las 19:29:59
-- Versión del servidor: 10.11.14-MariaDB-0ubuntu0.24.04.1
-- Versión de PHP: 8.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `c110lizzosoftVehiculos`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alertas_servicios`
--

CREATE TABLE `alertas_servicios` (
  `IDAlerta` int(11) NOT NULL,
  `nombreAlerta` varchar(100) NOT NULL,
  `IDServicio` int(11) NOT NULL,
  `diasRecordatorio` int(11) NOT NULL COMMENT 'Días que deben pasar tras el servicio para enviar la alerta',
  `asuntoMensaje` varchar(150) NOT NULL,
  `plantillaMensaje` text NOT NULL,
  `estado` varchar(50) DEFAULT 'Activo',
  `sucursal_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `fechaCreacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `areassistema`
--

CREATE TABLE `areassistema` (
  `IDAreaSistema` int(11) NOT NULL,
  `nombreAreaSistema` varchar(30) NOT NULL,
  `descripcionAreaSistema` varchar(2000) NOT NULL,
  `estado` varchar(50) DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `areassistema`
--

INSERT INTO `areassistema` (`IDAreaSistema`, `nombreAreaSistema`, `descripcionAreaSistema`, `estado`) VALUES
(1, 'Órdenes de Trabajo', '', 'Activo'),
(2, 'Reportes y Estadísticas', '', 'Activo'),
(4, 'Configuración - Personal', '', 'Activo'),
(5, 'Configuración - Clientes', '', 'Activo'),
(6, 'Configuración - Servicios', '', 'Activo'),
(7, 'Gestión de Reclamos', '', 'Activo'),
(8, 'Configuración - Vehículos', '', 'Activo'),
(9, 'Gestión de Alertas', '', 'Activo'),
(10, 'Gestión de Presupuestos', 'Módulo para crear y administrar presupuestos', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `IDCliente` int(11) NOT NULL,
  `numeroDocumentoCliente` varchar(50) DEFAULT NULL,
  `nombre` varchar(30) NOT NULL,
  `apellido` varchar(30) NOT NULL,
  `telefono` bigint(15) NOT NULL,
  `IDTipoDocumento` int(11) NOT NULL,
  `IDTipoNumeroTelefono` int(11) NOT NULL,
  `estado` varchar(50) DEFAULT 'Activo',
  `sucursal_id` int(11) DEFAULT 1,
  `empresa_id` int(11) DEFAULT 1,
  `email` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_horarios`
--

CREATE TABLE `configuracion_horarios` (
  `IDConfig` int(11) NOT NULL,
  `tipo_jornada` enum('continua','partida') DEFAULT 'continua',
  `hora_inicio_manana` time NOT NULL DEFAULT '08:00:00',
  `hora_fin_manana` time NOT NULL DEFAULT '13:00:00',
  `hora_inicio_tarde` time DEFAULT '14:00:00',
  `hora_fin_tarde` time DEFAULT '18:00:00',
  `dias_laborables` varchar(50) DEFAULT '1,2,3,4,5' COMMENT '1=Lunes, 7=Domingo',
  `empresa_id` int(11) NOT NULL,
  `sucursal_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `configuracion_horarios`
--

INSERT INTO `configuracion_horarios` (`IDConfig`, `tipo_jornada`, `hora_inicio_manana`, `hora_fin_manana`, `hora_inicio_tarde`, `hora_fin_tarde`, `dias_laborables`, `empresa_id`, `sucursal_id`) VALUES
(1, 'continua', '08:00:00', '17:00:00', NULL, NULL, '1,2,3,4,5', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalleregistro`
--

CREATE TABLE `detalleregistro` (
  `IDDetalleregistro` int(11) NOT NULL,
  `IDRegistroServicio` int(11) NOT NULL,
  `IDServicio` int(11) NOT NULL,
  `observacionRegistroServicio` varchar(4000) NOT NULL,
  `costoServicio` float NOT NULL,
  `sucursal_id` int(11) DEFAULT 1,
  `empresa_id` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_presupuestos`
--

CREATE TABLE `detalle_presupuestos` (
  `IDDetalle` int(11) NOT NULL,
  `IDPresupuesto` int(11) NOT NULL,
  `IDServicio` int(11) DEFAULT NULL,
  `descripcion_libre` varchar(255) DEFAULT NULL,
  `precio_unitario` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_reclamos`
--

CREATE TABLE `detalle_reclamos` (
  `IDDetalleReclamo` int(11) NOT NULL,
  `IDReclamo` int(11) NOT NULL,
  `IDServicio` int(11) NOT NULL,
  `observacionDetalleReclamo` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresas`
--

CREATE TABLE `empresas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `logoEmpresa` varchar(255) DEFAULT NULL,
  `razon_social` varchar(150) DEFAULT NULL,
  `cuit` varchar(15) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `estado` varchar(50) DEFAULT 'Activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `empresas`
--

INSERT INTO `empresas` (`id`, `nombre`, `logoEmpresa`, `razon_social`, `cuit`, `direccion`, `telefono`, `email`, `estado`, `fecha_creacion`) VALUES
(1, 'Garbuio Motor-Service', NULL, 'Garbuio Motor-Service', '00-00000000-0', 'Chacabuco, M5501FUA Godoy Cruz, Mendoza', '2616387571', NULL, 'Activo', '2026-07-23 21:32:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `envios_alertas_log`
--

CREATE TABLE `envios_alertas_log` (
  `IDEnvio` int(11) NOT NULL,
  `IDAlerta` int(11) NOT NULL,
  `IDVehiculo` int(11) NOT NULL,
  `fechaEnvio` datetime NOT NULL,
  `estadoEnvio` varchar(50) DEFAULT 'Exitoso',
  `detalle_error` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estadossolicitud`
--

CREATE TABLE `estadossolicitud` (
  `IDEstadoSolicitud` int(11) NOT NULL,
  `nombreEstadoSolicitud` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estadossolicitud`
--

INSERT INTO `estadossolicitud` (`IDEstadoSolicitud`, `nombreEstadoSolicitud`) VALUES
(1, 'Pendiente'),
(2, 'En Proceso'),
(3, 'Finalizado'),
(4, 'Finalizado-NE'),
(5, 'Finalizado-ENT'),
(6, 'Prioritario'),
(7, 'No Prioritario'),
(8, 'Cancelada');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `funciones`
--

CREATE TABLE `funciones` (
  `IDFuncion` int(11) NOT NULL,
  `nombreFuncion` varchar(30) NOT NULL,
  `descripcionFuncion` varchar(2000) NOT NULL,
  `estado` varchar(50) DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `funciones`
--

INSERT INTO `funciones` (`IDFuncion`, `nombreFuncion`, `descripcionFuncion`, `estado`) VALUES
(1, 'Crear', '', 'Activo'),
(2, 'Editar', '', 'Activo'),
(3, 'Ver', '', 'Activo'),
(4, 'Reporte Ingresos por Servicios', '', 'Activo'),
(5, 'Reporte Accesos y Seguridad', '', 'Activo'),
(6, 'Reporte Productividad del Pers', '', 'Activo'),
(10, 'Ver Todas las Órdenes', '', 'Activo'),
(11, 'Anular/Cancelar', 'Permite cancelar registros', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs_accesos`
--

CREATE TABLE `logs_accesos` (
  `id` int(11) NOT NULL,
  `IDUsuario` int(11) DEFAULT NULL,
  `nombreUsuario` varchar(30) DEFAULT NULL,
  `accion` varchar(100) DEFAULT NULL,
  `fecha_hora` datetime DEFAULT current_timestamp(),
  `sucursal_id` int(11) DEFAULT 1,
  `empresa_id` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `logs_accesos`
--

INSERT INTO `logs_accesos` (`id`, `IDUsuario`, `nombreUsuario`, `accion`, `fecha_hora`, `sucursal_id`, `empresa_id`) VALUES
(1, 1, 'hugoadmin', 'Modificacion de datos de personal: Garbuio, Hugo', '2026-07-23 19:36:01', 1, 1),
(2, 1, 'hugoadmin', 'Modificacion de datos de personal: Garbuio, Hugo', '2026-07-23 19:36:25', 1, 1),
(3, 1, 'hugoadmin', 'Modificacion de datos de personal: Garbuio, Hugo', '2026-07-24 22:00:43', 1, 1),
(4, 1, 'hugoadmin', 'Cierre de sesión manual', '2026-07-24 22:01:15', 1, 1),
(5, 1, 'hugoadmin', 'Cierre de sesión manual', '2026-07-29 12:48:52', 1, 1),
(6, 1, 'hugoadmin', 'Cierre de sesión manual', '2026-08-13 15:36:02', 1, 1),
(7, 1, 'hugoadmin', 'Cierre de sesión manual', '2026-08-13 16:23:28', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `log_ingresos_servicios`
--

CREATE TABLE `log_ingresos_servicios` (
  `id` int(11) NOT NULL,
  `IDDetalleregistro` int(11) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `accion` varchar(20) DEFAULT NULL,
  `usuario` varchar(50) DEFAULT NULL,
  `sucursal_id` int(11) DEFAULT 1,
  `empresa_id` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisosusuarios`
--

CREATE TABLE `permisosusuarios` (
  `IDPermisoUsuario` int(11) NOT NULL,
  `IDAreaSistema` int(11) NOT NULL,
  `IDFuncion` int(11) NOT NULL,
  `IDUsuario` int(11) NOT NULL,
  `estado` varchar(50) DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos_reportes`
--

CREATE TABLE `permisos_reportes` (
  `IDPermisoReporte` int(11) NOT NULL,
  `IDUsuario` int(11) NOT NULL,
  `IDAreaSistema` int(11) NOT NULL,
  `IDReporte` varchar(20) NOT NULL,
  `estado` varchar(50) DEFAULT 'Activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personal`
--

CREATE TABLE `personal` (
  `IDPersonal` int(11) NOT NULL,
  `numeroDocumentoPersonal` int(15) NOT NULL,
  `nombre` varchar(30) NOT NULL,
  `apellido` varchar(30) NOT NULL,
  `telefono` bigint(15) NOT NULL,
  `IDUsuario` int(11) DEFAULT NULL,
  `IDTipoDocumento` int(11) NOT NULL,
  `IDTipoNumeroTelefono` int(11) NOT NULL,
  `estado` varchar(50) DEFAULT 'Activo',
  `sucursal_id` int(11) DEFAULT 1,
  `empresa_id` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `personal`
--

INSERT INTO `personal` (`IDPersonal`, `numeroDocumentoPersonal`, `nombre`, `apellido`, `telefono`, `IDUsuario`, `IDTipoDocumento`, `IDTipoNumeroTelefono`, `estado`, `sucursal_id`, `empresa_id`) VALUES
(1, 14925602, 'Hugo', 'Garbuio', 2616387571, 1, 1, 1, 'Activo', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `presupuestos`
--

CREATE TABLE `presupuestos` (
  `IDPresupuesto` int(11) NOT NULL,
  `IDSucursal` int(11) NOT NULL,
  `fecha_creacion` datetime NOT NULL,
  `validez_dias` int(11) NOT NULL DEFAULT 15,
  `casual_nombre` varchar(100) DEFAULT NULL,
  `casual_apellido` varchar(100) DEFAULT NULL,
  `casual_telefono` varchar(50) DEFAULT NULL,
  `casual_patente` varchar(20) DEFAULT NULL,
  `casual_tipo_vehiculo` varchar(50) DEFAULT NULL,
  `casual_marca` varchar(50) DEFAULT NULL,
  `casual_modelo` varchar(50) DEFAULT NULL,
  `IDCliente` int(11) DEFAULT NULL,
  `IDVehiculo` int(11) DEFAULT NULL,
  `estado` enum('Pendiente','Aprobado','Rechazado','Vencido') NOT NULL DEFAULT 'Pendiente',
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notas_adicionales` text DEFAULT NULL,
  `IDOrdenTrabajo` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reclamos`
--

CREATE TABLE `reclamos` (
  `IDReclamo` int(11) NOT NULL,
  `IDRegistroServicioOriginal` int(11) NOT NULL,
  `fechaReclamo` datetime NOT NULL DEFAULT current_timestamp(),
  `observacionReclamo` varchar(4000) NOT NULL,
  `estadoReclamo` varchar(50) DEFAULT 'Pendiente',
  `sucursal_id` int(11) DEFAULT 1,
  `empresa_id` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `registrosservicios`
--

CREATE TABLE `registrosservicios` (
  `IDRegistroServicio` int(11) NOT NULL,
  `fechaRegistroServicio` date NOT NULL,
  `fechaInicio` datetime DEFAULT NULL,
  `fechaFin` datetime DEFAULT NULL,
  `numeroDocumentoPersonal` int(15) DEFAULT NULL,
  `IDVehiculo` int(11) NOT NULL,
  `kilometrajeIngreso` int(11) DEFAULT 0,
  `observacionGeneral` varchar(4000) DEFAULT NULL,
  `nivelCombustible` varchar(50) DEFAULT '1/2 Tanque',
  `prioridad` int(11) DEFAULT NULL,
  `IDEstado` int(11) NOT NULL DEFAULT 1,
  `sucursal_id` int(11) DEFAULT 1,
  `empresa_id` int(11) DEFAULT 1,
  `numeroOrdenTrabajo` int(11) NOT NULL,
  `motivoAnulacion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reportes_sistema`
--

CREATE TABLE `reportes_sistema` (
  `IDReporte` varchar(20) NOT NULL,
  `nombreReporte` varchar(100) NOT NULL,
  `descripcionReporte` varchar(2000) NOT NULL,
  `estado` varchar(50) DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `IDRol` int(11) NOT NULL,
  `nombreRol` varchar(50) NOT NULL,
  `descripcionRol` text DEFAULT NULL,
  `estado` varchar(50) DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`IDRol`, `nombreRol`, `descripcionRol`, `estado`) VALUES
(1, 'Administrador', 'Control total del sistema y reportes', 'Activo'),
(2, 'Mecánico', 'Acceso a órdenes de trabajo, vehículos y checklist', 'Activo'),
(3, 'Recepción / Caja', 'Gestión de clientes, ingresos de vehículos y cobros', 'Activo'),
(4, 'Especial', 'Acceso especial con permisos personalizados y selección de sucursales', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rubros`
--

CREATE TABLE `rubros` (
  `IDRubro` int(11) NOT NULL,
  `nombreRubro` varchar(100) NOT NULL,
  `descripcionRubro` text DEFAULT NULL,
  `estado` varchar(20) DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rubros`
--

INSERT INTO `rubros` (`IDRubro`, `nombreRubro`, `descripcionRubro`, `estado`) VALUES
(1, 'Motos', 'Taller especializado en motocicletas', 'Activo'),
(2, 'Autos y Camionetas', 'Taller mecánico para vehículos livianos', 'Activo'),
(3, 'Camiones', 'Taller especializado en vehículos pesados y maquinaria', 'Activo'),
(4, 'Trafics', 'Taller especializado en trafics, furgones y utilitarios medianos', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicios`
--

CREATE TABLE `servicios` (
  `IDServicio` int(11) NOT NULL,
  `nombreServicio` varchar(50) NOT NULL,
  `descripcionServicio` varchar(4000) NOT NULL,
  `costoServicio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estado` varchar(50) DEFAULT 'Activo',
  `empresa_id` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sucursales`
--

CREATE TABLE `sucursales` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `IDRubro` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `codigo` varchar(20) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `estado` varchar(50) DEFAULT 'Activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `sucursales`
--

INSERT INTO `sucursales` (`id`, `empresa_id`, `IDRubro`, `nombre`, `codigo`, `direccion`, `telefono`, `email`, `estado`, `fecha_creacion`) VALUES
(1, 1, 2, 'Chacabuco Godoy Cruz', NULL, NULL, NULL, NULL, 'Activo', '2026-07-23 21:32:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tiposdocumentos`
--

CREATE TABLE `tiposdocumentos` (
  `IDTipoDocumento` int(11) NOT NULL,
  `tipoDocumento` varchar(11) NOT NULL,
  `estado` varchar(50) DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tiposdocumentos`
--

INSERT INTO `tiposdocumentos` (`IDTipoDocumento`, `tipoDocumento`, `estado`) VALUES
(1, 'DNI', 'Activo'),
(2, 'Pasaporte', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tiposnumerotelefono`
--

CREATE TABLE `tiposnumerotelefono` (
  `IDTipoNumeroTelefono` int(11) NOT NULL,
  `tipoNumeroTelefono` varchar(20) NOT NULL,
  `estado` varchar(50) DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tiposnumerotelefono`
--

INSERT INTO `tiposnumerotelefono` (`IDTipoNumeroTelefono`, `tipoNumeroTelefono`, `estado`) VALUES
(1, 'Celular', 'Activo'),
(2, 'Fijo', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `IDUsuario` int(11) NOT NULL,
  `nombreUsuario` varchar(30) NOT NULL,
  `contraseñaUsuario` varchar(35) NOT NULL,
  `email` varchar(150) NOT NULL,
  `fechaCreacion` date NOT NULL DEFAULT current_timestamp(),
  `fechaUltimoAcceso` datetime NOT NULL,
  `estado` varchar(50) DEFAULT 'Activo',
  `sucursal_id` int(11) DEFAULT 1,
  `empresa_id` int(11) DEFAULT 1,
  `IDRol` int(11) DEFAULT NULL,
  `codigo_recuperacion` varchar(6) DEFAULT NULL,
  `expiracion_codigo` datetime DEFAULT NULL,
  `tema_preferido` enum('claro','oscuro') NOT NULL DEFAULT 'claro'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`IDUsuario`, `nombreUsuario`, `contraseñaUsuario`, `email`, `fechaCreacion`, `fechaUltimoAcceso`, `estado`, `sucursal_id`, `empresa_id`, `IDRol`, `codigo_recuperacion`, `expiracion_codigo`, `tema_preferido`) VALUES
(1, 'hugoadmin', 'e10adc3949ba59abbe56e057f20f883e', 'hugosbikers@gmail.com', '2026-07-23', '2026-08-13 16:23:25', 'Activo', 1, 1, 1, NULL, NULL, 'claro');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vehiculos`
--

CREATE TABLE `vehiculos` (
  `IDVehiculo` int(11) NOT NULL,
  `patente` varchar(20) NOT NULL,
  `numeroMotor` varchar(50) DEFAULT NULL,
  `numeroChasis` varchar(50) DEFAULT NULL,
  `anioFabricacion` int(11) DEFAULT NULL,
  `colorVehiculo` varchar(30) NOT NULL,
  `tipoCombustible` varchar(50) DEFAULT NULL,
  `observacionVehiculo` varchar(2000) DEFAULT NULL,
  `fotoVehiculo` varchar(255) DEFAULT NULL,
  `IDCliente` int(11) NOT NULL,
  `tipoVehiculo` varchar(50) NOT NULL,
  `marca` varchar(50) NOT NULL,
  `modelo` varchar(50) NOT NULL,
  `estado` varchar(50) DEFAULT 'Activo',
  `sucursal_id` int(11) DEFAULT 1,
  `empresa_id` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alertas_servicios`
--
ALTER TABLE `alertas_servicios`
  ADD PRIMARY KEY (`IDAlerta`),
  ADD KEY `fk_alerta_servicio` (`IDServicio`);

--
-- Indices de la tabla `areassistema`
--
ALTER TABLE `areassistema`
  ADD PRIMARY KEY (`IDAreaSistema`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`IDCliente`),
  ADD UNIQUE KEY `unq_doc_cliente_sucursal` (`numeroDocumentoCliente`,`empresa_id`,`sucursal_id`),
  ADD KEY `IDTipoDocumento` (`IDTipoDocumento`),
  ADD KEY `IDTipoNumeroTelefono` (`IDTipoNumeroTelefono`),
  ADD KEY `idx_clientes_documento` (`numeroDocumentoCliente`),
  ADD KEY `idx_clientes_sucursal` (`sucursal_id`),
  ADD KEY `fk_clientes_empresa` (`empresa_id`);

--
-- Indices de la tabla `configuracion_horarios`
--
ALTER TABLE `configuracion_horarios`
  ADD PRIMARY KEY (`IDConfig`);

--
-- Indices de la tabla `detalleregistro`
--
ALTER TABLE `detalleregistro`
  ADD PRIMARY KEY (`IDDetalleregistro`),
  ADD KEY `idx_detalleregistro_registro` (`IDRegistroServicio`),
  ADD KEY `fk_detregistro_sucursal` (`sucursal_id`),
  ADD KEY `fk_detregistro_empresa` (`empresa_id`),
  ADD KEY `fk_dr_servicio_real` (`IDServicio`);

--
-- Indices de la tabla `detalle_presupuestos`
--
ALTER TABLE `detalle_presupuestos`
  ADD PRIMARY KEY (`IDDetalle`),
  ADD KEY `fk_detalle_presupuesto` (`IDPresupuesto`),
  ADD KEY `fk_detalle_servicio` (`IDServicio`);

--
-- Indices de la tabla `detalle_reclamos`
--
ALTER TABLE `detalle_reclamos`
  ADD PRIMARY KEY (`IDDetalleReclamo`),
  ADD KEY `fk_detallereclamo_reclamo` (`IDReclamo`),
  ADD KEY `fk_detallereclamo_servicio` (`IDServicio`);

--
-- Indices de la tabla `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `envios_alertas_log`
--
ALTER TABLE `envios_alertas_log`
  ADD PRIMARY KEY (`IDEnvio`),
  ADD KEY `IDAlerta` (`IDAlerta`),
  ADD KEY `IDVehiculo` (`IDVehiculo`);

--
-- Indices de la tabla `estadossolicitud`
--
ALTER TABLE `estadossolicitud`
  ADD PRIMARY KEY (`IDEstadoSolicitud`);

--
-- Indices de la tabla `funciones`
--
ALTER TABLE `funciones`
  ADD PRIMARY KEY (`IDFuncion`);

--
-- Indices de la tabla `logs_accesos`
--
ALTER TABLE `logs_accesos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_logsacc_sucursal` (`sucursal_id`),
  ADD KEY `fk_logsacc_empresa` (`empresa_id`);

--
-- Indices de la tabla `log_ingresos_servicios`
--
ALTER TABLE `log_ingresos_servicios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_logsingr_sucursal` (`sucursal_id`),
  ADD KEY `fk_logsingr_empresa` (`empresa_id`);

--
-- Indices de la tabla `permisosusuarios`
--
ALTER TABLE `permisosusuarios`
  ADD PRIMARY KEY (`IDPermisoUsuario`),
  ADD KEY `IDFuncion` (`IDFuncion`),
  ADD KEY `idx_permisos_usuario` (`IDUsuario`),
  ADD KEY `idx_permisos_area` (`IDAreaSistema`);

--
-- Indices de la tabla `permisos_reportes`
--
ALTER TABLE `permisos_reportes`
  ADD PRIMARY KEY (`IDPermisoReporte`),
  ADD UNIQUE KEY `unique_permiso_reporte` (`IDUsuario`,`IDAreaSistema`,`IDReporte`),
  ADD KEY `fk_permisos_reportes_usuario` (`IDUsuario`),
  ADD KEY `fk_permisos_reportes_area` (`IDAreaSistema`),
  ADD KEY `fk_permisos_reportes_reporte` (`IDReporte`);

--
-- Indices de la tabla `personal`
--
ALTER TABLE `personal`
  ADD PRIMARY KEY (`IDPersonal`),
  ADD UNIQUE KEY `unq_doc_empresa_personal` (`numeroDocumentoPersonal`,`empresa_id`),
  ADD KEY `IDUsuario` (`IDUsuario`),
  ADD KEY `IDTipoDocumento` (`IDTipoDocumento`),
  ADD KEY `IDTipoNumeroTelefono` (`IDTipoNumeroTelefono`),
  ADD KEY `fk_personal_sucursal` (`sucursal_id`),
  ADD KEY `idx_personal_documento` (`numeroDocumentoPersonal`),
  ADD KEY `fk_personal_empresa` (`empresa_id`);

--
-- Indices de la tabla `presupuestos`
--
ALTER TABLE `presupuestos`
  ADD PRIMARY KEY (`IDPresupuesto`),
  ADD KEY `fk_presupuestos_sucursal` (`IDSucursal`),
  ADD KEY `fk_presupuestos_cliente` (`IDCliente`),
  ADD KEY `fk_presupuestos_vehiculo` (`IDVehiculo`);

--
-- Indices de la tabla `reclamos`
--
ALTER TABLE `reclamos`
  ADD PRIMARY KEY (`IDReclamo`),
  ADD KEY `fk_reclamos_registro` (`IDRegistroServicioOriginal`);

--
-- Indices de la tabla `registrosservicios`
--
ALTER TABLE `registrosservicios`
  ADD PRIMARY KEY (`IDRegistroServicio`),
  ADD KEY `IDVehiculo` (`IDVehiculo`),
  ADD KEY `idx_registros_personal` (`numeroDocumentoPersonal`),
  ADD KEY `idx_registros_fecha` (`fechaRegistroServicio`),
  ADD KEY `idx_registros_sucursal` (`sucursal_id`),
  ADD KEY `fk_registros_empresa` (`empresa_id`);

--
-- Indices de la tabla `reportes_sistema`
--
ALTER TABLE `reportes_sistema`
  ADD PRIMARY KEY (`IDReporte`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`IDRol`);

--
-- Indices de la tabla `rubros`
--
ALTER TABLE `rubros`
  ADD PRIMARY KEY (`IDRubro`);

--
-- Indices de la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD PRIMARY KEY (`IDServicio`),
  ADD KEY `fk_servicios_empresa` (`empresa_id`);

--
-- Indices de la tabla `sucursales`
--
ALTER TABLE `sucursales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sucursal_empresa` (`empresa_id`),
  ADD KEY `fk_sucursal_rubro` (`IDRubro`);

--
-- Indices de la tabla `tiposdocumentos`
--
ALTER TABLE `tiposdocumentos`
  ADD PRIMARY KEY (`IDTipoDocumento`);

--
-- Indices de la tabla `tiposnumerotelefono`
--
ALTER TABLE `tiposnumerotelefono`
  ADD PRIMARY KEY (`IDTipoNumeroTelefono`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`IDUsuario`),
  ADD KEY `fk_usuarios_sucursal` (`sucursal_id`),
  ADD KEY `fk_usuarios_empresa` (`empresa_id`),
  ADD KEY `fk_usuarios_rol` (`IDRol`);

--
-- Indices de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD PRIMARY KEY (`IDVehiculo`),
  ADD UNIQUE KEY `unq_patente_sucursal` (`patente`,`empresa_id`,`sucursal_id`),
  ADD KEY `idx_vehiculos_cliente` (`IDCliente`),
  ADD KEY `idx_vehiculos_sucursal` (`sucursal_id`),
  ADD KEY `fk_vehiculos_empresa` (`empresa_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alertas_servicios`
--
ALTER TABLE `alertas_servicios`
  MODIFY `IDAlerta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `areassistema`
--
ALTER TABLE `areassistema`
  MODIFY `IDAreaSistema` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `IDCliente` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `configuracion_horarios`
--
ALTER TABLE `configuracion_horarios`
  MODIFY `IDConfig` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `detalleregistro`
--
ALTER TABLE `detalleregistro`
  MODIFY `IDDetalleregistro` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_presupuestos`
--
ALTER TABLE `detalle_presupuestos`
  MODIFY `IDDetalle` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_reclamos`
--
ALTER TABLE `detalle_reclamos`
  MODIFY `IDDetalleReclamo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `envios_alertas_log`
--
ALTER TABLE `envios_alertas_log`
  MODIFY `IDEnvio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `estadossolicitud`
--
ALTER TABLE `estadossolicitud`
  MODIFY `IDEstadoSolicitud` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `funciones`
--
ALTER TABLE `funciones`
  MODIFY `IDFuncion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `logs_accesos`
--
ALTER TABLE `logs_accesos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `log_ingresos_servicios`
--
ALTER TABLE `log_ingresos_servicios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `permisosusuarios`
--
ALTER TABLE `permisosusuarios`
  MODIFY `IDPermisoUsuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT de la tabla `permisos_reportes`
--
ALTER TABLE `permisos_reportes`
  MODIFY `IDPermisoReporte` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `personal`
--
ALTER TABLE `personal`
  MODIFY `IDPersonal` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `presupuestos`
--
ALTER TABLE `presupuestos`
  MODIFY `IDPresupuesto` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reclamos`
--
ALTER TABLE `reclamos`
  MODIFY `IDReclamo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `registrosservicios`
--
ALTER TABLE `registrosservicios`
  MODIFY `IDRegistroServicio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `IDRol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `rubros`
--
ALTER TABLE `rubros`
  MODIFY `IDRubro` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `servicios`
--
ALTER TABLE `servicios`
  MODIFY `IDServicio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sucursales`
--
ALTER TABLE `sucursales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `tiposdocumentos`
--
ALTER TABLE `tiposdocumentos`
  MODIFY `IDTipoDocumento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `tiposnumerotelefono`
--
ALTER TABLE `tiposnumerotelefono`
  MODIFY `IDTipoNumeroTelefono` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `IDUsuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  MODIFY `IDVehiculo` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `alertas_servicios`
--
ALTER TABLE `alertas_servicios`
  ADD CONSTRAINT `fk_alerta_servicio_rel` FOREIGN KEY (`IDServicio`) REFERENCES `servicios` (`IDServicio`) ON DELETE CASCADE;

--
-- Filtros para la tabla `detalleregistro`
--
ALTER TABLE `detalleregistro`
  ADD CONSTRAINT `fk_dr_registro_real` FOREIGN KEY (`IDRegistroServicio`) REFERENCES `registrosservicios` (`IDRegistroServicio`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dr_servicio_real` FOREIGN KEY (`IDServicio`) REFERENCES `servicios` (`IDServicio`) ON DELETE CASCADE;

--
-- Filtros para la tabla `detalle_presupuestos`
--
ALTER TABLE `detalle_presupuestos`
  ADD CONSTRAINT `fk_detalle_presupuesto` FOREIGN KEY (`IDPresupuesto`) REFERENCES `presupuestos` (`IDPresupuesto`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_detalle_servicio` FOREIGN KEY (`IDServicio`) REFERENCES `servicios` (`IDServicio`) ON DELETE SET NULL;

--
-- Filtros para la tabla `detalle_reclamos`
--
ALTER TABLE `detalle_reclamos`
  ADD CONSTRAINT `fk_dr_reclamo_fk` FOREIGN KEY (`IDReclamo`) REFERENCES `reclamos` (`IDReclamo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reclamo_servicio_real` FOREIGN KEY (`IDServicio`) REFERENCES `servicios` (`IDServicio`) ON DELETE CASCADE;

--
-- Filtros para la tabla `envios_alertas_log`
--
ALTER TABLE `envios_alertas_log`
  ADD CONSTRAINT `envios_alertas_log_ibfk_1` FOREIGN KEY (`IDAlerta`) REFERENCES `alertas_servicios` (`IDAlerta`) ON DELETE CASCADE,
  ADD CONSTRAINT `envios_alertas_log_ibfk_2` FOREIGN KEY (`IDVehiculo`) REFERENCES `vehiculos` (`IDVehiculo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `presupuestos`
--
ALTER TABLE `presupuestos`
  ADD CONSTRAINT `fk_presupuestos_cliente` FOREIGN KEY (`IDCliente`) REFERENCES `clientes` (`IDCliente`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_presupuestos_sucursal` FOREIGN KEY (`IDSucursal`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_presupuestos_vehiculo` FOREIGN KEY (`IDVehiculo`) REFERENCES `vehiculos` (`IDVehiculo`) ON DELETE SET NULL;

--
-- Filtros para la tabla `registrosservicios`
--
ALTER TABLE `registrosservicios`
  ADD CONSTRAINT `fk_registro_vehiculo_real` FOREIGN KEY (`IDVehiculo`) REFERENCES `vehiculos` (`IDVehiculo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `sucursales`
--
ALTER TABLE `sucursales`
  ADD CONSTRAINT `fk_sucursales_rubro_real` FOREIGN KEY (`IDRubro`) REFERENCES `rubros` (`IDRubro`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`IDRol`) REFERENCES `roles` (`IDRol`);

--
-- Filtros para la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD CONSTRAINT `fk_vehiculo_cliente_real` FOREIGN KEY (`IDCliente`) REFERENCES `clientes` (`IDCliente`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
