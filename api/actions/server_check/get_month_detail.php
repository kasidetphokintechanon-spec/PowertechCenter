<?php
// api/actions/server_check/get_month_detail.php
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));

if ($month < 1 || $month > 12)     { echo json_encode(['status'=>'error','message'=>'invalid month']); exit; }
if ($year  < 2000 || $year > 2100) { echo json_encode(['status'=>'error','message'=>'invalid year']);  exit; }

$from = sprintf('%04d-%02d-01', $year, $month);
$to   = date('Y-m-t', strtotime($from));
$days = (int)date('t', strtotime($from));

$rooms    = $conn->query("SELECT id, code, name, location, company FROM sc_rooms WHERE is_active = 1 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$allItems = $conn->query("SELECT id, room_id, item_no, label, input_type, threshold FROM sc_items WHERE is_active = 1 ORDER BY room_id, sort_order")->fetch_all(MYSQLI_ASSOC);

$itemsByRoom = [];
foreach ($allItems as $item) {
    $rid = (int)$item['room_id'];
    if (!isset($itemsByRoom[$rid])) $itemsByRoom[$rid] = [];
    $itemsByRoom[$rid][] = $item;
}

// All records + values for the month
$sql = "SELECT r.room_id, r.check_date, r.checked_by, r.suggestions, v.item_id, v.val
        FROM sc_records r
        LEFT JOIN sc_values v ON v.record_id = r.id
        WHERE r.check_date BETWEEN '$from' AND '$to'";

$rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$vals     = []; // vals[rid][date][iid] = val
$checkers = []; // checkers[rid][date]  = checked_by name
$sugg     = []; // sugg[rid][date]      = suggestions text

foreach ($rows as $row) {
    $rid  = (int)$row['room_id'];
    $date = $row['check_date'];
    $iid  = ($row['item_id'] !== null) ? (int)$row['item_id'] : null;

    if (!isset($checkers[$rid][$date])) {
        $checkers[$rid][$date] = $row['checked_by'] ?? '';
        $sugg[$rid][$date]     = $row['suggestions'] ?? '';
    }
    if ($iid !== null) {
        $vals[$rid][$date][$iid] = $row['val'] ?? '';
    }
}

echo json_encode([
    'status'      => 'success',
    'rooms'       => $rooms,
    'itemsByRoom' => $itemsByRoom,
    'vals'        => $vals,
    'checkers'    => $checkers,
    'sugg'        => $sugg,
    'days'        => $days,
    'month'       => $month,
    'year'        => $year,
]);
