<?php
// api/actions/getEquipmentData.php

// Read-only on $_SESSION. admin.html fires this concurrently with several other
// data-key fetches on every load — release the session lock immediately so those
// requests run in parallel instead of queuing behind PHP's session lock.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

// auto-migrate depreciation columns before querying them
$_chk = $conn->query("SHOW COLUMNS FROM asset_categories LIKE 'default_useful_life'");
if ($_chk && $_chk->num_rows === 0) {
    $conn->query("ALTER TABLE asset_categories ADD COLUMN default_useful_life TINYINT UNSIGNED DEFAULT NULL");
}
$_chk2 = $conn->query("SHOW COLUMNS FROM it_assets LIKE 'useful_life_override'");
if ($_chk2 && $_chk2->num_rows === 0) {
    $conn->query("ALTER TABLE it_assets ADD COLUMN useful_life_override TINYINT UNSIGNED DEFAULT NULL");
}

// ดึงข้อมูลพัสดุ
$sql = "SELECT
            a.*,
            a.id as db_id,
            a.purchase_date as 'วันที่ซื้อ',
            a.equipment_id as 'ID อุปกรณ์',
            a.name as 'ชื่ออุปกรณ์',
            a.serial_number as 'Serial Number',
            c.cat_name as 'หมวดหมู่',
            c.default_useful_life,
            d.dept_name as owning_department_name,
            a.status as 'สถานะ',
            a.image_url as 'รูปภาพ (URL)',
            a.parent_id,
            p.equipment_id as parent_equipment_id,
            p.name as parent_name
        FROM it_assets a
        LEFT JOIN asset_categories c ON a.asset_category = c.cat_id
        LEFT JOIN departments d ON a.owning_department = d.dept_id
        LEFT JOIN it_assets p ON a.parent_id = p.id";

$role = $_SESSION['role'] ?? 'guest';
$sessionCompany = $_SESSION['company'] ?? '';
$stmt = null;

if ($role !== 'admin' && !empty($sessionCompany)) {
    $sql .= " WHERE a.company = ? ORDER BY a.id DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) $stmt->bind_param("s", $sessionCompany);
} else {
    $sql .= " ORDER BY a.id DESC";
    $stmt = $conn->prepare($sql);
}

$data = [];
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $normalizeUploadPath = function($value) {
            $v = trim((string)($value ?? ''));
            if ($v === '') return '';
            if (strpos($v, 'uploads/') === 0) return $v;
            $path = parse_url($v, PHP_URL_PATH);
            if (!$path) $path = $v;
            $path = ltrim($path, '/');
            $pos = strpos($path, 'uploads/');
            if ($pos !== false) return substr($path, $pos);
            return $v;
        };
        while ($row = $result->fetch_assoc()) {
            $row['accessories'] = json_decode($row['accessories'] ?? '[]', true);
            if (isset($row['image_url'])) $row['image_url'] = $normalizeUploadPath($row['image_url']);
            if (isset($row['รูปภาพ (URL)'])) $row['รูปภาพ (URL)'] = $normalizeUploadPath($row['รูปภาพ (URL)']);
            if (isset($row['images']) && !empty($row['images'])) {
                $imgs = json_decode($row['images'], true);
                if (is_array($imgs)) {
                    $imgs = array_values(array_filter(array_map($normalizeUploadPath, $imgs), function($v) { return $v !== ''; }));
                    $row['images'] = json_encode($imgs, JSON_UNESCAPED_UNICODE);
                }
            }
            $data[] = $row;
        }
    }
}
echo json_encode($data);
?>
