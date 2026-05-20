<?php
// api/actions/export_logs.php
$company = $_GET['company'] ?? '';
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM it_logs WHERE 1=1";
$params = [];
$types = "";

if (!empty($company)) {
    $sql .= " AND company = ?";
    $params[] = $company;
    $types .= "s";
}
if (!empty($status)) {
    $sql .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}
if (!empty($type)) {
    $sql .= " AND type = ?";
    $params[] = $type;
    $types .= "s";
}
if (!empty($search)) {
    $searchTerm = "%$search%";
    $sql .= " AND (id LIKE ? OR requester LIKE ? OR problem LIKE ? OR asset_id LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ssss";
}

$sql .= " ORDER BY date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=it_logs_export_' . date('Y-m-d_H-i') . '.csv');

$output = fopen('php://output', 'w');
fputs($output, "\xEF\xBB\xBF"); // BOM for Excel
fputcsv($output, ['Ticket ID', 'Date', 'Company', 'Department', 'Requester', 'Asset ID', 'Type', 'Problem', 'Solution', 'Status', 'Urgency', 'Serviced By', 'Cost', 'Completed Date']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['date'],
        $row['company'],
        $row['department'],
        $row['requester'],
        $row['asset_id'],
        $row['type'],
        $row['problem'],
        $row['solution'],
        $row['status'],
        $row['urgency'],
        $row['servicedBy'],
        $row['repair_cost'],
        $row['completed_date']
    ]);
}
fclose($output);
exit;
?>