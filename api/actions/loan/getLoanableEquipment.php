<?php
// api/actions/getLoanableEquipment.php
$sql = "SELECT 
            a.id as db_id,
            a.equipment_id as 'ID อุปกรณ์',
            a.name as 'ชื่ออุปกรณ์',
            c.cat_name as 'หมวดหมู่',
            a.serial_number as 'Serial Number',
            a.status as 'สถานะ',
            a.image_url as 'รูปภาพ (URL)',
            a.company,
            a.borrower as 'ผู้ยืม',
            a.spec_details as 'รายละเอียด',
            a.brand,
            a.model,
            a.location,
            a.accessories,
            a.is_loanable
        FROM it_assets a
        LEFT JOIN asset_categories c ON a.asset_category = c.cat_id
        WHERE a.is_loanable = 1 ORDER BY a.id DESC";

$result = $conn->query($sql);
$data = [];
if($result) {
    while ($row = $result->fetch_assoc()) {
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
        $row['accessories'] = json_decode($row['accessories'] ?? '[]', true);
        if (isset($row['รูปภาพ (URL)'])) $row['รูปภาพ (URL)'] = $normalizeUploadPath($row['รูปภาพ (URL)']);
        $data[] = $row;
    }
}
echo json_encode($data);
?>
