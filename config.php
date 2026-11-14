<?php
// Ejemplo de configuración compatible con XAMPP (local) y Railway (producción)

$host = getenv('MYSQLHOST') ?: (getenv('DB_HOST') ?: 'localhost');
$db   = getenv('MYSQLDATABASE') ?: (getenv('DB_NAME') ?: 'minas_burguer');
$user = getenv('MYSQLUSER') ?: (getenv('DB_USER') ?: 'root');
$pass = getenv('MYSQLPASSWORD') ?: (getenv('DB_PASS') ?: '');
$port = getenv('MYSQLPORT') ?: (getenv('DB_PORT') ?: '3306');

// IMPORTANTE: Agregar siempre el puerto ($port) para forzar conexión TCP/IP y evitar el error de socket
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log("Error de conexion DB: " . $e->getMessage());
    die("Error de conexion con la base de datos.");
}