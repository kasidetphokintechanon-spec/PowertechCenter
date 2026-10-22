<?php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$role = $_SESSION['role'] ?? 'guest';
if ($role !== 'admin' && !hasPermission($conn, 'admin.manage_permissions')) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

// ตรวจลิงก์ใช้เวลาหลายวินาที — ปล่อยล็อกเซสชันก่อน ไม่ให้คำขออื่นของผู้ใช้ (เช่น บันทึก) ต้องรอ
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$serviceId = trim((string)($_REQUEST['service_id'] ?? ''));
if ($serviceId === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing service_id']);
    exit;
}

$stmt = $conn->prepare("SELECT id, title, url_primary, url_fallback FROM services WHERE id = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed']);
    exit;
}
$stmt->bind_param("s", $serviceId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Service not found']);
    exit;
}

$normalizeUrl = function (?string $url): string {
    $url = trim((string)$url);
    // Normalize escaped slash forms from stored JSON-like strings.
    $url = str_replace(['\\/', '\\\\'], ['/', '/'], $url);
    if ($url === '') return '';
    return $url;
};

$hasHttpScheme = static function (string $url): bool {
    return (bool)preg_match('#^https?://#i', $url);
};

$probeUrl = function (string $url, int $timeoutSec = 3): array {
    if ($url === '') return ['ok' => false, 'http_code' => 0, 'error' => 'Empty URL'];
    $parts = @parse_url($url);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return ['ok' => false, 'http_code' => 0, 'error' => 'Unsupported scheme'];
    }

    $attempt = function (bool $headOnly) use ($url, $timeoutSec): array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeoutSec);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSec);
        curl_setopt($ch, CURLOPT_NOBODY, $headOnly);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PowertechCenter-HealthCheck/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $body = curl_exec($ch);
        $curlErr = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $ok = ($curlErr === '' && $code >= 200 && $code < 400);
        return [
            'ok' => $ok,
            'http_code' => $code,
            'error' => $curlErr !== '' ? $curlErr : '',
            'has_body' => is_string($body) && strlen($body) > 0
        ];
    };

    $first = $attempt(true);
    if ($first['ok']) return $first;
    $second = $attempt(false);
    if ($second['ok']) return $second;

    if ($first['error'] === '' && $second['error'] !== '') return $second;
    if ($first['http_code'] === 0 && $second['http_code'] > 0) return $second;
    return $first;
};

$primaryUrl = $normalizeUrl((string)($row['url_primary'] ?? ''));
$backupUrl  = $normalizeUrl((string)($row['url_fallback'] ?? ''));

// For flexible path/program inputs without http(s) scheme, treat as configured/ready.
$primary = $primaryUrl === ''
    ? ['ok' => false, 'http_code' => 0, 'error' => 'Empty URL']
    : ($hasHttpScheme($primaryUrl)
        ? $probeUrl($primaryUrl, 3)
        : ['ok' => true, 'http_code' => 0, 'error' => '', 'mode' => 'configured-non-http']);

$backup = $backupUrl === ''
    ? ['ok' => false, 'http_code' => 0, 'error' => 'No backup URL']
    : ($hasHttpScheme($backupUrl)
        ? $probeUrl($backupUrl, 3)
        : ['ok' => true, 'http_code' => 0, 'error' => '', 'mode' => 'configured-non-http']);

$active = 'none';
$selectedUrl = '';
if (!empty($primary['ok'])) {
    $active = 'primary';
    $selectedUrl = $primaryUrl;
} elseif ($backupUrl !== '' && !empty($backup['ok'])) {
    $active = 'backup';
    $selectedUrl = $backupUrl;
}

echo json_encode([
    'status' => 'success',
    'service' => [
        'id' => (string)$row['id'],
        'title' => (string)($row['title'] ?? '')
    ],
    'primary' => array_merge($primary, ['url' => $primaryUrl]),
    'backup' => array_merge($backup, ['url' => $backupUrl]),
    'active_link' => $active,
    'selected_url' => $selectedUrl,
    'checked_at' => date('c')
]);

