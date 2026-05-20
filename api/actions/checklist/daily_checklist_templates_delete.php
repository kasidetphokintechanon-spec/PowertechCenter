<?php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$role = $_SESSION['role'] ?? 'guest';

if (!in_array($role, ['admin', 'staff'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid id']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE daily_checklist_items SET is_active = 0 WHERE id = ?");
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบรายการสำหรับลบ']);
        exit;
    }
    echo json_encode(['status' => 'success']);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

