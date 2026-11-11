<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';

if (!esAdmin()) {
    redirect('index.php');
}
requierePermiso('configuracion');

$mensaje = $_SESSION['flash_mensaje'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_mensaje'], $_SESSION['flash_error']);

// Asegurar que exista la columna telefono_whatsapp en configuracion_pago automáticamente
try {
    $col_check = $pdo->query("SHOW COLUMNS FROM configuracion_pago LIKE 'telefono_whatsapp'")->fetch();
    if (!$col_check) {
        $pdo->exec("ALTER TABLE configuracion_pago ADD COLUMN telefono_whatsapp VARCHAR(20) DEFAULT NULL AFTER telefono");
        $pdo->exec("UPDATE configuracion_pago SET telefono_whatsapp = telefono WHERE id = 1");
    }
} catch (Exception $e) {
    error_log('Auto-migracion telefono_whatsapp: ' . $e->getMessage());
}

// Lista de bancos de Venezuela
$bancos_ve = [
    '0102' => 'Banco de Venezuela',
    '0105' => 'Mercantil',
    '0108' => 'BBVA Provincial',
    '0114' => 'Bancaribe',
    '0115' => 'Banco Exterior',
    '0128' => 'Banco Caroní',
    '0134' => 'Banesco',
    '0137' => 'Banco Sofitasa',
    '0138' => 'Banco Plaza',
    '0146' => 'Bangente',
    '0151' => 'BFC Banco Fondo Común',
    '0156' => '100% Banco',
    '0157' => 'DelSur',
    '0163' => 'Banco del Tesoro',
    '0166' => 'Banco Agrícola de Venezuela',
    '0168' => 'Bancrecer',
    '0169' => 'Mi Banco',
    '0171' => 'Banco Activo',
    '0172' => 'Bancamiga',
    '0174' => 'Banplus',
    '0175' => 'Banco Bicentenario',
    '0177' => 'Banco Fanb',
    '0191' => 'Banco Nacional de Crédito (BNC)'
];

$prefijos_tel = ['0412', '0414', '0416', '0422', '0424', '0426'];
$tipos_cedula = ['V' => 'V - Venezolano', 'E' => 'E - Extranjero', 'J' => 'J - Jurídico'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $banco_codigo = $_POST['banco'] ?? '';
    $prefijo_tel = $_POST['prefijo_tel'] ?? '';
    $numero_tel = trim($_POST['numero_tel'] ?? '');
    $prefijo_wa = $_POST['prefijo_wa'] ?? '';
    $numero_wa = trim($_POST['numero_wa'] ?? '');
    $tipo_cedula = $_POST['tipo_cedula'] ?? 'V';
    $numero_cedula = trim($_POST['numero_cedula'] ?? '');
    $titular = v_str($_POST['titular'] ?? '', 2, 60, true, true);
    $tasa_dolar = v_float($_POST['tasa_dolar'] ?? 1, 0.01, 9999.99, 2);

    $tel_pagomovil_valido = v_telefono_ve($prefijo_tel, $numero_tel);
    $tel_whatsapp_valido = v_telefono_ve($prefijo_wa, $numero_wa);
    $len_ced = strlen($numero_cedula);

    if (array_key_exists($banco_codigo, $bancos_ve) === false) {
        $error = 'Selecciona un banco valido.';
    } elseif ($tel_pagomovil_valido === null) {
        $error = 'El telefono de PagoMovil no es valido (prefijo venezolano y 7 digitos).';
    } elseif ($tel_whatsapp_valido === null) {
        $error = 'El telefono de WhatsApp no es valido (prefijo venezolano y 7 digitos).';
    } elseif (array_key_exists($tipo_cedula, $tipos_cedula) === false) {
        $error = 'Selecciona un tipo de cedula valido.';
    } elseif ($len_ced < 5 || $len_ced > 9 || ctype_digit($numero_cedula) === false) {
        $error = 'El numero de cedula debe tener entre 5 y 9 digitos (solo numeros).';
    } elseif ($titular === null) {
        $error = 'El titular debe tener entre 2 y 60 caracteres.';
    } elseif ($tasa_dolar === null) {
        $error = 'La tasa del dolar no es valida.';
    } else {
        $banco_texto = $bancos_ve[$banco_codigo];
        $cedula_completa = $tipo_cedula . '-' . $numero_cedula;

        try {
            $stmt = $pdo->prepare("UPDATE configuracion_pago SET banco = ?, telefono = ?, telefono_whatsapp = ?, cedula = ?, titular = ?, tasa_dolar = ? WHERE id = 1");
            $stmt->execute([$banco_texto, $tel_pagomovil_valido, $tel_whatsapp_valido, $cedula_completa, $titular, $tasa_dolar]);
            $mensaje = 'Configuracion actualizada correctamente.';
        } catch (Exception $e) {
            error_log('Configuracion error: ' . $e->getMessage());
            $error = 'Error al guardar la configuracion.';
        }
    }

    if ($mensaje || $error) {
        $_SESSION['flash_mensaje'] = $mensaje;
        $_SESSION['flash_error'] = $error;
        redirect('admin/configuracion.php');
    }
}

$config = $pdo->query("SELECT * FROM configuracion_pago WHERE id = 1")->fetch();

// Separar el telefono de PagoMovil (ej: 04141234567)
$prefijo_actual = substr($config['telefono'] ?? '0414', 0, 4);
$numero_actual = substr($config['telefono'] ?? '1234567', 4, 7);

// Separar el telefono de WhatsApp (si está vacío, usa el de PagoMovil por defecto)
$tel_wa_guardado = !empty($config['telefono_whatsapp']) ? $config['telefono_whatsapp'] : ($config['telefono'] ?? '04141234567');
$prefijo_wa_actual = substr($tel_wa_guardado, 0, 4);
$numero_wa_actual = substr($tel_wa_guardado, 4, 7);

// Separar la cedula guardada (ej: V-12345678)
$partes_cedula = explode('-', $config['cedula'] ?? 'V-12345678');
$tipo_cedula_actual = $partes_cedula[0] ?? 'V';
$numero_cedula_actual = $partes_cedula[1] ?? '12345678';

partial('head', [
    'titulo_pagina' => 'Configuracion',
    'cargar_sweetalert' => true,
    'css_pagina' => 'admin',
    'css_extra' => ['css/configuracion.css']
]);
?>
<?php partial('menu', ['menu_activo' => 'configuracion']); ?>
<?php partial('header', ['titulo_pagina' => 'Configuracion']); ?>

<main class="container config-main">
    <div class="tabla-card config-card">
        <h3 class="config-titulo"><i class="fas fa-cogs"></i> Configuracion del Sistema</h3>

        <?php if ($mensaje): ?><div data-flash-success="<?= htmlspecialchars($mensaje) ?>" class="d-none"></div><?php endif; ?>
        <?php if ($error): ?><div data-flash-error="<?= htmlspecialchars($error) ?>" class="d-none"></div><?php endif; ?>

        <form method="post" novalidate>
            <!-- TASA DEL DOLAR -->
            <div class="campo-form tasa-box">
                <label class="tasa-label">Tasa del Dolar (Bs.) *</label>
                <div class="input-number-group tasa-group">
                    <button type="button" class="input-number-btn tasa-btn" data-action="menos" data-target="tasa_dolar" data-step="0.10">
                        <i class="fas fa-minus"></i>
                    </button>
                    <div class="input-number-wrapper tasa-wrapper">
                        <span class="input-number-prefix tasa-prefix">Bs.</span>
                        <input type="number" step="0.01" min="0.01" max="9999.99" name="tasa_dolar" id="tasa_dolar" value="<?= htmlspecialchars($config['tasa_dolar'] ?? '1.00') ?>" required class="tasa-input">
                    </div>
                    <button type="button" class="input-number-btn tasa-btn" data-action="mas" data-target="tasa_dolar" data-step="0.10">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <small class="tasa-hint">Esta tasa se mostrara a los clientes en la tienda y en la comanda.</small>
            </div>

            <!-- BANCO -->
            <div class="campo-form">
                <label>Banco (PagoMovil) *</label>
                <select name="banco" required>
                    <option value="">Selecciona un banco</option>
                    <?php foreach ($bancos_ve as $codigo => $nombre): ?>
                        <option value="<?= $codigo ?>" <?= ($config['banco'] ?? '') === $nombre ? 'selected' : '' ?>>
                            <?= htmlspecialchars($nombre) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grid">
                <!-- TELEFONO PAGOMOVIL -->
                <div class="campo-form">
                    <label>Telefono (PagoMovil) *</label>
                    <div class="input-inline-flex">
                        <select name="prefijo_tel" required class="select-prefijo-tel">
                            <?php foreach ($prefijos_tel as $pref): ?>
                                <option value="<?= $pref ?>" <?= $prefijo_actual === $pref ? 'selected' : '' ?>><?= $pref ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="tel" name="numero_tel" value="<?= htmlspecialchars($numero_actual) ?>" required pattern="[0-9]{7}" maxlength="7" minlength="7" inputmode="numeric" placeholder="1234567" class="input-flex-1">
                    </div>
                </div>

                <!-- CEDULA -->
                <div class="campo-form">
                    <label>Cedula / RIF (PagoMovil) *</label>
                    <div class="input-inline-flex">
                        <select name="tipo_cedula" required class="select-tipo-cedula">
                            <?php foreach ($tipos_cedula as $tipo => $etiqueta): ?>
                                <option value="<?= $tipo ?>" <?= $tipo_cedula_actual === $tipo ? 'selected' : '' ?>><?= $etiqueta ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="numero_cedula" value="<?= htmlspecialchars($numero_cedula_actual) ?>" required pattern="[0-9]{5,9}" maxlength="9" minlength="5" inputmode="numeric" placeholder="12345678" class="input-flex-1">
                    </div>
                </div>
            </div>

            <div class="form-grid">
                <!-- TITULAR -->
                <div class="campo-form">
                    <label>Titular de la cuenta *</label>
                    <input type="text" name="titular" value="<?= htmlspecialchars($config['titular'] ?? '') ?>" required maxlength="60" placeholder="Ej: Minas Burguer C.A. o Abraham Febres">
                </div>

                <!-- TELEFONO WHATSAPP DEL NEGOCIO (APARTE) -->
                <div class="campo-form">
                    <label><i class="fab fa-whatsapp" style="color: #25D366;"></i> Telefono de WhatsApp (Contacto / Soporte) *</label>
                    <div class="input-inline-flex">
                        <select name="prefijo_wa" required class="select-prefijo-tel">
                            <?php foreach ($prefijos_tel as $pref): ?>
                                <option value="<?= $pref ?>" <?= $prefijo_wa_actual === $pref ? 'selected' : '' ?>><?= $pref ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="tel" name="numero_wa" value="<?= htmlspecialchars($numero_wa_actual) ?>" required pattern="[0-9]{7}" maxlength="7" minlength="7" inputmode="numeric" placeholder="1234567" class="input-flex-1">
                    </div>
                    <small class="tasa-hint">Numero al que escribiran los clientes desde el menu y soporte.</small>
                </div>
            </div>

            <button type="submit" class="btn-admin btn-guardar-config"><i class="fas fa-save"></i> Guardar Configuracion</button>
        </form>
    </div>
</main>

<?php partial('footer', ['cargar_admin_ux' => true, 'js_extra_footer' => ['js/configuracion.js']]); ?>