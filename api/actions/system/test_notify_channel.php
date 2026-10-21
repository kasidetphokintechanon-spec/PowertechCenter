<?php
// api/actions/system/test_notify_channel.php
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

$channel_type = trim($_POST['channel_type'] ?? 'telegram');
$token        = trim($_POST['token'] ?? '');
$dest_id      = trim($_POST['dest_id'] ?? '');
$ch_id        = intval($_POST['id'] ?? 0); // ถ้ามี id ให้ดึง token จาก DB (กัน preview)

if (!$dest_id) throw new Exception("กรุณาระบุ Destination ID / Chat ID");

// ถ้า token ดูเหมือน preview (มี ...) ให้ดึงจาก DB
if ($ch_id > 0 && (empty($token) || str_contains($token, '...'))) {
    $stmt = $conn->prepare("SELECT token, channel_type FROM notify_channels WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $ch_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $token        = $row['token'];
            $channel_type = $row['channel_type'];
        }
    }
}

if (!$token) throw new Exception("ไม่พบ Token — กรุณากรอก Token ใหม่");

$testText = "✅ Test OK — Powertech System\n" . date('d/m/Y H:i:s') . "\nChannel type: " . strtoupper($channel_type);

if ($channel_type === 'telegram') {
    $url  = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = ['chat_id' => $dest_id, 'text' => $testText, 'parse_mode' => 'HTML'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        $j = json_decode($result, true);
        throw new Exception("Telegram Error: " . ($j['description'] ?? $curlErr ?? "HTTP $httpCode"));
    }

} elseif ($channel_type === 'line') {
    $lineData = [
        'to'       => $dest_id,
        'messages' => [['type' => 'text', 'text' => $testText]]
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.line.me/v2/bot/message/push");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($lineData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        $j = json_decode($result, true);
        throw new Exception("LINE Error: " . ($j['message'] ?? $curlErr ?? "HTTP $httpCode"));
    }
} else {
    throw new Exception("channel_type ไม่ถูกต้อง");
}

echo json_encode(['status' => 'success', 'message' => "ส่งข้อความทดสอบสำเร็จ ({$channel_type})!"]);
