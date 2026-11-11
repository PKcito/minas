<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';

if (!esAdmin()) { redirect('index.php'); }
requierePermiso('pedidos');

$mensaje = $_SESSION['flash_mensaje'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_mensaje'], $_SESSION['flash_error']);

// ============================================================
// FILTROS COMPLETOS
// ============================================================
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$filtro_estado = intval($_GET['estado'] ?? 0);
$filtro_entrega = $_GET['entrega'] ?? '';
$filtro_metodo_pago = intval($_GET['metodo_pago'] ?? 0);
$filtro_estado_pago = intval($_GET['estado_pago'] ?? 0);
$filtro_producto = intval($_GET['producto'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) $fecha_desde = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) $fecha_hasta = date('Y-m-d');

// ============================================================
// CONSTRUCCIÓN DEL WHERE
// ============================================================
$where = ["DATE(p.fecha_pedido) BETWEEN ? AND ?"];
$params = [$fecha_desde, $fecha_hasta];

if ($filtro_estado > 0) { $where[] = "p.estado_id = ?"; $params[] = $filtro_estado; }
if ($filtro_entrega === 'delivery' || $filtro_entrega === 'recoger') { $where[] = "p.tipo_entrega = ?"; $params[] = $filtro_entrega; }
if ($filtro_metodo_pago > 0) { $where[] = "p.metodo_pago_id = ?"; $params[] = $filtro_metodo_pago; }
if ($filtro_estado_pago > 0) { $where[] = "p.estado_pago_id = ?"; $params[] = $filtro_estado_pago; }
if ($filtro_producto > 0) { $where[] = "EXISTS (SELECT 1 FROM detalle_pedido dp WHERE dp.pedido_id = p.id AND dp.producto_id = ?)"; $params[] = $filtro_producto; }

$where_sql = 'WHERE ' . implode(' AND ', $where);

// Filtro para VENTAS Y ESTADÍSTICAS:
// Si el admin NO filtró por un estado en particular ($filtro_estado == 0), medimos solo ventas reales (Entregados = 5).
// Si el admin SÍ eligió un estado en el filtro (ej. Pendiente = 1 o En preparación = 3), respetamos ese filtro sin chocar con estado_id = 5.
$where_kpi = $where;
$params_kpi = $params;
if ($filtro_estado == 0) {
    $where_kpi[] = "p.estado_id = 5";
}
$where_sql_kpi = 'WHERE ' . implode(' AND ', $where_kpi);

// Filtro para PEDIDOS ACTIVOS (En curso)
$where_activos = $where;
$params_activos = $params;
$where_activos[] = "p.estado_id != 6 AND p.estado_id != 5";
$where_sql_activos = 'WHERE ' . implode(' AND ', $where_activos);

$hay_filtros = ($filtro_estado > 0 || $filtro_entrega || $filtro_metodo_pago > 0 || $filtro_estado_pago > 0 || $filtro_producto > 0 || $fecha_desde !== date('Y-m-d', strtotime('-30 days')) || $fecha_hasta !== date('Y-m-d'));

// ============================================================
// KPIs
// ============================================================
$stmt = $pdo->prepare("SELECT COALESCE(SUM(p.total_pedido), 0) AS total, COUNT(*) AS pedidos FROM pedidos p JOIN usuarios u ON p.usuario_id = u.id $where_sql_kpi");
$stmt->execute($params_kpi);
$kpi_ventas = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) AS pedidos FROM pedidos p JOIN usuarios u ON p.usuario_id = u.id $where_sql_activos");
$stmt->execute($params_activos);
$kpi_activos = $stmt->fetch();

$ticket_promedio = $kpi_ventas['pedidos'] > 0 ? $kpi_ventas['total'] / $kpi_ventas['pedidos'] : 0;
$dias_rango = (strtotime($fecha_hasta) - strtotime($fecha_desde)) / 86400 + 1;
if ($dias_rango < 1) $dias_rango = 1;
$promedio_diario = $kpi_ventas['total'] / $dias_rango;

// ============================================================
// GRÁFICOS (Datos para JS)
// ============================================================
// 1. Estados (Con mapeo exacto de color por ID de estado)
$sql_estado = "SELECT e.id, e.nombre, COUNT(p.id) AS total FROM estados_pedido e LEFT JOIN pedidos p ON p.estado_id = e.id AND DATE(p.fecha_pedido) BETWEEN ? AND ?";
$params_estado = [$fecha_desde, $fecha_hasta];
if ($filtro_estado > 0) { $sql_estado .= " AND p.estado_id = ?"; $params_estado[] = $filtro_estado; }
if ($filtro_entrega) { $sql_estado .= " AND p.tipo_entrega = ?"; $params_estado[] = $filtro_entrega; }
if ($filtro_metodo_pago > 0) { $sql_estado .= " AND p.metodo_pago_id = ?"; $params_estado[] = $filtro_metodo_pago; }
if ($filtro_estado_pago > 0) { $sql_estado .= " AND p.estado_pago_id = ?"; $params_estado[] = $filtro_estado_pago; }
if ($filtro_producto > 0) { $sql_estado .= " AND EXISTS (SELECT 1 FROM detalle_pedido dp WHERE dp.pedido_id = p.id AND dp.producto_id = ?)"; $params_estado[] = $filtro_producto; }
$sql_estado .= " GROUP BY e.id, e.nombre ORDER BY e.id";
$stmt = $pdo->prepare($sql_estado);
$stmt->execute($params_estado);
$por_estado_raw = $stmt->fetchAll();

$colores_estados = [1=>'#ffc107', 2=>'#17a2b8', 3=>'#007bff', 4=>'#28a745', 5=>'#6c757d', 6=>'#dc3545', 7=>'#9c27b0'];
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

// 2. Ventas por Día
$stmt = $pdo->prepare("SELECT DATE(p.fecha_pedido) AS dia, COALESCE(SUM(p.total_pedido), 0) AS total FROM pedidos p JOIN usuarios u ON p.usuario_id = u.id $where_sql_kpi GROUP BY DATE(p.fecha_pedido) ORDER BY dia ASC");
$stmt->execute($params_kpi);
$raw_ventas_dia = $stmt->fetchAll();
$ventas_por_dia = [];
foreach ($raw_ventas_dia as $r) { $ventas_por_dia[$r['dia']] = floatval($r['total']); }

$labels_ventas = [];
$data_ventas = [];
if ($dias_rango > 90) {
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(p.fecha_pedido, '%Y-%m') AS mes, COALESCE(SUM(p.total_pedido), 0) AS total FROM pedidos p JOIN usuarios u ON p.usuario_id = u.id $where_sql_kpi GROUP BY DATE_FORMAT(p.fecha_pedido, '%Y-%m') ORDER BY mes ASC");
    $stmt->execute($params_kpi);
    foreach ($stmt->fetchAll() as $r) {
        $labels_ventas[] = date('m/Y', strtotime($r['mes'] . '-01'));
        $data_ventas[] = floatval($r['total']);
    }
} else {
    $cursor = strtotime($fecha_desde);
    $fin = strtotime($fecha_hasta);
    while ($cursor <= $fin) {
        $f = date('Y-m-d', $cursor);
        $labels_ventas[] = date('d/m', $cursor);
        $data_ventas[] = $ventas_por_dia[$f] ?? 0;
        $cursor = strtotime('+1 day', $cursor);
    }
}

// 3. Métodos de Pago
$stmt = $pdo->prepare("SELECT mp.nombre, COUNT(p.id) AS total FROM pedidos p JOIN metodos_pago mp ON p.metodo_pago_id = mp.id JOIN usuarios u ON p.usuario_id = u.id $where_sql_kpi GROUP BY mp.id, mp.nombre ORDER BY total DESC");
$stmt->execute($params_kpi);
$por_metodo = $stmt->fetchAll();
$metodos_nombres = [];
$metodos_totales = [];
foreach ($por_metodo as $m) {
    $metodos_nombres[] = ucfirst($m['nombre']);
    $metodos_totales[] = intval($m['total']);
}

// 4. Delivery vs Recoger
$stmt = $pdo->prepare("SELECT p.tipo_entrega, COUNT(*) AS total FROM pedidos p JOIN usuarios u ON p.usuario_id = u.id $where_sql_kpi GROUP BY p.tipo_entrega");
$stmt->execute($params_kpi);
$por_entrega_raw = $stmt->fetchAll();
$por_entrega = ['delivery' => 0, 'recoger' => 0];
foreach ($por_entrega_raw as $r) {
    $por_entrega[$r['tipo_entrega']] = intval($r['total']);
}

// 5. Ventas por Día de la Semana
$stmt = $pdo->prepare("SELECT DAYOFWEEK(p.fecha_pedido) AS dia_num, COALESCE(SUM(p.total_pedido), 0) AS total FROM pedidos p JOIN usuarios u ON p.usuario_id = u.id $where_sql_kpi GROUP BY DAYOFWEEK(p.fecha_pedido) ORDER BY dia_num");
$stmt->execute($params_kpi);
$raw_dias = $stmt->fetchAll();
$dias_semana = [1=>'Dom', 2=>'Lun', 3=>'Mar', 4=>'Mié', 5=>'Jue', 6=>'Vie', 7=>'Sáb'];
$labels_dias = [];
$data_dias = [];
foreach ($dias_semana as $num => $nombre) {
    $labels_dias[] = $nombre;
    $encontrado = false;
    foreach ($raw_dias as $r) {
        if ($r['dia_num'] == $num) {
            $data_dias[] = floatval($r['total']);
            $encontrado = true;
            break;
        }
    }
    if (!$encontrado) $data_dias[] = 0;
}

// ============================================================
// LISTAS VISUALES (HTML)
// ============================================================
// 6. Ingresos por Categoría (Incluyendo la suma de los extras de cada producto)
$stmt = $pdo->prepare("
    SELECT c.nombre, 
           COALESCE(SUM(dp.cantidad * (dp.precio_unitario_historico + COALESCE((SELECT SUM(dpe.precio_extra) FROM detalle_pedido_extras dpe WHERE dpe.detalle_pedido_id = dp.id), 0))), 0) AS total_dinero 
    FROM detalle_pedido dp 
    JOIN productos pr ON dp.producto_id = pr.id 
    JOIN categorias c ON pr.categoria_id = c.id 
    JOIN pedidos p ON dp.pedido_id = p.id 
    JOIN usuarios u ON p.usuario_id = u.id 
    $where_sql_kpi 
    GROUP BY c.id, c.nombre 
    ORDER BY total_dinero DESC
");
$stmt->execute($params_kpi);
$por_categoria = $stmt->fetchAll();
$max_categoria = (count($por_categoria) > 0 && floatval($por_categoria[0]['total_dinero']) > 0) ? floatval($por_categoria[0]['total_dinero']) : 1;

// 7. Top 5 Productos
$stmt = $pdo->prepare("SELECT pr.nombre, pr.imagen_icono, SUM(dp.cantidad) AS total_vendidos FROM detalle_pedido dp JOIN productos pr ON dp.producto_id = pr.id JOIN pedidos p ON dp.pedido_id = p.id JOIN usuarios u ON p.usuario_id = u.id $where_sql_kpi GROUP BY pr.id, pr.nombre, pr.imagen_icono ORDER BY total_vendidos DESC LIMIT 5");
$stmt->execute($params_kpi);
$top_productos = $stmt->fetchAll();
$max_producto = (count($top_productos) > 0 && intval($top_productos[0]['total_vendidos']) > 0) ? intval($top_productos[0]['total_vendidos']) : 1;

// 8. Top 5 Clientes
$stmt = $pdo->prepare("SELECT c.nombres, c.apellidos, COALESCE(SUM(p.total_pedido), 0) AS total FROM pedidos p JOIN usuarios u ON p.usuario_id = u.id JOIN clientes c ON u.id = c.usuario_id $where_sql_kpi GROUP BY c.id, c.nombres, c.apellidos ORDER BY total DESC LIMIT 5");
$stmt->execute($params_kpi);
$top_clientes = $stmt->fetchAll();
$max_cliente = (count($top_clientes) > 0 && floatval($top_clientes[0]['total']) > 0) ? floatval($top_clientes[0]['total']) : 1;

// 9. Pedidos Recientes
$stmt = $pdo->prepare("SELECT p.id, p.fecha_pedido, p.total_pedido, c.nombres, c.apellidos, e.nombre AS estado_nombre, e.id AS estado_id FROM pedidos p JOIN usuarios u ON p.usuario_id = u.id JOIN clientes c ON u.id = c.usuario_id JOIN estados_pedido e ON p.estado_id = e.id $where_sql ORDER BY p.fecha_pedido DESC LIMIT 5");
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
                <option value="0">Todos</option>
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

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon ventas"><i class="fas fa-dollar-sign"></i></div>
            <div class="kpi-info">
                <div class="label"><?= $filtro_estado > 0 ? 'Total Filtrado' : 'Ventas Reales (Entregados)' ?></div>
                <div class="valor">$<?= number_format($kpi_ventas['total'], 2) ?></div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon pedidos"><i class="fas fa-shopping-bag"></i></div>
            <div class="kpi-info">
                <div class="label">Pedidos Entregados / Activos</div>
                <div class="valor"><?= number_format($kpi_ventas['pedidos']) ?> <small style="font-size: 0.9rem; color: var(--texto-secundario); font-weight: 600;">(<?= number_format($kpi_activos['pedidos']) ?> activos)</small></div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon ticket"><i class="fas fa-receipt"></i></div>
            <div class="kpi-info">
                <div class="label">Ticket Promedio</div>
                <div class="valor">$<?= number_format($ticket_promedio, 2) ?></div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon promedio"><i class="fas fa-calendar-day"></i></div>
            <div class="kpi-info">
                <div class="label">Promedio Diario (<?= intval($dias_rango) ?> días)</div>
                <div class="valor">$<?= number_format($promedio_diario, 2) ?></div>
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
        <!-- 1. Evolución de Ventas (Ancho completo) -->
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

        <!-- 6. Ingresos por Categoría (Con extras incluidos) -->
        <div class="grafico-card">
            <h3><i class="fas fa-tags"></i> Ingresos por Categoría</h3>
            <?php if (empty($por_categoria)): ?>
                <div class="sin-datos">Sin datos en este período.</div>
            <?php else: ?>
                <div class="lista-progreso">
                    <?php foreach ($por_categoria as $cat): ?>
                        <?php $pct = min(100, round((floatval($cat['total_dinero']) / $max_categoria) * 100)); ?>
                        <div class="progreso-item">
                            <div class="progreso-icono cat"><i class="fas fa-utensils"></i></div>
                            <div class="progreso-info">
                                <div class="progreso-header">
                                    <span><?= htmlspecialchars($cat['nombre']) ?></span>
                                    <span>$<?= number_format($cat['total_dinero'], 2) ?></span>
                                </div>
                                <div class="progreso-barra-bg">
                                    <div class="progreso-barra-fill cat" style="width: <?= $pct ?>%;"></div>
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
                                    <span><?= intval($prod['total_vendidos']) ?> uds.</span>
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
                                    <span><?= htmlspecialchars($cli['nombres'] . ' ' . $cli['apellidos']) ?></span>
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
                                    <td><?= htmlspecialchars($rec['nombres'] . ' ' . $rec['apellidos']) ?></td>
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