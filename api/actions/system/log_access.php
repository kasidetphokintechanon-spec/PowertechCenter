<?php
// api/actions/system/log_access.php

$page = $_POST['page'] ?? '';

if (!empty($page)) {
    // บันทึกการเข้าใช้งานลงใน asset_history (ใช้เป็น Audit Log รวม)
    // asset_db_id = 0 หมายถึง System/General Log
    $details = "เข้าชมหน้า: " . $page;
    
    // เรียกใช้ฟังก์ชัน logAssetHistory ที่ประกาศไว้ใน api.php
    logAssetHistory($conn, 0, 'ACCESS', $details);
    
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'ignored']);
}
?>