<?php
/**
 * notify_cron.php — แจ้งเตือนตามเวลา (เรียกจาก api.php ทุก request แบบ lazy เหมือนสรุปสต็อก)
 *
 * ไม่ต้องตั้งโปรแกรมบนเครื่องจริง — เช็กตอนมีคนใช้เว็บ · กันทำงานถี่ด้วย system_settings.notify_cron_last
 * กันส่งซ้ำด้วย notify_log (event + ref) — แต่ละเรื่องเด้งครั้งเดียว
 *   it_overdue           งานไม่มีใครรับเกินเวลารับเรื่อง / ซ่อมนานเกินเวลาปิดงาน (ต่องาน)
 *   server_check_missing 15:00–18:00 วันทำงาน ห้องที่ยังไม่บันทึกผลตรวจวันนี้ (วันละครั้ง)
 *   loan_overdue         ยืมอุปกรณ์เลยกำหนดคืน (ต่อใบ ครั้งเดียว)
 *   it_daily_summary     08:00–10:00 วันทำงาน สรุปงานค้างให้ทีม IT (วันละครั้ง)
 *   car_daily / car_pending_soon / car_key_overdue  จองรถ — ดู _cronCars ใน car_helper.php
 *   room_daily           07:00–09:00 วันทำงาน ห้องที่ต้องเตรียมวันนี้ + รออนุมัติ (ดู room_helper.php)
 */

// เวลาปิดงาน (ชม.) ชุดเดียวกับบอร์ดคิวงานและรายงาน IT
const NOTIFY_FIX_HOURS = ['ปกติ' => 72, 'ด่วน' => 24, 'ด่วนที่สุด' => 4];

function _checkNotifyCrons($conn) {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        // ทำอย่างมากทุก 5 นาที — ใช้ UPDATE แบบมีเงื่อนไขกันหลาย request แย่งกันรันพร้อมกัน
        $now = time();
        $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('notify_cron_last', '0')");
        $st = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'notify_cron_last' AND CAST(setting_value AS UNSIGNED) < ?");
        $nowS = (string)$now; $limit = $now - 300;
        $st->bind_param('si', $nowS, $limit);
        $st->execute();
        if ($st->affected_rows < 1) return;

        $workday = _notifyIsWorkday($conn, date('Y-m-d'));
        $hm = date('H:i');
        _cronItOverdue($conn);
        if (function_exists('_initLoanTables')) _cronLoanOverdue($conn);
        if (function_exists('_cronCars')) _cronCars($conn);
        if ($workday && $hm >= '07:00' && $hm < '09:00' && function_exists('_cronRoomDaily')) _cronRoomDaily($conn);
        if ($workday && $hm >= '08:00' && $hm < '10:00') _cronItDailySummary($conn);
        if ($workday && $hm >= '15:00' && $hm < '18:00') _cronServerCheckMissing($conn);
    } catch (Throwable $e) {
        error_log('[NotifyCron] ' . $e->getMessage());
    }
}

function _notifyIsWorkday($conn, $date) {
    $w = (int)date('w', strtotime($date));
    if ($w === 0 || $w === 6) return false;
    $st = $conn->prepare("SELECT 1 FROM company_holidays WHERE holiday_date = ? LIMIT 1");
    if (!$st) return true;
    $st->bind_param('s', $date);
    $st->execute();
    return !$st->get_result()->fetch_row();
}

// เวลารับเรื่องจากหน้าตั้งค่า (ข้อความเช่น "ภายใน 4 ชม.") → นาที · ชุดเดียวกับบอร์ดคิวงาน
function _notifyRespMinutes($conn) {
    $out = ['ปกติ' => 240, 'ด่วน' => 120, 'ด่วนที่สุด' => 30];
    $map = ['sla_normal' => 'ปกติ', 'sla_urgent' => 'ด่วน', 'sla_critical' => 'ด่วนที่สุด'];
    $r = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('sla_normal','sla_urgent','sla_critical')");
    if ($r) foreach ($r->fetch_all(MYSQLI_ASSOC) as $row) {
        if (preg_match('/(\d+(?:\.\d+)?)\s*(ชม|ชั่วโมง|นาที|วัน)/u', (string)$row['setting_value'], $m)) {
            $out[$map[$row['setting_key']]] = (int)((float)$m[1] * ($m[2] === 'นาที' ? 1 : ($m[2] === 'วัน' ? 1440 : 60)));
        }
    }
    return $out;
}

function _notifyMinTh($m) {
    $m = max(0, (int)round($m));
    if ($m < 60) return "{$m} นาที";
    if ($m < 1440) return floor($m / 60) . ' ชม.' . ($m % 60 && $m < 180 ? ' ' . ($m % 60) . ' นาที' : '');
    return floor($m / 1440) . ' วัน' . (floor(($m % 1440) / 60) ? ' ' . floor(($m % 1440) / 60) . ' ชม.' : '');
}

// อาการจากข้อความแจ้งซ่อม (บรรทัด "อาการที่พบ:") — ไม่มีก็ใช้บรรทัดแรก
function _notifySymptom($problem) {
    if (preg_match('/^(?:อาการที่พบ|ความต้องการ)[:：]\s*(.+)$/mu', (string)$problem, $m)) return mb_substr(trim($m[1]), 0, 80);
    $first = trim(preg_replace('/^\[[^\]]*\]\s*/u', '', strtok((string)$problem, "\n") ?: ''));
    return mb_substr($first, 0, 80);
}

// งานที่มีเวลาแจ้งจริง (จากประวัติเหตุการณ์) — งานเก่าที่ไม่มีประวัติไม่ถูกนับ กันเด้งงานเก่าพรึ่บตอนเปิดใช้ครั้งแรก
function _notifyOpenTickets($conn) {
    $sql = "SELECT l.id, l.company, l.requester, l.department, l.problem, l.status, l.urgency, l.servicedBy,
                (SELECT MIN(e.created_at) FROM mt_ticket_events e WHERE e.log_id COLLATE utf8mb4_unicode_ci = l.id COLLATE utf8mb4_unicode_ci AND e.event_type = 'CREATE') AS c_at
            FROM it_logs l
            WHERE l.status NOT IN ('Completed','Cancelled','Unrepairable') AND l.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $r = $conn->query($sql);
    return $r ? array_filter($r->fetch_all(MYSQLI_ASSOC), fn($t) => !empty($t['c_at'])) : [];
}

function _cronItOverdue($conn) {
    $resp = _notifyRespMinutes($conn);
    $waiting = ['Awaiting Confirmation', 'PR Submitted', 'PO Issued', 'Waiting for Parts', 'Waiting Parts', 'Sent to Vendor', 'Waiting for User'];
    foreach (_notifyOpenTickets($conn) as $t) {
        if (in_array($t['status'], $waiting, true)) continue; // รอคนอื่นอยู่ ไม่ใช่ช่างช้า
        $urg = $t['urgency'] ?: 'ปกติ';
        $el = (time() - strtotime($t['c_at'])) / 60;
        if ($t['status'] === 'Pending') { $lim = $resp[$urg] ?? 240; $kind = 'resp'; $what = 'ยังไม่มีใครรับเรื่อง'; }
        else { $lim = (NOTIFY_FIX_HOURS[$urg] ?? 72) * 60; $kind = 'fix'; $what = 'ซ่อมนานเกินเวลาปิดงาน'; }
        if ($el <= $lim) continue;
        $ref = $t['id'] . ':' . $kind;
        if (notifyAlreadySent($conn, 'it_overdue', $ref)) continue;
        $msg = "<b>⏰ งานซ่อมเกินเวลา — {$what}</b>\n"
             . "<b>เลขที่:</b> {$t['id']}\n"
             . "<b>อาการ:</b> " . _notifySymptom($t['problem']) . "\n"
             . "<b>ผู้แจ้ง:</b> {$t['requester']} · {$t['department']}\n"
             . "<b>ความด่วน:</b> {$urg}\n"
             . "<b>เกินมาแล้ว:</b> " . _notifyMinTh($el - $lim) . "\n"
             . "<b>ช่าง:</b> " . ($t['servicedBy'] ?: 'ยังไม่มี');
        sendNotify($conn, $msg, 'it_overdue', $t['company'] ?: null, $ref);
    }
}

function _cronItDailySummary($conn) {
    $ref = date('Y-m-d');
    if (notifyAlreadySent($conn, 'it_daily_summary', $ref)) return;
    $r = $conn->query("SELECT status, COUNT(*) n FROM it_logs WHERE status NOT IN ('Completed','Cancelled','Unrepairable') GROUP BY status");
    $by = []; if ($r) foreach ($r->fetch_all(MYSQLI_ASSOC) as $row) $by[$row['status']] = (int)$row['n'];
    $open = array_sum($by);
    $pending = $by['Pending'] ?? 0;
    $confirm = $by['Awaiting Confirmation'] ?? 0;
    $parts = ($by['PR Submitted'] ?? 0) + ($by['PO Issued'] ?? 0) + ($by['Sent to Vendor'] ?? 0) + ($by['Waiting for Parts'] ?? 0) + ($by['Waiting Parts'] ?? 0);
    $late = 0;
    $resp = _notifyRespMinutes($conn);
    foreach (_notifyOpenTickets($conn) as $t) {
        $urg = $t['urgency'] ?: 'ปกติ';
        $el = (time() - strtotime($t['c_at'])) / 60;
        if ($t['status'] === 'Pending' ? $el > ($resp[$urg] ?? 240) : (!in_array($t['status'], ['Awaiting Confirmation', 'PR Submitted', 'PO Issued', 'Sent to Vendor', 'Waiting for User'], true) && $el > (NOTIFY_FIX_HOURS[$urg] ?? 72) * 60)) $late++;
    }
    $y = $conn->query("SELECT COUNT(*) FROM it_logs WHERE DATE(COALESCE(completed_date, closed_date)) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetch_row()[0] ?? 0;
    $rooms = $conn->query("SELECT COUNT(*) FROM sc_rooms WHERE is_active = 1")->fetch_row()[0] ?? 0;
    $msg = "<b>☀️ สรุปเช้า ทีม IT — " . date('d/m/') . (date('Y') + 543) . "</b>\n"
         . "<b>งานค้างทั้งหมด:</b> {$open} งาน\n"
         . "<b>รอรับเรื่อง:</b> {$pending} งาน\n"
         . "<b>เกินเวลา:</b> " . ($late ? "⚠️ {$late} งาน" : '0') . "\n"
         . "<b>รออะไหล่/ภายนอก:</b> {$parts} งาน\n"
         . "<b>รอผู้แจ้งยืนยัน:</b> {$confirm} งาน\n"
         . "<b>ปิดงานเมื่อวาน:</b> {$y} งาน\n"
         . "<b>ห้อง Server ต้องตรวจวันนี้:</b> {$rooms} ห้อง";
    sendNotify($conn, $msg, 'it_daily_summary', null, $ref);
}

function _cronServerCheckMissing($conn) {
    $today = date('Y-m-d');
    if (notifyAlreadySent($conn, 'server_check_missing', $today)) return;
    // ห้องที่ยังไม่มีผลตรวจที่กรอกค่าแล้วของวันนี้
    $st = $conn->prepare("SELECT r.code, r.name, r.company FROM sc_rooms r
        WHERE r.is_active = 1 AND NOT EXISTS (
            SELECT 1 FROM sc_records c JOIN sc_values v ON v.record_id = c.id
            WHERE c.room_id = r.id AND c.check_date = ? AND v.val IS NOT NULL AND v.val <> '')
        ORDER BY r.sort_order");
    $st->bind_param('s', $today);
    $st->execute();
    $rooms = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    if (!$rooms) return;
    $msg = "<b>🖥️ ยังไม่ได้ตรวจห้อง Server วันนี้ (" . count($rooms) . " ห้อง)</b>\n"
         . "<b>ห้องที่ยังไม่บันทึก:</b>\n"
         . implode("\n", array_map(fn($r) => "• {$r['code']} — {$r['name']} ({$r['company']})", $rooms)) . "\n"
         . "<b>หมายเหตุ:</b> บันทึกย้อนหลังเกิน 1 วันต้องระบุเหตุผล";
    sendNotify($conn, $msg, 'server_check_missing', null, $today);
}

// ยืมอุปกรณ์เลยกำหนดคืน — เตือนกลุ่มครั้งเดียวต่อใบ (ขยายเวลาแล้วไม่นับ เพราะ end_date เลื่อนไปแล้ว)
function _cronLoanOverdue($conn) {
    _initLoanTables($conn);
    $r = $conn->query("SELECT * FROM it_loan_requests WHERE status = 'active' AND end_date IS NOT NULL AND end_date < CURDATE()");
    if (!$r) return;
    foreach ($r->fetch_all(MYSQLI_ASSOC) as $l) {
        if (notifyAlreadySent($conn, 'loan_overdue', $l['code'])) continue;
        $days = (int)((strtotime(date('Y-m-d')) - strtotime($l['end_date'])) / 86400);
        loanNotify($conn, 'loan_overdue', '⏰ ยืมอุปกรณ์เกินกำหนดคืน', $l, ['เกินมาแล้ว' => $days . ' วัน', 'ติดต่อ' => $l['contact']]);
    }
}
