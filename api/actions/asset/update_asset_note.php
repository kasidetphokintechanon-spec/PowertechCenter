<?php
// api/actions/update_asset_note.php

$history_id = intval($_POST['history_id'] ?? 0);
$note = $_POST['note'] ?? '';

if ($history_id <= 0) { echo json_encode(['status' => 'error', 'message' => 'Invalid ID']); exit; }

$sel = $conn->prepare("SELECT details FROM asset_history WHERE history_id = ?");
$sel->bind_param("i", $history_id);
$sel->execute();
$row = $sel->get_result()->fetch_assoc();
$details = json_decode($row['details'], true);
$details['text'] = $note;

$newDetails = json_encode($details, JSON_UNESCAPED_UNICODE);
$stmt = $conn->prepare("UPDATE asset_history SET details = ? WHERE history_id = ?");
$stmt->bind_param("si", $newDetails, $history_id);
if ($stmt->execute()) echo json_encode(['status' => 'success']);
else throw new Exception($stmt->error);
?>