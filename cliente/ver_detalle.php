<?php
require_once __DIR__ . '/../config.php';

if (!usuarioLogueado()) { redirect('auth/login.php'); }

$pedido_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$pedido_id) die('ID no valido.');

$usuario_id = $_SESSION['usuario_id'];
$es_admin = esAdmin();

if ($es_admin) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.email, c.nombres, c.apellidos, c.telefono,
               mp.nombre AS metodo_pago_nombre,
               ep.nombre AS estado_pago_nombre
        FROM pedidos p
        JOIN usuarios u ON p.usuario_id = u.id
        LEFT JOIN clientes c ON u.id = c.usuario_id
        JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
        JOIN estados_pago ep ON p.estado_pago_id = ep.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pedido_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT p.*, u.email, c.nombres, c.apellidos, c.telefono,
               mp.nombre AS metodo_pago_nombre,
               ep.nombre AS estado_pago_nombre
        FROM pedidos p
        JOIN usuarios u ON p.usuario_id = u.id
        LEFT JOIN clientes c ON u.id = c.usuario_id
        JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
        JOIN estados_pago ep ON p.estado_pago_id = ep.id
        WHERE p.id = ? AND p.usuario_id = ?
    ");
    $stmt->execute([$pedido_id, $usuario_id]);
}
$pedido = $stmt->fetch();
if (!$pedido) die('Pedido no encontrado.');

$detalles = $pdo->prepare("
    SELECT d.*, pr.nombre, pr.descripcion AS producto_descripcion, pr.imagen_icono
    FROM detalle_pedido d
    JOIN productos pr ON d.producto_id = pr.id
    WHERE d.pedido_id = ?
");
$detalles->execute([$pedido_id]);
$productos = $detalles->fetchAll();

$extras_por_detalle = [];
$ids = array_column($productos, 'id');
if (!empty($ids)) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmtExt = $pdo->prepare("SELECT detalle_pedido_id, nombre, precio_extra FROM detalle_pedido_extras WHERE detalle_pedido_id IN ($ph)");
    $stmtExt->execute($ids);
    foreach ($stmtExt->fetchAll() as $e) {
        $extras_por_detalle[$e['detalle_pedido_id']][] = $e;
    }
}

$stmtHist = $pdo->prepare("
    SELECT h.fecha, h.notas, ea.nombre AS estado_anterior, en.nombre AS estado_nuevo
    FROM historial_estados_pedido h
    LEFT JOIN estados_pedido ea ON h.estado_anterior_id = ea.id
    JOIN estados_pedido en ON h.estado_nuevo_id = en.id
    WHERE h.pedido_id = ?
    ORDER BY h.fecha ASC
");
$stmtHist->execute([$pedido_id]);
$historial = $stmtHist->fetchAll();

$estados = [
    1 => ['nombre' => 'Pendiente',  'color' => '#ffc107'],
    2 => ['nombre' => 'Confirmado', 'color' => '#17a2b8'],
    3 => ['nombre' => 'Preparando', 'color' => '#007bff'],
    4 => ['nombre' => 'Listo',      'color' => '#28a745'],
    5 => ['nombre' => 'Entregado',  'color' => '#6c757d'],
    6 => ['nombre' => 'Cancelado',  'color' => '#dc3545'],
    7 => ['nombre' => 'En camino',  'color' => '#9c27b0']
];
$estado_pedido = intval($pedido['estado_id']);
$estado_actual = $estados[$estado_pedido] ?? ['nombre' => 'Desconocido', 'color' => '#6c757d'];

// Si el pedido fue cancelado automáticamente por tiempo y no tenía fila de "Cancelado" en historial, la agregamos visualmente
$tiene_cancelado_en_hist = false;
foreach ($historial as $h) {
    if (strtolower($h['estado_nuevo'] ?? '') === 'cancelado') {
        $tiene_cancelado_en_hist = true;
        break;
    }
}
if ($estado_pedido === 6 && !$tiene_cancelado_en_hist) {
    $historial[] = [
        'fecha'           => $pedido['fecha_pedido'],
        'estado_anterior' => 'Pendiente',
        'estado_nuevo'    => 'Cancelado',
        'notas'           => $pedido['razon_cancelacion'] ?? 'Cancelado automáticamente'
    ];
}

$es_delivery = ($pedido['tipo_entrega'] === 'delivery');
$cancelado = ($estado_pedido == 6);

if ($es_delivery) {$pasos = [1 => 'Pendiente', 2 => 'Confirmado', 3 => 'Preparando', 4 => 'Listo', 5 => 'En camino', 6 => 'Entregado'];
    $mapa_paso = [1 => 1, 2 => 2, 3 => 3, 4 => 4, 7 => 5, 5 => 6]; } else {$pasos = [1 => 'Pendiente', 2 => 'Confirmado', 3 => 'Preparando', 4 => 'Listo', 5 => 'Entregado'];
    $mapa_paso = [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5]; }$paso_actual = $mapa_paso[$estado_pedido] ?? 0;

$paso_actual = 0;
if ($estado_pedido == 1) $paso_actual = 1;
elseif ($estado_pedido == 2) $paso_actual = 2;
elseif ($estado_pedido == 3) $paso_actual = 3;
elseif ($estado_pedido == 4) $paso_actual = 4;
elseif ($estado_pedido == 7) $paso_actual = 5;
elseif ($estado_pedido == 5) $paso_actual = 6;

$volver_url = $es_admin ? 'admin/pedidos.php' : 'cliente/dashboard.php';
$volver_texto = $es_admin ? 'Volver a pedidos' : 'Volver a Mi Panel';

partial('head', [
    'titulo_pagina' => 'Pedido #' . $pedido['id'],
    'css_pagina'    => 'cliente',
    'css_extra'     => ['css/ver_detalle.css']
]);
?>

<?php partial('menu', ['menu_activo' => $es_admin ? 'pedidos' : 'historial']); ?>
<?php partial('header', [
    'titulo_pagina'  => 'Pedido #' . $pedido['id'],
    'mostrar_salir'  => false,
    'mostrar_volver' => ['url' => $volver_url, 'texto' => $volver_texto]
]); ?>

<section class="container ver-detalle-section">

    <!-- TARJETA 1: SEGUIMIENTO -->
    <div class="seguimiento-card">
        <div class="seguimiento-titulo">
            <i class="fas fa-route"></i> Estado de tu pedido
        </div>

        <?php if ($cancelado): ?>
            <div class="seguimiento-cancelado">
                <i class="fas fa-times-circle"></i>
                <p>Pedido cancelado</p>
                <?php if (!empty($pedido['razon_cancelacion'])): ?>
                    <span class="razon-cancelacion-txt"><?= htmlspecialchars($pedido['razon_cancelacion']) ?></span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="seguimiento-barra">
                <?php 
                $n = 1;
                foreach ($pasos as $num => $nombre_paso): 
                    $completado = ($n < $paso_actual);
                    $actual = ($n == $paso_actual);
                ?>
                    <div class="seguimiento-paso <?= $completado ? 'completado' : '' ?> <?= $actual ? 'actual' : '' ?>">
                        <div class="circulo">
                            <?php if ($completado): ?>
                                <i class="fas fa-check"></i>
                            <?php elseif ($actual): ?>
                                <i class="fas fa-circle icono-punto-actual"></i>
                            <?php else: ?>
                                <?= $n ?>
                            <?php endif; ?>
                        </div>
                        <div class="etiqueta"><?= htmlspecialchars($nombre_paso) ?></div>
                    </div>
                <?php 
                $n++;
                endforeach; 
                ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- TARJETA 2: DETALLE COMPLETO -->
    <div class="detalle-card">
        <div class="detalle-top-bar">
            <div>
                <h2>Pedido #<?= intval($pedido['id']) ?></h2>
                <p class="texto-secundario">
                    <i class="far fa-calendar-alt"></i> <?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?>
                </p>
            </div>
            <span class="badge-estado-grande" style="background: <?= $estado_actual['color'] ?>;">
                <?= htmlspecialchars($estado_actual['nombre']) ?>
            </span>
        </div>

        <!-- GRILLA DE INFORMACIÓN DEL PEDIDO (NUNCA SE CAE NI SE DEFORMA) -->
        <div class="detalle-meta-grid">
            <?php if ($es_admin): ?>
                <div class="meta-item">
                    <span class="meta-label"><i class="fas fa-user"></i> Cliente</span>
                    <strong class="meta-valor"><?= htmlspecialchars(trim(($pedido['nombres'] ?? '') . ' ' . ($pedido['apellidos'] ?? ''))) ?></strong>
                    <small class="meta-sub"><?= htmlspecialchars($pedido['email'] ?? '') ?><?= !empty($pedido['telefono']) ? ' • ' . htmlspecialchars($pedido['telefono']) : '' ?></small>
                </div>
            <?php endif; ?>

            <div class="meta-item">
                <span class="meta-label"><i class="fas fa-truck"></i> Tipo de entrega</span>
                <strong class="meta-valor"><?= $pedido['tipo_entrega'] === 'delivery' ? 'Delivery a domicilio' : 'Recoger en el local' ?></strong>
            </div>

            <div class="meta-item">
                <span class="meta-label"><i class="fas fa-wallet"></i> Método y Estado de pago</span>
                <strong class="meta-valor"><?= htmlspecialchars(ucfirst($pedido['metodo_pago_nombre'])) ?></strong>
                <small class="meta-sub">Estado: <?= htmlspecialchars(ucfirst($pedido['estado_pago_nombre'])) ?></small>
            </div>

            <?php if (!empty($pedido['direccion_texto'])): ?>
                <div class="meta-item meta-full">
                    <span class="meta-label"><i class="fas fa-map-marker-alt"></i> Dirección de entrega</span>
                    <strong class="meta-valor"><?= htmlspecialchars($pedido['direccion_texto']) ?></strong>
                </div>
            <?php endif; ?>
        </div>

        <h3 class="subtitulo-productos">
            <i class="fas fa-hamburger"></i> Productos del pedido
        </h3>

        <div class="lista-productos-detalle">
            <?php foreach ($productos as $d): ?>
                <?php
                $suma_extras_item = 0;
                if (!empty($extras_por_detalle[$d['id']])) {
                    foreach ($extras_por_detalle[$d['id']] as $e) {
                        $suma_extras_item += (float) $e['precio_extra'];
                    }
                }
                $precio_unitario_con_extras = (float) $d['precio_unitario_historico'] + $suma_extras_item;
                $subtotal_item = $precio_unitario_con_extras * (int) $d['cantidad'];
                ?>
                <div class="producto-detalle">
                    <div class="producto-detalle-header">
                        <div class="producto-detalle-nombre">
                            <i class="<?= htmlspecialchars($d['imagen_icono'] ?? 'fas fa-hamburger') ?>"></i>
                            <?= htmlspecialchars($d['nombre']) ?>
                        </div>
                        <span class="producto-detalle-cant">x<?= (int) $d['cantidad'] ?></span>
                    </div>

                    <?php if (!empty($d['producto_descripcion'])): ?>
                        <div class="producto-detalle-desc">
                            <i class="fas fa-info-circle icono-info-desc"></i>
                            <?= htmlspecialchars($d['producto_descripcion']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($extras_por_detalle[$d['id']])): ?>
                        <div class="producto-detalle-extras">
                            <strong><i class="fas fa-plus-circle"></i> Extras agregados</strong>
                            <?php foreach ($extras_por_detalle[$d['id']] as $e): ?>
                                + <?= htmlspecialchars($e['nombre']) ?>
                                <span class="extra-precio-acento">($<?= number_format((float) $e['precio_extra'], 2) ?>)</span><br>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="producto-detalle-precio">
                        $<?= number_format($subtotal_item, 2) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="detalle-total-box">
            <span class="detalle-total-label">Total del pedido</span>
            <span class="detalle-total-monto">$<?= number_format($pedido['total_pedido'], 2) ?></span>
        </div>

        <?php if (!empty($historial)): ?>
            <div class="historial-seccion">
                <h3 class="historial-titulo">
                    <i class="fas fa-history"></i> Historial del pedido
                </h3>
                <div class="mini-historial">
                    <?php foreach ($historial as $h): ?>
                        <?php
                        $color_ant = '#6c757d';
                        $color_nue = '#6c757d';
                        foreach ($estados as $id => $est) {
                            if (strtolower($est['nombre']) == strtolower($h['estado_anterior'] ?? '')) $color_ant = $est['color'];
                            if (strtolower($est['nombre']) == strtolower($h['estado_nuevo'] ?? '')) $color_nue = $est['color'];
                        }
                        ?>
                        <div class="mini-historial-item">
                            <div>
                                <span class="fecha"><i class="far fa-clock"></i> <?= date('d/m/Y H:i', strtotime($h['fecha'])) ?></span>
                                <?php if (!empty($h['notas'])): ?>
                                    <small class="hist-nota-inline">— <?= htmlspecialchars($h['notas']) ?></small>
                                <?php endif; ?>
                            </div>
                            <span class="cambio">
                                <?php if (!empty($h['estado_anterior'])): ?>
                                    <span class="badge-mini-estado" style="background: <?= $color_ant ?>;"><?= htmlspecialchars(ucfirst($h['estado_anterior'])) ?></span>
                                    <i class="fas fa-arrow-right icono-flecha-hist"></i>
                                <?php else: ?>
                                    <span class="texto-pedido-creado">Pedido creado</span>
                                    <i class="fas fa-arrow-right icono-flecha-hist"></i>
                                <?php endif; ?>
                                <span class="badge-mini-estado" style="background: <?= $color_nue ?>;"><?= htmlspecialchars(ucfirst($h['estado_nuevo'])) ?></span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="comanda-acciones">
        <a href="<?= url('cliente/factura.php?id=' . $pedido['id']) ?>" class="btn-ver-comanda">
            <i class="fas fa-file-invoice"></i> Ver comanda
        </a>
    </div>
</section>

<?php partial('footer'); ?>