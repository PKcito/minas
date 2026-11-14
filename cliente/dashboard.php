<?php
require_once __DIR__ . '/../config.php';

if (!usuarioLogueado() || esAdmin()) {
    redirect('index.php');
}

$usuario_id = $_SESSION['usuario_id'];
$nombre = $_SESSION['nombres'] ?? 'Cliente';

// 1. Obtener Pedido Activo (si tiene uno en curso)
$stmt = $pdo->prepare("
    SELECT p.id, p.fecha_pedido, p.total_pedido, p.tipo_entrega,
           e.nombre AS estado_nombre, e.id AS estado_id
    FROM pedidos p
    JOIN estados_pedido e ON p.estado_id = e.id
    WHERE p.usuario_id = ? AND p.estado_id IN (1, 2, 3, 4, 7)
    ORDER BY p.fecha_pedido DESC
    LIMIT 1
");
$stmt->execute([$usuario_id]);
$pedido_activo = $stmt->fetch();

// 2. Obtener Estadísticas generales (excluyendo pedidos cancelados estado_id = 6)
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total_pedidos,
           COALESCE(SUM(total_pedido), 0) AS total_gastado
    FROM pedidos
    WHERE usuario_id = ? AND estado_id != 6
");
$stmt->execute([$usuario_id]);
$stats_cliente = $stmt->fetch();

$total_pedidos = intval($stats_cliente['total_pedidos'] ?? 0);
$total_gastado = round(floatval($stats_cliente['total_gastado'] ?? 0), 2);

// 3. Obtener 3 productos activos para "¿Volver a pedir?"
$stmt = $pdo->query("
    SELECT p.id, p.nombre, p.precio_unitario, p.imagen, p.imagen_icono
    FROM productos p
    WHERE p.activo = 1
    ORDER BY RAND()
    LIMIT 3
");
$sugerencias = $stmt->fetchAll();

// Tasa del dólar para mostrar referencia en Bs.
$tasa_dolar = floatval($pdo->query("SELECT tasa_dolar FROM configuracion_pago WHERE id = 1")->fetchColumn() ?: 1);

$colores_estados = [
    1 => '#ffc107', 2 => '#17a2b8', 3 => '#007bff', 4 => '#28a745',
    5 => '#6c757d', 6 => '#dc3545', 7 => '#9c27b0'
];

partial('head', [
    'titulo_pagina' => 'Mi Panel',
    'css_pagina' => 'cliente',
    'css_extra' => ['css/cliente_dashboard.css']
]);
?>

<?php partial('menu', ['menu_activo' => 'dashboard']); ?>
<?php partial('header', ['titulo_pagina' => 'Mi Panel', 'mostrar_salir' => true]); ?>

<div class="app-container">

    <!-- Banner de Bienvenida -->
    <div class="app-hero">
        <div>
            <h1>¡Hola, <span><?= htmlspecialchars(explode(' ', trim($nombre))[0]) ?></span>! 👋</h1>
            <p>¿Se te antoja algo rico hoy? Recuerda que tenemos las mejores hamburguesas de Carabobo.</p>

            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="valor"><?= number_format($total_pedidos) ?></div>
                    <div class="label">Pedidos</div>
                </div>
                <div class="hero-stat">
                    <div class="valor">$<?= number_format($total_gastado, 2) ?></div>
                    <div class="label">Gastado</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ALERTA DE TIEMPO LÍMITE PARA EL CLIENTE -->
    <?php if ($pedido_activo && intval($pedido_activo['estado_id']) === 1): ?>
        <?php $segundos_restantes = tiempoRestanteCancelacion($pedido_activo['fecha_pedido']); ?>
        <div class="alerta-tiempo timer-container" data-tiempo="<?= $segundos_restantes ?>">
            <i class="fas fa-hourglass-half"></i>
            <div>
                <h4>¡Atención! Tu pedido está pendiente de verificación</h4>
                <p>Asegúrate de haber subido tu comprobante de pago. Si el administrador no lo verifica a tiempo, el pedido se cancelará automáticamente.</p>
                <div>Tiempo restante: <span class="tiempo-texto">--:--</span></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- SEGUIMIENTO DE PEDIDO ACTIVO -->
    <?php if ($pedido_activo): ?>
        <?php
        $estado = intval($pedido_activo['estado_id']);
        $pasos = [1 => 'Pendiente', 2 => 'Aprobado', 3 => 'Cocina', 4 => 'Listo', 7 => 'Camino', 5 => 'Entregado'];
        $mapa_orden = [1 => 1, 2 => 2, 3 => 3, 4 => 4, 7 => 5, 5 => 6];
        $paso_actual = $mapa_orden[$estado] ?? 0;

        $total_intervalos = max(1, count($pasos) - 1);
        $pct_progreso = $paso_actual > 1 ? round((($paso_actual - 1) / $total_intervalos) * 100, 2) : 0;
        ?>
        <div class="pedido-activo-card">
            <div class="pa-header">
                <h3><i class="fas fa-motorcycle"></i> Tu pedido #<?= intval($pedido_activo['id']) ?> está en curso</h3>
                <span class="pa-badge" style="background: <?= $colores_estados[$estado] ?? '#6c757d' ?>;">
                    <?= htmlspecialchars(ucfirst($pedido_activo['estado_nombre'])) ?>
                </span>
            </div>

            <?php if ($estado !== 6): ?>
                <div class="pa-progress">
                    <div class="pa-linea"></div>
                    <div class="pa-linea-progreso" style="width: <?= $pct_progreso ?>%;"></div>

                    <?php $n = 1; foreach ($pasos as $num => $nombre_paso): ?>
                        <div class="pa-step <?= $n < $paso_actual ? 'completado' : '' ?> <?= $n === $paso_actual ? 'actual' : '' ?>">
                            <div class="pa-dot">
                                <?php if ($n < $paso_actual): ?><i class="fas fa-check"></i><?php else: ?><?= $n ?><?php endif; ?>
                            </div>
                            <span><?= $nombre_paso ?></span>
                        </div>
                    <?php $n++; endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="pa-footer">
                <div>
                    <span class="pa-total-label">Total a pagar:</span>
                    <span class="total">$<?= number_format($pedido_activo['total_pedido'], 2) ?></span>
                </div>
                <a href="<?= url('cliente/ver_detalle.php?id=' . intval($pedido_activo['id'])) ?>" class="btn-ver-detalle">
                    Ver detalles <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- VOLVER A PEDIR -->
    <h2 class="seccion-titulo"><i class="fas fa-fire"></i> ¿Volver a pedir?</h2>
    <div class="sugerencias-grid">
        <?php foreach ($sugerencias as $sug): ?>
            <a href="<?= url('index.php') ?>" class="sugerencia-card">
                <div class="sugerencia-img">
                    <?php if (!empty($sug['imagen']) && file_exists(__DIR__ . '/../uploads/productos/' . $sug['imagen'])): ?>
                        <img src="<?= url('uploads/productos/' . htmlspecialchars($sug['imagen'])) ?>" alt="">
                    <?php else: ?>
                        <i class="<?= htmlspecialchars($sug['imagen_icono'] ?? 'fas fa-hamburger') ?>"></i>
                    <?php endif; ?>
                </div>
                <div class="sugerencia-info">
                    <h4><?= htmlspecialchars($sug['nombre']) ?></h4>
                    <div>
                        <div class="sugerencia-precio">$<?= number_format($sug['precio_unitario'], 2) ?></div>
                        <span class="sugerencia-precio-bs">Bs. <?= number_format($sug['precio_unitario'] * $tasa_dolar, 2) ?></span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ACCESOS RÁPIDOS -->
    <h2 class="seccion-titulo"><i class="fas fa-th-large"></i> Mis Herramientas</h2>
    <div class="accesos-grid">
        <a href="<?= url('index.php') ?>" class="acceso-card">
            <div class="acceso-icono"><i class="fas fa-utensils"></i></div>
            <span>Menú Completo</span>
        </a>
        <a href="<?= url('cliente/historial.php') ?>" class="acceso-card">
            <div class="acceso-icono"><i class="fas fa-receipt"></i></div>
            <span>Mis Pedidos</span>
        </a>
        <a href="<?= url('cliente/perfil.php') ?>" class="acceso-card">
            <div class="acceso-icono"><i class="fas fa-map-marker-alt"></i></div>
            <span>Mis Direcciones</span>
        </a>
        <a href="<?= url('api/ver_notificaciones.php') ?>" class="acceso-card">
            <div class="acceso-icono"><i class="fas fa-bell"></i></div>
            <span>Notificaciones</span>
        </a>
    </div>

</div>

<script>
window.CLIENTE_CONFIG = {};
</script>

<?php partial('footer', ['js_extra_footer' => ['js/cliente_dashboard.js']]); ?>