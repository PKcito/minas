<?php
require_once __DIR__ . '/../config.php';

if (!esAdmin()) {
    redirect('index.php');
}
if (!tienePermiso('reportes') && !tienePermiso('pedidos')) {
    redirect('admin/dashboard.php');
}

$nombre_negocio_csv = 'MINAS BURGUER';
try {
    $cfg_csv = $pdo->query("SELECT nombre_negocio FROM configuracion_pago WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!empty(trim($cfg_csv['nombre_negocio'] ?? ''))) {
        $nombre_negocio_csv = trim($cfg_csv['nombre_negocio']);
    }
} catch (Exception $e) {}

$input = array_merge($_GET, $_POST);

$fecha_desde = $input['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_hasta = $input['fecha_hasta'] ?? date('Y-m-d');
$filtro_estado = intval($input['estado'] ?? 0);
$filtro_entrega = trim($input['entrega'] ?? '');
$filtro_metodo_pago = intval($input['metodo_pago'] ?? 0);
$filtro_estado_pago = intval($input['estado_pago'] ?? 0);
$filtro_producto = intval($input['producto'] ?? 0);
$filtro_cliente = intval($input['cliente'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {
    $fecha_desde = date('Y-m-d', strtotime('-30 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
    $fecha_hasta = date('Y-m-d');
}
if ($fecha_desde > $fecha_hasta) {
    $tmp = $fecha_desde;
    $fecha_desde = $fecha_hasta;
    $fecha_hasta = $tmp;
}

$where = ["DATE(p.fecha_pedido) BETWEEN ? AND ?"];
$params = [$fecha_desde, $fecha_hasta];

if ($filtro_estado > 0) {
    $where[] = "p.estado_id = ?";
    $params[] = $filtro_estado;
}
if ($filtro_entrega === 'delivery' || $filtro_entrega === 'recoger') {
    $where[] = "p.tipo_entrega = ?";
    $params[] = $filtro_entrega;
}
if ($filtro_metodo_pago > 0) {
    $where[] = "p.metodo_pago_id = ?";
    $params[] = $filtro_metodo_pago;
}
if ($filtro_estado_pago > 0) {
    $where[] = "p.estado_pago_id = ?";
    $params[] = $filtro_estado_pago;
}
if ($filtro_producto > 0) {
    $where[] = "EXISTS (SELECT 1 FROM detalle_pedido dp_f WHERE dp_f.pedido_id = p.id AND dp_f.producto_id = ?)";
    $params[] = $filtro_producto;
}
if ($filtro_cliente > 0) {
    $where[] = "p.usuario_id = ?";
    $params[] = $filtro_cliente;
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

if ($filtro_producto > 0) {
    $id_prod_seguro = intval($filtro_producto);
    $sql_monto_pedido = "(
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

$stmt = $pdo->prepare("
    SELECT p.id, p.fecha_pedido, p.tipo_entrega, p.total_pedido,
           ($sql_monto_pedido) AS monto_computado,
           COALESCE(c.nombres, 'Cliente') AS nombres,
           COALESCE(c.apellidos, CONCAT('#', p.usuario_id)) AS apellidos,
           COALESCE(c.telefono, '-') AS telefono,
           e.id AS estado_id, e.nombre AS estado_nombre,
           mp.nombre AS metodo_pago_nombre,
           COALESCE(ep.nombre, 'Pendiente') AS estado_pago_nombre
    FROM pedidos p
    LEFT JOIN clientes c ON p.usuario_id = c.usuario_id
    JOIN estados_pedido e ON p.estado_id = e.id
    JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
    LEFT JOIN estados_pago ep ON p.estado_pago_id = ep.id
    $where_sql
    ORDER BY p.fecha_pedido DESC, p.id DESC
");
$stmt->execute($params);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = 'reporte_pedidos_' . $fecha_desde . '_al_' . $fecha_hasta . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// BOM UTF-8 para que Excel abra acentos y ñ correctamente
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, [$nombre_negocio_csv . ' - REPORTE DE PEDIDOS'], ';');
fputcsv($out, ['Periodo:', $fecha_desde . ' al ' . $fecha_hasta, 'Generado:', date('d/m/Y H:i')], ';');
fputcsv($out, [], ';');

fputcsv($out, [
    'ID Pedido',
    'Fecha y Hora',
    'Cliente',
    'Telefono',
    'Tipo Entrega',
    'Metodo de Pago',
    'Estado Pago',
    'Estado Pedido',
    $filtro_producto > 0 ? 'Subtotal Producto Filtrado ($)' : 'Total Pedido ($)',
    'Computa en Ingresos'
], ';');

$suma_valida = 0.0;
$pedidos_validos = 0;

foreach ($pedidos as $p) {
    $es_cancelado = intval($p['estado_id']) === 6;
    $monto = round(floatval($p['monto_computado']), 2);

    if (!$es_cancelado || $filtro_estado === 6) {
        $suma_valida += $monto;
        $pedidos_validos++;
    }

    fputcsv($out, [
        '#' . $p['id'],
        date('d/m/Y H:i', strtotime($p['fecha_pedido'])),
        trim($p['nombres'] . ' ' . $p['apellidos']),
        $p['telefono'],
        ucfirst($p['tipo_entrega']),
        ucfirst($p['metodo_pago_nombre']),
        ucfirst($p['estado_pago_nombre']),
        ucfirst($p['estado_nombre']),
        number_format($monto, 2, '.', ''),
        $es_cancelado ? 'NO (Cancelado)' : 'SI'
    ], ';');
}

fputcsv($out, [], ';');
fputcsv($out, ['RESUMEN CONTABLE'], ';');
fputcsv($out, ['Pedidos Validos Computados', $pedidos_validos], ';');
fputcsv($out, ['Total Facturado Valido ($)', number_format($suma_valida, 2, '.', '')], ';');

fclose($out);
exit;