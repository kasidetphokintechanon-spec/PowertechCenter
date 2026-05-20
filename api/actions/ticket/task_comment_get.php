<?php
// api/actions/task_comment_get.php
// ดึง comments และ progress ของงาน

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'guest';
$sessionCompany = $_SESSION['company'] ?? '';
$sessionDept = $_SESSION['primary_department'] ?? '';

$taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
if ($taskId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid task_id']);
    exit;
}

// ตรวจสิทธิ์
$stmt = $conn->prepare("SELECT user_id, company, department, share_scope, progress_pct FROM personal_tasks WHERE id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'DB error']);
    exit;
}
$stmt->bind_param("i", $taskId);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();

if (!$task) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบงาน']);
    exit;
}

$allowed = false;
if ($task['user_id'] === $userId) {
    $allowed = true;
} elseif ($task['share_scope'] === 'department' && $task['company'] === $sessionCompany && $task['department'] === $sessionDept) {
    $allowed = true;
} elseif ($task['share_scope'] === 'company' && in_array($role, ['admin', 'staff'], true)) {
    $allowed = $role === 'admin' || $task['company'] === $sessionCompany;
}

if (!$allowed) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์']);
    exit;
}

// ตรวจว่ามีตาราง comments ไหม
$tblChk = $conn->query("SHOW TABLES LIKE 'personal_task_comments'");
if (!$tblChk || $tblChk->num_rows === 0) {
    echo json_encode(['status' => 'success', 'progress_pct' => 0, 'comments' => []]);
    exit;
}

$stmtC = $conn->prepare("SELECT id, user_id, user_name, comment, progress_pct, created_at FROM personal_task_comments WHERE task_id = ? ORDER BY created_at ASC");
if (!$stmtC) {
    echo json_encode(['status' => 'error', 'message' => 'DB error']);
    exit;
}
$stmtC->bind_param("i", $taskId);
$stmtC->execute();
$resC = $stmtC->get_result();

$comments = [];
while ($row = $resC->fetch_assoc()) {
    $comments[] = $row;
}

echo json_encode([
    'status' => 'success',
    'progress_pct' => (int)($task['progress_pct'] ?? 0),
    'comments' => $comments
]);
?>
