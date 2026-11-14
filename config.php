<?php
// Iniciar sesión de forma segura si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración de conexión PDO compatible con XAMPP (Local) y Railway (Producción)
$host = getenv('MYSQLHOST') ?: (getenv('DB_HOST') ?: 'localhost');
$db   = getenv('MYSQLDATABASE') ?: (getenv('DB_NAME') ?: 'minas_burguer');
$user = getenv('MYSQLUSER') ?: (getenv('DB_USER') ?: 'root');
$pass = getenv('MYSQLPASSWORD') ?: (getenv('DB_PASS') ?: '');
$port = getenv('MYSQLPORT') ?: (getenv('DB_PORT') ?: '3306');

// El parámetro port= es vital en la nube para forzar TCP/IP y evitar errores de socket
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log("Error de conexion DB: " . $e->getMessage());
    die("<div style='background:#ffebee; color:#c62828; padding:20px; font-family:sans-serif; border-radius:8px; margin:20px; text-align:center;'>
        <h3>❌ Error de conexión con la Base de Datos</h3>
        <p>No se pudo establecer comunicación con el servidor MySQL.</p>
        <p><small>Por favor verifica que el servicio de base de datos esté activo o que las variables de entorno en Railway estén configuradas correctamente.</small></p>
    </div>");
}

/**
 * Función global para generar URLs absolutas y dinámicas del proyecto.
 * Funciona tanto en subcarpetas locales (/minas/) como en dominios raíz en la nube.
 */
function url($path = '') {
    // Detectar protocolo (HTTP o HTTPS)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    
    // Obtener el host actual (ej. localhost o tuapp.up.railway.app)
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Determinar la ruta base del proyecto de forma automática
    // Si estás en local en /minas/..., extrae /minas/
    $script_name = dirname($_SERVER['SCRIPT_NAME']);
    $base_path = '';
    
    // En entornos locales tipo XAMPP con subcarpeta
    if (strpos($script_name, '/minas') !== false) {
        $base_path = '/minas';
    }

    // Limpiar barras iniciales del parámetro path
    $path = ltrim($path, '/');

    return $protocol . $host . $base_path . ($path ? '/' . $path : '');
}

/**
 * Redirección limpia y segura
 */
function redirect($path) {
    header("Location: " . url($path));
    exit;
}

/**
 * Validar si hay un usuario logueado en la sesión
 */
function usuarioLogueado() {
    return isset($_SESSION['usuario_id']);
}

/**
 * Validar si el usuario actual es administrador (rol_id = 1)
 */
function esAdmin() {
    return isset($_SESSION['rol_id']) && (int)$_SESSION['rol_id'] === 1;
}

/**
 * Validar permisos granulares del administrador (si el sistema los utiliza)
 */
function requierePermiso($modulo) {
    if (!esAdmin()) {
        redirect('index.php');
    }
    // Si el admin tiene todos los permisos en sesión o rol maestro, se permite el acceso.
    // Si manejas tabla de permisos por roles, puedes validarlo aquí.
    return true;
}