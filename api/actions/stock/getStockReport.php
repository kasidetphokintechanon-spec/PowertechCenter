<?php
// api/actions/stock/getStockReport.php — aggregated stats for dashboard
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");

$company    = $_GET['company']    ?? 'All';
$stock_type = $_GET['stock_type'] ?? '';
$month      = $_GET['month']      ?? '';
$department = $_GET['department'] ?? '';

$thaiMonths = ['01'=>'ม.ค.','02'=>'ก.พ.','03'=>'มี.ค.','04'=>'เม.ย.',
               '05'=>'พ.ค.','06'=>'มิ.ย.','07'=>'ก.ค.','08'=>'ส.ค.',
               '09'=>'ก.ย.','10'=>'ต.ค.','11'=>'พ.ย.','12'=>'ธ.ค.'];

// Current period dates
if ($month) {
    $endYear = (int)substr($month, 0, 4);
    $endMon  = (int)substr($month, 5, 2);
    $lastDay = date('t', mktime(0, 0, 0, $endMon, 1, $endYear));
    $curFrom = sprintf('%04d-%02d-01 00:00:00', $endYear, $endMon);
    $curTo   = sprintf('%04d-%02d-%02d 23:59:59', $endYear, $endMon, $lastDay);
    $prevMon  = $endMon - 1; $prevYear = $endYear;
    if ($prevMon < 1) { $prevMon = 12; $prevYear--; }
    $prevLastDay = date('t', mktime(0, 0, 0, $prevMon, 1, $prevYear));
    $prevFrom = sprintf('%04d-%02d-01 00:00:00', $prevYear, $prevMon);
    $prevTo   = sprintf('%04d-%02d-%02d 23:59:59', $prevYear, $prevMon, $prevLastDay);
} else {
    $endYear = (int)date('Y'); $endMon = (int)date('m');
    $curFrom = $curTo = $prevFrom = $prevTo = '';
}

// ── Helper ──
function addTxFilters($company, $stock_type, $df, $dt, &$p, &$t) {
    $w = [];
    if ($company !== 'All') { $w[] = "st.company = ?"; $p[] = $company; $t .= 's'; }
    if ($stock_type)         { $w[] = "si.type = ?";   $p[] = $stock_type; $t .= 's'; }
    if ($df)                 { $w[] = "st.transaction_date >= ?"; $p[] = $df; $t .= 's'; }
    if ($dt)                 { $w[] = "st.transaction_date <= ?"; $p[] = $dt; $t .= 's'; }
    return $w;
}

// ── KPI current ──
$p = []; $t = '';
$w = addTxFilters($company, $stock_type, $curFrom, $curTo, $p, $t);
$wh = $w ? 'WHERE ' . implode(' AND ', $w) : '';
$stmt = $conn->prepare("SELECT
    COALESCE(SUM(CASE WHEN st.type='issued'   THEN st.quantity*si.price_per_unit ELSE 0 END),0) AS issued_value,
    COALESCE(SUM(CASE WHEN st.type='received' THEN st.quantity ELSE 0 END),0) AS received_count,
    COALESCE(SUM(CASE WHEN st.type='received' THEN st.quantity*si.price_per_unit ELSE 0 END),0) AS received_value,
    COALESCE(SUM(CASE WHEN st.type='issued'   THEN st.quantity ELSE 0 END),0) AS issued_count
    FROM stock_transactions st JOIN stock_items si ON si.id=st.item_id $wh");
if ($p) $stmt->bind_param($t, ...$p);
$stmt->execute();
$kpiCur = $stmt->get_result()->fetch_assoc();

// ── KPI previous ──
$kpiPrev = ['issued_value'=>0,'received_count'=>0,'received_value'=>0,'issued_count'=>0];
if ($month) {
    $pp = []; $pt = '';
    $wp = addTxFilters($company, $stock_type, $prevFrom, $prevTo, $pp, $pt);
    $whp = $wp ? 'WHERE ' . implode(' AND ', $wp) : '';
    $stmt2 = $conn->prepare("SELECT
        COALESCE(SUM(CASE WHEN st.type='issued'   THEN st.quantity*si.price_per_unit ELSE 0 END),0) AS issued_value,
        COALESCE(SUM(CASE WHEN st.type='received' THEN st.quantity ELSE 0 END),0) AS received_count,
        COALESCE(SUM(CASE WHEN st.type='received' THEN st.quantity*si.price_per_unit ELSE 0 END),0) AS received_value,
        COALESCE(SUM(CASE WHEN st.type='issued'   THEN st.quantity ELSE 0 END),0) AS issued_count
        FROM stock_transactions st JOIN stock_items si ON si.id=st.item_id $whp");
    if ($pp) $stmt2->bind_param($pt, ...$pp);
    $stmt2->execute();
    $kpiPrev = $stmt2->get_result()->fetch_assoc();
}

// ── Current inventory value (balance × price all active items) ──
$svP = []; $svT = '';
$stJoinCoSv = '';
if ($company !== 'All') { $stJoinCoSv = "AND st2.company = ?"; $svP[] = $company; $svT .= 's'; }
$svW = ["si.is_active = 1"];
if ($company !== 'All') { $svW[] = "si.company = ?"; $svP[] = $company; $svT .= 's'; }
if ($stock_type)         { $svW[] = "si.type = ?";   $svP[] = $stock_type; $svT .= 's'; }
$stmtSv = $conn->prepare("SELECT COALESCE(SUM(bal * price_per_unit),0) AS stock_value FROM (
    SELECT si.price_per_unit,
        COALESCE(SUM(CASE WHEN st2.type='received' THEN st2.quantity
                          WHEN st2.type='issued'   THEN -st2.quantity
                          WHEN st2.type='adjusted' THEN st2.quantity
                          WHEN st2.type='returned' THEN st2.quantity END),0) AS bal
    FROM stock_items si
    LEFT JOIN stock_transactions st2 ON st2.item_id=si.id $stJoinCoSv
    WHERE " . implode(' AND ', $svW) . "
    GROUP BY si.id, si.price_per_unit
    HAVING bal > 0
) AS sub");
if ($svP) $stmtSv->bind_param($svT, ...$svP);
$stmtSv->execute();
$stockValue = (float)($stmtSv->get_result()->fetch_assoc()['stock_value'] ?? 0);

// ── Requests KPI (with date filter for totals) ──
$rW = []; $rP = []; $rT = '';
if ($company !== 'All') { $rW[] = "company = ?"; $rP[] = $company; $rT .= 's'; }
if ($stock_type)         { $rW[] = "stock_type = ?"; $rP[] = $stock_type; $rT .= 's'; }
if ($curFrom)            { $rW[] = "request_date >= ?"; $rP[] = $curFrom; $rT .= 's'; }
if ($curTo)              { $rW[] = "request_date <= ?"; $rP[] = $curTo; $rT .= 's'; }
$rWh = $rW ? 'WHERE ' . implode(' AND ', $rW) : '';
$stmtRq = $conn->prepare("SELECT COUNT(*) AS total,
    COALESCE(SUM(status='approved'),0) AS approved_count,
    COALESCE(SUM(status='rejected'),0) AS rejected_count
    FROM stock_requests $rWh");
if ($rP) $stmtRq->bind_param($rT, ...$rP);
$stmtRq->execute();
$rqData = $stmtRq->get_result()->fetch_assoc();

// pending_count: always count ALL pending (no date filter) so the nav badge is accurate
$pW = []; $pP = []; $pT = '';
if ($company !== 'All') { $pW[] = "company = ?"; $pP[] = $company; $pT .= 's'; }
if ($stock_type)         { $pW[] = "stock_type = ?"; $pP[] = $stock_type; $pT .= 's'; }
$pW[] = "status = 'pending'";
$pWh = 'WHERE ' . implode(' AND ', $pW);
$stmtPend = $conn->prepare("SELECT COUNT(*) AS pending_count FROM stock_requests $pWh");
if ($pP) $stmtPend->bind_param($pT, ...$pP);
$stmtPend->execute();
$pendData = $stmtPend->get_result()->fetch_assoc();

// ── Build 6-month window ──
$chartMonths = [];
for ($i = 5; $i >= 0; $i--) {
    $y = $endYear; $m = $endMon - $i;
    while ($m < 1) { $m += 12; $y--; }
    $chartMonths[] = sprintf('%04d-%02d', $y, $m);
}
$chart6From = $chartMonths[0] . '-01 00:00:00';
$lastDayEnd  = date('t', mktime(0, 0, 0, $endMon, 1, $endYear));
$chart6To   = $chartMonths[5] . '-' . $lastDayEnd . ' 23:59:59';

$cP = [$chart6From, $chart6To]; $cT = 'ss';
$cW = ["st.transaction_date >= ?", "st.transaction_date <= ?"];
if ($company !== 'All') { $cW[] = "st.company = ?"; $cP[] = $company; $cT .= 's'; }
if ($stock_type)         { $cW[] = "si.type = ?";   $cP[] = $stock_type; $cT .= 's'; }
$stmtCh = $conn->prepare("SELECT DATE_FORMAT(st.transaction_date,'%Y-%m') AS month,
    COALESCE(SUM(CASE WHEN st.type='received' THEN st.quantity ELSE 0 END),0) AS received_qty,
    COALESCE(SUM(CASE WHEN st.type='issued'   THEN st.quantity ELSE 0 END),0) AS issued_qty,
    COALESCE(SUM(CASE WHEN st.type='issued'   THEN st.quantity*si.price_per_unit ELSE 0 END),0) AS issued_value
    FROM stock_transactions st JOIN stock_items si ON si.id=st.item_id
    WHERE " . implode(' AND ', $cW) . "
    GROUP BY DATE_FORMAT(st.transaction_date,'%Y-%m') ORDER BY month ASC");
$stmtCh->bind_param($cT, ...$cP);
$stmtCh->execute();
$chartMap = [];
foreach ($stmtCh->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $chartMap[$r['month']] = $r;

// Build monthly array (6 months)
$monthly = [];
foreach ($chartMonths as $ym) {
    $mon2 = substr($ym, 5, 2); $yr4 = (int)substr($ym, 0, 4);
    $be2  = substr((string)($yr4 + 543), 2, 2);
    $r    = $chartMap[$ym] ?? [];
    $monthly[] = [
        'month'       => $ym,
        'label'       => ($thaiMonths[$mon2] ?? $mon2) . ' ' . $be2,
        'received_qty'=> (int)($r['received_qty'] ?? 0),
        'issued_qty'  => (int)($r['issued_qty']   ?? 0),
        'issued_value'=> (float)($r['issued_value']?? 0),
    ];
}

// Summary: last 4 months (from the same chart data)
$summary4 = [];
for ($i = 3; $i >= 0; $i--) {
    $y = $endYear; $m = $endMon - $i;
    while ($m < 1) { $m += 12; $y--; }
    $ym   = sprintf('%04d-%02d', $y, $m);
    $mon2 = substr($ym, 5, 2);
    $be2  = substr((string)($y + 543), 2, 2);
    $r    = $chartMap[$ym] ?? [];
    $summary4[] = [
        'label'    => ($thaiMonths[$mon2] ?? $mon2) . ' ' . $be2,
        'received' => (int)($r['received_qty'] ?? 0),
        'issued'   => (int)($r['issued_qty']   ?? 0),
        'value'    => (float)($r['issued_value']?? 0),
    ];
}

// ── Top 5 items ──
$tP = []; $tT = '';
$tW = ["st.type = 'issued'"];
if ($company !== 'All') { $tW[] = "st.company = ?"; $tP[] = $company; $tT .= 's'; }
if ($stock_type)         { $tW[] = "si.type = ?";   $tP[] = $stock_type; $tT .= 's'; }
if ($curFrom)            { $tW[] = "st.transaction_date >= ?"; $tP[] = $curFrom; $tT .= 's'; }
if ($curTo)              { $tW[] = "st.transaction_date <= ?"; $tP[] = $curTo;   $tT .= 's'; }
$stmtTop = $conn->prepare("SELECT si.name, si.unit, SUM(st.quantity) AS total_issued
    FROM stock_transactions st JOIN stock_items si ON si.id=st.item_id
    WHERE " . implode(' AND ', $tW) . "
    GROUP BY si.id ORDER BY total_issued DESC LIMIT 5");
if ($tP) $stmtTop->bind_param($tT, ...$tP);
$stmtTop->execute();
$topItems = $stmtTop->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Department breakdown ──
$dP = []; $dT = '';
$dW = ["sr.status = 'approved'", "sr.department IS NOT NULL", "sr.department != ''"];
if ($company !== 'All') { $dW[] = "sr.company = ?"; $dP[] = $company; $dT .= 's'; }
if ($stock_type)         { $dW[] = "sr.stock_type = ?"; $dP[] = $stock_type; $dT .= 's'; }
if ($curFrom)            { $dW[] = "sr.request_date >= ?"; $dP[] = $curFrom; $dT .= 's'; }
if ($curTo)              { $dW[] = "sr.request_date <= ?"; $dP[] = $curTo; $dT .= 's'; }
$stmtDept = $conn->prepare("SELECT sr.department,
    COALESCE(SUM(COALESCE(sri.qty_approved,sri.qty_requested,0)*si.price_per_unit),0) AS total_value
    FROM stock_requests sr
    JOIN stock_request_items sri ON sri.request_id=sr.id
    JOIN stock_items si ON si.id=sri.item_id
    WHERE " . implode(' AND ', $dW) . "
    GROUP BY sr.department ORDER BY total_value DESC LIMIT 5");
if ($dP) $stmtDept->bind_param($dT, ...$dP);
$stmtDept->execute();
$departments = $stmtDept->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Company breakdown ──
$coNames  = ['PTA'=>'PowerTechAssembly','PT4'=>'PowerTech2004','PTE'=>'PowerTechEnergy'];
$coColors = ['PTA'=>'#2563eb','PT4'=>'#10b981','PTE'=>'#f59e0b'];
$cvP = []; $cvT = '';
$cvW = ["st.type = 'issued'"];
if ($stock_type) { $cvW[] = "si.type = ?";   $cvP[] = $stock_type; $cvT .= 's'; }
if ($curFrom)    { $cvW[] = "st.transaction_date >= ?"; $cvP[] = $curFrom; $cvT .= 's'; }
if ($curTo)      { $cvW[] = "st.transaction_date <= ?"; $cvP[] = $curTo;   $cvT .= 's'; }
if ($company !== 'All') { $cvW[] = "st.company = ?"; $cvP[] = $company; $cvT .= 's'; }
$stmtCo = $conn->prepare("SELECT st.company,
    COALESCE(SUM(st.quantity*si.price_per_unit),0) AS total_value
    FROM stock_transactions st JOIN stock_items si ON si.id=st.item_id
    WHERE " . implode(' AND ', $cvW) . "
    GROUP BY st.company ORDER BY total_value DESC");
if ($cvP) $stmtCo->bind_param($cvT, ...$cvP);
$stmtCo->execute();
$coRows = $stmtCo->get_result()->fetch_all(MYSQLI_ASSOC);
$totalCoVal = array_sum(array_column($coRows, 'total_value')) ?: 1;
$companies = array_map(function($r) use ($coNames, $coColors, $totalCoVal) {
    return [
        'company' => $r['company'],
        'name'    => $coNames[$r['company']] ?? $r['company'],
        'value'   => (float)$r['total_value'],
        'color'   => $coColors[$r['company']] ?? '#64748b',
        'pct'     => round($r['total_value'] / $totalCoVal * 100, 1),
    ];
}, $coRows);

// ── Low stock count ──
$lwP = []; $lwT = ''; $lwW = ["si.is_active = 1"];
if ($company !== 'All') { $lwW[] = "si.company = ?"; $lwP[] = $company; $lwT .= 's'; }
if ($stock_type)         { $lwW[] = "si.type = ?";   $lwP[] = $stock_type; $lwT .= 's'; }
$stJoinCo = ($company !== 'All') ? "AND st2.company = ?" : "";
if ($company !== 'All') { $lwP[] = $company; $lwT .= 's'; }
$stmtLow = $conn->prepare("SELECT COUNT(*) AS cnt FROM (
    SELECT si.id, si.min_stock,
        COALESCE(SUM(CASE WHEN st2.type='received' THEN st2.quantity
                          WHEN st2.type='issued'   THEN -st2.quantity
                          WHEN st2.type='adjusted' THEN st2.quantity
                          WHEN st2.type='returned' THEN st2.quantity END),0) AS bal
    FROM stock_items si
    LEFT JOIN stock_transactions st2 ON st2.item_id=si.id $stJoinCo
    WHERE " . implode(' AND ', $lwW) . "
    GROUP BY si.id, si.min_stock
    HAVING si.min_stock > 0 AND bal <= si.min_stock
) AS lq");
if ($lwP) $stmtLow->bind_param($lwT, ...$lwP);
$stmtLow->execute();
$lowCount = (int)$stmtLow->get_result()->fetch_assoc()['cnt'];

// ── Low stock items list ──
$liP = []; $liT = '';
$liW = ["si.is_active = 1"];
// JOIN param must be bound before WHERE params
$stJoinCo2 = '';
if ($company !== 'All') { $stJoinCo2 = "AND st2.company = ?"; $liP[] = $company; $liT .= 's'; }
if ($company !== 'All') { $liW[] = "si.company = ?"; $liP[] = $company; $liT .= 's'; }
if ($stock_type)         { $liW[] = "si.type = ?";   $liP[] = $stock_type; $liT .= 's'; }
$stmtLi = $conn->prepare("SELECT si.name, si.item_code, si.min_stock,
    COALESCE(SUM(CASE WHEN st2.type='received' THEN st2.quantity
                      WHEN st2.type='issued'   THEN -st2.quantity
                      WHEN st2.type='adjusted' THEN st2.quantity
                      WHEN st2.type='returned' THEN st2.quantity END),0) AS balance
    FROM stock_items si
    LEFT JOIN stock_transactions st2 ON st2.item_id=si.id $stJoinCo2
    WHERE " . implode(' AND ', $liW) . "
    GROUP BY si.id, si.name, si.item_code, si.min_stock
    HAVING si.min_stock > 0 AND balance <= si.min_stock
    ORDER BY balance ASC LIMIT 20");
if ($liP) $stmtLi->bind_param($liT, ...$liP);
$stmtLi->execute();
$lowItems = $stmtLi->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Dept list for filter dropdown ──
$dListStmt = $conn->prepare("SELECT DISTINCT department FROM stock_requests WHERE department IS NOT NULL AND department != '' ORDER BY department");
$dListStmt->execute();
$deptList = array_column($dListStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'department');

echo json_encode([
    'kpi' => [
        'stock_value'         => $stockValue,
        'issued_value'        => (float)($kpiCur['issued_value']   ?? 0),
        'issued_value_prev'   => (float)($kpiPrev['issued_value']  ?? 0),
        'received_value'      => (float)($kpiCur['received_value'] ?? 0),
        'received_value_prev' => (float)($kpiPrev['received_value']?? 0),
        'received_count'      => (int)($kpiCur['received_count']   ?? 0),
        'received_count_prev' => (int)($kpiPrev['received_count']  ?? 0),
        'issued_count'        => (int)($kpiCur['issued_count']     ?? 0),
        'issued_count_prev'   => (int)($kpiPrev['issued_count']    ?? 0),
        'total_requests'      => (int)($rqData['total']            ?? 0),
        'approved_count'      => (int)($rqData['approved_count']   ?? 0),
        'rejected_count'      => (int)($rqData['rejected_count']   ?? 0),
        'pending_count'       => (int)($pendData['pending_count']  ?? 0),
    ],
    'monthly'     => $monthly,
    'top_items'   => $topItems,
    'departments' => $departments,
    'summary'     => $summary4,
    'companies'   => $companies,
    'low_count'   => $lowCount,
    'low_items'   => $lowItems,
    'dept_list'   => $deptList,
    'generated_at'=> date('d/m/') . (date('Y') + 543) . ' ' . date('H:i'),
]);
?>
