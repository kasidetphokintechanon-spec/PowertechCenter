<?php
// api/actions/get_pending_counts.php

$loan_count = 0; $repair_count = 0; $booking_count = 0;

try {
    if (isset($conn) && $conn instanceof mysqli) {
        $sessionCompany = $_SESSION['company'] ?? '';
        $role = $_SESSION['role'] ?? 'guest';

        // Loan (สินทรัพย์ที่รออนุมัติยืม)
        $sql = "SELECT COUNT(*) as count FROM it_assets WHERE status = 'รออนุมัติ'";
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
            if ($res = $conn->query($sql)) {
                $loan_count = (int)$res->fetch_assoc()['count'];
            }
        }

        // Repair (ตั๋วซ่อมรอจัดการ)
        $sql = "SELECT COUNT(*) as count FROM it_logs WHERE status = 'Pending'";
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
            if ($res = $conn->query($sql)) {
                $repair_count = (int)$res->fetch_assoc()['count'];
            }
        }

        // Booking (การจองรออนุมัติ)
        // ใช้ join กับ meeting_rooms เพื่อกรองบริษัทได้แม่นยำ
        $sql = "SELECT COUNT(*) as count FROM bookings b JOIN meeting_rooms r ON b.room_id = r.id WHERE b.status = 'Pending'";
        if ($role !== 'admin' && !empty($sessionCompany)) {
            $sql .= " AND r.company = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $sessionCompany);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $row = $res->fetch_assoc()) $booking_count = (int)$row['count'];
            }
        } else {
            if ($res = $conn->query($sql)) {
                $booking_count = (int)$res->fetch_assoc()['count'];
            }
        }
    }
} catch (Throwable $e) {
    // ป้องกัน 500 - ถ้า error ให้ส่งค่า 0 ทั้งหมด
}

echo json_encode([
    'status' => 'success',
    'loan' => $loan_count, 'repair' => $repair_count, 'booking' => $booking_count,
    'loan_pending_count' => $loan_count,
    'repair_pending_count' => $repair_count
]);
?>
