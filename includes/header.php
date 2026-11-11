<?php
$titulo_pagina  = $titulo_pagina  ?? '';
$mostrar_salir  = $mostrar_salir  ?? usuarioLogueado();
$mostrar_volver = $mostrar_volver ?? null;

$logueado_header = function_exists('usuarioLogueado') && usuarioLogueado();
$es_admin_header = function_exists('esAdmin') && esAdmin();

$nombre_header = '';
$rol_header = '';
if ($logueado_header) {
    if (!empty($_SESSION['nombres'])) {
        $partes = explode(' ', trim($_SESSION['nombres']));
        $nombre_header = $partes[0];
    } elseif (!empty($_SESSION['email'])) {
        $nombre_header = explode('@', $_SESSION['email'])[0];
    }
    $rol_header = $es_admin_header ? 'Administrador' : 'Cliente';
}
?>
<header class="header">
    <div class="container">
        <span class="header-titulo">
            <?= htmlspecialchars($titulo_pagina) ?>
        </span>

        <div class="header-derecha">
            <?php if ($logueado_header && !$es_admin_header): ?>
                <button class="notif-btn" title="Notificaciones" onclick="window.location.href='<?= url('api/ver_notificaciones.php') ?>'">
                    <i class="fas fa-bell"></i>
                    <span class="notif-badge" id="notifBadge">0</span>
                </button>
            <?php endif; ?>

            <?php if ($logueado_header && $nombre_header): ?>
                <span class="header-user" title="<?= htmlspecialchars($_SESSION['nombres'] ?? '') ?> - <?= $rol_header ?>">
                    <i class="fas fa-user-circle"></i>
                    <span class="header-user-nombre"><?= htmlspecialchars($nombre_header) ?></span>
                </span>
            <?php endif; ?>

            <button class="theme-toggle" id="themeToggle" title="Cambiar tema claro/oscuro"><i class="fas fa-moon"></i></button>

            <?php if ($logueado_header && !$es_admin_header): ?>
                <a href="<?= url('index.php?abrir_carrito=1') ?>" class="header-icon-btn" title="Ver carrito" style="position: relative;">
                    <i class="fas fa-shopping-bag"></i>
                    <span class="header-badge" id="cartCountHeader" style="display: none;">0</span>
                </a>
            <?php endif; ?>

            <?php if ($mostrar_volver): ?>
                <a href="<?= url($mostrar_volver['url']) ?>" class="header-btn" title="Volver">
                    <i class="fas fa-arrow-left"></i> <?= htmlspecialchars($mostrar_volver['texto'] ?? 'Volver') ?>
                </a>
            <?php elseif ($mostrar_salir): ?>
                <a href="<?= url('auth/logout.php') ?>" class="header-btn" title="Cerrar sesion">
                    <i class="fas fa-sign-out-alt"></i> Salir
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php if ($logueado_header && !$es_admin_header): ?>
<script>
(function() {
    function actualizarBadgeCarritoHeader() {
        fetch('<?= url('api/obtener_carrito.php') ?>', { credentials: 'same-origin' })
            .then(res => res.json())
            .then(data => {
                if (!data.carrito) return;
                const total = data.carrito.reduce((s, i) => s + (parseInt(i.cantidad) || 0), 0);
                const badge = document.getElementById('cartCountHeader');
                if (!badge) return;
                if (total > 0) {
                    badge.textContent = total;
                    badge.style.display = 'inline-flex';
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(() => {});
    }
    document.addEventListener('DOMContentLoaded', actualizarBadgeCarritoHeader);
})();
</script>
<?php endif; ?>