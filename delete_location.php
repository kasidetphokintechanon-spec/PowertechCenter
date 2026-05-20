<?php
// api/actions/system/delete_location.php

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

$id = $_POST['id'] ?? '';
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID']);
    exit;
}

// Get image to delete
$res = $conn->query("SELECT image FROM locations WHERE id = " . intval($id));
if ($row = $res->fetch_assoc()) {
    if ($row['image'] && file_exists($row['image'])) {
        unlink($row['image']);
    }
}

if ($conn->query("DELETE FROM locations WHERE id = " . intval($id))) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
}
?>