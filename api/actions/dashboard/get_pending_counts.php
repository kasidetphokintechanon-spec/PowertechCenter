<?php
// api/actions/dashboard/get_pending_counts.php

$room_pending = 0; $room_prep = 0; $stock_pending = 0; $loan_count = 0; $repair_count = 0; $booking_count = 0; $stock_low = 0; $users_no_role = 0; $kb_draft_count = 0; $sc_missing = 0;

try {
    if (isset($conn) && $conn instanceof mysqli) {
        $sessionCompany = $_SESSION['company'] ?? '';
        $role = $_SESSION['role'] ?? 'guest';

        // Loan (สินทรัพย์ที่รออนุมัติยืม)
        _initLoanTables($conn);
        // นับเฉพาะบริษัทที่ดูแล (Admin กลาง = ทุกบริษัท · ตั้งในหน้ากำหนดสิทธิ์)
        [$cw, $cp] = scopeSql('asset_company', userCompanies($conn));
        $stmt = $conn->prepare("SELECT COUNT(*) FROM it_loan_requests WHERE (status = 'requested' OR (status = 'active' AND extend_status = 'pending')){$cw}");
        if ($stmt) { if ($cp) $stmt->bind_param(str_repeat('s', count($cp)), ...$cp); $stmt->execute(); $loan_count = (int)$stmt->get_result()->fetch_row()[0]; }

        // Repair (ตั๋วซ่อม status=Pending ยังไม่รับงาน)
        $sql = "SELECT COUNT(*) as count FROM it_logs WHERE status = 'Pending'";
        if ($role !== 'admin' && !empty($sessionCompany)) {
            $sql .= " AND company = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) { $stmt->bind_param("s", $sessionCompany); $stmt->execute(); $res = $stmt->get_result(); if ($res && $row = $res->fetch_assoc()) $repair_count = (int)$row['count']; }
        } else {
            if ($res = $conn->query($sql)) $repair_count = (int)$res->fetch_assoc()['count'];
        }

        // จองห้อง/รถ ย้ายไปใช้ roomsys / carsys แล้ว — booking คงไว้เป็น 0 ให้หน้าที่ยังอ่านค่านี้ไม่พัง

        // ห้องประชุม: งานของผู้ดูแลห้อง (นับเฉพาะห้องที่ดูแล)
        if (function_exists('roomCanManage') && roomCanManage($conn)) {
            _initRoomTables($conn);
            ['pending' => $room_pending, 'prep' => $room_prep] = roomKeeperTodo($conn);
        }

        // Stock low (items ที่ balance <= min_stock)
        $stockSql = "SELECT COUNT(*) AS count FROM (
            SELECT si.id
            FROM stock_items si
            LEFT JOIN stock_transactions st ON st.item_id = si.id
            WHERE si.min_stock > 0 AND si.is_active = 1";
        $stockParams = []; $stockTypes = '';
        if ($role !== 'admin' && !empty($sessionCompany)) {
            $stockSql .= " AND si.company = ?";
            $stockParams[] = $sessionCompany; $stockTypes .= 's';
        }
        $stockSql .= " GROUP BY si.id
            HAVING COALESCE(SUM(CASE WHEN st.type='received' THEN st.quantity WHEN st.type='issued' THEN -st.quantity WHEN st.type='adjusted' THEN st.quantity ELSE 0 END), 0) <= si.min_stock
        ) AS low_items";
        if ($stockTypes) {
            $stmt = $conn->prepare($stockSql);
            if ($stmt) { $stmt->bind_param($stockTypes, ...$stockParams); $stmt->execute(); $res = $stmt->get_result(); if ($res && $row = $res->fetch_assoc()) $stock_low = (int)$row['count']; }
        } else {
            if ($res = $conn->query($stockSql)) $stock_low = (int)$res->fetch_assoc()['count'];
        }

        // ใบเบิกของรออนุมัติ — ตัวเลขบนเมนู "สต็อก & เบิกของ" (งานที่ผู้ดูแลต้องทำ)
        [$cw, $cp] = scopeSql('company', userCompanies($conn));
        $stmt = $conn->prepare("SELECT COUNT(*) FROM stock_requests WHERE status = 'pending'{$cw}");
        if ($stmt) { if ($cp) $stmt->bind_param(str_repeat('s', count($cp)), ...$cp); $stmt->execute(); $stock_pending = (int)$stmt->get_result()->fetch_row()[0]; }

        // Users no role (admin only — employees ที่ยังไม่มี role)
        if ($role === 'admin') {
            if ($res = $conn->query("SELECT COUNT(*) AS count FROM employees WHERE role IS NULL OR role = ''")) {
                $users_no_role = (int)$res->fetch_assoc()['count'];
            }
        }

        // ห้อง Server ที่ยังไม่ตรวจวันนี้ (เฉพาะวันทำงาน) — ตัวเลขบนเมนู "ตรวจห้อง Server"
        if (!function_exists('_notifyIsWorkday') || _notifyIsWorkday($conn, date('Y-m-d'))) {
            $today = date('Y-m-d');
            $st = $conn->prepare("SELECT COUNT(*) FROM sc_rooms r WHERE r.is_active = 1 AND NOT EXISTS (
                SELECT 1 FROM sc_records c JOIN sc_values v ON v.record_id = c.id
                WHERE c.room_id = r.id AND c.check_date = ? AND v.val IS NOT NULL AND v.val <> '')");
            if ($st) { $st->bind_param('s', $today); $st->execute(); $sc_missing = (int)$st->get_result()->fetch_row()[0]; }
        }

        // KB drafts รอตรวจ (สร้างอัตโนมัติจากการปิด ticket — ยังไม่เผยแพร่)
        if ($res = $conn->query("SELECT COUNT(*) AS count FROM kb_articles WHERE is_published = 0")) {
            $kb_draft_count = (int)$res->fetch_assoc()['count'];
        }
    }
} catch (Throwable $e) {
    // ป้องกัน 500 - ถ้า error ให้ส่งค่า 0 ทั้งหมด
}

echo json_encode([
    'status'             => 'success',
    'loan_pending'       => $loan_count,
    'ticket_unassigned'  => $repair_count,
    'stock_low'          => $stock_low,
    'stock_pending'      => $stock_pending,
    'users_no_role'      => $users_no_role,
    'kb_draft_count'     => $kb_draft_count,
    'server_check_missing' => $sc_missing,
    'room_pending'       => $room_pending,
    'room_prep'          => $room_prep,
    // backward-compat keys
    'loan'               => $loan_count,
    'repair'             => $repair_count,
    'booking'            => $booking_count,
    'loan_pending_count' => $loan_count,
    'repair_pending_count' => $repair_count,
]);
?>
