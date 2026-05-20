<?php
// api/actions/update_item.php

function it_logs_is_valid_transition($from, $to) {
    if ($to === null || $to === '' || $from === $to) return true;
    $map = [
        'Pending'           => ['Received', 'Cancelled'],
        'Received'          => ['In Progress', 'PR Submitted', 'PO Issued', 'Parts Received', 'Waiting for Parts', 'Temporary Fix', 'Unrepairable', 'Cancelled'],
        'In Progress'       => ['PR Submitted', 'PO Issued', 'Parts Received', 'Waiting for Parts', 'Temporary Fix', 'Unrepairable', 'Completed', 'Cancelled'],
        'PR Submitted'      => ['PO Issued', 'Parts Received', 'In Progress', 'Waiting for Parts', 'Temporary Fix', 'Unrepairable', 'Completed', 'Cancelled'],
        'PO Issued'         => ['Parts Received', 'In Progress', 'Waiting for Parts', 'Temporary Fix', 'Unrepairable', 'Completed', 'Cancelled'],
        'Parts Received'    => ['In Progress', 'Waiting for Parts', 'Temporary Fix', 'Unrepairable', 'Completed', 'Cancelled'],
        'Waiting for Parts' => ['PR Submitted', 'PO Issued', 'Parts Received', 'In Progress', 'Unrepairable', 'Completed', 'Cancelled'],
        'Temporary Fix'     => ['PR Submitted', 'PO Issued', 'Parts Received', 'In Progress', 'Unrepairable', 'Completed', 'Cancelled'],
        'Unrepairable'      => ['In Progress'],
        'Completed'         => ['In Progress'],
        'Cancelled'         => ['In Progress'],
    ];
    if (!isset($map[$from])) return true;
    return in_array($to, $map[$from], true);
}

$key = $_POST['key'] ?? '';
$role = $_SESSION['role'] ?? 'guest';
$isLoggedIn = isset($_SESSION['user_id']);
$primaryDepartment = trim((string)($_SESSION['primary_department'] ?? ''));
$isItDeptUser = (strcasecmp($primaryDepartment, 'IT') === 0);

if ($key === 'it_logs') {
    if (!$isLoggedIn || (!in_array($role, ['admin', 'staff'], true) && !$isItDeptUser)) throw new Exception("Access Denied");
    $data = json_decode($_POST['data'], true);
    $servicedById = $_POST['servicedById'] ?? null;
    $requester_id = $_POST['requester_id'] ?? null;
    $expected_current_status = trim((string)($_POST['expected_current_status'] ?? ''));
    $reassignment_reason = trim((string)($_POST['reassignment_reason'] ?? ''));
    $id = $data['id'] ?? null; // For updates

    $sessionCompany = $_SESSION['company'] ?? '';
    if ($role !== 'admin' && $sessionCompany && $id) {
        $stmt_chk = $conn->prepare("SELECT company FROM it_logs WHERE id = ? LIMIT 1");
        if (!$stmt_chk) throw new Exception("Prepare failed");
        $stmt_chk->bind_param("s", $id);
        $stmt_chk->execute();
        $res_chk = $stmt_chk->get_result();
        $row_chk = $res_chk ? $res_chk->fetch_assoc() : null;
        if (!$row_chk || ($row_chk['company'] ?? '') !== $sessionCompany) throw new Exception("Access Denied");
    }

    // Add the extra IDs to the data array
    if ($servicedById) $data['servicedById'] = $servicedById;
    if ($requester_id) $data['requester_id'] = $requester_id;
    unset($data['company']);

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

    $fields = ['id', 'date', 'company', 'department', 'requester', 'servicedBy', 'asset_id', 'problem', 'contact', 'solution', 'status', 'type', 'urgency', 'repair_cost', 'appointment_date', 'servicedById', 'requester_id', 'completed_date', 'closed_date', 'last_work_start_time', 'total_work_duration_seconds', 'evaluation_hardware', 'evaluation_software', 'evaluation_repair_cost', 'evaluation_recommendation', 'evaluation_reason', 'pr_no', 'po_no', 'vendor_name', 'eta_date'];

    if (!$id) throw new Exception("ID is required for update.");

    $conn->begin_transaction();
    try {
        // 1. Fetch current state for time tracking (lock the row for update)
        $stmt_current = $conn->prepare("SELECT status, servicedById, servicedBy, solution, closed_date, completed_date, last_work_start_time, total_work_duration_seconds FROM it_logs WHERE id = ? FOR UPDATE");
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

        // Existing logic for notifications and asset history
        if (isset($data['status'])) {
            $safeId = $conn->real_escape_string($id);
            $ticketQ = $conn->query("SELECT asset_id, requester, problem, servicedBy, company FROM it_logs WHERE id = '$safeId'");
            if ($ticketQ && $ticketRow = $ticketQ->fetch_assoc()) {
                $newStatus = $data['status'];

                // Part 1: Send Telegram Notification
                $requester = $ticketRow['requester'] ?? '-';
                $problem = $ticketRow['problem'] ?? '-';
                $servicedBy = $data['servicedBy'] ?? $ticketRow['servicedBy'] ?? 'N/A';
                $solution = $data['solution'] ?? '-';
                $now = date("d/m/Y H:i");

                $statusInfo = [
                    'Received'          => ['icon' => '📩', 'text' => 'Received'],
                    'PR Submitted'      => ['icon' => '🧾', 'text' => 'PR Submitted'],
                    'PO Issued'         => ['icon' => '📄', 'text' => 'PO Issued'],
                    'Parts Received'    => ['icon' => '📥', 'text' => 'Parts Received'],
                    'In Progress'       => ['icon' => '⏳', 'text' => 'In Progress'],
                    'Waiting for Parts' => ['icon' => '📦', 'text' => 'Waiting for Parts'],
                    'Temporary Fix'     => ['icon' => '🔧', 'text' => 'Temporary Fix'],
                    'Unrepairable'      => ['icon' => '⚠️', 'text' => 'Unrepairable'],
                    'Completed'         => ['icon' => '✅', 'text' => 'Completed'],
                    'Cancelled'         => ['icon' => '🚫', 'text' => 'Cancelled']
                ];

                if (isset($statusInfo[$newStatus])) {
                    $s = $statusInfo[$newStatus];
                    
                    $isReopen = ($current_status === 'Completed' || $current_status === 'Cancelled') && $newStatus === 'In Progress';
                    $headerText = $isReopen ? "🔄 Ticket Reopened ↩️" : "🔄 Update: {$s['text']} {$s['icon']}";

                    $problemRaw = $ticketRow['problem'] ?? '-';
                    $isProgrammer = (strpos($problemRaw, '[PROGRAMMER REQUEST]') !== false);
                    
                    if ($isProgrammer) {
                        $formattedProblem = str_replace(
                            ['[PROGRAMMER REQUEST]', 'Software:', 'Module:', '1. อาการที่พบ:', '2. ความต้องการ:', '3. แหล่งข้อมูล:', '----------------------'],
                            ['<b>💻 Programmer Request</b>', '<b>Software:</b>', '<b>Module:</b>', '<b>1. อาการที่พบ:</b>', '<b>2. ความต้องการ:</b>', '<b>3. แหล่งข้อมูล:</b>', '➖➖➖➖➖➖➖➖➖➖'],
                            $problemRaw
                        );
                        $problemDisplay = "\n" . $formattedProblem;
                    } else {
                        $problemDisplay = " " . $problemRaw;
                    }

                    $msg = "<b>{$headerText}</b>\n" .
                           "➖➖➖➖➖➖➖➖➖➖\n" .
                           "<b>🏢 Company:</b> " . ($ticketRow['company']??'-') . "\n" .
                           "<b>🎫 Ticket:</b> <code>{$id}</code>\n" .
                           "<b>🕒 Time:</b> {$now}\n" .
                           "<b>👤 User:</b> {$requester}\n" .
                           "<b>👨‍🔧 Tech:</b> {$servicedBy}\n" .
                           "➖➖➖➖➖➖➖➖➖➖\n" .
                           "<b>📝 Problem:</b>{$problemDisplay}\n";
                    
                    if ($newStatus === 'Completed' || $newStatus === 'Cancelled' || $newStatus === 'Temporary Fix' || $newStatus === 'Unrepairable' || $isReopen) {
                        $msg .= "<b>💡 Note/Solution:</b> {$solution}\n";
                    }
                    
                    $msg .= "\n#Update #StatusChange";
                    if ($isReopen) $msg .= " #Reopened";
                    
                    sendTelegramNotification($conn, $msg, 'repair');
                }

                // Part 2: Update Asset Status & Log History
                $assetIdStr = $ticketRow['asset_id'];
                if ($assetIdStr) {
                    $assetIdStr = $conn->real_escape_string($assetIdStr);
                    $assetQ = $conn->query("SELECT id FROM it_assets WHERE equipment_id = '$assetIdStr' LIMIT 1");
                    if ($assetQ && $assetRow = $assetQ->fetch_assoc()) {
                        $assetDbId = $assetRow['id'];
                        if ($newStatus === 'In Progress') {
                            $conn->query("UPDATE it_assets SET status = 'ส่งซ่อม' WHERE id = $assetDbId");
                            logAssetHistory($conn, $assetDbId, 'REPAIR_START', "Ticket #$id: Started repair");
                        } elseif ($newStatus === 'Completed') {
                            $conn->query("UPDATE it_assets SET status = 'ใช้งานปกติ' WHERE id = $assetDbId");
                            logAssetHistory($conn, $assetDbId, 'REPAIR_COMPLETE', "Ticket #$id: Repair completed. Solution: " . ($data['solution']??'-'));
                        } elseif ($newStatus === 'Cancelled') {
                            $conn->query("UPDATE it_assets SET status = 'ใช้งานปกติ' WHERE id = $assetDbId AND status = 'ส่งซ่อม'");
                            logAssetHistory($conn, $assetDbId, 'REPAIR_CANCEL', "Ticket #$id: Cancelled");
                        } elseif ($newStatus === 'Unrepairable') {
                            $conn->query("UPDATE it_assets SET status = 'ใช้งานปกติ' WHERE id = $assetDbId AND status = 'ส่งซ่อม'");
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
    } else if ($key === 'kb_articles') {
        $data['is_published'] = isset($data['is_published']) && $data['is_published'] == 1 ? 1 : 0;
        $data['author_id'] = $_SESSION['user_id'] ?? null;
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
    $params[] = $data[$pk] ?? $data['id'];
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) echo json_encode(['status' => 'success']);
    else throw new Exception($stmt->error);
}
?>
