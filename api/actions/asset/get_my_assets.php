<?php
// api/actions/asset/get_my_assets.php
// Returns assets assigned to the logged-in user (borrower_id = session user_id)
// รวมเครื่องที่ผู้ใช้เป็นผู้ดูแล (custodian) ด้วย — หน้าแจ้งซ่อมดึงไปให้เลือกเครื่อง

$userId = $_SESSION['user_id'] ?? '';
if ($userId === '') {
    echo json_encode([]);
    exit;
}

// + เครื่องของแผนกตัวเอง (แผนกเจ้าของ = แผนกที่ซื้อ · หรือถือครองเป็นแผนก) — หัวหน้าแจ้งซ่อมเครื่องลูกน้อง/ปริ้นเตอร์แผนกได้
//   คนใช้เครื่องเป็นอีกเรื่อง: ส่ง user_name ไปให้เลือกถูกเครื่อง · จำกัดบริษัทที่ล็อกอิน (IT ของ PTA กับ PTE เป็นคนละแผนก)
$deptName = (string)($_SESSION['primary_department'] ?? '');
$company  = (string)($_SESSION['company'] ?? '');
$stmt = $conn->prepare(
    "SELECT a.equipment_id, a.asset_id, a.serial_number, a.name, a.status, a.image_url, a.borrower AS user_name,
            (a.borrower_id = ? OR a.custodian_employee_id = ?) AS mine, a.custodian_type
     FROM it_assets a LEFT JOIN departments d ON d.dept_id = a.owning_department OR d.dept_id = a.custodian_dept_id
     WHERE a.status NOT IN ('ไม่ใช้งาน','จำหน่ายแล้ว','สูญหาย')
       AND (a.borrower_id = ? OR a.custodian_employee_id = ?
            OR (? <> '' AND d.dept_name = ? AND (? = '' OR a.company IS NULL OR a.company = '' OR a.company = ?)))
     GROUP BY a.id
     ORDER BY mine DESC, a.status = 'ส่งซ่อม' ASC, a.name ASC
     LIMIT 40"
);
if (!$stmt) { echo json_encode([]); exit; }
$stmt->bind_param('ssssssss', $userId, $userId, $userId, $userId, $deptName, $deptName, $company, $company);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($rows as &$row) {
    $row['mine'] = (int)$row['mine'] === 1;
    $v = trim((string)($row['image_url'] ?? ''));
    if ($v !== '') {
        if (strpos($v, 'uploads/') !== 0) {
            $path = ltrim(parse_url($v, PHP_URL_PATH) ?: $v, '/');
            $pos  = strpos($path, 'uploads/');
            $row['image_url'] = $pos !== false ? substr($path, $pos) : '';
        }
    }
}
unset($row);

echo json_encode($rows);
