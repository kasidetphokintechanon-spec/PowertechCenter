<?php
// api/actions/update_asset_note.php

$history_id = $_POST['history_id'] ?? '';
$note = $_POST['note'] ?? '';

// ดึงข้อมูลเดิมเพื่อคงรูปภาพไว้
$res = $conn->query("SELECT details FROM asset_history WHERE history_id = '$history_id'");
$row = $res->fetch_assoc();
$details = json_decode($row['details'], true);
$details['text'] = $note;

$newDetails = json_encode($details, JSON_UNESCAPED_UNICODE);
$stmt = $conn->prepare("UPDATE asset_history SET details = ? WHERE history_id = ?");
$stmt->bind_param("si", $newDetails, $history_id);
if ($stmt->execute()) echo json_encode(['status' => 'success']);
else throw new Exception($stmt->error);
?>