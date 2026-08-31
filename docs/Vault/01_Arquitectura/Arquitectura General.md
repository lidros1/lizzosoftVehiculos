# Arquitectura General

> Índice: [[00_Index/INDEX]]

## Tecnologías

- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP nativo
- **Base de datos:** MySQL

## Estructura de directorios

- `/Conexion` — BD y PHPMailer
- `/Login` — autenticación y sesión
- `/HTML`, `/CSS` — vistas y estilos
- `/CRUD_*` — entidades maestras
- `/GestionStock` — inventario
- `/registrosServicios` — órdenes y reclamos
- `/clientes_config/{empresa}/` — configuración por tenant

## Flujo de datos

Usuario → HTML/CSS/JS → PHP → MySQL → respuesta o redirección

## Módulos principales

Login, Clientes, Vehículos, Servicios (catálogo), Personal, Stock, Reportes

## Objetivo

Sistema multitenant para gestión de vehículos y servicios de taller.
