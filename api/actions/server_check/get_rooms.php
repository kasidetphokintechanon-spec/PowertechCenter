<?php
// api/actions/server_check/get_rooms.php
$date = preg_replace('/[^0-9\-]/', '', $_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

$rooms = $conn->query("SELECT * FROM sc_rooms WHERE is_active = 1 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);

foreach ($rooms as &$room) {
    $rid  = (int)$room['id'];
    $rec  = $conn->query("SELECT id, checked_by FROM sc_records WHERE room_id=$rid AND check_date='$date'")->fetch_assoc();
    $room['record_id']  = $rec ? (int)$rec['id'] : null;
    $room['checked_by'] = $rec ? ($rec['checked_by'] ?? '') : '';

    $total  = (int)$conn->query("SELECT COUNT(*) FROM sc_items WHERE room_id=$rid AND is_active=1")->fetch_row()[0];
    $filled = 0;
    $hasIssue = false;
    if ($rec) {
        $rid_val = (int)$rec['id'];
        $filled   = (int)$conn->query("SELECT COUNT(*) FROM sc_values WHERE record_id=$rid_val AND val IS NOT NULL AND val != ''")->fetch_row()[0];
        $issueRow = $conn->query("SELECT COUNT(*) FROM sc_values WHERE record_id=$rid_val AND val IN ('✖','issue')")->fetch_row();
        $hasIssue = $issueRow && (int)$issueRow[0] > 0;
    }
    $room['progress']  = ['filled' => $filled, 'total' => $total, 'done' => $total > 0 && $filled >= $total];
    $room['has_issue'] = $hasIssue;
}

echo json_encode(['status' => 'success', 'rooms' => $rooms]);
