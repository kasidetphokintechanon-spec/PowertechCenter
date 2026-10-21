<?php
// api/actions/setup/getSetupAssetCodes.php
// คืนค่าสถานะ Setup Checklist ล่าสุดของทุก asset_code (1 query เดียว ไม่ query ทีละแถวใน list ทรัพย์สิน)
if (!isset($conn)) { http_response_code(500); echo json_encode(['status'=>'error','message'=>'No DB']); exit; }
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

$sql = "SELECT sr.asset_code, sr.status
        FROM setup_records sr
        INNER JOIN (
            SELECT asset_code, MAX(id) AS max_id
            FROM setup_records
            WHERE asset_code != ''
            GROUP BY asset_code
        ) latest ON latest.asset_code = sr.asset_code AND latest.max_id = sr.id";

$res = $conn->query($sql);
$map = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $map[$row['asset_code']] = $row['status'];
    }
}

echo json_encode(['status'=>'success','records'=>$map]);
