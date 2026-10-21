<?php
// ปิดการแสดง Error ผ่าน HTML เพื่อป้องกัน JSON Error
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1); // แนะนำให้เปิด log เพื่อดู error จริงในไฟล์ php_error.log

// เริ่ม Output Buffering เพื่อป้องกัน Error/Warning แทรกใน JSON Response
ob_start();

ini_set('default_socket_timeout', '3');

// ดักจับ Fatal Error ที่ try-catch จับไม่ได้ (เช่น Memory Exhausted, Time Limit)
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_length()) ob_clean(); // ล้าง Buffer เดิม
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        echo json_encode(['status' => 'error', 'message' => 'Fatal Error: ' . $error['message']]);
    }
});

// CORS Headers (ถ้าจำเป็นต้องเปิดให้โดเมนอื่นเรียกใช้)
// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type, Authorization");

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Bangkok');

// Helper Function: บันทึกประวัติ (Asset History)
function logAssetHistory($conn, $assetDbId, $eventType, $details, $userName = 'System') {
        if (isset($_SESSION['display_name'])) $userName = $_SESSION['display_name'];
        elseif (isset($_SESSION['username'])) $userName = $_SESSION['username'];
        
        $stmt = $conn->prepare("INSERT INTO asset_history (asset_db_id, event_type, details, user_name) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isss", $assetDbId, $eventType, $details, $userName);
            if (!$stmt->execute()) {
                error_log("logAssetHistory Execute Error: " . $stmt->error);
            }
        } else {
            error_log("logAssetHistory Prepare Error: " . $conn->error);
        }
    }

// Helper Function: ส่งแจ้งเตือน Telegram
function sendTelegramNotification($conn, $message, $type = 'general') {
        $settings = [];
        $res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('telegram_active', 'telegram_token', 'telegram_chat_id', 'notify_repair', 'notify_booking', 'notify_loan', 'line_active', 'line_channel_token', 'line_dest_id')");
        while ($row = $res->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        // Check specific notification type settings (Default to 1/On if not set)
        // Note: These settings apply to both Telegram and LINE
        if ($type === 'repair' && (isset($settings['notify_repair']) && $settings['notify_repair'] === '0')) return;
        if ($type === 'loan' && (isset($settings['notify_loan']) && $settings['notify_loan'] === '0')) return;

        // 1. Send Telegram
        if (!empty($settings['telegram_active']) && $settings['telegram_active'] === '1' && !empty($settings['telegram_token']) && !empty($settings['telegram_chat_id'])) {
            $url = "https://api.telegram.org/bot" . $settings['telegram_token'] . "/sendMessage";
            $data = ['chat_id' => $settings['telegram_chat_id'], 'text' => $message, 'parse_mode' => 'HTML'];

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
            if ($result === false) {
                error_log("Telegram Curl Error: " . curl_error($ch));
            }
            curl_close($ch);
        }

        // 2. Send LINE Messaging API (Replacement for LINE Notify)
        if (!empty($settings['line_active']) && $settings['line_active'] === '1' && !empty($settings['line_channel_token']) && !empty($settings['line_dest_id'])) {
            $lineUrl = "https://api.line.me/v2/bot/message/push";
            
            // Parse message to extract data for Flex Message
            // Assuming message format is consistent with what we generate in other files
            // Example: "<b>Header</b>\n...\n<b>Key:</b> Value..."
            
            // Default fallback to text if parsing fails or type is unknown
            $flexMessage = null;
            
            // Extract Title/Header
            preg_match('/<b>(.*?)<\/b>/', $message, $titleMatch);
            $title = $titleMatch[1] ?? 'Notification';
            
            // Extract Key-Value pairs
            preg_match_all('/<b>(.*?):<\/b>\s*(.*?)(?=\n|$)/u', $message, $matches, PREG_SET_ORDER);
            $details = [];
            foreach ($matches as $m) {
                $details[] = ['key' => trim($m[1]), 'value' => trim($m[2])];
            }

            // Determine Color based on Type
            $headerColor = '#1DB446'; // Default LINE Green
            if ($type === 'repair') $headerColor = '#EF454D'; // Red
            elseif ($type === 'loan') $headerColor = '#FF9500'; // Orange

            // Construct Flex Bubble
            $contents = [];
            
            // Header
            $contents[] = [
                'type' => 'text',
                'text' => strip_tags($title),
                'weight' => 'bold',
                'size' => 'xl',
                'color' => $headerColor
            ];
            
            // Separator
            $contents[] = ['type' => 'separator', 'margin' => 'md'];

            // Details Body
            $bodyContents = [];
            foreach ($details as $detail) {
                $bodyContents[] = [
                    'type' => 'box',
                    'layout' => 'baseline',
                    'spacing' => 'sm',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => $detail['key'],
                            'color' => '#aaaaaa',
                            'size' => 'sm',
                            'flex' => 2
                        ],
                        [
                            'type' => 'text',
                            'text' => strip_tags($detail['value']), // Remove HTML tags from value
                            'wrap' => true,
                            'color' => '#666666',
                            'size' => 'sm',
                            'flex' => 4
                        ]
                    ]
                ];
            }
            
            if (!empty($bodyContents)) {
                $contents[] = [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'margin' => 'lg',
                    'spacing' => 'sm',
                    'contents' => $bodyContents
                ];
            }

            // Footer (Timestamp)
            $contents[] = ['type' => 'separator', 'margin' => 'xl'];
            $contents[] = [
                'type' => 'text',
                'text' => date('d/m/Y H:i'),
                'size' => 'xs',
                'color' => '#aaaaaa',
                'align' => 'end',
                'margin' => 'md'
            ];
            
            // Action Buttons
            $actionUrl = '';
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $baseUrl = "$protocol://$host" . dirname($_SERVER['PHP_SELF']);
            
            if ($type === 'repair') $actionUrl = $baseUrl . '/log.html';
            elseif ($type === 'loan') $actionUrl = $baseUrl . '/log.html';
            
            if ($actionUrl) {
                $contents[] = [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'margin' => 'md',
                    'contents' => [
                        [
                            'type' => 'button',
                            'action' => [
                                'type' => 'uri',
                                'label' => 'View Details',
                                'uri' => $actionUrl
                            ],
                            'style' => 'secondary',
                            'height' => 'sm'
                        ]
                    ]
                ];
            }
            
            $flexContainer = [
                'type' => 'bubble',
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => $contents
                ]
            ];

            $lineData = [
                'to' => $settings['line_dest_id'],
                'messages' => [
                    [
                        'type' => 'flex',
                        'altText' => strip_tags($title), // Text shown in chat list
                        'contents' => $flexContainer
                    ]
                ]
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $lineUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($lineData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $settings['line_channel_token']
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            $result = curl_exec($ch);
            if ($result === false) {
                error_log("LINE Curl Error: " . curl_error($ch));
            }
            
            // Log error if LINE API fails (ช่วยดูว่าเน็ตออกได้ไหม)
            if (curl_errno($ch)) {
                error_log("LINE API Error: " . curl_error($ch));
            }
            
            curl_close($ch);
        }
    }

require_once __DIR__ . '/includes/permissions_registry.php';
require_once __DIR__ . '/includes/notify_helper.php';

function getBasePermissionsForRole($role) {
    $role = $role ?: 'guest';
    if ($role === 'admin') {
        return ['*'];
    }
    // staff / user — สิทธิ์จากตาราง user_permissions เท่านั้น (ไม่ให้เข้า admin โดยปริยาย)
    return [];
}

function permissionTablesExist($conn) {
    static $cache = null;
    if ($cache !== null) return $cache;
    $res = $conn->query("SHOW TABLES LIKE 'user_permissions'");
    $cache = ($res && $res->num_rows > 0);
    return $cache;
}

function getEffectivePermissionsForUserId($conn, $userId, $role) {
    $perms = getBasePermissionsForRole($role);
    if (!$userId) return array_values(array_unique($perms));
    if (!permissionTablesExist($conn)) return array_values(array_unique($perms));
    $stmtRoles = $conn->prepare("SELECT pr.permissions_json FROM permission_roles pr JOIN user_permission_roles upr ON upr.role_id = pr.id WHERE upr.user_id = ?");
    if ($stmtRoles) {
        $stmtRoles->bind_param("s", $userId);
        $stmtRoles->execute();
        $resRoles = $stmtRoles->get_result();
        while ($row = $resRoles->fetch_assoc()) {
            $json = $row['permissions_json'] ?? '[]';
            $list = json_decode($json, true);
            if (is_array($list)) {
                foreach ($list as $p) {
                    if (is_string($p) && $p !== '') $perms[] = $p;
                }
            }
        }
    }
    $stmtUser = $conn->prepare("SELECT permission_key, is_granted FROM user_permissions WHERE user_id = ?");
    if ($stmtUser) {
        $stmtUser->bind_param("s", $userId);
        $stmtUser->execute();
        $resUser = $stmtUser->get_result();
        while ($row = $resUser->fetch_assoc()) {
            $key = $row['permission_key'] ?? '';
            if ($key === '') continue;
            $isGranted = (int)($row['is_granted'] ?? 0) === 1;
            if ($isGranted) {
                $perms[] = $key;
            } else {
                $perms = array_values(array_filter($perms, function($v) use ($key) { return $v !== $key; }));
            }
        }
    }
    return array_values(array_unique($perms));
}

function hasPermission($conn, $permissionKey) {
    $userId = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['role'] ?? 'guest';
    static $cache = null;
    if ($cache === null) {
        $cache = getEffectivePermissionsForUserId($conn, $userId, $role);
    }
    if (in_array('*', $cache, true)) {
        return true;
    }
    return in_array($permissionKey, $cache, true);
}

function refreshSessionPermissions($conn): array {
    $userId = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['role'] ?? 'guest';
    $effective = getEffectivePermissionsForUserId($conn, $userId, $role);
    $_SESSION['effective_permissions'] = $effective;
    $_SESSION['can_access_admin'] = ($role === 'admin') || in_array('admin.access_admin', $effective, true);
    $_SESSION['can_manage_stock'] = (in_array('stock.manage', $effective, true) || in_array('*', $effective, true)) ? 1 : 0;
    return $effective;
}

function canAccessAdminPanel($conn): bool {
    return !empty($_SESSION['can_access_admin']) || hasPermission($conn, 'admin.access_admin');
}

function requirePermission($conn, string $permissionKey): void {
    if (!hasPermission($conn, $permissionKey)) {
        if (ob_get_length()) {
            ob_clean();
        }
        echo json_encode(['status' => 'error', 'message' => 'Insufficient permission']);
        exit;
    }
}

/** Ensure services.is_active exists (1 = show on index, 0 = hold/hidden). */
function ptc_ensure_services_is_active_column(mysqli $conn): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $res = @$conn->query("SHOW COLUMNS FROM `services` LIKE 'is_active'");
    if ($res && $res->num_rows === 0) {
        @$conn->query(
            "ALTER TABLE `services` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=visible on index' AFTER `sort_order`"
        );
    }
}

function syncStockPermissionGrant($conn, string $userId, int $canManage): void {
    if (!permissionTablesExist($conn)) {
        return;
    }
    if ($canManage === 1) {
        $del = $conn->prepare('DELETE FROM user_permissions WHERE user_id = ? AND permission_key = ?');
        $key = 'stock.manage';
        if ($del) {
            $del->bind_param('ss', $userId, $key);
            $del->execute();
        }
        $ins = $conn->prepare('INSERT INTO user_permissions (user_id, permission_key, is_granted) VALUES (?, ?, 1)');
        if ($ins) {
            $ins->bind_param('ss', $userId, $key);
            $ins->execute();
        }
    } else {
        $del = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ? AND permission_key = 'stock.manage'");
        if ($del) {
            $del->bind_param('s', $userId);
            $del->execute();
        }
    }
}

function syncEmployeeStockFlagFromPermissions($conn, string $userId): void {
    $stmt = $conn->prepare('SELECT role FROM employees WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if (!$row) {
        return;
    }
    $effective = getEffectivePermissionsForUserId($conn, $userId, $row['role'] ?? 'user');
    $flag = (in_array('stock.manage', $effective, true) || in_array('*', $effective, true)) ? 1 : 0;
    $upd = $conn->prepare('UPDATE employees SET can_manage_stock = ? WHERE id = ?');
    if ($upd) {
        $upd->bind_param('is', $flag, $userId);
        $upd->execute();
    }
}

// ── Rate limiting (session-based, no DB required) ────────────────────────
// Applies only to write/sensitive actions to avoid slowing normal reads.
function applyRateLimit(string $action, int $maxPerMinute = 30): void {
    $sensitiveActions = ['add_item','update_item','delete_item','cancel_ticket','upload_attachment',
                         'login','request_password_reset','reset_password','rate_ticket'];
    if (!in_array($action, $sensitiveActions, true)) return;

    if (session_status() !== PHP_SESSION_ACTIVE) return; // session not started yet
    $key  = 'rl_' . $action;
    $now  = time();
    $data = $_SESSION[$key] ?? ['count' => 0, 'window_start' => $now];

    if ($now - $data['window_start'] >= 60) {
        $data = ['count' => 0, 'window_start' => $now]; // reset window
    }

    $data['count']++;
    $_SESSION[$key] = $data;

    if ($data['count'] > $maxPerMinute) {
        if (ob_get_length()) ob_clean();
        http_response_code(429);
        echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please wait a moment.']);
        exit;
    }
}

try {
    // นำเข้าไฟล์ Config (ย้ายมาใน try เพื่อดักจับ Error จาก config.php ได้)
    require_once 'config.php';

    $hosts = array_values(array_unique(array_filter([DB_HOST, 'localhost', '127.0.0.1', '::1'])));
    $conn = null;
    $lastError = null;
    foreach ($hosts as $host) {
        $candidate = mysqli_init();
        if (!$candidate) {
            $lastError = 'mysqli_init() failed';
            continue;
        }
        $candidate->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
        if (defined('MYSQLI_OPT_READ_TIMEOUT')) $candidate->options(MYSQLI_OPT_READ_TIMEOUT, 3);
        if (defined('MYSQLI_OPT_WRITE_TIMEOUT')) $candidate->options(MYSQLI_OPT_WRITE_TIMEOUT, 3);
        @$candidate->real_connect($host, DB_USER, DB_PASS, DB_NAME);
        if (!$candidate->connect_error) {
            $conn = $candidate;
            $lastError = null;
            break;
        }
        $lastError = $candidate->connect_error;
    }
    if (!$conn) {
        throw new Exception('Database connection failed: ' . ($lastError ?: 'unknown error'));
    }
    // ตรวจสอบว่าตั้งค่า Charset สำเร็จหรือไม่
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("Error loading character set utf8mb4: " . $conn->error);
    }

    // Lazy cron: ส่ง Stock Summary อัตโนมัติถ้าถึงเวลาที่ตั้งไว้
    _checkStockSummaryCron($conn);

    $action = $_REQUEST['action'] ?? '';

    // Apply rate limit for write/sensitive actions
    applyRateLimit($action);

    // --- Modular Action Handler ---
    // ตรวจสอบว่ามีไฟล์ action แยกในโฟลเดอร์ api/actions/ หรือไม่
    // ป้องกัน Directory Traversal โดยอนุญาตเฉพาะตัวอักษร a-z, 0-9 และ _
    $safeAction = preg_replace('/[^a-zA-Z0-9_]/', '', $action);

    // TISAX: ด่านกลาง — เดิมหลาย action ไม่เช็กล็อกอินเอง ทำให้คนนอกดึง token LINE/Telegram,
    // รายชื่อพนักงาน และแก้ทะเบียนพัสดุได้ → ปิดทุกอย่างไว้ก่อน เปิดเฉพาะที่ต้องใช้ก่อนล็อกอินจริงๆ
    $PUBLIC_ACTIONS = [
        'login', 'check_session', 'logout', 'request_password_reset', 'reset_password',
        'get_it_support_settings', 'get_scan_settings', 'get_company_info',      // หน้า login / โลโก้
        'get_public_directory', 'get_company_order', 'get_directory_categories', 'get_job_positions', // Contact List (ดูได้โดยไม่ล็อกอิน)
        'get_kb_data', 'get_kb_article_by_id', 'suggest_kb_articles',             // KB อ่านอย่างเดียว
        'get_audit_confirm', 'submit_audit_confirm',                               // ลิงก์ตรวจนับ (ใช้ token แทนล็อกอิน)
        'get_kiosk_data', 'create_walkin_booking', 'get_monthly_schedule', 'get_job_plans',
    ];
    // action 'get' เป็นตัวดึงข้อมูลรวมหลายตาราง — เปิดให้คนไม่ล็อกอินเฉพาะรายชื่อแผนก (ตัวกรองใน Contact List)
    $isPublicGet = $safeAction === 'get' && in_array($_GET['file'] ?? '', ['departments'], true);
    if ($safeAction !== '' && !$isPublicGet && !in_array($safeAction, $PUBLIC_ACTIONS, true) && empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ', 'loggedin' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // action ที่ไฟล์ตัวเองไม่เช็กสิทธิ์ — ล็อกอินอย่างเดียวไม่พอ (มีสิทธิ์ข้อใดข้อหนึ่งก็ผ่าน)
    $ACTION_PERMS = [
        'save_equipment' => ['asset.manage'], 'transfer_asset' => ['asset.manage'],
        'export_assets' => ['asset.manage'], 'generate_next_asset_id' => ['asset.manage'],
        'check_duplicate_asset' => ['asset.manage'], 'asset_lifecycle' => ['asset.manage'],
        'create_repair' => ['asset.manage', 'it_log.access'],
        'export_employees' => ['employee.manage'], 'update_department_phone' => ['employee.manage'],
        'get_audit_logs' => ['audit.view'], 'export_audit_logs' => ['audit.view'],
        'get_telegram_settings' => ['settings.notifications'], 'save_telegram_settings' => ['settings.notifications'],
        'get_line_settings' => ['settings.notifications'], 'save_line_settings' => ['settings.notifications'],
        'test_telegram' => ['settings.notifications'], 'test_line' => ['settings.notifications'],
        'save_notify_channel' => ['settings.notifications'], 'delete_notify_channel' => ['settings.notifications'],
        'test_notify_channel' => ['settings.notifications'], 'test_notify_routing' => ['settings.notifications'],
        'get_notify_log' => ['settings.notifications'],
        'save_company_info' => ['admin.access_admin'], 'upload_company_logo' => ['admin.access_admin'],
        'save_it_support_settings' => ['admin.access_admin'], 'save_scan_settings' => ['admin.access_admin'],
        'reorder_services' => ['admin.access_admin'], 'probe_host' => ['admin.access_admin', 'it_log.access'],
        'save_stock_summary_schedule' => ['stock.manage'], 'saveStockSummarySchedule' => ['stock.manage'],
        'deleteStockSummarySchedule' => ['stock.manage'], 'initStockTables' => ['stock.manage'],
        // ใบติดตั้งมี IP / ชื่อผู้ใช้ VPN / สิทธิ์ระบบ — เฉพาะ IT (เดิมพนักงานทั่วไปเปิดดู/ลบได้)
        'getSetupRecords' => ['it_log.access', 'asset.manage'], 'getSetupRecord' => ['it_log.access', 'asset.manage'],
        'saveSetupRecord' => ['it_log.access', 'asset.manage'], 'deleteSetupRecord' => ['it_log.access', 'asset.manage'],
        'findSetupRecordByAsset' => ['it_log.access', 'asset.manage'], 'getSetupAssetCodes' => ['it_log.access', 'asset.manage'],
        'clear_loan_history' => ['loan.manage'], 'denyRequest' => ['loan.manage'], 'update_loan_status' => ['loan.manage'],
    ];
    if (isset($ACTION_PERMS[$safeAction])) {
        $allowed = false;
        foreach ($ACTION_PERMS[$safeAction] as $perm) {
            if (hasPermission($conn, $perm)) { $allowed = true; break; }
        }
        if (!$allowed) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ใช้งานส่วนนี้'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // กำหนดรายชื่อโฟลเดอร์ย่อยที่จะให้ระบบค้นหาไฟล์
    $subfolders = [
        'auth',
        'employee',
        'asset',
        'loan',
        'ticket',
        'chat',
        'dashboard',
        'kb',
        'system',
        'checklist',
        'stock',
        'setup',
        'server_check'
    ];

    if (!empty($safeAction)) {
        foreach ($subfolders as $folder) {
            $folderPath = $folder ? "$folder/" : "";
            $path = __DIR__ . "/api/actions/{$folderPath}{$safeAction}.php";
            
            if (file_exists($path)) {
                require $path;
                exit; // จบการทำงานเมื่อเจอและรันไฟล์แล้ว
            }
        }
    }
    // ------------------------------

    // All actions are now handled by the Modular Action Handler above.
    
    // If execution reaches here, it means no action matched
    echo json_encode(['status' => 'error', 'message' => 'Invalid action or endpoint not found']);

} catch (Throwable $e) {
    // ล้าง Buffer เดิมทิ้ง (ถ้ามี) เพื่อให้ส่งกลับเฉพาะ JSON Error
    if (ob_get_length()) ob_clean();

    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
