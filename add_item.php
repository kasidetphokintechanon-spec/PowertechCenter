<?php
// api/actions/add_item.php

$key = $_POST['key'] ?? '';

if ($key === 'it_logs') {
    $data = json_decode($_POST['data'], true);
    $servicedById = $_POST['servicedById'] ?? null;
    $requester_id = $_POST['requester_id'] ?? null;
    
    // Add the extra IDs to the data array
    if ($servicedById) $data['servicedById'] = $servicedById;
    if ($requester_id) $data['requester_id'] = $requester_id;

    $fields = ['id', 'date', 'company', 'department', 'requester', 'servicedBy', 'asset_id', 'problem', 'solution', 'status', 'type', 'urgency', 'repair_cost', 'appointment_date', 'servicedById', 'requester_id', 'completed_date', 'last_work_start_time', 'total_work_duration_seconds'];

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
        
        $msg = "<b>🆕 New Ticket Alert {$uEmoji}</b>\n" .
               "➖➖➖➖➖➖➖➖➖➖\n" .
               "<b>🏢 Company:</b> " . ($data['company']??'-') . "\n" .
               "<b>🎫 Ticket:</b> <code>{$new_id}</code>\n" .
               "<b>🕒 Time:</b> {$now}\n" .
               "<b>👤 User:</b> " . ($data['requester']??'-') . "\n" .
               "<b>🏢 Dept:</b> " . ($data['department']??'-') . "\n" .
               "<b>🚨 Level:</b> {$urgency}\n" .
               "➖➖➖➖➖➖➖➖➖➖\n" .
               "<b>📝 Problem:</b>\n" . ($data['problem']??'-') . "\n" .
               "\n#NewTicket #ITSupport";

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