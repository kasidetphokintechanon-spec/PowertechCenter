<?php
// webhook.php
// รับ Webhook จาก LINE: รองรับ !id และปุ่ม Approve/Reject

require_once 'config.php'; // เพื่อดึงค่า DB หรือ Config ถ้าจำเป็น (ในที่นี้อาจไม่ต้องใช้ DB แต่เผื่อไว้)

// 1. ตอบกลับ LINE ทันทีเพื่อให้ Verify ผ่าน (สำคัญมาก)
http_response_code(200);
echo "OK";
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// ปิดการแสดง Error หน้าเว็บเพื่อไม่ให้กวน Response
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 2. เชื่อมต่อฐานข้อมูล
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    exit;
}
$conn->set_charset("utf8mb4");

// 3. ดึง Channel Access Token และ Channel Secret จาก DB
$channelToken = '';
$channelSecret = '';
$res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('line_channel_token','line_channel_secret')");
while ($row = $res->fetch_assoc()) {
    if ($row['setting_key'] === 'line_channel_token') $channelToken = $row['setting_value'];
    if ($row['setting_key'] === 'line_channel_secret') $channelSecret = $row['setting_value'];
}

// 4. รับข้อมูลและตรวจสอบ LINE Signature
$content = file_get_contents('php://input');
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

// ตรวจสอบ X-Line-Signature (ป้องกัน request ปลอม)
if (!empty($channelSecret)) {
    $lineSignature = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';
    $expectedSig = base64_encode(hash_hmac('sha256', $content, $channelSecret, true));
    if (!hash_equals($expectedSig, $lineSignature)) {
        error_log("webhook.php: Invalid LINE signature from IP $clientIP");
        exit;
    }
}

$events = json_decode($content, true);
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

// บันทึก Log ลงไฟล์ webhook_log.txt (เปิดดูไฟล์นี้เพื่อ Debug)
file_put_contents('webhook_log.txt', date("Y-m-d H:i:s") . " [IP: $clientIP] Data: " . $content . "\n", FILE_APPEND);

if (!empty($events['events'])) {
    foreach ($events['events'] as $event) {
        $replyToken = $event['replyToken'];
        
        // --- กรณีเพิ่มเพื่อน (Follow) ---
        if ($event['type'] === 'follow') {
            $welcomeMsg = "ยินดีต้อนรับสู่ Powertech System Center!\n\nพิมพ์ '!id' เพื่อดู User ID ของคุณ\nสำหรับนำไปตั้งค่าในระบบแจ้งเตือน";
            replyToLine($replyToken, $welcomeMsg, $channelToken);
        }

        // --- กรณีพิมพ์ข้อความ (!id) ---
        if ($event['type'] === 'message' && $event['message']['type'] === 'text') {
            $text = trim($event['message']['text']);
            if ($text === '!id') {
                $sourceId = $event['source']['userId'];
                $type = 'User';
                if (isset($event['source']['groupId'])) {
                    $sourceId = $event['source']['groupId'];
                    $type = 'Group';
                }
                replyToLine($replyToken, "ID ของคุณคือ ($type): " . $sourceId, $channelToken);
            }
            elseif (strtolower($text) === 'test') {
                replyToLine($replyToken, "✅ Webhook เชื่อมต่อสำเร็จ!\nSystem Ready.", $channelToken);
            }
        }
        
        // --- กรณีกดปุ่ม (Postback) ---
        elseif ($event['type'] === 'postback') {
            $postbackData = $event['postback']['data']; // ex: action=approve_booking&ref=BK...
            $data = [];
            parse_str($postbackData, $data);
            
            if (isset($data['action']) && isset($data['ref'])) {
                $action = $data['action'];
                $replyMsg = "";

                // ตัวอย่าง Logic (ปรับชื่อตารางตามจริง เช่น bookings หรือ job_tickets)
                $ref = $data['ref'];
                if ($action === 'approve_booking') {
                    $stmt = $conn->prepare("UPDATE bookings SET status = 'Confirmed' WHERE booking_ref = ? AND status = 'Pending'");
                    $stmt->bind_param("s", $ref);
                    $stmt->execute();
                    if ($stmt->affected_rows > 0) $replyMsg = "✅ อนุมัติการจอง $ref แล้ว";
                    else $replyMsg = "⚠️ ไม่สามารถอนุมัติได้ (สถานะอาจเปลี่ยนไปแล้ว)";
                }
                elseif ($action === 'reject_booking') {
                    $stmt = $conn->prepare("UPDATE bookings SET status = 'Cancelled' WHERE booking_ref = ? AND status = 'Pending'");
                    $stmt->bind_param("s", $ref);
                    $stmt->execute();
                    if ($stmt->affected_rows > 0) $replyMsg = "🚫 ปฏิเสธการจอง $ref แล้ว";
                    else $replyMsg = "⚠️ ไม่สามารถปฏิเสธได้";
                }

                if ($replyMsg) replyToLine($replyToken, $replyMsg, $channelToken);
            }
        }
    }
}

function replyToLine($replyToken, $text, $channelToken) {
    $url = "https://api.line.me/v2/bot/message/reply";
    $payload = [
        'replyToken' => $replyToken,
        'messages' => [['type' => 'text', 'text' => $text]]
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $channelToken]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
