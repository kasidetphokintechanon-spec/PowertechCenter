<?php
// test_notify_routing.php — ทดสอบ sendNotify() routing จริง (ผ่าน event + company filter)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

$event   = trim($_POST['event']   ?? 'it_repair');
$company = trim($_POST['company'] ?? '') ?: null;

$allowed = ['it_repair','it_cancel','it_approve','stock_new','stock_approve','stock_reject','stock_cancel','stock_low','loan_new','loan_approve','loan_reject'];
if (!in_array($event, $allowed)) throw new Exception("event ไม่ถูกต้อง");

// ตรวจว่า channel ไหนจะรับ event นี้บ้าง (dry-run)
_initNotifyChannelsTable($conn);
$res = $conn->query("SELECT id, name, channel_type, token, dest_id, events, company, enabled FROM notify_channels ORDER BY sort_order ASC, id ASC");
$channels = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$matched  = [];
$skipped  = [];

foreach ($channels as $ch) {
    if (!$ch['enabled']) { $skipped[] = ['name'=>$ch['name'], 'reason'=>'channel ปิดอยู่ (enabled=0)']; continue; }
    if ($ch['company'] && $company && $ch['company'] !== $company) {
        $skipped[] = ['name'=>$ch['name'], 'reason'=>"company ไม่ตรง (channel={$ch['company']}, ticket={$company})"]; continue;
    }
    $events = json_decode($ch['events'], true) ?? ['*'];
    if (!in_array('*', $events) && !in_array($event, $events)) {
        $skipped[] = ['name'=>$ch['name'], 'reason'=>'event ไม่อยู่ใน list (' . implode(', ', $events) . ')']; continue;
    }
    $matched[] = $ch;
}

if (empty($matched)) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'ไม่มี channel ที่รับ event "' . $event . '" — ดูรายละเอียดด้านล่าง',
        'skipped' => $skipped,
        'matched' => [],
    ]);
    exit;
}

// ส่งจริงผ่าน sendNotify
$now = date('d/m/Y H:i');
$testMsg = "<b>🧪 Test Routing — {$event}</b>\n" .
           "➖➖➖➖➖➖➖➖➖➖\n" .
           "<b>📡 Event:</b> {$event}\n" .
           "<b>🏢 Company:</b> " . ($company ?: 'ทุกบริษัท') . "\n" .
           "<b>🕒 Time:</b> {$now}\n" .
           "➖➖➖➖➖➖➖➖➖➖\n" .
           "ถ้าได้รับข้อความนี้ แสดงว่า routing ทำงานถูกต้อง ✅";

sendNotify($conn, $testMsg, $event, $company);

echo json_encode([
    'status'  => 'success',
    'message' => 'ส่งผ่าน routing สำเร็จ — ' . count($matched) . ' channel',
    'matched' => array_map(fn($c) => $c['name'], $matched),
    'skipped' => $skipped,
]);
