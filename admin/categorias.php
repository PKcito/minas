<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';
require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/paginacion.php';

if (!esAdmin()) { redirect('index.php'); }
requierePermiso('categorias'); 

aplicar_headers_seguridad();

$mensaje = $_SESSION['flash_mensaje'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_mensaje'], $_SESSION['flash_error']);

$columna_activo = $pdo->query("SHOW COLUMNS FROM categorias LIKE 'activo'")->rowCount() > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar' || $accion === 'editar') {
        $id = $accion === 'editar' ? v_int($_POST['id'] ?? 0, 1) : 0;
        $nombre = v_str($_POST['nombre'] ?? '', 2, 40, true, true);
        $descripcion = v_texto($_POST['descripcion'] ?? '', 0, 100);

        if ($id === null) {
            $error = 'ID invalido.';
        } elseif ($nombre === null) {
            $error = 'El nombre debe tener entre 2 y 40 caracteres.';
        } elseif ($descripcion === null) {
            $error = 'La descripcion no puede superar los 100 caracteres.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM categorias WHERE nombre = ? AND id != ?");
            $stmt->execute([$nombre, $id ?: 0]);
            if ($stmt->fetch()) {
                $error = 'Ya existe una categoria con ese nombre.';
            } else {
                try {
                    if ($accion === 'agregar') {
                        if ($columna_activo) {
                            $stmt = $pdo->prepare("INSERT INTO categorias (nombre, descripcion, activo) VALUES (?, ?, 1)");
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)");
                        }
                        $stmt->execute([$nombre, $descripcion]);
                        $mensaje = "Categoria '$nombre' agregada.";
                    } else {
                        $stmt = $pdo->prepare("UPDATE categorias SET nombre = ?, descripcion = ? WHERE id = ?");
                        $stmt->execute([$nombre, $descripcion, $id]);
                        $mensaje = 'Categoria actualizada.';
                    }
                } catch (PDOException $e) {
                    log_error_seguro('categoria', $e);
                    $error = 'Error al guardar la categoria.';
                }
            }
        }
    } elseif ($accion === 'cambiar_estado') {
        $id = v_int($_POST['id'] ?? 0, 1);
        $nuevo = v_int($_POST['activo'] ?? -1, 0, 1);

        if ($id === null || $nuevo === null) {
            $error = 'Datos invalidos.';
        } elseif (!$columna_activo) {
            $error = "La tabla no tiene columna 'activo'.";
        } else {
            if ($nuevo == 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE categoria_id = ? AND activo = 1");
                $stmt->execute([$id]);
                $count = $stmt->fetchColumn();
                if ($count > 0) {
                    $error = "No se puede ocultar: tiene $count producto(s) activo(s).";
                } else {
                    $stmt = $pdo->prepare("UPDATE categorias SET activo = ? WHERE id = ?");
                    $stmt->execute([$nuevo, $id]);
                    $mensaje = 'Categoria ocultada.';
                }
            } else {
                $stmt = $pdo->prepare("UPDATE categorias SET activo = ? WHERE id = ?");
                $stmt->execute([$nuevo, $id]);
                $mensaje = 'Categoria mostrada.';
            }
        }
    }

    if ($mensaje || $error) {
        $_SESSION['flash_mensaje'] = $mensaje;
        $_SESSION['flash_error'] = $error;
        $pag_actual = intval($_GET['pagina'] ?? 1);
        redirect('admin/categorias.php' . ($pag_actual > 1 ? '?pagina=' . $pag_actual : ''));
    }
}

$pag_categorias = paginar($pdo, "SELECT * FROM categorias ORDER BY nombre", [], 10);
$categorias = $pag_categorias['items'];

partial('head', [
    'titulo_pagina' => 'Gestionar categorias',
    'cargar_sweetalert' => true,
    'css_pagina' => 'admin',
    'css_extra' => ['css/categorias.css']
]);
?>

<?php partial('menu', ['menu_activo' => 'categorias']); ?>
<?php partial('header', ['titulo_pagina' => 'Gestionar categorias']); ?>

<main class="container categorias-main">
    <h2 class="titulo-admin"><i class="fas fa-tags"></i> Gestionar categorias</h2>

    <?php if ($mensaje): ?><div data-flash-success="<?= htmlspecialchars($mensaje) ?>" class="d-none"></div><?php endif; ?>
    <?php if ($error): ?><div data-flash-error="<?= htmlspecialchars($error) ?>" class="d-none"></div><?php endif; ?>

    <div class="tabla-card card-mb">
        <h3 class="tabla-titulo"><i class="fas fa-plus-circle"></i> Agregar categoria</h3>
        <form method="post" novalidate>
            <input type="hidden" name="accion" value="agregar">
            <div class="form-grid">
                <div class="campo-form">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" required minlength="2" maxlength="40" placeholder="Ej: Hamburguesas">
                </div>
                <div class="campo-form">
                    <label>Descripcion</label>
                    <input type="text" name="descripcion" maxlength="100" placeholder="Opcional">
                </div>
            </div>
            <button type="submit" class="btn-admin btn-guardar-cat">
                <i class="fas fa-save"></i> Guardar
            </button>
        </form>
    </div>

    <div class="tabla-card">
        <h3 class="tabla-titulo"><i class="fas fa-list"></i> Categorias existentes</h3>
        <div class="table-responsive">
            <table class="tabla-admin">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripcion</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($categorias) === 0): ?>
                        <tr>
                            <td colspan="5" class="celda-vacia">
                                <i class="fas fa-inbox icono-vacio"></i>
                                No hay categorias creadas.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categorias as $cat): ?>
                            <tr>
                                <td>#<?= intval($cat['id']) ?></td>
                                <td><strong><?= htmlspecialchars($cat['nombre']) ?></strong></td>
                                <td><?= htmlspecialchars($cat['descripcion'] ?? '') ?></td>
                                <td>
                                    <?php if ($columna_activo): ?>
                                        <span class="<?= ($cat['activo'] ?? 1) ? 'badge-activo' : 'badge-inactivo' ?>">
                                            <?= ($cat['activo'] ?? 1) ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-activo">Activo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="acciones-container">
                                        <button type="button" class="btn btn-warning" onclick='editarCategoria(<?= json_encode($cat, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <?php if ($columna_activo): ?>
                                            <form method="post" class="form-cambiar-estado-categoria confirm-form d-inline" data-categoria="<?= htmlspecialchars($cat['nombre']) ?>" data-accion="<?= ($cat['activo'] ?? 1) ? 'ocultar' : 'mostrar' ?>">
                                                <input type="hidden" name="accion" value="cambiar_estado">
                                                <input type="hidden" name="id" value="<?= intval($cat['id']) ?>">
                                                <input type="hidden" name="activo" value="<?= ($cat['activo'] ?? 1) ? 0 : 1 ?>">
                                                <button type="submit" class="btn btn-secondary">
                                                    <?= ($cat['activo'] ?? 1) ? '<i class="fas fa-eye-slash"></i> Ocultar' : '<i class="fas fa-eye"></i> Mostrar' ?>
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

<div id="editModal" class="modal-overlay">
    <div class="modal-box modal-box-sm">
        <button type="button" class="modal-cerrar" onclick="cerrarModal()">X</button>
        <h3><i class="fas fa-edit"></i> Editar categoria</h3>
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
                <button type="button" onclick="cerrarModal()" class="btn btn-secondary btn-cancelar-modal">Cancelar</button>
                <button type="submit" class="btn-admin"><i class="fas fa-save"></i> Actualizar</button>
            </div>
        </form>
    </div>
</div>

<?php partial('footer', ['cargar_admin_ux' => true, 'js_extra_footer' => ['js/categorias.js']]); ?>