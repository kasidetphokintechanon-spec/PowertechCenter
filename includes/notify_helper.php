<?php
/**
 * notify_helper.php — Unified notification routing
 *
 * Events:
 *   it_repair     งานซ่อม/แจ้งปัญหา IT ใหม่
 *   it_cancel     ยกเลิกงาน IT
 *   it_approve    ปิด/อนุมัติงาน IT
 *   stock_new     เบิกของใหม่ (รออนุมัติ)
 *   stock_approve อนุมัติเบิกของ
 *   stock_reject  ปฏิเสธเบิกของ
 *   stock_cancel  ยกเลิกคำขอเบิก
 *   stock_low     สต็อกใกล้หมด
 *   loan_new      ยืมอุปกรณ์ใหม่
 *   loan_approve  อนุมัติยืม
 *   loan_reject   ปฏิเสธยืม
 *   server_check_issue  เช็คห้อง Server พบรายการมีปัญหา (✗)
 *   loan_return   คืนอุปกรณ์ที่ยืม
 *   it_user_reply ผู้แจ้งตอบกลับช่าง / กด "ยังไม่หาย"
 *   it_overdue    งานซ่อมเกินเวลา (ยังไม่มีใครรับ / ซ่อมนานเกิน) — ตัวเช็กตามเวลา
 *   it_daily_summary    สรุปเช้าให้ทีม IT 08:00 วันทำงาน — ตัวเช็กตามเวลา
 *   server_check_missing ยังไม่ได้ตรวจห้อง Server วันนี้ (15:00 วันทำงาน) — ตัวเช็กตามเวลา
 *
 * ทุกการส่งถูกจดใน notify_log (หน้า "ศูนย์แจ้งเตือน" ใช้โชว์ไฟเขียว/แดง และ "ส่งไปล่าสุด")
 */

// ── TABLE INIT ────────────────────────────────────────────────────────
function _initStockSummarySchedulesTable($conn) {
    static $done = false;
    if ($done) return;
    $conn->query("CREATE TABLE IF NOT EXISTS stock_summary_schedules (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        name         VARCHAR(100) NOT NULL DEFAULT '',
        company      VARCHAR(10)  DEFAULT NULL,
        stock_type   VARCHAR(20)  DEFAULT NULL,
        frequency    ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
        send_time    VARCHAR(5)   NOT NULL DEFAULT '08:00',
        day_of_week  TINYINT      DEFAULT NULL,
        day_of_month TINYINT      DEFAULT NULL,
        enabled      TINYINT(1)   NOT NULL DEFAULT 1,
        last_sent    DATE         DEFAULT NULL,
        created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    )");
    $done = true;
}

function _initNotifyChannelsTable($conn) {
    static $done = false;
    if ($done) return;
    $conn->query("CREATE TABLE IF NOT EXISTS notify_channels (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        name         VARCHAR(100) NOT NULL,
        channel_type ENUM('telegram','line') NOT NULL DEFAULT 'telegram',
        token        TEXT NOT NULL,
        dest_id      VARCHAR(255) NOT NULL,
        events       JSON NOT NULL,
        company      VARCHAR(10) DEFAULT NULL,
        enabled      TINYINT(1) NOT NULL DEFAULT 1,
        sort_order   INT NOT NULL DEFAULT 0,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $done = true;
}

// ประวัติการส่ง — ok: 1 สำเร็จ / 0 ล้มเหลว / 2 ไม่มีกลุ่มรับ · ref ใช้กันส่งซ้ำของตัวเช็กตามเวลา
function _initNotifyLogTable($conn) {
    static $done = false;
    if ($done) return;
    $conn->query("CREATE TABLE IF NOT EXISTS notify_log (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        event        VARCHAR(40)  NOT NULL,
        ref          VARCHAR(80)  DEFAULT NULL,
        title        VARCHAR(200) DEFAULT NULL,
        channel_id   INT          DEFAULT NULL,
        channel_name VARCHAR(100) DEFAULT NULL,
        ok           TINYINT(1)   NOT NULL DEFAULT 1,
        detail       VARCHAR(255) DEFAULT NULL,
        INDEX idx_nl_created (created_at),
        INDEX idx_nl_event_ref (event, ref),
        INDEX idx_nl_channel (channel_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}

function _notifyLog($conn, $event, $ref, $title, $chId, $chName, $ok, $detail = null) {
    _initNotifyLogTable($conn);
    $st = $conn->prepare("INSERT INTO notify_log (event, ref, title, channel_id, channel_name, ok, detail) VALUES (?,?,?,?,?,?,?)");
    if (!$st) return;
    $title  = mb_substr((string)$title, 0, 200);
    $detail = $detail === null ? null : mb_substr((string)$detail, 0, 255);
    $st->bind_param('sssisis', $event, $ref, $title, $chId, $chName, $ok, $detail);
    $st->execute();
}

// เคยส่งเรื่องนี้ไปแล้วหรือยัง (ตัวเช็กตามเวลาส่งแค่ครั้งเดียวต่อเรื่อง)
function notifyAlreadySent($conn, $event, $ref) {
    _initNotifyLogTable($conn);
    $st = $conn->prepare("SELECT 1 FROM notify_log WHERE event = ? AND ref = ? LIMIT 1");
    $st->bind_param('ss', $event, $ref);
    $st->execute();
    return (bool)$st->get_result()->fetch_row();
}

// ── MAIN ENTRY POINT ──────────────────────────────────────────────────
/**
 * ส่งการแจ้งเตือนไปยังทุก channel ที่ match กับ event + company
 *
 * @param mysqli      $conn
 * @param string      $message   HTML message (Telegram) — LINE จะ parse auto
 * @param string      $event     e.g. 'stock_new', 'it_repair'
 * @param string|null $company   'PTA'|'PT4'|'PTE'|null
 */
function sendNotify($conn, $message, $event, $company = null, $ref = null) {
    _initNotifyChannelsTable($conn);
    preg_match('/<b>(.*?)<\/b>/', $message, $_tm);
    $title = trim(strip_tags($_tm[1] ?? $event));

    $res = $conn->query("SELECT * FROM notify_channels WHERE enabled = 1 ORDER BY sort_order ASC, id ASC");
    $channels = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

    // ไม่มีกลุ่มเปิดอยู่ = ไม่ส่ง · เดิมหันไปใช้กุญแจเก่าใน system_settings ซึ่งยกเลิกแล้ว (หลุด 22 ต.ค. 2026)
    if (empty($channels)) {
        error_log("[Notify] No enabled channels — not sent (event={$event})");
        _notifyLog($conn, $event, $ref, $title, null, null, 2, 'ไม่มีกลุ่มที่เปิดใช้งาน');
        return;
    }

    $matchCount = 0;
    foreach ($channels as $ch) {
        // Filter: company
        if ($ch['company'] && $company && $ch['company'] !== $company) {
            error_log("[Notify] SKIP '{$ch['name']}' — company mismatch (ch={$ch['company']}, ticket={$company})");
            continue;
        }

        // Filter: events (["*"] = ทุก event)
        $events = json_decode($ch['events'], true) ?? ['*'];
        if (!in_array('*', $events) && !in_array($event, $events)) {
            error_log("[Notify] SKIP '{$ch['name']}' — event '{$event}' not in [" . implode(',', $events) . "]");
            continue;
        }

        error_log("[Notify] SEND '{$ch['name']}' via {$ch['channel_type']} (event={$event}, company=" . ($company ?: 'any') . ")");
        $matchCount++;
        $r = [false, 'ประเภทกลุ่มไม่รู้จัก'];
        if ($ch['channel_type'] === 'telegram') {
            $r = _notifySendTelegram($ch['token'], $ch['dest_id'], $message);
        } elseif ($ch['channel_type'] === 'line') {
            $r = _notifySendLine($ch['token'], $ch['dest_id'], $message, $event);
        }
        _notifyLog($conn, $event, $ref, $title, (int)$ch['id'], $ch['name'], $r[0] ? 1 : 0, $r[0] ? null : $r[1]);
    }

    if ($matchCount === 0) {
        error_log("[Notify] NO MATCH for event='{$event}' company='" . ($company ?: 'any') . "' — notification not sent");
        _notifyLog($conn, $event, $ref, $title, null, null, 2, 'ไม่มีกลุ่มรับเรื่องนี้');
    }
}

// ── SEND VIA TELEGRAM ─────────────────────────────────────────────────
function _notifySendTelegram($token, $chat_id, $message) {
    $url  = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $message, 'parse_mode' => 'HTML'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = $result === false ? curl_error($ch) : '';
    if ($result === false) error_log("[Notify] Telegram curl error: " . $err);
    curl_close($ch);
    if ($result === false) return [false, 'ต่อ Telegram ไม่ได้: ' . $err];
    if ($httpCode !== 200) return [false, $httpCode === 401 ? 'กุญแจ (Token) ไม่ถูกต้อง' : "Telegram ตอบ HTTP {$httpCode}"];
    return [true, ''];
}

// ── SEND VIA LINE MESSAGING API ───────────────────────────────────────
function _notifySendLine($token, $dest_id, $message, $event = 'general') {
    // สี header ตาม event type
    $colorMap = [
        'it_repair'     => '#EF454D',
        'it_cancel'     => '#FF9500',
        'it_approve'    => '#1DB446',
        'stock_new'     => '#2563EB',
        'stock_approve' => '#16A34A',
        'stock_reject'  => '#DC2626',
        'stock_cancel'  => '#FF9500',
        'stock_low'     => '#D97706',
        'stock_summary' => '#0F172A',
        'loan_new'      => '#7C3AED',
        'loan_approve'  => '#16A34A',
        'loan_reject'   => '#DC2626',
        'server_check_issue' => '#B91C1C',
        'server_check_missing' => '#B45309',
        'loan_return'   => '#0891B2',
        'it_user_reply' => '#7C3AED',
        'it_overdue'    => '#DC2626',
        'it_daily_summary' => '#0F172A',
    ];
    $color = $colorMap[$event] ?? '#1DB446';

    // Parse title จาก <b>Title</b>
    preg_match('/<b>(.*?)<\/b>/', $message, $titleMatch);
    $title = strip_tags($titleMatch[1] ?? 'Notification');

    // Build Flex bubble body — parse line-by-line to support multi-line bullet lists
    $contents = [];
    $contents[] = ['type' => 'text', 'text' => $title, 'weight' => 'bold', 'size' => 'lg', 'color' => $color, 'wrap' => true];
    $contents[] = ['type' => 'separator', 'margin' => 'md'];

    if (preg_match('/<b>[^<]+?:<\/b>/', $message)) {
        // Structured message: parse line-by-line (handles multi-line bullet lists correctly)
        $msgLines   = explode("\n", $message);
        $kvRows     = [];
        $bulletHdr  = null;
        $bulletLines= [];
        $skipFirst  = true;

        $flushKvRows = function() use (&$kvRows, &$contents) {
            if (!empty($kvRows)) {
                $contents[] = ['type' => 'box', 'layout' => 'vertical', 'margin' => 'lg', 'spacing' => 'sm', 'contents' => $kvRows];
                $kvRows = [];
            }
        };
        $flushBullets = function() use (&$bulletHdr, &$bulletLines, &$contents) {
            if ($bulletHdr !== null && !empty($bulletLines)) {
                $hdrText = trim(strip_tags($bulletHdr));
                $bc = [['type' => 'text', 'text' => $hdrText, 'color' => '#aaaaaa', 'size' => 'sm', 'weight' => 'bold', 'margin' => 'md']];
                foreach ($bulletLines as $bl) {
                    $bc[] = ['type' => 'text', 'text' => $bl, 'wrap' => true, 'color' => '#333333', 'size' => 'sm'];
                }
                $contents[] = ['type' => 'box', 'layout' => 'vertical', 'margin' => 'xs', 'spacing' => 'xs', 'contents' => $bc];
            }
            $bulletHdr   = null;
            $bulletLines = [];
        };

        foreach ($msgLines as $line) {
            $line = rtrim($line);
            if ($skipFirst) { $skipFirst = false; continue; }
            if (strpos($line, '➖') === 0 || strpos($line, '#') === 0 || $line === '') continue;

            if (preg_match('/^<b>([^<]+?):<\/b>\s*(.*)$/u', $line, $m)) {
                $key = trim(strip_tags($m[1]));
                $val = trim(strip_tags($m[2]));
                if (!$key) continue;

                if ($val !== '') {
                    // Inline value → standard two-column kv row
                    $flushBullets();
                    $kvRows[] = [
                        'type' => 'box', 'layout' => 'baseline', 'spacing' => 'sm',
                        'contents' => [
                            ['type' => 'text', 'text' => $key, 'color' => '#aaaaaa', 'size' => 'sm', 'flex' => 2],
                            ['type' => 'text', 'text' => $val, 'wrap' => true, 'color' => '#333333', 'size' => 'sm', 'flex' => 4]
                        ]
                    ];
                } else {
                    // No inline value → expect bullet lines next
                    $flushKvRows();
                    $flushBullets();
                    $bulletHdr   = $m[1];
                    $bulletLines = [];
                }
            } elseif ($bulletHdr !== null && trim($line) !== '') {
                // Accumulate bullet lines for current header
                $bulletLines[] = strip_tags($line);
            }
        }
        $flushKvRows();
        $flushBullets();

    } else {
        // ไม่มี key-value → plain text (เช่น stock summary)
        $plain = strip_tags(str_replace(
            ['<b>', '</b>', '<br>', '<br/>', '<br />'],
            ['', '', "\n", "\n", "\n"],
            html_entity_decode($message)
        ));
        // ตัดบรรทัดแรก (title) ออก เพราะแสดงแล้วข้างบน
        $lines = explode("\n", trim($plain));
        array_shift($lines);
        $body = trim(implode("\n", $lines));
        if ($body) {
            $contents[] = [
                'type'  => 'text',
                'text'  => mb_substr($body, 0, 2000),
                'size'  => 'sm',
                'color' => '#333333',
                'wrap'  => true,
                'margin' => 'md'
            ];
        }
    }

    $contents[] = ['type' => 'separator', 'margin' => 'xl'];
    $contents[] = ['type' => 'text', 'text' => date('d/m/Y H:i'), 'size' => 'xs', 'color' => '#aaaaaa', 'align' => 'end', 'margin' => 'md'];

    $lineData = [
        'to'       => $dest_id,
        'messages' => [[
            'type'     => 'flex',
            'altText'  => $title,
            'contents' => ['type' => 'bubble', 'body' => ['type' => 'box', 'layout' => 'vertical', 'contents' => $contents]]
        ]]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.line.me/v2/bot/message/push");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($lineData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($result === false) {
        error_log("[Notify] LINE curl error: " . curl_error($ch));
    } elseif ($httpCode !== 200) {
        error_log("[Notify] LINE API error HTTP {$httpCode}: " . $result);
    } else {
        error_log("[Notify] LINE sent OK to dest={$dest_id} event={$event}");
    }
    $err = $result === false ? curl_error($ch) : '';
    curl_close($ch);
    if ($result === false) return [false, 'ต่อ LINE ไม่ได้: ' . $err];
    if ($httpCode === 401) return [false, 'กุญแจ (Token) ไม่ถูกต้อง หรือถูกยกเลิกแล้ว'];
    if ($httpCode === 429) return [false, 'ส่งเกินโควตาข้อความของ LINE เดือนนี้'];
    if ($httpCode !== 200) return [false, "LINE ตอบ HTTP {$httpCode}"];
    return [true, ''];
}

// ── STOCK SUMMARY BUILDER ─────────────────────────────────────────────
/**
 * สร้างข้อความสรุป Stock ทั้งหมด จัดกลุ่มตาม บริษัท → ประเภท
 */
function buildStockSummaryMessage($conn, $company = null, $stock_type = null) {
    $where    = ["si.is_active = 1"];
    $params   = [];
    $types    = '';
    $txClause = '';

    if ($company && in_array($company, ['PTA','PT4','PTE'])) {
        $where[]   = "si.company = ?";
        $params[]  = $company;
        $types    .= 's';
        $txClause  = "AND st.company = ?";
    }

    if ($stock_type && in_array($stock_type, ['stationery','maid'])) {
        $where[]  = "si.type = ?";
        $params[] = $stock_type;
        $types   .= 's';
    }

    $whereStr = 'WHERE ' . implode(' AND ', $where);
    $sql = "SELECT si.id, si.name, si.unit, si.company, si.type, si.min_stock,
                COALESCE(SUM(CASE
                    WHEN st.type='received' THEN  st.quantity
                    WHEN st.type='issued'   THEN -st.quantity
                    WHEN st.type='adjusted' THEN  st.quantity
                    WHEN st.type='returned' THEN  st.quantity
                END), 0) AS current_balance
            FROM stock_items si
            LEFT JOIN stock_transactions st ON st.item_id = si.id {$txClause}
            {$whereStr}
            GROUP BY si.id
            ORDER BY si.company ASC, si.type ASC, si.name ASC";

    if ($txClause) { array_unshift($params, $company); $types = 's' . $types; }

    $stmt = $conn->prepare($sql);
    if (!$stmt) return "<b>📦 สรุป Stock</b>\nไม่สามารถดึงข้อมูลได้";
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($rows)) return "<b>📦 สรุป Stock</b>\nไม่มีรายการสินค้าในระบบ";

    $now     = date('d/m/Y H:i');
    $typeMap = ['stationery' => '📎 เครื่องเขียน', 'maid' => '🧹 แม่บ้าน'];

    // Group by company → type
    $grouped = [];
    foreach ($rows as $r) $grouped[$r['company']][$r['type']][] = $r;

    $lines = ["<b>📦 สรุป Stock — {$now}</b>", "➖➖➖➖➖➖➖➖➖➖"];
    $total = 0; $lowCount = 0; $emptyCount = 0;

    foreach ($grouped as $comp => $typeGroups) {
        $lines[] = "\n<b>🏢 {$comp}</b>";
        foreach ($typeGroups as $type => $items) {
            $typeLabel = $typeMap[$type] ?? $type;
            $lines[] = "<b>{$typeLabel}</b>";
            foreach ($items as $item) {
                $bal  = intval($item['current_balance']);
                $min  = intval($item['min_stock']);
                $total++;
                if ($bal <= 0) {
                    $icon = '🔴'; $warn = ' ❗หมดแล้ว'; $emptyCount++;
                } elseif ($min > 0 && $bal <= $min) {
                    $icon = '🟡'; $warn = ' ⚠️ใกล้หมด'; $lowCount++;
                } else {
                    $icon = '🟢'; $warn = '';
                }
                $lines[] = "{$icon} {$item['name']} — <b>{$bal}</b> {$item['unit']}{$warn}";
            }
        }
    }

    $lines[] = "\n➖➖➖➖➖➖➖➖➖➖";
    $lines[] = "รวม {$total} รายการ | 🔴 หมด {$emptyCount} | 🟡 ใกล้หมด {$lowCount}";
    $lines[] = "#StockSummary";

    return implode("\n", $lines);
}

// ── LAZY CRON: SCHEDULED STOCK SUMMARY ───────────────────────────────
/**
 * เรียกจาก api.php ทุก request — ตรวจว่าถึงเวลาส่ง Stock Summary ไหม
 */
function _checkStockSummaryCron($conn) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    _initStockSummarySchedulesTable($conn);

    $res = $conn->query("SELECT * FROM stock_summary_schedules WHERE enabled=1");
    if (!$res || $res->num_rows === 0) return;

    $today       = date('Y-m-d');
    $currentTime = date('H:i');
    $dayOfWeek   = intval(date('w'));   // 0=Sunday
    $dayOfMonth  = intval(date('j'));   // 1-31

    while ($sched = $res->fetch_assoc()) {
        // ตรวจว่าส่งวันนี้แล้วไหม
        if ($sched['last_sent'] === $today) continue;

        // ตรวจว่าถึงเวลาไหม (ภายใน 10 นาที)
        $schedDt   = DateTime::createFromFormat('H:i', $sched['send_time']);
        $currentDt = DateTime::createFromFormat('H:i', $currentTime);
        if (!$schedDt || !$currentDt) continue;
        $diffMins = ($currentDt->getTimestamp() - $schedDt->getTimestamp()) / 60;
        if ($diffMins < 0 || $diffMins > 10) continue;

        // ตรวจ frequency
        $freq = $sched['frequency'];
        if ($freq === 'weekly'  && intval($sched['day_of_week'])  !== $dayOfWeek)  continue;
        if ($freq === 'monthly' && intval($sched['day_of_month']) !== $dayOfMonth) continue;

        // ส่ง!
        try {
            $msg = buildStockSummaryMessage($conn, $sched['company'] ?: null, $sched['stock_type'] ?: null);
            sendNotify($conn, $msg, 'stock_summary', $sched['company'] ?: null);
            $sid = intval($sched['id']);
            $updSched = $conn->prepare("UPDATE stock_summary_schedules SET last_sent=? WHERE id=?");
            $updSched->bind_param("si", $today, $sid);
            $updSched->execute();
            error_log("[StockCron] Schedule #{$sid} '{$sched['name']}' sent at {$currentTime}");
        } catch (Throwable $e) {
            error_log("[StockCron] Schedule #{$sched['id']} error: " . $e->getMessage());
        }
    }
}
