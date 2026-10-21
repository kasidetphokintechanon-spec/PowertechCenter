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
