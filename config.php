<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Caracas');

$es_nube = !empty($_ENV['DB_HOST']) || !empty($_SERVER['DB_HOST']) || getenv('DB_HOST') !== false || !empty($_ENV['MYSQLHOST']) || !empty($_SERVER['MYSQLHOST']) || getenv('MYSQLHOST') !== false;
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

$host   = leer_env_db(['DB_HOST', 'MYSQLHOST', 'RAILWAY_PRIVATE_DOMAIN'], 'localhost');
$port   = leer_env_db(['DB_PORT', 'MYSQLPORT'], '3306');
$dbname = leer_env_db(['DB_DATABASE', 'MYSQLDATABASE', 'MYSQL_DATABASE'], 'railway');
$user   = leer_env_db(['DB_USERNAME', 'MYSQLUSER'], 'root');
$pass   = leer_env_db(['DB_PASSWORD', 'MYSQLPASSWORD', 'MYSQL_ROOT_PASSWORD'], '');

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
        sleep(2);
    }
}

function url($path = '') { return BASE_URL . ltrim($path, '/'); }
function redirect($path) { header('Location: ' . url($path)); exit; }
function asset($path) { return BASE_URL . 'assets/' . ltrim($path, '/'); }
function partial($nombre, $vars = []) { extract($vars); include __DIR__ . '/includes/' . $nombre . '.php'; }
function escaparHtml($texto) { return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8'); }