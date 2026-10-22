<?php
// api/actions/loan/loan_catalog.php — เครื่องให้ยืม + ช่วงที่ถูกจอง 30 วันข้างหน้า (ใช้วาดแถบว่าง/ไม่ว่าง)
// เห็นเครื่องของบริษัทตัวเอง + บริษัทที่ตั้งกติกาให้ยืมข้ามได้ (loan_company_rules) · admin เห็นทุกบริษัท
_initLoanTables($conn);
$role = $_SESSION['role'] ?? 'user';
$myCo = (string)($_SESSION['company'] ?? '');

$cos = null;
if ($role !== 'admin' && $myCo !== '') {
    $cos = [$myCo];
    $st = $conn->prepare("SELECT to_company FROM loan_company_rules WHERE from_company = ? AND is_allowed = 1");
    if ($st) { $st->bind_param('s', $myCo); $st->execute(); foreach ($st->get_result()->fetch_all() as $r) $cos[] = $r[0]; }
}

$sql = "SELECT a.id, a.equipment_id, a.name, a.brand, a.model, a.company, a.status, a.image_url, a.accessories, a.spec_details, a.location,
            COALESCE(c.cat_name, 'อื่น ๆ') AS category
        FROM it_assets a LEFT JOIN asset_categories c ON c.cat_id = a.asset_category
        WHERE a.is_loanable = 1 AND a.status NOT IN ('เลิกใช้งาน','จำหน่ายแล้ว','สูญหาย','ไม่ใช้งาน')";
if ($cos) $sql .= " AND a.company IN (" . implode(',', array_fill(0, count($cos), '?')) . ")";
$sql .= " ORDER BY category, a.name";
$st = $conn->prepare($sql);
if ($cos) $st->bind_param(str_repeat('s', count($cos)), ...$cos);
$st->execute();
$items = $st->get_result()->fetch_all(MYSQLI_ASSOC);

// ช่วงที่ถูกจอง/ยืมอยู่ — โชว์แค่ว่า "ไม่ว่าง" ไม่โชว์ชื่อคนอื่น (ยกเว้นของตัวเอง)
$book = [];
$r = $conn->query("SELECT asset_id, start_date, end_date, status, borrower_id FROM it_loan_requests
    WHERE status IN ('requested','approved','active') AND COALESCE(end_date, '9999-12-31') >= CURDATE()");
foreach ($r->fetch_all(MYSQLI_ASSOC) as $b) {
    $book[(int)$b['asset_id']][] = ['from' => $b['start_date'], 'to' => $b['end_date'], 'status' => $b['status'], 'mine' => (string)$b['borrower_id'] === (string)($_SESSION['user_id'] ?? '')];
}

$norm = function ($v) {
    $v = trim((string)$v); if ($v === '') return '';
    $p = ltrim(parse_url($v, PHP_URL_PATH) ?: $v, '/'); $i = strpos($p, 'uploads/');
    return $i !== false ? substr($p, $i) : '';
};
foreach ($items as &$it) {
    $it['id'] = (int)$it['id'];
    $it['accessories'] = json_decode($it['accessories'] ?: '[]', true) ?: [];
    $it['image_url'] = $norm($it['image_url']);
    $it['bookings'] = $book[$it['id']] ?? [];
    // เครื่องเสีย/รอซ่อมยังโชว์ แต่ยืมไม่ได้
    $it['blocked'] = in_array($it['status'], ['ชำรุด', 'รอซ่อม', 'ส่งซ่อม'], true);
}
unset($it);

echo json_encode(['status' => 'success', 'items' => $items, 'today' => date('Y-m-d')], JSON_UNESCAPED_UNICODE);
