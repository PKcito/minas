<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';

if (!usuarioLogueado()) { redirect('auth/login.php'); }

$usuario_id = $_SESSION['usuario_id'];
$carrito = $_SESSION['carrito'] ?? [];

if (empty($carrito)) {
    if (!empty($_SESSION['pedido_finalizado_id'])) {
        redirect('cliente/historial.php');
    }
    redirect('index.php');
}

if (isset($_SESSION['checkout_data']['comprobante'])) {
    redirect('cliente/comprobante_pago.php');
}

unset($_SESSION['pedido_finalizado_id']);

$total = 0;
foreach ($carrito as $item) {
    $total += floatval($item['precio']) * intval($item['cantidad']);
}

$pref_tipo_entrega = $_SESSION['checkout_data']['tipo_entrega'] ?? 'recoger';
$pref_direccion_id = intval($_SESSION['checkout_data']['direccion_id'] ?? 0);
$pref_metodo_pago = $_SESSION['checkout_data']['metodo_pago'] ?? '';
$pref_monto_pagado = floatval($_SESSION['checkout_data']['monto_pagado'] ?? 0);
if ($pref_monto_pagado <= 0) $pref_monto_pagado = round($total * 0.5, 2);

$nueva_dir_id = v_int($_GET['direccion_agregada'] ?? 0, 0);
if ($nueva_dir_id && $nueva_dir_id > 0) {
    $pref_direccion_id = $nueva_dir_id;
}

$stmt = $pdo->prepare("
    SELECT d.*, td.nombre AS tipo_nombre
    FROM direcciones d
    JOIN tipos_direccion td ON d.tipo_direccion_id = td.id
    JOIN clientes c ON d.cliente_id = c.id
    WHERE c.usuario_id = ?
    ORDER BY d.predeterminada DESC, d.id
");
$stmt->execute([$usuario_id]);
$direcciones = $stmt->fetchAll();

if ($pref_direccion_id === 0) {
    foreach ($direcciones as $d) {
        if ($d['predeterminada']) {
            $pref_direccion_id = intval($d['id']);
            break;
        }
    }
}

$config_pago = $pdo->query("SELECT * FROM configuracion_pago WHERE activo = 1 LIMIT 1")->fetch();
$tasa_dolar = floatval($config_pago['tasa_dolar'] ?? 1);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    $tipo_entrega = $_POST['tipo_entrega'] ?? '';
    $direccion_id = v_int($_POST['direccion_id'] ?? 0, 0);
    $direccion_texto = '';
    $metodo_pago = $_POST['metodo_pago'] ?? '';
    $monto_pagado = v_float($_POST['monto_pagado'] ?? 0, 0, 99999.99, 2);

    // Mantener la selección del usuario en caso de error de validación
    $pref_tipo_entrega = in_array($tipo_entrega, ['delivery', 'recoger'], true) ? $tipo_entrega : $pref_tipo_entrega;
    $pref_direccion_id = $direccion_id ?: $pref_direccion_id;
    $pref_metodo_pago = in_array($metodo_pago, ['efectivo', 'pago_movil'], true) ? $metodo_pago : $pref_metodo_pago;
    if ($monto_pagado !== null && $monto_pagado > 0) {
        $pref_monto_pagado = $monto_pagado;
    }

    // Verificar que la dirección seleccionada exista y pertenezca al usuario en sesión
    $direccion_valida = false;
    if ($tipo_entrega === 'delivery' && $direccion_id && $direccion_id > 0) {
        foreach ($direcciones as $d) {
            if (intval($d['id']) === $direccion_id) {
                $direccion_texto = $d['calle'] . ' — ' . $d['parroquia'] . ', ' . $d['municipio'] . ', ' . $d['estado'];
                if (!empty($d['referencia'])) {
                    $direccion_texto .= ' (Ref: ' . $d['referencia'] . ')';
                }
                $direccion_valida = true;
                break;
            }
        }
    }

    if (!in_array($tipo_entrega, ['delivery', 'recoger'], true)) {
        $error = 'Selecciona un tipo de entrega valido.';
    } elseif ($tipo_entrega === 'delivery' && !$direccion_valida) {
        $error = 'Selecciona o agrega una direccion de entrega valida.';
    } elseif (!in_array($metodo_pago, ['efectivo', 'pago_movil'], true)) {
        $error = 'Selecciona un metodo de pago valido.';
    } elseif ($metodo_pago === 'efectivo' && $monto_pagado === null) {
        $error = 'El monto del adelanto no es valido.';
    } elseif ($metodo_pago === 'efectivo') {
        $minimo = round($total * 0.5, 2);
        if ($monto_pagado < $minimo) $error = 'El adelanto debe ser al menos 50% ($' . number_format($minimo, 2) . ').';
        if ($monto_pagado > $total) $error = 'El monto no puede superar el total.';
    }

    if (empty($error)) {
        $_SESSION['checkout_data'] = [
            'tipo_entrega'    => $tipo_entrega,
            'direccion_id'    => $tipo_entrega === 'delivery' ? $direccion_id : 0,
            'direccion_texto' => $tipo_entrega === 'delivery' ? $direccion_texto : 'Retiro en local',
            'metodo_pago'     => $metodo_pago,
            'monto_pagado'    => $metodo_pago === 'efectivo' ? $monto_pagado : $total,
        ];
        redirect_prg('cliente/comprobante_pago.php');
    }
}

partial('head', [
    'titulo_pagina' => 'Checkout',
    'cargar_sweetalert' => true,
    'css_pagina' => 'cliente',
    'css_extra' => ['css/checkout.css']
]);
?>

<?php partial('menu', ['menu_activo' => 'tienda']); ?>
<?php partial('header', [
    'titulo_pagina' => 'Finalizar pedido',
    'mostrar_salir' => false,
    'mostrar_volver' => ['url' => 'index.php', 'texto' => 'Volver']
]); ?>

<div class="container checkout-container">
    <h2 class="titulo-admin checkout-titulo"><i class="fas fa-clipboard-check"></i> Finalizar pedido</h2>

    <?php if ($error): ?>
        <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
        <input type="hidden" name="confirmar" value="1">

        <!-- PASO 1: ENTREGA -->
        <div class="step">
            <h3><i class="fas fa-truck"></i> Como recibiras tu pedido?</h3>
            <div class="radio-cards">
                <label class="radio-card <?= $pref_tipo_entrega === 'delivery' ? 'seleccionada' : '' ?>" id="radioDelivery">
                    <input type="radio" name="tipo_entrega" value="delivery" <?= $pref_tipo_entrega === 'delivery' ? 'checked' : '' ?> onchange="toggleEntrega(this.value)">
                    <i class="fas fa-truck radio-icono"></i>
                    <div>
                        <span class="radio-texto">Delivery</span>
                        <span class="radio-sub">Te lo llevamos a tu casa</span>
                    </div>
                </label>
                <label class="radio-card <?= $pref_tipo_entrega === 'recoger' ? 'seleccionada' : '' ?>" id="radioRecoger">
                    <input type="radio" name="tipo_entrega" value="recoger" <?= $pref_tipo_entrega === 'recoger' ? 'checked' : '' ?> onchange="toggleEntrega(this.value)">
                    <i class="fas fa-store radio-icono"></i>
                    <div>
                        <span class="radio-texto">Recoger</span>
                        <span class="radio-sub">Pasa por el local</span>
                    </div>
                </label>
            </div>

            <div id="direccionContainer" class="<?= $pref_tipo_entrega === 'delivery' ? '' : 'hidden' ?>">
                <h4 class="direccion-titulo-h4">
                    <i class="fas fa-map-marker-alt"></i> Selecciona tu direccion
                </h4>

                <?php if (count($direcciones) > 0): ?>
                    <div class="direcciones-checkout">
                        <?php foreach ($direcciones as $d): ?>
                            <?php $sel = ($pref_direccion_id == $d['id']); ?>
                            <label class="direccion-checkout <?= $sel ? 'seleccionada' : '' ?>">
                                <input type="radio" name="direccion_id" value="<?= intval($d['id']) ?>" <?= $sel ? 'checked' : '' ?>>
                                <div class="dir-checkout-icono"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="dir-checkout-info">
                                    <div class="dir-checkout-titulo">
                                        <?= htmlspecialchars($d['calle']) ?>
                                        <span class="dir-checkout-tipo"><?= htmlspecialchars($d['tipo_nombre']) ?></span>
                                        <?php if ($d['predeterminada']): ?>
                                            <span class="dir-checkout-badge"><i class="fas fa-check"></i> Principal</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="dir-checkout-linea">
                                        <?= htmlspecialchars($d['parroquia']) ?>, <?= htmlspecialchars($d['municipio']) ?>
                                    </div>
                                    <div class="dir-checkout-linea">
                                        <?= htmlspecialchars($d['estado']) ?>
                                    </div>
                                    <?php if (!empty($d['referencia'])): ?>
                                        <div class="dir-checkout-linea linea-referencia">
                                            <i class="fas fa-info-circle icon-xs"></i> <?= htmlspecialchars($d['referencia']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="sin-direcciones-msg">No tienes direcciones guardadas.</p>
                <?php endif; ?>

                <a href="<?= url('cliente/perfil.php?return=checkout') ?>" class="btn-perfil" id="btnAgregarDireccion">
                    <i class="fas fa-plus-circle"></i> Agregar nueva direccion
                </a>
            </div>
        </div>

        <!-- PASO 2: PAGO -->
        <div class="step">
            <h3><i class="fas fa-credit-card"></i> Metodo de pago</h3>
            <div class="radio-cards">
                <label class="radio-card <?= $pref_metodo_pago === 'efectivo' ? 'seleccionada' : '' ?>" id="radioEfectivo">
                    <input type="radio" name="metodo_pago" value="efectivo" <?= $pref_metodo_pago === 'efectivo' ? 'checked' : '' ?> onchange="togglePago(this.value)">
                    <i class="fas fa-money-bill-wave radio-icono"></i>
                    <div>
                        <span class="radio-texto">Efectivo</span>
                        <span class="radio-sub">Adelanto 50%</span>
                    </div>
                </label>
                <label class="radio-card <?= $pref_metodo_pago === 'pago_movil' ? 'seleccionada' : '' ?>" id="radioPagoMovil">
                    <input type="radio" name="metodo_pago" value="pago_movil" <?= $pref_metodo_pago === 'pago_movil' ? 'checked' : '' ?> onchange="togglePago(this.value)">
                    <i class="fas fa-mobile-alt radio-icono"></i>
                    <div>
                        <span class="radio-texto">PagoMovil</span>
                        <span class="radio-sub">Pago total</span>
                    </div>
                </label>
            </div>

            <!-- INFO ADELANTO EN EFECTIVO -->
            <div id="efectivoInfo" class="<?= $pref_metodo_pago === 'efectivo' ? '' : 'hidden' ?>">
                <div class="mensaje-info">
                    <i class="fas fa-info-circle"></i> <strong>Importante:</strong> Debes cancelar el <strong>50%</strong> por adelantado vía PagoMóvil. El resto lo pagas en efectivo al recibir.
                </div>
                <div class="campo-form">
                    <label>Monto del adelanto (minimo $<?= number_format($total * 0.5, 2) ?>)</label>
                    <div class="input-number-group">
                        <button type="button" class="input-number-btn" data-action="menos" data-target="monto_pagado" data-step="0.5" aria-label="Disminuir monto">
                            <i class="fas fa-minus"></i>
                        </button>
                        <div class="input-number-wrapper">
                            <span class="input-number-prefix">$</span>
                            <input type="number" step="0.01" name="monto_pagado" id="monto_pagado"
                                   min="<?= number_format($total * 0.5, 2, '.', '') ?>"
                                   max="<?= number_format($total, 2, '.', '') ?>"
                                   value="<?= number_format($pref_monto_pagado, 2, '.', '') ?>">
                        </div>
                        <button type="button" class="input-number-btn" data-action="mas" data-target="monto_pagado" data-step="0.5" aria-label="Aumentar monto">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <small class="ayuda-texto">
                        <i class="fas fa-info-circle"></i> Puedes ajustar el monto entre el 50% y el 100% del total
                    </small>
                </div>
                <?php if ($config_pago): ?>
                    <div class="datos-pago-box">
                        <h4><i class="fas fa-university"></i> Datos para el PagoMovil</h4>
                        <div class="dato"><strong>Banco:</strong> <span><?= htmlspecialchars($config_pago['banco']) ?></span></div>
                        <div class="dato"><strong>Telefono:</strong> <span><?= htmlspecialchars($config_pago['telefono']) ?></span></div>
                        <div class="dato"><strong>Cedula / RIF:</strong> <span><?= htmlspecialchars($config_pago['cedula']) ?></span></div>
                        <div class="dato"><strong>Titular:</strong> <span><?= htmlspecialchars($config_pago['titular']) ?></span></div>
                        <div class="dato"><strong>Monto a transferir:</strong> <span class="monto-destacado" id="monto_efectivo_span">$<?= number_format($pref_monto_pagado, 2) ?></span></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- INFO PAGO MÓVIL TOTAL -->
            <div id="pagoMovilInfo" class="<?= $pref_metodo_pago === 'pago_movil' ? '' : 'hidden' ?>">
                <?php if ($config_pago): ?>
                    <div class="datos-pago-box">
                        <h4><i class="fas fa-university"></i> Datos para el PagoMovil</h4>
                        <div class="dato"><strong>Banco:</strong> <span><?= htmlspecialchars($config_pago['banco']) ?></span></div>
                        <div class="dato"><strong>Telefono:</strong> <span><?= htmlspecialchars($config_pago['telefono']) ?></span></div>
                        <div class="dato"><strong>Cedula / RIF:</strong> <span><?= htmlspecialchars($config_pago['cedula']) ?></span></div>
                        <div class="dato"><strong>Titular:</strong> <span><?= htmlspecialchars($config_pago['titular']) ?></span></div>
                        <div class="dato"><strong>Monto total ($):</strong> <span class="monto-destacado">$<?= number_format($total, 2) ?></span></div>
                        <?php if ($tasa_dolar > 0): ?>
                            <div class="dato"><strong>Equivalente (Bs.):</strong> <span class="monto-destacado">Bs. <?= number_format($total * $tasa_dolar, 2) ?></span></div>
                        <?php endif; ?>
                    </div>
                    <p class="pago-movil-nota">
                        <i class="fas fa-camera"></i> En el siguiente paso podras adjuntar la captura del comprobante de pago.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- PASO 3: RESUMEN DEL PEDIDO -->
        <div class="step">
            <h3><i class="fas fa-shopping-bag"></i> Resumen de tu pedido</h3>
            <?php foreach ($carrito as $item): ?>
                <div class="resumen-item">
                    <span><?= htmlspecialchars($item['nombre']) ?> <strong>x<?= intval($item['cantidad']) ?></strong></span>
                    <strong>$<?= number_format(floatval($item['precio']) * intval($item['cantidad']), 2) ?></strong>
                </div>
                <?php if (!empty($item['extras'])): ?>
                    <div class="resumen-extras">
                        <?php foreach ($item['extras'] as $ex): ?>
                            + <?= htmlspecialchars($ex['nombre']) ?> ($<?= number_format(floatval($ex['precio']), 2) ?>)<br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="resumen-total">
                <span>Total a pagar</span>
                <span>$<?= number_format($total, 2) ?></span>
            </div>
            <?php if ($tasa_dolar > 0): ?>
                <div class="resumen-item" style="border-bottom: none; padding-top: 0.25rem; font-size: 0.85rem; color: var(--texto-secundario);">
                    <span>Total en Bolívares (Tasa: Bs. <?= number_format($tasa_dolar, 2) ?>)</span>
                    <strong>Bs. <?= number_format($total * $tasa_dolar, 2) ?></strong>
                </div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn-confirmar">
            <i class="fas fa-arrow-right"></i> Continuar al comprobante de pago
        </button>
    </form>
</div>

<?php partial('footer', ['js_extra_footer' => ['js/checkout.js']]); ?>