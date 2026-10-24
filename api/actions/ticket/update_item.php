<?php
// api/actions/update_item.php

function it_logs_is_valid_transition($from, $to) {
    if ($to === null || $to === '' || $from === $to) return true;
    $intermediate = ['In Progress', 'PR Submitted', 'PO Issued', 'Waiting for User', 'Sent to Vendor', 'Temporary Fix', 'Unrepairable', 'Awaiting Confirmation', 'Cancelled'];
    $map = [
        'Pending'                => ['Received', 'Cancelled'],
        'Received'               => $intermediate,
        'In Progress'            => array_merge($intermediate, ['Completed']),
        'PR Submitted'           => array_merge($intermediate, ['Completed']),
        'PO Issued'              => array_merge($intermediate, ['Completed']),
        'Waiting for User'       => array_merge($intermediate, ['Completed']),
        'Sent to Vendor'         => array_merge($intermediate, ['Completed']),
        'Temporary Fix'          => array_merge($intermediate, ['Completed']),
        'Unrepairable'           => ['In Progress'],
        'Awaiting Confirmation'  => ['Completed', 'In Progress', 'Cancelled'],
        'Completed'              => ['In Progress'],
        'Cancelled'              => ['In Progress'],
    ];
    if (!isset($map[$from])) return true;
    return in_array($to, $map[$from], true);
}

$key = $_POST['key'] ?? '';
$role = $_SESSION['role'] ?? 'guest';
$isLoggedIn = isset($_SESSION['user_id']);
$primaryDepartment = trim((string)($_SESSION['primary_department'] ?? ''));
$isItDeptUser = (strcasecmp($primaryDepartment, 'IT') === 0);

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
    if (!$isLoggedIn || (!in_array($role, ['admin', 'staff'], true) && !$isItDeptUser)) throw new Exception("Access Denied");
    $data = json_decode($_POST['data'], true);
    $servicedById = $_POST['servicedById'] ?? null;
    $requester_id = $_POST['requester_id'] ?? null;
    $expected_current_status = trim((string)($_POST['expected_current_status'] ?? ''));
    $reassignment_reason = trim((string)($_POST['reassignment_reason'] ?? ''));
    $id = $data['id'] ?? null; // For updates

    $sessionCompany = $_SESSION['company'] ?? '';
    // ช่าง IT แก้งานได้ทุกบริษัทที่ดูแล · คนอื่นเฉพาะบริษัทที่ล็อกอิน
    $allowCos = hasPermission($conn, 'it_log.access') ? userCompanies($conn) : ($sessionCompany ? [$sessionCompany] : []);
    if ($role !== 'admin' && $allowCos && $id) {
        $stmt_chk = $conn->prepare("SELECT company FROM it_logs WHERE id = ? LIMIT 1");
        if (!$stmt_chk) throw new Exception("Prepare failed");
        $stmt_chk->bind_param("s", $id);
        $stmt_chk->execute();
        $res_chk = $stmt_chk->get_result();
        $row_chk = $res_chk ? $res_chk->fetch_assoc() : null;
        if (!$row_chk || !in_array($row_chk['company'] ?? '', $allowCos, true)) throw new Exception("Access Denied");
    }

    // Add the extra IDs to the data array
    if ($servicedById) $data['servicedById'] = $servicedById;
    if ($requester_id) $data['requester_id'] = $requester_id;
    unset($data['company']);

    $schemaChecks = [
        'pr_no'              => "ALTER TABLE it_logs ADD COLUMN pr_no VARCHAR(50) DEFAULT NULL AFTER evaluation_reason",
        'po_no'              => "ALTER TABLE it_logs ADD COLUMN po_no VARCHAR(50) DEFAULT NULL AFTER pr_no",
        'vendor_name'        => "ALTER TABLE it_logs ADD COLUMN vendor_name VARCHAR(255) DEFAULT NULL AFTER po_no",
        'eta_date'           => "ALTER TABLE it_logs ADD COLUMN eta_date DATE DEFAULT NULL AFTER vendor_name",
        'root_cause'         => "ALTER TABLE it_logs ADD COLUMN root_cause TEXT DEFAULT NULL AFTER solution",
        'doc_number'         => "ALTER TABLE it_logs ADD COLUMN doc_number VARCHAR(30) DEFAULT NULL AFTER root_cause",
        'user_confirmed'           => "ALTER TABLE it_logs ADD COLUMN user_confirmed TINYINT(1) DEFAULT 0 AFTER doc_number",
        'user_confirmed_at'        => "ALTER TABLE it_logs ADD COLUMN user_confirmed_at DATETIME DEFAULT NULL AFTER user_confirmed",
        'user_confirmed_by'        => "ALTER TABLE it_logs ADD COLUMN user_confirmed_by VARCHAR(100) DEFAULT NULL AFTER user_confirmed_at",
        'awaiting_confirmation_at' => "ALTER TABLE it_logs ADD COLUMN awaiting_confirmation_at DATETIME DEFAULT NULL AFTER user_confirmed_by",
        'kb_article_id'            => "ALTER TABLE it_logs ADD COLUMN kb_article_id INT(11) DEFAULT NULL AFTER solution",
    ];
    foreach ($schemaChecks as $col => $ddl) {
        $colRes = $conn->query("SHOW COLUMNS FROM it_logs LIKE '" . $conn->real_escape_string($col) . "'");
        if (!$colRes || intval($colRes->num_rows) === 0) {
            $conn->query($ddl);
        }
    }

    $fields = ['id', 'date', 'company', 'department', 'requester', 'servicedBy', 'asset_id', 'problem', 'contact', 'solution', 'root_cause', 'doc_number', 'user_confirmed', 'user_confirmed_at', 'user_confirmed_by', 'awaiting_confirmation_at', 'status', 'type', 'urgency', 'repair_cost', 'appointment_date', 'servicedById', 'requester_id', 'completed_date', 'closed_date', 'last_work_start_time', 'total_work_duration_seconds', 'evaluation_hardware', 'evaluation_software', 'evaluation_repair_cost', 'evaluation_recommendation', 'evaluation_reason', 'pr_no', 'po_no', 'vendor_name', 'eta_date', 'kb_article_id'];

    if (!$id) throw new Exception("ID is required for update.");

    $conn->begin_transaction();
    try {
        // 1. Fetch current state for time tracking (lock the row for update)
        $stmt_current = $conn->prepare("SELECT status, servicedById, servicedBy, solution, closed_date, completed_date, last_work_start_time, total_work_duration_seconds, doc_number FROM it_logs WHERE id = ? FOR UPDATE");
        if (!$stmt_current) throw new Exception("Prepare failed (Fetch Current Log): " . $conn->error);
        $stmt_current->bind_param("s", $id);
        $stmt_current->execute();
        $current_log_res = $stmt_current->get_result();
        $current_log = $current_log_res->fetch_assoc();

        $new_status = $data['status'] ?? null;
        $current_status = null;
        
        if ($current_log) {
            $current_status = $current_log['status'];
            if ($expected_current_status !== '' && $current_status !== $expected_current_status) {
                throw new Exception("Ticket ถูกอัปเดตโดยผู้ใช้อื่นแล้ว (สถานะล่าสุด: {$current_status}) กรุณาโหลดใหม่");
            }

            if ($new_status && !it_logs_is_valid_transition((string)$current_status, (string)$new_status)) {
                throw new Exception("Invalid status transition from {$current_status} to {$new_status}");
            }

            $oldAssigneeId = trim((string)($current_log['servicedById'] ?? ''));
            $newAssigneeId = trim((string)($data['servicedById'] ?? ''));
            $isAssigneeChanged = ($newAssigneeId !== '' && $oldAssigneeId !== $newAssigneeId);
            if ($isAssigneeChanged && $oldAssigneeId !== '') {
                if ($reassignment_reason === '') {
                    throw new Exception("กรุณาระบุเหตุผลการเปลี่ยนผู้รับผิดชอบ");
                }
                $actor = trim((string)($_SESSION['display_name'] ?? $_SESSION['username'] ?? $_SESSION['user_id'] ?? 'system'));
                $oldAssigneeName = trim((string)($current_log['servicedBy'] ?? $oldAssigneeId));
                $newAssigneeName = trim((string)($data['servicedBy'] ?? $newAssigneeId));
                $note = '[' . date("d/m/Y H:i") . "] Reassigned: {$oldAssigneeName} -> {$newAssigneeName} by {$actor} | Reason: {$reassignment_reason}";
                $baseSolution = array_key_exists('solution', $data) ? (string)$data['solution'] : (string)($current_log['solution'] ?? '');
                $data['solution'] = trim($baseSolution) === '' ? $note : ($baseSolution . "\n" . $note);
            }

            $is_stopping_work = ($current_status === 'In Progress' && $new_status && $new_status !== 'In Progress');
            $is_starting_work = ($new_status === 'In Progress' && $current_status !== 'In Progress');

            if ($is_stopping_work && !empty($current_log['last_work_start_time'])) {
                $start_time = new DateTime($current_log['last_work_start_time']);
                $end_time = new DateTime(); // Now
                $interval_seconds = $end_time->getTimestamp() - $start_time->getTimestamp();
                
                $data['total_work_duration_seconds'] = (int)$current_log['total_work_duration_seconds'] + $interval_seconds;
                $data['last_work_start_time'] = null; // Set to null as work is stopping
            }
            
            if ($is_starting_work) {
                $data['last_work_start_time'] = date("Y-m-d H:i:s");
            }
        }

        // Generate doc_number + set awaiting timer when moving to Awaiting Confirmation
        if (isset($data['status']) && $data['status'] === 'Awaiting Confirmation') {
            $existingDocNum = trim((string)($current_log['doc_number'] ?? $data['doc_number'] ?? ''));
            if ($existingDocNum === '') {
                $year = date('Y');
                $countRes = $conn->query("SELECT COUNT(*) AS cnt FROM it_logs WHERE doc_number LIKE 'FM-IT-001/{$year}/%'");
                $cnt = $countRes ? (int)($countRes->fetch_assoc()['cnt'] ?? 0) : 0;
                $data['doc_number'] = 'FM-IT-001/' . $year . '/' . str_pad($cnt + 1, 4, '0', STR_PAD_LEFT);
            }
            // Always reset the auto-close timer so reopened tickets get a fresh 3-day window
            $data['awaiting_confirmation_at'] = date("Y-m-d H:i:s");
        }

        if (isset($data['status']) && $data['status'] === 'Completed') {
            $data['completed_date'] = date("Y-m-d H:i:s");
            if ($current_log && empty($current_log['closed_date'])) {
                $data['closed_date'] = date("Y-m-d H:i:s");
            }
        } elseif (isset($data['status']) && in_array($data['status'], ['Cancelled', 'Unrepairable'], true)) {
            if ($current_log && empty($current_log['closed_date'])) {
                $data['closed_date'] = date("Y-m-d H:i:s");
            }
        } elseif (isset($data['status']) && $data['status'] === 'In Progress' && in_array((string)$current_status, ['Completed', 'Cancelled', 'Unrepairable'], true)) {
            $data['closed_date'] = null;
            $data['completed_date'] = null;
        }

        $set = []; $types = ""; $params = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data) && $f !== 'id') {
                $set[] = "`$f`=?";
                if ($data[$f] === null) {
                    $types .= "s"; $params[] = null;
                } else {
                    $types .= "s"; $params[] = $data[$f];
                }
            }
        }

        if (count($set) > 0) {
            $sql = "UPDATE it_logs SET " . implode(',', $set) . " WHERE id=?";
            $types .= "s";
            $params[] = $id;

            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Prepare failed (Update Log): " . $conn->error);
            $stmt->bind_param($types, ...$params);

            if (!$stmt->execute()) {
                throw new Exception("Execute failed (Update Log): " . $stmt->error);
            }
        }

        // Log status change event
        if ($new_status && $current_status && $new_status !== $current_status) {
            $actor     = trim((string)($_SESSION['display_name'] ?? $_SESSION['username'] ?? $_SESSION['user_id'] ?? 'system'));
            $actor_id  = trim((string)($_SESSION['user_id'] ?? 'system'));
            $ev_type   = 'STATUS';
            if ($current_status === 'Pending' && $new_status === 'Received') $ev_type = 'ACCEPT';
            elseif ($new_status === 'Completed')  $ev_type = 'CLOSE';
            elseif ($new_status === 'Cancelled')  $ev_type = 'CANCEL';
            $ev_assigned_id   = $data['servicedById'] ?? null;
            $ev_assigned_name = $data['servicedBy']   ?? null;
            $ev_stmt = $conn->prepare("INSERT INTO mt_ticket_events (log_id,event_type,from_status,to_status,assigned_to_id,assigned_to_name,actor_id,actor_name,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())");
            if ($ev_stmt) {
                $ev_stmt->bind_param("ssssssss", $id, $ev_type, $current_status, $new_status, $ev_assigned_id, $ev_assigned_name, $actor_id, $actor);
                $ev_stmt->execute();
            }
        }

        // Existing logic for notifications and asset history
        if (isset($data['status'])) {
            $ticketStmt = $conn->prepare("SELECT asset_id, requester, problem, servicedBy, company FROM it_logs WHERE id = ?");
            $ticketStmt->bind_param("s", $id);
            $ticketStmt->execute();
            $ticketQ = $ticketStmt->get_result();
            if ($ticketQ && $ticketRow = $ticketQ->fetch_assoc()) {
                $newStatus = $data['status'];

                // Part 1: Send Telegram Notification
                $requester = $ticketRow['requester'] ?? '-';
                $problem = $ticketRow['problem'] ?? '-';
                $servicedBy = $data['servicedBy'] ?? $ticketRow['servicedBy'] ?? 'N/A';
                $solution = $data['solution'] ?? '-';
                $now = date("d/m/Y H:i");

                $statusInfo = [
                    'Received'               => ['icon' => '📩', 'text' => 'รับเรื่องแล้ว'],
                    'PR Submitted'           => ['icon' => '🧾', 'text' => 'ส่ง PR แล้ว'],
                    'PO Issued'              => ['icon' => '📄', 'text' => 'ออก PO แล้ว'],
                    'In Progress'            => ['icon' => '⏳', 'text' => 'กำลังดำเนินการ'],
                    'Waiting for User'       => ['icon' => '💬', 'text' => 'รอผู้ใช้ตอบกลับ'],
                    'Sent to Vendor'         => ['icon' => '🏭', 'text' => 'ส่งซ่อมภายนอก'],
                    'Temporary Fix'          => ['icon' => '🔧', 'text' => 'แก้ชั่วคราว'],
                    'Unrepairable'           => ['icon' => '⚠️', 'text' => 'ซ่อมไม่ได้ (แนะนำเปลี่ยน)'],
                    'Awaiting Confirmation'  => ['icon' => '⏰', 'text' => 'รอยืนยันจากผู้แจ้ง'],
                    'Completed'              => ['icon' => '✅', 'text' => 'เสร็จสิ้น'],
                    'Cancelled'              => ['icon' => '🚫', 'text' => 'ยกเลิก'],
                ];

                $notifyStatuses = ['Awaiting Confirmation', 'Cancelled'];
                if (isset($statusInfo[$newStatus]) && in_array($newStatus, $notifyStatuses, true)) {
                    $s = $statusInfo[$newStatus];
                    
                    $isReopen = ($current_status === 'Completed' || $current_status === 'Cancelled') && $newStatus === 'In Progress';
                    $notifyEvent = 'it_repair';
                    if (in_array($newStatus, ['Completed', 'Awaiting Confirmation', 'Unrepairable'], true)) $notifyEvent = 'it_approve';
                    elseif ($newStatus === 'Cancelled') $notifyEvent = 'it_cancel';
                    require_once __DIR__ . '/../../../includes/notify_helper.php';
                    ticketNotify($conn, $notifyEvent, $isReopen ? 'เปิดงานซ่อมใหม่' : ($newStatus === 'Awaiting Confirmation' ? 'ซ่อมเสร็จ รอผู้แจ้งยืนยัน' : $s['text']), $id,
                        ['ช่าง' => $servicedBy, 'วิธีแก้ / หมายเหตุ' => $solution !== '-' ? $solution : '', 'ถัดไป' => $newStatus === 'Awaiting Confirmation' ? 'ผู้แจ้งกดยืนยันในเว็บ (ปิดเองใน 3 วันถ้าไม่ยืนยัน)' : '']);
                }

                // Part 2: Update Asset Status & Log History
                $assetIdStr = $ticketRow['asset_id'];
                if ($assetIdStr) {
                    $assetStmt = $conn->prepare("SELECT id FROM it_assets WHERE equipment_id = ? LIMIT 1");
                    $assetStmt->bind_param("s", $assetIdStr);
                    $assetStmt->execute();
                    $assetQ = $assetStmt->get_result();
                    if ($assetQ && $assetRow = $assetQ->fetch_assoc()) {
                        $assetDbId = intval($assetRow['id']);
                        if ($newStatus === 'In Progress') {
                            $updAsset = $conn->prepare("UPDATE it_assets SET status = 'ส่งซ่อม' WHERE id = ?");
                            $updAsset->bind_param("i", $assetDbId); $updAsset->execute();
                            logAssetHistory($conn, $assetDbId, 'REPAIR_START', "Ticket #$id: Started repair");
                        } elseif ($newStatus === 'Completed') {
                            $updAsset = $conn->prepare("UPDATE it_assets SET status = 'ใช้งานปกติ' WHERE id = ?");
                            $updAsset->bind_param("i", $assetDbId); $updAsset->execute();
                            logAssetHistory($conn, $assetDbId, 'REPAIR_COMPLETE', "Ticket #$id: Repair completed. Solution: " . ($data['solution']??'-'));
                        } elseif ($newStatus === 'Cancelled') {
                            $updAsset = $conn->prepare("UPDATE it_assets SET status = 'ใช้งานปกติ' WHERE id = ? AND status = 'ส่งซ่อม'");
                            $updAsset->bind_param("i", $assetDbId); $updAsset->execute();
                            logAssetHistory($conn, $assetDbId, 'REPAIR_CANCEL', "Ticket #$id: Cancelled");
                        } elseif ($newStatus === 'Unrepairable') {
                            // ซ่อมไม่ได้ ไม่ควรกลับไป "ใช้งานปกติ" เหมือนซ่อมเสร็จ — ตั้งเป็น "ชำรุด" ให้เห็นชัดว่าเครื่องนี้มีปัญหาอยู่
                            // (ไม่ auto ตัดจำหน่ายเป็น "เลิกใช้งาน" เพราะการตัดจำหน่ายต้องผ่านขั้นตอนอนุมัติ/บัญชีแยกต่างหาก)
                            $updAsset = $conn->prepare("UPDATE it_assets SET status = 'ชำรุด' WHERE id = ? AND status = 'ส่งซ่อม'");
                            $updAsset->bind_param("i", $assetDbId); $updAsset->execute();
                            logAssetHistory($conn, $assetDbId, 'REPAIR_UNREPAIRABLE', "Ticket #$id: Unrepairable. Recommendation: " . ($data['solution']??'-'));
                        }
                    }
                }
            }
        }

        $conn->commit();
        echo json_encode(['status' => 'success']);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
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
    $pk = $config[$key]['pk'];
    $fields = $config[$key]['fields'];
    
    // Handle meeting_rooms images logic (same as add_item but for update context)
    if ($key === 'meeting_rooms') {
        $existing_images = [];
        $id_to_fetch = $data['id'] ?? ($data[$pk] ?? 0);
        if ($id_to_fetch) {
            $res = $conn->query("SELECT images FROM meeting_rooms WHERE id = " . intval($id_to_fetch));
            if ($res && $row = $res->fetch_assoc()) {
                $existing_images = json_decode($row['images'] ?? '[]', true);
                if (!is_array($existing_images)) $existing_images = [];
            }
        }

        $targetDir = "uploads/meeting_rooms/";
        if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

        for ($i = 1; $i <= 4; $i++) {
            if (isset($_FILES["room_image_$i"]) && $_FILES["room_image_$i"]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES["room_image_$i"];
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newFileName = uniqid('room_') . '.' . $ext;
                
                if (move_uploaded_file($file['tmp_name'], $targetDir . $newFileName)) {
                    $index = $i - 1;
                    if (isset($existing_images[$index]) && file_exists($existing_images[$index])) {
                        @unlink($existing_images[$index]);
                    }
                    $existing_images[$index] = $targetDir . $newFileName;
                }
            }
        }
        $data['images'] = json_encode(array_values($existing_images), JSON_UNESCAPED_UNICODE);
    } else if ($key === 'services') {
        $data['id'] = trim((string)($data['id'] ?? ''));
        if ($data['id'] === '') {
            throw new Exception('กรุณาระบุรหัสบริการ (ID)');
        }
        $data['is_active'] = (isset($data['is_active']) && (int)$data['is_active'] === 0) ? 0 : 1;
    } else if ($key === 'kb_articles') {
        $data['is_published'] = isset($data['is_published']) && $data['is_published'] == 1 ? 1 : 0;
        // คงผู้เขียนเดิมไว้ — ไม่งั้น admin ที่กดเผยแพร่ร่าง (จากการปิด ticket) จะกลายเป็นผู้เขียนแทนช่าง
        unset($data['author_id']);
    }
    
    $svcPrevId = '';
    $svcFallbackUrl = '';
    if ($key === 'services') {
        $svcPrevId = trim((string)($data['_previous_id'] ?? ''));
        $svcFallbackUrl = trim((string)($data['_fallback_url'] ?? ''));
        unset($data['_previous_id'], $data['_fallback_url']);
    }

    $set = []; $types = ""; $params = [];
    foreach ($fields as $f) {
        if (isset($data[$f])) {
            $set[] = "$f=?";
            $types .= "s";
            $params[] = $data[$f];
        }
    }

    $sql = "UPDATE $table SET " . implode(',', $set) . " WHERE $pk=?";
    $types .= "s";
    $wherePk = $data[$pk] ?? $data['id'];

    if ($key === 'services') {
        $wherePk = $svcPrevId !== '' ? $svcPrevId : trim((string)($data['id'] ?? ''));
        if ($svcPrevId === '' && $svcFallbackUrl !== '') {
            $byUrl = $conn->prepare("SELECT id FROM services WHERE TRIM(url_primary) = ? OR url_primary = ? LIMIT 1");
            if ($byUrl) {
                $byUrl->bind_param('ss', $svcFallbackUrl, $svcFallbackUrl);
                $byUrl->execute();
                $urlRes = $byUrl->get_result();
                if ($urlRes && ($urlRow = $urlRes->fetch_assoc())) {
                    $foundPk = trim((string)($urlRow['id'] ?? ''));
                    if ($foundPk !== '') {
                        $wherePk = $foundPk;
                    }
                }
            }
        }
        if (trim((string)$wherePk) === '') {
            if ($svcFallbackUrl === '') {
                throw new Exception('ไม่พบรายการเดิมในระบบ — ลองลบแล้วสร้างใหม่ด้วย Service ID');
            }
            $sql = "UPDATE $table SET " . implode(',', $set) . " WHERE TRIM(url_primary)=? OR url_primary=?";
            $types .= 'ss';
            $params[] = $svcFallbackUrl;
            $params[] = $svcFallbackUrl;
        } else {
            $params[] = $wherePk;
        }
    } else {
        $params[] = $wherePk;
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) echo json_encode(['status' => 'success']);
    else throw new Exception($stmt->error);
}
?>
