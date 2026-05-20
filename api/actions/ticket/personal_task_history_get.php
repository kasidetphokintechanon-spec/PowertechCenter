<?php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
if ($taskId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid task id']);
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['display_name'] ?? ($_SESSION['username'] ?? '');
$role = $_SESSION['role'] ?? 'guest';
$sessionCompany = $_SESSION['company'] ?? '';
$sessionDept = $_SESSION['primary_department'] ?? '';

$stmt = $conn->prepare("SELECT user_id, company, department, share_scope FROM personal_tasks WHERE id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Task not found']);
    exit;
}
$stmt->bind_param("i", $taskId);
$stmt->execute();
$res = $stmt->get_result();
$task = $res->fetch_assoc();

if (!$task) {
    echo json_encode(['status' => 'error', 'message' => 'Task not found']);
    exit;
}

$ownerId = $task['user_id'];
$taskCompany = $task['company'] ?? '';
$taskDept = $task['department'] ?? '';
$shareScope = $task['share_scope'] ?? 'private';

$allowed = false;
if ($ownerId === $userId) {
    $allowed = true;
} elseif ($shareScope === 'department' && $sessionCompany && $sessionDept && $taskCompany === $sessionCompany && $taskDept === $sessionDept) {
    $allowed = true;
} elseif ($shareScope === 'company' && in_array($role, ['admin', 'staff'], true)) {
    if ($role === 'admin') {
        $allowed = true;
    } elseif ($sessionCompany && $taskCompany === $sessionCompany) {
        $allowed = true;
    }
}

if (!$allowed) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

$log = $conn->prepare("INSERT INTO personal_task_history (task_id, user_id, user_name, action) VALUES (?, ?, ?, ?)");
if ($log) {
    $act = 'view';
    $log->bind_param("isss", $taskId, $userId, $userName, $act);
    $log->execute();
}

$h = $conn->prepare("SELECT id, user_id, user_name, action, old_status, new_status, old_priority, new_priority, created_at FROM personal_task_history WHERE task_id = ? ORDER BY created_at DESC, id DESC");
if (!$h) {
    echo json_encode(['status' => 'error', 'message' => 'Cannot load history']);
    exit;
}
$h->bind_param("i", $taskId);
$h->execute();
$resH = $h->get_result();
$rows = [];
while ($row = $resH->fetch_assoc()) {
    $rows[] = $row;
}

echo json_encode(['status' => 'success', 'items' => $rows]);
