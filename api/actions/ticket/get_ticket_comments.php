<?php
// api/actions/ticket/get_ticket_comments.php
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

$ticketId = trim($_GET['ticket_id'] ?? '');
if ($ticketId === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing ticket_id']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT id, ticket_id, user_id, user_name, user_role, message, file_path, file_name,
            DATE_FORMAT(created_at,'%Y-%m-%d %H:%i:%s') AS created_at
     FROM ticket_comments WHERE ticket_id = ? ORDER BY created_at ASC"
);
$stmt->bind_param('s', $ticketId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
echo json_encode(['status' => 'success', 'comments' => $rows]);
