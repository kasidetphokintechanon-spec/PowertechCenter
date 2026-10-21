<?php
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id   = intval($_POST['id'] ?? 0);
$note = trim($_POST['note'] ?? '');

if (!$id || $note === '') {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุรายละเอียดปัญหา']);
    exit;
}

// โหลดข้อมูล asset จาก DB
$assetStmt = $conn->prepare("SELECT equipment_id, name, company, department FROM it_assets WHERE id = ? LIMIT 1");
if (!$assetStmt) {
    echo json_encode(['status' => 'error', 'message' => 'DB error']);
    exit;
}
$assetStmt->bind_param('i', $id);
$assetStmt->execute();
$asset = $assetStmt->get_result()->fetch_assoc();
if (!$asset) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบพัสดุ']);
    exit;
}

// อัปโหลดรูปภาพ (ถ้ามี)
$imagePath = '';
if (isset($_FILES['issue_image']) && $_FILES['issue_image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = APP_ROOT . '/uploads/notes/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
    $ext     = strtolower(pathinfo($_FILES['issue_image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (in_array($ext, $allowed)) {
        $fileName = uniqid('issue_') . '.' . $ext;
        if (move_uploaded_file($_FILES['issue_image']['tmp_name'], $uploadDir . $fileName)) {
            $imagePath = 'uploads/notes/' . $fileName;
        } else {
            error_log('[PTC] report_asset_issue: move_uploaded_file failed → ' . $uploadDir . $fileName);
        }
    }
}

// บันทึก Asset History
$details = json_encode(['text' => '🚨 แจ้งปัญหา: ' . $note, 'image' => $imagePath], JSON_UNESCAPED_UNICODE);
logAssetHistory($conn, $id, 'ISSUE', $details);

// ── สร้าง IT Ticket ใน it_logs ────────────────────────────────────────
$role        = $_SESSION['role'] ?? 'user';
$requester   = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'ผู้ใช้';
$requesterId = (string)($_SESSION['user_id'] ?? '');
$company     = ($role !== 'admin' && !empty($_SESSION['company']))
               ? $_SESSION['company']
               : ($asset['company'] ?? 'PTA');
$department  = $_SESSION['primary_department'] ?? $asset['department'] ?? '';
$equipmentId = $asset['equipment_id'] ?? '';
$today       = date('Y-m-d');

// สร้าง ticket ID อัตโนมัติ
$prefix     = "{$company}-MIS" . date('ymd') . '-';
$stmt_id    = $conn->prepare("SELECT id FROM it_logs WHERE id LIKE ? ORDER BY id DESC LIMIT 1");
$searchPfx  = $prefix . '%';
$stmt_id->bind_param('s', $searchPfx);
$stmt_id->execute();
$lastRow   = $stmt_id->get_result()->fetch_assoc();
$nextNum   = $lastRow ? intval(end(explode('-', $lastRow['id']))) + 1 : 1;
$ticketId  = $prefix . str_pad($nextNum, 2, '0', STR_PAD_LEFT);

// Deduplicate ด้วย request_uid (ป้องกัน double-submit)
$request_uid = trim($_POST['request_uid'] ?? '');
if ($request_uid !== '') {
    $dupStmt = $conn->prepare("SELECT id FROM it_logs WHERE request_uid = ? LIMIT 1");
    $dupStmt->bind_param('s', $request_uid);
    $dupStmt->execute();
    $dupRow = $dupStmt->get_result()->fetch_assoc();
    if ($dupRow) {
        echo json_encode(['status' => 'success', 'ticket_id' => $dupRow['id'], 'deduplicated' => true]);
        exit;
    }
}

// Ensure request_uid column exists
$conn->query("ALTER TABLE it_logs ADD COLUMN IF NOT EXISTS request_uid VARCHAR(64) NULL");

$insertSql = "INSERT INTO it_logs (id, date, company, department, requester, requester_id, asset_id, problem, status, urgency, request_uid)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'ปกติ', ?)";
$ins = $conn->prepare($insertSql);
if (!$ins) {
    error_log('[report_asset_issue] prepare failed: ' . $conn->error);
    echo json_encode(['status' => 'success', 'message' => 'บันทึกการแจ้งปัญหาเรียบร้อยแล้ว (no ticket)']);
    exit;
}
$ins->bind_param('sssssssss',
    $ticketId, $today, $company, $department,
    $requester, $requesterId, $equipmentId, $note, $request_uid
);
$ins->execute();

// บันทึก ticket event
$evStmt = $conn->prepare("INSERT INTO mt_ticket_events (log_id,event_type,from_status,to_status,actor_id,actor_name,created_at)
                          VALUES (?,'CREATE',NULL,'Pending',?,?,NOW())");
if ($evStmt) {
    $evStmt->bind_param('sss', $ticketId, $requesterId, $requester);
    $evStmt->execute();
}

// ── ส่ง Notification ──────────────────────────────────────────────────
$urgEmoji = '✨';
$now      = date('d/m/Y H:i');
$msg = "<b>🆕 New Ticket Alert {$urgEmoji}</b>\n" .
       "➖➖➖➖➖➖➖➖➖➖\n" .
       "<b>🏢 Company:</b> {$company}\n" .
       "<b>🎫 Ticket:</b> <code>{$ticketId}</code>\n" .
       "<b>🕒 Time:</b> {$now}\n" .
       "<b>👤 User:</b> {$requester}\n" .
       "<b>🏢 Dept:</b> {$department}\n" .
       "<b>🚨 Level:</b> ปกติ\n" .
       "<b>💻 อุปกรณ์:</b> {$equipmentId}\n" .
       "➖➖➖➖➖➖➖➖➖➖\n" .
       "<b>📝 Problem:</b>\n{$note}\n" .
       "\n#NewTicket #ITSupport";

sendNotify($conn, $msg, 'it_repair', $company);

echo json_encode(['status' => 'success', 'ticket_id' => $ticketId, 'message' => 'บันทึกการแจ้งปัญหาเรียบร้อยแล้ว']);
