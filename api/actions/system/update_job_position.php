<?php
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? 'guest'), ['admin', 'staff'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    throw new Exception('Invalid position id');
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

$name = trim($_POST['name'] ?? '');
$sortOrder = intval($_POST['sort_order'] ?? 999);
$isActive = intval($_POST['is_active'] ?? 1) ? 1 : 0;
if ($sortOrder < 1) $sortOrder = 999;

if ($name !== '') {
    if (mb_strlen($name, 'UTF-8') > 255) throw new Exception('Position name is too long');
    $stmt = $conn->prepare("UPDATE job_positions SET name = ?, sort_order = ?, is_active = ? WHERE id = ?");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param("siii", $name, $sortOrder, $isActive, $id);
} else {
    $stmt = $conn->prepare("UPDATE job_positions SET sort_order = ?, is_active = ? WHERE id = ?");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param("iii", $sortOrder, $isActive, $id);
}

if (!$stmt->execute()) throw new Exception('Update failed: ' . $stmt->error);
echo json_encode(['status' => 'success']);
