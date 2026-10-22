<?php
// api/actions/server_check/save_check.php
$isLoggedIn = isset($_SESSION['user_id']);
if (!$isLoggedIn) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

$room_id     = (int)($_POST['room_id'] ?? 0);
$check_date  = preg_replace('/[^0-9\-]/', '', $_POST['check_date'] ?? date('Y-m-d'));
$checked_by  = trim(strip_tags($_POST['checked_by'] ?? ''));
$suggestions = trim(strip_tags($_POST['suggestions'] ?? ''));
$values_raw  = $_POST['values'] ?? '{}';
$values      = json_decode($values_raw, true) ?: [];

if (!$room_id) { echo json_encode(['status'=>'error','message'=>'missing room_id']); exit; }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_date)) { echo json_encode(['status'=>'error','message'=>'invalid date']); exit; }
// TISAX/ISO: บันทึกผลตรวจล่วงหน้าไม่ได้ — วันที่ยังไม่ถึงไม่มีการตรวจจริง
if ($check_date > date('Y-m-d')) { echo json_encode(['status'=>'error','message'=>'บันทึกผลตรวจของวันที่ยังไม่ถึงไม่ได้']); exit; }

// ── บันทึกย้อนหลัง (TISAX): วันที่เก่ากว่าเมื่อวาน ต้องมีเหตุผล — เก็บใน audit_log (ไม่ต้องแก้โครงสร้างตาราง) ──
if (!function_exists('scIsBackfill')) {
    function scIsBackfill(string $date): bool { return $date < date('Y-m-d', strtotime('-1 day')); }
    function scLogBackfill(mysqli $conn, int $roomId, string $target, string $reason, array $extra = []): void {
        $uid  = (string)($_SESSION['user_id'] ?? '');
        $name = (string)($_SESSION['display_name'] ?? $_SESSION['username'] ?? '');
        $tid  = 'sc:' . $roomId . ':' . $target;
        $details = json_encode(array_merge(['room_id' => $roomId, 'reason' => $reason], $extra), JSON_UNESCAPED_UNICODE);
        // บันทึกอัตโนมัติทุกครั้งที่กด → ไม่ลงซ้ำถ้าเหตุผลเดิมของวัน/ห้องเดิม
        $chk = $conn->prepare("SELECT details FROM audit_log WHERE action='server_check_backfill' AND target_id=? ORDER BY id DESC LIMIT 1");
        $chk->bind_param('s', $tid);
        $chk->execute();
        $last = json_decode((string)($chk->get_result()->fetch_row()[0] ?? ''), true);
        if (($last['reason'] ?? null) === $reason && empty($extra)) return;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $st = $conn->prepare("INSERT INTO audit_log (user_id, user_name, action, target_id, details, ip_address) VALUES (?, ?, 'server_check_backfill', ?, ?, ?)");
        $st->bind_param('sssss', $uid, $name, $tid, $details, $ip);
        $st->execute();
    }
}
$backfillReason = trim(strip_tags($_POST['backfill_reason'] ?? ''));
if (scIsBackfill($check_date) && $backfillReason === '') {
    echo json_encode(['status'=>'error','code'=>'need_backfill_reason','message'=>'บันทึกย้อนหลังเกิน 1 วัน ต้องระบุเหตุผล']); exit;
}

$saved_id   = trim((string)($_SESSION['user_id'] ?? ''));
$saved_name = trim((string)($_SESSION['display_name'] ?? $_SESSION['username'] ?? ''));

// Snapshot old values first — used after saving to detect newly-introduced issues (avoid re-notifying on every autosave)
$oldVals = [];
$sel0 = $conn->prepare("SELECT v.item_id, v.val FROM sc_records r JOIN sc_values v ON v.record_id = r.id WHERE r.room_id=? AND r.check_date=?");
$sel0->bind_param("is", $room_id, $check_date);
$sel0->execute();
foreach ($sel0->get_result()->fetch_all(MYSQLI_ASSOC) as $row) { $oldVals[(int)$row['item_id']] = $row['val']; }

$stmt = $conn->prepare(
    "INSERT INTO sc_records (room_id,check_date,checked_by,suggestions,saved_by_id,saved_by_name)
     VALUES (?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE
       checked_by=VALUES(checked_by),
       suggestions=VALUES(suggestions),
       saved_by_id=VALUES(saved_by_id),
       saved_by_name=VALUES(saved_by_name),
       updated_at=NOW()"
);
$stmt->bind_param("isssss", $room_id, $check_date, $checked_by, $suggestions, $saved_id, $saved_name);
if (!$stmt->execute()) { echo json_encode(['status'=>'error','message'=>'Save failed: ' . $stmt->error]); exit; }

if (scIsBackfill($check_date)) scLogBackfill($conn, $room_id, $check_date, $backfillReason);

$sel = $conn->prepare("SELECT id FROM sc_records WHERE room_id=? AND check_date=?");
$sel->bind_param("is", $room_id, $check_date);
$sel->execute();
$record_id = (int)($sel->get_result()->fetch_row()[0] ?? 0);
if (!$record_id) { echo json_encode(['status'=>'error','message'=>'Record not found after save']); exit; }

$vstmt = $conn->prepare(
    "INSERT INTO sc_values (record_id,item_id,val) VALUES (?,?,?)
     ON DUPLICATE KEY UPDATE val=VALUES(val)"
);
$newIssueItemIds = [];
foreach ($values as $item_id => $val) {
    $iid = (int)$item_id;
    $v   = (string)$val;
    $vstmt->bind_param("iis", $record_id, $iid, $v);
    $vstmt->execute();
    if ($v === '✖' && ($oldVals[$iid] ?? '') !== '✖') $newIssueItemIds[] = $iid;
}

if (!empty($newIssueItemIds)) {
    notifyServerCheckIssue($conn, $room_id, $check_date, $checked_by, $suggestions, $newIssueItemIds);
}

echo json_encode(['status'=>'success','record_id'=>$record_id]);

function notifyServerCheckIssue($conn, $room_id, $check_date, $checked_by, $suggestions, $itemIds) {
    $room = $conn->query("SELECT code, name, company FROM sc_rooms WHERE id=" . (int)$room_id)->fetch_assoc();
    if (!$room) return;

    $idList = implode(',', array_map('intval', $itemIds));
    $labels = $conn->query("SELECT label FROM sc_items WHERE id IN ($idList)")->fetch_all(MYSQLI_ASSOC);

    $checker = $checked_by ?: '—';
    $now     = date('d/m/Y H:i');
    $msg = "<b>⚠️ Server Check พบปัญหา</b>\n" .
           "➖➖➖➖➖➖➖➖➖➖\n" .
           "<b>🏢 Company:</b> {$room['company']}\n" .
           "<b>🏠 ห้อง:</b> {$room['code']} — {$room['name']}\n" .
           "<b>📅 วันที่:</b> {$check_date}\n" .
           "<b>👤 ผู้ตรวจ:</b> {$checker}\n" .
           "➖➖➖➖➖➖➖➖➖➖\n" .
           "<b>รายการที่มีปัญหา:</b>\n";
    foreach ($labels as $l) { $msg .= $l['label'] . "\n"; }
    if (trim((string)$suggestions) !== '') {
        $msg .= "➖➖➖➖➖➖➖➖➖➖\n<b>📝 หมายเหตุ:</b> {$suggestions}\n";
    }
    $msg .= "\n#ServerCheck";

    sendNotify($conn, $msg, 'server_check_issue', $room['company'] ?: null);
}
