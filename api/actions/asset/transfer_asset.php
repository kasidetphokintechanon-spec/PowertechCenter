<?php
// api/actions/transfer_asset.php

$db_id = $_POST['db_id'] ?? '';
$new_company = $_POST['new_company'] ?? '';
$reason = $_POST['reason'] ?? '';
$transfer_set = isset($_POST['transfer_set']) && $_POST['transfer_set'] === '1';

if (empty($db_id) || empty($new_company)) {
    throw new Exception("Missing parameters.");
}

$conn->begin_transaction();
try {
    $ids_to_update = [$db_id];

    if ($transfer_set) {
        // Find all children of the parent asset
        $child_stmt = $conn->prepare("SELECT id FROM it_assets WHERE parent_id = ?");
        if (!$child_stmt) throw new Exception("Prepare failed (Find Children): " . $conn->error);
        $child_stmt->bind_param("i", $db_id);
        $child_stmt->execute();
        $child_result = $child_stmt->get_result();
        while ($row = $child_result->fetch_assoc()) {
            $ids_to_update[] = $row['id'];
        }
    }

    // Create placeholders for the IN clause
    $placeholders = implode(',', array_fill(0, count($ids_to_update), '?'));
    $types = str_repeat('i', count($ids_to_update));

    $sql = "UPDATE it_assets SET company = ? WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed (Update Set): " . $conn->error);
    
    $params = array_merge([$new_company], $ids_to_update);
    $stmt->bind_param("s" . $types, ...$params);
    
    if (!$stmt->execute()) throw new Exception("Update failed: " . $stmt->error);

    foreach ($ids_to_update as $id) {
        logAssetHistory($conn, $id, 'TRANSFER', "โอนย้ายไปบริษัท $new_company. เหตุผล: $reason");
    }

    $conn->commit();
    echo json_encode(['status' => 'success']);
} catch (Exception $e) { $conn->rollback(); throw $e; }
?>