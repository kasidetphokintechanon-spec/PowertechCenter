<?php
header('Content-Type: text/html; charset=utf-8');

// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$host = 'localhost'; $user = 'root'; $pass = ''; $db_name = 'powertech_system';
$conn = new mysqli($host, $user, $pass, $db_name);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>กำลังเพิ่มข้อมูลคลังความรู้ (Knowledge Base)...</h2>";

// 1. เพิ่มหมวดหมู่ (Categories)
$categories = [
    ['name' => 'Hardware', 'icon' => 'fa-desktop', 'desc' => 'ปัญหาเกี่ยวกับอุปกรณ์คอมพิวเตอร์ ปริ้นเตอร์ เมาส์ คีย์บอร์ด'],
    ['name' => 'Software', 'icon' => 'fa-window-maximize', 'desc' => 'การใช้งานโปรแกรมต่างๆ Office, ERP, Windows'],
    ['name' => 'Network', 'icon' => 'fa-wifi', 'desc' => 'อินเทอร์เน็ต, WiFi, VPN, เข้า Drive กลางไม่ได้'],
    ['name' => 'Email', 'icon' => 'fa-envelope', 'desc' => 'การใช้งานอีเมล Outlook, การตั้งค่าลายเซ็น'],
    ['name' => 'Security', 'icon' => 'fa-shield-alt', 'desc' => 'รหัสผ่าน, ไวรัส, ความปลอดภัยข้อมูล']
];

foreach ($categories as $cat) {
    // ใช้ INSERT ... SELECT ... WHERE NOT EXISTS เพื่อป้องกันข้อมูลซ้ำ
    $stmt = $conn->prepare("INSERT INTO kb_categories (name, description, icon, sort_order) SELECT ?, ?, ?, 99 WHERE NOT EXISTS (SELECT 1 FROM kb_categories WHERE name = ?)");
    $stmt->bind_param("ssss", $cat['name'], $cat['desc'], $cat['icon'], $cat['name']);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo "<p style='color: green;'>✅ เพิ่มหมวดหมู่: {$cat['name']}</p>";
    }
}

// 2. เพิ่มบทความ (Articles)
$articles = [
    [
        'cat' => 'Hardware', 'title' => 'ปริ้นไม่ออกทำอย่างไร (Printer Troubleshooting)',
        'content' => "# ปริ้นไม่ออกทำอย่างไร\n\n1. **ตรวจสอบกระดาษ**: ดูว่ากระดาษหมดหรือกระดาษติดหรือไม่\n2. **ตรวจสอบไฟสถานะ**: ดูที่เครื่องปริ้นเตอร์ว่ามีไฟกระพริบแจ้งเตือนหรือไม่\n3. **ตรวจสอบการเชื่อมต่อ**: สาย USB หรือสาย LAN หลวมหรือไม่\n4. **Restart**: ลองปิด-เปิดเครื่องปริ้นเตอร์ใหม่\n\nหากยังไม่ได้ผล กรุณาแจ้ง IT พร้อมระบุชื่อเครื่องปริ้นเตอร์",
        'tags' => 'printer, paper jam, offline, ปริ้นไม่ออก'
    ],
    [
        'cat' => 'Network', 'title' => 'วิธีเชื่อมต่อ WiFi สำหรับพนักงาน',
        'content' => "# การเชื่อมต่อ WiFi\n\n1. เลือกชื่อ WiFi: **Powertech-Staff**\n2. รหัสผ่าน: `P@wertech2024` (ตัวอย่าง)\n3. หากเชื่อมต่อไม่ได้ ให้ลองกด **Forget Network** แล้วเชื่อมต่อใหม่\n\n*หมายเหตุ: WiFi Guest สำหรับบุคคลภายนอกเท่านั้น*",
        'tags' => 'wifi, internet, connect, ไวไฟ'
    ],
    [
        'cat' => 'Email', 'title' => 'การตั้งค่าลายเซ็น (Signature) ใน Outlook',
        'content' => "# วิธีตั้งค่า Signature\n\n1. เปิด Outlook ไปที่ **File > Options**\n2. เลือก **Mail** > **Signatures...**\n3. กด **New** เพื่อสร้างลายเซ็นใหม่\n4. ใส่รายละเอียด ชื่อ, ตำแหน่ง, เบอร์โทร และโลโก้บริษัท\n5. กด OK เพื่อบันทึก\n\n*ควรใช้รูปแบบมาตรฐานตามที่ HR กำหนด*",
        'tags' => 'outlook, email, signature, ลายเซ็น'
    ],
    [
        'cat' => 'Software', 'title' => 'คอมพิวเตอร์ช้า เบื้องต้นควรทำอย่างไร',
        'content' => "# คอมพิวเตอร์ช้า\n\n1. **Restart เครื่อง**: การรีสตาร์ทช่วยเคลียร์หน่วยความจำและปิดโปรแกรมที่ค้างอยู่ได้\n2. **ปิดโปรแกรมที่ไม่ใช้**: ตรวจสอบ Taskbar ว่ามีโปรแกรมเปิดค้างไว้เยอะหรือไม่\n3. **ลบไฟล์ขยะ**: พิมพ์ `Disk Cleanup` ในช่องค้นหา Windows แล้วกด OK\n4. **สแกนไวรัส**: หากเครื่องช้าผิดปกติ อาจเกิดจากไวรัส",
        'tags' => 'slow, performance, lag, คอมช้า'
    ],
    [
        'cat' => 'Security', 'title' => 'ลืมรหัสผ่านเข้าระบบ (Forgot Password)',
        'content' => "# ลืมรหัสผ่าน\n\nหากท่านลืมรหัสผ่านเข้า Windows หรือเข้าระบบ ERP\n\n1. ติดต่อแผนก IT โทรภายใน **1234**\n2. หรือแจ้งผ่านระบบ Ticket เลือกหัวข้อ 'Reset Password'\n3. เจ้าหน้าที่จะทำการรีเซ็ตและแจ้งรหัสชั่วคราวให้ทราบ\n\n**ข้อแนะนำ:** ควรเปลี่ยนรหัสผ่านทุกๆ 90 วัน",
        'tags' => 'password, reset, login, ลืมรหัส'
    ]
];

foreach ($articles as $art) {
    // หา ID ของหมวดหมู่
    $catRes = $conn->query("SELECT id FROM kb_categories WHERE name = '{$art['cat']}'");
    $catId = $catRes->fetch_assoc()['id'] ?? 0;
    
    if ($catId) {
        $stmt = $conn->prepare("INSERT INTO kb_articles (category_id, title, content, tags, is_published, view_count, updated_at) SELECT ?, ?, ?, ?, 1, 0, NOW() WHERE NOT EXISTS (SELECT 1 FROM kb_articles WHERE title = ?)");
        $stmt->bind_param("issss", $catId, $art['title'], $art['content'], $art['tags'], $art['title']);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo "<p style='color: blue;'>✅ เพิ่มบทความ: {$art['title']}</p>";
        }
    }
}

echo "<hr><h3>🎉 ดำเนินการเสร็จสิ้น</h3>";
echo "<a href='kb.html' style='font-size: 18px; font-weight: bold;'>ไปที่หน้า Knowledge Base</a>";
?>
