<?php
require_once __DIR__ . '/env.php';
loadEnv();

try {
    $dsn = 'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4';
    $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_PERSISTENT => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    error_log('['.date('Y-m-d H:i:s').'] Error DB: ' . $e->getMessage() . PHP_EOL, 3, __DIR__.'/../logs/db_errors.log');
    http_response_code(500);
    die('Error de conexión a la base de datos.');
}
