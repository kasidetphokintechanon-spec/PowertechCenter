<?php
// api/actions/server_check/get_monthly_summary.php
$room_id = (int)($_GET['room_id'] ?? 0);
$year    = (int)($_GET['year']    ?? date('Y'));
$month   = (int)($_GET['month']   ?? date('n'));

if (!$room_id) { echo json_encode(['status'=>'error','message'=>'missing room_id']); exit; }

$from = sprintf('%04d-%02d-01', $year, $month);
$to   = date('Y-m-t', strtotime($from));
$days = (int)date('t', strtotime($from));

$room  = $conn->query("SELECT * FROM sc_rooms WHERE id=$room_id")->fetch_assoc();
$items = $conn->query("SELECT * FROM sc_items WHERE room_id=$room_id ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);

$records = $conn->query(
    "SELECT id, check_date, checked_by FROM sc_records
     WHERE room_id=$room_id AND check_date BETWEEN '$from' AND '$to'
     ORDER BY check_date"
)->fetch_all(MYSQLI_ASSOC);

$data = [];
foreach ($records as $rec) {
    $rid  = (int)$rec['id'];
    $vals = $conn->query("SELECT item_id, val FROM sc_values WHERE record_id=$rid")->fetch_all(MYSQLI_ASSOC);
    $vmap = [];
    foreach ($vals as $v) $vmap[(string)$v['item_id']] = $v['val'];
    $data[$rec['check_date']] = [
        'record_id'  => $rid,
        'checked_by' => $rec['checked_by'],
        'values'     => $vmap,
    ];
}

echo json_encode([
    'status' => 'success',
    'room'   => $room,
    'items'  => $items,
    'days'   => $days,
    'year'   => $year,
    'month'  => $month,
    'data'   => $data,
]);
