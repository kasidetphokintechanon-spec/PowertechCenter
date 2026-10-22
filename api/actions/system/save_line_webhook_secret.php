<?php
// api/actions/system/save_line_webhook_secret.php — Channel secret ของบอท LINE
// ใช้ตรวจว่าคำสั่งที่เข้ามาทาง webhook (ปุ่มอนุมัติในแชต) มาจาก LINE จริง · สิทธิ์ settings.notifications
$secret = trim((string)($_POST['secret'] ?? ''));
if ($secret !== '' && !preg_match('/^[a-f0-9]{32}$/i', $secret)) {
    echo json_encode(['status' => 'error', 'message' => 'Channel secret ต้องเป็นตัวอักษร a-f / 0-9 จำนวน 32 ตัว']);
    exit;
}
$st = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('line_channel_secret', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
$st->bind_param('s', $secret);
$st->execute();
// TISAX: เปลี่ยนค่าความปลอดภัยต้องมีร่องรอย (ไม่จดค่า secret เอง)
$uid = (string)($_SESSION['user_id'] ?? ''); $uname = (string)($_SESSION['display_name'] ?? $uid); $ip = $_SERVER['REMOTE_ADDR'] ?? '';
$note = $secret === '' ? 'ล้าง Channel secret' : 'ตั้ง Channel secret ใหม่';
$au = $conn->prepare("INSERT INTO audit_log (user_id, user_name, action, target_id, details, ip_address) VALUES (?, ?, 'line_webhook_secret_update', 'system_settings', ?, ?)");
if ($au) { $au->bind_param('ssss', $uid, $uname, $note, $ip); $au->execute(); }
echo json_encode(['status' => 'success']);
