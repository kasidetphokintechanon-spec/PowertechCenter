<?php
// api/actions/add_item.php

$key = $_POST['key'] ?? '';
$role = $_SESSION['role'] ?? 'guest';
$isLoggedIn = isset($_SESSION['user_id']);

if ($key === 'it_logs') {
    if (!$isLoggedIn) throw new Exception("Unauthorized");
    $data = json_decode($_POST['data'], true);
    $servicedById = $_POST['servicedById'] ?? null;
    $requester_id = $_POST['requester_id'] ?? null;
    
    if (!is_array($data)) $data = [];
    $request_uid = trim((string)($data['request_uid'] ?? ''));

    $colCheck = $conn->query("SHOW COLUMNS FROM it_logs LIKE 'request_uid'");
    if (!$colCheck || intval($colCheck->num_rows) === 0) {
        $conn->query("ALTER TABLE it_logs ADD COLUMN request_uid VARCHAR(64) NULL AFTER requester_id");
        $conn->query("ALTER TABLE it_logs ADD KEY idx_request_uid (request_uid)");
    }

    if ($role !== 'admin' && !empty($_SESSION['company'])) {
        $data['company'] = $_SESSION['company'];
    }

    if ($role === 'user') {
        $data['requester_id'] = (string)$_SESSION['user_id'];
        $data['requester'] = (string)($_SESSION['display_name'] ?? $_SESSION['username'] ?? '');
        if (!empty($_SESSION['primary_department'])) $data['department'] = (string)$_SESSION['primary_department'];
        $servicedById = null;
        unset($data['servicedById'], $data['servicedBy']);
    } else {
        if ($servicedById) $data['servicedById'] = $servicedById;
        if ($requester_id) $data['requester_id'] = $requester_id;
    }

    if ($request_uid !== '') {
        $data['request_uid'] = $request_uid;
        $stmt_dup = $conn->prepare("SELECT id FROM it_logs WHERE request_uid = ? LIMIT 1");
        if ($stmt_dup) {
            $stmt_dup->bind_param("s", $request_uid);
            $stmt_dup->execute();
            $res_dup = $stmt_dup->get_result();
            $row_dup = $res_dup ? $res_dup->fetch_assoc() : null;
            if ($row_dup && !empty($row_dup['id'])) {
                echo json_encode(['status' => 'success', 'new_id' => $row_dup['id'], 'deduplicated' => true]);
                return;
            }
        }
    }

    $schemaChecks = [
        'pr_no' => "ALTER TABLE it_logs ADD COLUMN pr_no VARCHAR(50) DEFAULT NULL AFTER evaluation_reason",
        'po_no' => "ALTER TABLE it_logs ADD COLUMN po_no VARCHAR(50) DEFAULT NULL AFTER pr_no",
        'vendor_name' => "ALTER TABLE it_logs ADD COLUMN vendor_name VARCHAR(255) DEFAULT NULL AFTER po_no",
        'eta_date' => "ALTER TABLE it_logs ADD COLUMN eta_date DATE DEFAULT NULL AFTER vendor_name"
    ];
    foreach ($schemaChecks as $col => $ddl) {
        $colRes = $conn->query("SHOW COLUMNS FROM it_logs LIKE '" . $conn->real_escape_string($col) . "'");
        if (!$colRes || intval($colRes->num_rows) === 0) {
            $conn->query($ddl);
        }
    }

    $fields = ['id', 'date', 'company', 'department', 'requester', 'servicedBy', 'asset_id', 'problem', 'contact', 'solution', 'status', 'type', 'urgency', 'repair_cost', 'appointment_date', 'servicedById', 'requester_id', 'request_uid', 'completed_date', 'last_work_start_time', 'total_work_duration_seconds', 'evaluation_hardware', 'evaluation_software', 'evaluation_repair_cost', 'evaluation_recommendation', 'evaluation_reason', 'pr_no', 'po_no', 'vendor_name', 'eta_date'];

    $cols = []; $vals = []; $types = ""; $params = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $data)) {
            $cols[] = "`$f`";
            $vals[] = "?";
            $types .= "s"; // Assume all are strings for simplicity
            $params[] = $data[$f];
        }
    }
    
    // Generate Custom ID if not provided (Auto-generate Ticket ID)
    if (!in_array("`id`", $cols)) {
        $company = $data['company'] ?? 'PTA';
        $dateStr = date('ymd');
        $prefix = "{$company}-MIS{$dateStr}-";
        
        $searchPrefix = $prefix . '%';
        $stmt_id = $conn->prepare("SELECT id FROM it_logs WHERE id LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt_id->bind_param("s", $searchPrefix);
        $stmt_id->execute();
        $last_res = $stmt_id->get_result();

        $next_num = 1;
        if ($last_res && $row = $last_res->fetch_assoc()) {
            $parts = explode('-', $row['id']);
            $next_num = intval(end($parts)) + 1;
        }
        $new_id = $prefix . str_pad($next_num, 2, '0', STR_PAD_LEFT);
        
        $cols[] = "`id`"; $vals[] = "?"; $types .= "s"; $params[] = $new_id;
    } else {
        $new_id = $data['id'];
    }

    if (count($cols) === 0) throw new Exception("No data provided for insert.");

    $sql = "INSERT INTO it_logs (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed (Add Log): " . $conn->error);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        // Send Telegram Notification
        $urgency = $data['urgency'] ?? '-';
        $now = date("d/m/Y H:i");
        $uEmoji = ($urgency === 'ด่วนที่สุด') ? '🔥' : (($urgency === 'ด่วน') ? '⚡' : '✨');
        
        $problemRaw = $data['problem'] ?? '-';
        $isProgrammer = (strpos($problemRaw, '[PROGRAMMER REQUEST]') !== false);
        
        if ($isProgrammer) {
            // Reformat programmer request for better display in Line/Telegram
            $formattedProblem = str_replace(
                ['[PROGRAMMER REQUEST]', 'Software:', 'Module:', '1. อาการที่พบ:', '2. ความต้องการ:', '3. แหล่งข้อมูล:', '----------------------'],
                ['<b>💻 Programmer Request</b>', '<b>Software:</b>', '<b>Module:</b>', '<b>1. อาการที่พบ:</b>', '<b>2. ความต้องการ:</b>', '<b>3. แหล่งข้อมูล:</b>', '➖➖➖➖➖➖➖➖➖➖'],
                $problemRaw
            );
            
            $msg = "<b>🆕 New Programmer Ticket {$uEmoji}</b>\n" .
                   "➖➖➖➖➖➖➖➖➖➖\n" .
                   "<b>🏢 Company:</b> " . ($data['company']??'-') . "\n" .
                   "<b>🎫 Ticket:</b> <code>{$new_id}</code>\n" .
                   "<b>🕒 Time:</b> {$now}\n" .
                   "<b>👤 User:</b> " . ($data['requester']??'-') . "\n" .
                   "<b>🏢 Dept:</b> " . ($data['department']??'-') . "\n" .
                   "<b>🚨 Level:</b> {$urgency}\n" .
                   "➖➖➖➖➖➖➖➖➖➖\n" .
                   $formattedProblem . "\n" .
                   "\n#NewTicket #Programmer";
        } else {
            $msg = "<b>🆕 New Ticket Alert {$uEmoji}</b>\n" .
                   "➖➖➖➖➖➖➖➖➖➖\n" .
                   "<b>🏢 Company:</b> " . ($data['company']??'-') . "\n" .
                   "<b>🎫 Ticket:</b> <code>{$new_id}</code>\n" .
                   "<b>🕒 Time:</b> {$now}\n" .
                   "<b>👤 User:</b> " . ($data['requester']??'-') . "\n" .
                   "<b>🏢 Dept:</b> " . ($data['department']??'-') . "\n" .
                   "<b>🚨 Level:</b> {$urgency}\n" .
                   "➖➖➖➖➖➖➖➖➖➖\n" .
                   "<b>📝 Problem:</b>\n" . $problemRaw . "\n" .
                   "\n#NewTicket #ITSupport";
        }

        sendTelegramNotification($conn, $msg, 'repair');

        // Log to Asset History (บันทึกประวัติการแจ้งซ่อมลงใน Asset)
        if (!empty($data['asset_id'])) {
            $assetIdStr = $conn->real_escape_string($data['asset_id']);
            $assetQ = $conn->query("SELECT id FROM it_assets WHERE equipment_id = '$assetIdStr' LIMIT 1");
            if ($assetQ && $assetRow = $assetQ->fetch_assoc()) {
                logAssetHistory($conn, $assetRow['id'], 'REPAIR_REQUEST', "Ticket #$new_id: " . ($data['problem']??''));
            }
        }

        echo json_encode(['status' => 'success', 'new_id' => $new_id]);
    } else {
        throw new Exception("Execute failed (Add Log): " . $stmt->error);
    }
} else {
    if (!$isLoggedIn || !in_array($role, ['admin', 'staff'], true)) throw new Exception("Access Denied");
    // Original generic handler for other keys
    $json = $_POST['data'] ?? '{}';
    $data = json_decode($json, true);
    
    $config = [
        'departments' => ['table' => 'departments', 'pk' => 'dept_id', 'fields' => ['dept_name', 'dept_abbr']],
        'asset_categories' => ['table' => 'asset_categories', 'pk' => 'cat_id', 'fields' => ['cat_name', 'cat_abbr']],
        'meeting_rooms' => ['table' => 'meeting_rooms', 'pk' => 'id', 'fields' => ['name', 'company', 'location', 'capacity', 'equipment', 'is_active', 'details', 'custodian', 'contact_phone', 'status', 'images']],
        'announcements' => ['table' => 'announcements', 'pk' => 'id', 'fields' => ['title', 'content', 'category', 'level', 'expirationDate']],
        'holidays' => ['table' => 'holidays', 'pk' => 'id', 'fields' => ['name', 'date', 'type']],
        'services' => ['table' => 'services', 'pk' => 'id', 'fields' => ['id', 'icon', 'title', 'description', 'color', 'sort_order', 'url_primary', 'url_fallback']],
        'kb_categories' => ['table' => 'kb_categories', 'pk' => 'id', 'fields' => ['name', 'description', 'icon', 'sort_order']],
        'kb_articles' => ['table' => 'kb_articles', 'pk' => 'id', 'fields' => ['category_id', 'title', 'content', 'tags', 'is_published', 'author_id']],
        'job_tickets' => ['table' => 'job_tickets', 'pk' => 'id', 'fields' => ['company', 'department', 'building', 'job_name', 'responsible', 'deadline', 'is_receive', 'is_doing', 'is_send', 'is_approve', 'is_done', 'created_by_id', 'created_by_name']]
    ];

    if (!isset($config[$key])) throw new Exception("Unknown key: $key");
    
    $table = $config[$key]['table'];
    $fields = $config[$key]['fields'];
    
    if ($key === 'kb_articles') {
        $data['is_published'] = isset($data['is_published']) && $data['is_published'] == 1 ? 1 : 0;
        $data['author_id'] = $_SESSION['user_id'] ?? null;
    } else if ($key === 'job_tickets') {
        $data['created_by_id'] = $_SESSION['user_id'] ?? null;
        $data['created_by_name'] = $_SESSION['display_name'] ?? null;
        if (empty($data['department'])) $data['department'] = $_SESSION['primary_department'] ?? null;
    }
    
    $cols = []; $vals = []; $types = ""; $params = [];
    foreach ($fields as $f) {
        if (isset($data[$f])) {
            $cols[] = $f;
            $vals[] = "?";
            $types .= "s";
            $params[] = $data[$f];
        }
    }
    $sql = "INSERT INTO $table (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) echo json_encode(['status' => 'success']);
    else throw new Exception($stmt->error);
}
?>
