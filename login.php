<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/config.php';
    $conn = mysqli_init();
    if (!$conn) {
        throw new Exception('Database connection failed: mysqli_init() failed');
    }
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    if (defined('MYSQLI_OPT_READ_TIMEOUT')) $conn->options(MYSQLI_OPT_READ_TIMEOUT, 3);
    if (defined('MYSQLI_OPT_WRITE_TIMEOUT')) $conn->options(MYSQLI_OPT_WRITE_TIMEOUT, 3);
    @$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    require __DIR__ . '/api/actions/auth/login.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
