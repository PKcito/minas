<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';

if (!esAdmin()) {
    redirect('index.php');
}
if (!tienePermiso('reportes') && !tienePermiso('pedidos')) {
    redirect('admin/dashboard.php');
}

// ============================================================
// 1. IDENTIDAD VISUAL DESDE CONFIGURACION_PAGO
// ============================================================
$nombre_negocio_pdf = 'MINAS BURGUER';
$logo_pdf_url = '';$tasa_dolar_pdf = 1.00;

try {
    $cfg_pdf =$pdo->query("SELECT * FROM configuracion_pago WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($cfg_pdf) {
        if (!empty(trim($cfg_pdf['nombre_negocio'] ?? ''))) {
            $nombre_negocio_pdf = trim($cfg_pdf['nombre_negocio']);
        }
        if (!empty($cfg_pdf['logo']) && file_exists(__DIR__ . '/../uploads/config/' .$cfg_pdf['logo'])) {
            $logo_pdf_url = url('uploads/config/' . rawurlencode($cfg_pdf['logo']));
        }
        if (!empty($cfg_pdf['tasa_dolar']) && floatval($cfg_pdf['tasa_dolar']) > 0) {
            $tasa_dolar_pdf = floatval($cfg_pdf['tasa_dolar']);
        }
    }
} catch (Exception $e) {
    error_log('Error cargando configuracion en reporte_pdf.php: ' . $e->getMessage());
}

// Formatear última palabra en color acento (#d32f2f)
$palabras_pdf = preg_split('/\s+/',$nombre_negocio_pdf);
if (count($palabras_pdf) > 1) {$ultima_pdf = array_pop($palabras_pdf);$primera_pdf = implode(' ', $palabras_pdf);$titulo_marca_html = htmlspecialchars($primera_pdf) . ' <span>' . htmlspecialchars($ultima_pdf) . '</span>';
} else {
    $titulo_marca_html = '<span>' . htmlspecialchars($nombre_negocio_pdf) . '</span>';
}

// ============================================================
// 2. RECEPCIÓN DE FILTROS (SOPORTE GET Y POST)
// ============================================================
$input = array_merge($_GET,$_POST);

$fecha_desde =$input['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_hasta =$input['fecha_hasta'] ?? date('Y-m-d');
$filtro_estado = intval($input['estado'] ?? 0);
$filtro_entrega = trim($input['entrega'] ?? '');
$filtro_metodo_pago = intval($input['metodo_pago'] ?? 0);
$filtro_estado_pago = intval($input['estado_pago'] ?? 0);
$filtro_producto = intval($input['producto'] ?? 0);
$filtro_cliente = intval($input['cliente'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {$fecha_desde = date('Y-m-d', strtotime('-30 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {$fecha_hasta = date('Y-m-d');
}
if ($fecha_desde >$fecha_hasta) {
    $tmp =$fecha_desde;
    $fecha_desde =$fecha_hasta;
    $fecha_hasta =$tmp;
}

$dt_desde = new DateTime($fecha_desde);$dt_hasta = new DateTime($fecha_hasta);$dias_rango = max(1, intval($dt_desde->diff($dt_hasta)->days) + 1);

// ============================================================
// 3. CONDICIONES SQL Y EXPRESIÓN EXACTA DE MONTO
// ============================================================
$where = ["DATE(p.fecha_pedido) BETWEEN ? AND ?"];
$params = [$fecha_desde,$fecha_hasta];

if ($filtro_estado > 0) {$where[] = "p.estado_id = ?";
    $params[] =$filtro_estado;
}
if ($filtro_entrega === 'delivery' || $filtro_entrega === 'recoger') {$where[] = "p.tipo_entrega = ?";
    $params[] =$filtro_entrega;
}
if ($filtro_metodo_pago > 0) {$where[] = "p.metodo_pago_id = ?";
    $params[] =$filtro_metodo_pago;
}
if ($filtro_estado_pago > 0) {$where[] = "p.estado_pago_id = ?";
    $params[] =$filtro_estado_pago;
}
if ($filtro_producto > 0) {$where[] = "EXISTS (SELECT 1 FROM detalle_pedido dp_f WHERE dp_f.pedido_id = p.id AND dp_f.producto_id = ?)";
    $params[] =$filtro_producto;
}
if ($filtro_cliente > 0) {$where[] = "p.usuario_id = ?";
    $params[] =$filtro_cliente;
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

// Universo contable válido: excluye Cancelados (estado_id = 6) salvo que se filtre explícitamente por Cancelados
$where_kpi =$where;
$params_kpi =$params;
if ($filtro_estado === 0) {$where_kpi[] = "p.estado_id != 6";
}
$where_sql_kpi = 'WHERE ' . implode(' AND ', $where_kpi);

if ($filtro_producto > 0) {$id_prod_seguro = intval($filtro_producto);$sql_monto_pedido = "(
        SELECT COALESCE(SUM(
            dp_sub.cantidad * (
                dp_sub.precio_unitario_historico +
                COALESCE((SELECT SUM(dpe_sub.precio_extra) FROM detalle_pedido_extras dpe_sub WHERE dpe_sub.detalle_pedido_id = dp_sub.id), 0)
            )
        ), 0)
        FROM detalle_pedido dp_sub
        WHERE dp_sub.pedido_id = p.id AND dp_sub.producto_id = {$id_prod_seguro}
    )";
} else {
    $sql_monto_pedido = "p.total_pedido";
}

// ============================================================
// 4. CONSULTAS DE RESUMEN Y TABLAS DEL REPORTE
// ============================================================
$stmt =$pdo->prepare("
    SELECT
        COALESCE(SUM($sql_monto_pedido), 0) AS ingresos_validos,
        COUNT(p.id) AS pedidos_validos,
        COALESCE(SUM(CASE WHEN p.estado_id = 5 THEN $sql_monto_pedido ELSE 0 END), 0) AS ingresos_entregados,
        SUM(CASE WHEN p.estado_id = 5 THEN 1 ELSE 0 END) AS cantidad_entregados,
        COALESCE(SUM(CASE WHEN p.estado_id NOT IN (5, 6) THEN $sql_monto_pedido ELSE 0 END), 0) AS ingresos_en_curso,
        SUM(CASE WHEN p.estado_id NOT IN (5, 6) THEN 1 ELSE 0 END) AS cantidad_en_curso
    FROM pedidos p
    $where_sql_kpi
");
$stmt->execute($params_kpi);
$kpi =$stmt->fetch(PDO::FETCH_ASSOC);

$total_facturado = round(floatval($kpi['ingresos_validos'] ?? 0), 2);
$total_pedidos_validos = intval($kpi['pedidos_validos'] ?? 0);
$ingresos_entregados = round(floatval($kpi['ingresos_entregados'] ?? 0), 2);
$cantidad_entregados = intval($kpi['cantidad_entregados'] ?? 0);
$ingresos_en_curso = round(floatval($kpi['ingresos_en_curso'] ?? 0), 2);
$cantidad_en_curso = intval($kpi['cantidad_en_curso'] ?? 0);

$ticket_promedio =$total_pedidos_validos > 0 ? round($total_facturado / $total_pedidos_validos, 2) : 0.00;
$promedio_diario = round($total_facturado / $dias_rango, 2);

// Ingresos por Categoría
$where_detalle =$where_kpi;
$params_detalle =$params_kpi;
if ($filtro_producto > 0) {$where_detalle[] = "dp.producto_id = ?";
    $params_detalle[] =$filtro_producto;
}
$where_sql_detalle = 'WHERE ' . implode(' AND ', $where_detalle);

$stmt =$pdo->prepare("
    SELECT c.nombre,
           SUM(dp.cantidad) AS unidades,
           COALESCE(SUM(
               dp.cantidad * (
                   dp.precio_unitario_historico +
                   COALESCE(ext.total_extras_unit, 0)
               )
           ), 0) AS total_dinero
    FROM detalle_pedido dp
    JOIN productos pr ON dp.producto_id = pr.id
    JOIN categorias c ON pr.categoria_id = c.id
    JOIN pedidos p ON dp.pedido_id = p.id
    LEFT JOIN (
        SELECT detalle_pedido_id, SUM(precio_extra) AS total_extras_unit
        FROM detalle_pedido_extras
        GROUP BY detalle_pedido_id
    ) ext ON ext.detalle_pedido_id = dp.id
    $where_sql_detalle
    GROUP BY c.id, c.nombre
    ORDER BY total_dinero DESC
");
$stmt->execute($params_detalle);
$por_categoria =$stmt->fetchAll(PDO::FETCH_ASSOC);

// Top 5 Productos
$stmt =$pdo->prepare("
    SELECT pr.nombre,
           SUM(dp.cantidad) AS unidades,
           COALESCE(SUM(
               dp.cantidad * (
                   dp.precio_unitario_historico +
                   COALESCE(ext.total_extras_unit, 0)
               )
           ), 0) AS total_dinero
    FROM detalle_pedido dp
    JOIN productos pr ON dp.producto_id = pr.id
    JOIN pedidos p ON dp.pedido_id = p.id
    LEFT JOIN (
        SELECT detalle_pedido_id, SUM(precio_extra) AS total_extras_unit
        FROM detalle_pedido_extras
        GROUP BY detalle_pedido_id
    ) ext ON ext.detalle_pedido_id = dp.id
    $where_sql_detalle
    GROUP BY pr.id, pr.nombre
    ORDER BY unidades DESC, total_dinero DESC
    LIMIT 5
");
$stmt->execute($params_detalle);
$top_productos =$stmt->fetchAll(PDO::FETCH_ASSOC);

// Listado completo de pedidos en el rango
$stmt =$pdo->prepare("
    SELECT p.id, p.fecha_pedido, p.tipo_entrega, p.total_pedido,
           ($sql_monto_pedido) AS monto_computado,
           COALESCE(c.nombres, 'Cliente') AS nombres,
           COALESCE(c.apellidos, CONCAT('#', p.usuario_id)) AS apellidos,
           COALESCE(c.telefono, '-') AS telefono,
           e.id AS estado_id, e.nombre AS estado_nombre,
           mp.nombre AS metodo_pago_nombre,
           ep.nombre AS estado_pago_nombre
    FROM pedidos p
    LEFT JOIN clientes c ON p.usuario_id = c.usuario_id
    JOIN estados_pedido e ON p.estado_id = e.id
    JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
    LEFT JOIN estados_pago ep ON p.estado_pago_id = ep.id
    $where_sql
    ORDER BY p.fecha_pedido DESC, p.id DESC
");
$stmt->execute($params);
$pedidos =$stmt->fetchAll(PDO::FETCH_ASSOC);

// Nombres legibles de filtros aplicados
$etiquetas_filtros = [];
if ($filtro_estado > 0) {
    $st =$pdo->prepare("SELECT nombre FROM estados_pedido WHERE id = ?");
    $st->execute([$filtro_estado]);
    if ($n =$st->fetchColumn()) $etiquetas_filtros[] = "Estado: " . ucfirst($n);
} else {
    $etiquetas_filtros[] = "Estado: Todos (Excluyendo cancelados en totales)";
}
if ($filtro_entrega !== '') {
    $etiquetas_filtros[] = "Entrega: " . ucfirst($filtro_entrega);
}
if ($filtro_metodo_pago > 0) {
    $st =$pdo->prepare("SELECT nombre FROM metodos_pago WHERE id = ?");
    $st->execute([$filtro_metodo_pago]);
    if ($n = $st->fetchColumn())$etiquetas_filtros[] = "Método Pago: " . ucfirst($n);
}
if ($filtro_producto > 0) {
    $st =$pdo->prepare("SELECT nombre FROM productos WHERE id = ?");
    $st->execute([$filtro_producto]);
    if ($n =$st->fetchColumn()) $etiquetas_filtros[] = "Producto: " . $n;
}

$colores_estados = [
    1 => '#ffc107', 2 => '#17a2b8', 3 => '#007bff', 4 => '#28a745',
    5 => '#6c757d', 6 => '#dc3545', 7 => '#9c27b0'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Ventas - <?= htmlspecialchars($nombre_negocio_pdf) ?> (<?= htmlspecialchars($fecha_desde) ?> a <?= htmlspecialchars($fecha_hasta) ?>)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1f2937;
            background: #f3f4f6;
            padding: 24px;
            line-height: 1.45;
        }
        .report-sheet {
            max-width: 1050px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 32px 36px;
        }
        .no-print-bar {
            max-width: 1050px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .btn-bar {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        .btn-print { background: #d32f2f; color: #fff; }
        .btn-print:hover { background: #b71c1c; }
        .btn-back { background: #1f2937; color: #fff; }
        .btn-back:hover { background: #111827; }

        /* Encabezado con Logo y Nombre dinámico */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #d32f2f;
            padding-bottom: 18px;
            margin-bottom: 22px;
            gap: 20px;
        }
        .brand-box {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .brand-logo {
            width: 62px;
            height: 62px;
            border-radius: 14px;
            object-fit: contain;
            border: 2px solid #d32f2f;
            background: #121212;
            padding: 3px;
        }
        .brand-icon-fallback {
            width: 58px;
            height: 58px;
            border-radius: 14px;
            background: #d32f2f;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
        }
        .brand-title h1 {
            font-size: 1.65rem;
            font-weight: 900;
            letter-spacing: 0.5px;
            color: #111827;
            text-transform: uppercase;
        }
        .brand-title h1 span {
            color: #d32f2f;
        }
        .brand-title p {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 600;
            margin-top: 2px;
        }
        .meta-box {
            text-align: right;
            font-size: 0.83rem;
            color: #4b5563;
        }
        .meta-box strong { color: #111827; }

        .filtros-resumen {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 20px;
            font-size: 0.82rem;
            color: #374151;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .filtros-resumen span {
            background: #fff;
            border: 1px solid #d1d5db;
            padding: 3px 10px;
            border-radius: 50px;
            font-weight: 600;
        }

        /* KPIs */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }
        .kpi-box {
            border: 1px solid #e5e7eb;
            border-left: 4px solid #d32f2f;
            border-radius: 10px;
            padding: 12px 14px;
            background: #fafafa;
        }
        .kpi-box .label {
            font-size: 0.72rem;
            text-transform: uppercase;
            color: #6b7280;
            font-weight: 700;
        }
        .kpi-box .val {
            font-size: 1.35rem;
            font-weight: 800;
            color: #111827;
            margin-top: 3px;
        }
        .kpi-box .sub {
            font-size: 0.74rem;
            color: #6b7280;
            margin-top: 2px;
        }

        .two-cols {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 24px;
        }
        .section-box {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 14px;
        }
        .section-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: #111827;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 7px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 6px;
        }
        .section-title i { color: #d32f2f; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        th, td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }
        th {
            background: #f9fafb;
            font-weight: 700;
            color: #374151;
            text-transform: uppercase;
            font-size: 0.72rem;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge-est {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 50px;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .fila-cancelada {
            opacity: 0.6;
            background: #fff5f5;
        }
        .report-footer {
            margin-top: 26px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: #6b7280;
        }

        @media print {
            body { background: #fff; padding: 0; }
            .no-print-bar { display: none !important; }
            .report-sheet { box-shadow: none; padding: 10px; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <a href="<?= url('admin/dashboard.php') ?>" class="btn-bar btn-back">
        <i class="fas fa-arrow-left"></i> Volver al Panel
    </a>
    <button type="button" class="btn-bar btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Imprimir / Guardar como PDF
    </button>
</div>

<div class="report-sheet">
    <header class="report-header">
        <div class="brand-box">
            <?php if ($logo_pdf_url !== ''): ?>
                <img src="<?= htmlspecialchars($logo_pdf_url) ?>" alt="Logo" class="brand-logo">
            <?php else: ?>
                <div class="brand-icon-fallback"><i class="fas fa-hamburger"></i></div>
            <?php endif; ?>
            <div class="brand-title">
                <h1><?= $titulo_marca_html ?></h1>
                <p>Reporte Ejecutivo y Contable de Ventas</p>
            </div>
        </div>
        <div class="meta-box">
            <div><strong>Período:</strong> <?= date('d/m/Y', strtotime($fecha_desde)) ?> al <?= date('d/m/Y', strtotime($fecha_hasta)) ?> (<?= $dias_rango ?> días)</div>
            <div><strong>Fecha de emisión:</strong> <?= date('d/m/Y h:i A') ?></div>
            <div><strong>Tasa referencial:</strong> Bs. <?= number_format($tasa_dolar_pdf, 2) ?> / USD</div>
        </div>
    </header>

    <div class="filtros-resumen">
        <strong><i class="fas fa-filter"></i> Parámetros del reporte:</strong>
        <?php foreach ($etiquetas_filtros as $ef): ?>
            <span><?= htmlspecialchars($ef) ?></span>
        <?php endforeach; ?>
    </div>

    <div class="kpi-grid">
        <div class="kpi-box">
            <div class="label"><?= $filtro_producto > 0 ? 'Ingresos del Producto' : 'Ingresos Válidos ($)' ?></div>
            <div class="val">$<?= number_format($total_facturado, 2) ?></div>
            <div class="sub">Bs. <?= number_format($total_facturado * $tasa_dolar_pdf, 2) ?></div>
        </div>
        <div class="kpi-box">
            <div class="label">Desglose de Estado</div>
            <div class="val"><?= number_format($total_pedidos_validos) ?> ped.</div>
            <div class="sub">Entregado: $<?= number_format($ingresos_entregados, 2) ?> (<?= $cantidad_entregados ?>) · En curso: $<?= number_format($ingresos_en_curso, 2) ?> (<?= $cantidad_en_curso ?>)</div>
        </div>
        <div class="kpi-box">
            <div class="label">Ticket Promedio</div>
            <div class="val">$<?= number_format($ticket_promedio, 2) ?></div>
            <div class="sub">Por pedido válido</div>
        </div>
        <div class="kpi-box">
            <div class="label">Promedio Diario</div>
            <div class="val">$<?= number_format($promedio_diario, 2) ?></div>
            <div class="sub">En <?= $dias_rango ?> día(s)</div>
        </div>
    </div>

    <div class="two-cols">
        <div class="section-box">
            <div class="section-title"><i class="fas fa-tags"></i> Ingresos por Categoría (Incluye Extras)</div>
            <table>
                <thead>
                    <tr>
                        <th>Categoría</th>
                        <th class="text-center">Unidades</th>
                        <th class="text-right">Ingresos ($)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($por_categoria)): ?>
                        <tr><td colspan="3" class="text-center">Sin registros en este período.</td></tr>
                    <?php else: ?>
                        <?php foreach ($por_categoria as $cat): ?>
                            <tr>
                                <td><?= htmlspecialchars($cat['nombre']) ?></td>
                                <td class="text-center"><?= intval($cat['unidades']) ?></td>
                                <td class="text-right"><strong>$<?= number_format($cat['total_dinero'], 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="section-box">
            <div class="section-title"><i class="fas fa-fire"></i> Top 5 Productos Vendidos</div>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="text-center">Unidades</th>
                        <th class="text-right">Total ($)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_productos)): ?>
                        <tr><td colspan="3" class="text-center">Sin registros en este período.</td></tr>
                    <?php else: ?>
                        <?php foreach ($top_productos as $tp): ?>
                            <tr>
                                <td><?= htmlspecialchars($tp['nombre']) ?></td>
                                <td class="text-center"><?= intval($tp['unidades']) ?></td>
                                <td class="text-right"><strong>$<?= number_format($tp['total_dinero'], 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="section-box">
        <div class="section-title"><i class="fas fa-clipboard-list"></i> Detalle de Pedidos (<?= count($pedidos) ?> registros)</div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha / Hora</th>
                    <th>Cliente</th>
                    <th>Entrega</th>
                    <th>Método Pago</th>
                    <th>Estado</th>
                    <th class="text-right"><?= $filtro_producto > 0 ? 'Monto Prod. ($)' : 'Total ($)' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pedidos)): ?>
                    <tr><td colspan="7" class="text-center">No hay pedidos para los filtros seleccionados.</td></tr>
                <?php else: ?>
                    <?php foreach ($pedidos as $ped): ?>
                        <?php
                        $es_cancelado = intval($ped['estado_id']) === 6;
                        $color_bg = $colores_estados[intval($ped['estado_id'])] ?? '#6c757d';
                        ?>
                        <tr class="<?= $es_cancelado ? 'fila-cancelada' : '' ?>">
                            <td><strong>#<?= intval($ped['id']) ?></strong></td>
                            <td><?= date('d/m/Y H:i', strtotime($ped['fecha_pedido'])) ?></td>
                            <td><?= htmlspecialchars(trim($ped['nombres'] . ' ' . $ped['apellidos'])) ?></td>
                            <td><?= htmlspecialchars(ucfirst($ped['tipo_entrega'])) ?></td>
                            <td><?= htmlspecialchars(ucfirst($ped['metodo_pago_nombre'])) ?></td>
                            <td>
                                <span class="badge-est" style="background: <?= $color_bg ?>;">
                                    <?= htmlspecialchars(ucfirst($ped['estado_nombre'])) ?>
                                </span>
                            </td>
                            <td class="text-right">
                                <strong>$<?= number_format($ped['monto_computado'], 2) ?></strong>
                                <?php if ($es_cancelado): ?><br><small>(No suma)</small><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <footer class="report-footer">
        <span><?= htmlspecialchars($nombre_negocio_pdf) ?> — Sistema de Gestión y Pedidos Online</span>
        <span>Generado por: <?= htmlspecialchars($_SESSION['nombres'] ?? 'Administrador') ?></span>
    </footer>
</div>

</body>
</html>