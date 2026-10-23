<?php
/**
 * access_helper.php — "ดูแลบริษัทไหน" ของเจ้าหน้าที่ (ใช้กับหน้าที่ที่แยกตามบริษัท: งานช่าง IT, ทรัพย์สิน/ใบติดตั้ง, สต็อก, ยืม-คืน)
 *
 * admin (Admin กลาง) = ทุกบริษัท · เจ้าหน้าที่ = บริษัทที่ติ๊กไว้ในหน้ากำหนดสิทธิ์ (user_company_scope)
 * ยังไม่ได้ติ๊กเลย = บริษัทของตัวเองที่เลือกตอนล็อกอิน (เหมือนก่อนมีหน้านี้)
 * พนักงานทั่วไป เบิก/ยืมได้เฉพาะบริษัทตัวเองเสมอ — ไม่ใช้ตัวนี้
 */

const ACCESS_COS = ['PTA', 'PT4', 'PTE'];

function _initAccessTables($conn) {
    static $done = false;
    if ($done) return;
    $done = true;
    $conn->query("CREATE TABLE IF NOT EXISTS user_company_scope (
        user_id  VARCHAR(50) NOT NULL,
        company  VARCHAR(10) NOT NULL,
        PRIMARY KEY (user_id, company)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** บริษัทที่บัญชีนี้ดูแลได้ */
function userCompanies($conn, $userId = null) {
    static $cache = [];
    $uid = (string)($userId ?? ($_SESSION['user_id'] ?? ''));
    if (isset($cache[$uid])) return $cache[$uid];
    if ($userId === null && ($_SESSION['role'] ?? '') === 'admin') return $cache[$uid] = ACCESS_COS;
    _initAccessTables($conn);
    $st = $conn->prepare("SELECT company FROM user_company_scope WHERE user_id = ?");
    $st->bind_param('s', $uid); $st->execute();
    $cos = array_values(array_intersect(ACCESS_COS, array_column($st->get_result()->fetch_all(), 0)));
    if (!$cos && $userId === null) { $mine = (string)($_SESSION['company'] ?? ''); $cos = in_array($mine, ACCESS_COS, true) ? [$mine] : []; }
    return $cache[$uid] = $cos;
}

/** ตัวกรองบริษัทของหน้าจัดการ: เลือกบริษัทที่ดูแลได้ → บริษัทนั้น · ไม่เลือก/เลือกนอกสิทธิ์ → ทุกบริษัทที่ดูแล */
function scopeCompanies($conn, $want = '') {
    $all = userCompanies($conn);
    return in_array($want, $all, true) ? [$want] : $all;
}

function inScope($conn, $company) { return in_array((string)$company, userCompanies($conn), true); }

/** "AND col IN (?,?)" + ค่าที่ต้อง bind · ไม่มีบริษัทเลย = ไม่เห็นอะไร */
function scopeSql($col, array $cos) {
    if (!$cos) return [' AND 1 = 0', []];
    return [" AND {$col} IN (" . implode(',', array_fill(0, count($cos), '?')) . ')', $cos];
}

/**
 * หน้าที่ในหน้ากำหนดสิทธิ์ (การ์ดภาษาไทย) → สิทธิ์ในระบบ · scoped = ใช้ "บริษัทที่ดูแล"
 * มีหน้าที่อย่างน้อย 1 ข้อ → ได้ admin.access_admin (เข้าหน้าระบบจัดการ) อัตโนมัติ
 */
const ACCESS_DUTIES = [
    'it'       => ['t' => 'ช่าง IT', 'd' => 'รับงานซ่อม ตรวจห้อง Server ติดตั้ง/คืนเครื่อง ดูรายงาน IT', 'perms' => ['it_log.access', 'it_report.access'], 'scoped' => true],
    'asset'    => ['t' => 'ดูแลทรัพย์สิน IT', 'd' => 'ทะเบียนพัสดุ หมวดหมู่ สถานที่', 'perms' => ['asset.manage'], 'scoped' => true],
    'stock'    => ['t' => 'ดูแลสต็อก & เบิกของ', 'd' => 'อนุมัติ จ่ายของ รับเข้า นับสต็อก', 'perms' => ['stock.manage'], 'scoped' => true],
    'loan'     => ['t' => 'ดูแลยืม-คืนอุปกรณ์', 'd' => 'อนุมัติ ส่งมอบ รับคืน', 'perms' => ['loan.manage'], 'scoped' => true],
    'employee' => ['t' => 'ดูแลข้อมูลพนักงาน', 'd' => 'พนักงาน แผนก ตำแหน่ง ประกาศ วันหยุด', 'perms' => ['employee.manage'], 'scoped' => false],
    'kb'       => ['t' => 'ดูแลคลังความรู้', 'd' => 'เขียน/แก้บทความวิธีแก้ปัญหา', 'perms' => ['kb.manage'], 'scoped' => false],
    'notify'   => ['t' => 'ตั้งค่าแจ้งเตือน', 'd' => 'LINE / Telegram ใครได้รับเรื่องอะไร', 'perms' => ['settings.notifications'], 'scoped' => false],
    'audit'    => ['t' => 'ตรวจสอบ (Audit)', 'd' => 'ดูประวัติการใช้งานระบบ อ่านอย่างเดียว', 'perms' => ['audit.view'], 'scoped' => false],
    'perm'     => ['t' => 'จัดการสิทธิ์ผู้ใช้', 'd' => 'ตั้งหน้าที่ให้คนอื่น (ตั้ง Admin กลางไม่ได้)', 'perms' => ['admin.manage_permissions'], 'scoped' => false],
];

/** หน้าที่จากสิทธิ์ที่มีผลจริง — นับว่ามีหน้าที่เมื่อมีสิทธิ์ครบทุกข้อของหน้าที่นั้น */
function dutiesFromPerms(array $perms) {
    $out = [];
    foreach (ACCESS_DUTIES as $k => $d) if (!array_diff($d['perms'], $perms)) $out[] = $k;
    return $out;
}

/** ใบติดตั้ง/คืนเครื่อง ต้องเป็นของบริษัทที่ดูแล (ใบไม่ระบุบริษัท = ไม่จำกัด) */
function requireSetupInScope($conn, $id) {
    if (!$id) return;
    $st = $conn->prepare("SELECT company FROM setup_records WHERE id = ?");
    if (!$st) return;
    $st->bind_param('i', $id); $st->execute();
    $co = (string)($st->get_result()->fetch_row()[0] ?? '');
    if ($co !== '' && !inScope($conn, $co)) throw new Exception("ใบนี้เป็นของ {$co} ซึ่งคุณไม่ได้ดูแล");
}
