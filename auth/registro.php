<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';

if (usuarioLogueado()) {
    if (esAdmin()) { redirect('admin/dashboard.php'); }
    else { redirect('cliente/dashboard.php'); }
}

$error = '';$valores_previos = [
    'nombres'   => '',
    'apellidos' => '',
    'email'     => '',
    'prefijo'   => '0412',
    'numero_7'  => ''
];

$prefijos_ve = ['0412', '0414', '0416', '0422', '0424', '0426'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombres_raw   =$_POST['nombres'] ?? '';
    $apellidos_raw =$_POST['apellidos'] ?? '';
    $email_raw     =$_POST['email'] ?? '';
    $password_raw  =$_POST['password'] ?? '';
    $confirmar_raw =$_POST['confirmar_password'] ?? '';
    $prefijo_raw   =$_POST['prefijo'] ?? '';
    $numero_raw    =$_POST['numero_7'] ?? '';

    $valores_previos = [
        'nombres'   => $nombres_raw,
        'apellidos' => $apellidos_raw,
        'email'     => $email_raw,
        'prefijo'   => $prefijo_raw, 'numero_7'  =>$numero_raw
    ];

    $nombres   = v_str($nombres_raw, 2, 40, false, true);$apellidos = v_str($apellidos_raw, 2, 40, false, true);$email     = v_email($email_raw);$password  = v_password($password_raw, 6, 72);$telefono  = v_telefono_ve($prefijo_raw,$numero_raw);

    if ($nombres === null) {$error = 'El nombre debe tener entre 2 y 40 caracteres y solo puede contener letras.';
    } elseif ($apellidos === null) {$error = 'El apellido debe tener entre 2 y 40 caracteres y solo puede contener letras.';
    } elseif ($email === null) {$error = 'El email no es valido o supera los 100 caracteres.';
    } elseif ($password === null) {$error = 'La contrasena debe tener entre 6 y 72 caracteres y al menos una letra.';
    } elseif ($password_raw !== $confirmar_raw) {$error = 'Las contrasenas no coinciden.';
    } elseif ($telefono === null) {$error = 'El telefono no es valido. Usa un prefijo venezolano (0412, 0414, etc) y 7 digitos.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt =$pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'El email ya esta registrado.';$pdo->rollBack();
            } else {
                $stmt =$pdo->prepare("SELECT id FROM clientes WHERE telefono = ?");
                $stmt->execute([$telefono]);
                if ($stmt->fetch()) {
                    $error = 'El numero de telefono ya esta registrado en otra cuenta.';$pdo->rollBack();
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt =$pdo->prepare("INSERT INTO usuarios (email, password_hash, rol_id, activo) VALUES (?, ?, 2, 1)");
                    $stmt->execute([$email,$hash]);
                    $usuario_id =$pdo->lastInsertId();

                    $stmt =$pdo->prepare("INSERT INTO clientes (usuario_id, nombres, apellidos, telefono) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$usuario_id,$nombres, $apellidos,$telefono]);

                    $pdo->commit();

                    $_SESSION['registro_exitoso'] = 'Cuenta creada correctamente. Ya puedes iniciar sesion.';
                    redirect('auth/login.php');
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {$pdo->rollBack();
            }
            $error = 'Error en la base de datos. Intenta de nuevo.';
            error_log('Registro error: ' . $e->getMessage());
        }
    }
}

partial('head', [
    'titulo_pagina' => 'Registrarse',
    'css_pagina'    => 'auth',
    'css_extra'     => ['css/registro.css']
]);
?>
<div class="auth-container">
    <div class="auth-card">
        <h2>MINAS <span class="marca-acento">BURGUER</span></h2>
        <p class="subtitulo">Crea tu cuenta y empieza a pedir</p>

        <?php if ($error): ?>
            <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="form-group">
                <label for="nombres">Nombres *</label>
                <input type="text" id="nombres" name="nombres" placeholder="Tus nombres" required minlength="2" maxlength="40" pattern="[A-Za-záéíóúÁÉÍÓÚñÑüÜ\s]+" value="<?= htmlspecialchars($valores_previos['nombres']) ?>">
            </div>
            <div class="form-group">
                <label for="apellidos">Apellidos *</label>
                <input type="text" id="apellidos" name="apellidos" placeholder="Tus apellidos" required minlength="2" maxlength="40" pattern="[A-Za-záéíóúÁÉÍÓÚñÑüÜ\s]+" value="<?= htmlspecialchars($valores_previos['apellidos']) ?>">
            </div>
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" placeholder="ejemplo@correo.com" required maxlength="100" value="<?= htmlspecialchars($valores_previos['email']) ?>">
            </div>

            <div class="form-group">
                <label for="numero_7">Telefono / WhatsApp *</label>
                <div class="registro-tel-group">
                    <select name="prefijo" id="prefijo" required class="registro-prefijo">
                        <?php foreach ($prefijos_ve as$pref): ?>
                            <option value="<?= $pref ?>" <?= $valores_previos['prefijo'] === $pref ? 'selected' : '' ?>><?= $pref ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="tel" name="numero_7" id="numero_7" placeholder="1234567" required pattern="[0-9]{7}" maxlength="7" inputmode="numeric" value="<?= htmlspecialchars($valores_previos['numero_7']) ?>" class="registro-numero">
                </div>
                <small class="registro-whatsapp-hint">
                    <i class="fab fa-whatsapp icono-wa"></i> Este numero se usara para contactarte por WhatsApp.
                </small>
            </div>

            <div class="form-group">
                <label for="password">Contrasena *</label>
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
            <button type="submit" class="btn-submit">Registrarme</button>
        </form>

        <div class="auth-link">
            Ya tienes cuenta? <a href="<?= url('auth/login.php') ?>">Inicia sesion</a>
        </div>
        <div class="link-inferior link-volver-mt">
            <a href="<?= url('index.php') ?>"><i class="fas fa-arrow-left"></i> Volver al menu</a>
        </div>
    </div>
</div>

<?php partial('footer', ['js_extra_footer' => ['js/registro.js', 'js/auth.js']]); ?>