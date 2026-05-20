<?php
// api/actions/get_asset_repair_history.php

$asset_id = $_GET['asset_id'] ?? '';
if (empty($asset_id)) {
    echo json_encode([]);
    exit;
}

// Query the it_logs table for records matching the asset_id
$stmt = $conn->prepare("SELECT id, date, problem, solution, status, servicedBy FROM it_logs WHERE asset_id = ? ORDER BY date DESC, id DESC");
if (!$stmt) {
    throw new Exception("Prepare failed (get_asset_repair_history): " . $conn->error);
}

$stmt->bind_param("s", $asset_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
if ($result) $data = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($data);
?>