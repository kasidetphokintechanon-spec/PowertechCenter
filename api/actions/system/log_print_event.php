<?php
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$document_type = $_POST['document_type'] ?? '';
$document_id = $_POST['document_id'] ?? '';
$action = $_POST['event_type'] ?? '';
$client_meta = $_POST['client_meta'] ?? '';

if ($document_type === '' || $document_id === '' || $action === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

$user_id = (string)$_SESSION['user_id'];
$user_name = (string)($_SESSION['display_name'] ?? $_SESSION['username'] ?? '');
$role = (string)($_SESSION['role'] ?? '');

$restrictedTypes = ['it_logs', 'it_repair_ticket'];
if ($role === 'user' && in_array($document_type, $restrictedTypes, true)) {
    $stmt_owner = $conn->prepare("SELECT requester_id FROM it_logs WHERE id = ? LIMIT 1");
    if (!$stmt_owner) {
        echo json_encode(['status' => 'error', 'message' => 'Prepare failed']);
        exit;
    }
    $stmt_owner->bind_param("s", $document_id);
    $stmt_owner->execute();
    $owner_res = $stmt_owner->get_result();
    $row_owner = $owner_res ? $owner_res->fetch_assoc() : null;
    $owner_id = (string)($row_owner['requester_id'] ?? '');
    if ($owner_id === '' || $owner_id !== $user_id) {
        echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
        exit;
    }
}

$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
if (strpos($client_ip, ',') !== false) {
    $client_ip = trim(explode(',', $client_ip)[0]);
}
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$encKeyB64 = getenv('PRINT_LOG_AES_KEY') ?: '';
if ($encKeyB64 !== '') {
    $key = base64_decode($encKeyB64, true);
    if ($key === false || strlen($key) !== 32) {
        $key = hash('sha256', $encKeyB64, true);
    }
    $enc = function ($plaintext) use ($key) {
        if ($plaintext === '') return '';
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false || $tag === '') return $plaintext;
        return 'enc:v1:' . base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($ciphertext);
    };
    $user_agent = $enc($user_agent);
    $client_meta = $enc($client_meta);
}

$prev_hash = str_repeat('0', 64);
$res = $conn->query("SELECT entry_hash FROM print_logs ORDER BY id DESC LIMIT 1");
if ($res && ($row = $res->fetch_assoc()) && !empty($row['entry_hash'])) {
    $prev_hash = $row['entry_hash'];
}

$payload = [
    'ts' => gmdate('c'),
    'user_id' => $user_id,
    'user_name' => $user_name,
    'role' => $role,
    'document_type' => $document_type,
    'document_id' => $document_id,
    'action' => $action,
    'client_ip' => $client_ip,
    'user_agent' => $user_agent,
    'client_meta' => $client_meta
];
$payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($payload_json === false) $payload_json = '{}';

$entry_hash = hash('sha256', $prev_hash . '|' . $payload_json);

$secret = getenv('PRINT_LOG_SECRET') ?: '';
if ($secret === '') {
    $secret = getenv('APP_KEY') ?: '';
}
if ($secret === '') {
    $secret = session_id();
}
$signature = hash_hmac('sha256', $entry_hash, $secret);

$stmt = $conn->prepare("INSERT INTO print_logs (user_id, user_name, role, document_type, document_id, action, client_ip, user_agent, client_meta, prev_hash, entry_hash, signature) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed']);
    exit;
}
$stmt->bind_param(
    "ssssssssssss",
    $user_id,
    $user_name,
    $role,
    $document_type,
    $document_id,
    $action,
    $client_ip,
    $user_agent,
    $client_meta,
    $prev_hash,
    $entry_hash,
    $signature
);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
}
?>
