<?php
// api/actions/system/delete_notify_channel.php
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) throw new Exception("ID ไม่ถูกต้อง");

$stmt = $conn->prepare("DELETE FROM notify_channels WHERE id = ?");
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("i", $id);
$stmt->execute();

echo json_encode(['status' => 'success']);
