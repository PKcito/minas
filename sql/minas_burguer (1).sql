-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 28-10-2026 a las 06:50:50
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

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
(2, 3, 'Abraham', 'Febres', '04123403630', NULL, NULL),
(3, 4, 'Jhon', 'Smith', '04121234567', NULL, NULL);

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
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `activo_unico` tinyint(4) GENERATED ALWAYS AS (if(`activo` = 1,1,NULL)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Datos de PagoMóvil. Solo 1 configuración activa permitida';

--
-- Volcado de datos para la tabla `configuracion_pago`
--

INSERT INTO `configuracion_pago` (`id`, `banco`, `telefono`, `cedula`, `titular`, `activo`) VALUES
(1, 'Banesco', '04141234567', 'V-12345678', 'Minas Burguer C.A.', 1);

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
(1, 'Hamburguesa Clásica', 'Carne 150g, lechuga, tomate, cebolla y salsa especial', 5.50, NULL, 'fas fa-hamburger', 1, 1),
(2, 'Hamburguesa Doble', 'Doble carne, doble queso cheddar, tocineta', 8.00, NULL, 'fas fa-hamburger', 1, 1),
(3, 'Hamburguesa BBQ', 'Carne 150g, salsa BBQ, aros de cebolla, queso', 7.00, NULL, 'fas fa-hamburger', 1, 1),
(4, 'Hamburguesa Pollo', 'Pechuga de pollo a la plancha, lechuga, tomate', 6.50, NULL, 'fas fa-drumstick-bite', 1, 1),
(5, 'Coca-Cola 350ml', 'Lata 350ml', 1.50, NULL, 'fas fa-glass-whiskey', 2, 1),
(6, 'Jugo Natural', 'Jugo de naranja natural 400ml', 2.00, NULL, 'fas fa-glass-whiskey', 2, 1),
(7, 'Malteada de Chocolate', 'Malteada 400ml con helado de chocolate', 3.50, NULL, 'fas fa-coffee', 2, 1),
(8, 'Helado de Vainilla', 'Copa de helado 120g', 3.00, NULL, 'fas fa-ice-cream', 3, 1),
(9, 'Brownie', 'Brownie de chocolate con nueces', 2.50, NULL, 'fas fa-cookie-bite', 3, 1);

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
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Credenciales de acceso. Rol 1=admin, 2=cliente';

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `email`, `password_hash`, `rol_id`, `activo`, `fecha_registro`) VALUES
(3, 'abrahamfebres57@gmail.com', '$2y$10$1ikB/n.KAYLTgDXklqDjT.MjsbIgpz6vVZ69G6rI9LgtW6hLdhwEK', 1, 1, '2026-10-28 05:24:27'),
(4, 'jhons@gmail.com', '$2y$10$r5WcOkdvEvYK7o0ClO63CuJCPklx7gzkeTOclrLCOyZ6nGSMSs7hC', 2, 1, '2026-10-28 05:26:33');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `configuracion_pago`
--
ALTER TABLE `configuracion_pago`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `detalle_pago`
--
ALTER TABLE `detalle_pago`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_pedido_extras`
--
ALTER TABLE `detalle_pedido_extras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `metodos_pago`
--
ALTER TABLE `metodos_pago`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
