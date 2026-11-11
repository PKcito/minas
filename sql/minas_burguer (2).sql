-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 04-11-2026 a las 19:06:51
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `minas_burguer`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(40) NOT NULL,
  `descripcion` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categorías del menú (hamburguesas, bebidas, postres...)';

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Hamburguesas', 'Hamburguesas clásicas y especiales'),
(2, 'Bebidas', 'Refrescos, jugos y malteadas'),
(3, 'Postres', 'Postres y helados'),
(4, 'Combos', 'Combos completos con bebida y postre');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categoria_extras`
--

CREATE TABLE `categoria_extras` (
  `categoria_id` int(11) NOT NULL,
  `extra_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Extras que aplican a toda una categoría';

--
-- Volcado de datos para la tabla `categoria_extras`
--

INSERT INTO `categoria_extras` (`categoria_id`, `extra_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nombres` varchar(40) NOT NULL,
  `apellidos` varchar(40) NOT NULL,
  `telefono` varchar(11) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `notas` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Datos personales de clientes. Solo 1 fila por usuario';

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `usuario_id`, `nombres`, `apellidos`, `telefono`, `fecha_nacimiento`, `notas`) VALUES
(3, 4, 'Jhon', 'Smith', '04121234567', NULL, NULL),
(4, 5, 'Abraham', 'Febres', '04123403630', NULL, NULL),
(5, 6, 'Jhon', 'smith', '04121234588', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_pago`
--

CREATE TABLE `configuracion_pago` (
  `id` int(11) NOT NULL,
  `banco` varchar(50) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `titular` varchar(100) NOT NULL,
  `tasa_dolar` decimal(10,2) NOT NULL DEFAULT 1.00,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `activo_unico` tinyint(4) GENERATED ALWAYS AS (if(`activo` = 1,1,NULL)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Datos de PagoMóvil. Solo 1 configuración activa permitida';

--
-- Volcado de datos para la tabla `configuracion_pago`
--

INSERT INTO `configuracion_pago` (`id`, `banco`, `telefono`, `cedula`, `titular`, `tasa_dolar`, `activo`) VALUES
(1, 'Banesco', '04141234567', 'V-12345678', 'Minas Burguer C.A.', 850.00, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_pago`
--

CREATE TABLE `detalle_pago` (
  `id` int(11) NOT NULL,
  `pago_id` int(11) NOT NULL,
  `metodo_pago_id` int(11) NOT NULL,
  `monto_parcial` decimal(10,2) NOT NULL,
  `referencia_externa` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Movimientos parciales de cada pago (adelanto, saldo, reembolso)';

--
-- Volcado de datos para la tabla `detalle_pago`
--

INSERT INTO `detalle_pago` (`id`, `pago_id`, `metodo_pago_id`, `monto_parcial`, `referencia_externa`) VALUES
(1, 1, 5, 7.00, 'PAGO-000001'),
(2, 2, 5, 3.00, 'PAGO-000002'),
(3, 3, 5, 7.25, 'PAGO-000003'),
(4, 4, 5, 51.75, 'PAGO-000004'),
(5, 5, 1, 4.63, 'PAGO-000005'),
(6, 6, 5, 7.00, 'PAGO-000006');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_pedido`
--

CREATE TABLE `detalle_pedido` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario_historico` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Líneas del pedido. precio_unitario_historico es snapshot del momento de compra';

--
-- Volcado de datos para la tabla `detalle_pedido`
--

INSERT INTO `detalle_pedido` (`id`, `pedido_id`, `producto_id`, `cantidad`, `precio_unitario_historico`) VALUES
(1, 1, 3, 1, 7.00),
(2, 2, 8, 1, 3.00),
(3, 3, 1, 1, 5.50),
(4, 4, 7, 2, 3.50),
(5, 4, 6, 3, 2.00),
(6, 4, 1, 5, 5.50),
(7, 5, 2, 1, 8.00),
(8, 6, 5, 1, 1.50),
(9, 6, 1, 1, 5.50);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_pedido_extras`
--

CREATE TABLE `detalle_pedido_extras` (
  `id` int(11) NOT NULL,
  `detalle_pedido_id` int(11) NOT NULL,
  `extra_id` int(11) DEFAULT NULL,
  `nombre` varchar(50) NOT NULL,
  `precio_extra` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Snapshot del nombre y precio de cada extra al momento de la compra';

--
-- Volcado de datos para la tabla `detalle_pedido_extras`
--

INSERT INTO `detalle_pedido_extras` (`id`, `detalle_pedido_id`, `extra_id`, `nombre`, `precio_extra`) VALUES
(1, 3, 7, 'Aguacate', 1.25),
(2, 3, 5, 'Salsa BBQ', 0.50),
(3, 6, 10, 'Sin lechuga', 0.00),
(4, 6, 9, 'Sin tomate', 0.00),
(5, 6, 7, 'Aguacate', 1.25),
(6, 6, 1, 'Queso extra', 1.00),
(7, 7, 8, 'Sin cebolla', 0.00),
(8, 7, 7, 'Aguacate', 1.25);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `direcciones`
--

CREATE TABLE `direcciones` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `tipo_direccion_id` int(11) NOT NULL,
  `estado` varchar(40) NOT NULL,
  `municipio` varchar(40) NOT NULL,
  `parroquia` varchar(50) NOT NULL,
  `calle` varchar(100) NOT NULL,
  `referencia` varchar(200) DEFAULT NULL,
  `predeterminada` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Direcciones de entrega por cliente. Solo 1 predeterminada por cliente';

--
-- Volcado de datos para la tabla `direcciones`
--

INSERT INTO `direcciones` (`id`, `cliente_id`, `tipo_direccion_id`, `estado`, `municipio`, `parroquia`, `calle`, `referencia`, `predeterminada`) VALUES
(2, 3, 1, 'Carabobo', 'Naguanagua', 'Urbana Naguanagua', 'av valmore rodriguez', 'cerca de las palmeras', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estados_pago`
--

CREATE TABLE `estados_pago` (
  `id` int(11) NOT NULL,
  `nombre` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Estados del pago: pendiente, completado, fallido, reembolsado';

--
-- Volcado de datos para la tabla `estados_pago`
--

INSERT INTO `estados_pago` (`id`, `nombre`) VALUES
(2, 'completado'),
(3, 'fallido'),
(1, 'pendiente'),
(4, 'reembolsado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estados_pedido`
--

CREATE TABLE `estados_pedido` (
  `id` int(11) NOT NULL,
  `nombre` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Estados del ciclo de vida del pedido';

--
-- Volcado de datos para la tabla `estados_pedido`
--

INSERT INTO `estados_pedido` (`id`, `nombre`) VALUES
(6, 'Cancelado'),
(2, 'Confirmado'),
(7, 'En camino'),
(5, 'Entregado'),
(4, 'Listo'),
(1, 'Pendiente'),
(3, 'Preparando');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `extras`
--

CREATE TABLE `extras` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `precio_extra` decimal(10,2) NOT NULL DEFAULT 0.00,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ingredientes/adicionales configurables por categoría o producto';

--
-- Volcado de datos para la tabla `extras`
--

INSERT INTO `extras` (`id`, `nombre`, `precio_extra`, `activo`) VALUES
(1, 'Queso extra', 1.00, 1),
(2, 'Tocino extra', 1.50, 1),
(3, 'Cebolla caramelizada', 0.75, 1),
(4, 'Jalapeños', 0.50, 1),
(5, 'Salsa BBQ', 0.50, 1),
(6, 'Huevo frito', 1.00, 1),
(7, 'Aguacate', 1.25, 1),
(8, 'Sin cebolla', 0.00, 1),
(9, 'Sin tomate', 0.00, 1),
(10, 'Sin lechuga', 0.00, 1),
(11, 'Sin salsa', 0.00, 1),
(12, 'Sin pepinillos', 0.00, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_estados_pedido`
--

CREATE TABLE `historial_estados_pedido` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `estado_anterior_id` int(11) DEFAULT NULL,
  `estado_nuevo_id` int(11) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `notas` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auditoría completa: quién cambió qué estado y cuándo';

--
-- Volcado de datos para la tabla `historial_estados_pedido`
--

INSERT INTO `historial_estados_pedido` (`id`, `pedido_id`, `usuario_id`, `estado_anterior_id`, `estado_nuevo_id`, `fecha`, `notas`) VALUES
(1, 1, 5, NULL, 1, '2026-11-04 14:31:32', 'Pedido creado por el cliente'),
(2, 2, 5, NULL, 1, '2026-11-04 14:47:53', 'Pedido creado por el cliente'),
(3, 3, 5, NULL, 1, '2026-11-04 16:45:06', 'Pedido creado por el cliente'),
(4, 3, 5, 1, 2, '2026-11-04 16:45:53', 'Cambio desde admin'),
(5, 3, 5, 2, 3, '2026-11-04 16:45:55', 'Cambio desde admin'),
(6, 3, 5, 3, 4, '2026-11-04 16:45:56', 'Cambio desde admin'),
(7, 3, 5, 4, 5, '2026-11-04 16:45:59', 'Cambio desde admin'),
(8, 4, 5, NULL, 1, '2026-11-04 16:51:58', 'Pedido creado por el cliente'),
(9, 4, 5, 1, 2, '2026-11-04 16:52:41', 'Cambio desde admin'),
(10, 4, 5, 2, 3, '2026-11-04 16:52:43', 'Cambio desde admin'),
(11, 4, 5, 3, 4, '2026-11-04 16:52:44', 'Cambio desde admin'),
(12, 4, 5, 4, 5, '2026-11-04 16:52:46', 'Cambio desde admin'),
(13, 5, 6, NULL, 1, '2026-11-04 17:21:08', 'Pedido creado por el cliente'),
(14, 6, 6, NULL, 1, '2026-11-04 17:48:23', 'Pedido creado por el cliente'),
(15, 6, 5, 1, 2, '2026-11-04 17:49:15', 'Cambio desde admin');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `metodos_pago`
--

CREATE TABLE `metodos_pago` (
  `id` int(11) NOT NULL,
  `nombre` varchar(30) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Métodos de pago configurados en el sistema';

--
-- Volcado de datos para la tabla `metodos_pago`
--

INSERT INTO `metodos_pago` (`id`, `nombre`, `activo`) VALUES
(1, 'Efectivo', 1),
(2, 'Punto de venta', 0),
(3, 'Transferencia', 0),
(4, 'Zelle', 0),
(5, 'PagoMóvil', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `pedido_id` int(11) DEFAULT NULL,
  `titulo` varchar(100) NOT NULL,
  `mensaje` varchar(500) NOT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notificaciones al cliente (cambios de estado, pagos, etc.)';

--
-- Volcado de datos para la tabla `notificaciones`
--

INSERT INTO `notificaciones` (`id`, `usuario_id`, `pedido_id`, `titulo`, `mensaje`, `leida`, `fecha_creacion`) VALUES
(1, 5, 3, 'Pago confirmado', 'Hemos confirmado tu pago para el pedido #3. Gracias!', 1, '2026-11-04 16:45:51'),
(2, 5, 3, 'Pedido confirmado', 'Tu pedido #3 fue confirmado.', 1, '2026-11-04 16:45:53'),
(3, 5, 3, 'Pedido en preparacion', 'Tu pedido #3 esta siendo preparado.', 1, '2026-11-04 16:45:55'),
(4, 5, 3, 'Tu pedido esta listo!', 'Tu pedido #3 esta listo para recoger.', 1, '2026-11-04 16:45:56'),
(5, 5, 3, 'Pedido entregado!', 'Tu pedido #3 fue entregado.', 1, '2026-11-04 16:45:59'),
(6, 5, 4, 'Pago confirmado', 'Hemos confirmado tu pago para el pedido #4. Gracias!', 1, '2026-11-04 16:52:38'),
(7, 5, 4, 'Pedido confirmado', 'Tu pedido #4 fue confirmado.', 1, '2026-11-04 16:52:41'),
(8, 5, 4, 'Pedido en preparacion', 'Tu pedido #4 esta siendo preparado.', 1, '2026-11-04 16:52:43'),
(9, 5, 4, 'Tu pedido esta listo!', 'Tu pedido #4 esta listo para recoger.', 1, '2026-11-04 16:52:44'),
(10, 5, 4, 'Pedido entregado!', 'Tu pedido #4 fue entregado.', 1, '2026-11-04 16:52:46'),
(11, 6, 5, 'Pago confirmado', 'Hemos confirmado tu pago para el pedido #5. Gracias!', 1, '2026-11-04 17:37:31'),
(12, 6, 6, 'Pago confirmado', 'Hemos confirmado tu pago para el pedido #6. Gracias!', 0, '2026-11-04 17:49:06'),
(13, 6, 6, 'Pedido confirmado', 'Tu pedido #6 fue confirmado.', 0, '2026-11-04 17:49:15');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `fecha_pago` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado_pago_id` int(11) NOT NULL,
  `monto_total` decimal(10,2) NOT NULL,
  `referencia` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de pagos. monto_total es la fuente de verdad del pago';

--
-- Volcado de datos para la tabla `pagos`
--

INSERT INTO `pagos` (`id`, `pedido_id`, `fecha_pago`, `estado_pago_id`, `monto_total`, `referencia`) VALUES
(1, 1, '2026-11-04 14:31:32', 1, 7.00, 'PAGO-000001'),
(2, 2, '2026-11-04 14:47:53', 1, 3.00, 'PAGO-000002'),
(3, 3, '2026-11-04 16:45:06', 2, 7.25, 'PAGO-000003'),
(4, 4, '2026-11-04 16:51:58', 2, 51.75, 'PAGO-000004'),
(5, 5, '2026-11-04 17:21:08', 2, 4.63, 'PAGO-000005'),
(6, 6, '2026-11-04 17:48:23', 2, 7.00, 'PAGO-000006');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_pedido` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_pedido` decimal(10,2) NOT NULL,
  `estado_id` int(11) NOT NULL DEFAULT 1,
  `tipo_entrega` enum('delivery','recoger') NOT NULL,
  `direccion_entrega_id` int(11) DEFAULT NULL,
  `direccion_texto` varchar(500) DEFAULT NULL,
  `metodo_pago_id` int(11) NOT NULL,
  `monto_pagado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `comprobante` varchar(255) DEFAULT NULL,
  `estado_pago_id` int(11) NOT NULL DEFAULT 1,
  `razon_cancelacion` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cabecera del pedido. total_pedido incluye productos + extras';

--
-- Volcado de datos para la tabla `pedidos`
--

INSERT INTO `pedidos` (`id`, `usuario_id`, `fecha_pedido`, `total_pedido`, `estado_id`, `tipo_entrega`, `direccion_entrega_id`, `direccion_texto`, `metodo_pago_id`, `monto_pagado`, `comprobante`, `estado_pago_id`, `razon_cancelacion`) VALUES
(1, 5, '2026-11-04 14:31:32', 7.00, 6, 'recoger', NULL, '', 5, 7.00, 'comprobante_374df96c165a95b4_1793802692.jpg', 1, 'Cancelado automáticamente por falta de confirmación (tiempo límite excedido)'),
(2, 5, '2026-11-04 14:47:53', 3.00, 6, 'recoger', NULL, '', 5, 3.00, 'comprobante_4ceb2bacf50d49e3_1793803673.jpg', 1, 'Cancelado automáticamente por falta de confirmación (tiempo límite excedido)'),
(3, 5, '2026-11-04 16:45:06', 7.25, 5, 'recoger', NULL, '', 5, 7.25, 'comprobante_989123b858ff22fc_1793810706.jpg', 2, NULL),
(4, 5, '2026-11-04 16:51:58', 51.75, 5, 'recoger', NULL, '', 5, 51.75, 'comprobante_8eab346179e16d20_1793811118.jpg', 2, NULL),
(5, 6, '2026-11-04 17:21:08', 9.25, 6, 'recoger', NULL, '', 1, 4.63, 'comprobante_908009e381731d68_1793812868.jpeg', 2, 'Cancelado automáticamente por falta de confirmación (tiempo límite excedido)'),
(6, 6, '2026-11-04 17:48:23', 7.00, 2, 'recoger', NULL, '', 5, 7.00, 'comprobante_d7b1f26ee06b5d6c_1793814503.jpeg', 2, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `imagen_icono` varchar(50) DEFAULT 'fas fa-hamburger',
  `categoria_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de productos del menú';

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `nombre`, `descripcion`, `precio_unitario`, `imagen`, `imagen_icono`, `categoria_id`, `activo`) VALUES
(1, 'Hamburguesa Clásica', 'Carne 150g, lechuga, tomate, cebolla y salsa especial', 5.50, 'producto_8dbd58148fc57f1c_1793207933.jpg', 'fas fa-hamburger', 1, 1),
(2, 'Hamburguesa Doble', 'Doble carne, doble queso cheddar, tocineta', 8.00, 'producto_d81a4ae7fb26864c_1793207811.jpg', 'fas fa-hamburger', 1, 1),
(3, 'Hamburguesa BBQ', 'Carne 150g, salsa BBQ, aros de cebolla, queso', 7.00, 'producto_47295afb9d0b66d7_1793207800.jpg', 'fas fa-hamburger', 1, 1),
(4, 'Hamburguesa Pollo', 'Pechuga de pollo a la plancha, lechuga, tomate', 6.50, 'producto_afbcfe5db3b470d9_1793207773.jpg', 'fas fa-hamburger', 1, 1),
(5, 'Coca-Cola 350ml', 'Lata 350ml', 1.50, 'producto_bd8336d575d6ebf6_1793208090.jpg', 'fas fa-hamburger', 2, 1),
(6, 'Jugo Natural', 'Jugo de naranja natural 400ml', 2.00, 'producto_3c636eff6cb1af4b_1793207636.jpg', 'fas fa-hamburger', 2, 1),
(7, 'Malteada de Chocolate', 'Malteada 400ml con helado de chocolate', 3.50, 'producto_77b822db79bebeca_1793208152.jpg', 'fas fa-hamburger', 2, 1),
(8, 'Helado de Vainilla', 'Copa de helado 120g', 3.00, 'producto_ca3d315075d6c4b6_1793207614.jpg', 'fas fa-hamburger', 3, 1),
(9, 'Brownie', 'Brownie de chocolate con nueces', 2.50, 'producto_c639353845f7ab31_1793207588.jpg', 'fas fa-hamburger', 3, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto_extras`
--

CREATE TABLE `producto_extras` (
  `producto_id` int(11) NOT NULL,
  `extra_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Extras específicos por producto (además de los heredados)';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nombre` varchar(20) NOT NULL,
  `descripcion` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Roles disponibles en el sistema';

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`, `descripcion`) VALUES
(1, 'admin', 'Administrador del sistema'),
(2, 'cliente', 'Cliente registrado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_direccion`
--

CREATE TABLE `tipos_direccion` (
  `id` int(11) NOT NULL,
  `nombre` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Clasificación de direcciones de entrega';

--
-- Volcado de datos para la tabla `tipos_direccion`
--

INSERT INTO `tipos_direccion` (`id`, `nombre`) VALUES
(1, 'Casa'),
(3, 'Otro'),
(2, 'Trabajo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `es_super_admin` tinyint(1) NOT NULL DEFAULT 0,
  `permisos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permisos`)),
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Credenciales de acceso. Rol 1=admin, 2=cliente';

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `email`, `password_hash`, `rol_id`, `es_super_admin`, `permisos`, `activo`, `fecha_registro`) VALUES
(4, 'jhons@gmail.com', '$2y$10$r5WcOkdvEvYK7o0ClO63CuJCPklx7gzkeTOclrLCOyZ6nGSMSs7hC', 2, 0, NULL, 1, '2026-10-28 05:26:33'),
(5, 'abrahamfebres57@gmail.com', '$2y$10$wfpQV/hjnn3.ACfK6qsL6.dLdJq2VWKXJqC1bTVCUIrT1i4SiC1w.', 1, 1, '[\"all\"]', 1, '2026-10-28 17:11:36'),
(6, 'jhon@gmail.com', '$2y$10$wTvCRhRiSxDop//OAKmaYOPwoGnjqKKdnzaU7Q5zRHGaNiquhB8Vy', 2, 0, NULL, 1, '2026-11-04 17:18:45');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_pedidos_completos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_pedidos_completos` (
`id` int(11)
,`fecha_pedido` timestamp
,`total_pedido` decimal(10,2)
,`monto_pagado` decimal(10,2)
,`tipo_entrega` enum('delivery','recoger')
,`estado_id` int(11)
,`estado_pedido` varchar(30)
,`estado_pago_id` int(11)
,`estado_pago` varchar(30)
,`metodo_pago` varchar(30)
,`cliente_email` varchar(100)
,`cliente_nombre` varchar(81)
,`cliente_telefono` varchar(11)
,`direccion_texto` varchar(500)
,`razon_cancelacion` varchar(500)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_top_productos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_top_productos` (
`producto_id` int(11)
,`producto` varchar(60)
,`categoria` varchar(40)
,`unidades_vendidas` decimal(32,0)
,`total_venta_base` decimal(42,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_ventas_por_dia`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_ventas_por_dia` (
`dia` date
,`total_pedidos` bigint(21)
,`total_facturado` decimal(32,2)
,`cancelados` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `v_pedidos_completos`
--
DROP TABLE IF EXISTS `v_pedidos_completos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_pedidos_completos`  AS SELECT `p`.`id` AS `id`, `p`.`fecha_pedido` AS `fecha_pedido`, `p`.`total_pedido` AS `total_pedido`, `p`.`monto_pagado` AS `monto_pagado`, `p`.`tipo_entrega` AS `tipo_entrega`, `p`.`estado_id` AS `estado_id`, `ep`.`nombre` AS `estado_pedido`, `p`.`estado_pago_id` AS `estado_pago_id`, `epg`.`nombre` AS `estado_pago`, `mp`.`nombre` AS `metodo_pago`, `u`.`email` AS `cliente_email`, concat(`c`.`nombres`,' ',`c`.`apellidos`) AS `cliente_nombre`, `c`.`telefono` AS `cliente_telefono`, `p`.`direccion_texto` AS `direccion_texto`, `p`.`razon_cancelacion` AS `razon_cancelacion` FROM (((((`pedidos` `p` join `usuarios` `u` on(`p`.`usuario_id` = `u`.`id`)) left join `clientes` `c` on(`u`.`id` = `c`.`usuario_id`)) join `estados_pedido` `ep` on(`p`.`estado_id` = `ep`.`id`)) join `estados_pago` `epg` on(`p`.`estado_pago_id` = `epg`.`id`)) join `metodos_pago` `mp` on(`p`.`metodo_pago_id` = `mp`.`id`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_top_productos`
--
DROP TABLE IF EXISTS `v_top_productos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_top_productos`  AS SELECT `pr`.`id` AS `producto_id`, `pr`.`nombre` AS `producto`, `cat`.`nombre` AS `categoria`, sum(`dp`.`cantidad`) AS `unidades_vendidas`, sum(`dp`.`cantidad` * `dp`.`precio_unitario_historico`) AS `total_venta_base` FROM (((`detalle_pedido` `dp` join `productos` `pr` on(`dp`.`producto_id` = `pr`.`id`)) left join `categorias` `cat` on(`pr`.`categoria_id` = `cat`.`id`)) join `pedidos` `p` on(`dp`.`pedido_id` = `p`.`id`)) WHERE `p`.`estado_id` <> 6 GROUP BY `pr`.`id`, `pr`.`nombre`, `cat`.`nombre` ORDER BY sum(`dp`.`cantidad`) DESC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_ventas_por_dia`
--
DROP TABLE IF EXISTS `v_ventas_por_dia`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_ventas_por_dia`  AS SELECT cast(`pedidos`.`fecha_pedido` as date) AS `dia`, count(0) AS `total_pedidos`, sum(case when `pedidos`.`estado_id` <> 6 then `pedidos`.`total_pedido` else 0 end) AS `total_facturado`, sum(case when `pedidos`.`estado_id` = 6 then 1 else 0 end) AS `cancelados` FROM `pedidos` GROUP BY cast(`pedidos`.`fecha_pedido` as date) ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `categoria_extras`
--
ALTER TABLE `categoria_extras`
  ADD PRIMARY KEY (`categoria_id`,`extra_id`),
  ADD KEY `fk_cat_ext_extra` (`extra_id`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_clientes_telefono` (`telefono`),
  ADD KEY `idx_clientes_nombres` (`nombres`,`apellidos`);

--
-- Indices de la tabla `configuracion_pago`
--
ALTER TABLE `configuracion_pago`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_config_activa` (`activo_unico`);

--
-- Indices de la tabla `detalle_pago`
--
ALTER TABLE `detalle_pago`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_detpago_metodo` (`metodo_pago_id`),
  ADD KEY `idx_detpago_pago` (`pago_id`);

--
-- Indices de la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_detalle_pedido` (`pedido_id`),
  ADD KEY `idx_detalle_producto` (`producto_id`);

--
-- Indices de la tabla `detalle_pedido_extras`
--
ALTER TABLE `detalle_pedido_extras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_det_ext_extra` (`extra_id`),
  ADD KEY `idx_det_ext_detalle` (`detalle_pedido_id`);

--
-- Indices de la tabla `direcciones`
--
ALTER TABLE `direcciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_direcciones_tipo` (`tipo_direccion_id`),
  ADD KEY `idx_direcciones_cliente` (`cliente_id`),
  ADD KEY `idx_direcciones_pred` (`cliente_id`,`predeterminada`);

--
-- Indices de la tabla `estados_pago`
--
ALTER TABLE `estados_pago`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `estados_pedido`
--
ALTER TABLE `estados_pedido`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `extras`
--
ALTER TABLE `extras`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `historial_estados_pedido`
--
ALTER TABLE `historial_estados_pedido`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_hist_usuario` (`usuario_id`),
  ADD KEY `fk_hist_estado_ant` (`estado_anterior_id`),
  ADD KEY `fk_hist_estado_nuevo` (`estado_nuevo_id`),
  ADD KEY `idx_hist_pedido` (`pedido_id`),
  ADD KEY `idx_hist_fecha` (`fecha`);

--
-- Indices de la tabla `metodos_pago`
--
ALTER TABLE `metodos_pago`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_usuario` (`usuario_id`),
  ADD KEY `idx_notif_usuario_leida` (`usuario_id`,`leida`),
  ADD KEY `idx_notif_pedido` (`pedido_id`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referencia` (`referencia`),
  ADD KEY `idx_pagos_pedido` (`pedido_id`),
  ADD KEY `idx_pagos_estado` (`estado_pago_id`),
  ADD KEY `idx_pagos_fecha` (`fecha_pago`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pedidos_metodo` (`metodo_pago_id`),
  ADD KEY `fk_pedidos_direccion` (`direccion_entrega_id`),
  ADD KEY `idx_pedidos_fecha` (`fecha_pedido`),
  ADD KEY `idx_pedidos_usuario` (`usuario_id`),
  ADD KEY `idx_pedidos_estado` (`estado_id`),
  ADD KEY `idx_pedidos_estado_pago` (`estado_pago_id`),
  ADD KEY `idx_pedidos_usuario_fecha` (`usuario_id`,`fecha_pedido`),
  ADD KEY `idx_pedidos_fecha_estado` (`fecha_pedido`,`estado_id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_producto_nombre_categoria` (`nombre`,`categoria_id`),
  ADD KEY `idx_productos_categoria` (`categoria_id`),
  ADD KEY `idx_productos_activo` (`activo`);

--
-- Indices de la tabla `producto_extras`
--
ALTER TABLE `producto_extras`
  ADD PRIMARY KEY (`producto_id`,`extra_id`),
  ADD KEY `fk_prod_ext_extra` (`extra_id`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `tipos_direccion`
--
ALTER TABLE `tipos_direccion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_usuarios_rol` (`rol_id`),
  ADD KEY `idx_usuarios_activo` (`activo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `configuracion_pago`
--
ALTER TABLE `configuracion_pago`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `detalle_pago`
--
ALTER TABLE `detalle_pago`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `detalle_pedido_extras`
--
ALTER TABLE `detalle_pedido_extras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `direcciones`
--
ALTER TABLE `direcciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `estados_pago`
--
ALTER TABLE `estados_pago`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `estados_pedido`
--
ALTER TABLE `estados_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `extras`
--
ALTER TABLE `extras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `historial_estados_pedido`
--
ALTER TABLE `historial_estados_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `metodos_pago`
--
ALTER TABLE `metodos_pago`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `tipos_direccion`
--
ALTER TABLE `tipos_direccion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `categoria_extras`
--
ALTER TABLE `categoria_extras`
  ADD CONSTRAINT `fk_cat_ext_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cat_ext_extra` FOREIGN KEY (`extra_id`) REFERENCES `extras` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `fk_clientes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `detalle_pago`
--
ALTER TABLE `detalle_pago`
  ADD CONSTRAINT `fk_detpago_metodo` FOREIGN KEY (`metodo_pago_id`) REFERENCES `metodos_pago` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detpago_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  ADD CONSTRAINT `fk_detalle_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detalle_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `detalle_pedido_extras`
--
ALTER TABLE `detalle_pedido_extras`
  ADD CONSTRAINT `fk_det_ext_detalle` FOREIGN KEY (`detalle_pedido_id`) REFERENCES `detalle_pedido` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_det_ext_extra` FOREIGN KEY (`extra_id`) REFERENCES `extras` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `direcciones`
--
ALTER TABLE `direcciones`
  ADD CONSTRAINT `fk_direcciones_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_direcciones_tipo` FOREIGN KEY (`tipo_direccion_id`) REFERENCES `tipos_direccion` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `historial_estados_pedido`
--
ALTER TABLE `historial_estados_pedido`
  ADD CONSTRAINT `fk_hist_estado_ant` FOREIGN KEY (`estado_anterior_id`) REFERENCES `estados_pedido` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hist_estado_nuevo` FOREIGN KEY (`estado_nuevo_id`) REFERENCES `estados_pedido` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hist_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hist_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `fk_notif_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notif_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `fk_pagos_estado` FOREIGN KEY (`estado_pago_id`) REFERENCES `estados_pago` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pagos_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `fk_pedidos_direccion` FOREIGN KEY (`direccion_entrega_id`) REFERENCES `direcciones` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedidos_estado` FOREIGN KEY (`estado_id`) REFERENCES `estados_pedido` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedidos_estado_pago` FOREIGN KEY (`estado_pago_id`) REFERENCES `estados_pago` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedidos_metodo` FOREIGN KEY (`metodo_pago_id`) REFERENCES `metodos_pago` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedidos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `fk_productos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `producto_extras`
--
ALTER TABLE `producto_extras`
  ADD CONSTRAINT `fk_prod_ext_extra` FOREIGN KEY (`extra_id`) REFERENCES `extras` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_prod_ext_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
