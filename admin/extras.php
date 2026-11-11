<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';
require_once __DIR__ . '/../includes/paginacion.php';

if (!esAdmin()) { redirect('index.php'); }
requierePermiso('extras'); 

$mensaje = $_SESSION['flash_mensaje'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_mensaje'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar' || $accion === 'editar') {
        $id = $accion === 'editar' ? v_int($_POST['id'] ?? 0, 1) : 0;
        $nombre = v_str($_POST['nombre'] ?? '', 2, 50, true, true);
        $precio_extra = v_float($_POST['precio_extra'] ?? 0, 0, 9999.99, 2);
        $categorias_seleccionadas_raw = $_POST['categorias'] ?? [];
        $categorias_seleccionadas = [];

        if (is_array($categorias_seleccionadas_raw)) {
            foreach ($categorias_seleccionadas_raw as $cat_id) {
                $cat_id = v_int($cat_id, 1);
                if ($cat_id !== null) $categorias_seleccionadas[] = $cat_id;
            }
        }

        if ($id === null) {
            $error = 'ID invalido.';
        } elseif ($nombre === null) {
            $error = 'El nombre debe tener entre 2 y 50 caracteres.';
        } elseif ($precio_extra === null) {
            $error = 'El precio debe ser un numero entre 0 y 9,999.99, con maximo 2 decimales.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM extras WHERE nombre = ? AND id != ?");
            $stmt->execute([$nombre, $id ?: 0]);
            if ($stmt->fetch()) {
                $error = 'Ya existe un extra con ese nombre.';
            } else {
                $categorias_validas = [];
                if (!empty($categorias_seleccionadas)) {
                    $ph = implode(',', array_fill(0, count($categorias_seleccionadas), '?'));
                    $stmt = $pdo->prepare("SELECT id FROM categorias WHERE id IN ($ph)");
                    $stmt->execute($categorias_seleccionadas);
                    $categorias_validas = array_column($stmt->fetchAll(), 'id');
                }

                try {
                    $pdo->beginTransaction();

                    if ($accion === 'agregar') {
                        $stmt = $pdo->prepare("INSERT INTO extras (nombre, precio_extra, activo) VALUES (?, ?, 1)");
                        $stmt->execute([$nombre, $precio_extra]);
                        $extra_id = $pdo->lastInsertId();
                    } else {
                        $stmt = $pdo->prepare("UPDATE extras SET nombre = ?, precio_extra = ? WHERE id = ?");
                        $stmt->execute([$nombre, $precio_extra, $id]);
                        $extra_id = $id;

                        $stmt = $pdo->prepare("DELETE FROM categoria_extras WHERE extra_id = ?");
                        $stmt->execute([$extra_id]);
                    }

                    if (!empty($categorias_validas)) {
                        $stmt = $pdo->prepare("INSERT INTO categoria_extras (categoria_id, extra_id) VALUES (?, ?)");
                        foreach ($categorias_validas as $cat_id) {
                            $stmt->execute([$cat_id, $extra_id]);
                        }
                    }

                    $pdo->commit();
                    $mensaje = $accion === 'agregar' ? 'Extra creado correctamente.' : 'Extra actualizado.';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log('Extra error: ' . $e->getMessage());
                    $error = 'Error al guardar el extra. Intenta de nuevo.';
                }
            }
        }
    } elseif ($accion === 'cambiar_estado') {
        $id = v_int($_POST['id'] ?? 0, 1);
        $nuevo = v_int($_POST['activo'] ?? -1, 0, 1);

        if ($id === null || $nuevo === null) {
            $error = 'Datos invalidos.';
        } else {
            $stmt = $pdo->prepare("UPDATE extras SET activo = ? WHERE id = ?");
            if ($stmt->execute([$nuevo, $id])) {
                $mensaje = $nuevo ? 'Extra activado.' : 'Extra desactivado.';
            } else {
                $error = 'Error al cambiar estado.';
            }
        }
    } elseif ($accion === 'desasignar_todo') {
        $id = v_int($_POST['id'] ?? 0, 1);

        if ($id === null) {
            $error = 'ID invalido.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("DELETE FROM categoria_extras WHERE extra_id = ?");
                $stmt->execute([$id]);

                $stmt = $pdo->prepare("DELETE FROM producto_extras WHERE extra_id = ?");
                $stmt->execute([$id]);

                $stmt = $pdo->prepare("UPDATE extras SET activo = 0 WHERE id = ?");
                $stmt->execute([$id]);

                $pdo->commit();
                $mensaje = 'Extra desactivado y desasignado de todas las categorias y productos. El historial de pedidos se conserva.';
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Desasignar extra error: ' . $e->getMessage());
                $error = 'Error al desasignar el extra.';
            }
        }
    }

    if ($mensaje || $error) {
        $_SESSION['flash_mensaje'] = $mensaje;
        $_SESSION['flash_error'] = $error;
        $pag_actual = intval($_GET['pagina'] ?? 1);
        redirect('admin/extras.php' . ($pag_actual > 1 ? '?pagina=' . $pag_actual : ''));
    }
}

$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll();

// Paginación de extras (10 por página)
$pag_extras = paginar($pdo, "SELECT * FROM extras ORDER BY activo DESC, nombre", [], 10);
$extras = $pag_extras['items'];

$extras_categorias = [];
$stmt = $pdo->query("SELECT extra_id, categoria_id FROM categoria_extras");
foreach ($stmt->fetchAll() as $row) {
    $extras_categorias[intval($row['extra_id'])][] = intval($row['categoria_id']);
}

$extras_productos = [];
$stmt = $pdo->query("SELECT extra_id, COUNT(*) AS total FROM producto_extras GROUP BY extra_id");
foreach ($stmt->fetchAll() as $row) {
    $extras_productos[intval($row['extra_id'])] = intval($row['total']);
}

partial('head', [
    'titulo_pagina' => 'Gestionar extras',
    'cargar_sweetalert' => true,
    'css_pagina' => 'admin',
    'css_extra' => ['css/extras.css']
]);
?>

<?php partial('menu', ['menu_activo' => 'extras']); ?>
<?php partial('header', ['titulo_pagina' => 'Gestionar extras']); ?>

<main class="container extras-main">
    <h2 class="titulo-admin"><i class="fas fa-plus-circle"></i> Gestionar extras</h2>

    <div class="info-desasignar">
        <i class="fas fa-info-circle"></i>
        <strong>Nota:</strong> Los extras <strong>no se eliminan permanentemente</strong> de la base de datos para preservar el historial de pedidos.
        Usa el boton <i class="fas fa-eye-slash"></i> para ocultar temporalmente, o el boton <i class="fas fa-unlink"></i> para desactivar y quitar de todas las categorias y productos.
    </div>

    <?php if ($mensaje): ?><div data-flash-success="<?= htmlspecialchars($mensaje) ?>" class="d-none"></div><?php endif; ?>
    <?php if ($error): ?><div data-flash-error="<?= htmlspecialchars($error) ?>" class="d-none"></div><?php endif; ?>

    <div class="tabla-card card-mb">
        <h3 class="tabla-titulo"><i class="fas fa-plus"></i> Crear nuevo extra</h3>
        <form method="post" novalidate>
            <input type="hidden" name="accion" value="agregar">
            <div class="form-grid">
                <div class="campo-form">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" required minlength="2" maxlength="50" placeholder="Ej: Queso extra" pattern="[A-Za-z0-9áéíóúÁÉÍÓÚñÑüÜ\s]+">
                </div>
                <div class="campo-form">
                    <label>Precio adicional *</label>
                    <input type="number" step="0.01" name="precio_extra" required min="0" max="9999.99" placeholder="0.00">
                </div>
                <div class="campo-form col-full">
                    <label>Categorias a las que aplica</label>
                    <div class="extras-cats-grid">
                        <?php foreach ($categorias as $cat): ?>
                            <label class="cat-check-item">
                                <input type="checkbox" name="categorias[]" value="<?= intval($cat['id']) ?>">
                                <?= htmlspecialchars($cat['nombre']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <small class="extras-hint">
                        <i class="fas fa-info-circle"></i> Si no seleccionas ninguna categoria, el extra solo podra asignarse a productos especificos.
                    </small>
                </div>
            </div>
            <button type="submit" class="btn-admin btn-guardar-extra">
                <i class="fas fa-save"></i> Crear extra
            </button>
        </form>
    </div>

    <div class="tabla-card">
        <h3 class="tabla-titulo"><i class="fas fa-list"></i> Extras existentes</h3>
        <div class="table-responsive">
            <table class="tabla-admin">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Precio</th>
                        <th>Categorias asignadas</th>
                        <th>Productos especificos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($extras) === 0): ?>
                        <tr>
                            <td colspan="7" class="celda-vacia">
                                <i class="fas fa-inbox icono-vacio"></i>
                                No hay extras creados aun.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($extras as $ex): ?>
                            <?php
                            $cats_ids = $extras_categorias[intval($ex['id'])] ?? [];
                            $cats_nombres = [];
                            foreach ($categorias as $c) {
                                if (in_array(intval($c['id']), $cats_ids, true)) $cats_nombres[] = $c['nombre'];
                            }
                            $total_prods = $extras_productos[intval($ex['id'])] ?? 0;
                            $clase_fila = $ex['activo'] ? '' : 'extra-inactivo';
                            ?>
                            <tr class="<?= $clase_fila ?>">
                                <td>#<?= intval($ex['id']) ?></td>
                                <td><strong><?= htmlspecialchars($ex['nombre']) ?></strong></td>
                                <td>$<?= number_format($ex['precio_extra'], 2) ?></td>
                                <td>
                                    <?php if (!empty($cats_nombres)): ?>
                                        <?php foreach ($cats_nombres as $cn): ?>
                                            <span class="badge-extra-cat"><?= htmlspecialchars($cn) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="badge-extra-sin">Ninguna</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($total_prods > 0): ?>
                                        <span class="badge-pedidos"><?= $total_prods ?> prod.</span>
                                    <?php else: ?>
                                        <span class="badge-extra-sin">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?= $ex['activo'] ? 'badge-activo' : 'badge-inactivo' ?>">
                                        <?= $ex['activo'] ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="acciones-container">
                                        <button type="button" class="btn btn-warning" onclick='editarExtra(<?= json_encode($ex, JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($cats_ids) ?>)'>
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <form method="post" class="form-cambiar-estado-extra confirm-form d-inline" data-nombre="<?= htmlspecialchars($ex['nombre']) ?>" data-accion="<?= $ex['activo'] ? 'ocultar' : 'activar' ?>">
                                            <input type="hidden" name="accion" value="cambiar_estado">
                                            <input type="hidden" name="id" value="<?= intval($ex['id']) ?>">
                                            <input type="hidden" name="activo" value="<?= $ex['activo'] ? 0 : 1 ?>">
                                            <button type="submit" class="btn btn-secondary">
                                                <?= $ex['activo'] ? '<i class="fas fa-eye-slash"></i> Ocultar' : '<i class="fas fa-eye"></i> Activar' ?>
                                            </button>
                                        </form>
                                        <?php if ($ex['activo'] || !empty($cats_ids) || $total_prods > 0): ?>
                                            <form method="post" class="form-desasignar-extra confirm-form d-inline" data-nombre="<?= htmlspecialchars($ex['nombre']) ?>">
                                                <input type="hidden" name="accion" value="desasignar_todo">
                                                <input type="hidden" name="id" value="<?= intval($ex['id']) ?>">
                                                <button type="submit" class="btn btn-danger" title="Desactivar y quitar de categorias y productos">
                                                    <i class="fas fa-unlink"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= renderPaginacion($pag_extras) ?>
    </div>
</main>

<!-- MODAL EDITAR EXTRA -->
<div class="modal-overlay" id="editExtraModal">
    <div class="modal-box">
        <button type="button" class="modal-cerrar" onclick="cerrarModalExtra()">X</button>
        <h3><i class="fas fa-edit"></i> Editar extra</h3>
        <form method="post" novalidate>
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id" id="edit_extra_id">
            <div class="form-grid">
                <div class="campo-form">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" id="edit_extra_nombre" required minlength="2" maxlength="50">
                </div>
                <div class="campo-form">
                    <label>Precio adicional *</label>
                    <input type="number" step="0.01" name="precio_extra" id="edit_extra_precio" required min="0" max="9999.99">
                </div>
                <div class="campo-form col-full">
                    <label>Categorias a las que aplica</label>
                    <div class="extras-cats-grid">
                        <?php foreach ($categorias as $cat): ?>
                            <label class="cat-check-item">
                                <input type="checkbox" name="categorias[]" value="<?= intval($cat['id']) ?>" class="edit-cat-check">
                                <?= htmlspecialchars($cat['nombre']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-acciones">
                <button type="button" class="btn btn-secondary btn-cancelar-modal" onclick="cerrarModalExtra()">Cancelar</button>
                <button type="submit" class="btn-admin"><i class="fas fa-save"></i> Actualizar</button>
            </div>
        </form>
    </div>
</div>

<?php partial('footer', ['cargar_admin_ux' => true, 'js_extra_footer' => ['js/extras.js']]); ?>