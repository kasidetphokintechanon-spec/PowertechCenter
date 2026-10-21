<?php
// api/actions/asset/get_my_assets.php
// Returns assets assigned to the logged-in user (borrower_id = session user_id)

$userId = $_SESSION['user_id'] ?? '';
if ($userId === '') {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT equipment_id, asset_id, serial_number, name, status, image_url
     FROM it_assets
     WHERE borrower_id = ?
       AND status NOT IN ('ไม่ใช้งาน','จำหน่ายแล้ว','สูญหาย')
     ORDER BY status = 'ส่งซ่อม' ASC, name ASC
     LIMIT 20"
);
if (!$stmt) { echo json_encode([]); exit; }
$stmt->bind_param('s', $userId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($rows as &$row) {
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
