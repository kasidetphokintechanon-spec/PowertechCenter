<?php
// api/actions/login.php

$json_data = json_decode(file_get_contents('php://input'), true);
$username = trim((string)($json_data['username'] ?? ''));
$password = (string)($json_data['password'] ?? '');
$selected_company = trim((string)($json_data['company'] ?? ''));
$remember_me = !empty($json_data['remember_me']);

// ตรวจสอบข้อมูลจากตาราง employees
$stmt = $conn->prepare("SELECT id, username, password, role, name, image, responsible_department FROM employees WHERE (username = ? OR id = ?) AND employment_status = 'active' ORDER BY id LIMIT 1");
if (!$stmt) throw new Exception("Prepare failed (Login): " . $conn->error);
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $storedPassword = (string)($row['password'] ?? '');
    $ok = false;
    if ($storedPassword !== '' && password_verify($password, $storedPassword)) {
        $ok = true;
    } else {
        // Legacy support: allow plain-text or md5 passwords, then upgrade to password_hash.
        $looksHashed = $storedPassword !== '' && ($storedPassword[0] === '$');
        if (!$looksHashed) {
            if ($storedPassword !== '' && hash_equals($storedPassword, $password)) {
                $ok = true;
            } elseif (preg_match('/^[a-f0-9]{32}$/i', $storedPassword) && hash_equals(strtolower($storedPassword), md5($password))) {
                $ok = true;
            }
        }
        
        // Default password support: if DB password is empty, allow using employee id as password once and upgrade.
        if (!$ok && $storedPassword === '' && isset($row['id'])) {
            $default = (string)$row['id'];
            if ($default !== '' && hash_equals($default, (string)$password)) {
                $ok = true;
            }
        }

        if ($ok) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $updHash = $conn->prepare("UPDATE employees SET password = ? WHERE id = ?");
            if ($updHash) {
                $updHash->bind_param("ss", $newHash, $row['id']);
                $updHash->execute();
            }
        }
    }

    if ($ok) {
        // 3. ป้องกัน Session Fixation Attack โดยการเปลี่ยน Session ID ใหม่เมื่อ Login ผ่าน
        session_regenerate_id(true);

        // Remember me token (optional)
        if ($remember_me) {
            $token = bin2hex(random_bytes(32));
            $upd_stmt = $conn->prepare("UPDATE employees SET remember_token = ? WHERE id = ?");
            if ($upd_stmt) {
                $upd_stmt->bind_param("ss", $token, $row['id']);
                $upd_stmt->execute();
            }
            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', isset($_SERVER['HTTPS']), true);
        }

        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = ($row['username'] ?? '') !== '' ? $row['username'] : $row['id'];
        $_SESSION['role'] = $row['role'] ?: 'staff';
        $_SESSION['display_name'] = $row['name'];
        $_SESSION['image'] = $row['image'] ?? null;
        $_SESSION['responsible_department'] = $row['responsible_department'] ?? null;

        // Find primary assignment and store in session
        $user_id = $row['id'];
        $company_set = false;

        // 1. ถ้ามีการเลือกบริษัทมา ให้ตรวจสอบสิทธิ์และใช้บริษัทนั้น
        if (!empty($selected_company)) {
            $chk_stmt = $conn->prepare("SELECT company, department FROM employee_assignments WHERE employee_id = ? AND company = ?");
            $chk_stmt->bind_param("ss", $user_id, $selected_company);
            $chk_stmt->execute();
            $chk_res = $chk_stmt->get_result();
            if ($chk_row = $chk_res->fetch_assoc()) {
                $_SESSION['company'] = $chk_row['company'];
                $_SESSION['primary_department'] = $chk_row['department'];
                $company_set = true;
            }
        }

        // 2. ถ้าไม่ได้เลือก หรือเลือกแล้วไม่มีสิทธิ์ ให้ใช้บริษัทหลัก (Primary)
        if (!$company_set) {
            $assign_stmt = $conn->prepare("SELECT company, department FROM employee_assignments WHERE employee_id = ? AND is_primary = 1 LIMIT 1");
            if ($assign_stmt) {
                $assign_stmt->bind_param("s", $user_id);
                $assign_stmt->execute();
                $assign_result = $assign_stmt->get_result();
                if ($assign_row = $assign_result->fetch_assoc()) {
                    $_SESSION['company'] = $assign_row['company'];
                    $_SESSION['primary_department'] = $assign_row['department'];
                } else {
                    // Fallback: get first assignment if no primary is set
                    $assign_stmt_fallback = $conn->prepare("SELECT company, department FROM employee_assignments WHERE employee_id = ? ORDER BY assignment_id LIMIT 1");
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

        if (empty($_SESSION['company']) && !empty($selected_company) && ($_SESSION['role'] ?? '') === 'admin') {
            $_SESSION['company'] = $selected_company;
        }

        // แปลงรหัสบริษัทเป็นชื่อเต็มสำหรับ Response ตอน Login
        $company_code = $_SESSION['company'] ?? null;
        $company_full = $company_code;
        $company_map = [
            'PTA' => 'Powertech Engine Assembly Co., Ltd.',
            'PT4' => 'Powertech 2004 Co., Ltd.',
            'PTE' => 'Powertech Energy Solutions Co., Ltd.'
        ];
        if (isset($company_map[$company_code])) {
            $company_full = $company_map[$company_code];
        }

        echo json_encode([
            'status' => 'success', 
            'role' => $_SESSION['role'],
            'company' => $company_code,
            'company_full_name' => $company_full
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านไม่ถูกต้อง']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบชื่อผู้ใช้งานนี้']);
}
?>
