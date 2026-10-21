<?php
// api/actions/delete_asset_note.php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$history_id = intval($_POST['history_id'] ?? 0);
if ($history_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    exit;
}

$role = $_SESSION['role'] ?? 'guest';
$is_admin = in_array($role, ['admin', 'staff'], true);

if (!$is_admin) {
    // Non-admin can only delete notes they created (matched by display_name)
    $current_name = $_SESSION['display_name'] ?? $_SESSION['username'] ?? '';
    $chk = $conn->prepare("SELECT history_id FROM asset_history WHERE history_id = ? AND user_name = ?");
    $chk->bind_param("is", $history_id, $current_name);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied']);
        exit;
    }
}

$stmt = $conn->prepare("DELETE FROM asset_history WHERE history_id = ?");
$stmt->bind_param("i", $history_id);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Delete failed']);
}
?>
