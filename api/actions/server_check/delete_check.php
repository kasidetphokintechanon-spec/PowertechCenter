<?php
// api/actions/server_check/delete_check.php
if (!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

$room_id    = (int)($_POST['room_id']    ?? 0);
$check_date = preg_replace('/[^0-9\-]/', '', $_POST['check_date'] ?? '');

if (!$room_id)                                         { echo json_encode(['status'=>'error','message'=>'missing room_id']);   exit; }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_date)) { echo json_encode(['status'=>'error','message'=>'invalid date']);      exit; }

$rec = $conn->query("SELECT id FROM sc_records WHERE room_id=$room_id AND check_date='$check_date'")->fetch_assoc();
if (!$rec) { echo json_encode(['status'=>'error','message'=>'record not found']); exit; }

$rid = (int)$rec['id'];
$conn->query("DELETE FROM sc_values  WHERE record_id=$rid");
$conn->query("DELETE FROM sc_records WHERE id=$rid");

echo json_encode(['status'=>'success']);
