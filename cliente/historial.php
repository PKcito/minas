<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/paginacion.php';

if (!usuarioLogueado()) {
    redirect('auth/login.php');
}

$usuario_id = $_SESSION['usuario_id'];
$es_admin = esAdmin();

// Si un administrador entra a esta ruta de cliente, lo enviamos a su panel de gestión de pedidos
if ($es_admin) {
    redirect('admin/pedidos.php');
}

$sql_historial = "
    SELECT p.*, c.nombres, c.apellidos,
           mp.nombre AS metodo_pago_nombre,
           ep.nombre AS estado_pago_nombre
    FROM pedidos p
    JOIN usuarios u ON p.usuario_id = u.id
    JOIN clientes c ON u.id = c.usuario_id
    JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
    JOIN estados_pago ep ON p.estado_pago_id = ep.id
    WHERE p.usuario_id = ?
    ORDER BY p.fecha_pedido DESC
";

$pag_historial = paginar($pdo, $sql_historial, [$usuario_id], 10);
$pedidos = $pag_historial['items'];

$colores_estados = [
    1 => '#ffc107', 2 => '#17a2b8', 3 => '#007bff', 4 => '#28a745',
    5 => '#6c757d', 6 => '#dc3545', 7 => '#9c27b0'
];
$estados_nombres = [
    1 => 'Pendiente', 2 => 'Confirmado', 3 => 'Preparando', 4 => 'Listo',
    5 => 'Entregado', 6 => 'Cancelado', 7 => 'En camino'
];

partial('head', [
    'titulo_pagina' => 'Mis pedidos',
    'cargar_sweetalert' => true,
    'css_pagina' => 'cliente',
    'css_extra' => ['css/paginacion.css', 'css/historial.css']
]);
?>

<?php partial('menu', ['menu_activo' => 'historial']); ?>
<?php partial('header', ['titulo_pagina' => 'Mis pedidos']); ?>

<section class="container historial-section">
    <div class="volver-wrapper">
        <a href="<?= url('cliente/dashboard.php') ?>" class="btn-volver-panel">
            <i class="fas fa-arrow-left"></i> Volver a Mi Panel
        </a>
    </div>

    <h2 class="titulo-historial"><i class="fas fa-history"></i> Mis pedidos</h2>

    <?php if (count($pedidos) === 0): ?>
        <div class="historial-vacio-card">
            <i class="fas fa-inbox icono-historial-vacio"></i>
            <p class="texto-historial-vacio">No has realizado ningun pedido aun.</p>
            <a href="<?= url('index.php') ?>" class="btn-ir-menu">Ver menu</a>
        </div>
    <?php else: ?>
        <div class="historial-card">
            <div class="table-responsive">
                <table class="tabla-historial">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Entrega</th>
                            <th>Pago</th>
                            <th>Estado</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $p): ?>
                            <tr>
                                <td class="col-id"><strong>#<?= intval($p['id']) ?></strong></td>
                                <td class="col-fecha"><?= date('d/m/Y H:i', strtotime($p['fecha_pedido'])) ?></td>
                                <td class="col-total"><strong>$<?= number_format($p['total_pedido'], 2) ?></strong></td>
                                <td class="col-entrega">
                                    <span class="entrega-chip">
                                        <?php if ($p['tipo_entrega'] === 'delivery'): ?>
                                            <i class="fas fa-truck"></i> Delivery
                                        <?php else: ?>
                                            <i class="fas fa-store"></i> Recoger
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="col-pago">
                                    <span class="metodo-pago-nombre"><?= htmlspecialchars($p['metodo_pago_nombre']) ?></span>
                                    <small class="subtexto-estado-pago"><?= htmlspecialchars(ucfirst($p['estado_pago_nombre'])) ?></small>
                                </td>
                                <td>
                                    <span class="badge-estado-historial" style="background: <?= $colores_estados[$p['estado_id']] ?? '#6c757d' ?>;">
                                        <?= $estados_nombres[$p['estado_id']] ?? 'Desconocido' ?>
                                    </span>
                                </td>
                                <td class="col-accion">
                                    <a href="<?= url('cliente/ver_detalle.php?id=' . intval($p['id'])) ?>" class="btn-ver-pedido">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= renderPaginacion($pag_historial) ?>
        </div>
    <?php endif; ?>
</section>

<?php partial('footer'); ?>