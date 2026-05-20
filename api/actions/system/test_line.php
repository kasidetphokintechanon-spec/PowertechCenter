<?php
// api/actions/system/test_line.php

// ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

$channel_token = $_POST['channel_token'] ?? '';
$dest_id = $_POST['dest_id'] ?? '';

if (empty($channel_token) || empty($dest_id)) {
    throw new Exception("กรุณาระบุ Channel Token และ Destination ID");
}

// URL สำหรับ Push Message
$url = "https://api.line.me/v2/bot/message/push";

// ข้อความทดสอบ (Flex Message)
$data = [
    'to' => $dest_id,
    'messages' => [
        [
            'type' => 'flex',
            'altText' => 'Test Message from Powertech',
            'contents' => [
                'type' => 'bubble',
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => 'Connection Successful! ✅',
                            'weight' => 'bold',
                            'size' => 'lg',
                            'color' => '#1DB446'
                        ],
                        [
                            'type' => 'text',
                            'text' => 'การตั้งค่า LINE Messaging API ของคุณถูกต้อง',
                            'margin' => 'md',
                            'size' => 'sm',
                            'color' => '#666666',
                            'wrap' => true
                        ],
                        [
                            'type' => 'separator',
                            'margin' => 'lg'
                        ],
                        [
                            'type' => 'text',
                            'text' => date('d/m/Y H:i:s'),
                            'size' => 'xs',
                            'color' => '#aaaaaa',
                            'margin' => 'md',
                            'align' => 'end'
                        ]
                    ]
                ]
            ]
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $channel_token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200) {
    $resJson = json_decode($result, true);
    $msg = $resJson['message'] ?? $error ?? "HTTP Status $httpCode";
    
    if ($httpCode == 401) {
        $msg = "Channel Access Token ไม่ถูกต้อง (Unauthorized)";
    } elseif ($httpCode == 400) {
        $msg = "User ID / Group ID ไม่ถูกต้อง หรือรูปแบบข้อความผิดพลาด";
    }
    
    throw new Exception("LINE API Error: " . $msg);
}

echo json_encode(['status' => 'success', 'message' => 'ส่งข้อความทดสอบสำเร็จ']);
?>
