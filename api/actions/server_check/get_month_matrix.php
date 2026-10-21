<?php
// api/actions/server_check/get_month_matrix.php
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));

if ($month < 1 || $month > 12)    { echo json_encode(['status'=>'error','message'=>'invalid month']); exit; }
if ($year  < 2000 || $year > 2100) { echo json_encode(['status'=>'error','message'=>'invalid year']);  exit; }

$from = sprintf('%04d-%02d-01', $year, $month);
$to   = date('Y-m-t', strtotime($from));
$days = (int)date('t', strtotime($from));

$rooms = $conn->query("SELECT id, code, name, location FROM sc_rooms ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);

// Items count per room
$roomTotals = [];
foreach ($rooms as $room) {
    $rid = (int)$room['id'];
    $roomTotals[$rid] = (int)$conn->query("SELECT COUNT(*) FROM sc_items WHERE room_id=$rid")->fetch_row()[0];
}

// All records + value summary for the month
$sql = "SELECT r.id, r.room_id, r.check_date, r.checked_by,
    COUNT(v.id) AS filled,
    SUM(CASE WHEN v.val IN ('✖','issue') THEN 1 ELSE 0 END) AS issues
    FROM sc_records r
    LEFT JOIN sc_values v ON v.record_id = r.id AND v.val IS NOT NULL AND v.val != ''
    WHERE r.check_date BETWEEN '$from' AND '$to'
    GROUP BY r.id, r.room_id, r.check_date, r.checked_by";

$rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$matrix = [];
foreach ($rows as $row) {
    $rid    = (int)$row['room_id'];
    $date   = $row['check_date'];
    $total  = $roomTotals[$rid] ?? 0;
    $filled = (int)$row['filled'];
    $issues = (int)$row['issues'];
    $status = $issues > 0 ? 'issue' : ($total > 0 && $filled >= $total ? 'ok' : 'partial');

    if (!isset($matrix[$rid])) $matrix[$rid] = [];
    $matrix[$rid][$date] = [
        'status'     => $status,
        'filled'     => $filled,
        'total'      => $total,
        'checked_by' => $row['checked_by'] ?? '',
    ];
}

echo json_encode([
    'status'     => 'success',
    'rooms'      => $rooms,
    'matrix'     => $matrix,
    'days'       => $days,
    'month'      => $month,
    'year'       => $year,
    'roomTotals' => $roomTotals,
]);
