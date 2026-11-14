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

// Auto-migración: asegurar columnas telefono_whatsapp, logo y banner en configuracion_pago
try {
    $col_wa = $pdo->query("SHOW COLUMNS FROM configuracion_pago LIKE 'telefono_whatsapp'")->fetch();
    if (!$col_wa) {
        $pdo->exec("ALTER TABLE configuracion_pago ADD COLUMN telefono_whatsapp VARCHAR(20) DEFAULT NULL AFTER telefono");
        $pdo->exec("UPDATE configuracion_pago SET telefono_whatsapp = telefono WHERE id = 1");
    }
    $col_logo = $pdo->query("SHOW COLUMNS FROM configuracion_pago LIKE 'logo'")->fetch();
    if (!$col_logo) {
        $pdo->exec("ALTER TABLE configuracion_pago ADD COLUMN logo VARCHAR(255) DEFAULT NULL");
    }
    $col_banner = $pdo->query("SHOW COLUMNS FROM configuracion_pago LIKE 'banner'")->fetch();
    if (!$col_banner) {
        $pdo->exec("ALTER TABLE configuracion_pago ADD COLUMN banner VARCHAR(255) DEFAULT NULL");
    }
} catch (Exception $e) {
    error_log('Auto-migracion configuracion_pago: ' . $e->getMessage());
}

$dir_uploads_config = __DIR__ . '/../uploads/config/';
if (!is_dir($dir_uploads_config)) {
    @mkdir($dir_uploads_config, 0775, true);
}

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

$config_actual = $pdo->query("SELECT * FROM configuracion_pago WHERE id = 1")->fetch();

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
        $logo_final = $config_actual['logo'] ?? null;
        $banner_final = $config_actual['banner'] ?? null;

        // Opción para quitar logo o banner actual si marcó la casilla
        if (!empty($_POST['eliminar_logo']) && $logo_final) {
            if (file_exists($dir_uploads_config . $logo_final)) {
                @unlink($dir_uploads_config . $logo_final);
            }
            $logo_final = null;
        }
        if (!empty($_POST['eliminar_banner']) && $banner_final) {
            if (file_exists($dir_uploads_config . $banner_final)) {
                @unlink($dir_uploads_config . $banner_final);
            }
            $banner_final = null;
        }

        $mimes_permitidos = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif'
        ];

        // Procesar subida de LOGO (máx 3MB)
        if (!$error && isset($_FILES['logo']) &&$_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {$error = 'Error al subir el archivo del logo.';
            } elseif ($_FILES['logo']['size'] > 3 * 1024 * 1024) {$error = 'El logo no debe superar los 3 MB.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);$mime = $finfo->file($_FILES['logo']['tmp_name']);
                if (!isset($mimes_permitidos[$mime])) {$error = 'Formato de logo no permitido. Usa PNG, JPG, WEBP o GIF.';
                } else {
                    $nuevo_logo = 'logo_' . time() . '_' . mt_rand(100, 999) . '.' . $mimes_permitidos[$mime];
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir_uploads_config .$nuevo_logo)) {
                        if (!empty($config_actual['logo']) && file_exists($dir_uploads_config .$config_actual['logo'])) {
                            @unlink($dir_uploads_config .$config_actual['logo']);
                        }
                        $logo_final =$nuevo_logo;
                    } else {
                        $error = 'No se pudo guardar la imagen del logo en el servidor.';
                    }
                }
            }
        }

        // Procesar subida de BANNER (máx 5MB)
        if (!$error && isset($_FILES['banner']) &&$_FILES['banner']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['banner']['error'] !== UPLOAD_ERR_OK) {$error = 'Error al subir el archivo del banner.';
            } elseif ($_FILES['banner']['size'] > 5 * 1024 * 1024) {$error = 'El banner no debe superar los 5 MB.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);$mime = $finfo->file($_FILES['banner']['tmp_name']);
                if (!isset($mimes_permitidos[$mime])) {$error = 'Formato de banner no permitido. Usa JPG, PNG, WEBP o GIF.';
                } else {
                    $nuevo_banner = 'banner_' . time() . '_' . mt_rand(100, 999) . '.' . $mimes_permitidos[$mime];
                    if (move_uploaded_file($_FILES['banner']['tmp_name'], $dir_uploads_config .$nuevo_banner)) {
                        if (!empty($config_actual['banner']) && file_exists($dir_uploads_config .$config_actual['banner'])) {
                            @unlink($dir_uploads_config .$config_actual['banner']);
                        }
                        $banner_final =$nuevo_banner;
                    } else {
                        $error = 'No se pudo guardar la imagen del banner en el servidor.';
                    }
                }
            }
        }

        if (!$error) {
            $banco_texto =$bancos_ve[$banco_codigo];$cedula_completa = $tipo_cedula . '-' .$numero_cedula;

            try {
                $stmt =$pdo->prepare("
                    UPDATE configuracion_pago
                    SET banco = ?, telefono = ?, telefono_whatsapp = ?, cedula = ?, titular = ?, tasa_dolar = ?, logo = ?, banner = ?
                    WHERE id = 1
                ");
                $stmt->execute([
                    $banco_texto,$tel_pagomovil_valido,
                    $tel_whatsapp_valido,$cedula_completa,
                    $titular,$tasa_dolar,
                    $logo_final,$banner_final
                ]);
                $mensaje = 'Configuracion actualizada correctamente.';
            } catch (Exception $e) {
                error_log('Configuracion error: ' . $e->getMessage());$error = 'Error al guardar la configuracion.';
            }
        }
    }

    if ($mensaje ||$error) {
        $_SESSION['flash_mensaje'] =$mensaje;
        $_SESSION['flash_error'] =$error;
        redirect('admin/configuracion.php');
    }
}

$config =$pdo->query("SELECT * FROM configuracion_pago WHERE id = 1")->fetch();

$prefijo_actual = substr($config['telefono'] ?? '0414', 0, 4);
$numero_actual = substr($config['telefono'] ?? '1234567', 4, 7);

$tel_wa_guardado = !empty($config['telefono_whatsapp']) ? $config['telefono_whatsapp'] : ($config['telefono'] ?? '04141234567');
$prefijo_wa_actual = substr($tel_wa_guardado, 0, 4);
$numero_wa_actual = substr($tel_wa_guardado, 4, 7);

$partes_cedula = explode('-',$config['cedula'] ?? 'V-12345678');
$tipo_cedula_actual =$partes_cedula[0] ?? 'V';
$numero_cedula_actual =$partes_cedula[1] ?? '12345678';

$tiene_logo = !empty($config['logo']) && file_exists($dir_uploads_config .$config['logo']);
$tiene_banner = !empty($config['banner']) && file_exists($dir_uploads_config .$config['banner']);

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

        <form method="post" enctype="multipart/form-data" novalidate>
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

            <!-- IDENTIDAD VISUAL: LOGO Y BANNER -->
            <div class="branding-section">
                <h4 class="branding-subtitulo"><i class="fas fa-palette"></i> Identidad Visual (Menu y Tienda)</h4>
                <div class="form-grid">
                    <!-- SUBIR LOGO -->
                    <div class="campo-form branding-card">
                        <label><i class="fas fa-image"></i> Logo del Negocio</label>
                        <div class="branding-preview-box logo-preview-box" id="previewBoxLogo">
                            <?php if ($tiene_logo): ?>
                                <img src="<?= url('uploads/config/' . htmlspecialchars($config['logo'])) ?>" alt="Logo actual" id="imgPreviewLogo">
                            <?php else: ?>
                                <div class="branding-placeholder" id="placeholderLogo">
                                    <i class="fas fa-hamburger"></i>
                                    <span>Sin logo personalizado</span>
                                </div>
                                <img src="" alt="Vista previa logo" id="imgPreviewLogo" class="d-none">
                            <?php endif; ?>
                        </div>
                        <input type="file" name="logo" id="inputLogo" accept="image/png,image/jpeg,image/webp,image/gif" class="input-file-branding">
                        <small class="branding-hint">Se muestra en el menu lateral y en el banner del menu (PNG o JPG cuadrado recomendado).</small>
                        <?php if ($tiene_logo): ?>
                            <label class="check-eliminar-img">
                                <input type="checkbox" name="eliminar_logo" value="1"> Quitar logo actual
                            </label>
                        <?php endif; ?>
                    </div>

                    <!-- SUBIR BANNER -->
                    <div class="campo-form branding-card">
                        <label><i class="fas fa-panorama"></i> Banner del Menu</label>
                        <div class="branding-preview-box banner-preview-box" id="previewBoxBanner">
                            <?php if ($tiene_banner): ?>
                                <img src="<?= url('uploads/config/' . htmlspecialchars($config['banner'])) ?>" alt="Banner actual" id="imgPreviewBanner">
                            <?php else: ?>
                                <div class="branding-placeholder" id="placeholderBanner">
                                    <i class="fas fa-store"></i>
                                    <span>Sin banner personalizado</span>
                                </div>
                                <img src="" alt="Vista previa banner" id="imgPreviewBanner" class="d-none">
                            <?php endif; ?>
                        </div>
                        <input type="file" name="banner" id="inputBanner" accept="image/png,image/jpeg,image/webp,image/gif" class="input-file-branding">
                        <small class="branding-hint">Se muestra en la cabecera del menu y en la portada de la tienda (Horizontal recomendado).</small>
                        <?php if ($tiene_banner): ?>
                            <label class="check-eliminar-img">
                                <input type="checkbox" name="eliminar_banner" value="1"> Quitar banner actual
                            </label>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- BANCO -->
            <div class="campo-form">
                <label>Banco (PagoMovil) *</label>
                <select name="banco" required>
                    <option value="">Selecciona un banco</option>
                    <?php foreach ($bancos_ve as $codigo =>$nombre): ?>
                        <option value="<?= $codigo ?>" <?= ($config['banco'] ?? '') ===$nombre ? 'selected' : '' ?>>
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
                            <?php foreach ($prefijos_tel as$pref): ?>
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
                            <?php foreach ($tipos_cedula as $tipo =>$etiqueta): ?>
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

                <!-- TELEFONO WHATSAPP DEL NEGOCIO -->
                <div class="campo-form">
                    <label><i class="fab fa-whatsapp" style="color: #25D366;"></i> Telefono de WhatsApp (Contacto / Soporte) *</label>
                    <div class="input-inline-flex">
                        <select name="prefijo_wa" required class="select-prefijo-tel">
                            <?php foreach ($prefijos_tel as$pref): ?>
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