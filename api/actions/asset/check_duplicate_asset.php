<?php
// api/actions/check_duplicate_asset.php

$equipment_id = $_GET['equipment_id'] ?? '';
$db_id = $_GET['db_id'] ?? 0;

if (empty($equipment_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing equipment_id']);
    exit;
}

$sql = "SELECT id FROM it_assets WHERE equipment_id = ? AND id != ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $equipment_id, $db_id);
$stmt->execute();
$is_duplicate = $stmt->get_result()->num_rows > 0;

echo json_encode(['status' => 'success', 'is_duplicate' => $is_duplicate]);
?>