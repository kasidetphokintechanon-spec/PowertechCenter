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

function getBasePermissionsForRole($role) {
    $role = $role ?: 'guest';
    if ($role === 'admin') return ['*'];
    if ($role === 'staff') return ['admin.access_admin'];
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
    if (in_array('*', $cache, true)) return true;
    return in_array($permissionKey, $cache, true);
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

    $action = $_REQUEST['action'] ?? '';
    
    // --- Modular Action Handler ---
    // ตรวจสอบว่ามีไฟล์ action แยกในโฟลเดอร์ api/actions/ หรือไม่
    // ป้องกัน Directory Traversal โดยอนุญาตเฉพาะตัวอักษร a-z, 0-9 และ _
    $safeAction = preg_replace('/[^a-zA-Z0-9_]/', '', $action);
    
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
        'checklist'
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
