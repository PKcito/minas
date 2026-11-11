<?php
$menu_activo = $menu_activo ?? '';
$es_admin_menu = function_exists('esAdmin') && esAdmin();
$logueado_menu = function_exists('usuarioLogueado') && usuarioLogueado();

$nombre_menu = 'Invitado';
$email_menu = '';
$rol_texto = 'Invitado';

if ($logueado_menu) {
    if (!empty($_SESSION['nombres']) && !empty($_SESSION['apellidos'])) {
        $nombre_menu = trim($_SESSION['nombres'] . ' ' . $_SESSION['apellidos']);
    } elseif (!empty($_SESSION['nombres'])) {
        $nombre_menu = $_SESSION['nombres'];
    } elseif (!empty($_SESSION['email'])) {
        $nombre_menu = $_SESSION['email'];
    }
    $email_menu = $_SESSION['email'] ?? '';
    $rol_texto = $es_admin_menu ? 'Administrador' : 'Cliente';
}

// Obtener el número de WhatsApp independiente desde configuracion_pago
$wa_telefono_menu = '584141234567';
try {
    if (isset($pdo)) {
        $cfg_wa = $pdo->query("SELECT telefono, telefono_whatsapp FROM configuracion_pago WHERE id = 1 LIMIT 1")->fetch();
        $tel_elegido = !empty($cfg_wa['telefono_whatsapp']) ? $cfg_wa['telefono_whatsapp'] : ($cfg_wa['telefono'] ?? '');
        if ($tel_elegido) {
            $limpio = preg_replace('/\D/', '', $tel_elegido);
            if (!empty($limpio)) {
                $wa_telefono_menu = '58' . ltrim($limpio, '0');
            }
        }
    }
} catch (Exception $e) {}

$wa_mensaje_menu = urlencode("¡Hola Mina's Burguers! Me gustaría hacer una consulta.");
$wa_url_menu = "https://wa.me/{$wa_telefono_menu}?text={$wa_mensaje_menu}";
?>
<button class="menu-toggle" id="menuToggle" title="Abrir menu"><i class="fas fa-bars"></i></button>

<div class="offcanvas" id="offcanvasMenu">
    <button class="close-menu" id="closeMenu" title="Cerrar menu"><i class="fas fa-times"></i></button>
    <div class="logo-side">MINAS <span>BURGUER</span></div>
    <div class="user-info">
        <?php if ($logueado_menu): ?>
            <i class="fas <?= $es_admin_menu ? 'fa-user-cog' : 'fa-user-circle' ?>"></i>
            <strong><?= htmlspecialchars($nombre_menu) ?></strong>
            <br><small><?= htmlspecialchars($rol_texto) ?></small>
            <?php if ($email_menu): ?>
                <br><small style="font-size: 0.7rem; opacity: 0.7;"><?= htmlspecialchars($email_menu) ?></small>
            <?php endif; ?>
        <?php else: ?>
            <i class="fas fa-user"></i> Invitado
        <?php endif; ?>
    </div>
    <div class="nav-links">
        <?php if ($es_admin_menu): ?>
            <?php if (tienePermiso('pedidos')): ?>
                <a href="<?= url('admin/dashboard.php') ?>" class="<?= $menu_activo === 'dashboard' ? 'active' : '' ?>"><i class="fas fa-chart-pie"></i> Panel de control</a>
                <a href="<?= url('admin/pedidos.php') ?>" class="<?= $menu_activo === 'pedidos' ? 'active' : '' ?>"><i class="fas fa-clipboard-list"></i> Todos los pedidos</a>
            <?php endif; ?>
            <?php if (tienePermiso('productos')): ?>
                <a href="<?= url('admin/productos.php') ?>" class="<?= $menu_activo === 'productos' ? 'active' : '' ?>"><i class="fas fa-edit"></i> Gestionar productos</a>
            <?php endif; ?>
            <?php if (tienePermiso('categorias')): ?>
                <a href="<?= url('admin/categorias.php') ?>" class="<?= $menu_activo === 'categorias' ? 'active' : '' ?>"><i class="fas fa-tags"></i> Gestionar categorias</a>
            <?php endif; ?>
            <?php if (tienePermiso('extras')): ?>
                <a href="<?= url('admin/extras.php') ?>" class="<?= $menu_activo === 'extras' ? 'active' : '' ?>"><i class="fas fa-plus-circle"></i> Gestionar extras</a>
            <?php endif; ?>
            <?php if (tienePermiso('clientes')): ?>
                <a href="<?= url('admin/clientes.php') ?>" class="<?= $menu_activo === 'clientes' ? 'active' : '' ?>"><i class="fas fa-users"></i> Clientes</a>
            <?php endif; ?>
            <?php if (tienePermiso('usuarios')): ?>
                <a href="<?= url('admin/usuarios.php') ?>" class="<?= $menu_activo === 'usuarios' ? 'active' : '' ?>"><i class="fas fa-user-shield"></i> Administradores</a>
            <?php endif; ?>
            <?php if (tienePermiso('reportes')): ?>
                <a href="<?= url('admin/reportes.php') ?>" class="<?= $menu_activo === 'reportes' ? 'active' : '' ?>"><i class="fas fa-file-pdf"></i> Reportes</a>
            <?php endif; ?>
            <?php if (tienePermiso('configuracion')): ?>
                <a href="<?= url('admin/configuracion.php') ?>" class="<?= $menu_activo === 'configuracion' ? 'active' : '' ?>"><i class="fas fa-cogs"></i> Configuracion</a>
            <?php endif; ?>
            <a href="<?= url('index.php') ?>" class="<?= $menu_activo === 'tienda' ? 'active' : '' ?>"><i class="fas fa-store"></i> Ver tienda</a>
        <?php else: ?>
            <a href="<?= url('index.php') ?>" class="<?= $menu_activo === 'tienda' ? 'active' : '' ?>"><i class="fas fa-home"></i> Inicio</a>
            <?php if ($logueado_menu): ?>
                <a href="<?= url('cliente/dashboard.php') ?>" class="<?= $menu_activo === 'dashboard' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> Mi Panel</a>
                <a href="<?= url('cliente/historial.php') ?>" class="<?= $menu_activo === 'historial' ? 'active' : '' ?>"><i class="fas fa-history"></i> Mis pedidos</a>
                <a href="<?= url('cliente/perfil.php') ?>" class="<?= $menu_activo === 'perfil' ? 'active' : '' ?>"><i class="fas fa-user"></i> Mi perfil</a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($logueado_menu): ?>
            <a href="<?= url('auth/logout.php') ?>"><i class="fas fa-sign-out-alt"></i> Cerrar sesion</a>
        <?php else: ?>
            <a href="<?= url('auth/login.php') ?>"><i class="fas fa-sign-in-alt"></i> Iniciar sesion</a>
            <a href="<?= url('auth/registro.php') ?>"><i class="fas fa-user-plus"></i> Registrarse</a>
        <?php endif; ?>
    </div>

    <div class="menu-mode-toggle">
        <button type="button" id="toggleMenuMode" title="Cambiar entre menu fijo y flotante">
            <i class="fas fa-thumbtack" id="menuModeIcon"></i>
            <span id="menuModeText">Modo flotante</span>
        </button>
    </div>

    <div class="footer-links">
        <a href="<?= htmlspecialchars($wa_url_menu) ?>" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-whatsapp" style="color: #25D366;"></i> Contacto WhatsApp
        </a>
    </div>
</div>
<div class="overlay" id="overlay"></div>