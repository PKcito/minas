<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';

if (usuarioLogueado()) {
    if (esAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('cliente/dashboard.php');
    }
}

$token_raw = $_GET['token'] ?? $_POST['token'] ?? '';
$error = '';
$exito = '';
$token_valido = false;
$usuario_reset = null;

if (empty($token_raw)) {
    $error = 'El enlace de recuperacion no es valido o esta incompleto.';
} else {
    $decodificado = base64_decode($token_raw, true);
    if ($decodificado === false) {
        $error = 'Token de recuperacion malformado.';
    } else {
        $partes = explode('|', $decodificado);
        if (count($partes) !== 3) {
            $error = 'Estructura de token invalida.';
        } else {
            list($uid, $expira_ts, $firma_recibida) = $partes;
            $uid = intval($uid);
            $expira_ts = intval($expira_ts);

            if (time() > $expira_ts) {
                $error = 'Este enlace de recuperacion ha expirado (valido por 1 hora). Solicita uno nuevo.';
            } else {
                $stmt = $pdo->prepare("SELECT id, email, password_hash, activo FROM usuarios WHERE id = ? LIMIT 1");
                $stmt->execute([$uid]);
                $user = $stmt->fetch();

                if (!$user || intval($user['activo']) === 0) {
                    $error = 'La cuenta asociada no existe o esta desactivada.';
                } else {
                    $payload = $user['id'] . '|' . $expira_ts;
                    $firma_esperada = hash_hmac('sha256', $payload . '|' . $user['password_hash'], 'minas_burguer_secret_key_2026');

                    if (!hash_equals($firma_esperada, $firma_recibida)) {
                        $error = 'Este enlace ya fue utilizado o no es valido.';
                    } else {
                        $token_valido = true;
                        $usuario_reset = $user;
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido && $usuario_reset) {
    $password = $_POST['password'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    $pass_valida = v_password($password, 6, 72);

    if ($pass_valida === null) {
        $error = 'La contrasena debe tener entre 6 y 72 caracteres.';
    } elseif ($password !== $confirmar) {
        $error = 'Las contrasenas no coinciden.';
    } else {
        try {
            $nuevo_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmtUpd = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
            $stmtUpd->execute([$nuevo_hash, $usuario_reset['id']]);

            $exito = 'Tu contrasena ha sido actualizada correctamente. Ya puedes iniciar sesion.';
            $token_valido = false;
        } catch (PDOException $e) {
            error_log('Reset password error: ' . $e->getMessage());
            $error = 'Error al guardar la nueva contrasena. Intenta nuevamente.';
        }
    }
}

partial('head', [
    'titulo_pagina' => 'Restablecer contrasena',
    'css_pagina' => 'auth'
]);
?>
<div class="auth-container">
    <div class="auth-card">
        <h2><i class="fas fa-lock marca-acento"></i> Nueva clave</h2>
        <p class="subtitulo">Crea una nueva contrasena segura para tu cuenta</p>

        <?php if ($error): ?>
            <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($exito): ?>
            <div class="mensaje-exito"><?= htmlspecialchars($exito) ?></div>
            <div class="auth-link" style="margin-top: 1.25rem;">
                <a href="<?= url('auth/login.php') ?>" class="btn-submit" style="display: block; text-align: center; text-decoration: none; color: #fff;">
                    <i class="fas fa-sign-in-alt"></i> Ir a Iniciar Sesion
                </a>
            </div>
        <?php endif; ?>

        <?php if ($token_valido): ?>
            <form method="post" novalidate>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token_raw) ?>">

                <div class="form-group">
                    <label>Cuenta</label>
                    <input type="email" value="<?= htmlspecialchars($usuario_reset['email']) ?>" disabled style="opacity: 0.7; cursor: not-allowed;">
                </div>

                <div class="form-group">
                    <label for="password">Nueva contrasena *</label>
                    <input type="password" id="password" name="password" required minlength="6" maxlength="72" placeholder="Minimo 6 caracteres">
                </div>

                <div class="form-group">
                    <label for="confirmar">Confirmar nueva contrasena *</label>
                    <input type="password" id="confirmar" name="confirmar" required minlength="6" maxlength="72" placeholder="Repite tu nueva contrasena">
                </div>

                <button type="submit" class="btn-submit">Guardar nueva contrasena</button>
            </form>
        <?php elseif (!$exito): ?>
            <div class="auth-link" style="margin-top: 1.25rem;">
                <a href="<?= url('auth/recuperar.php') ?>"><i class="fas fa-redo"></i> Solicitar un nuevo enlace</a>
            </div>
        <?php endif; ?>

        <div class="auth-link">
            <a href="<?= url('auth/login.php') ?>"><i class="fas fa-arrow-left"></i> Volver al inicio de sesion</a>
        </div>
    </div>
</div>

<?php partial('footer'); ?>