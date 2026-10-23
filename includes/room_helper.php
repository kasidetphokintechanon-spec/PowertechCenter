<?php
/**
 * room_helper.php — จองห้องประชุม (แทน roomsys เดิม)
 *
 * จองแล้วได้ห้องทันที ไม่มีขั้นอนุมัติ — ระบบเดิมมีแต่ปี 2025 ไม่มีใบไหนถูกอนุมัติ (ใช้เป็นปฏิทินอย่างเดียว)
 * ของที่ขอให้เตรียม (น้ำ/อาหาร/อุปกรณ์) แจ้งผู้ดูแลห้อง + ผู้ดูแลกด "เตรียมแล้ว" · ทุกบริษัทจองได้ทุกห้อง
 * ห้องเป็นของบริษัทใดบริษัทหนึ่ง (rm_rooms.company) แต่ทุกบริษัทจองได้ — บริษัทใช้กรองตาราง + กำหนดว่าใครดูแล
 * ผู้ดูแลห้อง = มีหน้าที่ room.manage · ผูกรายห้องใน rm_room_keepers · ไม่ได้ผูกห้องไหน = ดูแลทุกห้องของบริษัทที่ดูแล (หน้าสิทธิ์)
 * กติกาแยกบริษัท (rm_company_rules): เวลาเปิด-ปิด จองล่วงหน้าได้กี่วัน จองนานสุด ของที่ขอเตรียมได้ · ห้องที่ตั้ง needs_approval = จองแล้ว "รออนุมัติ"
 * สถานะ: pending (รออนุมัติ) → booked · rejected / cancelled ปิดจบ · pending กันเวลาไว้แล้ว คนอื่นจองทับไม่ได้
 */

const ROOM_EXTRAS = [
    'water'   => ['t' => 'น้ำดื่ม', 'i' => '💧'],
    'coffee'  => ['t' => 'กาแฟ', 'i' => '☕'],
    'snack'   => ['t' => 'ขนม', 'i' => '🍪'],
    'food'    => ['t' => 'อาหาร', 'i' => '🍱'],
    'fruit'   => ['t' => 'ผลไม้', 'i' => '🍎'],
    'projector' => ['t' => 'โปรเจกเตอร์', 'i' => '📽'],
    'mic'     => ['t' => 'ไมค์', 'i' => '🎤'],
    'confcall' => ['t' => 'ประชุมทางไกล', 'i' => '📞'],
    'welcome' => ['t' => 'ป้ายต้อนรับ', 'i' => '🪧'],
];

function _initRoomTables($conn) {
    static $done = false;
    if ($done) return;
    $done = true;
    $conn->query("CREATE TABLE IF NOT EXISTS rm_rooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        building VARCHAR(150) DEFAULT NULL,
        seats INT NOT NULL DEFAULT 0,
        company VARCHAR(10) DEFAULT NULL,
        equipment VARCHAR(500) DEFAULT NULL,
        contact_name VARCHAR(150) DEFAULT NULL,
        contact_phone VARCHAR(50) DEFAULT NULL,
        photo VARCHAR(255) DEFAULT NULL,
        note VARCHAR(500) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        needs_approval TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        legacy_id INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("CREATE TABLE IF NOT EXISTS rm_company_rules (
        company VARCHAR(10) NOT NULL PRIMARY KEY, rules TEXT, updated_by VARCHAR(255) DEFAULT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("CREATE TABLE IF NOT EXISTS rm_room_keepers (
        room_id INT NOT NULL, user_id VARCHAR(50) NOT NULL, PRIMARY KEY (room_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("CREATE TABLE IF NOT EXISTS rm_bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) NOT NULL,
        room_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        start_at DATETIME NOT NULL,
        end_at DATETIME NOT NULL,
        attendees INT NOT NULL DEFAULT 0,
        booker_id VARCHAR(50) DEFAULT NULL,
        booker_name VARCHAR(255) NOT NULL,
        department VARCHAR(150) DEFAULT NULL,
        company VARCHAR(10) DEFAULT NULL,
        contact VARCHAR(100) DEFAULT NULL,
        extras VARCHAR(500) DEFAULT NULL,
        extras_note VARCHAR(500) DEFAULT NULL,
        host VARCHAR(255) DEFAULT NULL,
        participants VARCHAR(1000) DEFAULT NULL,
        has_guest TINYINT(1) NOT NULL DEFAULT 0,
        guest_company VARCHAR(255) DEFAULT NULL,
        note VARCHAR(500) DEFAULT NULL,
        status VARCHAR(15) NOT NULL DEFAULT 'booked',
        prepared_at DATETIME DEFAULT NULL,
        prepared_by VARCHAR(255) DEFAULT NULL,
        cancelled_at DATETIME DEFAULT NULL,
        cancelled_by VARCHAR(255) DEFAULT NULL,
        cancel_reason VARCHAR(500) DEFAULT NULL,
        legacy_id INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_code (code),
        KEY idx_room_time (room_id, start_at, end_at),
        KEY idx_booker (booker_id, start_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // ตารางที่สร้างก่อนมีขั้นอนุมัติ (เครื่องทดสอบ)
    if (!$conn->query("SHOW COLUMNS FROM rm_rooms LIKE 'needs_approval'")->num_rows) $conn->query("ALTER TABLE rm_rooms ADD COLUMN needs_approval TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
    if (!$conn->query("SHOW COLUMNS FROM rm_bookings LIKE 'host'")->num_rows) $conn->query("ALTER TABLE rm_bookings ADD COLUMN host VARCHAR(255) DEFAULT NULL AFTER extras_note, ADD COLUMN participants VARCHAR(1000) DEFAULT NULL AFTER host,
        ADD COLUMN has_guest TINYINT(1) NOT NULL DEFAULT 0 AFTER participants, ADD COLUMN guest_company VARCHAR(255) DEFAULT NULL AFTER has_guest, ADD COLUMN note VARCHAR(500) DEFAULT NULL AFTER guest_company");
}

/** รายละเอียดการประชุมจากฟอร์ม (ไม่บังคับ) · มีแขกภายนอกต้องบอกบริษัทแขก (เตรียมป้ายต้อนรับ / ทะเบียนผู้มาติดต่อ TISAX) */
function roomDetailFields($src, $old = []) {
    $g = fn($k, $n) => mb_substr(trim(strip_tags((string)($src[$k] ?? ($old[$k] ?? '')))), 0, $n);
    $guest = isset($src['has_guest']) ? (!empty($src['has_guest']) ? 1 : 0) : (int)($old['has_guest'] ?? 0);
    $gc = $guest ? $g('guest_company', 255) : '';
    if ($guest && $gc === '') throw new Exception('มีแขกภายนอก — ใส่ชื่อบริษัท/หน่วยงานของแขก');
    return [$g('host', 255), $g('participants', 1000), $guest, $gc, $g('note', 500)];
}

const ROOM_RULE_DEFAULT = ['open_from' => '07:00', 'open_to' => '20:00', 'max_days_ahead' => 90, 'max_hours' => 0, 'extras' => null, 'custom' => []];

/** กติกาของบริษัท (ห้องไม่ระบุบริษัท = ค่าเริ่มต้น) · extras null = เปิดทุกรายการมาตรฐาน */
function roomRules($conn, $company) {
    static $cache = [];
    $co = (string)$company;
    if (isset($cache[$co])) return $cache[$co];
    $r = ROOM_RULE_DEFAULT;
    if ($co !== '') {
        $st = $conn->prepare("SELECT rules FROM rm_company_rules WHERE company = ?");
        $st->bind_param('s', $co); $st->execute();
        $j = json_decode((string)($st->get_result()->fetch_row()[0] ?? ''), true);
        if (is_array($j)) $r = array_merge($r, array_intersect_key($j, $r));
    }
    return $cache[$co] = $r;
}

/** ของที่ขอเตรียมได้ในห้องของบริษัทนี้: [key => ['t','i']] */
function roomExtrasFor($conn, $company) {
    $r = roomRules($conn, $company);
    $out = [];
    foreach (ROOM_EXTRAS as $k => $v) if ($r['extras'] === null || in_array($k, (array)$r['extras'], true)) $out[$k] = $v;
    foreach ((array)$r['custom'] as $c) if (!empty($c['key']) && !empty($c['t'])) $out[(string)$c['key']] = ['t' => (string)$c['t'], 'i' => (string)($c['i'] ?? '•')];
    return $out;
}

function roomCanManage($conn) { return ($_SESSION['role'] ?? '') === 'admin' || hasPermission($conn, 'room.manage'); }
function roomActor() { return $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'ผู้ดูแลห้อง'; }

/** ผู้ดูแลระดับบริษัท (ไม่ได้ผูกรายห้อง) — เพิ่มห้องและเลือกผู้ดูแลรายห้องได้ ในบริษัทที่ดูแล · admin = ทุกบริษัท */
function roomIsCentral($conn) {
    if (!roomCanManage($conn)) return false;
    if (($_SESSION['role'] ?? '') === 'admin') return true;
    $uid = (string)$_SESSION['user_id'];
    $st = $conn->prepare("SELECT 1 FROM rm_room_keepers WHERE user_id = ? LIMIT 1");
    $st->bind_param('s', $uid); $st->execute();
    return !$st->get_result()->fetch_row();
}

/** ห้องที่บัญชีนี้ดูแล — admin = ทุกห้อง (null) · ผูกรายห้อง = ห้องที่ผูก · ไม่ได้ผูก = ห้องของบริษัทที่ดูแล (+ห้องที่ยังไม่ระบุบริษัท) · ไม่ใช่ผู้ดูแล = [] */
function roomKeptIds($conn) {
    static $memo = null;
    if ($memo !== null) return $memo === 'all' ? null : $memo;
    if (!roomCanManage($conn)) return $memo = [];
    if (($_SESSION['role'] ?? '') === 'admin') { $memo = 'all'; return null; }
    $uid = (string)$_SESSION['user_id'];
    $st = $conn->prepare("SELECT room_id FROM rm_room_keepers WHERE user_id = ?");
    $st->bind_param('s', $uid); $st->execute();
    $ids = array_map('intval', array_column($st->get_result()->fetch_all(), 0));
    if (!$ids) {
        $cos = userCompanies($conn);
        $ph = $cos ? implode(',', array_fill(0, count($cos), '?')) : "''";
        $st = $conn->prepare("SELECT id FROM rm_rooms WHERE company IS NULL OR company = '' OR company IN ($ph)");
        if ($cos) $st->bind_param(str_repeat('s', count($cos)), ...$cos);
        $st->execute();
        $ids = array_map('intval', array_column($st->get_result()->fetch_all(), 0));
    }
    return $memo = $ids;
}
/** งานค้างของผู้ดูแลห้อง (ตัวเลขบนเมนู): รออนุมัติทุกวันที่ยังไม่จบ + การจองวันนี้ที่ขอเตรียมของแต่ยังไม่ได้กดเตรียมแล้ว */
function roomKeeperTodo($conn) {
    $kept = roomKeptIds($conn);
    if ($kept === []) return ['pending' => 0, 'prep' => 0];
    $w = ''; $p = []; $t = '';
    if ($kept !== null) { $w = ' AND room_id IN (' . implode(',', array_fill(0, count($kept), '?')) . ')'; $p = $kept; $t = str_repeat('i', count($kept)); }
    $n = function ($cond) use ($conn, $w, $p, $t) {
        $st = $conn->prepare("SELECT COUNT(*) FROM rm_bookings WHERE {$cond}{$w}");
        if ($t) $st->bind_param($t, ...$p);
        $st->execute(); return (int)$st->get_result()->fetch_row()[0];
    };
    return ['pending' => $n("status = 'pending' AND end_at > NOW()"),
        'prep' => $n("status = 'booked' AND extras <> '' AND prepared_at IS NULL AND start_at < CURDATE() + INTERVAL 1 DAY AND end_at > NOW()")];
}
function roomKeeps($conn, $roomId) { $k = roomKeptIds($conn); return $k === null ? roomCanManage($conn) : in_array((int)$roomId, $k, true); }

function roomNextCode($conn) {
    $p = 'RB-' . date('ym') . '-';
    $st = $conn->prepare("SELECT code FROM rm_bookings WHERE code LIKE CONCAT(?, '%') ORDER BY code DESC LIMIT 1");
    $st->bind_param('s', $p); $st->execute();
    $last = $st->get_result()->fetch_row()[0] ?? '';
    return $p . str_pad((string)((int)substr($last, -3) + 1), 3, '0', STR_PAD_LEFT);
}

/** การจองที่ทับช่วงเวลา (ไม่นับใบ $except) */
function roomClash($conn, $roomId, $start, $end, $except = 0) {
    $st = $conn->prepare("SELECT id, code, title, booker_name, start_at, end_at FROM rm_bookings WHERE room_id = ? AND status IN ('booked','pending') AND id <> ? AND start_at < ? AND end_at > ? LIMIT 1");
    $st->bind_param('iiss', $roomId, $except, $end, $start); $st->execute();
    return $st->get_result()->fetch_assoc();
}

function roomExtrasClean($raw, array $allowed = ROOM_EXTRAS) {
    $arr = is_array($raw) ? $raw : (json_decode((string)$raw, true) ?: []);
    return array_values(array_intersect(array_keys($allowed), array_map('strval', $arr)));
}
function roomExtrasText($keys, $company = null) {
    global $conn;
    $all = $company !== null && isset($conn) ? roomExtrasFor($conn, $company) + ROOM_EXTRAS : ROOM_EXTRAS;
    return implode(', ', array_map(fn($k) => $all[$k]['t'] ?? $k, $keys));
}

/** กติกาบริษัทของห้อง: จองล่วงหน้าได้ไม่เกิน N วัน · นานสุด N ชม. (ผู้ดูแลห้องจองเกินได้) */
function roomCheckRules($conn, $room, $start, $end, $isKeeper) {
    if ($isKeeper) return;
    $r = roomRules($conn, $room['company'] ?? '');
    if ((int)$r['max_days_ahead'] > 0 && strtotime($start) > strtotime('+' . (int)$r['max_days_ahead'] . ' days')) throw new Exception("จองล่วงหน้าได้ไม่เกิน {$r['max_days_ahead']} วัน");
    if ((float)$r['max_hours'] > 0 && strtotime($end) - strtotime($start) > (float)$r['max_hours'] * 3600) throw new Exception("จองได้นานสุดครั้งละ {$r['max_hours']} ชั่วโมง — ถ้าต้องการนานกว่านี้ ติดต่อผู้ดูแลห้อง");
}

/** แถวที่ส่งหน้าเว็บ */
function roomRow($b) {
    $b['id'] = (int)$b['id']; $b['room_id'] = (int)$b['room_id']; $b['attendees'] = (int)$b['attendees'];
    $b['extras'] = $b['extras'] ? array_values(array_filter(explode(',', $b['extras']))) : [];
    $b['mine'] = (string)$b['booker_id'] === (string)($_SESSION['user_id'] ?? '');
    return $b;
}

function roomGet($conn, $id) {
    $st = $conn->prepare("SELECT b.*, r.name AS room_name, r.building, r.company AS room_company, r.needs_approval FROM rm_bookings b JOIN rm_rooms r ON r.id = b.room_id WHERE b.id = ?");
    $st->bind_param('i', $id); $st->execute();
    return $st->get_result()->fetch_assoc();
}

/** ตรวจเวลา: รูปแบบถูก · จบหลังเริ่ม · ไม่เกิน 14 วัน · ห้ามจองย้อนหลัง (ยกเว้นผู้ดูแล) */
function roomCheckTime($start, $end, $isKeeper) {
    $s = strtotime($start); $e = strtotime($end);
    if (!$s || !$e) throw new Exception('วันเวลาไม่ถูกต้อง');
    if ($e <= $s) throw new Exception('เวลาจบต้องหลังเวลาเริ่ม');
    if ($e - $s > 14 * 86400) throw new Exception('จองต่อเนื่องได้ไม่เกิน 14 วัน');
    if (!$isKeeper && $e < time()) throw new Exception('จองย้อนหลังไม่ได้');
    return [date('Y-m-d H:i:s', $s), date('Y-m-d H:i:s', $e)];
}

/** แจ้ง LINE — เฉพาะการจองที่ขอเตรียมของ หรือรออนุมัติ ($force) · ไม่แจ้งทุกการจองให้รก */
function roomNotify($conn, $event, $title, $b, $extra = [], $force = false) {
    $ex = is_array($b['extras']) ? $b['extras'] : array_filter(explode(',', (string)$b['extras']));
    if ((!$ex && !$force) || !function_exists('sendNotify')) return;
    $when = date('d/m H:i', strtotime($b['start_at'])) . '–' . (date('Y-m-d', strtotime($b['start_at'])) === date('Y-m-d', strtotime($b['end_at'])) ? date('H:i', strtotime($b['end_at'])) : date('d/m H:i', strtotime($b['end_at'])));
    $lines = ["<b>{$title} — {$b['code']}</b>", "<b>ห้อง:</b> {$b['room_name']}", "<b>เวลา:</b> {$when} · {$b['attendees']} คน",
        !empty($b['host']) ? "<b>ประชุมของ:</b> {$b['host']}" : '', !empty($b['has_guest']) ? "<b>แขกภายนอก:</b> {$b['guest_company']}" : '',
        "<b>ผู้จอง:</b> {$b['booker_name']}" . ($b['department'] ? " · {$b['department']}" : '') . ($b['contact'] ? " · {$b['contact']}" : ''),
        $ex ? "<b>เตรียม:</b> " . roomExtrasText($ex, $b['room_company'] ?? null) . ($b['extras_note'] ? " ({$b['extras_note']})" : '') : '<b>เตรียม:</b> -'];
    foreach ($extra as $k => $v) if ($v !== null && $v !== '') $lines[] = "<b>{$k}:</b> {$v}";
    // ส่งตามบริษัทเจ้าของห้อง (กลุ่มผู้ดูแลห้องนั้น) ไม่ใช่บริษัทผู้จอง — คน PT4 จองห้อง PTA ต้องเตือนผู้ดูแล PTA
    sendNotify($conn, implode("\n", array_filter($lines)), $event, ($b['room_company'] ?? '') ?: ($b['company'] ?: null));
}
