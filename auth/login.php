<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';

if (usuarioLogueado()) {
    if (esAdmin()) { redirect('admin/dashboard.php'); }
    else { redirect('cliente/dashboard.php'); }
}

$mensaje_registro = $_SESSION['registro_exitoso'] ?? '';
unset($_SESSION['registro_exitoso']);

$session_timeout_msg = $_SESSION['session_timeout_msg'] ?? '';
unset($_SESSION['session_timeout_msg']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Completa todos los campos.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT u.*, r.nombre AS rol_nombre
                FROM usuarios u
                JOIN roles r ON u.rol_id = r.id
                WHERE u.email = ?
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if (isset($user['activo']) && intval($user['activo']) === 0) {
                    $error = 'Tu cuenta se encuentra desactivada. Contacta al administrador.';
                } else {
                    session_regenerate_id(true);

                    $_SESSION['usuario_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['rol_id'] = $user['rol_id'];
                    $_SESSION['rol_nombre'] = $user['rol_nombre'];
                    $_SESSION['es_super_admin'] = $user['es_super_admin'] ?? 0;
                    $_SESSION['permisos'] = $user['permisos'] ?? '[]';

                    if ($user['rol_id'] == 2) {
                        $stmtCli = $pdo->prepare("SELECT nombres, apellidos FROM clientes WHERE usuario_id = ?");
                        $stmtCli->execute([$user['id']]);
                        $cliente = $stmtCli->fetch();
                        if ($cliente) {
                            $_SESSION['nombres'] = $cliente['nombres'];
                            $_SESSION['apellidos'] = $cliente['apellidos'];
                        } else {
                            $stmtIns = $pdo->prepare("INSERT INTO clientes (usuario_id, nombres, apellidos) VALUES (?, ?, ?)");
                            $stmtIns->execute([$user['id'], 'Cliente', 'Sin apellido']);
                            $_SESSION['nombres'] = 'Cliente';
                            $_SESSION['apellidos'] = 'Sin apellido';
                        }
                    } else {
                        $stmtCli = $pdo->prepare("SELECT nombres, apellidos FROM clientes WHERE usuario_id = ?");
                        $stmtCli->execute([$user['id']]);
                        $cliente = $stmtCli->fetch();
                        if ($cliente) {
                            $_SESSION['nombres'] = $cliente['nombres'];
                            $_SESSION['apellidos'] = $cliente['apellidos'];
                        } else {
                            $parte_email = explode('@', $user['email'])[0];
                            $_SESSION['nombres'] = ucfirst($parte_email);
                            $_SESSION['apellidos'] = '';
                        }
                    }

                    $_SESSION['bienvenida_pendiente'] = true;

                    $destino = ($user['rol_id'] == 1) ? 'admin/dashboard.php' : 'cliente/dashboard.php';
                    redirect_prg($destino);
                }
            } else {
                $error = 'Email o contrasena incorrectos.';
            }
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'Error al procesar el inicio de sesion. Intenta de nuevo.';
        }
    }
}

partial('head', [
    'titulo_pagina' => 'Iniciar sesion',
    'css_pagina' => 'auth'
]);
?>
<div class="auth-container">
    <div class="auth-card">
        <h2>MINAS <span class="marca-acento">BURGUER</span></h2>
        <p class="subtitulo">Inicia sesion para realizar tu pedido</p>

        <?php if ($mensaje_registro): ?>
            <div class="mensaje-exito"><?= htmlspecialchars($mensaje_registro) ?></div>
        <?php endif; ?>
        <?php if ($session_timeout_msg): ?>
            <div class="mensaje-error"><i class="fas fa-clock"></i> <?= htmlspecialchars($session_timeout_msg) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="ejemplo@correo.com" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="password">Contrasena</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="Tu contrasena" required autocomplete="off">
                    <button type="button" class="btn-toggle-pass" data-target="password" aria-label="Mostrar u ocultar contraseña" title="Mostrar contraseña">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-submit">Iniciar sesion</button>
        </form>

        <div class="link-inferior">
            <a href="<?= url('auth/recuperar.php') ?>">Olvidaste tu contrasena?</a>
        </div>
        <div class="auth-link">
            No tienes cuenta? <a href="<?= url('auth/registro.php') ?>">Registrate aqui</a>
        </div>
        <div class="link-inferior">
            <a href="<?= url('index.php') ?>"><i class="fas fa-arrow-left"></i> Volver al menu</a>
        </div>
    </div>
</div>

<?php partial('footer', ['js_extra_footer' => ['js/auth.js']]); ?>