<?php
require_once __DIR__ . '/../config.php';

if (!usuarioLogueado()) { redirect('auth/login.php'); }

$pedido_id = intval($_GET['id'] ?? 0);
if ($pedido_id == 0) die('ID no valido.');

$usuario_id = $_SESSION['usuario_id'];
$es_admin   = esAdmin();

if ($es_admin) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.email, c.nombres AS cliente_nombres, c.apellidos AS cliente_apellidos, c.telefono AS cliente_telefono,
               mp.nombre AS metodo_pago_nombre, ep.nombre AS estado_pago_nombre
        FROM pedidos p
        JOIN usuarios u      ON p.usuario_id = u.id
        LEFT JOIN clientes c ON u.id = c.usuario_id
        JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
        JOIN estados_pago ep ON p.estado_pago_id = ep.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pedido_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT p.*, u.email, c.nombres AS cliente_nombres, c.apellidos AS cliente_apellidos, c.telefono AS cliente_telefono,
               mp.nombre AS metodo_pago_nombre, ep.nombre AS estado_pago_nombre
        FROM pedidos p
        JOIN usuarios u      ON p.usuario_id = u.id
        LEFT JOIN clientes c ON u.id = c.usuario_id
        JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
        JOIN estados_pago ep ON p.estado_pago_id = ep.id
        WHERE p.id = ? AND p.usuario_id = ?
    ");
    $stmt->execute([$pedido_id, $usuario_id]);
}
$pedido = $stmt->fetch();
if (!$pedido) die('Pedido no encontrado.');

$detalles = $pdo->prepare("SELECT d.*, pr.nombre, pr.descripcion AS producto_descripcion FROM detalle_pedido d JOIN productos pr ON d.producto_id = pr.id WHERE d.pedido_id = ?");
$detalles->execute([$pedido_id]);
$productos = $detalles->fetchAll();

$extras = [];
if (!empty($productos)) {
    $ids = array_column($productos, 'id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmtExt = $pdo->prepare("SELECT detalle_pedido_id, nombre, precio_extra FROM detalle_pedido_extras WHERE detalle_pedido_id IN ($ph)");
    $stmtExt->execute($ids);
    foreach ($stmtExt->fetchAll() as $row) {
        $extras[$row['detalle_pedido_id']][] = $row;
    }
}

$estados = [
    1 => ['nombre' => 'Pendiente', 'color' => '#ffc107'],
    2 => ['nombre' => 'Confirmado', 'color' => '#17a2b8'],
    3 => ['nombre' => 'Preparando', 'color' => '#007bff'],
    4 => ['nombre' => 'Listo', 'color' => '#28a745'],
    5 => ['nombre' => 'Entregado', 'color' => '#6c757d'],
    6 => ['nombre' => 'Cancelado', 'color' => '#dc3545'],
    7 => ['nombre' => 'En camino', 'color' => '#9c27b0']
];
$estado_actual = $estados[$pedido['estado_id']] ?? ['nombre' => 'Desconocido', 'color' => '#6c757d'];

$nombre_cliente   = trim(($pedido['cliente_nombres'] ?? '') . ' ' . ($pedido['cliente_apellidos'] ?? ''));
$telefono_cliente = $pedido['cliente_telefono'] ?? '';
$whatsapp_link = '';
if (!empty($telefono_cliente)) {
    $tel_limpio = preg_replace('/[^0-9]/', '', $telefono_cliente);
    if (strlen($tel_limpio) >= 10) {
        if (substr($tel_limpio, 0, 1) === '0') {
            $tel_limpio = '58' . substr($tel_limpio, 1);
        } elseif (substr($tel_limpio, 0, 2) !== '58') {
            $tel_limpio = '58' . $tel_limpio;
        }
        $whatsapp_link = 'https://wa.me/' . $tel_limpio;
    }
}

// LOGICA DE PAGOS
$total_pedido = floatval($pedido['total_pedido']);
$monto_pagado = floatval($pedido['monto_pagado']);
$restante = $total_pedido - $monto_pagado;
$es_efectivo = (stripos($pedido['metodo_pago_nombre'], 'efectivo') !== false);

// TASA DEL DOLAR
$config = $pdo->query("SELECT tasa_dolar FROM configuracion_pago WHERE id = 1")->fetch();
$tasa = floatval($config['tasa_dolar'] ?? 1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comanda #<?= $pedido['id'] ?> - Minas Burguer</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/factura.css') ?>">
    
    <style>
        .factura-tabla { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        .factura-tabla tr { page-break-inside: avoid; page-break-after: auto; }
        @media print {
            .factura-container { width: 100%; max-width: 100%; box-shadow: none; border: none; }
            .factura-acciones { display: none !important; }
        }
        .desglose-pago { margin-top: 1rem; background: #f9f9f9; padding: 1rem; border-radius: 8px; border: 1px solid #eee; }
        .desglose-linea { display: flex; justify-content: space-between; padding: 0.3rem 0; font-size: 0.95rem; }
        .desglose-linea.total { border-top: 1px solid #ddd; margin-top: 0.5rem; padding-top: 0.5rem; font-weight: 700; font-size: 1.1rem; }
        .desglose-linea.restante { color: #d32f2f; font-weight: 800; }
    </style>
</head>
<body class="factura-body">

<div class="factura-container">
    <div class="factura-header">
        <h1>MINAS BURGUER</h1>
        <div class="factura-numero">Comanda #<?= str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) ?></div>
    </div>

    <div class="factura-datos">
        <div class="bloque">
            <strong>Cliente</strong>
            <span><?= htmlspecialchars($nombre_cliente) ?></span>
            <span style="font-size:0.9rem; color:#888;"><?= htmlspecialchars($pedido['email']) ?></span>
            <span style="font-size:0.9rem; color:#888;">
                <?php if ($whatsapp_link): ?>
                    <i class="fab fa-whatsapp" style="color: #25D366;"></i>
                    <a href="<?= htmlspecialchars($whatsapp_link) ?>" target="_blank" style="color: #888; text-decoration: none;">
                        <?= htmlspecialchars($telefono_cliente) ?>
                    </a>
                <?php else: ?>
                    Tel: No registrado
                <?php endif; ?>
            </span>
        </div>
        <div class="bloque">
            <strong>Pedido</strong>
            <span>Fecha: <?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?></span>
            <span>Estado: <span class="badge-estado-factura" style="background: <?= $estado_actual['color'] ?>;"><?= $estado_actual['nombre'] ?></span></span>
            <span>Metodo: <?= htmlspecialchars($pedido['metodo_pago_nombre']) ?></span>
        </div>
    </div>

    <?php if ($pedido['tipo_entrega'] === 'delivery' && !empty($pedido['direccion_texto'])): ?>
        <div class="factura-direccion">
            <strong>Direccion de entrega:</strong> <?= htmlspecialchars($pedido['direccion_texto']) ?>
        </div>
    <?php elseif ($pedido['tipo_entrega'] === 'recoger'): ?>
        <div class="factura-direccion">
            <strong>Retiro en local</strong>
        </div>
    <?php endif; ?>

    <table class="factura-tabla">
        <thead>
            <tr><th>Producto</th><th style="text-align:center;">Cant.</th><th style="text-align:right;">Precio unit.</th><th style="text-align:right;">Subtotal</th></tr>
        </thead>
        <tbody>
            <?php foreach ($productos as $item): ?>
                <?php
                $nombre = htmlspecialchars($item['nombre']);
                $extras_item = $extras[$item['id']] ?? [];
                $descripcion = trim($item['producto_descripcion'] ?? '');

                if ($descripcion) {
                    $nombre .= '<br><span style="font-size:0.75rem; color:#888; font-weight:400;">' . htmlspecialchars($descripcion) . '</span>';
                }
                if ($extras_item) {
                    foreach ($extras_item as $extra) {
                        $nombre .= '<br><span style="font-size:0.8rem; color:#d32f2f;">+ ' . htmlspecialchars($extra['nombre']) . ' (+$' . number_format($extra['precio_extra'], 2) . ')</span>';
                    }
                }
                ?>
                <tr>
                    <td><?= $nombre ?></td>
                    <td style="text-align:center;"><?= $item['cantidad'] ?></td>
                    <td style="text-align:right;">$<?= number_format($item['precio_unitario_historico'], 2) ?></td>
                    <td style="text-align:right;">$<?= number_format($item['precio_unitario_historico'] * $item['cantidad'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- DESGLOSE DE PAGOS -->
    <div class="desglose-pago">
        <div class="desglose-linea total">
            <span>TOTAL DEL PEDIDO</span>
            <span>$<?= number_format($total_pedido, 2) ?></span>
        </div>

        <?php if ($es_efectivo && $restante > 0): ?>
            <div class="desglose-linea" style="color: #28a745;">
                <span>ADELANTO PAGADO (50%)</span>
                <span>-$<?= number_format($monto_pagado, 2) ?></span>
            </div>
            <div class="desglose-linea restante">
                <span>FALTA POR COBRAR EN EFECTIVO</span>
                <span>$<?= number_format($restante, 2) ?></span>
            </div>
            <div style="font-size: 0.8rem; color: #666; margin-top: 0.5rem; text-align: center;">
                * El repartidor debe cobrar $<?= number_format($restante, 2) ?> al entregar.
            </div>
        <?php elseif (!$es_efectivo): ?>
            <div class="desglose-linea" style="color: #28a745;">
                <span>ESTADO DEL PAGO</span>
                <span>PAGADO VIA <?= strtoupper($pedido['metodo_pago_nombre']) ?></span>
            </div>
        <?php endif; ?>

        <div class="desglose-linea" style="font-size: 0.8rem; color: #888; margin-top: 0.5rem;">
            <span>Tasa del día: Bs. <?= number_format($tasa, 2) ?></span>
            <span>Total en Bs: <?= number_format($total_pedido * $tasa, 2) ?></span>
        </div>
    </div>

    <div class="factura-acciones" style="margin-top: 1.5rem;">
        <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Imprimir / Guardar PDF</button>
        <a href="<?= url('index.php') ?>" class="btn-volver-factura">Volver al menu</a>
    </div>

    <div class="factura-footer">
        <p>Gracias por tu compra!</p>
        <p><span class="marca">Minas Burguer</span> - Sabor que alimenta el alma</p>
    </div>
</div>

</body>
</html>