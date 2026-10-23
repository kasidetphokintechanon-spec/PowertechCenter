<?php
// api/actions/room/room_my.php — การจองของฉัน: ที่จะถึง + ย้อนหลัง 90 วัน (รวมที่ยกเลิก)
_initRoomTables($conn);
$uid = (string)$_SESSION['user_id'];
$st = $conn->prepare("SELECT b.*, r.name AS room_name, r.building, r.contact_name, r.contact_phone FROM rm_bookings b JOIN rm_rooms r ON r.id = b.room_id
    WHERE b.booker_id = ? AND b.end_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) ORDER BY (b.end_at >= NOW() AND b.status = 'booked') DESC, IF(b.end_at >= NOW(), b.start_at, NULL) ASC, b.start_at DESC LIMIT 100");
$st->bind_param('s', $uid); $st->execute();
echo json_encode(['status' => 'success', 'now' => date('Y-m-d H:i:s'), 'data' => array_map('roomRow', $st->get_result()->fetch_all(MYSQLI_ASSOC))], JSON_UNESCAPED_UNICODE);
