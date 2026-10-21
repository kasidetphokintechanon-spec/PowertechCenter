<?php
// api/actions/server_check/get_holidays.php
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? 0); // 0 = ทั้งปี

if ($month) {
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to   = date('Y-m-t', strtotime($from));
    $rows = $conn->query("SELECT id, holiday_date, name, company FROM company_holidays
                          WHERE holiday_date BETWEEN '$from' AND '$to'
                          ORDER BY holiday_date")->fetch_all(MYSQLI_ASSOC);
} else {
    $rows = $conn->query("SELECT id, holiday_date, name, company FROM company_holidays
                          WHERE YEAR(holiday_date) = $year
                          ORDER BY holiday_date")->fetch_all(MYSQLI_ASSOC);
}

// Return as flat date→name map + full list
$map = [];
foreach ($rows as $r) $map[$r['holiday_date']] = $r['name'];

echo json_encode(['status' => 'success', 'holidays' => $rows, 'map' => $map]);
