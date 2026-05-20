<?php
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? 'guest'), ['admin', 'staff'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}
if (function_exists('hasPermission') && !hasPermission($conn, 'asset.manage') && !hasPermission($conn, 'admin.access_admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Insufficient permission']);
    exit;
}

$id = $_POST['id'] ?? '';
$name = $_POST['name'] ?? '';
$company = $_POST['company'] ?? '';
$is_active = isset($_POST['is_active']) ? 1 : 0;
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
    $beforeJson = '';
    $beforeStmt = $conn->prepare("SELECT id, name, company, is_active, image FROM locations WHERE id = ? LIMIT 1");
    if ($beforeStmt) {
        $beforeStmt->bind_param("i", $id);
        $beforeStmt->execute();
        $beforeRes = $beforeStmt->get_result();
        if ($beforeRes && $beforeRow = $beforeRes->fetch_assoc()) {
            $beforeJson = json_encode($beforeRow, JSON_UNESCAPED_UNICODE);
        }
    }

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
        if (function_exists('logAssetHistory')) {
            $afterData = ['id' => intval($id), 'name' => $name, 'company' => $company, 'is_active' => intval($is_active), 'image' => $imagePath];
            logAssetHistory($conn, 0, 'LOCATION_UPDATE', 'before=' . $beforeJson . ' | after=' . json_encode($afterData, JSON_UNESCAPED_UNICODE));
        }
        echo json_encode(['status' => 'success', 'message' => 'Updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
} else {
    $sql = "INSERT INTO locations (name, company, is_active, image) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssis", $name, $company, $is_active, $imagePath);
    if ($stmt->execute()) {
        if (function_exists('logAssetHistory')) {
            $created = ['id' => intval($stmt->insert_id), 'name' => $name, 'company' => $company, 'is_active' => intval($is_active), 'image' => $imagePath];
            logAssetHistory($conn, 0, 'LOCATION_CREATE', json_encode($created, JSON_UNESCAPED_UNICODE));
        }
        echo json_encode(['status' => 'success', 'message' => 'Created successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
}
?>
