<?php
// api/actions/add_item.php

$key = $_POST['key'] ?? '';
$role = $_SESSION['role'] ?? 'guest';
$isLoggedIn = isset($_SESSION['user_id']);

function permissionForAdminWriteKey(string $key): ?string {
    $map = [
        'departments' => 'employee.manage',
        'meeting_rooms' => 'employee.manage',
        'meeting_amenities' => 'employee.manage',
        'announcements' => 'employee.manage',
        'holidays' => 'employee.manage',
        'services' => 'employee.manage',
        'job_tickets' => 'employee.manage',
        'asset_categories' => 'asset.manage',
        'kb_categories' => 'kb.manage',
        'kb_articles' => 'kb.manage',
    ];
    return $map[$key] ?? null;
}

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
        'eta_date' => "ALTER TABLE it_logs ADD COLUMN eta_date DATE DEFAULT NULL AFTER vendor_name",
        'kb_article_id' => "ALTER TABLE it_logs ADD COLUMN kb_article_id INT(11) DEFAULT NULL AFTER solution"
    ];
    foreach ($schemaChecks as $col => $ddl) {
        $colRes = $conn->query("SHOW COLUMNS FROM it_logs LIKE '" . $conn->real_escape_string($col) . "'");
        if (!$colRes || intval($colRes->num_rows) === 0) {
            $conn->query($ddl);
        }
    }

    $fields = ['id', 'date', 'company', 'department', 'requester', 'servicedBy', 'asset_id', 'problem', 'contact', 'solution', 'root_cause', 'status', 'type', 'urgency', 'repair_cost', 'appointment_date', 'servicedById', 'requester_id', 'request_uid', 'completed_date', 'last_work_start_time', 'total_work_duration_seconds', 'evaluation_hardware', 'evaluation_software', 'evaluation_repair_cost', 'evaluation_recommendation', 'evaluation_reason', 'pr_no', 'po_no', 'vendor_name', 'eta_date'];

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
        
        $isProgrammer = strpos((string)($data['problem'] ?? ''), '[PROGRAMMER REQUEST]') !== false;
        ticketNotify($conn, 'it_repair', $isProgrammer ? 'ขอโปรแกรมเมอร์ใหม่' : 'แจ้งซ่อมใหม่', $new_id, [], 'เปิดงาน / รับเรื่อง');

        // Log to Asset History (บันทึกประวัติการแจ้งซ่อมลงใน Asset)
        if (!empty($data['asset_id'])) {
            $assetIdStr = $data['asset_id'];
            $assetStmt = $conn->prepare("SELECT id FROM it_assets WHERE equipment_id = ? LIMIT 1");
            $assetStmt->bind_param("s", $assetIdStr);
            $assetStmt->execute();
            $assetQ = $assetStmt->get_result();
            if ($assetQ && $assetRow = $assetQ->fetch_assoc()) {
                logAssetHistory($conn, $assetRow['id'], 'REPAIR_REQUEST', "Ticket #$new_id: " . ($data['problem']??''));
            }
        }

        // Log initial Pending event
        $ev_actor    = trim((string)($_SESSION['display_name'] ?? $_SESSION['username'] ?? $_SESSION['user_id'] ?? 'ผู้ใช้'));
        $ev_actor_id = trim((string)($_SESSION['user_id'] ?? ''));
        $ev_type_new = 'CREATE';
        $ev_stmt = $conn->prepare("INSERT INTO mt_ticket_events (log_id,event_type,from_status,to_status,actor_id,actor_name,created_at) VALUES (?,'CREATE',NULL,'Pending',?,?,NOW())");
        if ($ev_stmt) {
            $ev_stmt->bind_param("sss", $new_id, $ev_actor_id, $ev_actor);
            $ev_stmt->execute();
        }

        echo json_encode(['status' => 'success', 'new_id' => $new_id]);
    } else {
        throw new Exception("Execute failed (Add Log): " . $stmt->error);
    }
} else {
    if (!$isLoggedIn) throw new Exception("Access Denied");
    if ($role !== 'admin') {
        $requiredPermission = permissionForAdminWriteKey($key);
        if ($requiredPermission !== null) {
            if (!hasPermission($conn, $requiredPermission)) throw new Exception("Access Denied");
        } elseif (!in_array($role, ['admin', 'staff'], true)) {
            throw new Exception("Access Denied");
        }
    }
    // Original generic handler for other keys
    $json = $_POST['data'] ?? '{}';
    $data = json_decode($json, true);
    
    $config = [
        'departments' => ['table' => 'departments', 'pk' => 'dept_id', 'fields' => ['dept_name', 'dept_abbr']],
        'asset_categories' => ['table' => 'asset_categories', 'pk' => 'cat_id', 'fields' => ['cat_name', 'cat_abbr', 'default_useful_life']],
        'meeting_rooms' => ['table' => 'meeting_rooms', 'pk' => 'id', 'fields' => ['name', 'company', 'location', 'capacity', 'equipment', 'is_active', 'details', 'custodian', 'contact_phone', 'status', 'images']],
        'announcements' => ['table' => 'announcements', 'pk' => 'id', 'fields' => ['title', 'content', 'category', 'level', 'expirationDate']],
        'holidays' => ['table' => 'holidays', 'pk' => 'id', 'fields' => ['name', 'date', 'type']],
        'services' => ['table' => 'services', 'pk' => 'id', 'fields' => ['id', 'icon', 'title', 'description', 'color', 'sort_order', 'is_active', 'url_primary', 'url_fallback']],
        'kb_categories' => ['table' => 'kb_categories', 'pk' => 'id', 'fields' => ['name', 'description', 'icon', 'sort_order']],
        'kb_articles' => ['table' => 'kb_articles', 'pk' => 'id', 'fields' => ['category_id', 'title', 'content', 'tags', 'is_published', 'author_id']],
        'job_tickets' => ['table' => 'job_tickets', 'pk' => 'id', 'fields' => ['company', 'department', 'building', 'job_name', 'responsible', 'deadline', 'is_receive', 'is_doing', 'is_send', 'is_approve', 'is_done', 'created_by_id', 'created_by_name']]
    ];

    if (!isset($config[$key])) throw new Exception("Unknown key: $key");

    if ($key === 'services') {
        ptc_ensure_services_is_active_column($conn);
    }
    
    $table = $config[$key]['table'];
    $fields = $config[$key]['fields'];
    
    if ($key === 'services') {
        $data['id'] = trim((string)($data['id'] ?? ''));
        if ($data['id'] === '') {
            throw new Exception('กรุณาระบุรหัสบริการ (ID)');
        }
        $data['is_active'] = (isset($data['is_active']) && (int)$data['is_active'] === 0) ? 0 : 1;
    } else if ($key === 'kb_articles') {
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
