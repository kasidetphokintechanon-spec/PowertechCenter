<?php
// api/actions/search_chat_directory.php

$term = $_GET['term'] ?? '';
$my_id = $_SESSION['user_id'] ?? '';

if (strlen($term) < 2) {
    echo json_encode(['status' => 'success', 'users' => []]);
    exit;
}

$searchTerm = "%" . $conn->real_escape_string($term) . "%";

$sql = "SELECT e.id, e.name, e.name_th, e.image, ea.department, ea.company
        FROM employees e
        JOIN employee_assignments ea ON e.id = ea.employee_id
        WHERE e.employment_status = 'active' AND ea.is_primary = 1
        AND (e.name LIKE ? OR e.name_th LIKE ? OR ea.department LIKE ?)
        AND e.id != ?
        LIMIT 20";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $my_id);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(['status' => 'success', 'users' => $users]);
?>