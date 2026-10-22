<?php
/**
 * loan_helper.php — ระบบยืม-คืนอุปกรณ์ IT (คำขอแยกจากข้อมูลเครื่อง)
 *
 * เดิมคำขอฝากไว้ใน it_assets (status รออนุมัติ + borrower) → จองล่วงหน้าไม่ได้ และคำขอที่ถูกปฏิเสธ/ยกเลิกหายไม่มีร่องรอย
 * ตอนนี้ทุกคำขออยู่ใน it_loan_requests · it_assets ยังถูกอัปเดตตอน "ส่งมอบ/รับคืน" ให้หน้าอื่น (ทะเบียนพัสดุ, ของในชื่อฉัน) เห็นเหมือนเดิม
 * it_loan_history ยังเขียนตอนส่งมอบ/รับคืน เพื่อรายงานและพาสปอร์ตเครื่องเดิม
 *
 * สถานะ: requested → approved → active → returned  (rejected / cancelled ปิดจบ)
 * ขอขยายเวลา: extend_to + extend_status pending/approved/rejected (ระหว่าง active)
 */

const LOAN_OPEN = ['requested', 'approved', 'active'];
const LOAN_TH = ['requested' => 'รออนุมัติ', 'approved' => 'อนุมัติแล้ว รอรับของ', 'active' => 'กำลังยืม', 'returned' => 'คืนแล้ว', 'rejected' => 'ไม่อนุมัติ', 'cancelled' => 'ยกเลิก'];

function _initLoanTables($conn) {
    static $done = false;
    if ($done) return;
    $done = true;
    $conn->query("CREATE TABLE IF NOT EXISTS it_loan_requests (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        code             VARCHAR(20)  NOT NULL,
        asset_id         INT          NOT NULL,
        equipment_id     VARCHAR(100) NOT NULL,
        equipment_name   VARCHAR(255) NOT NULL,
        asset_company    VARCHAR(10)  DEFAULT NULL,
        borrower_id      VARCHAR(50)  NOT NULL,
        borrower_name    VARCHAR(255) NOT NULL,
        department       VARCHAR(150) DEFAULT NULL,
        borrower_company VARCHAR(10)  DEFAULT NULL,
        contact          VARCHAR(100) DEFAULT NULL,
        purpose          TEXT,
        start_date       DATE NOT NULL,
        end_date         DATE DEFAULT NULL,
        status           VARCHAR(20)  NOT NULL DEFAULT 'requested',
        decided_by       VARCHAR(255) DEFAULT NULL,
        decided_at       DATETIME     DEFAULT NULL,
        reject_reason    VARCHAR(500) DEFAULT NULL,
        handover_by      VARCHAR(255) DEFAULT NULL,
        handover_at      DATETIME     DEFAULT NULL,
        handover_items   TEXT,
        handover_sig     VARCHAR(255) DEFAULT NULL,
        return_by        VARCHAR(255) DEFAULT NULL,
        return_at        DATETIME     DEFAULT NULL,
        return_items     TEXT,
        return_condition VARCHAR(30)  DEFAULT NULL,
        return_notes     TEXT,
        return_sig       VARCHAR(255) DEFAULT NULL,
        wiped            TINYINT(1)   NOT NULL DEFAULT 0,
        extend_to        DATE         DEFAULT NULL,
        extend_reason    VARCHAR(500) DEFAULT NULL,
        extend_status    VARCHAR(20)  DEFAULT NULL,
        history_id       INT          DEFAULT NULL,
        created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_loan_code (code),
        INDEX idx_loan_asset (asset_id, status),
        INDEX idx_loan_borrower (borrower_id, status),
        INDEX idx_loan_status (status, end_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    _loanMigrateLegacy($conn);
}

// ครั้งแรกที่เปิดใช้: เครื่องที่ค้างสถานะแบบเก่า (รออนุมัติ/ถูกยืม) ย้ายเข้าเป็นคำขอ — งานที่ค้างอยู่ไม่หาย
function _loanMigrateLegacy($conn) {
    $r = $conn->query("SELECT a.id, a.equipment_id, a.name, a.company, a.status, a.borrower, a.borrower_id, a.department, a.purpose, a.return_date, a.checkout_time
        FROM it_assets a WHERE a.status IN ('รออนุมัติ','ถูกยืม')
          AND NOT EXISTS (SELECT 1 FROM it_loan_requests q WHERE q.asset_id = a.id AND q.status IN ('requested','approved','active'))");
    if (!$r) return;
    foreach ($r->fetch_all(MYSQLI_ASSOC) as $a) {
        $st = $a['status'] === 'ถูกยืม' ? 'active' : 'requested';
        $start = $a['checkout_time'] ? substr($a['checkout_time'], 0, 10) : date('Y-m-d');
        $end = $a['return_date'] ?: null;
        $code = loanNextCode($conn);
        $bid = (string)($a['borrower_id'] ?? ''); $bn = (string)($a['borrower'] ?? '-');
        $ins = $conn->prepare("INSERT INTO it_loan_requests (code, asset_id, equipment_id, equipment_name, asset_company, borrower_id, borrower_name, department, purpose, start_date, end_date, status, handover_at, handover_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $hAt = $st === 'active' ? ($a['checkout_time'] ?: date('Y-m-d H:i:s')) : null;
        $hBy = $st === 'active' ? 'ย้ายจากระบบเดิม' : null;
        $ins->bind_param('sissssssssssss', $code, $a['id'], $a['equipment_id'], $a['name'], $a['company'], $bid, $bn, $a['department'], $a['purpose'], $start, $end, $st, $hAt, $hBy);
        $ins->execute();
        // คำขอแบบใหม่ไม่ล็อกเครื่องไว้ระหว่างรออนุมัติ (จองล่วงหน้าได้) — คืนสถานะเครื่องเป็นปกติ
        if ($st === 'requested') {
            $u = $conn->prepare("UPDATE it_assets SET status = 'ใช้งานปกติ', borrower = NULL, borrower_id = NULL, department = NULL, return_date = NULL, purpose = NULL WHERE id = ? AND status = 'รออนุมัติ'");
            $u->bind_param('i', $a['id']); $u->execute();
        }
    }
}

// เลขที่ LN-YYMM-### ต่อเดือน
function loanNextCode($conn) {
    $prefix = 'LN-' . date('ym') . '-';
    $st = $conn->prepare("SELECT code FROM it_loan_requests WHERE code LIKE CONCAT(?, '%') ORDER BY code DESC LIMIT 1");
    $st->bind_param('s', $prefix); $st->execute();
    $last = $st->get_result()->fetch_row()[0] ?? '';
    $n = $last ? ((int)substr($last, -3)) + 1 : 1;
    return $prefix . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
}

// ช่วงวันชนกับคำขออื่นที่ยังไม่ปิดไหม (end_date ว่าง = ยืมไม่มีกำหนด → ชนทุกวันหลังเริ่ม)
function loanOverlaps($conn, $assetId, $start, $end, $exceptId = 0) {
    $endQ = $end ?: '9999-12-31';
    $st = $conn->prepare("SELECT code, borrower_name, start_date, end_date, status FROM it_loan_requests
        WHERE asset_id = ? AND id <> ? AND status IN ('requested','approved','active')
          AND start_date <= ? AND COALESCE(end_date, '9999-12-31') >= ? LIMIT 1");
    $st->bind_param('iiss', $assetId, $exceptId, $endQ, $start);
    $st->execute();
    return $st->get_result()->fetch_assoc();
}

function loanGet($conn, $id) {
    $st = $conn->prepare("SELECT * FROM it_loan_requests WHERE id = ?");
    $st->bind_param('i', $id); $st->execute();
    return $st->get_result()->fetch_assoc();
}

function loanCanManage($conn) {
    return function_exists('hasPermission') ? hasPermission($conn, 'loan.manage') : (($_SESSION['role'] ?? '') === 'admin');
}

function loanActor() {
    return (string)($_SESSION['display_name'] ?? $_SESSION['username'] ?? $_SESSION['user_id'] ?? 'System');
}

// เก็บลายเซ็น data:image/png → uploads/signatures/ คืน path (ว่าง = ไม่มี)
function loanSaveSignature($dataUrl, $prefix) {
    if (!preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/', (string)$dataUrl, $m)) return '';
    $bin = base64_decode($m[1], true);
    if ($bin === false || strlen($bin) > 2 * 1024 * 1024) return '';
    $dir = dirname(__DIR__) . '/uploads/signatures/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.png';
    return file_put_contents($dir . $name, $bin) ? 'uploads/signatures/' . $name : '';
}

// ข้อความแจ้งเตือน (รูปแบบเดียวกับแจ้งเตือนอื่น: บรรทัดแรก <b>หัวข้อ</b> ตามด้วย <b>คีย์:</b> ค่า)
function loanNotify($conn, $event, $title, $loan, $extra = []) {
    $range = date('d/m', strtotime($loan['start_date'])) . ($loan['end_date'] ? '–' . date('d/m', strtotime($loan['end_date'])) : ' (ไม่มีกำหนดคืน)');
    $lines = ["<b>{$title} — {$loan['code']}</b>", "<b>อุปกรณ์:</b> {$loan['equipment_name']} ({$loan['equipment_id']})", "<b>ผู้ยืม:</b> {$loan['borrower_name']}" . ($loan['department'] ? " · {$loan['department']}" : ''), "<b>ช่วงยืม:</b> {$range}"];
    foreach ($extra as $k => $v) if ($v !== null && $v !== '') $lines[] = "<b>{$k}:</b> {$v}";
    if (function_exists('sendNotify')) sendNotify($conn, implode("\n", $lines), $event, $loan['asset_company'] ?: null, $event === 'loan_overdue' ? $loan['code'] : null);
}

// แถวที่ส่งให้หน้าเว็บ — คำนวณ "เกินกำหนด" ตอนอ่าน (ไม่ต้องมีงานเบื้องหลังมาเปลี่ยนสถานะ)
function loanRow($r) {
    $today = date('Y-m-d');
    $r['id'] = (int)$r['id']; $r['asset_id'] = (int)$r['asset_id'];
    $r['status_th'] = LOAN_TH[$r['status']] ?? $r['status'];
    $r['overdue_days'] = ($r['status'] === 'active' && $r['end_date'] && $r['end_date'] < $today) ? (int)((strtotime($today) - strtotime($r['end_date'])) / 86400) : 0;
    $r['days_left'] = ($r['status'] === 'active' && $r['end_date'] && $r['end_date'] >= $today) ? (int)((strtotime($r['end_date']) - strtotime($today)) / 86400) : null;
    foreach (['handover_items', 'return_items'] as $k) $r[$k] = $r[$k] ? (json_decode($r[$k], true) ?: []) : [];
    return $r;
}
