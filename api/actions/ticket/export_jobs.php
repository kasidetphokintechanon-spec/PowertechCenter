<?php
// api/actions/export_jobs.php
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$company = $_GET['company'] ?? '';

$sql = "SELECT * FROM job_tickets WHERE 1=1";
$params = [];
$types = "";

if (!empty($company) && $company !== 'all') {
    $sql .= " AND company = ?";
    $params[] = $company;
    $types .= "s";
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=job_tickets_export_' . date('Y-m-d_H-i') . '.csv');

$output = fopen('php://output', 'w');
fputs($output, "\xEF\xBB\xBF");
fputcsv($output, ['Job ID', 'Company', 'Department', 'Building', 'Job Name', 'Responsible', 'Deadline', 'Status', 'Created By', 'Created At']);

while ($row = $result->fetch_assoc()) {
    $status = 'Pending';
    if ($row['is_done']) $status = 'Done';
    elseif ($row['is_approve']) $status = 'Approved';
    elseif ($row['is_send']) $status = 'Sent';
    elseif ($row['is_doing']) $status = 'Doing';
    elseif ($row['is_receive']) $status = 'Received';

    fputcsv($output, [
        $row['id'],
        $row['company'],
        $row['department'],
        $row['building'],
        $row['job_name'],
        $row['responsible'],
        $row['deadline'],
        $status,
        $row['created_by_name'],
        $row['last_update']
    ]);
}
fclose($output);
exit;
?>