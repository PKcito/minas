<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$producto_id = intval($_GET['producto_id'] ?? 0);

if ($producto_id <= 0) {
    echo json_encode(['extras' => []]);
    exit;
}

// Obtener la categoria del producto
$stmt = $pdo->prepare("SELECT categoria_id FROM productos WHERE id = ? AND activo = 1");
$stmt->execute([$producto_id]);
$categoria_id = $stmt->fetchColumn();

if (!$categoria_id) {
    echo json_encode(['extras' => []]);
    exit;
}

// Extras disponibles: heredados de la categoria + especificos del producto
$stmt = $pdo->prepare("
    SELECT DISTINCT e.id, e.nombre, e.precio_extra
    FROM extras e
    WHERE e.activo = 1
      AND (
        EXISTS (
            SELECT 1 FROM producto_extras pe
            WHERE pe.extra_id = e.id AND pe.producto_id = ?
        )
        OR EXISTS (
            SELECT 1 FROM categoria_extras ce
            WHERE ce.extra_id = e.id AND ce.categoria_id = ?
        )
      )
    ORDER BY e.nombre
");
$stmt->execute([$producto_id, $categoria_id]);

echo json_encode(['extras' => $stmt->fetchAll()]);