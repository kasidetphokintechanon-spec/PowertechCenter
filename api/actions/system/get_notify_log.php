<?php
// get_notify_log.php — อ่าน [Notify] log จาก PHP error log (admin only)
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

$limit = min(100, intval($_GET['limit'] ?? 50));

// หา PHP error log path
$logPath = ini_get('error_log');
if (!$logPath || !file_exists($logPath)) {
    // fallback: XAMPP default paths
    $candidates = [
        'C:/xampp/php/logs/php_error_log',
        'C:/xampp/apache/logs/error.log',
        __DIR__ . '/../../../../logs/php_error.log',
    ];
    foreach ($candidates as $c) {
        if (file_exists($c)) { $logPath = $c; break; }
    }
}

if (!$logPath || !file_exists($logPath)) {
    echo json_encode(['status' => 'ok', 'log_path' => null, 'lines' => [], 'message' => 'ไม่พบ PHP error log']);
    exit;
}

// อ่าน tail ของ log file (ไม่โหลดทั้งไฟล์)
$lines = [];
$fp = fopen($logPath, 'r');
if ($fp) {
    fseek($fp, 0, SEEK_END);
    $fileSize = ftell($fp);
    $readSize = min($fileSize, 102400); // อ่านสูงสุด 100KB สุดท้าย
    fseek($fp, -$readSize, SEEK_END);
    $raw = fread($fp, $readSize);
    fclose($fp);

    $allLines = explode("\n", $raw);
    // กรองเฉพาะ [Notify] lines
    $notifyLines = array_filter($allLines, fn($l) => strpos($l, '[Notify]') !== false || strpos($l, '[StockCron]') !== false);
    $notifyLines = array_values(array_reverse(array_slice(array_values($notifyLines), -$limit)));
    $lines = $notifyLines;
}

echo json_encode([
    'status'   => 'ok',
    'log_path' => $logPath,
    'lines'    => $lines,
]);
