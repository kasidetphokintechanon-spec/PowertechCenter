<?php
// api/actions/asset/create_repair.php
// Create a repair log entry in it_logs

$asset_id    = trim($_POST['asset_id']    ?? '');
$problem     = trim($_POST['problem']     ?? '');
$solution    = trim($_POST['solution']    ?? '');
$status      = trim($_POST['status']      ?? 'Pending');
$servicedBy  = trim($_POST['servicedBy']  ?? ($_SESSION['display_name'] ?? 'IT'));
$dateStr     = trim($_POST['date']        ?? date('Y-m-d'));

if (!$asset_id || !$problem) {
    throw new Exception("กรุณาระบุ Asset ID และอาการเสีย");
}

// Validate status
$validStatuses = ['Pending','In Progress','Waiting for Parts','Completed','Cancelled'];
if (!in_array($status, $validStatuses)) $status = 'Pending';

// Generate ticket ID: {company}-RPR{ymd}-{nn}
$company   = $_SESSION['company'] ?? 'PTE';
$requester = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'IT Admin';
$prefix    = strtoupper($company) . '-RPR' . date('ymd') . '-';
$idStmt    = $conn->prepare("SELECT id FROM it_logs WHERE id LIKE ? ORDER BY id DESC LIMIT 1");
if ($idStmt) {
    $searchPfx = $prefix . '%';
    $idStmt->bind_param('s', $searchPfx);
    $idStmt->execute();
    $lastRow = $idStmt->get_result()->fetch_assoc();
    $nextNum = $lastRow ? intval(end(explode('-', $lastRow['id']))) + 1 : 1;
} else {
    $nextNum = 1;
}
$ticketId = $prefix . str_pad($nextNum, 2, '0', STR_PAD_LEFT);
$today    = date('Y-m-d');

$stmt = $conn->prepare(
    "INSERT INTO it_logs (id, asset_id, date, problem, solution, status, servicedBy, requester, type, company, urgency)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ASSET_REPAIR', ?, 'ปกติ')"
);
if (!$stmt) throw new Exception("Prepare failed (create_repair): " . $conn->error);

$stmt->bind_param("sssssssss", $ticketId, $asset_id, $dateStr, $problem, $solution, $status, $servicedBy, $requester, $company);
if ($stmt->execute()) {
    // Format display job number
    $datePart = str_replace('-', '', substr($dateStr, 0, 10));
    $jobNo = 'RPR-' . $datePart . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

    // Log asset history
    if (function_exists('logAssetHistory')) {
        $findStmt = $conn->prepare("SELECT id FROM it_assets WHERE equipment_id = ? LIMIT 1");
        if ($findStmt) {
            $findStmt->bind_param("s", $asset_id);
            $findStmt->execute();
            $findRes = $findStmt->get_result()->fetch_assoc();
            if ($findRes) logAssetHistory($conn, $findRes['id'], 'REPAIR_CREATED', "Repair ticket {$jobNo} ({$ticketId}) created by {$servicedBy}: {$problem}");
        }
    }

    echo json_encode(['status' => 'success', 'id' => $ticketId, 'job_no' => $jobNo]);
} else {
    throw new Exception("Execute failed (create_repair): " . $stmt->error);
}
?>
