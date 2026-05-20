<?php
// api/actions/system/get_pending_counts.php

// ไม่ต้องใส่ header('Content-Type: application/json'); เพราะ api.php ใส่ให้แล้ว

// ตรวจสอบสิทธิ์ (Admin/Staff only)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

// 1. Count Pending Repairs
$repair_count = 0;
try {
    $sessionCompany = $_SESSION['company'] ?? '';
    $role = $_SESSION['role'] ?? 'guest';
    if (isset($conn) && $conn instanceof mysqli) {
        $sql = "SELECT COUNT(*) as count FROM it_logs WHERE status = 'Pending'";
        $stmt = null;
        if ($role !== 'admin' && !empty($sessionCompany)) {
            $sql .= " AND company = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $sessionCompany);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $row = $res->fetch_assoc()) $repair_count = (int)$row['count'];
            }
        } else {
            $res = $conn->query($sql);
            if ($res && $row = $res->fetch_assoc()) $repair_count = (int)$row['count'];
        }
    }
} catch (Exception $e) {
    // กรณีเกิด Error ให้ข้ามไป (ค่าเป็น 0)
}

// 2. Count Pending Loans
$loan_count = 0;
try {
    $sessionCompany = $_SESSION['company'] ?? '';
    $role = $_SESSION['role'] ?? 'guest';
    if (isset($conn) && $conn instanceof mysqli) {
        $sql = "SELECT COUNT(*) as count FROM it_assets WHERE status = 'รออนุมัติ'";
        $stmt = null;
        if ($role !== 'admin' && !empty($sessionCompany)) {
            $sql .= " AND company = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $sessionCompany);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $row = $res->fetch_assoc()) $loan_count = (int)$row['count'];
            }
        } else {
            $res = $conn->query($sql);
            if ($res && $row = $res->fetch_assoc()) $loan_count = (int)$row['count'];
        }
    }
} catch (Exception $e) {
    // Ignore error
}

echo json_encode([
    'status' => 'success',
    'repair_pending_count' => $repair_count,
    'loan_pending_count' => $loan_count
]);
?>
