<?php
// api/actions/ticket/ticket_print_log.php
// นับการพิมพ์ใบงานซ่อม (TISAX: ตรวจย้อนได้ว่าใครพิมพ์ เมื่อไหร่ · ใบที่ 2 ขึ้นไปขึ้น "สำเนา")
// ไม่ล็อกการพิมพ์ — กระดาษหายพิมพ์ใหม่ได้ แต่ทุกใบมีร่องรอยใน audit_log
//   GET  ?ticket_id=&doc=   → { count }        (ดูว่าพิมพ์ไปแล้วกี่ครั้ง ใช้ทำหัวกระดาษก่อนพิมพ์)
//   POST ticket_id, doc     → { copy }         (บันทึกการพิมพ์ครั้งนี้)
if (!isset($_SESSION['user_id'])) { echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit; }

$ticketId = trim((string)($_REQUEST['ticket_id'] ?? ''));
$doc = (string)($_REQUEST['doc'] ?? '');
// นับแยกตามชนิดใบ — ใบงานระหว่างซ่อมกับใบปิดงานเป็นคนละเอกสาร
if ($ticketId === '' || !in_array($doc, ['request', 'workorder', 'completion', 'cancelled'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบ']); exit;
}

$st = $conn->prepare("SELECT requester_id FROM it_logs WHERE id = ? LIMIT 1");
$st->bind_param('s', $ticketId);
$st->execute();
$row = $st->get_result()->fetch_assoc();
if (!$row) { echo json_encode(['status' => 'error', 'message' => 'ไม่พบงาน']); exit; }

// เจ้าของงาน หรือคนที่เข้าหน้าช่าง/ทะเบียนพัสดุได้
$uid = (string)$_SESSION['user_id'];
$isOwner = (string)($row['requester_id'] ?? '') === $uid;
if (!$isOwner && !hasPermission($conn, 'it_log.access') && !hasPermission($conn, 'asset.manage')) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์']); exit;
}

$target = $ticketId . ':' . $doc;
$cnt = $conn->prepare("SELECT COUNT(*) FROM audit_log WHERE action = 'ticket_print' AND target_id = ?");
$cnt->bind_param('s', $target);
$cnt->execute();
$count = (int)$cnt->get_result()->fetch_row()[0];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'success', 'count' => $count]); exit;
}

$copy = $count + 1;
$name = (string)($_SESSION['display_name'] ?? $_SESSION['username'] ?? $uid);
$details = json_encode(['ticket_id' => $ticketId, 'doc' => $doc, 'copy' => $copy], JSON_UNESCAPED_UNICODE);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ins = $conn->prepare("INSERT INTO audit_log (user_id, user_name, action, target_id, details, ip_address) VALUES (?, ?, 'ticket_print', ?, ?, ?)");
$ins->bind_param('sssss', $uid, $name, $target, $details, $ip);
$ins->execute();

echo json_encode(['status' => 'success', 'copy' => $copy]);
