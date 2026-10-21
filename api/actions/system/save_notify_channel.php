<?php
// api/actions/system/save_notify_channel.php
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

$id           = intval($_POST['id'] ?? 0);
$name         = trim($_POST['name'] ?? '');
$channel_type = trim($_POST['channel_type'] ?? 'telegram');
$token        = trim($_POST['token'] ?? '');
$dest_id      = trim($_POST['dest_id'] ?? '');
$events_raw   = $_POST['events'] ?? '["*"]';
$company      = trim($_POST['company'] ?? '') ?: null;
$enabled      = intval($_POST['enabled'] ?? 1);
$sort_order   = intval($_POST['sort_order'] ?? 0);

if (!$name)    throw new Exception("กรุณาระบุชื่อ Channel");
if (!$dest_id) throw new Exception("กรุณาระบุ Destination ID / Chat ID");
if (!in_array($channel_type, ['telegram', 'line'])) throw new Exception("ประเภท Channel ไม่ถูกต้อง");

$events = json_decode($events_raw, true);
if (!is_array($events) || empty($events)) $events = ['*'];
$events_json = json_encode(array_values($events));

_initNotifyChannelsTable($conn);

if ($id > 0) {
    // Update — ถ้า token ยาวพอ (ไม่ใช่ preview) ให้ update, ไม่งั้น skip token field
    $isRealToken = strlen($token) > 12 && strpos($token, '...') === false;
    if ($isRealToken) {
        $stmt = $conn->prepare("UPDATE notify_channels SET name=?, channel_type=?, token=?, dest_id=?, events=?, company=?, enabled=?, sort_order=? WHERE id=?");
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param("ssssssiii", $name, $channel_type, $token, $dest_id, $events_json, $company, $enabled, $sort_order, $id);
    } else {
        $stmt = $conn->prepare("UPDATE notify_channels SET name=?, channel_type=?, dest_id=?, events=?, company=?, enabled=?, sort_order=? WHERE id=?");
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param("sssssiii", $name, $channel_type, $dest_id, $events_json, $company, $enabled, $sort_order, $id);
    }
    $stmt->execute();
    $out_id = $id;
} else {
    if (!$token) throw new Exception("กรุณาระบุ Token");
    $stmt = $conn->prepare("INSERT INTO notify_channels (name, channel_type, token, dest_id, events, company, enabled, sort_order) VALUES (?,?,?,?,?,?,?,?)");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param("ssssssii", $name, $channel_type, $token, $dest_id, $events_json, $company, $enabled, $sort_order);
    $stmt->execute();
    $out_id = $conn->insert_id;
}

echo json_encode(['status' => 'success', 'id' => $out_id]);
