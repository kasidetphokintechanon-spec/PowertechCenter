<?php
// api/actions/server_check/get_check_data.php
$room_id = (int)($_GET['room_id'] ?? 0);
$date    = preg_replace('/[^0-9\-]/', '', $_GET['date'] ?? date('Y-m-d'));
if (!$room_id) { echo json_encode(['status'=>'error','message'=>'missing room_id']); exit; }

$items  = $conn->query("SELECT * FROM sc_items WHERE room_id=$room_id ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$record = null;
$values = (object)[];

$rec = $conn->query("SELECT * FROM sc_records WHERE room_id=$room_id AND check_date='$date'")->fetch_assoc();
if ($rec) {
    $record = $rec;
    $vals = $conn->query("SELECT item_id, val FROM sc_values WHERE record_id={$rec['id']}")->fetch_all(MYSQLI_ASSOC);
    $vmap = [];
    foreach ($vals as $v) $vmap[(string)$v['item_id']] = $v['val'];
    $values = $vmap;
}

echo json_encode(['status'=>'success','items'=>$items,'record'=>$record,'values'=>$values]);
