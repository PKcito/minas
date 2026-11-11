<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!usuarioLogueado()) {
    echo json_encode(['carrito' => []]);
    exit;
}

echo json_encode(['carrito' => $_SESSION['carrito'] ?? []]);