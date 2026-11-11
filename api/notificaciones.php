<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/seguridad.php';

aplicar_headers_seguridad(true);

if (!usuarioLogueado()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$usuario_id = intval($_SESSION['usuario_id']);
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'contar':
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0");
        $stmt->execute([$usuario_id]);
        echo json_encode(['count' => intval($stmt->fetchColumn())]);
        exit;

    case 'listar_dropdown':
        $stmt = $pdo->prepare("SELECT id, titulo, mensaje, fecha_creacion FROM notificaciones WHERE usuario_id = ? AND leida = 0 ORDER BY fecha_creacion DESC LIMIT 5");
        $stmt->execute([$usuario_id]);
        echo json_encode(['notificaciones' => $stmt->fetchAll()]);
        exit;

    case 'verificar_auto':
        $stmt = $pdo->prepare("SELECT id, titulo, mensaje FROM notificaciones WHERE usuario_id = ? AND leida = 0 ORDER BY fecha_creacion DESC LIMIT 1");
        $stmt->execute([$usuario_id]);
        $notif = $stmt->fetch();
        echo json_encode(['notificacion' => $notif ?: null]);
        exit;

    case 'marcar_leidas':
        $stmt = $pdo->prepare("UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0");
        $stmt->execute([$usuario_id]);
        echo json_encode(['success' => true]);
        exit;

    case 'listar_todas':
        $stmt = $pdo->prepare("SELECT id, titulo, mensaje, fecha_creacion, leida FROM notificaciones WHERE usuario_id = ? ORDER BY fecha_creacion DESC");
        $stmt->execute([$usuario_id]);
        echo json_encode(['notificaciones' => $stmt->fetchAll()]);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Accion no valida']);
        exit;
}