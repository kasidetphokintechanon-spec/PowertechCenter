<?php
$log_id = trim($_GET['log_id'] ?? '');
if (!$log_id) { echo json_encode(['status'=>'error','message'=>'missing log_id']); exit; }

$stmt = $conn->prepare(
    "SELECT event_type, from_status, to_status, actor_name, details, created_at
     FROM mt_ticket_events
     WHERE log_id = ?
     ORDER BY created_at ASC"
);
$stmt->bind_param('s', $log_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
echo json_encode(['status'=>'success','events'=>$rows]);
