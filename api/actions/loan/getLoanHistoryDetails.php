<?php
// api/actions/getLoanHistoryDetails.php
$history_id = $_GET['id'] ?? 0;
if (!$history_id) {
    throw new Exception("History ID is required.");
}

$stmt = $conn->prepare("
    SELECT 
        h.*, 
        a.image_url as asset_image_url
    FROM 
        it_loan_history h
    LEFT JOIN 
        it_assets a ON h.equipment_id = a.equipment_id
    WHERE 
        h.history_id = ?
");
if (!$stmt) throw new Exception("Prepare failed (getLoanHistoryDetails): " . $conn->error);

$stmt->bind_param("i", $history_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (is_array($data)) {
    $v = trim((string)($data['asset_image_url'] ?? ''));
    if ($v !== '') {
        if (strpos($v, 'uploads/') === 0) {
            $data['asset_image_url'] = $v;
        } else {
            $path = parse_url($v, PHP_URL_PATH);
            if (!$path) $path = $v;
            $path = ltrim($path, '/');
            $pos = strpos($path, 'uploads/');
            if ($pos !== false) $data['asset_image_url'] = substr($path, $pos);
        }
    }
}

echo json_encode(['status' => 'success', 'data' => $data]);
?>
