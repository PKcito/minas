<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';

header('Content-Type: application/json; charset=utf-8');

if (!usuarioLogueado()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$input = file_get_contents('php://input');
if (strlen($input) > 50000) {
    http_response_code(413);
    echo json_encode(['success' => false, 'message' => 'Carrito demasiado grande']);
    exit;
}

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON invalido']);
    exit;
}

if (!isset($data['carrito']) || !is_array($data['carrito'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos invalidos']);
    exit;
}

if (count($data['carrito']) > 50) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Demasiados productos en el carrito']);
    exit;
}

$carrito_limpio = [];

foreach ($data['carrito'] as $item) {
    // ============================================================
    // VALIDAR CAMPOS BASICOS
    // ============================================================
    if (!is_array($item)) continue;

    $id = v_int($item['id'] ?? 0, 1);
    $cantidad = v_int($item['cantidad'] ?? 0, 1, 100);
    if ($id === null || $cantidad === null) continue;

    $nombre_cliente = isset($item['nombre']) ? v_texto($item['nombre'], 1, 100) : null;
    if ($nombre_cliente === null) continue;

    // ============================================================
    // VALIDAR EXTRAS
    // ============================================================
    $extras = [];
    if (isset($item['extras']) && is_array($item['extras'])) {
        foreach ($item['extras'] as $ex) {
            if (!is_array($ex)) continue;
            $ex_id = v_int($ex['id'] ?? 0, 1);
            $ex_nombre = v_texto($ex['nombre'] ?? '', 1, 50);
            $ex_precio = v_float($ex['precio'] ?? 0, 0, 9999.99, 2);
            if ($ex_id === null || $ex_nombre === null || $ex_precio === null) continue;
            $extras[] = ['id' => $ex_id, 'nombre' => $ex_nombre, 'precio' => $ex_precio];
        }
    }

    // ============================================================
    // VALIDAR PRODUCTO EN BD Y RECALCULAR PRECIO EN SERVIDOR
    // Esto previene manipulacion del precio desde el navegador
    // ============================================================
    $stmt = $pdo->prepare("SELECT id, nombre, precio_unitario, activo FROM productos WHERE id = ?");
    $stmt->execute([$id]);
    $prod = $stmt->fetch();

    if (!$prod || !$prod['activo']) continue;

    $precio_base = floatval($prod['precio_unitario']);

    // Verificar extras contra la BD y recalcular su precio
    $extras_validados = [];
    $suma_extras = 0;
    if (!empty($extras)) {
        $extra_ids = array_column($extras, 'id');
        $ph = implode(',', array_fill(0, count($extra_ids), '?'));
        $stmt = $pdo->prepare("SELECT id, nombre, precio_extra FROM extras WHERE id IN ($ph) AND activo = 1");
        $stmt->execute($extra_ids);
        $extras_bd = [];
        foreach ($stmt->fetchAll() as $e) {
            $extras_bd[intval($e['id'])] = $e;
        }

        foreach ($extras as $ex) {
            if (isset($extras_bd[$ex['id']])) {
                $e_bd = $extras_bd[$ex['id']];
                $extras_validados[] = [
                    'id' => intval($e_bd['id']),
                    'nombre' => $e_bd['nombre'],
                    'precio' => floatval($e_bd['precio_extra'])
                ];
                $suma_extras += floatval($e_bd['precio_extra']);
            }
        }
    }

    // ============================================================
    // PRECIO FINAL CALCULADO EN EL SERVIDOR
    // El precio del cliente se ignora completamente
    // ============================================================
    $precio_final = round($precio_base + $suma_extras, 2);

    $carrito_limpio[] = [
        'id' => intval($prod['id']),
        'nombre' => $prod['nombre'],
        'precio' => $precio_final,
        'precio_base' => $precio_base,
        'extras' => $extras_validados,
        'cantidad' => $cantidad
    ];
}

$_SESSION['carrito'] = $carrito_limpio;

echo json_encode(['success' => true, 'total_items' => count($carrito_limpio)]);