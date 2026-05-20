<?php
// api/actions/search_assets.php

$term = $_GET['term'] ?? '';
if (strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

$searchTerm = "%" . $conn->real_escape_string($term) . "%";

$sql = "SELECT equipment_id, asset_id, serial_number, name, borrower, borrower_id, image_url 
        FROM it_assets 
        WHERE equipment_id LIKE ? 
           OR asset_id LIKE ? 
           OR serial_number LIKE ?
           OR name LIKE ?
           OR borrower LIKE ?
        LIMIT 10";

$stmt = $conn->prepare($sql);
if (!$stmt) throw new Exception("Prepare failed (Search Assets): " . $conn->error);
$stmt->bind_param("ssssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($data as &$row) {
    $v = trim((string)($row['image_url'] ?? ''));
    if ($v !== '') {
        if (strpos($v, 'uploads/') === 0) {
            $row['image_url'] = $v;
        } else {
            $path = parse_url($v, PHP_URL_PATH);
            if (!$path) $path = $v;
            $path = ltrim($path, '/');
            $pos = strpos($path, 'uploads/');
            if ($pos !== false) $row['image_url'] = substr($path, $pos);
        }
    }
}
unset($row);
echo json_encode($data);
?>
