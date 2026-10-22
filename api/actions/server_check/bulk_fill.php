<?php
// api/actions/server_check/bulk_fill.php
// POST: room_id, month, year, mode (all|missing|copy_from), copy_from (date string)
if (!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

$room_id   = (int)($_POST['room_id'] ?? 0);
$month     = (int)($_POST['month']   ?? date('n'));
$year      = (int)($_POST['year']    ?? date('Y'));
$mode      = in_array($_POST['mode'] ?? '', ['all','missing','copy_from']) ? $_POST['mode'] : 'all';
$copy_from = preg_replace('/[^0-9\-]/', '', $_POST['copy_from'] ?? '');

if (!$room_id)              { echo json_encode(['status'=>'error','message'=>'missing room_id']); exit; }
if ($month<1||$month>12)    { echo json_encode(['status'=>'error','message'=>'invalid month']);   exit; }
if ($year<2000||$year>2100) { echo json_encode(['status'=>'error','message'=>'invalid year']);    exit; }

$saved_id   = trim((string)($_SESSION['user_id']       ?? ''));
$saved_name = trim((string)($_SESSION['display_name']  ?? $_SESSION['username'] ?? ''));

$from = sprintf('%04d-%02d-01', $year, $month);
$to   = date('Y-m-t', strtotime($from));
$days = (int)date('t', strtotime($from));

// Load holidays for this month
$phRows   = $conn->query("SELECT holiday_date FROM company_holidays WHERE holiday_date BETWEEN '$from' AND '$to'")->fetch_all(MYSQLI_ASSOC);
$holidays = array_column($phRows, 'holiday_date');

// Items for this room
$items = $conn->query("SELECT id, input_type, threshold FROM sc_items WHERE room_id=$room_id AND is_active=1 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
if (!$items) { echo json_encode(['status'=>'error','message'=>'no items found']); exit; }

// Dates that already have records
$existingRows  = $conn->query("SELECT check_date FROM sc_records WHERE room_id=$room_id AND check_date BETWEEN '$from' AND '$to'")->fetch_all(MYSQLI_ASSOC);
$existingDates = array_column($existingRows, 'check_date');

// Copy-from values
$copyValues = [];
if ($mode === 'copy_from' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $copy_from)) {
    $rec = $conn->query("SELECT id FROM sc_records WHERE room_id=$room_id AND check_date='$copy_from'")->fetch_assoc();
    if ($rec) {
        $rid  = (int)$rec['id'];
        $vals = $conn->query("SELECT item_id, val FROM sc_values WHERE record_id=$rid")->fetch_all(MYSQLI_ASSOC);
        foreach ($vals as $v) $copyValues[(int)$v['item_id']] = $v['val'];
    }
}

function bulkDefaultVal($item, $copyValues, $mode) {
    if (in_array($item['input_type'], ['percent', 'days'], true)) return null; // ตัวเลขไม่คัดลอก/ไม่เติม — ต้องดูจริง
    if ($mode === 'copy_from' && isset($copyValues[(int)$item['id']])) {
        return $copyValues[(int)$item['id']];
    }
    switch ($item['input_type']) {
        case 'status':  return '###';
        case 'backup':  return 'O';
        // ตัวเลข (กล้องออนไลน์ / วันเก็บภาพ) ต้องมาจากการดูจริง — ไม่เติมให้ (TISAX) ช่างกรอกเอง
        case 'percent':
        case 'days':    return null;
    }
    return '###';
}

$checked_by = $saved_name ?: ($saved_id ?: 'Bulk Fill');
// Strip honorific prefix (Mr./Mrs./Dr./นาย/นาง/นางสาว)
$chk_parts = preg_split('/\s+/u', trim($checked_by));
if (count($chk_parts) > 1 && substr($chk_parts[0], -1) === '.') {
    $checked_by = implode(' ', array_slice($chk_parts, 1));
}

$rstmt = $conn->prepare(
    "INSERT INTO sc_records (room_id,check_date,checked_by,saved_by_id,saved_by_name)
     VALUES (?,?,?,?,?)
     ON DUPLICATE KEY UPDATE checked_by=VALUES(checked_by), saved_by_id=VALUES(saved_by_id), saved_by_name=VALUES(saved_by_name), updated_at=NOW()"
);
$vstmt = $conn->prepare(
    "INSERT INTO sc_values (record_id,item_id,val) VALUES (?,?,?)
     ON DUPLICATE KEY UPDATE val=VALUES(val)"
);

$filled  = 0;
$cleaned = 0;

// TISAX/ISO: เติมได้เฉพาะวันที่ผ่านมาแล้วและยังว่าง — ห้ามสร้างผลตรวจล่วงหน้า ห้ามทับ/ลบของที่บันทึกจริง
$today = date('Y-m-d');
if ($mode === 'all') $mode = 'missing';

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
// ดูก่อนว่าจะเติมวันเก่ากว่าเมื่อวานไหม — ถ้าใช่ต้องมีเหตุผล
$needReason = false;
for ($d = 1; $d <= $days; $d++) {
    $ds = sprintf('%04d-%02d-%02d', $year, $month, $d);
    if ($ds > $today) break;
    $w = (int)date('w', strtotime($ds));
    if ($w === 0 || $w === 6 || in_array($ds, $holidays) || in_array($ds, $existingDates)) continue;
    if (scIsBackfill($ds)) { $needReason = true; break; }
}
if ($needReason && $backfillReason === '') {
    echo json_encode(['status'=>'error','code'=>'need_backfill_reason','message'=>'มีวันที่ต้องบันทึกย้อนหลัง ต้องระบุเหตุผล']); exit;
}
$filledDates = [];

for ($d = 1; $d <= $days; $d++) {
    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
    if ($dateStr > $today) break;
    $dow     = (int)date('w', strtotime($dateStr)); // 0=Sun, 6=Sat
    $isWeekend = ($dow === 0 || $dow === 6);

    $isHoliday = in_array($dateStr, $holidays);

    // วันหยุดไม่เติมให้ (ถ้ามีคนเข้าตรวจจริงวันหยุด ข้อมูลนั้นเก็บไว้ ไม่ลบ)
    if ($isWeekend || $isHoliday) continue;

    if (in_array($dateStr, $existingDates)) continue;

    $rstmt->bind_param("issss", $room_id, $dateStr, $checked_by, $saved_id, $saved_name);
    if (!$rstmt->execute()) continue;

    $sel_r = $conn->prepare("SELECT id FROM sc_records WHERE room_id=? AND check_date=?");
    $sel_r->bind_param("is", $room_id, $dateStr);
    $sel_r->execute();
    $record_id = (int)($sel_r->get_result()->fetch_row()[0] ?? 0);
    if (!$record_id) continue;

    foreach ($items as $item) {
        $iid = (int)$item['id'];
        $val = bulkDefaultVal($item, $copyValues, $mode);
        if ($val === null) continue;
        $vstmt->bind_param("iis", $record_id, $iid, $val);
        $vstmt->execute();
    }
    $filled++;
    $filledDates[] = $dateStr;
}

$oldFilled = array_values(array_filter($filledDates, 'scIsBackfill'));
if ($oldFilled) scLogBackfill($conn, $room_id, sprintf('%04d-%02d', $year, $month) . ':bulk', $backfillReason, ['mode' => 'bulk_fill', 'dates' => $oldFilled]);
echo json_encode(['status'=>'success','filled'=>$filled,'cleaned'=>$cleaned]);
