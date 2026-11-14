<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Sincronizar hora de PHP con la de Venezuela
date_default_timezone_set('America/Caracas');

$es_nube = !empty($_ENV['MYSQLHOST']) || !empty($_SERVER['MYSQLHOST']) || getenv('MYSQLHOST') !== false;
define('BASE_URL', $es_nube ? '/' : '/minas/');

function leer_env_db($claves, $defecto = '') {
    foreach ((array)$claves as $k) {
        $v = $_ENV[$k] ?? ($_SERVER[$k] ?? getenv($k));
        if ($v !== false && $v !== null) {
            $v = trim((string)$v);
            if ($v !== '' && stripos($v, 'Pega el valor') === false && strpos($v, '${{') === false) {
                return $v;
            }
        }
    }
    return $defecto;
}

$host   = leer_env_db(['MYSQLHOST', 'RAILWAY_PRIVATE_DOMAIN', 'DB_HOST'], 'localhost');
$port   = leer_env_db(['MYSQLPORT', 'DB_PORT'], '3306');
$dbname = leer_env_db(['MYSQLDATABASE', 'MYSQL_DATABASE', 'DB_DATABASE', 'DB_NAME'], 'railway');
$user   = leer_env_db(['MYSQLUSER', 'DB_USERNAME', 'DB_USER'], 'root');
$pass   = leer_env_db(['MYSQLPASSWORD', 'MYSQL_ROOT_PASSWORD', 'DB_PASSWORD', 'DB_PASS'], '');

$url_env = leer_env_db(['MYSQL_URL', 'DATABASE_URL'], '');
if ($host === 'localhost' && !empty($url_env)) {
    $partes_url = parse_url($url_env);
    if (!empty($partes_url['host'])) {
        $host   = $partes_url['host'];
        $port   = $partes_url['port'] ?? '3306';
        $user   = isset($partes_url['user']) ? rawurldecode($partes_url['user']) : $user;
        $pass   = isset($partes_url['pass']) ? rawurldecode($partes_url['pass']) : $pass;
        $dbname = isset($partes_url['path']) ? ltrim($partes_url['path'], '/') : $dbname;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$timeout_duracion = 1800;
if (isset($_SESSION['usuario_id'])) {
    if (isset($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad'] > $timeout_duracion)) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['session_timeout_msg'] = 'Tu sesion expiro por inactividad. Vuelve a iniciar sesion.';
        $es_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($es_ajax) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'session_timeout' => true]);
            exit;
        }
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
    $_SESSION['ultima_actividad'] = time();
}

// Bloque de conexión con reintentos inteligentes para Railway
$intentos = 5;
$pdo = null;
while ($intentos > 0) {
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->exec("SET time_zone = '-04:00'");
        break;
    } catch (PDOException $e) {
        $intentos--;
        if ($intentos === 0) {
            die("Error de conexion tras varios intentos: " . $e->getMessage());
        }
        sleep(2); // Espera 2 segundos antes de reintentar conectar
    }
}

function url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

function redirect($path) {
    header('Location: ' . url($path));
    exit;
}

function redirect_prg($path) {
    $destino = url($path);
    $origen = $_SERVER['REQUEST_URI'] ?? url($path);
    header('Location: ' . $destino);

    $origen_js = json_encode($origen, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    $destino_js = json_encode($destino, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirigiendo...</title>';
    echo '<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;background:#f5f5f5;}.spinner{text-align:center;color:#666;}</style>';
    echo '<script>history.replaceState(null, "", ' . $origen_js . ');window.location.replace(' . $destino_js . ');</script>';
    echo '</head><body><div class="spinner"><p>Redirigiendo...</p></div></body></html>';
    exit;
}

function asset($path) {
    return BASE_URL . 'assets/' . ltrim($path, '/');
}

function partial($nombre, $vars = []) {
    extract($vars);
    include __DIR__ . '/includes/' . $nombre . '.php';
}

function usuarioLogueado() {
    return isset($_SESSION['usuario_id']);
}

function esAdmin() {
    return isset($_SESSION['rol_id']) && $_SESSION['rol_id'] == 1;
}

function esSuperAdmin() {
    return isset($_SESSION['es_super_admin']) && $_SESSION['es_super_admin'] == 1;
}

function tienePermiso($modulo) {
    if (!esAdmin()) return false;
    if (esSuperAdmin()) return true;
    $permisos = $_SESSION['permisos'] ?? [];
    if (is_string($permisos)) {
        $permisos = json_decode($permisos, true) ?: [];
    }
    return in_array('all', $permisos) || in_array($modulo, $permisos);
}

function requierePermiso($modulo) {
    if (!tienePermiso($modulo)) {
        $_SESSION['flash_error'] = "No tienes permiso para acceder a este módulo.";
        redirect('admin/dashboard.php');
    }
}

function obtenerNombreUsuario() {
    if (isset($_SESSION['nombres']) && isset($_SESSION['apellidos'])) {
        return trim($_SESSION['nombres'] . ' ' . $_SESSION['apellidos']);
    }
    return $_SESSION['nombres'] ?? 'Usuario';
}

function obtenerCarrito() {
    return $_SESSION['carrito'] ?? [];
}

function vaciarCarrito() {
    unset($_SESSION['carrito']);
}

function escaparHtml($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

function crearNotificacion($pdo, $usuario_id, $pedido_id, $titulo, $mensaje) {
    $stmt = $pdo->prepare("INSERT INTO notificaciones (usuario_id, pedido_id, titulo, mensaje) VALUES (?, ?, ?, ?)");
    $stmt->execute([$usuario_id, $pedido_id, $titulo, $mensaje]);
}

function tiempoRestanteCancelacion($fecha_pedido, $minutos_limite = 20) {
    $fecha_creacion = strtotime($fecha_pedido);
    $fecha_limite = $fecha_creacion + ($minutos_limite * 60);
    $ahora = time();
    return max(0, $fecha_limite - $ahora);
}

// Auto-cancelar pedidos pendientes (SOLO si estado_pago_id = 1 pendiente) y notificar al cliente
try {
    $stmtVencidos = $pdo->query("SELECT id, usuario_id FROM pedidos WHERE estado_id = 1 AND estado_pago_id = 1 AND fecha_pedido < NOW() - INTERVAL 20 MINUTE");
    $pedidos_vencidos = $stmtVencidos->fetchAll();

    if (!empty($pedidos_vencidos)) {
        $stmtUpd = $pdo->prepare("UPDATE pedidos SET estado_id = 6, razon_cancelacion = 'Cancelado automáticamente por falta de confirmación (tiempo límite excedido)' WHERE id = ?");
        foreach ($pedidos_vencidos as $pv) {
            $stmtUpd->execute([$pv['id']]);
            crearNotificacion(
                $pdo,
                $pv['usuario_id'],
                $pv['id'],
                'Pedido #' . $pv['id'] . ' Cancelado',
                'Tu pedido #' . $pv['id'] . ' fue cancelado automáticamente por superar el tiempo límite de confirmación.'
            );
        }
    }
} catch (Exception $e) {}