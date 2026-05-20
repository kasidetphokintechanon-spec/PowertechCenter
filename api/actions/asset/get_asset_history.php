<?php
// api/actions/get_asset_history.php

$id = $_GET['id'] ?? '';
$sql = "SELECT * FROM asset_history WHERE asset_db_id = ? ORDER BY timestamp DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = [];
while ($row = $result->fetch_assoc()) $data[] = $row;
echo json_encode($data);
?>