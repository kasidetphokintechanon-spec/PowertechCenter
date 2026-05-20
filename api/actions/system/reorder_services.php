<?php
// api/actions/reorder_services.php

$order = json_decode($_POST['order'] ?? '[]', true);
if (is_array($order) && count($order) > 0) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE services SET sort_order = ? WHERE id = ?");
        foreach ($order as $index => $id) {
            $sort = $index + 1;
            $stmt->bind_param("is", $sort, $id);
            $stmt->execute();
        }
        $conn->commit();
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}
?>