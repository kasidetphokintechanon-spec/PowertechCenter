<?php
// api/actions/get_last_update_timestamp.php
// ส่งคืนเวลาปัจจุบันเพื่อใช้ตรวจสอบว่าข้อมูลมีการอัปเดตหรือไม่ (ใช้ใน Directory)
echo json_encode(['status' => 'success', 'last_update' => date('Y-m-d H:i:s')]);
?>