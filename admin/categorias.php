<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';
require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/paginacion.php';

if (!esAdmin()) {
    redirect('index.php');
}
requierePermiso('categorias');

aplicar_headers_seguridad();

// Auto-migración: asegurar que exista la columna 'activo' en la tabla categorias
try {
    $col_activo = $pdo->query("SHOW COLUMNS FROM categorias LIKE 'activo'")->fetch();
    if (!$col_activo) {
        $pdo->exec("ALTER TABLE categorias ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER descripcion");
    }
} catch (Exception $e) {
    error_log('Auto-migracion categorias.activo: ' . $e->getMessage());
}

$mensaje = $_SESSION['flash_mensaje'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_mensaje'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar' || $accion === 'editar') {
        $id = v_int($_POST['id'] ?? 0, 1);
        $nombre = v_str($_POST['nombre'] ?? '', 2, 40);
        $descripcion = v_texto($_POST['descripcion'] ?? '', 0, 100);

        if ($nombre === null) {
            $error = 'El nombre es obligatorio (entre 2 y 40 caracteres, sin caracteres especiales invalidos).';
        } elseif ($descripcion === null) {
            $error = 'La descripcion no puede superar los 100 caracteres.';
        } else {
            try {
                if ($accion === 'agregar') {
                    $stmtCheck = $pdo->prepare("SELECT id FROM categorias WHERE LOWER(nombre) = LOWER(?) LIMIT 1");
                    $stmtCheck->execute([$nombre]);
                    if ($stmtCheck->fetchColumn()) {
                        $error = 'Ya existe una categoria con ese nombre.';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO categorias (nombre, descripcion, activo) VALUES (?, ?, 1)");
                        $stmt->execute([$nombre, $descripcion ?: null]);
                        $mensaje = 'Categoria creada correctamente.';
                    }
                } else {
                    if ($id === null) {
                        $error = 'ID de categoria invalido.';
                    } else {
                        $stmtCheck = $pdo->prepare("SELECT id FROM categorias WHERE LOWER(nombre) = LOWER(?) AND id != ? LIMIT 1");
                        $stmtCheck->execute([$nombre, $id]);
                        if ($stmtCheck->fetchColumn()) {
                            $error = 'Ya existe otra categoria con ese nombre.';
                        } else {
                            $stmt = $pdo->prepare("UPDATE categorias SET nombre = ?, descripcion = ? WHERE id = ?");
                            $stmt->execute([$nombre, $descripcion ?: null, $id]);
                            $mensaje = 'Categoria actualizada correctamente.';
                        }
                    }
                }
            } catch (Exception $e) {
                log_error_seguro('categorias-save', $e);
                $error = 'No se pudo guardar la categoria.';
            }
        }
    } elseif ($accion === 'cambiar_estado') {
        $id = v_int($_POST['id'] ?? 0, 1);
        if ($id === null) {
            $error = 'ID de categoria invalido.';
        } else {
            try {
                $stmtCat = $pdo->prepare("SELECT id, nombre, activo FROM categorias WHERE id = ? LIMIT 1");
                $stmtCat->execute([$id]);
                $cat_actual = $stmtCat->fetch();

                if (!$cat_actual) {
                    $error = 'Categoria no encontrada.';
                } else {
                    $nuevo_estado = intval($cat_actual['activo']) === 1 ? 0 : 1;

                    // Si intenta ocultar la categoría, verificamos si tiene productos activos
                    if ($nuevo_estado === 0) {
                        $stmtProd = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE categoria_id = ? AND activo = 1");
                        $stmtProd->execute([$id]);
                        $prod_activos = intval($stmtProd->fetchColumn());

                        if ($prod_activos > 0) {
                            $error = 'No puedes ocultar "' . $cat_actual['nombre'] . '" porque tiene ' . $prod_activos . ' producto(s) activo(s). Oculta o mueve primero sus productos.';
                        } else {
                            $stmtUpd = $pdo->prepare("UPDATE categorias SET activo = 0 WHERE id = ?");
                            $stmtUpd->execute([$id]);
                            $mensaje = 'Categoria "' . $cat_actual['nombre'] . '" ocultada de la tienda.';
                        }
                    } else {
                        $stmtUpd = $pdo->prepare("UPDATE categorias SET activo = 1 WHERE id = ?");
                        $stmtUpd->execute([$id]);
                        $mensaje = 'Categoria "' . $cat_actual['nombre'] . '" ahora esta visible.';
                    }
                }
            } catch (Exception $e) {
                log_error_seguro('categorias-estado', $e);
                $error = 'Error al cambiar el estado de la categoria.';
            }
        }
    }

    if ($mensaje || $error) {
        $_SESSION['flash_mensaje'] = $mensaje;
        $_SESSION['flash_error'] = $error;
        $pagina_actual = isset($_GET['pagina']) ? '?pagina=' . intval($_GET['pagina']) : '';
        redirect('admin/categorias.php' . $pagina_actual);
    }
}

$sql_categorias = "
    SELECT c.*,
           (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id) AS total_productos,
           (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id AND p.activo = 1) AS productos_activos
    FROM categorias c
    ORDER BY c.id ASC
";

$pag_categorias = paginar($pdo, $sql_categorias, [], 10);
$categorias = $pag_categorias['items'];

partial('head', [
    'titulo_pagina' => 'Gestionar Categorias',
    'cargar_sweetalert' => true,
    'css_pagina' => 'admin',
    'css_extra' => ['css/paginacion.css', 'css/categorias.css']
]);
?>

<?php partial('menu', ['menu_activo' => 'categorias']); ?>
<?php partial('header', ['titulo_pagina' => 'Gestionar Categorias']); ?>

<main class="container categorias-main">
    <h2 class="titulo-admin"><i class="fas fa-tags"></i> Gestionar Categorias</h2>

    <?php if ($mensaje): ?><div data-flash-success="<?= htmlspecialchars($mensaje) ?>" class="d-none"></div><?php endif; ?>
    <?php if ($error): ?><div data-flash-error="<?= htmlspecialchars($error) ?>" class="d-none"></div><?php endif; ?>

    <div class="tabla-card card-mb">
        <h3 class="tabla-titulo"><i class="fas fa-plus-circle"></i> Nueva Categoria</h3>
        <form method="post" novalidate>
            <input type="hidden" name="accion" value="agregar">
            <div class="form-grid">
                <div class="campo-form">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" required minlength="2" maxlength="40" placeholder="Ej: Hamburguesas, Bebidas, Postres">
                </div>
                <div class="campo-form">
                    <label>Descripcion (opcional)</label>
                    <input type="text" name="descripcion" maxlength="100" placeholder="Breve descripcion de la categoria">
                </div>
            </div>
            <button type="submit" class="btn-admin btn-guardar-cat"><i class="fas fa-save"></i> Guardar Categoria</button>
        </form>
    </div>

    <div class="tabla-card">
        <h3 class="tabla-titulo"><i class="fas fa-list"></i> Categorias Registradas</h3>
        <div class="table-responsive">
            <table class="tabla-admin">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripcion</th>
                        <th>Productos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categorias)): ?>
                        <tr>
                            <td colspan="6" class="celda-vacia">
                                <i class="fas fa-tags icono-vacio"></i>
                                No hay categorias registradas.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categorias as $c): ?>
                            <?php
                            $esta_activa = intval($c['activo'] ?? 1) === 1;
                            $prod_activos = intval($c['productos_activos'] ?? 0);
                            $prod_totales = intval($c['total_productos'] ?? 0);
                            ?>
                            <tr>
                                <td><strong>#<?= intval($c['id']) ?></strong></td>
                                <td><strong><?= htmlspecialchars($c['nombre']) ?></strong></td>
                                <td><?= htmlspecialchars($c['descripcion'] ?: '—') ?></td>
                                <td>
                                    <span class="badge-pedidos"><?= $prod_activos ?> activo(s)</span>
                                    <?php if ($prod_totales > $prod_activos): ?>
                                        <small class="texto-inactivos-cat">(<?= $prod_totales ?> total)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($esta_activa && $prod_activos === 0): ?>
                                        <span class="badge-estado badge-cat-vacia" title="No se muestra en la tienda hasta que tenga al menos 1 producto activo">
                                            Sin productos
                                        </span>
                                    <?php elseif ($esta_activa): ?>
                                        <span class="badge-estado badge-cat-visible">Visible</span>
                                    <?php else: ?>
                                        <span class="badge-estado badge-cat-oculta">Oculta</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="acciones-container">
                                        <button type="button" class="btn btn-warning" onclick='editarCategoria(<?= json_encode(["id" => intval($c["id"]), "nombre" => $c["nombre"], "descripcion" => $c["descripcion"] ?? ""], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            <i class="fas fa-edit"></i> Editar
                                        </button>

                                        <?php if ($esta_activa): ?>
                                            <?php if ($prod_activos === 0): ?>
                                                <form method="post" class="d-inline form-cambiar-estado-categoria" data-categoria="<?= htmlspecialchars($c['nombre']) ?>" data-accion="ocultar">
                                                    <input type="hidden" name="accion" value="cambiar_estado">
                                                    <input type="hidden" name="id" value="<?= intval($c['id']) ?>">
                                                    <button type="submit" class="btn btn-danger" title="Ocultar categoría vacía">
                                                        <i class="fas fa-eye-slash"></i> Ocultar
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-secondary btn-cat-bloqueada" onclick="Swal.fire('Categoría en uso', 'Esta categoría tiene <?= $prod_activos ?> producto(s) activo(s). Oculta primero sus productos si deseas ocultar la categoría.', 'info')" title="Tiene productos activos">
                                                    <i class="fas fa-lock"></i> En uso
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <form method="post" class="d-inline form-cambiar-estado-categoria" data-categoria="<?= htmlspecialchars($c['nombre']) ?>" data-accion="mostrar">
                                                <input type="hidden" name="accion" value="cambiar_estado">
                                                <input type="hidden" name="id" value="<?= intval($c['id']) ?>">
                                                <button type="submit" class="btn btn-success" title="Volver a mostrar categoría">
                                                    <i class="fas fa-eye"></i> Mostrar
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
        <?= renderPaginacion($pag_categorias) ?>
    </div>
</main>

<div class="modal-overlay" id="editModal">
    <div class="modal-box modal-box-sm">
        <button type="button" class="modal-cerrar" onclick="cerrarModal()">X</button>
        <h3><i class="fas fa-edit"></i> Editar Categoria</h3>
        <form method="post" novalidate>
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id" id="edit_id">
            <div class="campo-form">
                <label>Nombre *</label>
                <input type="text" name="nombre" id="edit_nombre" required minlength="2" maxlength="40">
            </div>
            <div class="campo-form">
                <label>Descripcion</label>
                <input type="text" name="descripcion" id="edit_descripcion" maxlength="100">
            </div>
            <div class="modal-acciones">
                <button type="button" class="btn btn-secondary btn-cancelar-modal" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn-admin"><i class="fas fa-save"></i> Actualizar</button>
            </div>
        </form>
    </div>
</div>

<?php partial('footer', ['cargar_admin_ux' => true, 'js_extra_footer' => ['js/categorias.js']]); ?>