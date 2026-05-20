<?php
// api/actions/get_bookings_by_date.php
$date = $_GET['date'] ?? date('Y-m-d');
$company = $_GET['company'] ?? '';

// Join meeting_rooms เพื่อดึงชื่อห้องและกรองบริษัทได้แม่นยำขึ้น
// กรองสถานะ Cancelled และ Rejected ออก เพื่อไม่ให้รกตาราง
$sql = "SELECT b.*, r.name as room_name, r.company as room_company, r.images as room_images 
        FROM bookings b 
        JOIN meeting_rooms r ON b.room_id = r.id 
        WHERE DATE(b.start_time) = ? AND b.status NOT IN ('Cancelled', 'Rejected')";

$params = [$date];
$types = "s";

if ($company && $company !== 'all') {
    $sql .= " AND r.company = ?";
    $params[] = $company;
    $types .= "s";
}

$sql .= " ORDER BY b.start_time ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($data);
?>