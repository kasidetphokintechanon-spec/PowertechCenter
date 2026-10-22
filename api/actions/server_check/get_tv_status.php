<?php
// api/actions/server_check/get_tv_status.php
// ข้อมูลจอ TV: ผลตรวจวันนี้ทุกห้อง + ประวัติ 7 วันทำงานล่าสุด
// แยกจาก get_month_detail เพราะจอ TV ต้องเห็น "วันนี้" เสมอ ไม่ว่าหน้าจะเปิดดูเดือนไหนอยู่
if (!isset($_SESSION['user_id'])) { echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit; }
// จอ TV รีเฟรชทุกนาที — ไม่ต้องถือล็อกเซสชัน
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$today = date('Y-m-d');

// วันหยุดบริษัทย้อนหลัง 30 วัน (ใช้ข้ามตอนนับ 7 วันทำงาน)
$hol = [];
$st = $conn->prepare("SELECT holiday_date FROM company_holidays WHERE holiday_date BETWEEN DATE_SUB(?, INTERVAL 30 DAY) AND ?");
$st->bind_param('ss', $today, $today);
$st->execute();
foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $h) $hol[$h['holiday_date']] = true;

$days = [];
for ($t = strtotime($today); count($days) < 7; $t -= 86400) {
    $d = date('Y-m-d', $t);
    $w = (int)date('w', $t);
    if ($w !== 0 && $w !== 6 && !isset($hol[$d])) $days[] = $d;
}
$days = array_reverse($days);
$from = min($days[0], $today);

$rooms = $conn->query("SELECT id, code, name, company FROM sc_rooms WHERE is_active = 1 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$items = $conn->query("SELECT id, room_id, label, input_type, threshold, total_count FROM sc_items WHERE is_active = 1 ORDER BY room_id, sort_order")->fetch_all(MYSQLI_ASSOC);
$itemsByRoom = [];
foreach ($items as $it) $itemsByRoom[(int)$it['room_id']][] = $it;

$st = $conn->prepare("SELECT r.room_id, r.check_date, r.checked_by, r.updated_at, v.item_id, v.val
    FROM sc_records r LEFT JOIN sc_values v ON v.record_id = r.id
    WHERE r.check_date BETWEEN ? AND ?");
$st->bind_param('ss', $from, $today);
$st->execute();
$recs = [];
foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $k = (int)$row['room_id'] . '|' . $row['check_date'];
    if (!isset($recs[$k])) $recs[$k] = ['by' => $row['checked_by'], 'at' => $row['updated_at'], 'vals' => []];
    if ($row['item_id'] !== null && $row['val'] !== '' && $row['val'] !== null) $recs[$k]['vals'][(int)$row['item_id']] = $row['val'];
}

// ไม่ผ่าน = ติ๊ก ✗ หรือตัวเลขต่ำกว่าเกณฑ์ (กติกาเดียวกับหน้าตรวจและใบพิมพ์)
function tvFail(array $it, $v): bool {
    if ($v === null || $v === '') return false;
    if (in_array($v, ['✖', '✗', 'issue', 'X', 'x'], true)) return true;
    if (in_array($it['input_type'], ['percent', 'days'], true) && is_numeric($v)) return (float)$v < (float)($it['threshold'] ?: 90);
    return false;
}
function tvState(array $items, array $vals): string {
    if (!$items) return 'none';
    $filled = 0; $bad = false;
    foreach ($items as $it) {
        $v = $vals[(int)$it['id']] ?? '';
        if ($v !== '') $filled++;
        if (tvFail($it, $v)) $bad = true;
    }
    return $bad ? 'bad' : ($filled === 0 ? 'none' : ($filled < count($items) ? 'part' : 'ok'));
}

$out = [];
foreach ($rooms as $r) {
    $rid = (int)$r['id'];
    $its = $itemsByRoom[$rid] ?? [];
    $todayRec = $recs[$rid . '|' . $today] ?? ['by' => '', 'at' => '', 'vals' => []];
    $hist = [];
    foreach ($days as $d) $hist[] = ['date' => $d, 'st' => tvState($its, ($recs[$rid . '|' . $d]['vals'] ?? []))];
    $out[] = [
        'id' => $rid, 'code' => $r['code'], 'name' => $r['name'], 'company' => $r['company'],
        'items' => array_map(fn($i) => ['id' => (int)$i['id'], 'label' => $i['label'], 'type' => $i['input_type'],
            'thr' => (float)($i['threshold'] ?: 90), 'total' => (int)($i['total_count'] ?? 0)], $its),
        'vals' => (object)$todayRec['vals'],
        'by' => $todayRec['vals'] ? (string)$todayRec['by'] : '',
        'at' => $todayRec['vals'] && $todayRec['at'] ? substr($todayRec['at'], 11, 5) : '',
        'state' => tvState($its, $todayRec['vals']),
        'hist' => $hist,
    ];
}

$w = (int)date('w');
echo json_encode([
    'status' => 'success', 'today' => $today, 'closed' => $w === 0 || $w === 6 || isset($hol[$today]),
    'days' => $days, 'rooms' => $out,
], JSON_UNESCAPED_UNICODE);
