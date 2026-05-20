<?php
$company = $_GET['company'] ?? '';
$sql = "SELECT * FROM locations WHERE is_active = 1";
if ($company) {
    $sql .= " AND (company = ? OR company IS NULL OR company = '')";
}
$sql .= " ORDER BY sort_order ASC, name ASC";
$stmt = $conn->prepare($sql);
if ($company) {
    $stmt->bind_param("s", $company);
}
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode(['status' => 'success', 'data' => $data]);
?>
