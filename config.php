<?php
// config.php

// เริ่ม Session อัตโนมัติถ้ายังไม่ได้เริ่ม (สำคัญสำหรับ update_db.php และไฟล์อื่นๆ)
if (session_status() === PHP_SESSION_NONE) {
    // ตั้งค่า Cookie Parameters เพื่อความปลอดภัย (HttpOnly, Secure, SameSite)
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'],
        'path' => $cookieParams['path'],
        'domain' => $cookieParams['domain'],
        'secure' => isset($_SERVER['HTTPS']), // ใช้ Secure เมื่อเป็น HTTPS เท่านั้น
        'httponly' => true, // ป้องกัน JavaScript เข้าถึง Cookie (ลดความเสี่ยง XSS)
        'samesite' => 'Lax' // ป้องกัน CSRF เบื้องต้น
    ]);
    session_start();
}

// ป้องกันการเข้าถึงไฟล์นี้โดยตรงจากเบราว์เซอร์
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    die('Access Denied');
}

// กำหนดค่าคงที่สำหรับการเชื่อมต่อฐานข้อมูล
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'powertech_system');

ini_set('default_socket_timeout', '3');

// --- Auto-Login Logic (Remember Me) ---

// ถ้าไม่มี Session แต่มี Cookie remember_token ให้ทำการ Login อัตโนมัติ
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $conn_check = null;
    $hosts = array_values(array_unique(array_filter([DB_HOST, 'localhost', '127.0.0.1', '::1'])));
    foreach ($hosts as $host) {
        $candidate = mysqli_init();
        if (!$candidate) continue;
        $candidate->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
        if (defined('MYSQLI_OPT_READ_TIMEOUT')) $candidate->options(MYSQLI_OPT_READ_TIMEOUT, 3);
        if (defined('MYSQLI_OPT_WRITE_TIMEOUT')) $candidate->options(MYSQLI_OPT_WRITE_TIMEOUT, 3);
        @$candidate->real_connect($host, DB_USER, DB_PASS, DB_NAME);
        if (!$candidate->connect_error) {
            $conn_check = $candidate;
            break;
        }
    }
    
    if ($conn_check && !$conn_check->connect_error) {
        $stmt = $conn_check->prepare("SELECT id, username, role, name, image, responsible_department FROM employees WHERE remember_token = ? AND employment_status = 'active' LIMIT 1");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($row = $res->fetch_assoc()) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'] ?: 'staff';
            $_SESSION['display_name'] = $row['name'];
            $_SESSION['image'] = $row['image'];
            $_SESSION['responsible_department'] = $row['responsible_department'];
            
            $user_id = $row['id'];
            $assign_stmt = $conn_check->prepare("SELECT company, department FROM employee_assignments WHERE employee_id = ? AND is_primary = 1 LIMIT 1");
            if ($assign_stmt) {
                $assign_stmt->bind_param("s", $user_id);
                $assign_stmt->execute();
                $assign_result = $assign_stmt->get_result();
                if ($assign_row = $assign_result->fetch_assoc()) {
                    $_SESSION['company'] = $assign_row['company'];
                    $_SESSION['primary_department'] = $assign_row['department'];
                } else {
                    $assign_stmt_fallback = $conn_check->prepare("SELECT company, department FROM employee_assignments WHERE employee_id = ? ORDER BY assignment_id LIMIT 1");
                    if ($assign_stmt_fallback) {
                        $assign_stmt_fallback->bind_param("s", $user_id);
                        $assign_stmt_fallback->execute();
                        $assign_result_fallback = $assign_stmt_fallback->get_result();
                        if ($assign_row_fallback = $assign_result_fallback->fetch_assoc()) {
                            $_SESSION['company'] = $assign_row_fallback['company'];
                            $_SESSION['primary_department'] = $assign_row_fallback['department'];
                        }
                    }
                }
            }
        }
        $conn_check->close();
    }
}
?>
