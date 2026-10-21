<?php
// api/actions/get_asset_history.php
// ไม่ส่ง limit → คืนเป็น array ทั้งหมด (admin.html ใช้แบบนี้)
// ส่ง limit/offset → คืน {status, history, total} แบบแบ่งหน้า และไม่รวม "เปิดดู" (asset_scan.html — เดิมได้ array เลยกด "โหลดเพิ่ม" ไม่ได้)

$id = (int)($_GET['id'] ?? 0);

if (isset($_GET['limit'])) {
    $limit  = max(1, min(50, (int)$_GET['limit']));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $c = $conn->prepare("SELECT COUNT(*) AS n FROM asset_history WHERE asset_db_id = ? AND event_type <> 'ACCESS'");
    $c->bind_param("i", $id);
    $c->execute();
    $total = (int)$c->get_result()->fetch_assoc()['n'];
    $stmt = $conn->prepare("SELECT history_id, event_type, details, user_name, timestamp FROM asset_history
                            WHERE asset_db_id = ? AND event_type <> 'ACCESS' ORDER BY timestamp DESC, history_id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $id, $limit, $offset);
    $stmt->execute();
    echo json_encode(['status' => 'success', 'history' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'total' => $total], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "SELECT * FROM asset_history WHERE asset_db_id = ? ORDER BY timestamp DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = [];
while ($row = $result->fetch_assoc()) $data[] = $row;
echo json_encode($data);
?>
