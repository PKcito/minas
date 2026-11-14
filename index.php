<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/carrito_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!usuarioLogueado()) {
        echo json_encode(['success' => false, 'message' => 'Debes iniciar sesion.']);
        exit;
    }
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT) ?: 1;
        if ($cantidad < 1) {
            $cantidad = 1;
        }
        $extras_recibidos = json_decode($_POST['extras'] ?? '[]', true);
        if (!is_array($extras_recibidos)) {
            $extras_recibidos = [];
        }

        if ($id) {
            try {
                // 1. Obtener nombre y precio real del producto desde la base de datos
                $stmt = $pdo->prepare("SELECT nombre, precio_unitario FROM productos WHERE id = ? AND activo = 1 LIMIT 1");
                $stmt->execute([$id]);
                $producto_db = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($producto_db) {
                    $nombre_real = $producto_db['nombre'];
                    $precio_real = (float) $producto_db['precio_unitario'];

                    // 2. Validar precios de los extras contra la base de datos (si se enviaron extras)
                    $extras_validados = [];
                    foreach ($extras_recibidos as $extra_item) {
                        $extra_id = isset($extra_item['id']) ? (int) $extra_item['id'] : 0;
                        if ($extra_id > 0) {
                            $stmt_extra = $pdo->prepare("SELECT id, nombre, precio FROM extras WHERE id = ? AND activo = 1 LIMIT 1");
                            $stmt_extra->execute([$extra_id]);
                            $extra_db = $stmt_extra->fetch(PDO::FETCH_ASSOC);
                            if ($extra_db) {
                                $extras_validados[] = [
                                    'id' => (int) $extra_db['id'],
                                    'nombre' => $extra_db['nombre'],
                                    'precio' => (float) $extra_db['precio']
                                ];
                            }
                        }
                    }

                    agregarAlCarrito($id, $nombre_real, $precio_real, $cantidad, $extras_validados);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'El producto no existe o no está disponible.']);
                }
            } catch (PDOException $e) {
                error_log("Error al agregar al carrito en index.php: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error interno al procesar el producto.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de producto inválido.']);
        }
        exit;
    } elseif ($accion === 'eliminar') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            eliminarDelCarrito($id);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    } elseif ($accion === 'actualizar') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);
        if ($id && $cantidad !== false && $cantidad > 0) {
            actualizarCantidad($id, $cantidad);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
}

// Consultas protegidas con bloque try/catch
$categorias = [];
$productos = [];
$extras = [];
$tasa_dolar = 1;
$logo_tienda = '';
$banner_tienda = '';
$nombre_negocio_tienda = 'MINAS BURGUER';
$texto_hero_tienda = 'Sabor que alimenta el alma. Elige tu favorita, personalizala y recibila en la puerta de tu casa.';

try {
    // Asegurar que la columna 'activo' exista en categorias también en producción (Railway)
    $col_cat_activo = $pdo->query("SHOW COLUMNS FROM categorias LIKE 'activo'")->fetch();
    if (!$col_cat_activo) {
        $pdo->exec("ALTER TABLE categorias ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER descripcion");
    }

    // Cargar solo categorías activas que tengan al menos 1 producto activo
    $categorias = $pdo->query("
        SELECT c.* 
        FROM categorias c 
        WHERE c.activo = 1 
          AND EXISTS (SELECT 1 FROM productos p WHERE p.categoria_id = c.id AND p.activo = 1)
        ORDER BY c.id
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $categorias = $pdo->query("
            SELECT c.* 
            FROM categorias c 
            WHERE EXISTS (SELECT 1 FROM productos p WHERE p.categoria_id = c.id AND p.activo = 1)
            ORDER BY c.id
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        error_log("Error cargando categorias en index.php: " . $e2->getMessage());
    }
}

try {
    // Cargar solo productos activos de categorías activas
    $productos = $pdo->query("
        SELECT p.*, c.nombre AS categoria_nombre
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.activo = 1 AND COALESCE(c.activo, 1) = 1
        ORDER BY p.categoria_id, p.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    $extras = $pdo->query("SELECT * FROM extras WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

    // Cargar tasa del dólar, logo, banner y textos desde configuracion_pago
    $cfg_tienda = $pdo->query("SELECT * FROM configuracion_pago WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($cfg_tienda) {
        if (!empty($cfg_tienda['tasa_dolar']) && $cfg_tienda['tasa_dolar'] > 0) {
            $tasa_dolar = (float) $cfg_tienda['tasa_dolar'];
        }
        if (!empty($cfg_tienda['logo']) && file_exists(__DIR__ . '/uploads/config/' . $cfg_tienda['logo'])) {
            $logo_tienda = url('uploads/config/' . rawurlencode($cfg_tienda['logo']));
        }
        if (!empty($cfg_tienda['banner']) && file_exists(__DIR__ . '/uploads/config/' . $cfg_tienda['banner'])) {
            $banner_tienda = url('uploads/config/' . rawurlencode($cfg_tienda['banner']));
        }
        if (!empty(trim($cfg_tienda['nombre_negocio'] ?? ''))) {
            $nombre_negocio_tienda = trim($cfg_tienda['nombre_negocio']);
        }
        if (!empty(trim($cfg_tienda['texto_hero'] ?? ''))) {
            $texto_hero_tienda = trim($cfg_tienda['texto_hero']);
        }
    }
} catch (PDOException $e) {
    error_log("Error cargando catalogo en index.php: " . $e->getMessage());
}

// Separar el nombre del negocio para resaltar la última palabra en rojo (ej: MINAS <span>BURGUER</span>)
$palabras_titulo = preg_split('/\s+/', $nombre_negocio_tienda);
if (count($palabras_titulo) > 1) {
    $ultima_palabra = array_pop($palabras_titulo);
    $primera_parte = implode(' ', $palabras_titulo);
    $titulo_hero_html = htmlspecialchars($primera_parte) . ' <span>' . htmlspecialchars($ultima_palabra) . '</span>';
} else {
    $titulo_hero_html = '<span>' . htmlspecialchars($nombre_negocio_tienda) . '</span>';
}

$isLoggedIn = usuarioLogueado();

$nombre_header_index = '';
if ($isLoggedIn && !empty($_SESSION['nombres'])) {
    $partes = explode(' ', trim($_SESSION['nombres']));
    $nombre_header_index = $partes[0];
}

$estilo_hero = '';
if (!empty($banner_tienda)) {
    $estilo_hero = "background: linear-gradient(115deg, rgba(15, 15, 15, 0.88) 0%, rgba(18, 18, 18, 0.72) 100%), url('" . htmlspecialchars($banner_tienda, ENT_QUOTES) . "') center/cover no-repeat;";
}

partial('head', [
    'titulo_pagina' => 'Pedi online',
    'cargar_sweetalert' => true,
    'css_pagina' => 'index'
]);
?>

<?php partial('menu', ['menu_activo' => 'tienda']); ?>

<header class="header">
    <div class="container">
        <span class="header-titulo" style="display: inline-flex; align-items: center; gap: 0.55rem;">
            <?php if (!empty($logo_tienda)): ?>
                <img src="<?= htmlspecialchars($logo_tienda) ?>" alt="Logo" style="width: 30px; height: 30px; object-fit: cover; border-radius: 8px; flex-shrink: 0;">
            <?php endif; ?>
            <?php if ($isLoggedIn && $nombre_header_index): ?>
                Hola, <?= htmlspecialchars($nombre_header_index) ?>!
            <?php else: ?>
                <?= htmlspecialchars($nombre_negocio_tienda) ?>
            <?php endif; ?>
        </span>

        <div class="header-derecha">
            <?php if ($isLoggedIn): ?>
                <div class="notif-container">
                    <button class="notif-btn" id="notifBtn" title="Notificaciones">
                        <i class="fas fa-bell"></i>
                        <span class="notif-badge" id="notifBadge">0</span>
                    </button>
                    <div class="notif-dropdown" id="notifDropdown">
                        <div id="notifList"></div>
                        <a href="<?= url('api/ver_notificaciones.php') ?>" class="notif-ver-todas">Ver todas</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($isLoggedIn && $nombre_header_index): ?>
                <a href="<?= url('cliente/dashboard.php') ?>" class="header-user" title="Ir a mi panel">
                    <i class="fas fa-user-circle"></i>
                    <span class="header-user-nombre"><?= htmlspecialchars($nombre_header_index) ?></span>
                </a>
            <?php endif; ?>

            <button class="theme-toggle" id="themeToggle" title="Cambiar tema claro/oscuro"><i class="fas fa-moon"></i></button>

            <button id="cartToggle" class="header-icon-btn" title="Ver carrito">
                <i class="fas fa-shopping-bag"></i>
                <span id="cartCount" class="header-badge">0</span>
            </button>

            <button id="userBtn" class="header-btn" title="<?= $isLoggedIn ? 'Mi cuenta' : 'Iniciar sesion' ?>">
                <i class="fas fa-user"></i> <?= $isLoggedIn ? 'Cuenta' : 'Entrar' ?>
            </button>
        </div>
    </div>
</header>

<section class="hero" <?php if ($estilo_hero !== ''): ?>style="<?= $estilo_hero ?>"<?php endif; ?>>
    <div class="container">
        <div class="hero-content">
            <h1 <?php if ($estilo_hero !== ''): ?>style="color: #ffffff; text-shadow: 0 2px 10px rgba(0,0,0,0.6);"<?php endif; ?>><?= $titulo_hero_html ?></h1>
            <p <?php if ($estilo_hero !== ''): ?>style="color: #f0f0f0; text-shadow: 0 1px 6px rgba(0,0,0,0.6);"<?php endif; ?>><?= htmlspecialchars($texto_hero_tienda) ?></p>
            <button class="btn-order" id="scrollToMenu">Ver menu <i class="fas fa-arrow-right"></i></button>
        </div>
        <div class="hero-image">
            <?php if (!empty($logo_tienda)): ?>
                <img src="<?= htmlspecialchars($logo_tienda) ?>" alt="<?= htmlspecialchars($nombre_negocio_tienda) ?>" style="width: 155px; height: 155px; object-fit: contain; border-radius: 22px; filter: drop-shadow(0 10px 24px rgba(0,0,0,0.45));">
            <?php else: ?>
                <i class="fas fa-hamburger"></i>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="container menu-section-padding" id="menuSection">
    <div class="filters" id="filterContainer">
        <button class="filter-btn active" data-category="all">Todos</button>
        <?php foreach ($categorias as $cat): ?>
            <button class="filter-btn" data-category="<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></button>
        <?php endforeach; ?>
    </div>

    <div class="menu-grid" id="productGrid">
        <?php foreach ($productos as $p): ?>
            <div class="product-card" data-category="<?= (int) $p['categoria_id'] ?>">
                <div class="product-img">
                    <?php if (!empty($p['imagen']) && file_exists(__DIR__ . '/uploads/productos/' . $p['imagen'])): ?>
                        <img src="<?= url('uploads/productos/' . htmlspecialchars($p['imagen'])) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>">
                    <?php else: ?>
                        <i class="<?= htmlspecialchars($p['imagen_icono'] ?? 'fas fa-hamburger') ?>"></i>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <h3><?= htmlspecialchars($p['nombre']) ?></h3>
                    <div class="price">
                        $<?= number_format((float) $p['precio_unitario'], 2) ?>
                        <span class="price-bs">Bs. <?= number_format((float) $p['precio_unitario'] * $tasa_dolar, 2) ?></span>
                    </div>
                    <div class="product-description"><?= htmlspecialchars($p['descripcion'] ?? '') ?></div>
                    <button class="add-btn"
                            data-id="<?= (int) $p['id'] ?>"
                            data-name="<?= htmlspecialchars($p['nombre']) ?>"
                            data-price="<?= (float) $p['precio_unitario'] ?>">
                        <i class="fas fa-plus-circle"></i> Agregar
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<div id="cartOverlay"></div>
<div id="cartOffcanvas">
    <div class="cart-header">
        <h3><i class="fas fa-shopping-bag"></i> Tu pedido</h3>
        <button id="closeCart" class="cart-close"><i class="fas fa-times"></i></button>
    </div>
    <div id="cartItems" class="cart-items-container"></div>
    <div class="cart-total-row cart-total-columns">
        <div class="cart-total-flex">
            <span>Total</span>
            <span id="cartTotalPrice">$0.00</span>
        </div>
        <div class="cart-total-bs" id="cartTotalBs">
            Bs. 0,00
        </div>
    </div>
    <button id="checkoutBtn" class="cart-checkout-btn">
        <i class="fas fa-check-circle"></i> Finalizar pedido
    </button>
</div>

<div id="modalExtras">
    <div class="modal-extras-box">
        <div class="modal-extras-header">
            <h3 id="modalProductoNombre">Personaliza tu producto</h3>
            <button id="cerrarModalExtrasBtn" class="modal-extras-close"><i class="fas fa-times"></i></button>
        </div>
        <div id="modalExtrasLista" class="modal-extras-contenido">
            <div class="modal-extras-cargando">Cargando opciones...</div>
        </div>
        <div class="modal-extras-footer">
            <div class="modal-extras-linea">
                <span>Precio base</span><span id="modalPrecioBase">$0.00</span>
            </div>
            <div class="modal-extras-linea modal-extras-linea-sub">
                <span>Extras seleccionados</span><span id="modalTotalExtras">+$0.00</span>
            </div>
            <div class="modal-extras-total">
                <span>Total</span><span id="modalTotalFinal">$0.00</span>
            </div>
            <button id="btnAgregarExtras" class="modal-extras-btn">
                <span class="btn-texto"><i class="fas fa-cart-plus"></i> Agregar al carrito</span>
                <span class="btn-precio" id="btnPrecioCarrito">$0.00</span>
            </button>
        </div>
    </div>
</div>

<div id="welcomeModal">
    <div class="modal-content">
        <div class="welcome-icon">
            <?php if (!empty($logo_tienda)): ?>
                <img src="<?= htmlspecialchars($logo_tienda) ?>" alt="<?= htmlspecialchars($nombre_negocio_tienda) ?>" style="width: 72px; height: 72px; object-fit: contain; border-radius: 16px;">
            <?php else: ?>
                🍔
            <?php endif; ?>
        </div>
        <h2>Bienvenido a <span><?= htmlspecialchars($nombre_negocio_tienda) ?></span>!</h2>
        <p>Arma tu pedido facilmente. <strong>No pagas aqui todavia</strong>, primero armas el carrito y despues coordinamos.</p>
        <div class="highlight">
            Registrate o inicia sesion, agrega productos y finaliza tu pedido.
        </div>
        <button class="btn-primary" id="welcomeAcceptBtn">Ok, ver el menu</button>
        <div class="checkbox-container">
            <input type="checkbox" id="welcomeDontShowAgain">
            <label for="welcomeDontShowAgain">No volver a mostrar</label>
        </div>
    </div>
</div>

<script>
window.MINAS_CONFIG = {
    isLoggedIn: <?= $isLoggedIn ? 'true' : 'false' ?>,
    tasaDolar: <?= json_encode((float) $tasa_dolar) ?>,
    URLs: {
        carritoObtener: '<?= url('api/obtener_carrito.php') ?>',
        carritoActualizar: '<?= url('api/actualizar_carrito.php') ?>',
        extras: '<?= url('api/obtener_extras.php') ?>',
        notificaciones: '<?= url('api/notificaciones.php') ?>',
        notifLista: '<?= url('api/ver_notificaciones.php') ?>',
        login: '<?= url('auth/login.php') ?>',
        checkout: '<?= url('cliente/checkout.php') ?>',
        dashboard: '<?= url('cliente/dashboard.php') ?>'
    }
};
</script>

<?php partial('footer', ['js_extra_footer' => ['js/index.js']]); ?>