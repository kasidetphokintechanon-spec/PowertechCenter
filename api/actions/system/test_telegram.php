<?php
// api/actions/system/test_telegram.php

// Check Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

$token = $_POST['token'] ?? '';
$chat_id = $_POST['chat_id'] ?? '';

if (empty($token) || empty($chat_id)) {
    throw new Exception("กรุณาระบุ Token และ Chat ID");
}

$url = "https://api.telegram.org/bot" . $token . "/sendMessage";
$data = [
    'chat_id' => $chat_id,
    'text' => "✅ Connection Successful!\nการตั้งค่า Telegram ของคุณถูกต้อง\n" . date('d/m/Y H:i:s'),
    'parse_mode' => 'HTML'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200) {
    $resJson = json_decode($result, true);
    $msg = $resJson['description'] ?? $error ?? "HTTP Status $httpCode";
    throw new Exception("Telegram API Error: " . $msg);
}

echo json_encode(['status' => 'success', 'message' => 'ส่งข้อความทดสอบสำเร็จ']);
?>
