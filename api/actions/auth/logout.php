<?php
// api/actions/logout.php

// 2. ล้างค่า Session Array และลบ Cookie ฝั่ง Client เพื่อการ Logout ที่สมบูรณ์
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
echo json_encode(['status' => 'success']);
?>