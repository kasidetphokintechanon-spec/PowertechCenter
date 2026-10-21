<?php
// api/actions/loan/requestEquipment.php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $raw = $_POST['data'] ?? '';
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new Exception('ข้อมูลไม่ถูกต้อง');
    }

    $eqId = trim($data['id'] ?? '');
    $borrower = trim($data['borrowerName'] ?? '');
    $dept = trim($data['department'] ?? '');
    $empId = trim($data['employeeId'] ?? '');
    $rDate = trim($data['returnDate'] ?? '');
    $noDueDate = !empty($data['noDueDate']);
    $purpose = trim($data['purpose'] ?? '');

    if ($eqId === '') {
        throw new Exception('ไม่พบรหัสอุปกรณ์');
    }
    if ($borrower === '' || $purpose === '' || (!$noDueDate && $rDate === '')) {
        throw new Exception('กรุณากรอกข้อมูลให้ครบทุกช่อง');
    }
    if ($noDueDate) {
        $rDate = null;
    } else {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rDate)) {
            throw new Exception('รูปแบบวันที่คืนไม่ถูกต้อง');
        }
        if ($rDate < date('Y-m-d')) {
            throw new Exception('วันที่คืนต้องไม่เป็นวันในอดีต');
        }
    }

    $stmt = $conn->prepare(
        "UPDATE it_assets 
         SET status = 'รออนุมัติ', borrower = ?, borrower_id = ?, department = ?, return_date = ?, purpose = ? 
         WHERE equipment_id = ? AND status IN ('ใช้งานปกติ', 'ว่าง')"
    );
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('ssssss', $borrower, $empId, $dept, $rDate, $purpose, $eqId);

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    if ($stmt->affected_rows <= 0) {
        throw new Exception('อุปกรณ์นี้ไม่ว่างหรือถูกจองแล้ว');
    }

    $res = $conn->prepare('SELECT id, name, company FROM it_assets WHERE equipment_id = ? LIMIT 1');
    if ($res) {
        $res->bind_param('s', $eqId);
        $res->execute();
        $row = $res->get_result()->fetch_assoc();
        if ($row) {
            logAssetHistory($conn, (int)$row['id'], 'LOAN_REQUEST', "Requested by $borrower for $purpose");

            $msg = "<b>📦 New Loan Request</b>\n" .
                '<b>🏢 Company:</b> ' . ($row['company'] ?? '-') . "\n" .
                "<b>Asset:</b> {$row['name']} ({$eqId})\n" .
                "<b>User:</b> {$borrower}\n" .
                "<b>Dept:</b> {$dept}\n" .
                '<b>Return:</b> ' . ($rDate ? date('d/m/Y', strtotime($rDate)) : 'ไม่มีกำหนดคืน') . "\n" .
                "<b>Purpose:</b> {$purpose}";
            sendTelegramNotification($conn, $msg, 'loan');
        }
    }

    echo json_encode(['status' => 'success']);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
