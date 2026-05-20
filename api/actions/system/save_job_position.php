<?php
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? 'guest'), ['admin', 'staff'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

$name = trim($_POST['name'] ?? '');
if ($name === '') {
    throw new Exception('Position name is required');
}
if (mb_strlen($name, 'UTF-8') > 255) {
    throw new Exception('Position name is too long');
}

$conn->query(
    "CREATE TABLE IF NOT EXISTS job_positions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        sort_order INT NOT NULL DEFAULT 999,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_position_name (name),
        KEY idx_sort_order (sort_order),
        KEY idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$sortOrder = intval($_POST['sort_order'] ?? 999);
if ($sortOrder < 1) $sortOrder = 999;

$stmt = $conn->prepare(
    "INSERT INTO job_positions (name, sort_order, is_active)
     VALUES (?, ?, 1)
     ON DUPLICATE KEY UPDATE is_active = 1, sort_order = VALUES(sort_order), updated_at = CURRENT_TIMESTAMP"
);
if (!$stmt) {
    throw new Exception('Prepare failed: ' . $conn->error);
}
$stmt->bind_param("si", $name, $sortOrder);
if (!$stmt->execute()) {
    throw new Exception('Save failed: ' . $stmt->error);
}

echo json_encode(['status' => 'success', 'name' => $name]);
