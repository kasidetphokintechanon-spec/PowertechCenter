<?php
// api/actions/update_job_status.php

$id = $_POST['id'] ?? 0;
$col = $_POST['col'] ?? ''; // e.g., 'is_receive'
$val = $_POST['val'] ?? 0; // 0 or 1

if (!$id || !$col) throw new Exception("Missing parameters.");

// Map col index/name to DB column if needed, or assume frontend sends correct column name
$allowed_cols = ['is_receive', 'is_doing', 'is_send', 'is_approve', 'is_done'];
if (!in_array($col, $allowed_cols)) throw new Exception("Invalid column.");

$stmt = $conn->prepare("UPDATE job_tickets SET $col = ?, last_update = NOW() WHERE id = ?");
$stmt->bind_param("ii", $val, $id);

if ($stmt->execute()) echo json_encode(['status' => 'success']);
else throw new Exception("Update failed: " . $stmt->error);
?>