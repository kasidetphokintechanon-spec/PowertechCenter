<?php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'guest';
$sessionCompany = $_SESSION['company'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid id']);
    exit;
}

try {
    $chk = $conn->prepare("SELECT user_id, company, task_type FROM personal_tasks WHERE id = ?");
    if (!$chk) {
        throw new Exception($conn->error);
    }
    $chk->bind_param("i", $id);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    if (!$row) {
        throw new Exception('ไม่พบรายการสำหรับลบ');
    }

    $canDelete = ($row['user_id'] ?? '') === $userId;
    if (!$canDelete && ($row['task_type'] ?? 'personal') === 'job_ticket' && in_array($role, ['admin', 'staff'], true)) {
        $canDelete = ($role === 'admin') || (($row['company'] ?? '') === $sessionCompany);
    }
    if (!$canDelete) {
        throw new Exception('ไม่มีสิทธิ์ลบรายการนี้');
    }

    $stmt = $conn->prepare("DELETE FROM personal_tasks WHERE id = ?");
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(['status' => 'success']);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
