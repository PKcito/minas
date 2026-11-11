<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';

if (!usuarioLogueado()) { redirect('auth/login.php'); }

$usuario_id = $_SESSION['usuario_id'];
$mensaje = $_SESSION['flash_mensaje'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_mensaje'], $_SESSION['flash_error']);

$prefijos_ve = ['0412', '0414', '0416', '0422', '0424', '0426'];

// Municipios de Carabobo
$municipios_carabobo = [
    'Bejuma', 'Carlos Arvelo', 'Diego Ibarra', 'Guacara',
    'Juan José Mora', 'Libertador', 'Los Guayos', 'Miranda',
    'Montalbán', 'Naguanagua', 'Puerto Cabello', 'San Diego',
    'San Joaquín', 'Valencia',
];

$stmt = $pdo->prepare("
    SELECT c.id AS cliente_id, c.nombres, c.apellidos, c.telefono, c.fecha_nacimiento, c.notas,
           u.email
    FROM clientes c
    JOIN usuarios u ON c.usuario_id = u.id
    WHERE c.usuario_id = ?
");
$stmt->execute([$usuario_id]);
$cliente = $stmt->fetch();
$cliente_id = intval($cliente['cliente_id'] ?? 0);

$return_param = (isset($_GET['return']) && $_GET['return'] === 'checkout') ? '?return=checkout' : '';

// ============================================================
// ACTUALIZAR DATOS PERSONALES
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_datos') {
    $nombres_raw = $_POST['nombres'] ?? '';
    $apellidos_raw = $_POST['apellidos'] ?? '';
    $email_raw = $_POST['email'] ?? '';
    $prefijo_raw = $_POST['prefijo'] ?? '';
    $numero_raw = $_POST['numero_7'] ?? '';

    $nombres = v_str($nombres_raw, 2, 40, false, true);
    $apellidos = v_str($apellidos_raw, 2, 40, false, true);
    $email = v_email($email_raw);
    $telefono = v_telefono_ve($prefijo_raw, $numero_raw);

    if ($nombres === null) {
        $error = 'El nombre debe tener entre 2 y 40 caracteres y solo letras.';
    } elseif ($apellidos === null) {
        $error = 'El apellido debe tener entre 2 y 40 caracteres y solo letras.';
    } elseif ($email === null) {
        $error = 'El email no es valido o supera los 100 caracteres.';
    } elseif ($telefono === null) {
        $error = 'El telefono no es valido. Usa un prefijo venezolano y 7 digitos.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$email, $usuario_id]);
            if ($stmt->fetch()) {
                throw new Exception('Ya existe otro usuario con ese email.');
            }

            $stmt = $pdo->prepare("SELECT id FROM clientes WHERE telefono = ? AND id != ?");
            $stmt->execute([$telefono, $cliente_id]);
            if ($stmt->fetch()) {
                throw new Exception('Ese numero de telefono ya esta registrado en otra cuenta.');
            }

            $stmt = $pdo->prepare("UPDATE clientes SET nombres = ?, apellidos = ?, telefono = ? WHERE id = ?");
            $stmt->execute([$nombres, $apellidos, $telefono, $cliente_id]);

            $stmt = $pdo->prepare("UPDATE usuarios SET email = ? WHERE id = ?");
            $stmt->execute([$email, $usuario_id]);

            $_SESSION['nombres'] = $nombres;
            $_SESSION['apellidos'] = $apellidos;
            $_SESSION['email'] = $email;

            $pdo->commit();
            $mensaje = 'Datos actualizados correctamente.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }

    $_SESSION['flash_mensaje'] = $mensaje;
    $_SESSION['flash_error'] = $error;
    redirect('cliente/perfil.php' . $return_param);
}

// ============================================================
// AGREGAR DIRECCIÓN
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agregar_direccion') {
    $calle = v_str($_POST['calle'] ?? '', 5, 150, true, true);
    $municipio = $_POST['municipio'] ?? '';
    $parroquia = v_str($_POST['parroquia'] ?? '', 2, 50, true, true);
    $referencia = v_texto($_POST['referencia'] ?? '', 0, 200);
    $tipo_direccion_id = v_int($_POST['tipo_direccion_id'] ?? 0, 1);
    $predeterminada = isset($_POST['predeterminada']) ? 1 : 0;

    if (!in_array($municipio, $municipios_carabobo, true)) {
        $error = 'Selecciona un municipio valido de Carabobo.';
    } elseif ($calle === null) {
        $error = 'La direccion debe tener entre 5 y 150 caracteres.';
    } elseif ($parroquia === null) {
        $error = 'La parroquia es obligatoria.';
    } elseif ($referencia === null) {
        $error = 'La referencia supera los 200 caracteres.';
    } elseif ($tipo_direccion_id === null) {
        $error = 'Selecciona un tipo de direccion valido.';
    } else {
        $estado = 'Carabobo';

        $stmt = $pdo->prepare("SELECT id FROM tipos_direccion WHERE id = ?");
        $stmt->execute([$tipo_direccion_id]);
        if (!$stmt->fetch()) {
            $error = 'El tipo de direccion no existe.';
        } else {
            try {
                $pdo->beginTransaction();

                if ($predeterminada) {
                    $stmt = $pdo->prepare("UPDATE direcciones SET predeterminada = 0 WHERE cliente_id = ?");
                    $stmt->execute([$cliente_id]);
                }

                $stmt = $pdo->prepare("INSERT INTO direcciones (cliente_id, estado, municipio, parroquia, calle, referencia, tipo_direccion_id, predeterminada) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $cliente_id, $estado, $municipio, $parroquia,
                    $calle, $referencia ?: null, $tipo_direccion_id, $predeterminada
                ]);

                $nueva_dir_id = $pdo->lastInsertId();
                $pdo->commit();
                $mensaje = "Direccion agregada correctamente.";

                $return = $_GET['return'] ?? '';
                if ($return === 'checkout') {
                    redirect('cliente/checkout.php?direccion_agregada=' . $nueva_dir_id);
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Direccion error: ' . $e->getMessage());
                $error = 'Error al guardar la direccion. Intenta de nuevo.';
            }
        }
    }

    $_SESSION['flash_mensaje'] = $mensaje;
    $_SESSION['flash_error'] = $error;
    redirect('cliente/perfil.php' . $return_param);
}

// ============================================================
// ELIMINAR DIRECCIÓN (Con desvinculación segura en pedidos)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_direccion') {
    $dir_id = v_int($_POST['direccion_id'] ?? 0, 1);
    if ($dir_id === null) {
        $error = 'ID invalido.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM direcciones WHERE id = ? AND cliente_id = ?");
        $stmt->execute([$dir_id, $cliente_id]);
        if ($stmt->fetch()) {
            try {
                $pdo->beginTransaction();
                $stmtUnlink = $pdo->prepare("UPDATE pedidos SET direccion_entrega_id = NULL WHERE direccion_entrega_id = ?");
                $stmtUnlink->execute([$dir_id]);

                $stmtDel = $pdo->prepare("DELETE FROM direcciones WHERE id = ?");
                $stmtDel->execute([$dir_id]);
                $pdo->commit();
                $mensaje = "Direccion eliminada.";
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Eliminar direccion error: ' . $e->getMessage());
                $error = 'No se pudo eliminar la direccion.';
            }
        } else {
            $error = 'Direccion no encontrada.';
        }
    }

    $_SESSION['flash_mensaje'] = $mensaje;
    $_SESSION['flash_error'] = $error;
    redirect('cliente/perfil.php' . $return_param);
}

// ============================================================
// MARCAR PREDETERMINADA
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'marcar_predeterminada') {
    $dir_id = v_int($_POST['direccion_id'] ?? 0, 1);
    if ($dir_id === null) {
        $error = 'ID invalido.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM direcciones WHERE id = ? AND cliente_id = ?");
        $stmt->execute([$dir_id, $cliente_id]);
        if ($stmt->fetch()) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE direcciones SET predeterminada = 0 WHERE cliente_id = ?");
                $stmt->execute([$cliente_id]);
                $stmt = $pdo->prepare("UPDATE direcciones SET predeterminada = 1 WHERE id = ?");
                $stmt->execute([$dir_id]);
                $pdo->commit();
                $mensaje = "Direccion marcada como predeterminada.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error al actualizar la direccion predeterminada.';
            }
        } else {
            $error = 'Direccion no encontrada.';
        }
    }

    $_SESSION['flash_mensaje'] = $mensaje;
    $_SESSION['flash_error'] = $error;
    redirect('cliente/perfil.php' . $return_param);
}

$stmt = $pdo->prepare("SELECT d.*, td.nombre as tipo_nombre FROM direcciones d JOIN tipos_direccion td ON d.tipo_direccion_id = td.id WHERE d.cliente_id = ? ORDER BY d.predeterminada DESC, d.id DESC");
$stmt->execute([$cliente_id]);
$direcciones = $stmt->fetchAll();
$tipos = $pdo->query("SELECT * FROM tipos_direccion")->fetchAll();

$prefijo_actual = '0412';
$numero_actual = '';
if (!empty($cliente['telefono'])) {
    $tel_limpio = preg_replace('/[^0-9]/', '', $cliente['telefono']);
    if (strlen($tel_limpio) >= 11) {
        $posible_prefijo = substr($tel_limpio, 0, 4);
        if (in_array($posible_prefijo, $prefijos_ve)) {
            $prefijo_actual = $posible_prefijo;
            $numero_actual = substr($tel_limpio, 4, 7);
        } else {
            $numero_actual = substr($tel_limpio, 0, 7);
        }
    } else {
        $numero_actual = substr($tel_limpio, 0, 7);
    }
}

$iconos_tipo = [1 => 'fa-home', 2 => 'fa-briefcase', 3 => 'fa-map-marker-alt'];

partial('head', [
    'titulo_pagina' => 'Mi perfil',
    'cargar_sweetalert' => true,
    'css_pagina' => 'cliente',
    'css_extra' => ['css/perfil.css']
]);
?>

<?php partial('menu', ['menu_activo' => 'perfil']); ?>
<?php partial('header', [
    'titulo_pagina' => 'Mi perfil',
    'mostrar_salir' => false,
    'mostrar_volver' => [
        'url'   => (isset($_GET['return']) && $_GET['return'] === 'checkout') ? 'cliente/checkout.php' : 'cliente/dashboard.php',
        'texto' => (isset($_GET['return']) && $_GET['return'] === 'checkout') ? 'Volver al pedido' : 'Volver a Mi Panel'
    ]
]); ?>

<section class="container perfil-container">
    <h2 class="titulo-admin"><i class="fas fa-user-circle"></i> Mi perfil</h2>

    <?php if ($mensaje): ?><div data-flash-success="<?= htmlspecialchars($mensaje) ?>" class="d-none"></div><?php endif; ?>
    <?php if ($error): ?><div data-flash-error="<?= htmlspecialchars($error) ?>" class="d-none"></div><?php endif; ?>

    <div class="perfil-grid">
        <!-- COLUMNA IZQUIERDA: DATOS PERSONALES + MIS DIRECCIONES -->
        <div class="perfil-col">
            <div class="perfil-card card-mb">
                <h3><i class="fas fa-id-card"></i> Datos personales</h3>
                <form method="post" novalidate>
                    <input type="hidden" name="accion" value="actualizar_datos">
                    <div class="form-grid">
                        <div class="campo-form">
                            <label>Nombres *</label>
                            <input type="text" name="nombres" value="<?= htmlspecialchars($cliente['nombres'] ?? '') ?>" required minlength="2" maxlength="40">
                        </div>
                        <div class="campo-form">
                            <label>Apellidos *</label>
                            <input type="text" name="apellidos" value="<?= htmlspecialchars($cliente['apellidos'] ?? '') ?>" required minlength="2" maxlength="40">
                        </div>
                    </div>
                    <div class="campo-form">
                        <label>Email *</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>" required maxlength="100">
                    </div>
                    <div class="campo-form">
                        <label>Telefono / WhatsApp *</label>
                        <div class="telefono-inline">
                            <select name="prefijo" class="select-prefijo-perfil">
                                <?php foreach ($prefijos_ve as $pr): ?>
                                    <option value="<?= $pr ?>" <?= $prefijo_actual === $pr ? 'selected' : '' ?>><?= $pr ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="tel" name="numero_7" id="perfil_numero_7" value="<?= htmlspecialchars($numero_actual) ?>" required maxlength="7" minlength="7" pattern="[0-9]{7}" inputmode="numeric" placeholder="1234567" class="input-flex-1">
                        </div>
                    </div>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Actualizar mis datos
                    </button>
                </form>
            </div>

            <div class="perfil-card">
                <h3><i class="fas fa-map-marked-alt"></i> Mis direcciones guardadas</h3>
                <?php if (empty($direcciones)): ?>
                    <div class="sin-direcciones">
                        <i class="fas fa-map-marker-alt"></i>
                        <p>Aun no tienes direcciones registradas.</p>
                    </div>
                <?php else: ?>
                    <div class="direcciones-lista">
                        <?php foreach ($direcciones as $d): ?>
                            <?php $icono = $iconos_tipo[intval($d['tipo_direccion_id'])] ?? 'fa-map-marker-alt'; ?>
                            <div class="direccion-card <?= $d['predeterminada'] ? 'es-predeterminada' : '' ?>">
                                <div class="direccion-icono"><i class="fas <?= $icono ?>"></i></div>
                                <div class="direccion-info">
                                    <div class="direccion-titulo">
                                        <?= htmlspecialchars($d['calle']) ?>
                                        <span class="direccion-tipo"><?= htmlspecialchars($d['tipo_nombre']) ?></span>
                                        <?php if ($d['predeterminada']): ?>
                                            <span class="badge-predeterminada"><i class="fas fa-check"></i> Principal</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="direccion-linea">
                                        <?= htmlspecialchars($d['parroquia']) ?>, <?= htmlspecialchars($d['municipio']) ?>, <?= htmlspecialchars($d['estado']) ?>
                                    </div>
                                    <?php if (!empty($d['referencia'])): ?>
                                        <div class="direccion-referencia">
                                            <i class="fas fa-info-circle"></i> <?= htmlspecialchars($d['referencia']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="direccion-acciones">
                                        <?php if (!$d['predeterminada']): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="accion" value="marcar_predeterminada">
                                                <input type="hidden" name="direccion_id" value="<?= intval($d['id']) ?>">
                                                <button type="submit" class="btn-pred">
                                                    <i class="fas fa-star"></i> Hacer principal
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" class="form-eliminar-direccion d-inline" data-calle="<?= htmlspecialchars($d['calle']) ?>">
                                            <input type="hidden" name="accion" value="eliminar_direccion">
                                            <input type="hidden" name="direccion_id" value="<?= intval($d['id']) ?>">
                                            <button type="submit" class="btn-eliminar">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- COLUMNA DERECHA: AGREGAR NUEVA DIRECCIÓN -->
        <div class="perfil-col">
            <div class="perfil-card">
                <h3><i class="fas fa-plus-circle"></i> Agregar nueva direccion</h3>
                <form method="post" novalidate>
                    <input type="hidden" name="accion" value="agregar_direccion">
                    <div class="form-grid">
                        <div class="campo-form">
                            <label>Estado</label>
                            <input type="text" value="Carabobo" readonly class="input-readonly">
                        </div>
                        <div class="campo-form">
                            <label>Municipio *</label>
                            <select name="municipio" required>
                                <option value="">Selecciona municipio</option>
                                <?php foreach ($municipios_carabobo as $mun): ?>
                                    <option value="<?= htmlspecialchars($mun) ?>" <?= $mun === 'Valencia' ? 'selected' : '' ?>><?= htmlspecialchars($mun) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="campo-form">
                            <label>Parroquia / Urbanizacion *</label>
                            <input type="text" name="parroquia" required minlength="2" maxlength="50" placeholder="Ej: San José, El Trigal">
                        </div>
                        <div class="campo-form">
                            <label>Tipo de direccion *</label>
                            <select name="tipo_direccion_id" required>
                                <?php foreach ($tipos as $t): ?>
                                    <option value="<?= intval($t['id']) ?>"><?= htmlspecialchars(ucfirst($t['nombre'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="campo-form">
                        <label>Calle / Avenida / Casa o Edificio *</label>
                        <input type="text" name="calle" required minlength="5" maxlength="150" placeholder="Ej: Av. Bolívar Norte, Edif. Torre Sindoni, Piso 2">
                    </div>
                    <div class="campo-form">
                        <label>Punto de referencia (opcional)</label>
                        <input type="text" name="referencia" maxlength="200" placeholder="Ej: Frente a la plaza, portón negro">
                    </div>
                    <div class="campo-form">
                        <label class="check-predeterminada-label">
                            <input type="checkbox" name="predeterminada" value="1" <?= empty($direcciones) ? 'checked' : '' ?>>
                            <span>Establecer como mi direccion principal de entrega</span>
                        </label>
                    </div>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-map-marker-alt"></i> Guardar direccion
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php partial('footer', ['cargar_admin_ux' => true, 'js_extra_footer' => ['js/perfil.js']]); ?>