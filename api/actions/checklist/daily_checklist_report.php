<?php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$role = $_SESSION['role'] ?? 'guest';
$sessionCompany = $_SESSION['company'] ?? '';

if (!in_array($role, ['admin', 'staff'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');
$company = $_GET['company'] ?? $sessionCompany;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
    $start = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    $end = date('Y-m-d');
}

$allowedCompanies = ['', 'PTA', 'PT4', 'PTE', 'all'];
if (!in_array($company, $allowedCompanies, true)) {
    $company = $sessionCompany;
}

$params = [$start, $end];
$types = 'ss';

$companyFilterSql = '';
if ($company && $company !== 'all') {
    $companyFilterSql = "AND i.company = ?";
    $params[] = $company;
    $types .= 's';
}

$sql = "SELECT d.checklist_date,
               i.company,
               COUNT(DISTINCT i.id) AS total_items,
               COUNT(DISTINCT l.checklist_item_id) AS checked_items
        FROM daily_checklist_items i
        JOIN (
            SELECT DATE(?) AS checklist_date
        ) d ON 1=1
        LEFT JOIN daily_checklist_logs l
            ON l.checklist_item_id = i.id
           AND l.checklist_date = d.checklist_date
        WHERE i.is_active = 1
          $companyFilterSql";

$days = [];
$current = new DateTime($start);
$endDate = new DateTime($end);
while ($current <= $endDate) {
    $days[] = $current->format('Y-m-d');
    $current->modify('+1 day');
}

$results = [];

foreach ($days as $day) {
    $paramsDay = [$day, $day];
    $typesDay = 'ss';
    $companyFilterSqlDay = '';
    $paramsExtra = [];
    $typesExtra = '';
    if ($company && $company !== 'all') {
        $companyFilterSqlDay = "AND i.company = ?";
        $paramsExtra[] = $company;
        $typesExtra .= 's';
    }

    $sqlDay = "SELECT '$day' AS checklist_date,
                      i.company,
                      COUNT(DISTINCT i.id) AS total_items,
                      COUNT(DISTINCT l.checklist_item_id) AS checked_items
               FROM daily_checklist_items i
               LEFT JOIN daily_checklist_logs l
                 ON l.checklist_item_id = i.id
                AND l.checklist_date = ?
               WHERE i.is_active = 1
                 $companyFilterSqlDay";

    $stmt = $conn->prepare($sqlDay);
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
        exit;
    }

    $bindTypes = 's' . $typesExtra;
    $bindParams = array_merge([$day], $paramsExtra);
    $stmt->bind_param($bindTypes, ...$bindParams);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();

    $totalItems = (int)($row['total_items'] ?? 0);
    $checkedItems = (int)($row['checked_items'] ?? 0);
    $percent = $totalItems > 0 ? round(($checkedItems / $totalItems) * 100, 2) : 0;

    $results[] = [
        'date' => $day,
        'company' => $company && $company !== 'all' ? $company : ($row['company'] ?? ''),
        'total_items' => $totalItems,
        'checked_items' => $checkedItems,
        'percent' => $percent
    ];
}

echo json_encode([
    'status' => 'success',
    'start' => $start,
    'end' => $end,
    'company' => $company,
    'summary' => $results
]);

