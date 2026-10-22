<?php
// api/actions/system/get_notify_overview.php — ข้อมูลหน้า "ศูนย์แจ้งเตือน": กลุ่ม + สุขภาพการส่ง + ประวัติล่าสุด
// สิทธิ์: settings.notifications (ตั้งใน $ACTION_PERMS ของ api.php) · กุญแจโชว์แค่ 6 ตัวท้าย
_initNotifyChannelsTable($conn);
_initNotifyLogTable($conn);

$channels = $conn->query("SELECT id, name, channel_type, RIGHT(token, 6) AS token_tail, dest_id, events, company, enabled, sort_order
    FROM notify_channels ORDER BY sort_order ASC, id ASC")->fetch_all(MYSQLI_ASSOC);

$stat = [];
$r = $conn->query("SELECT channel_id,
        SUM(ok = 1 AND created_at >= CURDATE()) AS today,
        SUM(ok = 1 AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)) AS week
    FROM notify_log WHERE channel_id IS NOT NULL AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY channel_id");
foreach ($r->fetch_all(MYSQLI_ASSOC) as $row) $stat[(int)$row['channel_id']] = $row;

foreach ($channels as &$c) {
    $c['events'] = json_decode($c['events'], true) ?: ['*'];
    $id = (int)$c['id'];
    $c['today'] = (int)($stat[$id]['today'] ?? 0);
    $c['week'] = (int)($stat[$id]['week'] ?? 0);
    $st = $conn->prepare("SELECT ok, created_at, detail FROM notify_log WHERE channel_id = ? ORDER BY id DESC LIMIT 1");
    $st->bind_param('i', $id);
    $st->execute();
    $c['last'] = $st->get_result()->fetch_assoc();
}
unset($c);

$log = $conn->query("SELECT created_at, event, title, channel_name, ok, detail FROM notify_log ORDER BY id DESC LIMIT 40")->fetch_all(MYSQLI_ASSOC);

$sec = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'line_channel_secret'")->fetch_row();

echo json_encode([
    'status' => 'success',
    'channels' => $channels,
    'log' => $log,
    'webhook_secret_set' => !empty($sec[0]),
], JSON_UNESCAPED_UNICODE);
