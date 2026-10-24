<?php
// api/actions/room/room_update.php — แก้การจอง (เวลา/เรื่อง/คน/ของที่ขอ) · เจ้าของ หรือผู้ดูแลห้องนั้น
// เปลี่ยนเวลา/ของที่ขอหลังผู้ดูแลเตรียมแล้ว → ล้างสถานะ "เตรียมแล้ว" ให้เตรียมใหม่
_initRoomTables($conn);
$b = roomGet($conn, (int)($_POST['id'] ?? 0));
if (!$b || !in_array($b['status'], ['booked', 'pending'], true)) throw new Exception('ไม่พบการจอง หรือยกเลิกไปแล้ว');
$mine = (string)$b['booker_id'] === (string)$_SESSION['user_id'];
$keeper = roomKeeps($conn, $b['room_id']);
if (!$mine && !$keeper) throw new Exception('แก้ได้เฉพาะการจองของตัวเอง');
if (!$keeper && strtotime($b['end_at']) < time()) throw new Exception('การจองนี้ผ่านไปแล้ว');
$title = mb_substr(trim(strip_tags((string)($_POST['title'] ?? $b['title']))), 0, 255);
if (mb_strlen($title) < 2) throw new Exception('ใส่หัวข้อการประชุม');
[$start, $end] = roomCheckTime((string)($_POST['start'] ?? $b['start_at']), (string)($_POST['end'] ?? $b['end_at']), $keeper);
roomCheckRules($conn, ['company' => $b['room_company']], $start, $end, $keeper);
$att = max(0, min(999, (int)($_POST['attendees'] ?? $b['attendees'])));
$extras = isset($_POST['extras']) ? roomExtrasClean($_POST['extras'], roomExtrasFor($conn, $b['room_company'])) : array_filter(explode(',', (string)$b['extras']));
$note = mb_substr(trim(strip_tags((string)($_POST['extras_note'] ?? $b['extras_note']))), 0, 500);
$ex = implode(',', $extras);
[$host, $parts, $guest, $gco, $gnote] = roomDetailFields($_POST, $b);
$changed = $start !== $b['start_at'] || $end !== $b['end_at'] || $ex !== (string)$b['extras'];
$conn->begin_transaction();
try {
    $lk = $conn->prepare("SELECT id FROM rm_rooms WHERE id = ? FOR UPDATE"); $lk->bind_param('i', $b['room_id']); $lk->execute(); $lk->get_result();
    if ($c = roomClash($conn, (int)$b['room_id'], $start, $end, (int)$b['id'])) throw new Exception("เวลานี้ห้องไม่ว่าง — {$c['booker_name']} " . date('H:i', strtotime($c['start_at'])) . '–' . date('H:i', strtotime($c['end_at'])));
    $u = $conn->prepare("UPDATE rm_bookings SET title = ?, start_at = ?, end_at = ?, attendees = ?, extras = ?, extras_note = ?, host = ?, participants = ?, has_guest = ?, guest_company = ?, note = ?" . ($changed ? ', prepared_at = NULL, prepared_by = NULL' : '') . " WHERE id = ?");
    $u->bind_param('sssissssissi', $title, $start, $end, $att, $ex, $note, $host, $parts, $guest, $gco, $gnote, $b['id']); $u->execute();
    $conn->commit();
} catch (Throwable $e) { $conn->rollback(); throw $e; }
if ($changed && $ex !== '') roomNotify($conn, 'room_book', '✏️ แก้การจองห้อง — เตรียมของใหม่', roomGet($conn, (int)$b['id']), ['เดิม' => date('d/m H:i', strtotime($b['start_at'])) . '–' . date('H:i', strtotime($b['end_at']))]);
echo json_encode(['status' => 'success']);
