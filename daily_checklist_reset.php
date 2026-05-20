<?php
require_once 'config.php';

if (!isset($_GET['key']) || $_GET['key'] !== date('Ymd')) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$db_name = DB_NAME;

$conn = new mysqli($host, $user, $pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    echo "DB Error";
    exit;
}
$conn->set_charset("utf8mb4");

$today = date('Y-m-d');

$conn->query("DELETE FROM daily_checklist_logs WHERE checklist_date < DATE_SUB('$today', INTERVAL 365 DAY)");

echo "OK";
