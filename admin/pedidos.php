<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';
require_once __DIR__ . '/../includes/paginacion.php';

if (!esAdmin()) { redirect('index.php'); }
requierePermiso('pedidos');

$mensaje = $_SESSION['flash_mensaje'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_mensaje'], $_SESSION['flash_error']);

$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';
$filtro_estado = intval($_GET['estado'] ?? 0);
$filtro_entrega = $_GET['entrega'] ?? '';
$filtro_metodo_pago = intval($_GET['metodo_pago'] ?? 0);
$filtro_estado_pago = intval($_GET['estado_pago'] ?? 0);
$filtro_cliente_id = intval($_GET['cliente_id'] ?? 0);

$flujo_estados = [1 => 2, 2 => 3, 3 => 4, 4 => 7, 7 => 5, 5 => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $filtros_redirect = [
        'fecha_desde' => $_POST['f_fecha_desde'] ?? '', 'fecha_hasta' => $_POST['f_fecha_hasta'] ?? '',
        'estado' => $_POST['f_estado'] ?? '', 'entrega' => $_POST['f_entrega'] ?? '',
        'metodo_pago' => $_POST['f_metodo_pago'] ?? '', 'estado_pago' => $_POST['f_estado_pago'] ?? '',
        'cliente_id' => $_POST['f_cliente_id'] ?? '', 'pagina' => $_POST['f_pagina'] ?? ''
    ];
    $query_redirect = http_build_query(array_filter($filtros_redirect, function($v) { return $v !== '' && $v !== '0' && $v !== null; }));

    if ($_POST['accion'] === 'cambiar_estado') {
        $pedido_id = intval($_POST['pedido_id']);
        $nuevo_estado = intval($_POST['estado_id']);
        $stmt = $pdo->prepare("SELECT usuario_id, tipo_entrega, estado_id, estado_pago_id, fecha_pedido FROM pedidos WHERE id = ?");
        $stmt->execute([$pedido_id]);
        $pedido = $stmt->fetch();

        // VALIDACIÓN DE EXPIRACIÓN (Solo si sigue en estado 1 y aún no está pagado)
        if ($pedido && $pedido['estado_id'] == 1 && $pedido['estado_pago_id'] == 1 && (time() - strtotime($pedido['fecha_pedido'])) > 1200) {
            $pdo->prepare("UPDATE pedidos SET estado_id = 6, razon_cancelacion = 'Cancelado automáticamente (tiempo límite excedido)' WHERE id = ?")->execute([$pedido_id]);
            $error = "El pedido #$pedido_id ha expirado y fue cancelado automáticamente. No puedes avanzarlo.";
        } elseif ($pedido && $pedido['estado_pago_id'] == 1) {
            $error = "No puedes avanzar el pedido #$pedido_id hasta que confirmes el pago.";
        } elseif (!$pedido) {
            $error = "Pedido no encontrado.";
        } elseif (in_array(intval($pedido['estado_id']), [5, 6])) {
            $error = "El pedido #$pedido_id ya esta finalizado o cancelado.";
        } else {
            $usuario_id = $pedido['usuario_id'];
            $tipo_entrega = $pedido['tipo_entrega'] ?? 'recoger';
            $estado_actual = intval($pedido['estado_id']);
            $siguiente_permitido = $flujo_estados[$estado_actual] ?? null;

            if ($estado_actual == 4 && $tipo_entrega === 'recoger' && $nuevo_estado == 5) { $siguiente_permitido = 5; }

            if ($nuevo_estado !== $siguiente_permitido) {
                $error = "Solo se puede avanzar al siguiente estado.";
            } else {
                $stmt = $pdo->prepare("UPDATE pedidos SET estado_id = ? WHERE id = ?");
                if ($stmt->execute([$nuevo_estado, $pedido_id])) {
                    $stmtHist = $pdo->prepare("INSERT INTO historial_estados_pedido (pedido_id, usuario_id, estado_anterior_id, estado_nuevo_id, notas) VALUES (?, ?, ?, ?, ?)");
                    $stmtHist->execute([$pedido_id, $_SESSION['usuario_id'], $estado_actual, $nuevo_estado, 'Cambio desde admin']);
                    $mensaje = "Estado del pedido #$pedido_id actualizado.";

                    $titulo_notif = ''; $msg_notif = '';
                    switch ($nuevo_estado) {
                        case 2: $titulo_notif = 'Pedido confirmado'; $msg_notif = "Tu pedido #$pedido_id fue confirmado."; break;
                        case 3: $titulo_notif = 'Pedido en preparacion'; $msg_notif = "Tu pedido #$pedido_id esta siendo preparado."; break;
                        case 4: $titulo_notif = 'Tu pedido esta listo!'; $msg_notif = ($tipo_entrega === 'delivery') ? "Tu pedido #$pedido_id esta listo para delivery." : "Tu pedido #$pedido_id esta listo para recoger."; break;
                        case 7: $titulo_notif = 'Tu pedido esta en camino!'; $msg_notif = "El pedido #$pedido_id esta en camino."; break;
                        case 5: $titulo_notif = 'Pedido entregado!'; $msg_notif = "Tu pedido #$pedido_id fue entregado."; break;
                    }
                    if ($titulo_notif) crearNotificacion($pdo, $usuario_id, $pedido_id, $titulo_notif, $msg_notif);
                } else {
                    $error = "No se pudo actualizar el estado del pedido #$pedido_id.";
                }
            }
        }
    } elseif ($_POST['accion'] === 'cancelar_pedido') {
        $pedido_id = intval($_POST['pedido_id']);
        $razon = trim($_POST['razon_cancelacion'] ?? '');

        if ($razon === '') {
            $error = "Debes indicar una razon para cancelar el pedido.";
        } else {
            $stmt = $pdo->prepare("SELECT usuario_id, estado_id FROM pedidos WHERE id = ?");
            $stmt->execute([$pedido_id]);
            $pedido = $stmt->fetch();

            if ($pedido && !in_array(intval($pedido['estado_id']), [5, 6, 7])) {
                $estado_actual = intval($pedido['estado_id']);
                $stmt = $pdo->prepare("UPDATE pedidos SET estado_id = 6, razon_cancelacion = ? WHERE id = ?");
                if ($stmt->execute([$razon, $pedido_id])) {
                    $stmtHist = $pdo->prepare("INSERT INTO historial_estados_pedido (pedido_id, usuario_id, estado_anterior_id, estado_nuevo_id, notas) VALUES (?, ?, ?, 6, ?)");
                    $stmtHist->execute([$pedido_id, $_SESSION['usuario_id'], $estado_actual, 'Cancelado. Razon: ' . $razon]);
                    crearNotificacion($pdo, $pedido['usuario_id'], $pedido_id, "Pedido cancelado", "Tu pedido #$pedido_id fue cancelado. Razon: $razon");
                    $mensaje = "Pedido #$pedido_id cancelado.";
                }
            } else {
                $error = "El pedido #$pedido_id no puede cancelarse en su estado actual.";
            }
        }
    } elseif ($_POST['accion'] === 'marcar_pago_completado') {
        $pedido_id = intval($_POST['pedido_id']);
        $stmt = $pdo->prepare("SELECT estado_id, estado_pago_id, fecha_pedido FROM pedidos WHERE id = ?");
        $stmt->execute([$pedido_id]);
        $pedido_check = $stmt->fetch();

        if ($pedido_check && $pedido_check['estado_id'] == 1 && $pedido_check['estado_pago_id'] == 1 && (time() - strtotime($pedido_check['fecha_pedido'])) > 1200) {
            $pdo->prepare("UPDATE pedidos SET estado_id = 6, razon_cancelacion = 'Cancelado automáticamente (tiempo límite excedido)' WHERE id = ?")->execute([$pedido_id]);
            $error = "El pedido #$pedido_id ha expirado y fue cancelado automáticamente. No se puede cobrar.";
        } else {
            $stmt = $pdo->prepare("UPDATE pedidos SET estado_pago_id = 2 WHERE id = ?");
            if ($stmt->execute([$pedido_id])) {
                $stmtPago = $pdo->prepare("UPDATE pagos SET estado_pago_id = 2 WHERE pedido_id = ?");
                $stmtPago->execute([$pedido_id]);
                $mensaje = "Pago del pedido #$pedido_id confirmado y completado. Ya puedes avanzar el pedido.";

                $stmtP = $pdo->prepare("SELECT usuario_id FROM pedidos WHERE id = ?");
                $stmtP->execute([$pedido_id]);
                $uid = $stmtP->fetchColumn();
                crearNotificacion($pdo, $uid, $pedido_id, "Pago confirmado", "Hemos confirmado tu pago para el pedido #$pedido_id. Gracias!");
            }
        }
    }

    if ($mensaje || $error) {
        $_SESSION['flash_mensaje'] = $mensaje;
        $_SESSION['flash_error'] = $error;
        redirect('admin/pedidos.php' . ($query_redirect ? '?' . $query_redirect : ''));
    }
}

$where = []; $params = [];
if ($filtro_fecha_desde !== '') { $where[] = "DATE(p.fecha_pedido) >= ?"; $params[] = $filtro_fecha_desde; }
if ($filtro_fecha_hasta !== '') { $where[] = "DATE(p.fecha_pedido) <= ?"; $params[] = $filtro_fecha_hasta; }
if ($filtro_estado > 0) { $where[] = "p.estado_id = ?"; $params[] = $filtro_estado; }
if ($filtro_entrega === 'delivery' || $filtro_entrega === 'recoger') { $where[] = "p.tipo_entrega = ?"; $params[] = $filtro_entrega; }
if ($filtro_metodo_pago > 0) { $where[] = "p.metodo_pago_id = ?"; $params[] = $filtro_metodo_pago; }
if ($filtro_estado_pago > 0) { $where[] = "p.estado_pago_id = ?"; $params[] = $filtro_estado_pago; }
if ($filtro_cliente_id > 0) { $where[] = "c.id = ?"; $params[] = $filtro_cliente_id; }

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql_base = "SELECT p.*, c.nombres, c.apellidos, c.telefono, e.nombre AS estado_nombre, mp.nombre AS metodo_pago_nombre, ep.nombre AS estado_pago_nombre
    FROM pedidos p
    JOIN usuarios u ON p.usuario_id = u.id
    JOIN clientes c ON u.id = c.usuario_id
    JOIN estados_pedido e ON p.estado_id = e.id
    JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
    JOIN estados_pago ep ON p.estado_pago_id = ep.id
    $where_sql ORDER BY p.fecha_pedido DESC";

$pag = paginar($pdo, $sql_base, $params, 20);
$pedidos = $pag['items'];

$sql_resumen = "SELECT 
    COUNT(*) AS total_pedidos,
    COALESCE(SUM(CASE WHEN p.estado_id != 6 THEN p.total_pedido ELSE 0 END), 0) AS total_facturado,
    SUM(CASE WHEN p.estado_id = 6 THEN 1 ELSE 0 END) AS total_cancelados
    FROM pedidos p
    JOIN usuarios u ON p.usuario_id = u.id
    JOIN clientes c ON u.id = c.usuario_id
    JOIN estados_pedido e ON p.estado_id = e.id
    JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
    JOIN estados_pago ep ON p.estado_pago_id = ep.id
    $where_sql";
$stmt = $pdo->prepare($sql_resumen);
$stmt->execute($params);
$resumen = $stmt->fetch();

$estados = $pdo->query("SELECT * FROM estados_pedido ORDER BY id")->fetchAll();
$metodos_pago = $pdo->query("SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY id")->fetchAll();
$estados_pago = $pdo->query("SELECT * FROM estados_pago ORDER BY id")->fetchAll();
$clientes = $pdo->query("SELECT c.id, c.nombres, c.apellidos FROM clientes c JOIN usuarios u ON c.usuario_id = u.id ORDER BY c.nombres, c.apellidos")->fetchAll();

$colores_estados = [1=>'#ffc107', 2=>'#17a2b8', 3=>'#007bff', 4=>'#28a745', 5=>'#6c757d', 6=>'#dc3545', 7=>'#9c27b0'];
$colores_pago = ['pendiente'=>'#ffc107', 'completado'=>'#28a745', 'fallido'=>'#dc3545', 'reembolsado'=>'#6c757d'];
$hay_filtros = !empty($where);

partial('head', [
    'titulo_pagina' => 'Todos los pedidos',
    'cargar_sweetalert' => true,
    'css_pagina' => 'admin',
    'css_extra' => ['css/paginacion.css', 'css/pedidos.css']
]);
?>

<?php partial('menu', ['menu_activo' => 'pedidos']); ?>
<?php partial('header', ['titulo_pagina' => 'Todos los pedidos']); ?>

<main class="container main-pedidos">
    <h2 class="titulo-admin"><i class="fas fa-clipboard-list"></i> Gestionar pedidos</h2>

    <?php if ($mensaje): ?><div data-flash-success="<?= htmlspecialchars($mensaje) ?>" style="display:none;"></div><?php endif; ?>
    <?php if ($error): ?><div data-flash-error="<?= htmlspecialchars($error) ?>" style="display:none;"></div><?php endif; ?>

    <form method="get" class="filtros-card">
        <div class="filtros-grid">
            <div class="filtro-item"><label>Desde</label><input type="date" name="fecha_desde" value="<?= htmlspecialchars($filtro_fecha_desde) ?>"></div>
            <div class="filtro-item"><label>Hasta</label><input type="date" name="fecha_hasta" value="<?= htmlspecialchars($filtro_fecha_hasta) ?>"></div>
            <div class="filtro-item"><label>Estado</label><select name="estado"><option value="0">Todos</option><?php foreach ($estados as $e): ?><option value="<?= $e['id'] ?>" <?= $filtro_estado == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($e['nombre'])) ?></option><?php endforeach; ?></select></div>
            <div class="filtro-item"><label>Entrega</label><select name="entrega"><option value="">Todas</option><option value="delivery" <?= $filtro_entrega === 'delivery' ? 'selected' : '' ?>>Delivery</option><option value="recoger" <?= $filtro_entrega === 'recoger' ? 'selected' : '' ?>>Recoger</option></select></div>
            <div class="filtro-item"><label>Metodo pago</label><select name="metodo_pago"><option value="0">Todos</option><?php foreach ($metodos_pago as $mp): ?><option value="<?= $mp['id'] ?>" <?= $filtro_metodo_pago == $mp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($mp['nombre']) ?></option><?php endforeach; ?></select></div>
            <div class="filtro-item"><label>Estado pago</label><select name="estado_pago"><option value="0">Todos</option><?php foreach ($estados_pago as $ep): ?><option value="<?= $ep['id'] ?>" <?= $filtro_estado_pago == $ep['id'] ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($ep['nombre'])) ?></option><?php endforeach; ?></select></div>
            <div class="filtro-item"><label>Cliente</label><select name="cliente_id"><option value="0">Todos</option><?php foreach ($clientes as $cl): ?><option value="<?= $cl['id'] ?>" <?= $filtro_cliente_id == $cl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cl['nombres'] . ' ' . $cl['apellidos']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="filtros-acciones">
            <button type="submit" class="btn-filtro btn-filtro-aplicar"><i class="fas fa-filter"></i> Aplicar filtros</button>
            <?php if ($hay_filtros): ?><a href="<?= url('admin/pedidos.php') ?>" class="btn-filtro btn-filtro-limpiar"><i class="fas fa-times"></i> Limpiar</a><?php endif; ?>
            <?php $qs_csv = http_build_query($_GET); $url_csv = url('admin/exportar_pedidos_csv.php') . ($qs_csv ? '?' . $qs_csv : ''); ?>
            <a href="<?= htmlspecialchars($url_csv) ?>" class="btn-filtro btn-export-csv"><i class="fas fa-file-excel"></i> Exportar CSV</a>
        </div>
    </form>

    <div class="resumen-barra">
        <div class="resumen-item resumen-pedidos">
            <div class="resumen-icono"><i class="fas fa-clipboard-list"></i></div>
            <div class="resumen-texto">
                <span class="resumen-label">Pedidos mostrados</span>
                <strong class="resumen-valor"><?= number_format($resumen['total_pedidos']) ?></strong>
            </div>
        </div>
        <div class="resumen-item resumen-facturado">
            <div class="resumen-icono"><i class="fas fa-dollar-sign"></i></div>
            <div class="resumen-texto">
                <span class="resumen-label">Total facturado</span>
                <strong class="resumen-valor">$<?= number_format($resumen['total_facturado'], 2) ?></strong>
            </div>
        </div>
        <?php if ($resumen['total_cancelados'] > 0): ?>
        <div class="resumen-item resumen-cancelados">
            <div class="resumen-icono"><i class="fas fa-ban"></i></div>
            <div class="resumen-texto">
                <span class="resumen-label">Cancelados</span>
                <strong class="resumen-valor"><?= number_format($resumen['total_cancelados']) ?></strong>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="tabla-card">
        <div class="table-responsive">
            <table class="tabla-admin">
                <thead>
                    <tr><th>ID</th><th>Cliente</th><th>Fecha</th><th>Total</th><th>Entrega</th><th>Pago</th><th>Comp.</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($pedidos)): ?>
                        <tr><td colspan="9" class="tabla-vacia">No se encontraron pedidos.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pedidos as $p): ?>
                            <?php
                            $est_actual = intval($p['estado_id']);
                            $es_final = in_array($est_actual, [5, 6]);
                            $tipo_ent = $p['tipo_entrega'] ?? 'recoger';
                            $sig_estado = ($est_actual == 4 && $tipo_ent === 'recoger') ? 5 : ($flujo_estados[$est_actual] ?? null);
                            $etiquetas_boton = [2 => 'Confirmar', 3 => 'Preparar', 4 => 'Listo', 7 => 'En camino', 5 => 'Entregado'];
                            $texto_boton = $sig_estado ? ($etiquetas_boton[$sig_estado] ?? 'Siguiente') : '';
                            
                            $segundos_restantes = 0;
                            $esta_expirado = false;
                            if ($est_actual == 1 && $p['estado_pago_id'] == 1) {
                                $segundos_restantes = tiempoRestanteCancelacion($p['fecha_pedido']);
                                if ($segundos_restantes <= 0) {
                                    $esta_expirado = true;
                                }
                            }

                            // Configurar enlace de WhatsApp con el estado del pedido
                            $tel_cliente_raw = preg_replace('/\D/', '', $p['telefono'] ?? '');
                            $tel_wa_cliente = (strlen($tel_cliente_raw) >= 10) ? ('58' . ltrim($tel_cliente_raw, '0')) : '';
                            $nombre_cli = trim(($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? ''));
                            $id_ped = intval($p['id']);

                            $msg_wa = '';
                            switch ($est_actual) {
                                case 1:
                                    $msg_wa = "¡Hola {$nombre_cli}! Te escribimos de *Mina's Burguers* sobre tu *Pedido #{$id_ped}* ($" . number_format($p['total_pedido'], 2) . "). Estamos verificando tu pago para procesarlo.";
                                    break;
                                case 2:
                                    $msg_wa = "¡Hola {$nombre_cli}! Te informamos de *Mina's Burguers* que tu *Pedido #{$id_ped}* ya fue *Confirmado* y pronto pasará a cocina.";
                                    break;
                                case 3:
                                    $msg_wa = "¡Hola {$nombre_cli}! Tu *Pedido #{$id_ped}* en *Mina's Burguers* ya se está *Preparando* en cocina.";
                                    break;
                                case 4:
                                    $msg_wa = ($tipo_ent === 'delivery')
                                        ? "¡Hola {$nombre_cli}! Tu *Pedido #{$id_ped}* de *Mina's Burguers* ya está *Listo* y pronto saldrá con nuestro delivery."
                                        : "¡Hola {$nombre_cli}! Tu *Pedido #{$id_ped}* de *Mina's Burguers* ya está *Listo para retirar en el local*. ¡Te esperamos!";
                                    break;
                                case 7:
                                    $msg_wa = "¡Hola {$nombre_cli}! Tu *Pedido #{$id_ped}* de *Mina's Burguers* ya va *En camino* hacia tu dirección. ¡Atento a tu teléfono!";
                                    break;
                                case 5:
                                    $msg_wa = "¡Hola {$nombre_cli}! Tu *Pedido #{$id_ped}* de *Mina's Burguers* fue *Entregado*. ¡Muchas gracias por tu compra y buen provecho!";
                                    break;
                                case 6:
                                    $motivo = !empty($p['razon_cancelacion']) ? " Motivo: " . $p['razon_cancelacion'] : "";
                                    $msg_wa = "Hola {$nombre_cli}, te escribimos de *Mina's Burguers* para informarte que tu *Pedido #{$id_ped}* fue *Cancelado*.{$motivo}";
                                    break;
                            }
                            $url_wa_pedido = $tel_wa_cliente ? "https://wa.me/{$tel_wa_cliente}?text=" . urlencode($msg_wa) : '';
                            ?>
                            <tr data-pedido-row="<?= $p['id'] ?>">
                                <td><strong>#<?= $p['id'] ?></strong></td>
                                <td class="celda-cliente">
                                    <div class="cliente-nombre-txt"><?= htmlspecialchars($nombre_cli) ?></div>
                                    <?php if ($url_wa_pedido): ?>
                                        <a href="<?= htmlspecialchars($url_wa_pedido) ?>" target="_blank" rel="noopener noreferrer" class="chip-whatsapp-cliente" title="Enviar estado del pedido por WhatsApp">
                                            <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($p['telefono']) ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="celda-fecha">
                                    <div><?= date('d/m/Y H:i', strtotime($p['fecha_pedido'])) ?></div>
                                    <?php if ($est_actual == 1 && $p['estado_pago_id'] == 1): ?>
                                        <span class="timer-container" data-tiempo="<?= $segundos_restantes ?>">
                                            <i class="fas fa-stopwatch"></i> <span class="tiempo-texto">--:--</span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><strong>$<?= number_format($p['total_pedido'], 2) ?></strong></td>
                                <td class="celda-entrega">
                                    <span class="entrega-badge">
                                        <?= $tipo_ent === 'delivery' ? '<i class="fas fa-truck"></i> Delivery' : '<i class="fas fa-store"></i> Recoger' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-estado" style="background: <?= $colores_pago[$p['estado_pago_nombre']] ?? '#6c757d' ?>;"><?= htmlspecialchars(ucfirst($p['estado_pago_nombre'])) ?></span>
                                    <div class="metodo-pago-text"><?= htmlspecialchars($p['metodo_pago_nombre']) ?></div>
                                </td>
                                <td class="celda-centrada">
                                    <?php if (!empty($p['comprobante'])): ?>
                                        <button type="button" class="btn btn-info btn-icon-only" onclick="verComprobante('<?= htmlspecialchars($p['comprobante']) ?>', <?= $p['id'] ?>)" title="Ver comprobante">
                                            <i class="fas fa-image"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="sin-comp">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-estado" style="background: <?= $colores_estados[$est_actual] ?? '#6c757d' ?>;"><?= htmlspecialchars(ucfirst($p['estado_nombre'])) ?></span>
                                </td>
                                <td>
                                    <div class="acciones-container" id="acciones-pedido-<?= $p['id'] ?>">
                                        <?php if (!$esta_expirado): ?>
                                            
                                            <?php if ($p['estado_pago_id'] == 1 && $est_actual != 6): ?>
                                                <form method="post" class="form-cobrar form-inline" data-pedido="<?= $p['id'] ?>">
                                                    <input type="hidden" name="accion" value="marcar_pago_completado">
                                                    <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                                                    <input type="hidden" name="f_pagina" value="<?= $pag['pagina'] ?>">
                                                    <button type="submit" class="btn btn-cobrar" title="Confirmar que el dinero ingresó a la cuenta">
                                                        <i class="fas fa-check-circle"></i> Cobrar
                                                    </button>
                                                </form>
                                            <?php elseif (!$es_final && $sig_estado): ?>
                                                <form method="post" class="form-cambiar-estado form-inline" data-pedido="<?= $p['id'] ?>">
                                                    <input type="hidden" name="accion" value="cambiar_estado">
                                                    <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                                                    <input type="hidden" name="estado_id" value="<?= $sig_estado ?>">
                                                    <input type="hidden" name="f_pagina" value="<?= $pag['pagina'] ?>">
                                                    <button type="submit" class="btn btn-primary" title="Avanzar al siguiente estado">
                                                        <i class="fas fa-arrow-right"></i> <?= htmlspecialchars($texto_boton) ?>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="estado-final-text"><?= $est_actual == 5 ? 'Completado' : 'Cancelado' ?></span>
                                            <?php endif; ?>

                                            <?php if (!$es_final && $est_actual != 7): ?>
                                                <form method="post" class="form-cancelar form-inline" data-pedido="<?= $p['id'] ?>">
                                                    <input type="hidden" name="accion" value="cancelar_pedido">
                                                    <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                                                    <input type="hidden" name="razon_cancelacion" class="input-razon" value="">
                                                    <input type="hidden" name="f_pagina" value="<?= $pag['pagina'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-icon-only" title="Cancelar pedido">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                        <?php else: ?>
                                            <span class="accion-expirada"><i class="fas fa-ban"></i> Expirado</span>
                                        <?php endif; ?>

                                        <button type="button" class="btn btn-secondary btn-icon-only" onclick="verHistorial(<?= $p['id'] ?>)" title="Ver historial de estados">
                                            <i class="fas fa-history"></i>
                                        </button>
                                        <a href="<?= url('cliente/ver_detalle.php?id=' . $p['id']) ?>" class="btn btn-info btn-icon-only" title="Ver detalle del pedido">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= renderPaginacion($pag) ?>
    </div>
</main>

<!-- MODAL COMPROBANTE -->
<div class="modal-comprobante" id="modalComprobante" style="display:none;">
    <div class="comprobante-modal-box">
        <button type="button" class="cerrar" onclick="cerrarComprobante()">&times;</button>
        <h3 class="titulo" id="tituloComprobante"></h3>
        <div id="contenidoComprobante"></div>
    </div>
</div>

<!-- MODAL HISTORIAL -->
<div class="modal-overlay" id="modalHistorial">
    <div class="modal-box">
        <button type="button" class="modal-cerrar" onclick="cerrarHistorial()">X</button>
        <h3><i class="fas fa-history"></i> Historial del pedido #<span id="histPedidoId"></span></h3>
        <div id="histContenido" class="historial-modal-contenido"></div>
    </div>
</div>

<script>
window.PEDIDOS_CONFIG = {
    urlComprobantes: '<?= url('comprobantes/') ?>',
    urlObtenerHistorial: '<?= url('admin/obtener_historial.php') ?>'
};
</script>

<?php partial('footer', ['cargar_admin_ux' => true, 'js_extra_footer' => ['js/pedidos.js']]); ?>