<?php
/**
 * replace_helper.php — ประเมินเครื่อง "ซ่อมไม่คุ้ม" + ติดตามการซื้อทดแทน
 *
 * ต่อจากงานซ่อม (it_logs) ที่ช่างกด "ซ่อมไม่ได้" — งานซ่อมปิดได้ แต่เรื่องยังไม่จบจนกว่าผู้ใช้ได้เครื่องใหม่และเครื่องเก่าถูกตัดจำหน่าย
 * จึงแยกตารางออกมา (it_replacements) ไม่ผูกกับสถานะงานซ่อม · เริ่มจากเครื่องในทะเบียนโดยไม่มีงานซ่อมก็ได้ (เช่น ตรวจสภาพประจำปี)
 * สถานะ: pending (รออนุมัติ — พิมพ์ใบประเมินให้หัวหน้าเซ็น) → approved (อนุมัติซื้อ) → ordered (สั่งซื้อแล้ว PR/PO) → received (ได้เครื่องใหม่)
 *        → closed (มอบเครื่องใหม่แล้ว + ตัดจำหน่ายเครื่องเก่า)  ·  rejected (ไม่อนุมัติ — ซ่อมต่อ/ใช้ต่อ) ปิดจบ
 * การอนุมัติเกิดนอกระบบ (เซ็นบนใบประเมิน) — IT บันทึกชื่อผู้อนุมัติ + วันที่ตามใบ · ทุกขั้นเก็บว่าใครกด เมื่อไหร่ (TISAX)
 */

const RP_STATUS = ['pending' => 'รออนุมัติ', 'approved' => 'อนุมัติซื้อ', 'ordered' => 'สั่งซื้อแล้ว', 'received' => 'ได้เครื่องใหม่', 'closed' => 'ปิดแล้ว', 'rejected' => 'ไม่อนุมัติ'];
const RP_REASONS = ['old' => 'เครื่องเก่า / หมดอายุการใช้งาน', 'no_parts' => 'ไม่มีอะไหล่ / เลิกผลิต', 'cost' => 'ค่าซ่อมไม่คุ้มเมื่อเทียบเครื่องใหม่',
    'damage' => 'เสียหายหนัก (ตก / น้ำเข้า / ไหม้)', 'spec' => 'สเปกไม่พอกับงาน', 'repeat' => 'เสียซ้ำบ่อย'];

function _initReplaceTables($conn) {
    static $done = false;
    if ($done) return;
    $done = true;
    $conn->query("CREATE TABLE IF NOT EXISTS it_replacements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) NOT NULL,
        ticket_id VARCHAR(50) DEFAULT NULL,
        asset_db_id INT DEFAULT NULL,
        asset_code VARCHAR(100) DEFAULT NULL,
        asset_name VARCHAR(255) DEFAULT NULL,
        serial_number VARCHAR(255) DEFAULT NULL,
        company VARCHAR(10) DEFAULT NULL,
        user_name VARCHAR(255) DEFAULT NULL,
        department VARCHAR(150) DEFAULT NULL,
        purchase_date DATE DEFAULT NULL,
        purchase_price DECIMAL(12,2) DEFAULT NULL,
        useful_life INT DEFAULT NULL,
        problem TEXT,
        findings TEXT,
        reasons VARCHAR(255) DEFAULT NULL,
        repair_cost DECIMAL(12,2) DEFAULT NULL,
        new_cost DECIMAL(12,2) DEFAULT NULL,
        recommendation VARCHAR(20) NOT NULL DEFAULT 'replace',
        new_spec TEXT,
        need_by DATE DEFAULT NULL,
        loaner VARCHAR(255) DEFAULT NULL,
        status VARCHAR(15) NOT NULL DEFAULT 'pending',
        assessed_by VARCHAR(255) DEFAULT NULL,
        assessed_at DATETIME DEFAULT NULL,
        approver_name VARCHAR(255) DEFAULT NULL,
        approved_on DATE DEFAULT NULL,
        decision_note VARCHAR(500) DEFAULT NULL,
        decided_by VARCHAR(255) DEFAULT NULL,
        decided_at DATETIME DEFAULT NULL,
        pr_no VARCHAR(50) DEFAULT NULL,
        po_no VARCHAR(50) DEFAULT NULL,
        vendor VARCHAR(255) DEFAULT NULL,
        eta_date DATE DEFAULT NULL,
        ordered_by VARCHAR(255) DEFAULT NULL,
        ordered_at DATETIME DEFAULT NULL,
        new_asset_code VARCHAR(100) DEFAULT NULL,
        received_by VARCHAR(255) DEFAULT NULL,
        received_at DATETIME DEFAULT NULL,
        closed_by VARCHAR(255) DEFAULT NULL,
        closed_at DATETIME DEFAULT NULL,
        close_note VARCHAR(500) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_code (code),
        KEY idx_status (status),
        KEY idx_ticket (ticket_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function rpActor() { return $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'IT'; }

function rpNextCode($conn) {
    $p = 'RP-' . date('ym') . '-';
    $st = $conn->prepare("SELECT code FROM it_replacements WHERE code LIKE CONCAT(?, '%') ORDER BY code DESC LIMIT 1");
    $st->bind_param('s', $p); $st->execute();
    $last = $st->get_result()->fetch_row()[0] ?? '';
    return $p . str_pad((string)((int)substr($last, -3) + 1), 3, '0', STR_PAD_LEFT);
}

function rpGet($conn, $id) {
    $st = $conn->prepare("SELECT r.*, a.status AS asset_status FROM it_replacements r LEFT JOIN it_assets a ON a.id = r.asset_db_id WHERE r.id = ?");
    $st->bind_param('i', $id); $st->execute();
    return $st->get_result()->fetch_assoc();
}

/** ใบนี้อยู่ในบริษัทที่ดูแลไหม (ใบไม่ระบุบริษัท = ไม่จำกัด) */
function rpInScope($conn, $r) { return !$r['company'] || inScope($conn, $r['company']); }

/** หาเครื่องในทะเบียนจากรหัสที่อยู่ในงานซ่อม (เก็บได้ทั้งรหัสอุปกรณ์ และรหัสทรัพย์สินบัญชี) */
function rpFindAsset($conn, $code) {
    $code = trim((string)$code);
    if ($code === '' || $code === '-') return null;
    $st = $conn->prepare("SELECT a.*, c.default_useful_life FROM it_assets a LEFT JOIN asset_categories c ON c.cat_id = a.asset_category WHERE a.equipment_id = ? OR a.asset_id = ? LIMIT 1");
    $st->bind_param('ss', $code, $code); $st->execute();
    return $st->get_result()->fetch_assoc();
}

/** อายุเครื่องเป็นเดือน (ไม่มีวันที่ซื้อ = null) */
function rpAgeMonths($date) {
    if (!$date) return null;
    $a = new DateTime($date); $b = new DateTime();
    $d = $a->diff($b);
    return $d->y * 12 + $d->m;
}

/** แถวที่ส่งหน้าเว็บ */
function rpRow($r) {
    foreach (['id', 'asset_db_id', 'useful_life'] as $k) if (isset($r[$k])) $r[$k] = $r[$k] === null ? null : (int)$r[$k];
    foreach (['purchase_price', 'repair_cost', 'new_cost'] as $k) $r[$k] = $r[$k] === null ? null : (float)$r[$k];
    $r['reasons'] = $r['reasons'] ? array_values(array_filter(explode(',', $r['reasons']))) : [];
    $r['age_months'] = rpAgeMonths($r['purchase_date']);
    $r['status_th'] = RP_STATUS[$r['status']] ?? $r['status'];
    return $r;
}
