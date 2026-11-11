<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/paginacion.php';

if (!usuarioLogueado()) {
    redirect('auth/login.php');
}

aplicar_headers_seguridad();

$usuario_id = intval($_SESSION['usuario_id']);
$es_admin = esAdmin();

// Paginación de notificaciones (15 por página)
$sql_notif = "
    SELECT n.id, n.titulo, n.mensaje, n.fecha_creacion, n.leida
    FROM notificaciones n
    WHERE n.usuario_id = ?
    ORDER BY n.fecha_creacion DESC
";
$pag_notif = paginar($pdo, $sql_notif, [$usuario_id], 15);
$notificaciones = $pag_notif['items'];

// Marcar como leídas las notificaciones pendientes del usuario
$stmtLeidas = $pdo->prepare("UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0");
$stmtLeidas->execute([$usuario_id]);

$url_volver = $es_admin ? 'admin/dashboard.php' : 'cliente/dashboard.php';
$texto_volver = $es_admin ? 'Volver al Dashboard' : 'Volver a Mi Panel';

partial('head', [
    'titulo_pagina' => 'Notificaciones',
    'css_pagina'    => 'cliente',
    'css_extra'     => ['css/notificaciones.css']
]);
?>

<?php partial('menu', ['menu_activo' => '']); ?>
<?php partial('header', [
    'titulo_pagina'  => 'Notificaciones',
    'mostrar_salir'  => false,
    'mostrar_volver' => ['url' => $url_volver, 'texto' => $texto_volver]
]); ?>

<div class="container-notif">
    <div class="volver-notif-wrapper">
        <a href="<?= url($url_volver) ?>" class="btn-volver">
            <i class="fas fa-arrow-left"></i> <?= htmlspecialchars($texto_volver) ?>
        </a>
    </div>

    <div class="card-notif">
        <h2 class="notif-header-titulo">
            <i class="fas fa-bell"></i> Historial de notificaciones
        </h2>

        <?php if (count($notificaciones) === 0): ?>
            <div class="notif-vacio">
                <i class="fas fa-bell-slash"></i>
                <p>No tienes notificaciones registradas.</p>
            </div>
        <?php else: ?>
            <div class="notif-lista">
                <?php foreach ($notificaciones as $notif): ?>
                    <div class="notif-item <?= !$notif['leida'] ? 'notif-item-nueva' : '' ?>">
                        <div class="titulo">
                            <span><?= htmlspecialchars($notif['titulo']) ?></span>
                            <span class="<?= $notif['leida'] ? 'badge-leida' : 'badge-no-leida' ?>">
                                <?= $notif['leida'] ? 'Leída' : 'Nueva' ?>
                            </span>
                        </div>
                        <div class="mensaje"><?= htmlspecialchars($notif['mensaje']) ?></div>
                        <div class="fecha"><i class="far fa-clock"></i> <?= date('d/m/Y H:i', strtotime($notif['fecha_creacion'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?= renderPaginacion($pag_notif) ?>
        <?php endif; ?>
    </div>
</div>

<?php partial('footer'); ?>