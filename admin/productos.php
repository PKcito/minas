<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';
require_once __DIR__ . '/../includes/paginacion.php';

if (!esAdmin()) { redirect('index.php'); }
requierePermiso('productos'); 

$mensaje = $_SESSION['flash_mensaje'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_mensaje'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar' || $accion === 'editar') {
        $id_raw = $_POST['id'] ?? 0;
        $id = $accion === 'editar' ? v_int($id_raw, 1) : 0;
        $nombre = v_str($_POST['nombre'] ?? '', 3, 60, true, true);
        $descripcion = v_texto($_POST['descripcion'] ?? '', 0, 500);
        $precio = v_float($_POST['precio'] ?? 0, 0.01, 9999.99, 2);
        $categoria_id = v_int($_POST['categoria_id'] ?? 0, 1);
        $imagen_icono = 'fas fa-hamburger';
        $extras_seleccionados = $_POST['extras'] ?? [];
        $extras_limpios = [];

        if (is_array($extras_seleccionados)) {
            foreach ($extras_seleccionados as $ex_id) {
                $ex_id = v_int($ex_id, 1);
                if ($ex_id !== null) $extras_limpios[] = $ex_id;
            }
        }

        if ($id === null) {
            $error = 'ID invalido.';
        } elseif ($nombre === null) {
            $error = 'El nombre debe tener entre 3 y 60 caracteres.';
        } elseif ($descripcion === null) {
            $error = 'La descripcion supera el limite de 500 caracteres.';
        } elseif ($precio === null) {
            $error = 'El precio debe estar entre $0.01 y $9,999.99, con maximo 2 decimales.';
        } elseif ($categoria_id === null) {
            $error = 'Selecciona una categoria valida.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM categorias WHERE id = ?");
            $stmt->execute([$categoria_id]);
            if (!$stmt->fetch()) {
                $error = 'La categoria seleccionada no existe.';
            } else {
                $stmt = $pdo->prepare("SELECT id FROM productos WHERE nombre = ? AND categoria_id = ? AND id != ?");
                $stmt->execute([$nombre, $categoria_id, $id ?: 0]);
                if ($stmt->fetch()) {
                    $error = 'Ya existe un producto con ese nombre en esta categoria.';
                } else {
                    $imagen_nombre = null;
                    $imagen_subida = false;

                    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                        $validacion = v_archivo_imagen($_FILES['imagen'], 2);
                        if (!$validacion['ok']) {
                            $error = $validacion['error'];
                        } else {
                            $imagen_nombre = v_generar_nombre_archivo('producto', $validacion['extension']);
                            $ruta = __DIR__ . '/../uploads/productos/' . $imagen_nombre;
                            if (!is_dir(__DIR__ . '/../uploads/productos')) mkdir(__DIR__ . '/../uploads/productos', 0777, true);
                            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta)) {
                                $error = 'Error al guardar imagen.';
                                $imagen_nombre = null;
                            } else {
                                $imagen_subida = true;
                            }
                        }
                    }

                    if (empty($error)) {
                        try {
                            $pdo->beginTransaction();
                            $imagen_anterior_a_borrar = null;

                            if ($accion === 'agregar') {
                                $stmt = $pdo->prepare("INSERT INTO productos (nombre, descripcion, precio_unitario, imagen_icono, imagen, categoria_id, activo) VALUES (?, ?, ?, ?, ?, ?, 1)");
                                $stmt->execute([$nombre, $descripcion, $precio, $imagen_icono, $imagen_nombre, $categoria_id]);
                                $producto_id = $pdo->lastInsertId();
                            } else {
                                $stmt = $pdo->prepare("SELECT imagen, imagen_icono FROM productos WHERE id = ?");
                                $stmt->execute([$id]);
                                $row = $stmt->fetch();

                                if ($row && !empty($row['imagen_icono'])) {
                                    $imagen_icono = $row['imagen_icono'];
                                }

                                if (!$imagen_nombre) {
                                    $imagen_nombre = $row['imagen'] ?? null;
                                } else {
                                    if (!empty($row['imagen']) && $row['imagen'] !== $imagen_nombre) {
                                        $imagen_anterior_a_borrar = $row['imagen'];
                                    }
                                }

                                $stmt = $pdo->prepare("UPDATE productos SET nombre = ?, descripcion = ?, precio_unitario = ?, imagen_icono = ?, imagen = ?, categoria_id = ? WHERE id = ?");
                                $stmt->execute([$nombre, $descripcion, $precio, $imagen_icono, $imagen_nombre, $categoria_id, $id]);
                                $producto_id = $id;

                                $stmt = $pdo->prepare("DELETE FROM producto_extras WHERE producto_id = ?");
                                $stmt->execute([$producto_id]);
                            }

                            if (!empty($extras_limpios)) {
                                $stmt_heredados = $pdo->prepare("SELECT extra_id FROM categoria_extras WHERE categoria_id = ?");
                                $stmt_heredados->execute([$categoria_id]);
                                $heredados = array_column($stmt_heredados->fetchAll(), 'extra_id');

                                $stmt = $pdo->prepare("INSERT INTO producto_extras (producto_id, extra_id) VALUES (?, ?)");
                                foreach ($extras_limpios as $extra_id) {
                                    if (!in_array($extra_id, $heredados)) {
                                        $stmt->execute([$producto_id, $extra_id]);
                                    }
                                }
                            }

                            $pdo->commit();

                            if ($imagen_anterior_a_borrar) {
                                $ruta_vieja = __DIR__ . '/../uploads/productos/' . $imagen_anterior_a_borrar;
                                if (file_exists($ruta_vieja)) {
                                    @unlink($ruta_vieja);
                                }
                            }

                            $mensaje = $accion === 'agregar' ? 'Producto agregado.' : 'Producto actualizado.';
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            error_log('Producto error: ' . $e->getMessage());
                            $error = 'Error al guardar el producto. Intenta de nuevo.';
                            if ($imagen_subida && $imagen_nombre) {
                                @unlink(__DIR__ . '/../uploads/productos/' . $imagen_nombre);
                            }
                        }
                    }
                }
            }
        }
    } elseif ($accion === 'cambiar_estado') {
        $id = v_int($_POST['id'] ?? 0, 1);
        $nuevo = v_int($_POST['activo'] ?? -1, 0, 1);

        if ($id === null || $nuevo === null) {
            $error = 'Datos invalidos.';
        } else {
            $stmt = $pdo->prepare("UPDATE productos SET activo = ? WHERE id = ?");
            if ($stmt->execute([$nuevo, $id])) {
                $mensaje = 'Estado actualizado.';
            } else {
                $error = 'Error al cambiar estado.';
            }
        }
    }

    if ($mensaje || $error) {
        $_SESSION['flash_mensaje'] = $mensaje;
        $_SESSION['flash_error'] = $error;
        $pag_actual = intval($_GET['pagina'] ?? 1);
        redirect('admin/productos.php' . ($pag_actual > 1 ? '?pagina=' . $pag_actual : ''));
    }
}

$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll();
$todos_extras = $pdo->query("SELECT * FROM extras WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Paginación de productos (10 por página)
$sql_productos = "SELECT p.*, c.nombre AS categoria_nombre FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id ORDER BY p.id DESC";
$pag_productos = paginar($pdo, $sql_productos, [], 10);
$productos = $pag_productos['items'];

$producto_extras = [];
$stmt = $pdo->query("SELECT producto_id, extra_id FROM producto_extras");
foreach ($stmt->fetchAll() as $row) {
    $producto_extras[intval($row['producto_id'])][] = intval($row['extra_id']);
}

$extras_por_categoria = [];
$stmt = $pdo->query("SELECT ce.categoria_id, ce.extra_id FROM categoria_extras ce JOIN extras e ON e.id = ce.extra_id WHERE e.activo = 1");
foreach ($stmt->fetchAll() as $row) {
    $extras_por_categoria[intval($row['categoria_id'])][] = intval($row['extra_id']);
}

partial('head', [
    'titulo_pagina' => 'Gestionar productos',
    'cargar_sweetalert' => true,
    'css_pagina' => 'admin',
    'css_extra' => ['css/productos.css']
]);
?>

<?php partial('menu', ['menu_activo' => 'productos']); ?>
<?php partial('header', ['titulo_pagina' => 'Gestionar productos']); ?>

<main class="container" style="padding: 2rem 0;">
    <h2 class="titulo-admin"><i class="fas fa-utensils"></i> Gestionar productos</h2>

    <?php if ($mensaje): ?><div data-flash-success="<?= htmlspecialchars($mensaje) ?>" class="d-none"></div><?php endif; ?>
    <?php if ($error): ?><div data-flash-error="<?= htmlspecialchars($error) ?>" class="d-none"></div><?php endif; ?>

    <!-- FORMULARIO CREAR -->
    <div class="tabla-card card-mb">
        <h3 class="tabla-titulo"><i class="fas fa-plus-circle"></i> Agregar nuevo producto</h3>
        <form method="post" enctype="multipart/form-data" novalidate id="formCrearProducto">
            <input type="hidden" name="accion" value="agregar">
            <div class="form-grid">
                <div class="campo-form">
                    <label>Nombre * <small class="text-hint">(3-60 caracteres)</small></label>
                    <input type="text" name="nombre" id="create_nombre" required minlength="3" maxlength="60" data-counter="create_nombre_counter">
                    <small class="char-counter" id="create_nombre_counter">0/60</small>
                </div>
                <div class="campo-form">
                    <label>Precio *</label>
                    <div class="input-number-group">
                        <button type="button" class="input-number-btn" data-action="menos" data-target="create_precio" data-step="0.50">
                            <i class="fas fa-minus"></i>
                        </button>
                        <div class="input-number-wrapper">
                            <span class="input-number-prefix">$</span>
                            <input type="number" step="0.01" name="precio" id="create_precio" required min="0.01" max="9999.99">
                        </div>
                        <button type="button" class="input-number-btn" data-action="mas" data-target="create_precio" data-step="0.50">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="campo-form col-full">
                    <label>Categoria *</label>
                    <select name="categoria_id" id="create_categoria_id" required>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= intval($cat['id']) ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo-imagen col-full">
                    <label>Imagen del producto</label>
                    <input type="file" name="imagen" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small>JPG, PNG, GIF, WEBP. Max. 2MB.</small>
                </div>
                <div class="campo-form col-full">
                    <label>Descripcion <small class="text-hint">(0-500 caracteres)</small></label>
                    <textarea name="descripcion" id="create_descripcion" rows="2" maxlength="500" data-counter="create_descripcion_counter"></textarea>
                    <small class="char-counter" id="create_descripcion_counter">0/500</small>
                </div>
                <div class="campo-form col-full">
                    <label>Extras especificos para este producto</label>
                    <div class="extras-grid" id="create_extras_grid">
                        <?php foreach ($todos_extras as $ex): ?>
                            <label class="extra-check-item" data-extra-id="<?= intval($ex['id']) ?>">
                                <input type="checkbox" name="extras[]" value="<?= intval($ex['id']) ?>" class="create-extra-check">
                                <?= htmlspecialchars($ex['nombre']) ?>
                                <small class="extra-precio-sm">+$<?= number_format($ex['precio_extra'], 2) ?></small>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="info-heredados d-none" id="info_heredados_create">
                        <i class="fas fa-info-circle"></i> <strong>Extras heredados de la categoria:</strong> <span id="lista_heredados_create"></span>
                        <br><small>Estos extras se aplican automaticamente y no es necesario marcarlos.</small>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn-admin btn-guardar">
                <i class="fas fa-save"></i> Guardar producto
            </button>
        </form>
    </div>

    <!-- TABLA PRODUCTOS -->
    <div class="tabla-card">
        <h3 class="tabla-titulo"><i class="fas fa-list"></i> Productos existentes</h3>
        <div class="table-responsive">
            <table class="tabla-admin">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Imagen</th>
                        <th>Nombre</th>
                        <th>Precio</th>
                        <th>Categoria</th>
                        <th>Extras</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem; color: var(--texto-secundario);">
                                No hay productos registrados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($productos as $p): ?>
                            <?php
                            $cats_extras = $extras_por_categoria[intval($p['categoria_id'])] ?? [];
                            $prod_extras = $producto_extras[intval($p['id'])] ?? [];
                            $total_extras_prod = count(array_unique(array_merge($cats_extras, $prod_extras)));
                            ?>
                            <tr>
                                <td>#<?= intval($p['id']) ?></td>
                                <td>
                                    <?php if (!empty($p['imagen']) && file_exists(__DIR__ . '/../uploads/productos/' . $p['imagen'])): ?>
                                        <img src="<?= url('uploads/productos/' . htmlspecialchars($p['imagen'])) ?>" class="producto-imagen-preview" alt="">
                                    <?php else: ?>
                                        <i class="<?= htmlspecialchars($p['imagen_icono'] ?: 'fas fa-hamburger') ?> tabla-img-icon"></i>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($p['nombre']) ?></strong></td>
                                <td>$<?= number_format($p['precio_unitario'], 2) ?></td>
                                <td><?= htmlspecialchars($p['categoria_nombre'] ?? 'Sin categoria') ?></td>
                                <td>
                                    <span class="badge-pedidos"><?= $total_extras_prod ?> extras</span>
                                </td>
                                <td>
                                    <span class="<?= $p['activo'] ? 'badge-activo' : 'badge-inactivo' ?>">
                                        <?= $p['activo'] ? 'Activo' : 'Oculto' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="acciones-container">
                                        <button type="button" class="btn btn-info" onclick='editarProducto(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($prod_extras) ?>)'>
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <form method="post" class="form-cambiar-estado-producto d-inline" data-nombre="<?= htmlspecialchars($p['nombre']) ?>" data-accion="<?= $p['activo'] ? 'ocultar' : 'mostrar' ?>">
                                            <input type="hidden" name="accion" value="cambiar_estado">
                                            <input type="hidden" name="id" value="<?= intval($p['id']) ?>">
                                            <input type="hidden" name="activo" value="<?= $p['activo'] ? 0 : 1 ?>">
                                            <button type="submit" class="btn btn-secondary">
                                                <?= $p['activo'] ? '<i class="fas fa-eye-slash"></i> Ocultar' : '<i class="fas fa-eye"></i> Mostrar' ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= renderPaginacion($pag_productos) ?>
    </div>
</main>

<!-- MODAL EDITAR PRODUCTO -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <button type="button" class="modal-cerrar" onclick="cerrarModal()">X</button>
        <h3><i class="fas fa-edit"></i> Editar producto</h3>
        <form method="post" enctype="multipart/form-data" novalidate id="formEditarProducto">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-grid">
                <div class="campo-form">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" id="edit_nombre" required minlength="3" maxlength="60">
                </div>
                <div class="campo-form">
                    <label>Precio *</label>
                    <div class="input-number-group">
                        <button type="button" class="input-number-btn" data-action="menos" data-target="edit_precio" data-step="0.50">
                            <i class="fas fa-minus"></i>
                        </button>
                        <div class="input-number-wrapper">
                            <span class="input-number-prefix">$</span>
                            <input type="number" step="0.01" name="precio" id="edit_precio" required min="0.01" max="9999.99">
                        </div>
                        <button type="button" class="input-number-btn" data-action="mas" data-target="edit_precio" data-step="0.50">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="campo-form col-full">
                    <label>Categoria *</label>
                    <select name="categoria_id" id="edit_categoria_id" required>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= intval($cat['id']) ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo-imagen col-full">
                    <label>Reemplazar imagen (opcional)</label>
                    <input type="file" name="imagen" accept="image/jpeg,image/png,image/gif,image/webp">
                    <div class="imagen-actual" id="edit_imagen_actual"></div>
                </div>
                <div class="campo-form col-full">
                    <label>Descripcion</label>
                    <textarea name="descripcion" id="edit_descripcion" rows="2" maxlength="500"></textarea>
                </div>
                <div class="campo-form col-full">
                    <label>Extras especificos para este producto</label>
                    <div class="extras-grid" id="edit_extras_grid">
                        <?php foreach ($todos_extras as $ex): ?>
                            <label class="extra-check-item" data-extra-id="<?= intval($ex['id']) ?>">
                                <input type="checkbox" name="extras[]" value="<?= intval($ex['id']) ?>" class="edit-extra-check">
                                <?= htmlspecialchars($ex['nombre']) ?>
                                <small class="extra-precio-sm">+$<?= number_format($ex['precio_extra'], 2) ?></small>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="info-heredados d-none" id="info_heredados_edit">
                        <i class="fas fa-info-circle"></i> <strong>Extras heredados de la categoria:</strong> <span id="lista_heredados_edit"></span>
                    </div>
                </div>
            </div>
            <div class="modal-acciones">
                <button type="button" class="btn-filtro btn-filtro-limpiar" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn-admin"><i class="fas fa-save"></i> Actualizar</button>
            </div>
        </form>
    </div>
</div>

<script>
window.PRODUCTOS_CONFIG = {
    extrasPorCategoria: <?= json_encode($extras_por_categoria) ?>,
    todosExtras: <?= json_encode($todos_extras) ?>,
    urlUploads: '<?= url('uploads/productos/') ?>'
};
</script>

<?php partial('footer', ['cargar_admin_ux' => true, 'js_extra_footer' => ['js/productos.js']]); ?>