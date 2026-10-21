<?php
// Doesn't touch $_SESSION. admin.html fires this concurrently with several other
// data-key fetches on every load — release the session lock immediately so those
// requests run in parallel instead of queuing behind PHP's session lock.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

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
