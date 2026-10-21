<?php
// api/actions/ticket/add_ticket_comment.php
require_once __DIR__ . '/../../..' . '/includes/notify_helper.php';

$conn->query("CREATE TABLE IF NOT EXISTS ticket_comments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id   VARCHAR(50)  NOT NULL,
    user_id     VARCHAR(100) NOT NULL,
    user_name   VARCHAR(200) NOT NULL,
    user_role   ENUM('it','user') NOT NULL DEFAULT 'user',
    message     TEXT NOT NULL,
    file_path   VARCHAR(500) DEFAULT NULL,
    file_name   VARCHAR(200) DEFAULT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tc_ticket (ticket_id),
    INDEX idx_tc_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$ticketId = trim($_POST['ticket_id'] ?? '');
$message  = trim($_POST['message']   ?? '');
$userId   = $_SESSION['user_id']     ?? '';
$userName = $_SESSION['display_name'] ?? ($_SESSION['username'] ?? $userId);
$role     = strtolower($_SESSION['role'] ?? 'user');
$company  = $_SESSION['company']     ?? null;

$hasFile = !empty($_FILES['comment_file']['name']) && $_FILES['comment_file']['error'] === UPLOAD_ERR_OK;
if ($ticketId === '' || ($message === '' && !$hasFile)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อความหรือแนบไฟล์']);
    exit;
}
if ($userId === '') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$itRoles   = ['admin', 'technician', 'it', 'manager'];
$userRole  = in_array($role, $itRoles) ? 'it' : 'user';

// Handle optional file upload
$filePath = null;
$fileName = null;
if ($hasFile) {
    $uploadDir = __DIR__ . '/../../../uploads/ticket_comments/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
    $origName = basename($_FILES['comment_file']['name']);
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowed  = ['jpg','jpeg','png','gif','webp','pdf','xlsx','xls','docx','doc','txt','zip'];
    if (!in_array($ext, $allowed)) {
        echo json_encode(['status' => 'error', 'message' => 'ไฟล์ประเภทนี้ไม่รองรับ']);
        exit;
    }
    $safeName = date('YmdHis') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
    if (move_uploaded_file($_FILES['comment_file']['tmp_name'], $uploadDir . $safeName)) {
        $filePath = 'uploads/ticket_comments/' . $safeName;
        $fileName = $origName;
    }
}

$stmt = $conn->prepare(
    "INSERT INTO ticket_comments (ticket_id, user_id, user_name, user_role, message, file_path, file_name)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param('sssssss', $ticketId, $userId, $userName, $userRole, $message, $filePath, $fileName);
if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
    exit;
}

$newId = $conn->insert_id;

// ── Auto status change ──
$newStatus = null;
$prevStatus = null;
$stCheck = $conn->prepare("SELECT status FROM it_logs WHERE id = ? LIMIT 1");
if ($stCheck) { $stCheck->bind_param('s', $ticketId); $stCheck->execute(); $prevStatus = $stCheck->get_result()->fetch_assoc()['status'] ?? null; }

if ($userRole === 'it' && !empty($_POST['set_waiting'])) {
    $upd = $conn->prepare("UPDATE it_logs SET status = 'Waiting for User' WHERE id = ?");
    if ($upd) { $upd->bind_param('s', $ticketId); $upd->execute(); }
    $newStatus = 'Waiting for User';
} elseif ($userRole === 'user' && $prevStatus === 'Waiting for User') {
    $upd2 = $conn->prepare("UPDATE it_logs SET status = 'In Progress' WHERE id = ?");
    if ($upd2) { $upd2->bind_param('s', $ticketId); $upd2->execute(); }
    $newStatus = 'In Progress';
}

// ── Audit log → mt_ticket_events ──
$snippet  = mb_substr($message, 0, 120, 'UTF-8') . (mb_strlen($message, 'UTF-8') > 120 ? '…' : '');
$fileNote = $fileName ? " [แนบ: {$fileName}]" : '';
$details  = ($userRole === 'it' ? '[IT] ' : '[User] ') . $userName . ': ' . $snippet . $fileNote;

$conn->query("CREATE TABLE IF NOT EXISTS mt_ticket_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_id VARCHAR(50) NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    from_status VARCHAR(100) DEFAULT NULL,
    to_status VARCHAR(100) DEFAULT NULL,
    actor_id VARCHAR(100) DEFAULT NULL,
    actor_name VARCHAR(200) DEFAULT NULL,
    assigned_to_id VARCHAR(100) DEFAULT NULL,
    assigned_to_name VARCHAR(200) DEFAULT NULL,
    details TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mte_log (log_id),
    INDEX idx_mte_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Log COMMENT event
$evStmt = $conn->prepare("INSERT INTO mt_ticket_events (log_id,event_type,from_status,to_status,actor_id,actor_name,details,created_at) VALUES (?,'COMMENT',?,?,?,?,?,NOW())");
if ($evStmt) { $evStmt->bind_param('ssssss', $ticketId, $prevStatus, $prevStatus, $userId, $userName, $details); $evStmt->execute(); }

// Log STATUS event if status changed
if ($newStatus && $prevStatus && $newStatus !== $prevStatus) {
    $stEvType = $newStatus === 'Waiting for User' ? 'WAIT_USER' : 'STATUS';
    $stEvStmt = $conn->prepare("INSERT INTO mt_ticket_events (log_id,event_type,from_status,to_status,actor_id,actor_name,created_at) VALUES (?,?,?,?,?,?,NOW())");
    if ($stEvStmt) { $stEvStmt->bind_param('ssssss', $ticketId, $stEvType, $prevStatus, $newStatus, $userId, $userName); $stEvStmt->execute(); }
}


$resp = [
    'status'    => 'success',
    'comment_id' => $newId,
    'user_role' => $userRole,
    'user_name' => $userName,
    'file_path' => $filePath,
    'file_name' => $fileName,
    'created_at' => date('Y-m-d H:i:s'),
];
if ($newStatus !== null) $resp['new_status'] = $newStatus;
echo json_encode($resp);
