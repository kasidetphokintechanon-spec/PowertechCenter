<?php
/**
 * car_helper.php — จองรถบริษัท (แทน carsys เดิม)
 *
 * ทุกใบต้องให้ผู้ดูแลรถอนุมัติ — รถกับคนขับมีจำกัด ต้องมีคนคุมคิว
 * สถานะ: pending (รออนุมัติ) → approved (อนุมัติ/ใช้งาน) → returning (คนจองกดคืนรถ รอส่งกุญแจ) → returned (ผู้ดูแลรับกุญแจคืน)
 *        rejected / cancelled ปิดจบ · pending กันเวลาไว้แล้ว คันเดียวกันจองทับไม่ได้
 * จองให้คนอื่นได้ (rider_name = ผู้ใช้รถ · ว่าง = คนจองใช้เอง) — ธุรการจองให้ผู้บริหาร/แผนกอื่นบ่อยในระบบเดิม
 * คนจองเลือกขับเองหรือขอคนขับ → ผู้ดูแลเลือกคนขับตอนอนุมัติ (คนขับคนเดียวกันซ้อนเวลาไม่ได้)
 * รถเป็นของบริษัท (cr_cars.company) ทุกบริษัทจองได้ · ผู้ดูแลรถ = หน้าที่ car.manage ตาม "บริษัทที่ดูแล" ในหน้าสิทธิ์
 * ไม่ลบใบจอง (ประวัติว่าใครใช้คันไหน — TISAX) ใช้ยกเลิก/ไม่อนุมัติแทน · รถเลิกใช้ = ปิด ไม่ลบ
 */

const CAR_LIVE = ['pending', 'approved', 'returning'];   // สถานะที่ยังกันเวลารถไว้

function _initCarTables($conn) {
    static $done = false;
    if ($done) return;
    $done = true;
    $conn->query("CREATE TABLE IF NOT EXISTS cr_cars (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plate VARCHAR(30) NOT NULL,
        province VARCHAR(60) DEFAULT NULL,
        name VARCHAR(150) DEFAULT NULL,
        brand VARCHAR(60) DEFAULT NULL,
        car_type VARCHAR(30) DEFAULT NULL,
        seats INT NOT NULL DEFAULT 0,
        color VARCHAR(60) DEFAULT NULL,
        company VARCHAR(10) DEFAULT NULL,
        photo VARCHAR(255) DEFAULT NULL,
        note VARCHAR(500) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        legacy_id INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_legacy (legacy_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("CREATE TABLE IF NOT EXISTS cr_drivers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        nickname VARCHAR(60) DEFAULT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        company VARCHAR(10) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        legacy_id INT DEFAULT NULL,
        UNIQUE KEY uq_legacy (legacy_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("CREATE TABLE IF NOT EXISTS cr_bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) NOT NULL,
        car_id INT NOT NULL,
        driver_id INT DEFAULT NULL,
        need_driver TINYINT(1) NOT NULL DEFAULT 0,
        purpose VARCHAR(255) NOT NULL,
        destination VARCHAR(255) DEFAULT NULL,
        start_at DATETIME NOT NULL,
        end_at DATETIME NOT NULL,
        passengers INT NOT NULL DEFAULT 0,
        passenger_names VARCHAR(1000) DEFAULT NULL,
        booker_id VARCHAR(50) DEFAULT NULL,
        booker_name VARCHAR(255) NOT NULL,
        rider_name VARCHAR(255) DEFAULT NULL,
        department VARCHAR(150) DEFAULT NULL,
        company VARCHAR(10) DEFAULT NULL,
        contact VARCHAR(100) DEFAULT NULL,
        note VARCHAR(500) DEFAULT NULL,
        status VARCHAR(15) NOT NULL DEFAULT 'pending',
        decided_at DATETIME DEFAULT NULL,
        decided_by VARCHAR(255) DEFAULT NULL,
        returned_at DATETIME DEFAULT NULL,
        return_note VARCHAR(500) DEFAULT NULL,
        received_at DATETIME DEFAULT NULL,
        received_by VARCHAR(255) DEFAULT NULL,
        cancelled_at DATETIME DEFAULT NULL,
        cancelled_by VARCHAR(255) DEFAULT NULL,
        cancel_reason VARCHAR(500) DEFAULT NULL,
        legacy_id INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_code (code),
        UNIQUE KEY uq_legacy (legacy_id),
        KEY idx_car_time (car_id, start_at, end_at),
        KEY idx_booker (booker_id, start_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function carCanManage($conn) { return ($_SESSION['role'] ?? '') === 'admin' || hasPermission($conn, 'car.manage'); }
function carActor() { return $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'ผู้ดูแลรถ'; }

/** บริษัทที่ดูแลรถ — null = ทุกบริษัท (admin) · [] = ไม่ใช่ผู้ดูแล */
function carKeptCos($conn) {
    if (!carCanManage($conn)) return [];
    if (($_SESSION['role'] ?? '') === 'admin') return null;
    return userCompanies($conn);
}
/** ดูแลรถคันนี้ไหม (รถที่ยังไม่ระบุบริษัท ผู้ดูแลทุกคนจัดการได้ จะได้ไม่มีคันที่ไม่มีเจ้าของ) */
function carKeeps($conn, $company) {
    $cos = carKeptCos($conn);
    return $cos === null || ($cos && (!$company || in_array($company, $cos, true)));
}
/** เงื่อนไข SQL จำกัดเฉพาะรถของบริษัทที่ดูแล (ใช้กับ alias c = cr_cars) */
function carScopeSql($conn) {
    $cos = carKeptCos($conn);
    if ($cos === null) return ['', []];
    if (!$cos) return [' AND 1 = 0', []];
    return [' AND (c.company IS NULL OR c.company = \'\' OR c.company IN (' . implode(',', array_fill(0, count($cos), '?')) . '))', $cos];
}

function carNextCode($conn) {
    $p = 'CB-' . date('ym') . '-';
    $st = $conn->prepare("SELECT code FROM cr_bookings WHERE code LIKE CONCAT(?, '%') ORDER BY code DESC LIMIT 1");
    $st->bind_param('s', $p); $st->execute();
    $last = $st->get_result()->fetch_row()[0] ?? '';
    return $p . str_pad((string)((int)substr($last, -3) + 1), 3, '0', STR_PAD_LEFT);
}

/** ใบที่ทับช่วงเวลาของรถคันนี้ (ไม่นับใบ $except) */
function carClash($conn, $carId, $start, $end, $except = 0) {
    $st = $conn->prepare("SELECT id, code, booker_name, start_at, end_at FROM cr_bookings WHERE car_id = ? AND status IN ('pending','approved','returning') AND id <> ? AND start_at < ? AND end_at > ? LIMIT 1");
    $st->bind_param('iiss', $carId, $except, $end, $start); $st->execute();
    return $st->get_result()->fetch_assoc();
}
/** คนขับที่ติดงานอื่นช่วงเดียวกัน (เฉพาะใบที่อนุมัติแล้ว) */
function carDriverClash($conn, $driverId, $start, $end, $except = 0) {
    $st = $conn->prepare("SELECT b.code, b.start_at, b.end_at, c.plate FROM cr_bookings b JOIN cr_cars c ON c.id = b.car_id WHERE b.driver_id = ? AND b.status IN ('approved','returning') AND b.id <> ? AND b.start_at < ? AND b.end_at > ? LIMIT 1");
    $st->bind_param('iiss', $driverId, $except, $end, $start); $st->execute();
    return $st->get_result()->fetch_assoc();
}

/** ตรวจเวลา: รูปแบบถูก · จบหลังเริ่ม · ไม่เกิน 30 วัน · ห้ามจองย้อนหลัง (ยกเว้นผู้ดูแลบันทึกย้อนหลัง) */
function carCheckTime($start, $end, $isKeeper) {
    $s = strtotime($start); $e = strtotime($end);
    if (!$s || !$e) throw new Exception('วันเวลาไม่ถูกต้อง');
    if ($e <= $s) throw new Exception('เวลาคืนรถต้องหลังเวลารับรถ');
    if ($e - $s > 30 * 86400) throw new Exception('จองต่อเนื่องได้ไม่เกิน 30 วัน');
    if (!$isKeeper && $e < time()) throw new Exception('จองย้อนหลังไม่ได้');
    return [date('Y-m-d H:i:s', $s), date('Y-m-d H:i:s', $e)];
}

function carGet($conn, $id) {
    $st = $conn->prepare("SELECT b.*, c.plate, c.province, c.name AS car_name, c.brand, c.seats, c.company AS car_company, d.name AS driver_name, d.phone AS driver_phone
        FROM cr_bookings b JOIN cr_cars c ON c.id = b.car_id LEFT JOIN cr_drivers d ON d.id = b.driver_id WHERE b.id = ?");
    $st->bind_param('i', $id); $st->execute();
    return $st->get_result()->fetch_assoc();
}
/** แถวที่ส่งหน้าเว็บ */
function carRow($b) {
    foreach (['id', 'car_id', 'passengers', 'need_driver'] as $k) if (isset($b[$k])) $b[$k] = (int)$b[$k];
    $b['driver_id'] = isset($b['driver_id']) ? (int)$b['driver_id'] : null;
    $b['mine'] = (string)$b['booker_id'] === (string)($_SESSION['user_id'] ?? '');
    return $b;
}
function carLabel($b) { return trim(($b['plate'] ?? '') . ' ' . ($b['car_name'] ?? '')); }

/** งานค้างของผู้ดูแลรถ (ตัวเลขบนเมนู): รออนุมัติ · รอรับกุญแจคืน · เลยเวลาคืนแล้วยังไม่คืน */
function carKeeperTodo($conn) {
    if (!carCanManage($conn)) return ['pending' => 0, 'receive' => 0, 'overdue' => 0];
    [$w, $p] = carScopeSql($conn);
    $n = function ($cond) use ($conn, $w, $p) {
        $st = $conn->prepare("SELECT COUNT(*) FROM cr_bookings b JOIN cr_cars c ON c.id = b.car_id WHERE {$cond}{$w}");
        if ($p) $st->bind_param(str_repeat('s', count($p)), ...$p);
        $st->execute(); return (int)$st->get_result()->fetch_row()[0];
    };
    return ['pending' => $n("b.status = 'pending' AND b.end_at > NOW()"), 'receive' => $n("b.status = 'returning'"),
        'overdue' => $n("b.status = 'approved' AND b.end_at < NOW()")];
}

/** LINE/Telegram ไปกลุ่มของบริษัทเจ้าของรถ (ผู้ดูแลรถคันนั้น) */
function carNotify($conn, $event, $title, $b, $extra = [], $ref = null, $path = 'cars.html?tab=keep', $label = 'เปิดดู / จัดการ') {
    if (!function_exists('sendNotify') || !$b) return;
    $sameDay = date('Y-m-d', strtotime($b['start_at'])) === date('Y-m-d', strtotime($b['end_at']));
    $when = date('d/m H:i', strtotime($b['start_at'])) . '–' . ($sameDay ? date('H:i', strtotime($b['end_at'])) : date('d/m H:i', strtotime($b['end_at'])));
    $lines = ["<b>{$title} — {$b['code']}</b>", "<b>รถ:</b> " . carLabel($b), "<b>เวลา:</b> {$when}", "<b>ไปทำอะไร:</b> {$b['purpose']}",
        !empty($b['destination']) ? "<b>ไปที่:</b> {$b['destination']}" : '', "<b>คนขับ:</b> " . ((int)$b['need_driver'] ? (!empty($b['driver_name']) ? ($b['status'] === 'pending' ? 'ขอ ' . $b['driver_name'] : $b['driver_name']) : 'ขอคนขับ (ยังไม่ได้เลือก)') : 'ขับเอง'),
        !empty($b['rider_name']) ? "<b>ผู้ใช้รถ:</b> {$b['rider_name']}" : '',
        "<b>ผู้จอง:</b> {$b['booker_name']}" . ($b['department'] ? " · {$b['department']}" : ''), (int)$b['passengers'] > 1 ? "<b>จำนวนคน:</b> {$b['passengers']} คน" : ''];
    foreach ($extra as $k => $v) if ($v !== null && $v !== '') $lines[] = "<b>{$k}:</b> {$v}";
    sendNotify($conn, implode("\n", array_filter($lines)), $event, ($b['car_company'] ?? '') ?: ($b['company'] ?: null), $ref, function_exists('notifyLink') ? notifyLink($conn, $path, $label) : null);
}

/** ความด่วนจากเวลาออกรถ — ข้อมูลเดิม: ครึ่งหนึ่งจองวันเดียวกับที่ใช้ อนุมัติช้า = ออกรถไม่ทัน */
function carUrgency($start) {
    $min = (int)round((strtotime($start) - time()) / 60);
    if ($min <= 0) return '⚡ ถึงเวลาออกแล้ว';
    if ($min <= 120) return "⚡ ออกอีก {$min} นาที";
    if (date('Y-m-d', strtotime($start)) === date('Y-m-d')) return 'วันนี้ ' . date('H:i', strtotime($start));
    if (date('Y-m-d', strtotime($start)) === date('Y-m-d', strtotime('+1 day'))) return 'พรุ่งนี้ ' . date('H:i', strtotime($start));
    return '';
}

/**
 * แจ้งกลุ่มคนขับเมื่อได้งาน/เปลี่ยนงานที่ออกภายใน 24 ชม. — สรุป 16:00 ไม่ทันงานที่จองวันเดียวกัน
 * $prev = ชื่อคนขับเดิม (ตอนเปลี่ยนคน) · ใบที่ออกเกิน 24 ชม. รอสรุปงานคนขับ 16:00 ตามปกติ
 */
function carNotifyDriverJob($conn, $b, $prev = null) {
    if (!$b || strtotime($b['start_at']) > time() + 86400 || strtotime($b['end_at']) < time()) return;
    if (!$b['driver_id'] && !$prev) return;
    $when = carUrgency($b['start_at']) ?: date('d/m H:i', strtotime($b['start_at']));
    if (!$b['driver_id']) { carNotify($conn, 'car_driver_job', "🧑‍✈️ ยกเลิกงานคนขับ — {$prev} ไม่ต้องไป", $b, ['เดิมเป็นงานของ' => $prev], null, 'cars.html', 'ดูตารางรถ'); return; }
    carNotify($conn, 'car_driver_job', "🧑‍✈️ งานคนขับ {$b['driver_name']} — {$when}", $b, ['เปลี่ยนจาก' => $prev], null, 'cars.html', 'ดูตารางรถ');
}

/**
 * แจ้งเตือนตามเวลา (เรียกจาก _checkNotifyCrons ทุก 5 นาที) · กันส่งซ้ำด้วย notify_log (event + ref)
 *   car_daily        16:00–18:00 งานคนขับ + ใบจองของวันทำงานถัดไป แยกกลุ่มตามบริษัทเจ้าของรถ (วันละครั้งต่อบริษัท)
 *   car_pending_soon อีกไม่ถึง 1 ชม. จะออกรถ แต่ยังไม่อนุมัติ (ต่อใบ ครั้งเดียว) — ระบบเดิมมีใบที่ไม่มีใครกดหลายร้อยใบ
 *   car_key_overdue  เลยเวลาคืน 30 นาที แล้วยังไม่รับกุญแจ (คืนช้าไม่กี่นาทีเป็นเรื่องปกติ — เตือนตรงเวลากลุ่มจะรก) (ต่อใบ ครั้งเดียว · ส่งเฉพาะ 07–20 น.)
 */
function _cronCars($conn) {
    _initCarTables($conn);
    $hm = date('H:i');
    if ($hm >= '16:00' && $hm < '18:00') _cronCarDaily($conn);
    $ids = fn($sql) => array_column($conn->query($sql)->fetch_all(), 0);
    foreach ($ids("SELECT id FROM cr_bookings WHERE status = 'pending' AND start_at <= NOW() + INTERVAL 1 HOUR AND end_at > NOW()") as $id) {
        $b = carGet($conn, (int)$id);
        if (!$b || notifyAlreadySent($conn, 'car_pending_soon', $b['code'])) continue;
        $min = (int)round((strtotime($b['start_at']) - time()) / 60);
        carNotify($conn, 'car_pending_soon', '⏳ ใกล้เวลาออกรถ — ยังไม่อนุมัติ', $b, ['ออกรถ' => $min > 0 ? "อีก {$min} นาที" : 'ถึงเวลาแล้ว'], $b['code']);
    }
    if ($hm < '07:00' || $hm >= '20:00') return;
    foreach ($ids("SELECT id FROM cr_bookings WHERE status IN ('approved','returning') AND end_at < NOW() - INTERVAL 30 MINUTE") as $id) {
        $b = carGet($conn, (int)$id);
        if (!$b || notifyAlreadySent($conn, 'car_key_overdue', $b['code'])) continue;
        $lm = (int)round((time() - strtotime($b['end_at'])) / 60); $late = intdiv($lm, 60);
        carNotify($conn, 'car_key_overdue', '🔑 เลยเวลาคืนรถ — ยังไม่รับกุญแจ', $b,
            ['สถานะ' => $b['status'] === 'returning' ? 'คนจองกดคืนแล้ว รอผู้ดูแลรับกุญแจ' : 'ยังไม่คืนรถ', 'เลยมา' => $late >= 24 ? round($late / 24) . ' วัน' : ($late ? "{$late} ชม." : "{$lm} นาที")], $b['code'], 'cars.html?tab=keep&view=active', 'รับกุญแจคืน');
    }
}

/** วันทำงานถัดไป (ข้ามวันหยุดในตั้งค่าวันหยุด) — ศุกร์เย็นส่งของวันจันทร์ */
function _carNextWorkday($conn) {
    for ($i = 1; $i <= 7; $i++) { $d = date('Y-m-d', strtotime("+$i day")); if (!function_exists('_notifyIsWorkday') || _notifyIsWorkday($conn, $d)) return $d; }
    return date('Y-m-d', strtotime('+1 day'));
}

function _cronCarDaily($conn) {
    $day = _carNextWorkday($conn);
    $from = "$day 00:00:00"; $to = date('Y-m-d', strtotime("$day +1 day")) . ' 00:00:00';
    $st = $conn->prepare("SELECT b.*, c.plate, c.company AS car_company, d.name AS driver_name, d.nickname AS driver_nick FROM cr_bookings b JOIN cr_cars c ON c.id = b.car_id LEFT JOIN cr_drivers d ON d.id = b.driver_id
        WHERE b.status IN ('pending','approved','returning') AND b.start_at < ? AND b.end_at > ? ORDER BY b.start_at");
    $st->bind_param('ss', $to, $from); $st->execute();
    $by = [];
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $b) $by[(string)$b['car_company']][] = $b;
    $drivers = $conn->query("SELECT id, name, nickname FROM cr_drivers WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
    $dTh = (int)date('j', strtotime($day)) . '/' . date('m', strtotime($day)) . '/' . (date('Y', strtotime($day)) + 543);
    $wd = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'][(int)date('w', strtotime($day))];
    foreach ($by as $co => $rows) {
        $ref = "$day:" . ($co ?: '-');
        if (notifyAlreadySent($conn, 'car_daily', $ref)) continue;
        $tm = fn($b) => date('H:i', strtotime($b['start_at'])) . '–' . date('H:i', strtotime($b['end_at']));
        $who = fn($b) => ($b['rider_name'] ?: $b['booker_name']);
        $lines = ["<b>🚗 งานรถวัน{$wd} {$dTh}" . ($co ? " ({$co})" : '') . ' — ' . count($rows) . ' ใบ</b>'];
        foreach ($drivers as $d) {
            $jobs = array_filter($rows, fn($b) => (int)$b['driver_id'] === (int)$d['id']);
            $lines[] = "<b>🧑‍✈️ {$d['name']}" . ($d['nickname'] ? " ({$d['nickname']})" : '') . ':</b> ' . ($jobs ? implode(' / ', array_map(fn($b) => $tm($b) . " {$b['plate']} → " . ($b['destination'] ?: $b['purpose']) . ' (' . $who($b) . ')', $jobs)) : 'ว่าง');
        }
        $need = array_filter($rows, fn($b) => (int)$b['need_driver'] && !$b['driver_id']);
        if ($need) $lines[] = '<b>⚠️ ขอคนขับ ยังไม่ได้เลือก:</b> ' . implode(' / ', array_map(fn($b) => $tm($b) . " {$b['plate']}", $need));
        $pend = array_filter($rows, fn($b) => $b['status'] === 'pending');
        if ($pend) $lines[] = '<b>🕓 ยังรออนุมัติ:</b> ' . count($pend) . ' ใบ';
        $lines[] = '<b>ทั้งหมด:</b>';
        foreach ($rows as $b) $lines[] = '• ' . $tm($b) . " {$b['plate']} — {$b['purpose']}" . ($b['destination'] ? " → {$b['destination']}" : '') . ' · ' . $who($b) . ' · ' . ((int)$b['need_driver'] ? ($b['driver_name'] ?: 'ขอคนขับ') : 'ขับเอง');
        sendNotify($conn, implode("\n", $lines), 'car_daily', $co ?: null, $ref, notifyLink($conn, 'cars.html', 'ดูตารางรถ 7 วัน'));
    }
}
