<?php
// api/actions/check_session.php

// แปลงรหัสบริษัทเป็นชื่อเต็ม
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

// ดึงรายชื่อบริษัทที่ User นี้มีสิทธิ์เข้าถึง
$available_companies = [];
if (isset($_SESSION['user_id'])) {
    $stmt_comps = $conn->prepare("SELECT company FROM employee_assignments WHERE employee_id = ?");
    $stmt_comps->bind_param("s", $_SESSION['user_id']);
    $stmt_comps->execute();
    $res_comps = $stmt_comps->get_result();
    while ($row_c = $res_comps->fetch_assoc()) {
        $available_companies[] = $row_c['company'];
    }
}

echo json_encode([
    'loggedin' => isset($_SESSION['user_id']),
    'user_id' => $_SESSION['user_id'] ?? null,
    'username' => $_SESSION['username'] ?? null,
    'role' => $_SESSION['role'] ?? 'guest',
    'display_name' => $_SESSION['display_name'] ?? 'Guest',
    'company' => $company_code,
    'company_full_name' => $company_full, // ส่งชื่อเต็มกลับไปด้วย
    'available_companies' => $available_companies,
    'primary_department' => $_SESSION['primary_department'] ?? null,
    'image' => $_SESSION['image'] ?? null
]);
?>