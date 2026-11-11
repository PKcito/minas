<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';

if (usuarioLogueado()) {
    if (esAdmin()) { redirect('admin/dashboard.php'); }
    else { redirect('cliente/dashboard.php'); }
}

$token_raw = $_GET['token'] ?? '';
$error = '';
$exito = '';
$token_valido = false;
$user = null;

if (!empty($token_raw)) {
    $decoded = base64_decode($token_raw, true);
    if ($decoded !== false) {
        $partes = explode('|', $decoded);
        if (count($partes) === 3) {
            list($uid, $expira_ts, $firma_recibida) = $partes;
            $uid = intval($uid);
            $expira_ts = intval($expira_ts);

            if ($uid > 0 && time() <= $expira_ts) {
                $stmt = $pdo->prepare("SELECT id, email, password_hash, activo FROM usuarios WHERE id = ? LIMIT 1");
                $stmt->execute([$uid]);
                $u = $stmt->fetch();

                if ($u && intval($u['activo']) === 1) {
                    $firma_esperada = hash_hmac('sha256', $uid . '|' . $expira_ts . '|' . $u['password_hash'], 'minas_burguer_secret_key_2026');
                    if (hash_equals($firma_esperada, $firma_recibida)) {
                        $token_valido = true;
                        $user = $u;
                    }
                }
            } else {
                $error = 'El enlace de recuperacion ha expirado. Solicita uno nuevo.';
            }
        }
    }
}

if (!$token_valido && empty($error)) {
    $error = 'El enlace de recuperacion no es valido o ya fue utilizado.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido && $user) {
    $password_raw = $_POST['password'] ?? '';
    $confirmar_raw = $_POST['confirmar_password'] ?? '';
    $password = v_password($password_raw, 6, 72);

    if ($password === null) {
        $error = 'La contrasena debe tener entre 6 y 72 caracteres y al menos una letra.';
    } elseif ($password_raw !== $confirmar_raw) {
        $error = 'Las contrasenas no coinciden.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $user['id']]);

            $_SESSION['registro_exitoso'] = 'Contrasena actualizada correctamente. Ya puedes iniciar sesion.';
            redirect('auth/login.php');
        } catch (PDOException $e) {
            error_log('Resetear password error: ' . $e->getMessage());
            $error = 'Error al actualizar la contrasena. Intenta de nuevo.';
        }
    }
}

partial('head', [
    'titulo_pagina' => 'Nueva contrasena',
    'css_pagina'    => 'auth'
]);
?>
<div class="auth-container">
    <div class="auth-card">
        <h2><i class="fas fa-lock marca-acento"></i> Nueva contrasena</h2>
        <p class="subtitulo">Ingresa y confirma tu nueva clave de acceso</p>

        <?php if ($error): ?>
            <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($token_valido): ?>
            <form method="post" novalidate>
                <div class="form-group">
                    <label for="password">Nueva contrasena *</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" placeholder="Minimo 6 caracteres" required minlength="6" maxlength="72">
                        <button type="button" class="btn-toggle-pass" data-target="password" aria-label="Mostrar u ocultar contraseña" title="Mostrar contraseña">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirmar_password">Confirmar contrasena *</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirmar_password" name="confirmar_password" placeholder="Repite tu contrasena" required minlength="6" maxlength="72">
                        <button type="button" class="btn-toggle-pass" data-target="confirmar_password" aria-label="Mostrar u ocultar confirmación" title="Mostrar contraseña">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-submit">Actualizar contrasena</button>
            </form>
        <?php else: ?>
            <div class="link-inferior">
                <a href="<?= url('auth/recuperar.php') ?>" class="btn-submit" style="display:inline-block; text-decoration:none; text-align:center;">
                    Solicitar nuevo enlace
                </a>
            </div>
        <?php endif; ?>

        <div class="auth-link">
            <a href="<?= url('auth/login.php') ?>"><i class="fas fa-arrow-left"></i> Volver al inicio de sesion</a>
        </div>
    </div>
</div>

<?php partial('footer', ['js_extra_footer' => ['js/auth.js']]); ?>