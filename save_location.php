<?php
// api/actions/system/save_location.php

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

$id = $_POST['id'] ?? '';
$name = $_POST['name'] ?? '';
$company = $_POST['company'] ?? '';
$is_active = isset($_POST['is_active']) ? 1 : 0;

// Handle Image Upload
$imagePath = null;
if (isset($_FILES['location_image']) && $_FILES['location_image']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['location_image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($ext, $allowed)) {
        $dir = 'uploads/locations/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $filename = uniqid('loc_') . '.' . $ext;
        if (move_uploaded_file($_FILES['location_image']['tmp_name'], $dir . $filename)) {
            $imagePath = $dir . $filename;
        }
    }
}

if ($id) {
    // Update
    $sql = "UPDATE locations SET name=?, company=?, is_active=?";
    $params = [$name, $company, $is_active];
    $types = "ssi";
    
    if ($imagePath) {
        $sql .= ", image=?";
        $params[] = $imagePath;
        $types .= "s";
    }
    
    $sql .= " WHERE id=?";
    $params[] = $id;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
} else {
    // Insert
    $sql = "INSERT INTO locations (name, company, is_active, image) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssis", $name, $company, $is_active, $imagePath);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Created successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
}
?>