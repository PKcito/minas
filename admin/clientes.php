<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';
require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/paginacion.php';

if (!esAdmin()) { redirect('index.php'); }
requierePermiso('clientes'); 

aplicar_headers_seguridad();

$mensaje = $_SESSION['flash_mensaje'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_mensaje'], $_SESSION['flash_error']);

$prefijos_ve = ['0412', '0414', '0416', '0422', '0424', '0426'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'editar') {
    $cliente_id = v_int($_POST['cliente_id'] ?? 0, 1);
    $nombres = v_str($_POST['nombres'] ?? '', 2, 40, false, true);
    $apellidos = v_str($_POST['apellidos'] ?? '', 2, 40, false, true);
    $email_raw = $_POST['email'] ?? '';
    $email = $email_raw === '' ? '' : v_email($email_raw);
    $prefijo = $_POST['prefijo'] ?? '';
    $numero_7 = $_POST['numero_7'] ?? '';
    $telefono = v_telefono_ve($prefijo, $numero_7);
    $fecha_nacimiento = v_fecha($_POST['fecha_nacimiento'] ?? '', true);
    $notas = v_texto($_POST['notas'] ?? '', 0, 500);

    if ($cliente_id === null) {
        $error = 'ID de cliente invalido.';
    } elseif ($nombres === null) {
        $error = 'El nombre debe tener entre 2 y 40 caracteres y solo letras.';
    } elseif ($apellidos === null) {
        $error = 'El apellido debe tener entre 2 y 40 caracteres y solo letras.';
    } elseif ($email_raw !== '' && $email === null) {
        $error = 'El email no es valido.';
    } elseif ($telefono === null) {
        $error = 'El telefono no es valido. Usa un prefijo venezolano y 7 digitos.';
    } elseif ($fecha_nacimiento === null) {
        $error = 'La fecha de nacimiento no es valida.';
    } elseif ($notas === null) {
        $error = 'Las notas superan los 500 caracteres.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT usuario_id FROM clientes WHERE id = ?");
            $stmt->execute([$cliente_id]);
            $usuario_id = $stmt->fetchColumn();
            if (!$usuario_id) throw new Exception('Cliente no encontrado.');

            $stmt = $pdo->prepare("SELECT id FROM clientes WHERE telefono = ? AND id != ?");
            $stmt->execute([$telefono, $cliente_id]);
            if ($stmt->fetchColumn()) throw new Exception('Ese numero de telefono ya esta registrado en otro cliente.');

            $stmt = $pdo->prepare("UPDATE clientes SET nombres = ?, apellidos = ?, telefono = ?, fecha_nacimiento = ?, notas = ? WHERE id = ?");
            $stmt->execute([$nombres, $apellidos, $telefono, $fecha_nacimiento ?: null, $notas ?: null, $cliente_id]);

            if (!empty($email)) {
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $stmt->execute([$email, $usuario_id]);
                if ($stmt->fetchColumn()) throw new Exception('Ya existe otro usuario con ese email.');
                $stmt = $pdo->prepare("UPDATE usuarios SET email = ? WHERE id = ?");
                $stmt->execute([$email, $usuario_id]);
            }

            $pdo->commit();
            $mensaje = 'Cliente actualizado.';
        } catch (Exception $e) {
            $pdo->rollBack();
            log_error_seguro('cliente-edit', $e);
            $error = $e->getMessage();
        }
    }

    if ($mensaje || $error) {
        $_SESSION['flash_mensaje'] = $mensaje;
        $_SESSION['flash_error'] = $error;
        $qs = $_GET;
        $query_str = !empty($qs) ? '?' . http_build_query($qs) : '';
        redirect('admin/clientes.php' . $query_str);
    }
}

// Filtros
$buscar = trim($_GET['buscar'] ?? '');
if (strlen($buscar) > 100) $buscar = substr($buscar, 0, 100);

$orden = $_GET['orden'] ?? 'recientes';
$ordenes_validos = ['recientes', 'nombre', 'mas_pedidos', 'mas_gastado'];
if (!in_array($orden, $ordenes_validos, true)) $orden = 'recientes';

$where = ["u.rol_id = 2"];
$params = [];

if ($buscar !== '') {
    $where[] = "(c.nombres LIKE ? OR c.apellidos LIKE ? OR u.email LIKE ? OR c.telefono LIKE ?)";
    $like = '%' . $buscar . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$order_sql = 'u.fecha_registro DESC';
if ($orden === 'nombre') $order_sql = 'c.nombres ASC, c.apellidos ASC';
elseif ($orden === 'mas_pedidos') $order_sql = 'total_pedidos DESC';
elseif ($orden === 'mas_gastado') $order_sql = 'total_gastado DESC';

$where_sql = 'WHERE ' . implode(' AND ', $where);

$sql = "
    SELECT c.id, c.usuario_id, c.nombres, c.apellidos, c.telefono, c.fecha_nacimiento, c.notas,
           u.email, u.fecha_registro,
           (SELECT COUNT(*) FROM pedidos p WHERE p.usuario_id = c.usuario_id) AS total_pedidos,
           (SELECT COALESCE(SUM(total_pedido), 0) FROM pedidos p WHERE p.usuario_id = c.usuario_id AND p.estado_id != 6) AS total_gastado,
           (SELECT MAX(fecha_pedido) FROM pedidos p WHERE p.usuario_id = c.usuario_id) AS ultima_compra
    FROM clientes c
    JOIN usuarios u ON c.usuario_id = u.id
    $where_sql
    ORDER BY $order_sql
";

// Paginación de clientes (10 por página)
$pag_clientes = paginar($pdo, $sql, $params, 10);
$clientes = $pag_clientes['items'];

$pedidos_por_cliente = [];
if (!empty($clientes)) {
    $usuario_ids = array_column($clientes, 'usuario_id');
    $ph = implode(',', array_fill(0, count($usuario_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT p.id, p.usuario_id, p.fecha_pedido, p.total_pedido, p.tipo_entrega,
               e.nombre AS estado_nombre, e.id AS estado_id,
               mp.nombre AS metodo_pago_nombre
        FROM pedidos p
        JOIN estados_pedido e ON p.estado_id = e.id
        JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
        WHERE p.usuario_id IN ($ph)
        ORDER BY p.fecha_pedido DESC
    ");
    $stmt->execute($usuario_ids);
    foreach ($stmt->fetchAll() as $p) {
        if (!isset($pedidos_por_cliente[$p['usuario_id']])) $pedidos_por_cliente[$p['usuario_id']] = [];
        if (count($pedidos_por_cliente[$p['usuario_id']]) < 5) $pedidos_por_cliente[$p['usuario_id']][] = $p;
    }
}

$kpi_total = $pdo->query("SELECT COUNT(*) FROM clientes c JOIN usuarios u ON c.usuario_id = u.id WHERE u.rol_id = 2")->fetchColumn();
$kpi_nuevos_mes = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol_id = 2 AND YEAR(fecha_registro) = YEAR(CURDATE()) AND MONTH(fecha_registro) = MONTH(CURDATE())")->fetchColumn();
$kpi_ticket = $pdo->query("SELECT COALESCE(AVG(p.total_pedido), 0) FROM pedidos p JOIN usuarios u ON p.usuario_id = u.id WHERE u.rol_id = 2 AND p.estado_id != 6")->fetchColumn();
$kpi_activos = $pdo->query("SELECT COUNT(DISTINCT p.usuario_id) FROM pedidos p JOIN usuarios u ON p.usuario_id = u.id WHERE u.rol_id = 2 AND p.estado_id != 6 AND p.fecha_pedido >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();

$colores_estados = [1=>'#ffc107', 2=>'#17a2b8', 3=>'#007bff', 4=>'#28a745', 5=>'#6c757d', 6=>'#dc3545', 7=>'#9c27b0'];

partial('head', [
    'titulo_pagina' => 'Clientes',
    'cargar_sweetalert' => true,
    'css_pagina' => 'admin',
    'css_extra' => ['css/paginacion.css', 'css/clientes.css']
]);
?>

<?php partial('menu', ['menu_activo' => 'clientes']); ?>
<?php partial('header', ['titulo_pagina' => 'Clientes']); ?>

<main class="container clientes-main">
    <h2 class="titulo-admin"><i class="fas fa-users"></i> Clientes</h2>

    <?php if ($mensaje): ?><div data-flash-success="<?= htmlspecialchars($mensaje) ?>" class="d-none"></div><?php endif; ?>
    <?php if ($error): ?><div data-flash-error="<?= htmlspecialchars($error) ?>" class="d-none"></div><?php endif; ?>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="icono"><i class="fas fa-users"></i></div>
            <div class="info">
                <div class="label">Total</div>
                <div class="valor"><?= number_format($kpi_total) ?></div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="icono"><i class="fas fa-user-plus"></i></div>
            <div class="info">
                <div class="label">Nuevos este mes</div>
                <div class="valor"><?= number_format($kpi_nuevos_mes) ?></div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="icono"><i class="fas fa-shopping-bag"></i></div>
            <div class="info">
                <div class="label">Activos (30d)</div>
                <div class="valor"><?= number_format($kpi_activos) ?></div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="icono"><i class="fas fa-receipt"></i></div>
            <div class="info">
                <div class="label">Ticket promedio</div>
                <div class="valor">$<?= number_format($kpi_ticket, 2) ?></div>
            </div>
        </div>
    </div>

    <form method="get" class="filtros-card">
        <div class="filtros-grid filtros-clientes-grid">
            <div class="filtro-item">
                <label>Buscar</label>
                <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>" placeholder="Nombre, email o telefono" maxlength="100">
            </div>
            <div class="filtro-item">
                <label>Ordenar por</label>
                <select name="orden">
                    <option value="recientes" <?= $orden === 'recientes' ? 'selected' : '' ?>>Mas recientes</option>
                    <option value="nombre" <?= $orden === 'nombre' ? 'selected' : '' ?>>Nombre A-Z</option>
                    <option value="mas_pedidos" <?= $orden === 'mas_pedidos' ? 'selected' : '' ?>>Mas pedidos</option>
                    <option value="mas_gastado" <?= $orden === 'mas_gastado' ? 'selected' : '' ?>>Mas gastado</option>
                </select>
            </div>
            <button type="submit" class="btn-filtro btn-filtro-aplicar"><i class="fas fa-search"></i> Aplicar</button>
            <?php if ($buscar !== '' || $orden !== 'recientes'): ?>
                <a href="<?= url('admin/clientes.php') ?>" class="btn-filtro btn-filtro-limpiar"><i class="fas fa-times"></i> Limpiar</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="tabla-card">
        <?php if (count($clientes) === 0): ?>
            <div class="sin-resultados">
                <i class="fas fa-user-slash icono-sin-clientes"></i>
                <p>No se encontraron clientes.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="tabla-admin">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Email</th>
                            <th>Telefono</th>
                            <th>Registro</th>
                            <th>Pedidos</th>
                            <th>Gastado</th>
                            <th>Ultima</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $c): ?>
                            <?php $pc = $pedidos_por_cliente[$c['usuario_id']] ?? []; ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($c['nombres'] . ' ' . $c['apellidos']) ?></strong>
                                    <?php if (!empty($c['notas'])): ?>
                                        <br><small class="nota-cliente-badge"><i class="fas fa-sticky-note"></i> <?= htmlspecialchars($c['notas']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($c['email']) ?></td>
                                <td><?= htmlspecialchars($c['telefono'] ?? '-') ?></td>
                                <td><?= date('d/m/Y', strtotime($c['fecha_registro'])) ?></td>
                                <td><span class="badge-pedidos"><?= number_format($c['total_pedidos']) ?></span></td>
                                <td><strong>$<?= number_format($c['total_gastado'], 2) ?></strong></td>
                                <td><?= $c['ultima_compra'] ? date('d/m/Y', strtotime($c['ultima_compra'])) : '-' ?></td>
                                <td>
                                    <div class="acciones-container">
                                        <button type="button" class="btn btn-info" onclick='verPedidos(<?= json_encode(["id" => intval($c["id"]), "nombre" => $c["nombres"] . " " . $c["apellidos"], "total_pedidos" => intval($c["total_pedidos"]), "total_gastado" => $c["total_gastado"]], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($pc, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Ver pedidos del cliente">
                                            <i class="fas fa-receipt"></i> Pedidos
                                        </button>
                                        <button type="button" class="btn btn-warning" onclick='editarCliente(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Editar datos del cliente">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= renderPaginacion($pag_clientes) ?>
        <?php endif; ?>
    </div>
</main>

<!-- MODAL VER PEDIDOS DEL CLIENTE -->
<div class="modal-overlay" id="modalPedidosCliente">
    <div class="modal-box modal-box-lg">
        <button type="button" class="modal-cerrar" onclick="cerrarModalPedidos()">X</button>
        <h3 id="modal_ped_titulo"><i class="fas fa-receipt"></i> Pedidos del cliente</h3>
        <p id="modal_ped_resumen" class="modal-subtitulo"></p>
        <div id="modal_ped_lista" class="lista-pedidos-modal"></div>
        <div class="modal-acciones">
            <a href="#" id="modal_ped_ver_todos" class="btn-admin">Ver todos en Pedidos</a>
            <button type="button" class="btn btn-secondary btn-cancelar-modal" onclick="cerrarModalPedidos()">Cerrar</button>
        </div>
    </div>
</div>

<!-- MODAL EDITAR CLIENTE -->
<div class="modal-overlay" id="modalEditarCliente">
    <div class="modal-box">
        <button type="button" class="modal-cerrar" onclick="cerrarModalEditar()">X</button>
        <h3><i class="fas fa-user-edit"></i> Editar cliente</h3>
        <form method="post" novalidate>
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="cliente_id" id="edit_cli_id">
            <div class="form-grid">
                <div class="campo-form">
                    <label>Nombres *</label>
                    <input type="text" name="nombres" id="edit_cli_nombres" required minlength="2" maxlength="40">
                </div>
                <div class="campo-form">
                    <label>Apellidos *</label>
                    <input type="text" name="apellidos" id="edit_cli_apellidos" required minlength="2" maxlength="40">
                </div>
                <div class="campo-form">
                    <label>Email *</label>
                    <input type="email" name="email" id="edit_cli_email" required maxlength="100">
                </div>
                <div class="campo-form">
                    <label>Telefono *</label>
                    <div class="input-inline-flex">
                        <select name="prefijo" id="edit_cli_prefijo" class="select-prefijo-cli">
                            <?php foreach ($prefijos_ve as $pr): ?>
                                <option value="<?= $pr ?>"><?= $pr ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="tel" name="numero_7" id="edit_cli_numero" required maxlength="7" minlength="7" pattern="[0-9]{7}" inputmode="numeric" class="input-flex-1">
                    </div>
                </div>
                <div class="campo-form col-full">
                    <label>Fecha de nacimiento</label>
                    <input type="date" name="fecha_nacimiento" id="edit_cli_nacimiento">
                </div>
                <div class="campo-form col-full">
                    <label>Notas internas (opcional)</label>
                    <textarea name="notas" id="edit_cli_notas" rows="2" maxlength="500" placeholder="Preferencias o notas sobre el cliente..."></textarea>
                </div>
            </div>
            <div class="modal-acciones">
                <button type="button" class="btn btn-secondary btn-cancelar-modal" onclick="cerrarModalEditar()">Cancelar</button>
                <button type="submit" class="btn-admin"><i class="fas fa-save"></i> Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
window.CLIENTES_CONFIG = {
    coloresEstados: <?= json_encode($colores_estados) ?>,
    urlPedidosAdmin: '<?= url('admin/pedidos.php?cliente_id=') ?>',
    urlVerDetalle: '<?= url('cliente/ver_detalle.php?id=') ?>'
};
</script>

<?php partial('footer', ['cargar_admin_ux' => true, 'js_extra_footer' => ['js/clientes.js']]); ?>