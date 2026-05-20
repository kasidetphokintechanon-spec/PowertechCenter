<?php
// api/actions/delete_item.php

$key = $_POST['key'] ?? '';
$id = $_POST['id'] ?? '';
$tableMap = [
    'it_assets' => ['table' => 'it_assets', 'pk' => 'id'],
    'employees' => ['table' => 'employees', 'pk' => 'id'],
    'holidays' => ['table' => 'holidays', 'pk' => 'id'],
    'services' => ['table' => 'services', 'pk' => 'id'],
    'meeting_rooms' => ['table' => 'meeting_rooms', 'pk' => 'id'],
    'meeting_amenities' => ['table' => 'meeting_amenities', 'pk' => 'id'],
    'departments' => ['table' => 'departments', 'pk' => 'dept_id'],
    'asset_categories' => ['table' => 'asset_categories', 'pk' => 'cat_id'],
    'announcements' => ['table' => 'announcements', 'pk' => 'id'],
    'it_logs' => ['table' => 'it_logs', 'pk' => 'id'],
    'kb_articles' => ['table' => 'kb_articles', 'pk' => 'id'],
    'kb_categories' => ['table' => 'kb_categories', 'pk' => 'id'],
    'job_tickets' => ['table' => 'job_tickets', 'pk' => 'id']
];

if (isset($tableMap[$key]) && !empty($id)) {
    $conn->begin_transaction();
    try {
        // Special handling for it_logs to delete attachments first
        if ($key === 'it_logs') {
            $attach_stmt = $conn->prepare("DELETE FROM it_log_attachments WHERE log_id = ?");
            if (!$attach_stmt) throw new Exception("Prepare failed (Delete Attachments): " . $conn->error);
            $attach_stmt->bind_param("s", $id);
            if (!$attach_stmt->execute()) throw new Exception("Execute failed (Delete Attachments): " . $attach_stmt->error);
        }

        $table = $tableMap[$key]['table'];
        $col = $tableMap[$key]['pk'];
        $stmt = $conn->prepare("DELETE FROM $table WHERE $col = ?");
        if (!$stmt) throw new Exception("Prepare failed (Delete): " . $conn->error);
        $stmt->bind_param("s", $id);
        if (!$stmt->execute()) throw new Exception($stmt->error);

        $conn->commit();
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
} else {
    throw new Exception("Invalid table key or ID");
}
?>