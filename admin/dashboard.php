<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';

if (!esAdmin()) {
    redirect('index.php');
}
requierePermiso('pedidos');

$mensaje = $_SESSION['flash_mensaje'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_mensaje'], $_SESSION['flash_error']);

// ============================================================
// 1. RECEPCIÓN Y SANEAMIENTO DE FILTROS
// ============================================================
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$filtro_estado = intval($_GET['estado'] ?? 0);
$filtro_entrega = $_GET['entrega'] ?? '';
$filtro_metodo_pago = intval($_GET['metodo_pago'] ?? 0);
$filtro_estado_pago = intval($_GET['estado_pago'] ?? 0);
$filtro_producto = intval($_GET['producto'] ?? 0);

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

// Cálculo matemático exacto de días en el rango (inclusivo)
$dt_desde = new DateTime($fecha_desde);
$dt_hasta = new DateTime($fecha_hasta);
$dias_rango = max(1, intval($dt_desde->diff($dt_hasta)->days) + 1);

$hay_filtros = (
    $filtro_estado > 0 ||
    $filtro_entrega !== '' ||
    $filtro_metodo_pago > 0 ||
    $filtro_estado_pago > 0 ||
    $filtro_producto > 0 ||
    $fecha_desde !== date('Y-m-d', strtotime('-30 days')) ||
    $fecha_hasta !== date('Y-m-d')
);

// ============================================================
// 2. CONSTRUCCIÓN DE CONDICIONES SQL PREPARADAS
// ============================================================
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

$where_sql = 'WHERE ' . implode(' AND ', $where);

// Universo contable válido: si no se filtró por un estado puntual, excluye Cancelados (estado_id = 6)
$where_kpi = $where;
$params_kpi = $params;
if ($filtro_estado === 0) {
    $where_kpi[] = "p.estado_id != 6";
}
$where_sql_kpi = 'WHERE ' . implode(' AND ', $where_kpi);

// Expresión dinámica de monto:
// - Si se filtra por un producto específico, suma únicamente el ingreso real de ese producto + sus extras en cada pedido.
// - Si es vista general, toma el total_pedido de la orden.
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

// ============================================================
// 3. CÁLCULO DE KPIs GENERALES Y ESPECÍFICOS (UNA SOLA CONSULTA ATÓMICA)
// ============================================================
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM($sql_monto_pedido), 0) AS ingresos_totales,
        COUNT(p.id) AS pedidos_validos,
        COALESCE(SUM(CASE WHEN p.estado_id = 5 THEN $sql_monto_pedido ELSE 0 END), 0) AS ingresos_entregados,
        SUM(CASE WHEN p.estado_id = 5 THEN 1 ELSE 0 END) AS cantidad_entregados,
        COALESCE(SUM(CASE WHEN p.estado_id NOT IN (5, 6) THEN $sql_monto_pedido ELSE 0 END), 0) AS ingresos_en_curso,
        SUM(CASE WHEN p.estado_id NOT IN (5, 6) THEN 1 ELSE 0 END) AS cantidad_en_curso
    FROM pedidos p
    $where_sql_kpi
");
$stmt->execute($params_kpi);
$resumen_kpi = $stmt->fetch();

$total_facturado = round(floatval($resumen_kpi['ingresos_totales'] ?? 0), 2);
$total_pedidos_validos = intval($resumen_kpi['pedidos_validos'] ?? 0);
$ingresos_entregados = round(floatval($resumen_kpi['ingresos_entregados'] ?? 0), 2);
$total_entregados = intval($resumen_kpi['cantidad_entregados'] ?? 0);
$ingresos_en_curso = round(floatval($resumen_kpi['ingresos_en_curso'] ?? 0), 2);
$total_en_curso = intval($resumen_kpi['cantidad_en_curso'] ?? 0);

$ticket_promedio = $total_pedidos_validos > 0 ? round($total_facturado / $total_pedidos_validos, 2) : 0.00;
$promedio_diario = round($total_facturado / $dias_rango, 2);

// ============================================================
// 4. DATOS PARA GRÁFICOS (CHART.JS)
// ============================================================

// 4.1 Pedidos por Estado (respeta todos los filtros activos en la condición ON del LEFT JOIN)
$condiciones_join_estado = ["DATE(p.fecha_pedido) BETWEEN ? AND ?"];
$params_estado = [$fecha_desde, $fecha_hasta];

if ($filtro_estado > 0) {
    $condiciones_join_estado[] = "p.estado_id = ?";
    $params_estado[] = $filtro_estado;
}
if ($filtro_entrega === 'delivery' || $filtro_entrega === 'recoger') {
    $condiciones_join_estado[] = "p.tipo_entrega = ?";
    $params_estado[] = $filtro_entrega;
}
if ($filtro_metodo_pago > 0) {
    $condiciones_join_estado[] = "p.metodo_pago_id = ?";
    $params_estado[] = $filtro_metodo_pago;
}
if ($filtro_estado_pago > 0) {
    $condiciones_join_estado[] = "p.estado_pago_id = ?";
    $params_estado[] = $filtro_estado_pago;
}
if ($filtro_producto > 0) {
    $condiciones_join_estado[] = "EXISTS (SELECT 1 FROM detalle_pedido dp_e WHERE dp_e.pedido_id = p.id AND dp_e.producto_id = ?)";
    $params_estado[] = $filtro_producto;
}

$sql_estado = "
    SELECT e.id, e.nombre, COUNT(p.id) AS total
    FROM estados_pedido e
    LEFT JOIN pedidos p ON p.estado_id = e.id AND " . implode(' AND ', $condiciones_join_estado) . "
    GROUP BY e.id, e.nombre
    ORDER BY e.id
";
$stmt = $pdo->prepare($sql_estado);
$stmt->execute($params_estado);
$por_estado_raw = $stmt->fetchAll();

$colores_estados = [
    1 => '#ffc107', 2 => '#17a2b8', 3 => '#007bff', 4 => '#28a745',
    5 => '#6c757d', 6 => '#dc3545', 7 => '#9c27b0'
];
$estados_labels = [];
$estados_data = [];
$estados_colors = [];

foreach ($por_estado_raw as $pe) {
    if (intval($pe['total']) > 0) {
        $id_est = intval($pe['id']);
        $estados_labels[] = ucfirst($pe['nombre']);
        $estados_data[] = intval($pe['total']);
        $estados_colors[] = $colores_estados[$id_est] ?? '#6c757d';
    }
}

// 4.2 Evolución de Ingresos por Día (o por Mes si el rango supera 90 días)
$labels_ventas = [];
$data_ventas = [];

if ($dias_rango > 90) {
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(p.fecha_pedido, '%Y-%m') AS mes,
               COALESCE(SUM($sql_monto_pedido), 0) AS total
        FROM pedidos p
        $where_sql_kpi
        GROUP BY DATE_FORMAT(p.fecha_pedido, '%Y-%m')
        ORDER BY mes ASC
    ");
    $stmt->execute($params_kpi);
    foreach ($stmt->fetchAll() as $r) {
        $labels_ventas[] = date('m/Y', strtotime($r['mes'] . '-01'));
        $data_ventas[] = round(floatval($r['total']), 2);
    }
} else {
    $stmt = $pdo->prepare("
        SELECT DATE(p.fecha_pedido) AS dia,
               COALESCE(SUM($sql_monto_pedido), 0) AS total
        FROM pedidos p
        $where_sql_kpi
        GROUP BY DATE(p.fecha_pedido)
        ORDER BY dia ASC
    ");
    $stmt->execute($params_kpi);
    $ventas_por_dia = [];
    foreach ($stmt->fetchAll() as $r) {
        $ventas_por_dia[$r['dia']] = round(floatval($r['total']), 2);
    }

    $cursor = new DateTime($fecha_desde);
    $fin = new DateTime($fecha_hasta);
    while ($cursor <= $fin) {
        $f = $cursor->format('Y-m-d');
        $labels_ventas[] = $cursor->format('d/m');
        $data_ventas[] = $ventas_por_dia[$f] ?? 0;
        $cursor->modify('+1 day');
    }
}

// 4.3 Métodos de Pago
$stmt = $pdo->prepare("
    SELECT mp.nombre, COUNT(p.id) AS total
    FROM pedidos p
    JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
    $where_sql_kpi
    GROUP BY mp.id, mp.nombre
    ORDER BY total DESC
");
$stmt->execute($params_kpi);
$metodos_nombres = [];
$metodos_totales = [];
foreach ($stmt->fetchAll() as $m) {
    $metodos_nombres[] = ucfirst($m['nombre']);
    $metodos_totales[] = intval($m['total']);
}

// 4.4 Delivery vs Recoger
$stmt = $pdo->prepare("
    SELECT p.tipo_entrega, COUNT(p.id) AS total
    FROM pedidos p
    $where_sql_kpi
    GROUP BY p.tipo_entrega
");
$stmt->execute($params_kpi);
$por_entrega = ['delivery' => 0, 'recoger' => 0];
foreach ($stmt->fetchAll() as $r) {
    $tipo_key = strtolower(trim($r['tipo_entrega']));
    if (isset($por_entrega[$tipo_key])) {
        $por_entrega[$tipo_key] = intval($r['total']);
    }
}

// 4.5 Ingresos por Día de la Semana
$stmt = $pdo->prepare("
    SELECT DAYOFWEEK(p.fecha_pedido) AS dia_num,
           COALESCE(SUM($sql_monto_pedido), 0) AS total
    FROM pedidos p
    $where_sql_kpi
    GROUP BY DAYOFWEEK(p.fecha_pedido)
    ORDER BY dia_num
");
$stmt->execute($params_kpi);
$mapa_dias = [];
foreach ($stmt->fetchAll() as $r) {
    $mapa_dias[intval($r['dia_num'])] = round(floatval($r['total']), 2);
}

$dias_semana = [1 => 'Dom', 2 => 'Lun', 3 => 'Mar', 4 => 'Mié', 5 => 'Jue', 6 => 'Vie', 7 => 'Sáb'];
$labels_dias = [];
$data_dias = [];
foreach ($dias_semana as $num => $nombre) {
    $labels_dias[] = $nombre;
    $data_dias[] = $mapa_dias[$num] ?? 0;
}

// ============================================================
// 5. LISTAS DETALLADAS (CATEGORÍAS, TOP PRODUCTOS, TOP CLIENTES, RECIENTES)
// ============================================================
$where_detalle_kpi = $where_kpi;
$params_detalle_kpi = $params_kpi;
if ($filtro_producto > 0) {
    $where_detalle_kpi[] = "dp.producto_id = ?";
    $params_detalle_kpi[] = $filtro_producto;
}
$where_sql_detalle = 'WHERE ' . implode(' AND ', $where_detalle_kpi);

// 5.1 Ingresos por Categoría (Precio histórico + suma exacta de extras por unidad)
$stmt = $pdo->prepare("
    SELECT c.nombre,
           SUM(dp.cantidad) AS unidades_categoria,
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
$stmt->execute($params_detalle_kpi);
$por_categoria = $stmt->fetchAll();

$suma_categorias = 0;
foreach ($por_categoria as $cat_item) {
    $suma_categorias += floatval($cat_item['total_dinero']);
}
$max_categoria = (count($por_categoria) > 0 && floatval($por_categoria[0]['total_dinero']) > 0)
    ? floatval($por_categoria[0]['total_dinero'])
    : 1;

// 5.2 Top 5 Productos Más Vendidos (con unidades e ingreso generado)
$stmt = $pdo->prepare("
    SELECT pr.nombre,
           pr.imagen_icono,
           SUM(dp.cantidad) AS total_vendidos,
           COALESCE(SUM(
               dp.cantidad * (
                   dp.precio_unitario_historico +
                   COALESCE(ext.total_extras_unit, 0)
               )
           ), 0) AS ingreso_producto
    FROM detalle_pedido dp
    JOIN productos pr ON dp.producto_id = pr.id
    JOIN pedidos p ON dp.pedido_id = p.id
    LEFT JOIN (
        SELECT detalle_pedido_id, SUM(precio_extra) AS total_extras_unit
        FROM detalle_pedido_extras
        GROUP BY detalle_pedido_id
    ) ext ON ext.detalle_pedido_id = dp.id
    $where_sql_detalle
    GROUP BY pr.id, pr.nombre, pr.imagen_icono
    ORDER BY total_vendidos DESC, ingreso_producto DESC
    LIMIT 5
");
$stmt->execute($params_detalle_kpi);
$top_productos = $stmt->fetchAll();
$max_producto = (count($top_productos) > 0 && intval($top_productos[0]['total_vendidos']) > 0)
    ? intval($top_productos[0]['total_vendidos'])
    : 1;

// 5.3 Top 5 Clientes (Agrupado por usuario para evitar duplicados)
$stmt = $pdo->prepare("
    SELECT COALESCE(c.nombres, 'Cliente') AS nombres,
           COALESCE(c.apellidos, CONCAT('#', p.usuario_id)) AS apellidos,
           COUNT(p.id) AS cantidad_pedidos,
           COALESCE(SUM($sql_monto_pedido), 0) AS total
    FROM pedidos p
    LEFT JOIN clientes c ON p.usuario_id = c.usuario_id
    $where_sql_kpi
    GROUP BY p.usuario_id, c.nombres, c.apellidos
    ORDER BY total DESC
    LIMIT 5
");
$stmt->execute($params_kpi);
$top_clientes = $stmt->fetchAll();
$max_cliente = (count($top_clientes) > 0 && floatval($top_clientes[0]['total']) > 0)
    ? floatval($top_clientes[0]['total'])
    : 1;

// 5.4 Últimos 5 Pedidos Recientes
$stmt = $pdo->prepare("
    SELECT p.id, p.fecha_pedido, p.total_pedido,
           COALESCE(c.nombres, 'Cliente') AS nombres,
           COALESCE(c.apellidos, '') AS apellidos,
           e.nombre AS estado_nombre, e.id AS estado_id
    FROM pedidos p
    LEFT JOIN clientes c ON p.usuario_id = c.usuario_id
    JOIN estados_pedido e ON p.estado_id = e.id
    $where_sql
    ORDER BY p.fecha_pedido DESC, p.id DESC
    LIMIT 5
");
$stmt->execute($params);
$recientes = $stmt->fetchAll();

$estados = $pdo->query("SELECT * FROM estados_pedido ORDER BY id")->fetchAll();
$metodos_pago = $pdo->query("SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY id")->fetchAll();
$estados_pago = $pdo->query("SELECT * FROM estados_pago ORDER BY id")->fetchAll();
$todos_productos = $pdo->query("SELECT id, nombre FROM productos ORDER BY nombre")->fetchAll();

$query_export = http_build_query($_GET);

partial('head', [
    'titulo_pagina' => 'Dashboard',
    'cargar_sweetalert' => true,
    'cargar_chartjs' => true,
    'css_pagina' => 'admin',
    'css_extra' => ['css/dashboard.css']
]);
?>

<?php partial('menu', ['menu_activo' => 'dashboard']); ?>
<?php partial('header', ['titulo_pagina' => 'Dashboard']); ?>

<main class="container" style="padding: 2rem 0;">
    <div class="dashboard-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h2><i class="fas fa-chart-line"></i> Panel de Control</h2>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="<?= url('admin/reporte_pdf.php' . ($query_export ? '?' . $query_export : '')) ?>" target="_blank" class="btn-filtro" style="background: #dc3545; color: #fff;">
                <i class="fas fa-file-pdf"></i> Exportar PDF
            </a>
            <a href="<?= url('admin/exportar_pedidos_csv.php' . ($query_export ? '?' . $query_export : '')) ?>" class="btn-filtro" style="background: #28a745; color: #fff;">
                <i class="fas fa-file-csv"></i> Exportar CSV
            </a>
        </div>
    </div>

    <?php if ($mensaje): ?><div data-flash-success="<?= htmlspecialchars($mensaje) ?>" style="display:none;"></div><?php endif; ?>
    <?php if ($error): ?><div data-flash-error="<?= htmlspecialchars($error) ?>" style="display:none;"></div><?php endif; ?>

    <!-- FILTROS -->
    <form method="get" class="filtros-card">
        <div class="filtro-item">
            <label>Desde</label>
            <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fecha_desde) ?>">
        </div>
        <div class="filtro-item">
            <label>Hasta</label>
            <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fecha_hasta) ?>">
        </div>
        <div class="filtro-item">
            <label>Estado</label>
            <select name="estado">
                <option value="0">Todos (Sin cancelados)</option>
                <?php foreach ($estados as $est): ?>
                    <option value="<?= $est['id'] ?>" <?= $filtro_estado == $est['id'] ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($est['nombre'])) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtro-item">
            <label>Entrega</label>
            <select name="entrega">
                <option value="">Todas</option>
                <option value="delivery" <?= $filtro_entrega === 'delivery' ? 'selected' : '' ?>>Delivery</option>
                <option value="recoger" <?= $filtro_entrega === 'recoger' ? 'selected' : '' ?>>Recoger</option>
            </select>
        </div>
        <div class="filtro-item">
            <label>Método Pago</label>
            <select name="metodo_pago">
                <option value="0">Todos</option>
                <?php foreach ($metodos_pago as $mp): ?>
                    <option value="<?= $mp['id'] ?>" <?= $filtro_metodo_pago == $mp['id'] ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($mp['nombre'])) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtro-item">
            <label>Estado Pago</label>
            <select name="estado_pago">
                <option value="0">Todos</option>
                <?php foreach ($estados_pago as $ep): ?>
                    <option value="<?= $ep['id'] ?>" <?= $filtro_estado_pago == $ep['id'] ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($ep['nombre'])) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtro-item">
            <label>Producto</label>
            <select name="producto">
                <option value="0">Todos</option>
                <?php foreach ($todos_productos as $tp): ?>
                    <option value="<?= $tp['id'] ?>" <?= $filtro_producto == $tp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tp['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtros-acciones">
            <button type="submit" class="btn-filtro btn-filtro-aplicar">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <?php if ($hay_filtros): ?>
                <a href="<?= url('admin/dashboard.php') ?>" class="btn-filtro btn-filtro-limpiar">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- KPIs GENERALES Y ESPECÍFICOS -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon ventas"><i class="fas fa-dollar-sign"></i></div>
            <div class="kpi-info">
                <div class="label"><?= $filtro_producto > 0 ? 'Ingresos del Producto' : ($filtro_estado > 0 ? 'Ingresos Filtrados' : 'Ingresos Válidos') ?></div>
                <div class="valor">$<?= number_format($total_facturado, 2) ?></div>
                <?php if ($filtro_estado === 0): ?>
                    <div style="font-size: 0.78rem; color: var(--texto-secundario); margin-top: 2px;">
                        Entregado: <strong>$<?= number_format($ingresos_entregados, 2) ?></strong> · En curso: <strong>$<?= number_format($ingresos_en_curso, 2) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon pedidos"><i class="fas fa-shopping-bag"></i></div>
            <div class="kpi-info">
                <div class="label">Pedidos Válidos</div>
                <div class="valor"><?= number_format($total_pedidos_validos) ?></div>
                <?php if ($filtro_estado === 0): ?>
                    <div style="font-size: 0.78rem; color: var(--texto-secundario); margin-top: 2px;">
                        <?= number_format($total_entregados) ?> entregados · <?= number_format($total_en_curso) ?> activos
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon ticket"><i class="fas fa-receipt"></i></div>
            <div class="kpi-info">
                <div class="label">Ticket Promedio</div>
                <div class="valor">$<?= number_format($ticket_promedio, 2) ?></div>
                <div style="font-size: 0.78rem; color: var(--texto-secundario); margin-top: 2px;">
                    Por pedido válido
                </div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon promedio"><i class="fas fa-calendar-day"></i></div>
            <div class="kpi-info">
                <div class="label">Promedio Diario (<?= intval($dias_rango) ?> días)</div>
                <div class="valor">$<?= number_format($promedio_diario, 2) ?></div>
                <div style="font-size: 0.78rem; color: var(--texto-secundario); margin-top: 2px;">
                    Del <?= date('d/m', strtotime($fecha_desde)) ?> al <?= date('d/m', strtotime($fecha_hasta)) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ACCESOS RÁPIDOS -->
    <div class="accesos-grid">
        <a href="<?= url('admin/pedidos.php') ?>" class="acceso-card">
            <i class="fas fa-clipboard-list"></i>
            <span>Gestionar Pedidos</span>
        </a>
        <a href="<?= url('admin/productos.php') ?>" class="acceso-card">
            <i class="fas fa-hamburger"></i>
            <span>Productos y Menú</span>
        </a>
        <a href="<?= url('admin/usuarios.php') ?>" class="acceso-card">
            <i class="fas fa-users"></i>
            <span>Clientes y Usuarios</span>
        </a>
        <a href="<?= url('index.php') ?>" class="acceso-card">
            <i class="fas fa-store"></i>
            <span>Ver Tienda Online</span>
        </a>
    </div>

    <!-- GRÁFICOS Y LISTAS -->
    <div class="graficos-grid">
        <!-- 1. Evolución de Ventas -->
        <div class="grafico-card full">
            <h3><i class="fas fa-chart-area"></i> Evolución de Ingresos ($)</h3>
            <div class="grafico-wrapper">
                <canvas id="chartVentas"></canvas>
            </div>
        </div>

        <!-- 2. Pedidos por Estado -->
        <div class="grafico-card">
            <h3><i class="fas fa-tasks"></i> Pedidos por Estado</h3>
            <div class="grafico-wrapper pie">
                <canvas id="chartEstados"></canvas>
            </div>
        </div>

        <!-- 3. Métodos de Pago -->
        <div class="grafico-card">
            <h3><i class="fas fa-wallet"></i> Métodos de Pago</h3>
            <div class="grafico-wrapper pie">
                <canvas id="chartMetodos"></canvas>
            </div>
        </div>

        <!-- 4. Delivery vs Recoger -->
        <div class="grafico-card">
            <h3><i class="fas fa-motorcycle"></i> Tipo de Entrega</h3>
            <div class="grafico-wrapper pie">
                <canvas id="chartEntrega"></canvas>
            </div>
        </div>

        <!-- 5. Ventas por Día de la Semana -->
        <div class="grafico-card half">
            <h3><i class="fas fa-calendar-week"></i> Ingresos por Día de la Semana</h3>
            <div class="grafico-wrapper">
                <canvas id="chartDiasSemana"></canvas>
            </div>
        </div>

        <!-- 6. Ingresos por Categoría (Con extras incluidos y porcentaje real) -->
        <div class="grafico-card">
            <h3><i class="fas fa-tags"></i> Ingresos por Categoría</h3>
            <?php if (empty($por_categoria)): ?>
                <div class="sin-datos">Sin datos en este período.</div>
            <?php else: ?>
                <div class="lista-progreso">
                    <?php foreach ($por_categoria as $cat): ?>
                        <?php
                        $monto_cat = floatval($cat['total_dinero']);
                        $pct_barra = min(100, round(($monto_cat / $max_categoria) * 100));
                        $pct_participacion = $suma_categorias > 0 ? round(($monto_cat / $suma_categorias) * 100, 1) : 0;
                        ?>
                        <div class="progreso-item">
                            <div class="progreso-icono cat"><i class="fas fa-utensils"></i></div>
                            <div class="progreso-info">
                                <div class="progreso-header">
                                    <span><?= htmlspecialchars($cat['nombre']) ?> <small style="color: var(--texto-secundario);"> (<?= intval($cat['unidades_categoria']) ?> uds · <?= $pct_participacion ?>%)</small></span>
                                    <span>$<?= number_format($monto_cat, 2) ?></span>
                                </div>
                                <div class="progreso-barra-bg">
                                    <div class="progreso-barra-fill cat" style="width: <?= $pct_barra ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 7. Top 5 Productos Más Vendidos -->
        <div class="grafico-card">
            <h3><i class="fas fa-fire"></i> Top 5 Productos</h3>
            <?php if (empty($top_productos)): ?>
                <div class="sin-datos">Sin datos en este período.</div>
            <?php else: ?>
                <div class="lista-progreso">
                    <?php foreach ($top_productos as $prod): ?>
                        <?php $pct = min(100, round((intval($prod['total_vendidos']) / $max_producto) * 100)); ?>
                        <div class="progreso-item">
                            <div class="progreso-icono prod">
                                <i class="<?= htmlspecialchars($prod['imagen_icono'] ?? 'fas fa-hamburger') ?>"></i>
                            </div>
                            <div class="progreso-info">
                                <div class="progreso-header">
                                    <span><?= htmlspecialchars($prod['nombre']) ?></span>
                                    <span><?= intval($prod['total_vendidos']) ?> uds. ($<?= number_format($prod['ingreso_producto'], 2) ?>)</span>
                                </div>
                                <div class="progreso-barra-bg">
                                    <div class="progreso-barra-fill prod" style="width: <?= $pct ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 8. Top 5 Clientes -->
        <div class="grafico-card">
            <h3><i class="fas fa-crown"></i> Mejores Clientes</h3>
            <?php if (empty($top_clientes)): ?>
                <div class="sin-datos">Sin datos en este período.</div>
            <?php else: ?>
                <div class="lista-progreso">
                    <?php foreach ($top_clientes as $cli): ?>
                        <?php $pct = min(100, round((floatval($cli['total']) / $max_cliente) * 100)); ?>
                        <div class="progreso-item">
                            <div class="progreso-icono cli"><i class="fas fa-user"></i></div>
                            <div class="progreso-info">
                                <div class="progreso-header">
                                    <span><?= htmlspecialchars(trim($cli['nombres'] . ' ' . $cli['apellidos'])) ?> <small style="color: var(--texto-secundario);"> (<?= intval($cli['cantidad_pedidos']) ?> ped.)</small></span>
                                    <span>$<?= number_format($cli['total'], 2) ?></span>
                                </div>
                                <div class="progreso-barra-bg">
                                    <div class="progreso-barra-fill cli" style="width: <?= $pct ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 9. Últimos 5 Pedidos -->
        <div class="grafico-card">
            <h3><i class="fas fa-clock"></i> Pedidos Recientes</h3>
            <?php if (empty($recientes)): ?>
                <div class="sin-datos">No hay pedidos recientes.</div>
            <?php else: ?>
                <div class="tabla-responsive">
                    <table class="recientes-tabla">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Total</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recientes as $rec): ?>
                                <tr>
                                    <td><a href="<?= url('cliente/ver_detalle.php?id=' . $rec['id']) ?>" style="color: var(--color-acento); font-weight: 700; text-decoration: none;">#<?= $rec['id'] ?></a></td>
                                    <td><?= htmlspecialchars(trim($rec['nombres'] . ' ' . $rec['apellidos'])) ?></td>
                                    <td><strong>$<?= number_format($rec['total_pedido'], 2) ?></strong></td>
                                    <td>
                                        <span class="badge-mini" style="background: <?= $colores_estados[intval($rec['estado_id'])] ?? '#6c757d' ?>;">
                                            <?= htmlspecialchars(ucfirst($rec['estado_nombre'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
window.DASHBOARD_CONFIG = {
    labelsVentas: <?= json_encode($labels_ventas) ?>,
    dataVentas: <?= json_encode($data_ventas) ?>,
    estadosLabels: <?= json_encode($estados_labels) ?>,
    estadosData: <?= json_encode($estados_data) ?>,
    estadosColors: <?= json_encode($estados_colors) ?>,
    metodosNombres: <?= json_encode($metodos_nombres) ?>,
    metodosTotales: <?= json_encode($metodos_totales) ?>,
    entregaDelivery: <?= intval($por_entrega['delivery'] ?? 0) ?>,
    entregaRecoger: <?= intval($por_entrega['recoger'] ?? 0) ?>,
    labelsDias: <?= json_encode($labels_dias) ?>,
    dataDias: <?= json_encode($data_dias) ?>
};
</script>

<?php partial('footer', ['cargar_admin_ux' => true, 'js_extra_footer' => ['js/dashboard.js']]); ?>