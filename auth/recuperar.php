<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';
require_once __DIR__ . '/../includes/correo.php';

if (usuarioLogueado()) {
    if (esAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('cliente/dashboard.php');
    }
}

$error = '';
$exito = '';
$enlace_respaldo = '';

// Obtener teléfono de WhatsApp del negocio desde configuracion_pago
$tel_wa_negocio = '584141234567';
try {
    $config_negocio = $pdo->query("SELECT telefono, telefono_whatsapp FROM configuracion_pago WHERE id = 1 LIMIT 1")->fetch();
    $tel_elegido = !empty($config_negocio['telefono_whatsapp']) ? $config_negocio['telefono_whatsapp'] : ($config_negocio['telefono'] ?? '04141234567');
    $tel_negocio_raw = preg_replace('/\D/', '', $tel_elegido);
    if (!empty($tel_negocio_raw)) {
        $tel_wa_negocio = '58' . ltrim($tel_negocio_raw, '0');
    }
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = v_email($_POST['email'] ?? '');

    if ($email === null) {
        $error = 'Ingresa un correo electronico valido.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, email, password_hash, activo FROM usuarios WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                if (intval($user['activo']) === 0) {
                    $error = 'Esta cuenta se encuentra suspendida. Contacta al soporte.';
                } else {
                    $expira_ts = time() + 3600;
                    $payload = $user['id'] . '|' . $expira_ts;
                    $firma = hash_hmac('sha256', $payload . '|' . $user['password_hash'], 'minas_burguer_secret_key_2026');
                    $token = base64_encode($payload . '|' . $firma);

                    $es_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
                    $protocolo = $es_https ? 'https://' : 'http://';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $ruta_relativa = url('auth/resetear_password.php?token=' . urlencode($token));

                    if (strpos($ruta_relativa, 'http') === 0) {
                        $enlace_absoluto = $ruta_relativa;
                    } else {
                        $enlace_absoluto = $protocolo . $host . '/' . ltrim($ruta_relativa, '/');
                    }

                    $detalle_error_api = '';
                    $enviado = enviar_correo_recuperacion($user['email'], $enlace_absoluto, $detalle_error_api);

                    if ($enviado) {
                        $exito = 'Hemos enviado un enlace de recuperacion a tu correo electronico. Revisa tu bandeja de entrada o la carpeta de Spam.';
                    } else {
                        $exito = 'Aviso EmailJS (' . $detalle_error_api . '). Puedes usar el enlace temporal aqui abajo:';
                        $enlace_respaldo = $enlace_absoluto;
                    }
                }
            } else {
                $exito = 'Si el correo esta registrado, recibiras un enlace para restablecer tu contrasena.';
            }
        } catch (PDOException $e) {
            error_log('Recuperar password error: ' . $e->getMessage());
            $error = 'Error al procesar la solicitud. Intenta de nuevo.';
        }
    }
}

partial('head', [
    'titulo_pagina' => 'Recuperar contrasena',
    'css_pagina' => 'auth'
]);
?>
<div class="auth-container">
    <div class="auth-card">
        <h2><i class="fas fa-key marca-acento"></i> Recuperar</h2>
        <p class="subtitulo">Ingresa tu correo para recibir el enlace de recuperacion</p>

        <?php if ($error): ?>
            <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($exito): ?>
            <div class="mensaje-exito"><?= htmlspecialchars($exito) ?></div>
        <?php endif; ?>

        <?php if ($enlace_respaldo): ?>
            <div class="enlace-recuperacion-box">
                <strong><i class="fas fa-link"></i> Enlace temporal de respaldo (Valido por 1 hora):</strong><br>
                <a href="<?= htmlspecialchars($enlace_respaldo) ?>" class="enlace-recuperacion-link">
                    Haz clic aqui para crear tu nueva contrasena
                </a>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="form-group">
                <label for="email">Email registrado</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="ejemplo@correo.com" required autocomplete="off">
            </div>
            <button type="submit" class="btn-submit">Enviar enlace al correo</button>
        </form>

        <div class="link-inferior">
            <a href="https://wa.me/<?= htmlspecialchars($tel_wa_negocio) ?>?text=<?= urlencode("¡Hola Mina's Burguers! Necesito ayuda para recuperar el acceso a mi cuenta.") ?>" target="_blank" rel="noopener noreferrer" class="link-wa-soporte">
                <i class="fab fa-whatsapp icono-wa"></i> ¿Problemas con tu correo? Escribenos por WhatsApp
            </a>
        </div>

        <div class="auth-link">
            <a href="<?= url('auth/login.php') ?>"><i class="fas fa-arrow-left"></i> Volver al inicio de sesion</a>
        </div>
    </div>
</div>

<?php partial('footer'); ?>