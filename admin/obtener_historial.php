<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';
require_once __DIR__ . '/../includes/seguridad.php';

if (!esAdmin()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
requierePermiso('pedidos'); 

aplicar_headers_seguridad(true);

$pedido_id = v_int($_GET['pedido_id'] ?? 0, 1);

if ($pedido_id === null) {
    echo json_encode(['historial' => []]);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM pedidos WHERE id = ?");
$stmt->execute([$pedido_id]);
if (!$stmt->fetchColumn()) {
    echo json_encode(['historial' => []]);
    exit;
}

$colores_estados = [
    1 => '#ffc107', 2 => '#17a2b8', 3 => '#007bff', 4 => '#28a745',
    5 => '#6c757d', 6 => '#dc3545', 7 => '#9c27b0'
];

$stmt = $pdo->prepare("
    SELECT h.id, h.fecha, h.notas,
           ea.nombre AS estado_anterior_nombre, ea.id AS estado_anterior_id,
           en.nombre AS estado_nuevo_nombre, en.id AS estado_nuevo_id,
           u.email,
           c.nombres, c.apellidos
    FROM historial_estados_pedido h
    LEFT JOIN estados_pedido ea ON h.estado_anterior_id = ea.id
    JOIN estados_pedido en ON h.estado_nuevo_id = en.id
    JOIN usuarios u ON h.usuario_id = u.id
    LEFT JOIN clientes c ON u.id = c.usuario_id
    WHERE h.pedido_id = ?
    ORDER BY h.fecha DESC
");
$stmt->execute([$pedido_id]);
$raw = $stmt->fetchAll();

$historial = [];
foreach ($raw as $h) {
    $nombre = trim(($h['nombres'] ?? '') . ' ' . ($h['apellidos'] ?? ''));
    if (empty($nombre)) $nombre = $h['email'];

    $historial[] = [
        'id'                     => intval($h['id']),
        'fecha_formateada'       => date('d/m/Y H:i', strtotime($h['fecha'])),
        'usuario_nombre'         => $nombre,
        'estado_anterior_nombre' => $h['estado_anterior_nombre'] ? ucfirst($h['estado_anterior_nombre']) : null,
        'estado_anterior_id'     => $h['estado_anterior_id'] ? intval($h['estado_anterior_id']) : null,
        'color_anterior'         => $h['estado_anterior_id'] ? ($colores_estados[$h['estado_anterior_id']] ?? null) : null,
        'estado_nuevo_nombre'    => ucfirst($h['estado_nuevo_nombre']),
        'estado_nuevo_id'        => intval($h['estado_nuevo_id']),
        'color_nuevo'            => $colores_estados[$h['estado_nuevo_id']] ?? '#6c757d',
        'notas'                  => $h['notas']
    ];
}

echo json_encode(['historial' => $historial]);