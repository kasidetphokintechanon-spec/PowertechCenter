<?php
/**
 * auto_login.php
 * Endpoint สำหรับ Windows Forms WebView2 Auto-Login
 * รับ empCode + company แล้วสร้าง Session และ Redirect ไปหน้าที่ต้องการ
 *
 * Security: ใช้ HMAC-SHA256 + Timestamp เพื่อป้องกัน Replay Attack
 */

error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/config.php';

// ── Token หมดอายุภายใน 90 วินาที ─────────────────────────────────────────────
define('TOKEN_TTL_SECONDS', 90);

// ── บริษัทที่อนุญาต ───────────────────────────────────────────────────────────
$COMPANY_MAP = [
    'PTA' => 'Powertech Engine Assembly Co., Ltd.',
    'PT4' => 'Powertech 2004 Co., Ltd.',
    'PTE' => 'Powertech Energy Solutions Co., Ltd.',
];

// ── Helper: Redirect พร้อม error ─────────────────────────────────────────────
function failRedirect(string $msg): void {
    // Redirect กลับ index.html พร้อม error query string
    header('Location: index.html?auto_login_error=' . urlencode($msg));
    exit;
}

// ── 1. รับ Parameters ─────────────────────────────────────────────────────────
$empCode   = trim($_GET['empCode']   ?? '');
$company   = strtoupper(trim($_GET['company'] ?? ''));
$ts        = trim($_GET['ts']        ?? '');
$sig       = trim($_GET['sig']       ?? '');
$returnUrl = trim($_GET['returnUrl'] ?? '');

// ── 2. ตรวจสอบ Parameters เบื้องต้น ─────────────────────────────────────────
if ($empCode === '' || $ts === '' || $sig === '') {
    failRedirect('Missing parameters');
}

// ── 3. ตรวจสอบ Timestamp (ป้องกัน Replay Attack) ──────────────────────────────
$tsInt = (int)$ts;
$now   = time();
if (abs($now - $tsInt) > TOKEN_TTL_SECONDS) {
    failRedirect('Token expired');
}

// ── 4. ตรวจสอบ HMAC Signature ─────────────────────────────────────────────────
$payload      = $empCode . '|' . $company . '|' . $ts;
$expectedSig  = hash_hmac('sha256', $payload, AUTO_LOGIN_SECRET);
if (!hash_equals($expectedSig, strtolower($sig))) {
    failRedirect('Invalid signature');
}

// ── 5. ตรวจสอบ company ────────────────────────────────────────────────────────
if ($company !== '' && !isset($COMPANY_MAP[$company])) {
    failRedirect('Invalid company');
}

// ── 6. เชื่อมต่อ Database ─────────────────────────────────────────────────────

$hosts = array_values(array_unique(array_filter([DB_HOST, 'localhost', '127.0.0.1'])));
$conn  = null;
foreach ($hosts as $host) {
    $c = mysqli_init();
    if (!$c) continue;
    $c->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    @$c->real_connect($host, DB_USER, DB_PASS, DB_NAME);
    if (!$c->connect_error) { $conn = $c; break; }
}
if (!$conn) {
    failRedirect('DB connection failed');
}
$conn->set_charset('utf8mb4');

// ── 7. ค้นหา Employee ─────────────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT id, username, role, name, image, responsible_department, can_manage_stock
     FROM employees
     WHERE id = ? AND employment_status = 'active'
     LIMIT 1"
);
if (!$stmt) { failRedirect('DB prepare error'); }
$stmt->bind_param('s', $empCode);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    failRedirect('Employee not found or inactive');
}

// ── 8. ตั้งค่า Session ────────────────────────────────────────────────────────
session_regenerate_id(true);

$_SESSION['user_id']      = $row['id'];
$_SESSION['username']     = ($row['username'] ?? '') !== '' ? $row['username'] : $row['id'];
$_SESSION['role']         = $row['role'] ?: 'staff';
$_SESSION['display_name'] = $row['name'];
$_SESSION['image']        = $row['image'] ?? null;
$_SESSION['responsible_department'] = $row['responsible_department'] ?? null;
$_SESSION['can_manage_stock']       = intval($row['can_manage_stock'] ?? 0);

// ── 9. กำหนด Company จาก Parameter หรือ Primary Assignment ──────────────────
$companySet = false;

if ($company !== '') {
    // ตรวจสอบสิทธิ์ว่า employee มี assignment ในบริษัทที่ส่งมา
    $chk = $conn->prepare(
        "SELECT company, department FROM employee_assignments
         WHERE employee_id = ? AND company = ? LIMIT 1"
    );
    if ($chk) {
        $chk->bind_param('ss', $row['id'], $company);
        $chk->execute();
        $chkRow = $chk->get_result()->fetch_assoc();
        if ($chkRow) {
            $_SESSION['company']            = $chkRow['company'];
            $_SESSION['primary_department'] = $chkRow['department'];
            $companySet = true;
        }
    }
    // admin ข้ามสิทธิ์ได้
    if (!$companySet && $_SESSION['role'] === 'admin') {
        $_SESSION['company'] = $company;
        $companySet = true;
    }
}

if (!$companySet) {
    // ใช้ Primary Assignment
    $asgn = $conn->prepare(
        "SELECT company, department FROM employee_assignments
         WHERE employee_id = ? AND is_primary = 1 LIMIT 1"
    );
    if ($asgn) {
        $asgn->bind_param('s', $row['id']);
        $asgn->execute();
        $asgnRow = $asgn->get_result()->fetch_assoc();
        if ($asgnRow) {
            $_SESSION['company']            = $asgnRow['company'];
            $_SESSION['primary_department'] = $asgnRow['department'];
            $companySet = true;
        }
    }
}

if (!$companySet) {
    // Fallback: บริษัทแรกที่เจอ
    $fb = $conn->prepare(
        "SELECT company, department FROM employee_assignments
         WHERE employee_id = ? ORDER BY assignment_id LIMIT 1"
    );
    if ($fb) {
        $fb->bind_param('s', $row['id']);
        $fb->execute();
        $fbRow = $fb->get_result()->fetch_assoc();
        if ($fbRow) {
            $_SESSION['company']            = $fbRow['company'];
            $_SESSION['primary_department'] = $fbRow['department'];
        }
    }
}

$conn->close();

// ── 10. Redirect ─────────────────────────────────────────────────────────────
// ตรวจสอบ returnUrl (อนุญาตเฉพาะ URL ภายในเว็บไซต์นี้)
$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

if ($returnUrl !== '') {
    // ป้องกัน Open Redirect: ต้องขึ้นต้นด้วย basePath หรือเป็น relative path
    $absReturn = (strpos($returnUrl, 'http') === 0) ? $returnUrl : $baseUrl . '/' . ltrim($returnUrl, '/');
    $parsedReturn = parse_url($absReturn, PHP_URL_HOST);
    $parsedBase   = parse_url($baseUrl, PHP_URL_HOST);

    if ($parsedReturn !== null && $parsedReturn !== $parsedBase) {
        // ไม่ใช่ host เดียวกัน — ส่งกลับหน้าหลักแทน
        header('Location: ' . $basePath . '/log.html');
    } else {
        header('Location: ' . $absReturn);
    }
} else {
    header('Location: ' . $basePath . '/log.html');
}
exit;
?>
