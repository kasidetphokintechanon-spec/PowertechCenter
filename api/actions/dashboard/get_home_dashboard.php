<?php
/**
 * Single lightweight payload for index.html home dashboard.
 */
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized', 'loggedin' => false]);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode(['status' => 'error', 'message' => 'Database unavailable']);
    exit;
}

$userId = (string)$_SESSION['user_id'];
$role = (string)($_SESSION['role'] ?? 'guest');
$company = (string)($_SESSION['company'] ?? '');

if (function_exists('refreshSessionPermissions')) {
    refreshSessionPermissions($conn);
}

$companyNameMap = [
    'PTA' => 'Powertech Engine Assembly',
    'PT4' => 'Powertech 2004',
    'PTE' => 'Powertech Energy Solutions',
];
$sessionOut = [
    'loggedin' => true,
    'user_id' => $userId,
    'username' => $_SESSION['username'] ?? $userId,
    'role' => $role,
    'display_name' => $_SESSION['display_name'] ?? 'User',
    'company' => $company,
    'company_full_name' => $companyNameMap[$company] ?? 'Powertech IT',
    'primary_department' => $_SESSION['primary_department'] ?? null,
    'can_access_admin' => !empty($_SESSION['can_access_admin']),
    'can_manage_stock' => intval($_SESSION['can_manage_stock'] ?? 0),
    'effective_permissions' => $_SESSION['effective_permissions'] ?? [],
];

function homeDashBindCompany(string $role, string $company, array &$where, array &$params, string &$types): void {
    if ($role !== 'admin' && $company !== '') {
        $where[] = 'company = ?';
        $params[] = $company;
        $types .= 's';
    }
}

// KPI aggregates (SQL only — no full table scan in browser)
$kpi = ['pending' => 0, 'in_progress' => 0, 'completed_today' => 0, 'loan_pending' => 0];
$activeStatuses = ['In Progress', 'Received', 'Waiting for Parts', 'Temporary Fix', 'PR Submitted', 'PO Issued', 'Parts Received'];

try {
    $where = ["status = 'Pending'"];
    $params = [];
    $types = '';
    homeDashBindCompany($role, $company, $where, $params, $types);
    $sql = 'SELECT COUNT(*) AS c FROM it_logs WHERE ' . implode(' AND ', $where);
    $kpi['pending'] = homeDashScalar($conn, $sql, $types, $params);

    $inList = "'" . implode("','", array_map([$conn, 'real_escape_string'], $activeStatuses)) . "'";
    $where = ["status IN ($inList)"];
    $params = [];
    $types = '';
    homeDashBindCompany($role, $company, $where, $params, $types);
    $sql = 'SELECT COUNT(*) AS c FROM it_logs WHERE ' . implode(' AND ', $where);
    $kpi['in_progress'] = homeDashScalar($conn, $sql, $types, $params);

    $where = ["status = 'Completed'", 'DATE(date) = CURDATE()'];
    $params = [];
    $types = '';
    homeDashBindCompany($role, $company, $where, $params, $types);
    $sql = 'SELECT COUNT(*) AS c FROM it_logs WHERE ' . implode(' AND ', $where);
    $kpi['completed_today'] = homeDashScalar($conn, $sql, $types, $params);

    _initLoanTables($conn);
    $loanSql = "SELECT COUNT(*) AS c FROM it_loan_requests WHERE (status = 'requested' OR (status = 'active' AND extend_status = 'pending'))";
    $loanParams = [];
    $loanTypes = '';
    if ($role !== 'admin' && $company !== '') {
        $loanSql .= ' AND asset_company = ?';
        $loanParams[] = $company;
        $loanTypes = 's';
    }
    $kpi['loan_pending'] = homeDashScalar($conn, $loanSql, $loanTypes, $loanParams);
} catch (Throwable $e) {
    error_log('[get_home_dashboard] KPI: ' . $e->getMessage());
}

// My tickets (max 8) — user scoped only
$myTickets = [];
try {
    $sql = "SELECT id, date, status, requester_id, servicedById, servicedBy, requester, department, LEFT(problem, 120) AS problem
            FROM it_logs
            WHERE (requester_id = ? OR servicedById = ?)
              AND status NOT IN ('Completed','Closed','Cancelled','Unrepairable')
            ORDER BY (status = 'Pending') DESC, date DESC, id DESC
            LIMIT 8";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ss', $userId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $myTickets[] = $row;
        }
    }
} catch (Throwable $e) {
    error_log('[get_home_dashboard] tickets: ' . $e->getMessage());
}

// My active loans (max 3)
$myLoans = [];
try {
    _initLoanTables($conn);
    $sql = "SELECT id AS loan_id, equipment_name AS asset_name, borrower_id, end_date,
            CASE status WHEN 'requested' THEN 'Pending' WHEN 'approved' THEN 'Approved' ELSE 'Active' END AS loan_status
            FROM it_loan_requests
            WHERE borrower_id = ? AND status IN ('requested', 'approved', 'active')
            ORDER BY start_date LIMIT 3";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $myLoans[] = $row;
        }
    }
} catch (Throwable $e) {
    error_log('[get_home_dashboard] loans: ' . $e->getMessage());
}

// ใบเบิกของที่ยังไม่ปิด (สูงสุด 3) — "พร้อมรับ" ขึ้นก่อน ให้รู้ว่าต้องไปรับของ
$myStock = [];
try {
    _initStockFlow($conn);
    $st = $conn->prepare("SELECT id FROM stock_requests WHERE requester_id = ? AND status IN ('pending','approved','ready') ORDER BY FIELD(status,'ready','approved','pending'), id DESC LIMIT 3");
    if ($st) {
        $st->bind_param('s', $userId);
        $st->execute();
        foreach ($st->get_result()->fetch_all() as $x) {
            $r = stockRow(stockGetRequest($conn, (int)$x[0]));
            $myStock[] = ['id' => $r['id'], 'code' => $r['code'], 'status' => $r['status'], 'status_th' => $r['status_th'], 'type_th' => $r['type_th'],
                'items' => implode(', ', array_map(fn($i) => $i['name'] . ' ×' . ($r['status'] === 'pending' ? $i['qty_requested'] : $i['qty_approved']), $r['items']))];
        }
    }
} catch (Throwable $e) {
    error_log('[get_home_dashboard] stock: ' . $e->getMessage());
}

// การจองห้องของฉัน (สูงสุด 3): ไม่อนุมัติ (3 วันล่าสุด) → รออนุมัติ → ประชุมวันนี้/พรุ่งนี้
$myRooms = [];
$roomTodo = null;
try {
    _initRoomTables($conn);
    $st = $conn->prepare("SELECT b.id, b.code, b.title, b.start_at, b.end_at, b.status, b.extras, b.prepared_at, b.cancel_reason, r.name AS room_name, r.needs_approval
        FROM rm_bookings b JOIN rm_rooms r ON r.id = b.room_id
        WHERE b.booker_id = ? AND (
            (b.status = 'pending' AND b.end_at > NOW())
            OR (b.status = 'booked' AND b.end_at > NOW() AND b.start_at < CURDATE() + INTERVAL 2 DAY)
            OR (b.status = 'rejected' AND b.start_at > NOW() AND b.cancelled_at >= NOW() - INTERVAL 3 DAY))
        ORDER BY FIELD(b.status, 'rejected', 'pending', 'booked'), b.start_at LIMIT 3");
    if ($st) {
        $st->bind_param('s', $userId);
        $st->execute();
        foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $r['id'] = (int)$r['id']; $r['needs_approval'] = (int)$r['needs_approval']; $r['extras'] = $r['extras'] !== null && $r['extras'] !== '';
            $myRooms[] = $r;
        }
    }
    // ผู้ดูแลห้อง: งานที่ต้องจัดการ (ตัวเลขเดียวกับเมนูห้องประชุม)
    if (roomCanManage($conn)) $roomTodo = roomKeeperTodo($conn);
} catch (Throwable $e) {
    error_log('[get_home_dashboard] rooms: ' . $e->getMessage());
}

// ใบจองรถของฉัน (สูงสุด 3): ไม่อนุมัติ (3 วันล่าสุด) → รอคืนกุญแจ → รออนุมัติ → อนุมัติแล้ว (ถึงพรุ่งนี้)
$myCars = [];
$carTodo = null;
try {
    _initCarTables($conn);
    $st = $conn->prepare("SELECT b.id, b.code, b.purpose, b.destination, b.start_at, b.end_at, b.status, b.need_driver, b.cancel_reason, c.plate, c.name AS car_name, d.name AS driver_name
        FROM cr_bookings b JOIN cr_cars c ON c.id = b.car_id LEFT JOIN cr_drivers d ON d.id = b.driver_id
        WHERE b.booker_id = ? AND (b.status IN ('pending','returning')
            OR (b.status = 'approved' AND b.start_at < CURDATE() + INTERVAL 2 DAY)
            OR (b.status = 'rejected' AND b.start_at > NOW() AND b.decided_at >= NOW() - INTERVAL 3 DAY))
        ORDER BY FIELD(b.status, 'rejected', 'returning', 'approved', 'pending'), b.start_at LIMIT 3");
    if ($st) {
        $st->bind_param('s', $userId);
        $st->execute();
        foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $r) { $r['id'] = (int)$r['id']; $r['need_driver'] = (int)$r['need_driver']; $myCars[] = $r; }
    }
    if (carCanManage($conn)) $carTodo = carKeeperTodo($conn);
} catch (Throwable $e) {
    error_log('[get_home_dashboard] cars: ' . $e->getMessage());
}

// Services menu (light columns)
$services = [];
try {
    ptc_ensure_services_is_active_column($conn);
    $res = $conn->query('SELECT id, title, description, icon, color, url_primary, url_fallback, sort_order, is_active FROM services WHERE COALESCE(is_active, 1) = 1 ORDER BY sort_order ASC, title ASC');
    if ($res) {
        $normalizeServiceUrl = static function ($value): string {
            $url = trim((string)$value);
            if ($url === '') return '';
            // Decode legacy escaped URL styles from historical admin entries.
            $url = str_replace(['\\/', '\\\\'], ['/', '/'], $url);
            return trim($url);
        };
        while ($row = $res->fetch_assoc()) {
            $services[] = [
                'id' => $row['id'] ?? '',
                'title' => $row['title'] ?? '',
                'sub' => $row['description'] ?? '',
                'icon' => $row['icon'] ?? 'fa-link',
                'color' => $row['color'] ?? '#2563eb',
                'url' => $normalizeServiceUrl($row['url_primary'] ?? ''),
                'backup' => $normalizeServiceUrl($row['url_fallback'] ?? ''),
                'sort_order' => (int)($row['sort_order'] ?? 999),
            ];
        }
    }
} catch (Throwable $e) {
    error_log('[get_home_dashboard] services: ' . $e->getMessage());
}

$queuePending = 0;
$queueActive = 0;
foreach ($myTickets as $t) {
    if (($t['status'] ?? '') === 'Pending') $queuePending++;
    if (in_array($t['status'] ?? '', $activeStatuses, true)) $queueActive++;
}

echo json_encode([
    'status' => 'success',
    'loggedin' => true,
    'session' => $sessionOut,
    'kpi' => $kpi,
    'my_tickets' => $myTickets,
    'my_loans' => $myLoans,
    'my_stock' => $myStock,
    'my_rooms' => $myRooms,
    'room_todo' => $roomTodo,
    'my_cars' => $myCars,
    'car_todo' => $carTodo,
    'services' => $services,
    'queue' => ['pending' => $queuePending, 'active' => $queueActive],
    'pending_total' => (int)$kpi['pending'] + (int)$kpi['loan_pending'],
], JSON_UNESCAPED_UNICODE);

function homeDashScalar(mysqli $conn, string $sql, string $types, array $params): int {
    if ($types !== '' && !empty($params)) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) return 0;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        return (int)($row['c'] ?? 0);
    }
    $res = $conn->query($sql);
    if (!$res) return 0;
    $row = $res->fetch_assoc();
    return (int)($row['c'] ?? 0);
}
