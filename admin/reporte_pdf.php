<?php
require_once __DIR__ . '/../config.php';

if (!esAdmin()) {
    redirect('index.php');
}
if (!tienePermiso('reportes') && !tienePermiso('pedidos')) {
    requierePermiso('reportes');
}

require_once __DIR__ . '/../dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Soportar tanto envío por formulario (POST desde reportes.php) como por enlace (GET desde dashboard.php)
$tipo_reporte    = $_POST['tipo_reporte'] ?? $_GET['tipo_reporte'] ?? 'general';
$fecha_desde     = $_POST['fecha_desde'] ?? $_GET['fecha_desde'] ?? '';
$fecha_hasta     = $_POST['fecha_hasta'] ?? $_GET['fecha_hasta'] ?? '';
$filtro_estado   = intval($_POST['estado'] ?? $_GET['estado'] ?? 0);
$filtro_entrega  = $_POST['entrega'] ?? $_GET['entrega'] ?? '';
$filtro_metodo   = intval($_POST['metodo_pago'] ?? $_GET['metodo_pago'] ?? 0);
$filtro_est_pago = intval($_POST['estado_pago'] ?? $_GET['estado_pago'] ?? 0);
$filtro_cliente  = intval($_POST['cliente_id'] ?? $_GET['cliente_id'] ?? 0);

$where = [];
$params = [];
if ($fecha_desde !== '') { $where[] = "DATE(p.fecha_pedido) >= ?"; $params[] = $fecha_desde; }
if ($fecha_hasta !== '') { $where[] = "DATE(p.fecha_pedido) <= ?"; $params[] = $fecha_hasta; }
if ($filtro_estado > 0) { $where[] = "p.estado_id = ?"; $params[] = $filtro_estado; }
if ($filtro_entrega === 'delivery' || $filtro_entrega === 'recoger') { $where[] = "p.tipo_entrega = ?"; $params[] = $filtro_entrega; }
if ($filtro_metodo > 0) { $where[] = "p.metodo_pago_id = ?"; $params[] = $filtro_metodo; }
if ($filtro_est_pago > 0) { $where[] = "p.estado_pago_id = ?"; $params[] = $filtro_est_pago; }
if ($filtro_cliente > 0) { $where[] = "c.id = ?"; $params[] = $filtro_cliente; }

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Condición adicional para reportes financieros: excluir cancelados (6) si no se filtró específicamente por estado 6
$where_ventas = $where;
if ($filtro_estado == 0) {
    $where_ventas[] = "p.estado_id != 6";
}
$where_ventas_sql = !empty($where_ventas) ? 'WHERE ' . implode(' AND ', $where_ventas) : '';

$titulo = 'Reporte';
$columnas = [];
$filas = [];
$resumen_html = '';
$grafico_titulo = '';
$grafico_label_col = '';
$grafico_valor_col = '';
$filas_grafico = null;

if ($tipo_reporte === 'general') {
    $titulo = 'Reporte general de pedidos';
    $stmt = $pdo->prepare("
        SELECT p.id, p.fecha_pedido, p.total_pedido, p.estado_id, p.tipo_entrega,
               c.nombres, c.apellidos, e.nombre AS estado_nombre,
               mp.nombre AS metodo_pago_nombre, ep.nombre AS estado_pago_nombre
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
    $filas = $stmt->fetchAll();
    $columnas = ['ID', 'Fecha', 'Cliente', 'Entrega', 'Método', 'Estado pago', 'Estado', 'Total'];

    $cantidad = count($filas);
    $total_facturado = 0;
    $pedidos_validos = 0;
    foreach ($filas as $f) {
        if (intval($f['estado_id']) !== 6) {
            $total_facturado += floatval($f['total_pedido']);
            $pedidos_validos++;
        }
    }
    $promedio = $pedidos_validos > 0 ? $total_facturado / $pedidos_validos : 0;
    $resumen_html = "<p><strong>Total de pedidos listados:</strong> " . number_format($cantidad) . "</p>"
                  . "<p><strong>Total facturado (sin cancelados):</strong> $" . number_format($total_facturado, 2) . "</p>"
                  . "<p><strong>Ticket promedio:</strong> $" . number_format($promedio, 2) . "</p>";

} elseif ($tipo_reporte === 'por_producto') {
    $titulo = 'Ventas por producto';
    $stmt = $pdo->prepare("
        SELECT 
            pr.nombre AS producto, 
            SUM(dp.cantidad) AS unidades, 
            SUM(dp.cantidad * dp.precio_unitario_historico) + COALESCE(SUM(ext.total_extras * dp.cantidad), 0) AS total
        FROM detalle_pedido dp
        JOIN productos pr ON dp.producto_id = pr.id
        JOIN pedidos p ON dp.pedido_id = p.id
        JOIN usuarios u ON p.usuario_id = u.id
        JOIN clientes c ON u.id = c.usuario_id
        LEFT JOIN (
            SELECT detalle_pedido_id, SUM(precio_extra) AS total_extras
            FROM detalle_pedido_extras
            GROUP BY detalle_pedido_id
        ) ext ON ext.detalle_pedido_id = dp.id
        $where_ventas_sql
        GROUP BY pr.id, pr.nombre
        ORDER BY unidades DESC
    ");
    $stmt->execute($params);
    $filas = $stmt->fetchAll();
    $columnas = ['Producto', 'Unidades vendidas', 'Total ($)'];
    $total = array_sum(array_column($filas, 'total'));
    $resumen_html = "<p><strong>Productos distintos:</strong> " . count($filas) . "</p><p><strong>Total facturado (con extras):</strong> $" . number_format($total, 2) . "</p>";
    $grafico_titulo = 'Unidades vendidas por producto';
    $grafico_label_col = 'producto';
    $grafico_valor_col = 'unidades';

} elseif ($tipo_reporte === 'por_categoria') {
    $titulo = 'Ventas por categoría';
    $stmt = $pdo->prepare("
        SELECT 
            cat.nombre AS categoria, 
            SUM(dp.cantidad) AS unidades, 
            SUM(dp.cantidad * dp.precio_unitario_historico) + COALESCE(SUM(ext.total_extras * dp.cantidad), 0) AS total
        FROM detalle_pedido dp
        JOIN productos pr ON dp.producto_id = pr.id
        JOIN categorias cat ON pr.categoria_id = cat.id
        JOIN pedidos p ON dp.pedido_id = p.id
        JOIN usuarios u ON p.usuario_id = u.id
        JOIN clientes c ON u.id = c.usuario_id
        LEFT JOIN (
            SELECT detalle_pedido_id, SUM(precio_extra) AS total_extras
            FROM detalle_pedido_extras
            GROUP BY detalle_pedido_id
        ) ext ON ext.detalle_pedido_id = dp.id
        $where_ventas_sql
        GROUP BY cat.id, cat.nombre
        ORDER BY total DESC
    ");
    $stmt->execute($params);
    $filas = $stmt->fetchAll();
    $columnas = ['Categoría', 'Unidades', 'Total ($)'];
    $total = array_sum(array_column($filas, 'total'));
    $resumen_html = "<p><strong>Categorías:</strong> " . count($filas) . "</p><p><strong>Total (con extras):</strong> $" . number_format($total, 2) . "</p>";
    $grafico_titulo = 'Ingresos por categoría';
    $grafico_label_col = 'categoria';
    $grafico_valor_col = 'total';

} elseif ($tipo_reporte === 'por_metodo_pago') {
    $titulo = 'Ventas por método de pago';
    $stmt = $pdo->prepare("
        SELECT mp.nombre AS metodo, COUNT(p.id) AS pedidos, COALESCE(SUM(p.total_pedido), 0) AS total
        FROM pedidos p
        JOIN usuarios u ON p.usuario_id = u.id
        JOIN clientes c ON u.id = c.usuario_id
        JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
        $where_ventas_sql
        GROUP BY mp.id, mp.nombre
        ORDER BY total DESC
    ");
    $stmt->execute($params);
    $filas = $stmt->fetchAll();
    $columnas = ['Método de pago', 'Pedidos', 'Total ($)'];
    $total = array_sum(array_column($filas, 'total'));
    $resumen_html = "<p><strong>Total facturado:</strong> $" . number_format($total, 2) . "</p>";
    $grafico_titulo = 'Ingresos por método de pago';
    $grafico_label_col = 'metodo';
    $grafico_valor_col = 'total';

} elseif ($tipo_reporte === 'por_entrega') {
    $titulo = 'Delivery vs Recoger';
    $stmt = $pdo->prepare("
        SELECT p.tipo_entrega AS tipo, COUNT(p.id) AS pedidos, COALESCE(SUM(p.total_pedido), 0) AS total
        FROM pedidos p
        JOIN usuarios u ON p.usuario_id = u.id
        JOIN clientes c ON u.id = c.usuario_id
        $where_ventas_sql
        GROUP BY p.tipo_entrega
        ORDER BY total DESC
    ");
    $stmt->execute($params);
    $filas = $stmt->fetchAll();
    $columnas = ['Tipo de entrega', 'Pedidos', 'Total ($)'];
    $total = array_sum(array_column($filas, 'total'));
    $resumen_html = "<p><strong>Total facturado:</strong> $" . number_format($total, 2) . "</p>";
    $grafico_titulo = 'Pedidos por tipo de entrega';
    $grafico_label_col = 'tipo';
    $grafico_valor_col = 'pedidos';

} elseif ($tipo_reporte === 'por_cliente') {
    $titulo = 'Pedidos por cliente';
    $stmt = $pdo->prepare("
        SELECT c.nombres, c.apellidos, c.telefono, COUNT(p.id) AS pedidos, COALESCE(SUM(p.total_pedido), 0) AS total
        FROM pedidos p
        JOIN usuarios u ON p.usuario_id = u.id
        JOIN clientes c ON u.id = c.usuario_id
        $where_ventas_sql
        GROUP BY c.id, c.nombres, c.apellidos, c.telefono
        ORDER BY total DESC
    ");
    $stmt->execute($params);
    $filas = $stmt->fetchAll();
    foreach ($filas as &$f) {
        $f['cliente'] = trim($f['nombres'] . ' ' . $f['apellidos']);
    }
    unset($f);
    $columnas = ['Cliente', 'Teléfono', 'Pedidos', 'Total ($)'];
    $total = array_sum(array_column($filas, 'total'));
    $resumen_html = "<p><strong>Clientes activos:</strong> " . count($filas) . "</p><p><strong>Total facturado:</strong> $" . number_format($total, 2) . "</p>";
    $grafico_titulo = 'Top 10 clientes por facturación';
    $grafico_label_col = 'cliente';
    $grafico_valor_col = 'total';
    if (count($filas) > 10) {
        $filas_grafico = array_slice($filas, 0, 10);
    }

} elseif ($tipo_reporte === 'por_estado') {
    $titulo = 'Pedidos por estado';
    $stmt = $pdo->prepare("
        SELECT e.nombre AS estado, COUNT(p.id) AS pedidos, COALESCE(SUM(p.total_pedido), 0) AS total
        FROM pedidos p
        JOIN usuarios u ON p.usuario_id = u.id
        JOIN clientes c ON u.id = c.usuario_id
        JOIN estados_pedido e ON p.estado_id = e.id
        $where_sql
        GROUP BY e.id, e.nombre
        ORDER BY e.id
    ");
    $stmt->execute($params);
    $filas = $stmt->fetchAll();
    $columnas = ['Estado', 'Pedidos', 'Total ($)'];
    $total = array_sum(array_column($filas, 'total'));
    $resumen_html = "<p><strong>Total en listado:</strong> $" . number_format($total, 2) . "</p>";
    $grafico_titulo = 'Pedidos por estado';
    $grafico_label_col = 'estado';
    $grafico_valor_col = 'pedidos';
}

$filtros_aplicados = [];
if ($fecha_desde) $filtros_aplicados[] = "Desde: " . date('d/m/Y', strtotime($fecha_desde));
if ($fecha_hasta) $filtros_aplicados[] = "Hasta: " . date('d/m/Y', strtotime($fecha_hasta));
if ($filtro_estado > 0) { $s = $pdo->prepare("SELECT nombre FROM estados_pedido WHERE id=?"); $s->execute([$filtro_estado]); $filtros_aplicados[] = "Estado: " . ucfirst($s->fetchColumn()); }
if ($filtro_entrega) $filtros_aplicados[] = "Entrega: " . ucfirst($filtro_entrega);
if ($filtro_metodo > 0) { $s = $pdo->prepare("SELECT nombre FROM metodos_pago WHERE id=?"); $s->execute([$filtro_metodo]); $filtros_aplicados[] = "Método: " . $s->fetchColumn(); }
if ($filtro_est_pago > 0) { $s = $pdo->prepare("SELECT nombre FROM estados_pago WHERE id=?"); $s->execute([$filtro_est_pago]); $filtros_aplicados[] = "Estado pago: " . ucfirst($s->fetchColumn()); }
if ($filtro_cliente > 0) { $s = $pdo->prepare("SELECT nombres, apellidos FROM clientes WHERE id=?"); $s->execute([$filtro_cliente]); $cl = $s->fetch(); if ($cl) $filtros_aplicados[] = "Cliente: " . $cl['nombres'] . ' ' . $cl['apellidos']; }

$filtros_txt = empty($filtros_aplicados) ? 'Ninguno' : implode(' | ', $filtros_aplicados);
$fecha_gen = date('d/m/Y H:i');

$grafico_html = '';
if ($grafico_titulo && !empty($filas)) {
    $datos_grafico = $filas_grafico ?: $filas;
    $max_valor = 0;
    foreach ($datos_grafico as $f) {
        $val = floatval($f[$grafico_valor_col] ?? 0);
        if ($val > $max_valor) $max_valor = $val;
    }
    $es_moneda = ($grafico_valor_col === 'total');

    if ($max_valor > 0) {
        $grafico_html = '<div class="grafico-bloque"><div class="grafico-titulo">' . htmlspecialchars($grafico_titulo) . '</div>';
        foreach ($datos_grafico as $f) {
            $label = htmlspecialchars(ucfirst($f[$grafico_label_col] ?? '—'));
            $valor = floatval($f[$grafico_valor_col] ?? 0);
            $ancho_pct = max(2, round(($valor / $max_valor) * 100));
            $valor_fmt = $es_moneda ? '$' . number_format($valor, 2) : number_format($valor);
            $grafico_html .= '
                <div class="barra-fila">
                    <div class="barra-label">' . $label . '</div>
                    <div class="barra-contenedor">
                        <div class="barra-relleno" style="width: ' . $ancho_pct . '%;"></div>
                    </div>
                    <div class="barra-valor">' . $valor_fmt . '</div>
                </div>';
        }
        $grafico_html .= '</div>';
    }
}

ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($titulo) ?> - Minas Burguer</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; margin: 0; padding: 20px; }
        .header { border-bottom: 2px solid #d32f2f; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { color: #d32f2f; margin: 0; font-size: 20px; }
        .header .sub { color: #666; font-size: 10px; margin-top: 4px; }
        .filtros { background: #f8f9fa; padding: 8px 12px; border-left: 3px solid #d32f2f; margin-bottom: 15px; font-size: 10px; }
        .resumen { background: #fff3cd; padding: 10px 12px; border-radius: 4px; margin-bottom: 15px; }
        .resumen p { margin: 3px 0; }
        .grafico-bloque { margin: 15px 0; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .grafico-titulo { font-weight: bold; margin-bottom: 8px; color: #d32f2f; font-size: 12px; }
        .barra-fila { margin-bottom: 6px; width: 100%; }
        .barra-label { display: inline-block; width: 28%; font-size: 10px; vertical-align: middle; }
        .barra-contenedor { display: inline-block; width: 52%; background: #eee; height: 12px; border-radius: 3px; vertical-align: middle; overflow: hidden; }
        .barra-relleno { background: #d32f2f; height: 12px; }
        .barra-valor { display: inline-block; width: 18%; text-align: right; font-weight: bold; font-size: 10px; vertical-align: middle; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #d32f2f; color: #fff; padding: 7px 6px; text-align: left; font-size: 10px; text-transform: uppercase; }
        td { padding: 6px; border-bottom: 1px solid #ddd; font-size: 10px; }
        tr:nth-child(even) td { background: #fafafa; }
        .footer { margin-top: 25px; text-align: center; font-size: 9px; color: #888; border-top: 1px solid #eee; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>MINAS BURGUER — <?= htmlspecialchars(strtoupper($titulo)) ?></h1>
        <div class="sub">Generado el: <?= $fecha_gen ?></div>
    </div>

    <div class="filtros">
        <strong>Filtros aplicados:</strong> <?= htmlspecialchars($filtros_txt) ?>
    </div>

    <?php if ($resumen_html): ?>
        <div class="resumen"><?= $resumen_html ?></div>
    <?php endif; ?>

    <?= $grafico_html ?>

    <table>
        <thead>
            <tr>
                <?php foreach ($columnas as $col): ?>
                    <th><?= htmlspecialchars($col) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($filas)): ?>
                <tr>
                    <td colspan="<?= count($columnas) ?>" style="text-align: center; padding: 15px;">No se encontraron registros con los filtros seleccionados.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($filas as $f): ?>
                    <tr>
                        <?php if ($tipo_reporte === 'general'): ?>
                            <td>#<?= intval($f['id']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($f['fecha_pedido'])) ?></td>
                            <td><?= htmlspecialchars($f['nombres'] . ' ' . $f['apellidos']) ?></td>
                            <td><?= ucfirst(htmlspecialchars($f['tipo_entrega'])) ?></td>
                            <td><?= htmlspecialchars($f['metodo_pago_nombre']) ?></td>
                            <td><?= ucfirst(htmlspecialchars($f['estado_pago_nombre'])) ?></td>
                            <td><?= ucfirst(htmlspecialchars($f['estado_nombre'])) ?></td>
                            <td><strong>$<?= number_format($f['total_pedido'], 2) ?></strong></td>
                        <?php elseif ($tipo_reporte === 'por_producto'): ?>
                            <td><?= htmlspecialchars($f['producto']) ?></td>
                            <td><?= intval($f['unidades']) ?></td>
                            <td><strong>$<?= number_format($f['total'], 2) ?></strong></td>
                        <?php elseif ($tipo_reporte === 'por_categoria'): ?>
                            <td><?= htmlspecialchars($f['categoria']) ?></td>
                            <td><?= intval($f['unidades']) ?></td>
                            <td><strong>$<?= number_format($f['total'], 2) ?></strong></td>
                        <?php elseif ($tipo_reporte === 'por_metodo_pago'): ?>
                            <td><?= htmlspecialchars(ucfirst($f['metodo'])) ?></td>
                            <td><?= intval($f['pedidos']) ?></td>
                            <td><strong>$<?= number_format($f['total'], 2) ?></strong></td>
                        <?php elseif ($tipo_reporte === 'por_entrega'): ?>
                            <td><?= htmlspecialchars(ucfirst($f['tipo'])) ?></td>
                            <td><?= intval($f['pedidos']) ?></td>
                            <td><strong>$<?= number_format($f['total'], 2) ?></strong></td>
                        <?php elseif ($tipo_reporte === 'por_cliente'): ?>
                            <td><?= htmlspecialchars($f['cliente']) ?></td>
                            <td><?= htmlspecialchars($f['telefono'] ?: '—') ?></td>
                            <td><?= intval($f['pedidos']) ?></td>
                            <td><strong>$<?= number_format($f['total'], 2) ?></strong></td>
                        <?php elseif ($tipo_reporte === 'por_estado'): ?>
                            <td><?= htmlspecialchars(ucfirst($f['estado'])) ?></td>
                            <td><?= intval($f['pedidos']) ?></td>
                            <td><strong>$<?= number_format($f['total'], 2) ?></strong></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        Mina's Burguers — Sistema de Control Administrativo y Gestión de Pedidos
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$nombre_pdf = 'Reporte_' . $tipo_reporte . '_' . date('Y-m-d_H-i') . '.pdf';
$dompdf->stream($nombre_pdf, ['Attachment' => false]);
exit;