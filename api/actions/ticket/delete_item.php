<?php
// api/actions/delete_item.php

$key = $_POST['key'] ?? '';
$id = $_POST['id'] ?? '';
$role = $_SESSION['role'] ?? 'guest';
$isLoggedIn = isset($_SESSION['user_id']);
$sessionCompany = $_SESSION['company'] ?? '';

if (!$isLoggedIn || !in_array($role, ['admin', 'staff'], true)) {
    throw new Exception("Access Denied");
}
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
    $assetDbIdForLog = null;

    if ($key === 'it_logs') {
        if ($role !== 'admin' && $sessionCompany) {
            $stmt_chk = $conn->prepare("SELECT company FROM it_logs WHERE id = ? LIMIT 1");
            if (!$stmt_chk) throw new Exception("Prepare failed");
            $stmt_chk->bind_param("s", $id);
            $stmt_chk->execute();
            $res_chk = $stmt_chk->get_result();
            $row_chk = $res_chk ? $res_chk->fetch_assoc() : null;
            if (!$row_chk || ($row_chk['company'] ?? '') !== $sessionCompany) {
                throw new Exception("Access Denied");
            }
        }

        // เตรียมข้อมูลสำหรับบันทึกประวัติใน asset_history หลังลบใบงาน
        $stmt_asset = $conn->prepare("SELECT asset_id FROM it_logs WHERE id = ? LIMIT 1");
        if ($stmt_asset) {
            $stmt_asset->bind_param("s", $id);
            $stmt_asset->execute();
            $asset_res = $stmt_asset->get_result();
            if ($asset_res && $asset_row = $asset_res->fetch_assoc()) {
                $assetIdStr = $conn->real_escape_string($asset_row['asset_id']);
                if ($assetIdStr) {
                    $assetQ = $conn->query("SELECT id FROM it_assets WHERE equipment_id = '$assetIdStr' LIMIT 1");
                    if ($assetQ && $assetDbRow = $assetQ->fetch_assoc()) {
                        $assetDbIdForLog = (int)$assetDbRow['id'];
                    }
                }
            }
        }
    }
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

        // บันทึกประวัติการลบใบงานลงใน asset_history (ใช้เป็น Audit Log)
        if ($key === 'it_logs' && $assetDbIdForLog) {
            logAssetHistory($conn, $assetDbIdForLog, 'REPAIR_DELETE', "Ticket #$id: Deleted");
        }

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
