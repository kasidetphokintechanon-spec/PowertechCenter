<?php
// api/actions/booking/cancel.php

// ตรวจสอบว่ามีการ Login หรือไม่
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$booking_id = $_POST['id'] ?? '';
$reason = $_POST['reason'] ?? '';

if (empty($booking_id)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสการจอง']);
    exit;
}

// ดึงข้อมูลการจอง
$sql = "SELECT id, title, booker_id, status, start_time, end_time, booking_ref FROM bookings WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลการจอง']);
    exit;
}

$booking = $result->fetch_assoc();

// ตรวจสอบสิทธิ์ (เจ้าของ หรือ Admin/Staff)
if ($booking['booker_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff') {
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ยกเลิกการจองนี้']);
    exit;
}

$current_status = $booking['status'];
$new_status = '';
$msg = '';

if ($current_status == 'Pending') {
    // ถ้ารออนุมัติ -> ยกเลิกได้ทันที
    $new_status = 'Cancelled';
    $msg = 'ยกเลิกการจองเรียบร้อยแล้ว';
} else if ($current_status == 'Confirmed') {
    // ถ้ายืนยันแล้ว -> เปลี่ยนเป็น Cancellation Requested
    $new_status = 'Cancellation Requested';
    $msg = 'ส่งคำขอยกเลิกเรียบร้อยแล้ว กรุณารอเจ้าหน้าที่อนุมัติ';
} else if ($current_status == 'Cancellation Requested') {
    echo json_encode(['status' => 'error', 'message' => 'รายการนี้ได้ส่งคำขอยกเลิกไปแล้ว']);
    exit;
} else if ($current_status == 'Cancelled' || $current_status == 'Rejected') {
    echo json_encode(['status' => 'error', 'message' => 'รายการนี้ถูกยกเลิกหรือปฏิเสธไปแล้ว']);
    exit;
} else {
    echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถยกเลิกรายการที่มีสถานะ ' . $current_status . ' ได้']);
    exit;
}

// อัปเดตสถานะ
$update_sql = "UPDATE bookings SET status = ? WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("si", $new_status, $booking_id);

if ($update_stmt->execute()) {
    // แจ้งเตือน Telegram หากเป็นการขอ Cancel
    if ($new_status == 'Cancellation Requested' && function_exists('sendTelegramNotification')) {
        $notify_msg = "<b>⚠️ Cancellation Requested</b>\n<b>Ref:</b> " . ($booking['booking_ref'] ?? $booking['id']) . "\n<b>Title:</b> " . $booking['title'] . "\n<b>By:</b> " . $_SESSION['display_name'];
        sendTelegramNotification($conn, $notify_msg, 'booking');
    }
    echo json_encode(['status' => 'success', 'message' => $msg, 'new_status' => $new_status]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล']);
}
?>