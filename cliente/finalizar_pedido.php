<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';

if (!usuarioLogueado()) { redirect('auth/login.php'); }

// ============================================================
// VALIDACIÓN DE ESTADO
// ============================================================
if (!isset($_SESSION['checkout_data']) || empty($_SESSION['carrito'])) {
    redirect_prg('cliente/checkout.php');
}

$data = $_SESSION['checkout_data'];
$carrito = $_SESSION['carrito'];
$usuario_id = $_SESSION['usuario_id'];

$metodo_pago_str = $data['metodo_pago'] ?? '';
$comprobante = $data['comprobante'] ?? null;

// Ambos métodos (PagoMóvil total y Efectivo con 50% de adelanto) requieren haber pasado por comprobante_pago.php
if (empty($comprobante)) {
    redirect_prg('cliente/comprobante_pago.php');
}

$total = 0;
foreach ($carrito as $item) {
    $total += floatval($item['precio']) * intval($item['cantidad']);
}

$tipo_entrega = $data['tipo_entrega'] ?? 'recoger';
$direccion_id = intval($data['direccion_id'] ?? 0);
$direccion_texto = $data['direccion_texto'] ?? '';
$monto_pagado = floatval($data['monto_pagado'] ?? 0);

$metodo_pago_map = ['efectivo' => 1, 'pago_movil' => 5];
$metodo_pago_id = $metodo_pago_map[$metodo_pago_str] ?? 1;

// Entra como pendiente de verificación (1) hasta que el administrador confirme el pago
$estado_pago_id = 1;

try {
    $pdo->beginTransaction();

    // Si eligió una dirección guardada, verificar que siga existiendo en la BD
    $dir_id_valido = null;
    if ($tipo_entrega === 'delivery' && $direccion_id > 0) {
        $stmtChkDir = $pdo->prepare("SELECT d.id FROM direcciones d JOIN clientes c ON d.cliente_id = c.id WHERE d.id = ? AND c.usuario_id = ?");
        $stmtChkDir->execute([$direccion_id, $usuario_id]);
        if ($stmtChkDir->fetchColumn()) {
            $dir_id_valido = $direccion_id;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO pedidos (usuario_id, fecha_pedido, total_pedido, estado_id,
                             tipo_entrega, direccion_entrega_id, direccion_texto,
                             metodo_pago_id, monto_pagado, comprobante, estado_pago_id)
        VALUES (?, NOW(), ?, 1, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $usuario_id,
        $total,
        $tipo_entrega,
        $dir_id_valido,
        $direccion_texto,
        $metodo_pago_id,
        $monto_pagado,
        $comprobante,
        $estado_pago_id
    ]);
    $pedido_id = $pdo->lastInsertId();

    $stmtHist = $pdo->prepare("
        INSERT INTO historial_estados_pedido 
        (pedido_id, usuario_id, estado_anterior_id, estado_nuevo_id, notas) 
        VALUES (?, ?, NULL, 1, ?)
    ");
    $stmtHist->execute([$pedido_id, $usuario_id, 'Pedido creado por el cliente']);

    $referencia = 'PAGO-' . str_pad($pedido_id, 6, '0', STR_PAD_LEFT);
    $stmtPago = $pdo->prepare("INSERT INTO pagos (pedido_id, fecha_pago, estado_pago_id, monto_total, referencia) VALUES (?, NOW(), ?, ?, ?)");
    $stmtPago->execute([$pedido_id, $estado_pago_id, $monto_pagado, $referencia]);
    $pago_id = $pdo->lastInsertId();

    $stmtDetPago = $pdo->prepare("INSERT INTO detalle_pago (pago_id, metodo_pago_id, monto_parcial, referencia_externa) VALUES (?, ?, ?, ?)");
    $stmtDetPago->execute([$pago_id, $metodo_pago_id, $monto_pagado, $referencia]);

    $stmtDet = $pdo->prepare("INSERT INTO detalle_pedido (pedido_id, producto_id, cantidad, precio_unitario_historico) VALUES (?, ?, ?, ?)");
    $stmtExt = $pdo->prepare("INSERT INTO detalle_pedido_extras (detalle_pedido_id, extra_id, nombre, precio_extra) VALUES (?, ?, ?, ?)");

    foreach ($carrito as $item) {
        $precio_final = floatval($item['precio']);
        $suma_extras = 0;
        if (!empty($item['extras'])) {
            foreach ($item['extras'] as $e) {
                $suma_extras += floatval($e['precio']);
            }
        }
        $precio_base = max(0, $precio_final - $suma_extras);

        $stmtDet->execute([$pedido_id, intval($item['id']), intval($item['cantidad']), $precio_base]);
        $detalle_id = $pdo->lastInsertId();

        if (!empty($item['extras'])) {
            foreach ($item['extras'] as $extra) {
                $stmtExt->execute([$detalle_id, intval($extra['id']), $extra['nombre'], floatval($extra['precio'])]);
            }
        }
    }

    $pdo->commit();

    // Flag para detectar si el usuario intenta volver atrás
    $_SESSION['pedido_finalizado_id'] = $pedido_id;

    unset($_SESSION['carrito']);
    unset($_SESSION['checkout_data']);

    redirect_prg('cliente/factura.php?id=' . $pedido_id);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Finalizar pedido error: ' . $e->getMessage());
    die('Error al guardar el pedido. Por favor intenta de nuevo o contactanos.');
}