<?php
// api/actions/task_comment_save.php
// บันทึก comment และ % ความคืบหน้าของงาน

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['display_name'] ?? ($_SESSION['username'] ?? '');
$role = $_SESSION['role'] ?? 'guest';
$sessionCompany = $_SESSION['company'] ?? '';
$sessionDept = $_SESSION['primary_department'] ?? '';

$taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
$comment = trim($_POST['comment'] ?? '');
$progress = isset($_POST['progress']) ? (int)$_POST['progress'] : -1;

if ($taskId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid task_id']);
    exit;
}
if ($comment === '' && $progress < 0) {
    echo json_encode(['status' => 'error', 'message' => 'ต้องใส่ comment หรือ progress']);
    exit;
}

// ตรวจสอบว่า user มีสิทธิ์เข้าถึง task นี้
$stmt = $conn->prepare("SELECT user_id, company, department, share_scope FROM personal_tasks WHERE id = ?");
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

// สร้างตารางถ้ายังไม่มี
$conn->query("CREATE TABLE IF NOT EXISTS personal_task_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id VARCHAR(64),
    user_name VARCHAR(100),
    comment TEXT,
    progress_pct TINYINT DEFAULT NULL,
    created_at DATETIME DEFAULT NOW(),
    INDEX idx_task_id (task_id)
)");

// เพิ่ม progress_pct column ใน personal_tasks ถ้ายังไม่มี
$chk = $conn->query("SHOW COLUMNS FROM personal_tasks LIKE 'progress_pct'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE personal_tasks ADD COLUMN progress_pct TINYINT DEFAULT 0 AFTER status");
}

try {
    $responseProgress = isset($task['progress_pct']) ? (int)$task['progress_pct'] : 0;

    // บันทึก comment
    if ($comment !== '') {
        $stmtC = $conn->prepare("INSERT INTO personal_task_comments (task_id, user_id, user_name, comment, progress_pct) VALUES (?, ?, ?, ?, ?)");
        if (!$stmtC) throw new Exception($conn->error);
        $progressVal = $progress >= 0 ? $progress : null;
        $stmtC->bind_param("isssi", $taskId, $userId, $userName, $comment, $progressVal);
        $stmtC->execute();
    }

    // อัปเดต % ความคืบหน้าใน personal_tasks
    if ($progress >= 0 && $progress <= 100) {
        $stmtP = $conn->prepare("UPDATE personal_tasks SET progress_pct = ?, updated_at = NOW() WHERE id = ?");
        if ($stmtP) {
            $stmtP->bind_param("ii", $progress, $taskId);
            $stmtP->execute();
            $responseProgress = $progress;
        }

        // บันทึก history ด้วย
        $hStmt = $conn->prepare("INSERT INTO personal_task_history (task_id, user_id, user_name, action) VALUES (?, ?, ?, ?)");
        if ($hStmt) {
            $act = "progress:{$progress}%";
            $hStmt->bind_param("isss", $taskId, $userId, $userName, $act);
            $hStmt->execute();
        }
    }

    echo json_encode(['status' => 'success', 'progress_pct' => $responseProgress]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
