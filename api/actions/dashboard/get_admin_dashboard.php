<?php
// api/actions/dashboard/get_admin_dashboard.php — Admin morning briefing
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode(['status' => 'error', 'message' => 'Database unavailable']);
    exit;
}

$role    = $_SESSION['role'] ?? 'guest';
$company = $_SESSION['company'] ?? '';
$isAdmin = ($role === 'admin');
$co      = (!$isAdmin && $company) ? $company : null;

$scalar = function(string $sql, string $types = '', array $params = []) use ($conn): int {
    if ($types && $params) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) return 0;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['c'] ?? 0);
    }
    $res = $conn->query($sql);
    if (!$res) return 0;
    return (int)($res->fetch_assoc()['c'] ?? 0);
};

$active = "'In Progress','Received','Waiting for Parts','Temporary Fix','PR Submitted','PO Issued','Parts Received'";

// ─── KPI ──────────────────────────────────────────────────────────────────
$kpi = ['pending'=>0,'in_progress'=>0,'completed_today'=>0,'stock_pending'=>0,'loan_pending'=>0];
try {
    $kpi['pending']         = $co ? $scalar("SELECT COUNT(*) AS c FROM it_logs WHERE status='Pending' AND company=?", 's', [$co])
                                  : $scalar("SELECT COUNT(*) AS c FROM it_logs WHERE status='Pending'");
    $kpi['in_progress']     = $co ? $scalar("SELECT COUNT(*) AS c FROM it_logs WHERE status IN ({$active}) AND company=?", 's', [$co])
                                  : $scalar("SELECT COUNT(*) AS c FROM it_logs WHERE status IN ({$active})");
    $kpi['completed_today'] = $co ? $scalar("SELECT COUNT(*) AS c FROM it_logs WHERE status='Completed' AND DATE(date)=CURDATE() AND company=?", 's', [$co])
                                  : $scalar("SELECT COUNT(*) AS c FROM it_logs WHERE status='Completed' AND DATE(date)=CURDATE()");
    $kpi['stock_pending']   = $co ? $scalar("SELECT COUNT(*) AS c FROM stock_requests WHERE status='pending' AND company=?", 's', [$co])
                                  : $scalar("SELECT COUNT(*) AS c FROM stock_requests WHERE status='pending'");
    $kpi['loan_pending']    = $co ? $scalar("SELECT COUNT(*) AS c FROM it_assets WHERE status='รออนุมัติ' AND company=?", 's', [$co])
                                  : $scalar("SELECT COUNT(*) AS c FROM it_assets WHERE status='รออนุมัติ'");
} catch (Throwable $e) {
    error_log('[get_admin_dashboard] KPI: ' . $e->getMessage());
}

// ─── IT Tickets Pending (top 5, oldest first) ─────────────────────────────
$tickets = [];
try {
    $sql = "SELECT id, company, requester, department, problem, date FROM it_logs WHERE status='Pending'" .
           ($co ? " AND company=?" : "") . " ORDER BY date ASC LIMIT 5";
    if ($co) {
        $stmt = $conn->prepare($sql); $stmt->bind_param('s', $co); $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }
    if ($res) while ($r = $res->fetch_assoc()) $tickets[] = $r;
} catch (Throwable $e) { error_log('[get_admin_dashboard] tickets: ' . $e->getMessage()); }

// ─── Stock รอ Approve (top 5) ─────────────────────────────────────────────
$stockReqs = [];
try {
    $sql = "SELECT sr.id, sr.company, sr.requester_name, sr.stock_type, sr.department,
                   COUNT(sri.id) AS item_count
            FROM stock_requests sr
            LEFT JOIN stock_request_items sri ON sri.request_id = sr.id
            WHERE sr.status = 'pending'" .
           ($co ? " AND sr.company=?" : "") . " GROUP BY sr.id ORDER BY sr.id DESC LIMIT 5";
    if ($co) {
        $stmt = $conn->prepare($sql); $stmt->bind_param('s', $co); $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }
    if ($res) while ($r = $res->fetch_assoc()) $stockReqs[] = $r;
} catch (Throwable $e) { error_log('[get_admin_dashboard] stock: ' . $e->getMessage()); }

// ─── Loan รอ Approve (top 5) ──────────────────────────────────────────────
$loanReqs = [];
try {
    $sql = "SELECT id, name AS asset_name, equipment_id, borrower, company, department
            FROM it_assets WHERE status='รออนุมัติ'" .
           ($co ? " AND company=?" : "") . " ORDER BY id DESC LIMIT 5";
    if ($co) {
        $stmt = $conn->prepare($sql); $stmt->bind_param('s', $co); $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }
    if ($res) while ($r = $res->fetch_assoc()) $loanReqs[] = $r;
} catch (Throwable $e) { error_log('[get_admin_dashboard] loan: ' . $e->getMessage()); }

// ─── Server Check — today's inspection status ─────────────────────────────
// Mirrors the same "checked / issue / pending" logic as server_check.html's TV Mode,
// but computed server-side from sc_records/sc_values (no live network probing needed —
// this is yesterday/today's already-submitted checklist data, not a live device check).
$serverCheck = ['total' => 0, 'checked' => 0, 'issue' => 0, 'closed_today' => false];
try {
    $today = date('Y-m-d');
    $dow   = (int)date('w', strtotime($today)); // 0=Sun, 6=Sat
    $isWeekend = ($dow === 0 || $dow === 6);

    $hstmt = $conn->prepare("SELECT 1 FROM company_holidays WHERE holiday_date=? AND (company IS NULL OR company='' OR company=?) LIMIT 1");
    $hco = $co ?: '';
    $hstmt->bind_param('ss', $today, $hco);
    $hstmt->execute();
    $isHoliday = (bool)$hstmt->get_result()->fetch_row();

    if ($isWeekend || $isHoliday) {
        $serverCheck['closed_today'] = true;
    } else {
        $sql = "SELECT r.id, r.company,
                       (SELECT COUNT(*) FROM sc_items WHERE room_id=r.id AND is_active=1) AS total_items
                FROM sc_rooms r WHERE r.is_active=1" . ($co ? " AND r.company=?" : "");
        if ($co) { $stmt = $conn->prepare($sql); $stmt->bind_param('s', $co); $stmt->execute(); $rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); }
        else { $rooms = $conn->query($sql)->fetch_all(MYSQLI_ASSOC); }

        $roomsWithItems = array_filter($rooms, fn($r) => (int)$r['total_items'] > 0);
        $serverCheck['total'] = count($roomsWithItems);

        foreach ($roomsWithItems as $r) {
            $rid = (int)$r['id'];
            $rec = $conn->query("SELECT id FROM sc_records WHERE room_id=$rid AND check_date='$today'")->fetch_assoc();
            if (!$rec) continue; // not checked yet today
            $recId   = (int)$rec['id'];
            $filled  = (int)$conn->query("SELECT COUNT(*) c FROM sc_values WHERE record_id=$recId AND val IS NOT NULL AND val!=''")->fetch_assoc()['c'];
            $hasIssue = (int)$conn->query("SELECT COUNT(*) c FROM sc_values WHERE record_id=$recId AND val='✖'")->fetch_assoc()['c'] > 0;
            if ($hasIssue) $serverCheck['issue']++;
            elseif ($filled >= (int)$r['total_items']) $serverCheck['checked']++;
        }
    }
} catch (Throwable $e) { error_log('[get_admin_dashboard] server_check: ' . $e->getMessage()); }

// ─── IT Links — device count ───────────────────────────────────────────────
// Same source as get_it_links_config.php (system_settings), scoped by company if needed.
$itLinksDevices = 0;
try {
    $row = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'it_links_config' LIMIT 1")->fetch_assoc();
    if ($row && $row['setting_value']) {
        $cfg = json_decode($row['setting_value'], true);
        if (is_array($cfg) && isset($cfg['companies'])) {
            foreach ($cfg['companies'] as $company) {
                if ($co && strcasecmp($company['code'] ?? '', $co) !== 0) continue;
                foreach (($company['sections'] ?? []) as $section) {
                    $itLinksDevices += count($section['devices'] ?? []);
                }
            }
        }
    }
} catch (Throwable $e) { error_log('[get_admin_dashboard] it_links: ' . $e->getMessage()); }

echo json_encode([
    'status'          => 'success',
    'kpi'             => $kpi,
    'tickets'         => $tickets,
    'stock_requests'  => $stockReqs,
    'loan_requests'   => $loanReqs,
    'server_check'    => $serverCheck,
    'it_links_devices'=> $itLinksDevices,
], JSON_UNESCAPED_UNICODE);
