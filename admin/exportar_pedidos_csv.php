<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';
require_once __DIR__ . '/../includes/seguridad.php';

if (!esAdmin()) { redirect('index.php'); }
if (!tienePermiso('pedidos') && !tienePermiso('reportes')) {
    requierePermiso('pedidos');
}

// ============================================================
// FILTROS VALIDADOS
// ============================================================
$filtro_fecha_desde = v_fecha($_GET['fecha_desde'] ?? '', true);
$filtro_fecha_hasta = v_fecha($_GET['fecha_hasta'] ?? '', true);
$filtro_estado = v_int($_GET['estado'] ?? 0, 0);
$filtro_entrega = $_GET['entrega'] ?? '';
$filtro_metodo_pago = v_int($_GET['metodo_pago'] ?? 0, 0);
$filtro_estado_pago = v_int($_GET['estado_pago'] ?? 0, 0);
$filtro_cliente_id = v_int($_GET['cliente_id'] ?? 0, 0);

$where = []; $params = [];
if ($filtro_fecha_desde) { $where[] = "DATE(p.fecha_pedido) >= ?"; $params[] = $filtro_fecha_desde; }
if ($filtro_fecha_hasta) { $where[] = "DATE(p.fecha_pedido) <= ?"; $params[] = $filtro_fecha_hasta; }
if ($filtro_estado > 0) { $where[] = "p.estado_id = ?"; $params[] = $filtro_estado; }
if ($filtro_entrega === 'delivery' || $filtro_entrega === 'recoger') { $where[] = "p.tipo_entrega = ?"; $params[] = $filtro_entrega; }
if ($filtro_metodo_pago > 0) { $where[] = "p.metodo_pago_id = ?"; $params[] = $filtro_metodo_pago; }
if ($filtro_estado_pago > 0) { $where[] = "p.estado_pago_id = ?"; $params[] = $filtro_estado_pago; }
if ($filtro_cliente_id > 0) { $where[] = "c.id = ?"; $params[] = $filtro_cliente_id; }

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT p.id, p.fecha_pedido, p.total_pedido, p.estado_id,
           c.nombres, c.apellidos, c.telefono,
           u.email,
           p.tipo_entrega, p.direccion_texto,
           mp.nombre AS metodo_pago,
           ep.nombre AS estado_pago,
           p.monto_pagado,
           e.nombre AS estado_pedido
    FROM pedidos p
    JOIN usuarios u ON p.usuario_id = u.id
    JOIN clientes c ON u.id = c.usuario_id
    JOIN estados_pedido e ON p.estado_id = e.id
    JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
    JOIN estados_pago ep ON p.estado_pago_id = ep.id
    $where_sql
    ORDER BY p.fecha_pedido DESC
");
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

// Extras por pedido
$extras_por_pedido = [];
if (!empty($pedidos)) {
    $ids = array_column($pedidos, 'id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmtExt = $pdo->prepare("
        SELECT dp.pedido_id, GROUP_CONCAT(CONCAT(dpe.nombre, ' (+$', dpe.precio_extra, ')') SEPARATOR ', ') AS extras
        FROM detalle_pedido dp
        JOIN detalle_pedido_extras dpe ON dpe.detalle_pedido_id = dp.id
        WHERE dp.pedido_id IN ($ph)
        GROUP BY dp.pedido_id
    ");
    $stmtExt->execute($ids);
    foreach ($stmtExt->fetchAll() as $row) {
        $extras_por_pedido[$row['pedido_id']] = $row['extras'];
    }
}

$nombre_archivo = 'Pedidos_MinasBurguer_' . date('Y-m-d_H-i') . '.csv';

// Headers de descarga
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

$output = fopen('php://output', 'w');

// BOM UTF-8
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

$sep = ';';

// ============================================================
// ENCABEZADO (con sanitizacion)
// ============================================================
fputcsv($output, sanitizar_fila_csv(['MINAS BURGUER - Reporte de Pedidos']), $sep);
fputcsv($output, sanitizar_fila_csv(['Generado: ' . date('d/m/Y H:i')]), $sep);

$filtros_txt = [];
if ($filtro_fecha_desde) $filtros_txt[] = 'Desde: ' . date('d/m/Y', strtotime($filtro_fecha_desde));
if ($filtro_fecha_hasta) $filtros_txt[] = 'Hasta: ' . date('d/m/Y', strtotime($filtro_fecha_hasta));
if ($filtro_estado > 0) { $s=$pdo->prepare("SELECT nombre FROM estados_pedido WHERE id=?"); $s->execute([$filtro_estado]); $filtros_txt[] = 'Estado: ' . ucfirst($s->fetchColumn()); }
if ($filtro_entrega) $filtros_txt[] = 'Entrega: ' . ucfirst($filtro_entrega);
if ($filtro_metodo_pago > 0) { $s=$pdo->prepare("SELECT nombre FROM metodos_pago WHERE id=?"); $s->execute([$filtro_metodo_pago]); $filtros_txt[] = 'Metodo: ' . $s->fetchColumn(); }
if ($filtro_estado_pago > 0) { $s=$pdo->prepare("SELECT nombre FROM estados_pago WHERE id=?"); $s->execute([$filtro_estado_pago]); $filtros_txt[] = 'Estado pago: ' . ucfirst($s->fetchColumn()); }
if ($filtro_cliente_id > 0) { $s=$pdo->prepare("SELECT nombres, apellidos FROM clientes WHERE id=?"); $s->execute([$filtro_cliente_id]); $cl=$s->fetch(); if ($cl) $filtros_txt[] = 'Cliente: ' . $cl['nombres'] . ' ' . $cl['apellidos']; }

fputcsv($output, sanitizar_fila_csv(['Filtros: ' . (empty($filtros_txt) ? 'Ninguno' : implode(' | ', $filtros_txt))]), $sep);

fputcsv($output, [], $sep);

// ============================================================
// ENCABEZADOS DE LA TABLA
// ============================================================
fputcsv($output, [
    'ID', 'Fecha', 'Cliente', 'Telefono', 'Email',
    'Tipo entrega', 'Direccion', 'Metodo pago', 'Estado pago',
    'Monto pagado', 'Estado pedido', 'Extras', 'Total'
], $sep);

// ============================================================
// FILAS — sanitizadas una por una
// ============================================================
foreach ($pedidos as $p) {
    $fila = [
        intval($p['id']),
        date('d/m/Y H:i', strtotime($p['fecha_pedido'])),
        trim($p['nombres'] . ' ' . $p['apellidos']),
        $p['telefono'] ?: '-',
        $p['email'] ?: '-',
        ucfirst($p['tipo_entrega']),
        $p['direccion_texto'] ?: '-',
        $p['metodo_pago'],
        ucfirst($p['estado_pago']),
        number_format((float)$p['monto_pagado'], 2, ',', ''),
        ucfirst($p['estado_pedido']),
        $extras_por_pedido[$p['id']] ?? '-',
        number_format((float)$p['total_pedido'], 2, ',', '')
    ];
    fputcsv($output, sanitizar_fila_csv($fila), $sep);
}

// ============================================================
// RESUMEN (Excluyendo cancelados del cálculo monetario)
// ============================================================
$total_general = 0;
$total_pagado = 0;
$cantidad = count($pedidos);
$cancelados = 0;

foreach ($pedidos as $p) {
    if (intval($p['estado_id']) === 6) {
        $cancelados++;
        continue;
    }
    $total_general += floatval($p['total_pedido']);
    $total_pagado += floatval($p['monto_pagado']);
}

fputcsv($output, [], $sep);

fputcsv($output, ['', '', '', '', '', '', '', '', '', 'RESUMEN', '', '', ''], $sep);
fputcsv($output, ['', '', '', '', '', '', '', '', '', 'Cantidad de pedidos', $cantidad, '', ''], $sep);
fputcsv($output, ['', '', '', '', '', '', '', '', '', 'Pedidos cancelados', $cancelados, '', ''], $sep);
fputcsv($output, sanitizar_fila_csv(['', '', '', '', '', '', '', '', '', 'Total facturado (sin cancelados)', number_format($total_general, 2, ',', ''), '', '']), $sep);
fputcsv($output, sanitizar_fila_csv(['', '', '', '', '', '', '', '', '', 'Total cobrado', number_format($total_pagado, 2, ',', ''), '', '']), $sep);
fputcsv($output, sanitizar_fila_csv(['', '', '', '', '', '', '', '', '', 'Pendiente de cobro', number_format(max(0, $total_general - $total_pagado), 2, ',', ''), '', '']), $sep);

fclose($output);
exit;