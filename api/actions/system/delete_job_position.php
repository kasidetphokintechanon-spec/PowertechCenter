<?php
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? 'guest'), ['admin', 'staff'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    throw new Exception('Invalid position id');
}

$stmt = $conn->prepare("DELETE FROM job_positions WHERE id = ?");
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("i", $id);
if (!$stmt->execute()) throw new Exception('Delete failed: ' . $stmt->error);

echo json_encode(['status' => 'success']);
