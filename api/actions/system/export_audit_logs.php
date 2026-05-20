<?php
// api/actions/export_audit_logs.php
$search = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$sql = "SELECT timestamp, user_name, event_type, asset_db_id, details FROM asset_history WHERE 1=1";
$params = [];
$types = "";

if ($search) {
    $sql .= " AND (user_name LIKE ? OR event_type LIKE ? OR details LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
    $types = "sss";
}
if ($start_date) {
    $sql .= " AND timestamp >= ?";
    $params[] = $start_date . " 00:00:00";
    $types .= "s";
}
if ($end_date) {
    $sql .= " AND timestamp <= ?";
    $params[] = $end_date . " 23:59:59";
    $types .= "s";
}
$sql .= " ORDER BY timestamp DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=audit_logs_' . date('Y-m-d_H-i') . '.csv');
$output = fopen('php://output', 'w');
fputs($output, "\xEF\xBB\xBF");
fputcsv($output, ['Timestamp', 'User', 'Action', 'Target ID', 'Details']);
while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}
fclose($output);
exit;
?>