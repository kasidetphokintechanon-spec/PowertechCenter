<?php
/**
 * stock_helper.php — ระบบเบิกของ (เครื่องเขียน / ของแม่บ้าน)
 *
 * สถานะใบเบิก: pending → approved (กำลังจัดของ) → ready (พร้อมรับ) → issued (รับของแล้ว)  ·  rejected / cancelled ปิดจบ
 * ตัดสต็อกตอน "จ่ายของ" จริง (เดิมตัดตอนกดอนุมัติ ทั้งที่ของยังไม่ออกจากห้อง) · ระหว่าง approved/ready ของถูก "จองไว้"
 * ยอดคงเหลือคำนวณจาก stock_transactions เสมอ (ไม่มีช่องเก็บยอด) · ทุกจำนวนเป็นหน่วยเบิก (หน่วยเล็กสุด) — แพ็คแปลงตอนรับเข้า
 */

const STOCK_OPEN = ['pending', 'approved', 'ready'];
const STOCK_TH = ['pending' => 'รออนุมัติ', 'approved' => 'กำลังจัดของ', 'ready' => 'พร้อมรับ', 'issued' => 'รับของแล้ว', 'rejected' => 'ไม่อนุมัติ', 'cancelled' => 'ยกเลิก'];
const STOCK_TYPE_TH = ['stationery' => 'เครื่องเขียน', 'maid' => 'ของแม่บ้าน'];
const STOCK_COS = ['PTA', 'PT4', 'PTE'];
// ยอดคงเหลือ: issued ลบ · received/returned บวก · adjusted มีเครื่องหมายในตัว
const STOCK_BAL_SQL = "COALESCE(SUM(CASE WHEN t.type = 'issued' THEN -t.quantity ELSE t.quantity END), 0)";

function _initStockFlow($conn) {
    static $done = false;
    if ($done) return;
    $done = true;
    require_once __DIR__ . '/../api/actions/stock/initStockTables.php';
    initStockTables($conn);
    if ($conn->query("SHOW COLUMNS FROM stock_requests LIKE 'issued_at'")->num_rows) return;
    // ครั้งแรก: เพิ่มขั้นจัดของ/จ่ายของ · ใบเดิมที่ "approved" ถูกตัดสต็อกไปแล้วตามระบบเก่า → ถือว่ารับของแล้ว
    $conn->query("ALTER TABLE stock_requests MODIFY status ENUM('pending','approved','ready','issued','rejected','cancelled') NOT NULL DEFAULT 'pending'");
    $conn->query("ALTER TABLE stock_requests
        ADD COLUMN ready_at DATETIME NULL, ADD COLUMN ready_by VARCHAR(255) NULL,
        ADD COLUMN issued_at DATETIME NULL, ADD COLUMN issued_by VARCHAR(255) NULL,
        ADD COLUMN receiver_sig VARCHAR(255) NULL, ADD COLUMN closed_reason VARCHAR(500) NULL");
    $conn->query("UPDATE stock_requests SET status = 'issued', issued_at = approved_at, issued_by = approved_by WHERE status = 'approved'");
}

function stockCode($id) { return 'SR-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT); }
function stockActor() { return $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'ผู้ดูแลสต็อก'; }
function stockCanManage($conn) { return ($_SESSION['role'] ?? '') === 'admin' || hasPermission($conn, 'stock.manage'); }

// ผู้ดูแลที่ไม่ใช่ admin ดูแลเฉพาะบริษัทตัวเอง
function stockCompanyScope($want = '') {
    $mine = (string)($_SESSION['company'] ?? '');
    if (($_SESSION['role'] ?? '') === 'admin') return in_array($want, STOCK_COS, true) ? $want : '';
    return $mine;
}

/** [item_id => ยอดคงเหลือ] */
function stockBalances($conn, array $ids) {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) return [];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $conn->prepare("SELECT t.item_id, " . STOCK_BAL_SQL . " FROM stock_transactions t WHERE t.item_id IN ($ph) GROUP BY t.item_id");
    $st->bind_param(str_repeat('i', count($ids)), ...$ids); $st->execute();
    $out = array_fill_keys($ids, 0);
    foreach ($st->get_result()->fetch_all() as $r) $out[(int)$r[0]] = (int)$r[1];
    return $out;
}

/** [item_id => จำนวนที่อนุมัติแล้วแต่ยังไม่จ่าย] — ไม่นับใบ $exceptReq */
function stockReserved($conn, array $ids, $exceptReq = 0) {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) return [];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $conn->prepare("SELECT i.item_id, COALESCE(SUM(i.qty_approved), 0) FROM stock_request_items i JOIN stock_requests r ON r.id = i.request_id
        WHERE r.status IN ('approved','ready') AND r.id <> ? AND i.item_id IN ($ph) GROUP BY i.item_id");
    $p = array_merge([(int)$exceptReq], $ids);
    $st->bind_param(str_repeat('i', count($p)), ...$p); $st->execute();
    $out = array_fill_keys($ids, 0);
    foreach ($st->get_result()->fetch_all() as $r) $out[(int)$r[0]] = (int)$r[1];
    return $out;
}

/** ใบเบิก + รายการ (ชื่อ หน่วย ราคา แพ็ค) */
function stockGetRequest($conn, $id) {
    $st = $conn->prepare("SELECT * FROM stock_requests WHERE id = ?");
    $st->bind_param('i', $id); $st->execute();
    $r = $st->get_result()->fetch_assoc();
    if (!$r) return null;
    $it = $conn->prepare("SELECT i.id AS row_id, i.item_id, i.qty_requested, i.qty_approved, s.item_code, s.name, s.unit, s.price_per_unit, s.pack_unit, s.pack_size, s.min_stock, s.category
        FROM stock_request_items i JOIN stock_items s ON s.id = i.item_id WHERE i.request_id = ? ORDER BY i.id");
    $it->bind_param('i', $id); $it->execute();
    $r['items'] = $it->get_result()->fetch_all(MYSQLI_ASSOC);
    return $r;
}

/** แถวที่ส่งให้หน้าเว็บ */
function stockRow($r) {
    $r['id'] = (int)$r['id'];
    $r['code'] = stockCode($r['id']);
    $r['status_th'] = STOCK_TH[$r['status']] ?? $r['status'];
    $r['type_th'] = STOCK_TYPE_TH[$r['stock_type']] ?? $r['stock_type'];
    $val = 0;
    foreach (($r['items'] ?? []) as &$i) {
        foreach (['row_id', 'item_id', 'qty_requested', 'pack_size', 'min_stock'] as $k) if (isset($i[$k])) $i[$k] = (int)$i[$k];
        $i['qty_approved'] = $i['qty_approved'] === null ? null : (int)$i['qty_approved'];
        $i['price_per_unit'] = (float)$i['price_per_unit'];
        $q = in_array($r['status'], ['approved', 'ready', 'issued'], true) ? (int)$i['qty_approved'] : $i['qty_requested'];
        $val += $q * $i['price_per_unit'];
    }
    unset($i);
    $r['value'] = round($val, 2);
    return $r;
}

/** มูลค่าที่แผนกเบิกเดือนนี้ (จ่ายแล้ว + อนุมัติรอจ่าย) และวงเงิน */
function stockDeptBudget($conn, $company, $dept, $type, $exceptReq = 0) {
    $st = $conn->prepare("SELECT monthly_value_limit FROM stock_dept_quotas WHERE company = ? AND department = ? AND stock_type = ?");
    $st->bind_param('sss', $company, $dept, $type); $st->execute();
    $limit = (float)($st->get_result()->fetch_row()[0] ?? 0);
    $st = $conn->prepare("SELECT COALESCE(SUM(i.qty_approved * s.price_per_unit), 0) FROM stock_requests r
        JOIN stock_request_items i ON i.request_id = r.id JOIN stock_items s ON s.id = i.item_id
        WHERE r.company = ? AND r.department = ? AND r.stock_type = ? AND r.id <> ?
          AND ((r.status = 'issued' AND DATE_FORMAT(r.issued_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')) OR r.status IN ('approved','ready'))");
    $st->bind_param('sssi', $company, $dept, $type, $exceptReq); $st->execute();
    return ['limit' => $limit, 'used' => round((float)$st->get_result()->fetch_row()[0], 2)];
}

function stockSaveSignature($dataUrl) {
    if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', (string)$dataUrl, $m)) return '';
    $bin = base64_decode($m[1], true);
    if ($bin === false || strlen($bin) < 200 || strlen($bin) > 600000) return '';
    $dir = __DIR__ . '/../uploads/signatures/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = 'stock-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.png';
    file_put_contents($dir . $name, $bin);
    return 'uploads/signatures/' . $name;
}

function stockNotify($conn, $event, $title, $r, $extra = []) {
    $lines = ["<b>{$title} — " . stockCode($r['id']) . "</b>",
        "<b>ผู้เบิก:</b> {$r['requester_name']}" . ($r['department'] ? " · {$r['department']}" : ''),
        "<b>ประเภท:</b> " . (STOCK_TYPE_TH[$r['stock_type']] ?? $r['stock_type']) . " · {$r['company']}"];
    if (!empty($r['items'])) {
        $show = in_array($r['status'], ['approved', 'ready', 'issued'], true);
        $list = array_map(fn($i) => "• {$i['name']} ×" . ($show ? (int)$i['qty_approved'] : (int)$i['qty_requested']) . " {$i['unit']}", $r['items']);
        $lines[] = "<b>รายการ:</b>\n" . implode("\n", $list);
    }
    foreach ($extra as $k => $v) if ($v !== null && $v !== '') $lines[] = "<b>{$k}:</b> {$v}";
    if (function_exists('sendNotify')) sendNotify($conn, implode("\n", $lines), $event, $r['company']);
}

/** หลังจ่ายของ: รายการที่ลดต่ำกว่าจุดสั่งซื้อ → แจ้งเตือน (ครั้งเดียวต่อสินค้าต่อวัน) */
function stockCheckLow($conn, array $itemIds, $company) {
    if (!$itemIds || !function_exists('sendNotify')) return;
    $bal = stockBalances($conn, $itemIds);
    $ph = implode(',', array_fill(0, count($itemIds), '?'));
    $st = $conn->prepare("SELECT id, name, unit, min_stock FROM stock_items WHERE id IN ($ph) AND min_stock > 0");
    $ids = array_map('intval', $itemIds);
    $st->bind_param(str_repeat('i', count($ids)), ...$ids); $st->execute();
    $items = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    // กันเตือนซ้ำ: ref ของวันนี้เก็บรหัสสินค้าที่เตือนไปแล้ว รูปแบบ "low-YYYYMMDD:,12,15,"
    $day = 'low-' . date('Ymd') . ':';
    $sent = '';
    if (function_exists('_initNotifyLogTable')) {
        _initNotifyLogTable($conn);
        $q = $conn->prepare("SELECT GROUP_CONCAT(ref) FROM notify_log WHERE event = 'stock_low' AND ref LIKE CONCAT(?, '%')");
        $q->bind_param('s', $day); $q->execute();
        $sent = (string)($q->get_result()->fetch_row()[0] ?? '');
    }
    $low = [];
    foreach ($items as $i) {
        if ($bal[(int)$i['id']] > (int)$i['min_stock'] || strpos($sent, ',' . $i['id'] . ',') !== false) continue;
        $low[] = $i;
    }
    if (!$low) return;
    $lines = ["<b>⚠️ ของใกล้หมด — {$company}</b>"];
    foreach ($low as $i) $lines[] = "<b>{$i['name']}:</b> เหลือ " . $bal[(int)$i['id']] . " {$i['unit']} (จุดสั่งซื้อ {$i['min_stock']})";
    sendNotify($conn, implode("\n", $lines), 'stock_low', $company, $day . ',' . implode(',', array_column($low, 'id')) . ',');
}

/**
 * จ่ายของ: ตัดสต็อกตามจำนวนที่อนุมัติ + ลายเซ็นผู้รับ · เรียกใน transaction ของผู้เรียก
 * เช็กยอดอีกรอบตอนจ่ายจริง (ระหว่างจัดของ อาจมีการปรับยอด/นับสต็อก)
 */
function stockDoIssue($conn, $r, $sig) {
    $ids = array_column($r['items'], 'item_id');
    $bal = stockBalances($conn, $ids);
    foreach ($r['items'] as $i) {
        if ((int)$i['qty_approved'] > $bal[(int)$i['item_id']]) throw new Exception("{$i['name']} ในคลังเหลือ {$bal[(int)$i['item_id']]} {$i['unit']} — ปรับยอด/รับของเข้าก่อน");
    }
    $by = stockActor();
    $tx = $conn->prepare("INSERT INTO stock_transactions (item_id, company, type, quantity, unit_cost, requester_name, requester_id, request_id, notes, created_by) VALUES (?,?,'issued',?,?,?,?,?,?,?)");
    $note = stockCode($r['id']) . ' จ่ายให้ ' . $r['requester_name'];
    foreach ($r['items'] as $i) {
        $q = (int)$i['qty_approved']; if ($q <= 0) continue;
        $cost = (float)$i['price_per_unit'];
        $tx->bind_param('isidssiss', $i['item_id'], $r['company'], $q, $cost, $r['requester_name'], $r['requester_id'], $r['id'], $note, $by); $tx->execute();
    }
    $u = $conn->prepare("UPDATE stock_requests SET status = 'issued', issued_at = NOW(), issued_by = ?, receiver_sig = ? WHERE id = ? AND status IN ('approved','ready')");
    $u->bind_param('ssi', $by, $sig, $r['id']); $u->execute();
    if ($u->affected_rows < 1) throw new Exception('ใบนี้ถูกเปลี่ยนสถานะไปแล้ว');
}
