<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';
require_once __DIR__ . '/../includes/paginacion.php';

if (!esAdmin()) { redirect('index.php'); }
requierePermiso('usuarios');

$mensaje = $_SESSION['flash_mensaje'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_mensaje'], $_SESSION['flash_error']);

$modulos_disponibles = [
    'pedidos'       => 'Gestión de Pedidos',
    'productos'     => 'Gestión de Productos',
    'categorias'    => 'Gestión de Categorías',
    'extras'        => 'Gestión de Extras',
    'clientes'      => 'Gestión de Clientes',
    'usuarios'      => 'Gestión de Administradores',
    'reportes'      => 'Reportes',
    'configuracion' => 'Configuración'
];

// ============================================================
// CREAR ADMINISTRADOR
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $email_raw = $_POST['email'] ?? '';
    $password_raw = $_POST['password'] ?? '';
    $confirmar_raw = $_POST['confirmar_password'] ?? '';
    $permisos_raw = $_POST['permisos'] ?? [];
    
    $email = v_email($email_raw);
    $password = v_password($password_raw, 6, 72);
    
    $permisos_limpios = [];
    if (is_array($permisos_raw)) {
        foreach ($permisos_raw as $p) {
            if (array_key_exists($p, $modulos_disponibles)) {
                $permisos_limpios[] = $p;
            }
        }
    }

    if ($email === null) {
        $error = 'El email no es valido.';
    } elseif ($password === null) {
        $error = 'La contrasena debe tener entre 6 y 72 caracteres.';
    } elseif ($password_raw !== $confirmar_raw) {
        $error = 'Las contrasenas no coinciden.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'El email ya esta registrado.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $permisos_json = json_encode($permisos_limpios);
                
                $stmt = $pdo->prepare("INSERT INTO usuarios (email, password_hash, rol_id, es_super_admin, permisos, activo) VALUES (?, ?, 1, 0, ?, 1)");
                $stmt->execute([$email, $hash, $permisos_json]);
                $mensaje = "Administrador creado correctamente.";
            }
        } catch (PDOException $e) {
            error_log('Crear admin error: ' . $e->getMessage());
            $error = 'Error en la base de datos. Intenta de nuevo.';
        }
    }
    
    if ($mensaje || $error) {
        $_SESSION['flash_mensaje'] = $mensaje;
        $_SESSION['flash_error'] = $error;
        redirect('admin/usuarios.php');
    }
}

// ============================================================
// CAMBIAR ESTADO (ACTIVAR/DESACTIVAR)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_estado') {
    $user_id = v_int($_POST['user_id'] ?? 0, 1);
    $nuevo_estado = v_int($_POST['activo'] ?? -1, 0, 1);

    $stmt = $pdo->prepare("SELECT es_super_admin, email FROM usuarios WHERE id = ? AND rol_id = 1");
    $stmt->execute([$user_id]);
    $target_user = $stmt->fetch();

    if ($user_id === null || $nuevo_estado === null) {
        $error = 'Datos invalidos.';
    } elseif ($user_id == $_SESSION['usuario_id']) {
        $error = 'No puedes desactivar tu propia cuenta.';
    } elseif ($target_user && $target_user['es_super_admin'] == 1) {
        $error = 'No puedes desactivar al Súper Administrador.';
    } elseif (!$target_user) {
        $error = 'Solo puedes gestionar administradores.';
    } else {
        $stmt = $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
        if ($stmt->execute([$nuevo_estado, $user_id])) {
            $mensaje = "Estado del administrador actualizado.";
        } else {
            $error = 'Error al cambiar el estado.';
        }
    }
    
    if ($mensaje || $error) {
        $_SESSION['flash_mensaje'] = $mensaje;
        $_SESSION['flash_error'] = $error;
        $pag_actual = intval($_GET['pagina'] ?? 1);
        redirect('admin/usuarios.php' . ($pag_actual > 1 ? '?pagina=' . $pag_actual : ''));
    }
}

// ============================================================
// ACTUALIZAR PERMISOS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_permisos') {
    $user_id = v_int($_POST['user_id'] ?? 0, 1);
    $permisos_raw = $_POST['permisos'] ?? [];
    
    $stmt = $pdo->prepare("SELECT es_super_admin FROM usuarios WHERE id = ? AND rol_id = 1");
    $stmt->execute([$user_id]);
    $target_user = $stmt->fetch();

    if ($user_id === null) {
        $error = 'ID invalido.';
    } elseif ($target_user && $target_user['es_super_admin'] == 1) {
        $error = 'No puedes cambiar los permisos del Súper Administrador.';
    } elseif ($user_id == $_SESSION['usuario_id'] && !esSuperAdmin()) {
        $error = 'No puedes modificar los permisos de tu propia cuenta.';
    } elseif (!$target_user) {
        $error = 'Usuario no encontrado.';
    } else {
        $permisos_limpios = [];
        if (is_array($permisos_raw)) {
            foreach ($permisos_raw as $p) {
                if (array_key_exists($p, $modulos_disponibles)) {
                    $permisos_limpios[] = $p;
                }
            }
        }
        $permisos_json = json_encode($permisos_limpios);
        
        $stmt = $pdo->prepare("UPDATE usuarios SET permisos = ? WHERE id = ?");
        if ($stmt->execute([$permisos_json, $user_id])) {
            if ($user_id == $_SESSION['usuario_id']) {
                $_SESSION['permisos'] = $permisos_limpios;
            }
            $mensaje = "Permisos actualizados correctamente.";
        } else {
            $error = 'Error al actualizar permisos.';
        }
    }
    
    if ($mensaje || $error) {
        $_SESSION['flash_mensaje'] = $mensaje;
        $_SESSION['flash_error'] = $error;
        $pag_actual = intval($_GET['pagina'] ?? 1);
        redirect('admin/usuarios.php' . ($pag_actual > 1 ? '?pagina=' . $pag_actual : ''));
    }
}

// ============================================================
// OBTENER USUARIOS PAGINADOS
// ============================================================
$sql = "
    SELECT u.id, u.email, u.rol_id, u.activo, u.fecha_registro, u.es_super_admin, u.permisos,
    r.nombre as rol_nombre,
    c.nombres, c.apellidos
    FROM usuarios u
    JOIN roles r ON u.rol_id = r.id
    LEFT JOIN clientes c ON u.id = c.usuario_id
    WHERE u.rol_id = 1
    ORDER BY u.es_super_admin DESC, u.id ASC
";
$pag_usuarios = paginar($pdo, $sql, [], 10);
$usuarios = $pag_usuarios['items'];

partial('head', [
    'titulo_pagina' => 'Gestionar Administradores',
    'cargar_sweetalert' => true,
    'css_pagina' => 'admin',
    'css_extra' => ['css/usuarios.css']
]);
?>

<?php partial('menu', ['menu_activo' => 'usuarios']); ?>
<?php partial('header', ['titulo_pagina' => 'Gestionar Administradores']); ?>

<main class="container usuarios-main">
    <h2 class="titulo-admin"><i class="fas fa-user-shield"></i> Gestionar Administradores</h2>

    <p class="usuarios-descripcion">
        <i class="fas fa-info-circle"></i> El <strong>Súper Administrador</strong> tiene acceso total e intocable. A los demás administradores puedes asignarles permisos específicos para que solo vean ciertos módulos.
    </p>

    <?php if ($mensaje): ?><div data-flash-success="<?= htmlspecialchars($mensaje) ?>" class="d-none"></div><?php endif; ?>
    <?php if ($error): ?><div data-flash-error="<?= htmlspecialchars($error) ?>" class="d-none"></div><?php endif; ?>

    <!-- FORMULARIO PARA CREAR ADMINISTRADOR -->
    <div class="tabla-card card-mb">
        <h3 class="tabla-titulo"><i class="fas fa-user-plus"></i> Crear nuevo administrador</h3>
        <form method="post" novalidate>
            <input type="hidden" name="accion" value="crear">
            <div class="form-grid">
                <div class="campo-form">
                    <label for="admin_email">Email *</label>
                    <input type="email" id="admin_email" name="email" required maxlength="100" placeholder="empleado@minasburguer.com">
                </div>
                <div class="campo-form">
                    <label for="admin_password">Contrasena *</label>
                    <div class="password-wrapper">
                        <input type="password" id="admin_password" name="password" required minlength="6" maxlength="72" placeholder="Minimo 6 caracteres">
                        <button type="button" class="btn-toggle-pass" data-target="admin_password" aria-label="Mostrar u ocultar contraseña" title="Mostrar contraseña">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="campo-form">
                    <label for="admin_confirmar_password">Confirmar Contrasena *</label>
                    <div class="password-wrapper">
                        <input type="password" id="admin_confirmar_password" name="confirmar_password" required minlength="6" maxlength="72" placeholder="Repetir contrasena">
                        <button type="button" class="btn-toggle-pass" data-target="admin_confirmar_password" aria-label="Mostrar u ocultar confirmación" title="Mostrar contraseña">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="campo-form mt-1">
                <label>Permisos de acceso</label>
                <div class="extras-grid permisos-grid">
                    <?php foreach ($modulos_disponibles as $clave => $nombre_mod): ?>
                        <label class="extra-check-item">
                            <input type="checkbox" name="permisos[]" value="<?= $clave ?>">
                            <?= htmlspecialchars($nombre_mod) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn-admin mt-1"><i class="fas fa-save"></i> Crear Administrador</button>
        </form>
    </div>

    <!-- LISTA DE ADMINISTRADORES -->
    <div class="tabla-card">
        <h3 class="tabla-titulo"><i class="fas fa-list"></i> Administradores existentes</h3>
        <div class="table-responsive">
            <table class="tabla-admin">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>Registro</th>
                        <th>Estado</th>
                        <th>Permisos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <?php 
                        $es_super = $u['es_super_admin'] == 1;
                        $permisos_usuario = json_decode($u['permisos'] ?? '[]', true) ?: [];
                        ?>
                        <tr>
                            <td>#<?= intval($u['id']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($u['email']) ?></strong>
                                <?php if ($es_super): ?>
                                    <br><span class="badge-estado badge-super-admin"><i class="fas fa-crown"></i> Súper Admin</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($u['fecha_registro'])) ?></td>
                            <td>
                                <span class="<?= $u['activo'] ? 'badge-activo' : 'badge-inactivo' ?>">
                                    <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($es_super): ?>
                                    <span class="texto-acceso-total">Acceso Total</span>
                                <?php elseif ($u['id'] == $_SESSION['usuario_id'] && !esSuperAdmin()): ?>
                                    <span class="texto-protegido">Tu cuenta</span>
                                <?php else: ?>
                                    <button type="button" class="btn btn-info btn-gestionar-permisos" onclick='editarPermisos(<?= intval($u['id']) ?>, <?= json_encode($permisos_usuario) ?>)'>
                                        <i class="fas fa-key"></i> Gestionar
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="acciones-container">
                                    <?php if (!$es_super && $u['id'] != $_SESSION['usuario_id']): ?>
                                        <form method="post" class="form-cambiar-estado confirm-form d-inline" data-user="<?= intval($u['id']) ?>" data-email="<?= htmlspecialchars($u['email']) ?>" data-accion="<?= $u['activo'] ? 'bloquear' : 'activar' ?>">
                                            <input type="hidden" name="accion" value="cambiar_estado">
                                            <input type="hidden" name="user_id" value="<?= intval($u['id']) ?>">
                                            <input type="hidden" name="activo" value="<?= $u['activo'] ? 0 : 1 ?>">
                                            <button type="submit" class="btn btn-secondary">
                                                <?= $u['activo'] ? '<i class="fas fa-user-slash"></i> Bloquear' : '<i class="fas fa-user-check"></i> Activar' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="texto-protegido">Protegido</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= renderPaginacion($pag_usuarios) ?>
    </div>
</main>

<!-- MODAL EDITAR PERMISOS -->
<div class="modal-overlay" id="modalPermisos">
    <div class="modal-box">
        <button type="button" class="modal-cerrar" onclick="cerrarModalPermisos()">X</button>
        <h3><i class="fas fa-key"></i> Gestionar Permisos</h3>
        <form method="post">
            <input type="hidden" name="accion" value="actualizar_permisos">
            <input type="hidden" name="user_id" id="perm_user_id">
            <div class="extras-grid permisos-grid modal-permisos-grid">
                <?php foreach ($modulos_disponibles as $clave => $nombre_mod): ?>
                    <label class="extra-check-item">
                        <input type="checkbox" name="permisos[]" value="<?= $clave ?>" class="chk-permiso">
                        <?= htmlspecialchars($nombre_mod) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="modal-acciones">
                <button type="button" class="btn-filtro btn-filtro-limpiar" onclick="cerrarModalPermisos()">Cancelar</button>
                <button type="submit" class="btn-admin"><i class="fas fa-save"></i> Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<?php partial('footer', ['cargar_admin_ux' => true, 'js_extra_footer' => ['js/usuarios.js', 'js/auth.js']]); ?>