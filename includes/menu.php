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

// Obtener WhatsApp, Logo, Banner y Nombre del Negocio desde configuracion_pago
$wa_telefono_menu = '584141234567';
$logo_menu_url = '';
$banner_menu_url = '';
$nombre_negocio_menu = 'MINAS BURGUER';

try {
    if (isset($pdo)) {
        $cfg_menu = $pdo->query("SELECT * FROM configuracion_pago WHERE id = 1 LIMIT 1")->fetch();
        if ($cfg_menu) {
            $tel_elegido = !empty($cfg_menu['telefono_whatsapp']) ? $cfg_menu['telefono_whatsapp'] : ($cfg_menu['telefono'] ?? '');
            if ($tel_elegido) {
                $limpio = preg_replace('/\D/', '', $tel_elegido);
                if (!empty($limpio)) {
                    $wa_telefono_menu = '58' . ltrim($limpio, '0');
                }
            }
            if (!empty($cfg_menu['logo']) && file_exists(__DIR__ . '/../uploads/config/' . $cfg_menu['logo'])) {
                $logo_menu_url = url('uploads/config/' . rawurlencode($cfg_menu['logo']));
            }
            if (!empty($cfg_menu['banner']) && file_exists(__DIR__ . '/../uploads/config/' . $cfg_menu['banner'])) {
                $banner_menu_url = url('uploads/config/' . rawurlencode($cfg_menu['banner']));
            }
            if (!empty(trim($cfg_menu['nombre_negocio'] ?? ''))) {
                $nombre_negocio_menu = trim($cfg_menu['nombre_negocio']);
            }
        }
    }
} catch (Exception $e) {}

$palabras_menu = preg_split('/\s+/', $nombre_negocio_menu);
if (count($palabras_menu) > 1) {
    $ultima_menu = array_pop($palabras_menu);
    $primera_menu = implode(' ', $palabras_menu);
    $titulo_menu_html = htmlspecialchars($primera_menu) . ' <span>' . htmlspecialchars($ultima_menu) . '</span>';
} else {
    $titulo_menu_html = '<span>' . htmlspecialchars($nombre_negocio_menu) . '</span>';
}

$wa_mensaje_menu = urlencode("¡Hola {$nombre_negocio_menu}! Me gustaría hacer una consulta.");
$wa_url_menu = "https://wa.me/{$wa_telefono_menu}?text={$wa_mensaje_menu}";

$estilo_cabecera_menu = '';
if ($banner_menu_url !== '') {
    $estilo_cabecera_menu = "background: linear-gradient(180deg, rgba(18,18,18,0.65) 0%, rgba(18,18,18,0.92) 100%), url('" . htmlspecialchars($banner_menu_url, ENT_QUOTES) . "') center/cover no-repeat; padding-top: 1.75rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--borde);";
}
?>
<button class="menu-toggle" id="menuToggle" title="Abrir menu"><i class="fas fa-bars"></i></button>

<div class="offcanvas" id="offcanvasMenu">
    <button class="close-menu" id="closeMenu" title="Cerrar menu" style="z-index: 5;"><i class="fas fa-times"></i></button>

    <div class="logo-side" style="display: flex; align-items: center; gap: 0.7rem; <?= $estilo_cabecera_menu ?>">
        <?php if ($logo_menu_url !== ''): ?>
            <img src="<?= htmlspecialchars($logo_menu_url) ?>" alt="Logo" style="width: 42px; height: 42px; object-fit: cover; border-radius: 10px; border: 2px solid var(--color-acento); background: var(--bg-principal); flex-shrink: 0;">
        <?php else: ?>
            <span style="width: 40px; height: 40px; border-radius: 10px; background: var(--color-acento); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                <i class="fas fa-hamburger"></i>
            </span>
        <?php endif; ?>
        <div style="line-height: 1.15; <?= $banner_menu_url !== '' ? 'color: #ffffff; text-shadow: 0 2px 6px rgba(0,0,0,0.8);' : '' ?>">
            <?= $titulo_menu_html ?>
        </div>
    </div>

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