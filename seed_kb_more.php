<?php
header('Content-Type: text/html; charset=utf-8');
$conn = new mysqli('localhost','root','','powertech_system');
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("Connection failed: ".$conn->connect_error);

echo "<h2>เพิ่มบทความคลังความรู้ (Knowledge Base) เพิ่มเติม...</h2>";

// --- เพิ่มหมวดหมู่ใหม่ ---
$newCats = [
    ['name' => 'General IT',     'icon' => 'fa-laptop',        'desc' => 'ปัญหาการใช้งานทั่วไป IT'],
    ['name' => 'Hardware',       'icon' => 'fa-desktop',       'desc' => 'อุปกรณ์คอมพิวเตอร์ ปริ้นเตอร์ เมาส์ คีย์บอร์ด'],
    ['name' => 'Software',       'icon' => 'fa-window-maximize','desc' => 'โปรแกรม Office, ERP, Windows'],
    ['name' => 'Network',        'icon' => 'fa-wifi',           'desc' => 'อินเทอร์เน็ต WiFi VPN Drive'],
    ['name' => 'Email',          'icon' => 'fa-envelope',       'desc' => 'Outlook, อีเมล, ลายเซ็น'],
    ['name' => 'Security',       'icon' => 'fa-shield-alt',     'desc' => 'รหัสผ่าน ไวรัส ความปลอดภัย'],
    ['name' => 'Meeting & AV',   'icon' => 'fa-video',          'desc' => 'ห้องประชุม โปรเจคเตอร์ Teams/Zoom'],
    ['name' => 'Phone & Mobile', 'icon' => 'fa-mobile-alt',     'desc' => 'โทรศัพท์ภายใน สมาร์ทโฟน'],
];

foreach ($newCats as $i => $cat) {
    $stmt = $conn->prepare("INSERT INTO kb_categories (name, description, icon, sort_order) SELECT ?,?,?,? WHERE NOT EXISTS (SELECT 1 FROM kb_categories WHERE name=?)");
    $sort = $i + 1;
    $stmt->bind_param("sssis", $cat['name'], $cat['desc'], $cat['icon'], $sort, $cat['name']);
    $stmt->execute();
    if ($stmt->affected_rows > 0) echo "<p style='color:green'>✅ หมวดใหม่: {$cat['name']}</p>";
}

// map category name -> id
$catMap = [];
$res = $conn->query("SELECT id, name FROM kb_categories");
while ($row = $res->fetch_assoc()) $catMap[$row['name']] = (int)$row['id'];

// --- บทความทั้งหมด ---
$articles = [

    // ===== General IT =====
    ['cat'=>'General IT','title'=>'วิธีเปลี่ยนรหัสผ่าน Windows','tags'=>'password,windows,เปลี่ยนรหัส','content'=>
"# วิธีเปลี่ยนรหัสผ่าน Windows

## ขั้นตอน
1. กดปุ่ม **Ctrl + Alt + Del** พร้อมกัน
2. เลือก **Change a password**
3. กรอกรหัสผ่านเดิมในช่องแรก
4. กรอกรหัสผ่านใหม่ในช่องที่ 2 และ 3 (ต้องตรงกัน)
5. กดปุ่ม Enter หรือ ลูกศร → เพื่อยืนยัน

## เงื่อนไขรหัสผ่านที่ดี
- ความยาวอย่างน้อย **8 ตัวอักษร**
- มีตัวอักษรพิมพ์ใหญ่ พิมพ์เล็ก ตัวเลข และอักขระพิเศษ
- ไม่ใช้รหัสที่เคยใช้ในช่วง 3 ครั้งที่ผ่านมา

> **คำแนะนำ:** ควรเปลี่ยนรหัสผ่านทุก 90 วัน เพื่อความปลอดภัย"],

    ['cat'=>'General IT','title'=>'คอมพิวเตอร์ไม่ยอมเปิด ทำอย่างไร','tags'=>'computer,boot,เปิดไม่ติด','content'=>
"# คอมพิวเตอร์ไม่ยอมเปิด

## ตรวจสอบเบื้องต้น
1. **ตรวจสอบสายไฟ** — ดูว่าสายไฟเสียบครบหรือไม่ ทั้งที่เครื่องและที่ปลั๊ก
2. **กดปุ่มไฟ UPS** — บางครั้ง UPS ปิดอยู่หรือแบตฯ หมด
3. **รอ 30 วินาที** แล้วลองกดปุ่มเปิดอีกครั้ง (อาจค้างจากการปิดก่อนหน้า)
4. **ตรวจจอภาพ** — แยกว่าเครื่องเปิดแล้วแต่จอไม่ติด หรือเครื่องไม่ได้เปิดเลย

## กรณีไฟไม่ขึ้นที่เครื่องเลย
- ลองเปลี่ยนปลั๊กไฟช่องอื่น
- ตรวจว่า Power Strip / ปลั๊กพ่วงเปิดสวิตช์อยู่

## ติดต่อ IT
หากดำเนินการข้างต้นแล้วยังไม่ได้ผล **อย่าพยายามเปิดเครื่องซ้ำหลายครั้ง** ให้แจ้ง IT ทันที"],

    ['cat'=>'General IT','title'=>'วิธี Lock หน้าจอก่อนออกจากโต๊ะ','tags'=>'lock,screen,security,หน้าจอ','content'=>
"# วิธี Lock หน้าจอ

## วิธีที่เร็วที่สุด
กด **Windows + L** — หน้าจอล็อกทันที

## วิธีอื่น
- คลิกขวาที่ Taskbar > Sleep
- กด **Ctrl + Alt + Del** > เลือก Lock

## ทำไมต้อง Lock
- ป้องกันคนอื่นเข้าถึงข้อมูลในเครื่อง
- ป้องกันการส่งอีเมลหรือแก้ไขเอกสารโดยไม่ได้รับอนุญาต
- เป็นนโยบายความปลอดภัยของบริษัท

> **ข้อกำหนด:** ทุกครั้งที่ออกจากโต๊ะทำงาน ต้อง Lock หน้าจอเสมอ"],

    ['cat'=>'General IT','title'=>'วิธี Screenshot (จับภาพหน้าจอ)','tags'=>'screenshot,print screen,จับภาพ','content'=>
"# วิธีจับภาพหน้าจอ

## วิธีที่ 1: Snipping Tool (แนะนำ)
1. กด **Windows + Shift + S**
2. ลากเลือกบริเวณที่ต้องการ
3. ภาพถูกคัดลอกอัตโนมัติ — วางใน Word หรือ Chat ได้เลย (Ctrl+V)

## วิธีที่ 2: Print Screen
- กดปุ่ม **PrtSc** — คัดลอกทั้งหน้าจอ
- กด **Alt + PrtSc** — คัดลอกเฉพาะหน้าต่างที่เลือก
- วางผลลัพธ์ด้วย **Ctrl + V** ใน Paint หรือโปรแกรมอื่น

## วิธีที่ 3: บันทึกเป็นไฟล์
- กด **Windows + PrtSc** — บันทึกไฟล์ PNG อัตโนมัติที่โฟลเดอร์ Pictures > Screenshots"],

    // ===== Hardware =====
    ['cat'=>'Hardware','title'=>'ปริ้นไม่ออก — วิธีแก้ไขเบื้องต้น','tags'=>'printer,print,ปริ้นไม่ออก,offline','content'=>
"# ปริ้นไม่ออกทำอย่างไร

## ตรวจสอบตามลำดับ

### 1. ตรวจสอบเครื่องปริ้นเตอร์
- ไฟเขียวติดปกติหรือไม่? ถ้าไฟกระพริบสีแดง/ส้ม = มีปัญหา
- กระดาษหมดหรือกระดาษติดอยู่หรือไม่
- หมึกพิมพ์หมดหรือใกล้หมดหรือไม่

### 2. ตรวจสอบสถานะบนคอมพิวเตอร์
1. เปิด **Settings > Printers & Scanners**
2. ตรวจว่าปริ้นเตอร์แสดงสถานะ **Ready** หรือ **Offline**
3. ถ้า Offline: คลิกขวา > **See what's printing** > เลือก **Printer** > ยกเลิก **Use Printer Offline**

### 3. ลบ Print Job ที่ค้างอยู่
1. เปิด **See what's printing**
2. เลือก Document ทั้งหมด > กด Delete
3. ลองสั่งพิมพ์ใหม่

### 4. Restart Spooler
1. พิมพ์ `services.msc` ในช่อง Run
2. หา **Print Spooler** > คลิกขวา > Restart

หากยังไม่ได้ผล แจ้ง IT พร้อมระบุ **ชื่อเครื่องปริ้นเตอร์** และ **ชื่อเครื่องคอมพิวเตอร์**"],

    ['cat'=>'Hardware','title'=>'จอภาพไม่ติด / No Signal ทำอย่างไร','tags'=>'monitor,no signal,จอไม่ติด,display','content'=>
"# จอภาพไม่ติด / No Signal

## ตรวจสอบตามลำดับ

1. **ตรวจสายสัญญาณ** — สาย HDMI หรือ VGA เสียบแน่นทั้งสองฝั่ง (จอ + เครื่อง) หรือไม่
2. **ตรวจไฟจอ** — กดปุ่มเปิดจอ ดูว่าไฟสถานะติดหรือไม่
3. **เปลี่ยน Input Source** — กดปุ่ม Input/Source บนจอ เลือก HDMI1 / HDMI2 / VGA ให้ตรงกับสายที่เสียบ
4. **รีสตาร์ทเครื่อง** — บางครั้งการบูทใหม่แก้ปัญหาได้
5. **ทดสอบสายอื่น** — ลองเปลี่ยนสาย HDMI เส้นอื่น

## กรณีมี 2 จอ
- กด **Windows + P** เลือก Extend หรือ Duplicate
- ถ้าจอเพิ่งย้ายโต๊ะ อาจต้องตั้ง Resolution ใหม่"],

    ['cat'=>'Hardware','title'=>'เมาส์/คีย์บอร์ดไม่ทำงาน','tags'=>'mouse,keyboard,เมาส์,คีย์บอร์ด,ไม่ทำงาน','content'=>
"# เมาส์ / คีย์บอร์ดไม่ทำงาน

## เมาส์ไม่ทำงาน
1. ถอดแล้วเสียบ USB ใหม่ — รอ 5 วินาที
2. ลองเสียบ USB ช่องอื่น
3. ถ้าเป็น **ไร้สาย**: ตรวจสอบแบตเตอรี่ และ USB Receiver ว่าเสียบครบ
4. ทดสอบกับคอมพิวเตอร์เครื่องอื่น — ถ้าไม่ทำงานบนเครื่องอื่นด้วย = เมาส์พัง

## คีย์บอร์ดไม่ทำงาน
1. ถอดแล้วเสียบ USB ใหม่
2. รีสตาร์ทเครื่อง
3. ตรวจสอบว่ากด **Fn + Num Lock** ค้างไว้โดยบังเอิญหรือไม่
4. ทำความสะอาดฝุ่นใต้แป้นพิมพ์ (ใช้สเปรย์เป่าลม)

> หากอุปกรณ์พัง แจ้ง IT เพื่อเบิกอะไหล่ทดแทน"],

    ['cat'=>'Hardware','title'=>'UPS (เครื่องสำรองไฟ) ส่งเสียงร้อง ต้องทำอย่างไร','tags'=>'ups,power,สำรองไฟ,แบต','content'=>
"# UPS ส่งเสียงร้อง

## ความหมายของเสียงเตือน

| เสียง | ความหมาย |
|-------|----------|
| บีบสั้น ๆ ต่อเนื่อง | ไฟดับ — UPS กำลังจ่ายไฟแทน |
| บีบยาว 1 ครั้ง | แบตเตอรี่ใกล้หมด |
| บีบถี่มาก | UPS มีปัญหา / แบตเสื่อม |

## สิ่งที่ต้องทำ

### เมื่อไฟดับ (เสียงบีบต่อเนื่อง)
1. **บันทึกงาน** ทันที — Ctrl+S ทุกโปรแกรม
2. ปิดโปรแกรมทั้งหมด
3. ปิดคอมพิวเตอร์อย่างถูกวิธี (Shut down) อย่ารอให้ UPS หมด

### เมื่อแบตใกล้หมด
- เร่งบันทึกงานและปิดเครื่อง
- แจ้ง IT หลังไฟกลับมา เพื่อตรวจสอบแบตเตอรี่ UPS"],

    // ===== Software =====
    ['cat'=>'Software','title'=>'คอมพิวเตอร์ช้า — วิธีแก้เบื้องต้น','tags'=>'slow,performance,คอมช้า,lag','content'=>
"# คอมพิวเตอร์ช้า ทำอย่างไร

## แก้เบื้องต้นด้วยตัวเอง

### 1. รีสตาร์ทเครื่อง
วิธีที่ง่ายที่สุด — เคลียร์หน่วยความจำ RAM และปิดโปรแกรมที่ค้างอยู่

### 2. ปิดโปรแกรมที่ไม่ใช้
- กด **Ctrl + Shift + Esc** เปิด Task Manager
- ดู CPU / Memory ว่าโปรแกรมใดใช้มาก
- คลิกขวาโปรแกรมนั้น > End Task

### 3. ลบไฟล์ชั่วคราว
1. กด **Windows + R** พิมพ์ `temp` แล้ว Enter
2. เลือกทั้งหมด (Ctrl+A) แล้วลบ (Delete)
3. ทำซ้ำกับ `%temp%` และ `prefetch`

### 4. Disk Cleanup
1. คลิกขวา Drive C: > Properties > Disk Cleanup
2. เลือกทุกช่อง > OK

### 5. ปิด Startup Programs
1. Task Manager > แท็บ **Startup**
2. Disable โปรแกรมที่ไม่จำเป็น

> หากทำทุกขั้นตอนแล้วยังช้า ให้แจ้ง IT เพื่อตรวจสอบ RAM / HDD"],

    ['cat'=>'Software','title'=>'Windows Update ค้างหรือติดลูป ทำอย่างไร','tags'=>'windows update,stuck,ค้าง','content'=>
"# Windows Update ค้าง / ติดลูป

## ขั้นตอนแก้ไข

### วิธีที่ 1: รอให้เสร็จ
Update บางตัวใช้เวลานาน โดยเฉพาะ Feature Update (นาน 30-60 นาที)
**อย่าปิดเครื่องระหว่าง Update กำลังทำงาน**

### วิธีที่ 2: Restart และลองใหม่
หาก Update ค้างนานเกิน 2 ชั่วโมง ลอง Restart ดู

### วิธีที่ 3: Windows Update Troubleshooter
1. Settings > Update & Security > Troubleshoot
2. เลือก Windows Update > Run the troubleshooter

### วิธีที่ 4: Reset Windows Update Service
1. เปิด Command Prompt (Admin)
2. พิมพ์คำสั่งตามลำดับ:
```
net stop wuauserv
net stop cryptSvc
net stop bits
net stop msiserver
ren C:\\Windows\\SoftwareDistribution SoftwareDistribution.old
net start wuauserv
net start cryptSvc
net start bits
net start msiserver
```
3. ลอง Windows Update อีกครั้ง

> **หมายเหตุ:** อย่าพยายาม Rollback Update โดยไม่ปรึกษา IT ก่อน"],

    ['cat'=>'Software','title'=>'Microsoft Office ไม่ยอมเปิด หรือเปิดช้ามาก','tags'=>'office,word,excel,outlook,ไม่เปิด','content'=>
"# Microsoft Office เปิดไม่ได้ / ช้ามาก

## แก้เบื้องต้น

### 1. Safe Mode
1. กด **Windows + R** พิมพ์ `winword /safe` (Word) หรือ `excel /safe`
2. ถ้าเปิดได้ใน Safe Mode — ปัญหาอาจอยู่ที่ Add-in
3. ไปที่ File > Options > Add-ins > Disable Add-ins ทีละตัว

### 2. Repair Office
1. Settings > Apps > Microsoft Office > Modify
2. เลือก **Quick Repair** ก่อน
3. ถ้าไม่หาย เลือก **Online Repair**

### 3. ล้าง Office Cache
1. ปิดโปรแกรม Office ทั้งหมด
2. เปิด `%localappdata%\\Microsoft\\Office` แล้วลบโฟลเดอร์ Cache

### 4. ตรวจ License
- File > Account — ดูว่า Office ยังมี License อยู่
- ถ้าขึ้น Unlicensed Product แจ้ง IT ทันที"],

    ['cat'=>'Software','title'=>'วิธีเพิ่ม Printer ในคอมพิวเตอร์ (Add Printer)','tags'=>'add printer,install,เพิ่มปริ้นเตอร์','content'=>
"# วิธีเพิ่ม / ติดตั้ง Printer

## เพิ่ม Network Printer (แบบ IP)
1. เปิด **Settings > Devices > Printers & Scanners**
2. คลิก **Add a printer or scanner**
3. ถ้าไม่พบ คลิก **The printer that I want isn't listed**
4. เลือก **Add a printer using a TCP/IP address or hostname**
5. กรอก IP Address ของปริ้นเตอร์ (ถามจาก IT)
6. คลิก Next และติดตั้ง Driver

## เพิ่มผ่าน Windows Share
1. เปิด File Explorer พิมพ์ `\\\\[ชื่อ Server]` ใน Address bar
2. ดับเบิลคลิก Printer ที่ต้องการ
3. คลิก **Yes** เมื่อถามว่าต้องการ Add

## ติดตั้ง Driver จาก IT
- แจ้ง IT เพื่อขอไฟล์ Driver ของปริ้นเตอร์รุ่นนั้น
- รัน Setup.exe แล้วทำตามขั้นตอน"],

    ['cat'=>'Software','title'=>'ไฟล์หาย หรือลบโดยไม่ตั้งใจ — กู้คืนอย่างไร','tags'=>'recover,deleted,recycle bin,ไฟล์หาย','content'=>
"# ไฟล์หาย / ลบโดยไม่ตั้งใจ

## ขั้นตอนกู้คืน

### 1. Recycle Bin (ถังขยะ)
- ดับเบิลคลิก Recycle Bin บน Desktop
- หาไฟล์ที่ต้องการ > คลิกขวา > **Restore**

### 2. Previous Versions
1. คลิกขวาที่โฟลเดอร์ที่ไฟล์อยู่ > **Properties**
2. แท็บ **Previous Versions**
3. เลือก Version ก่อนหน้า > Restore

### 3. File History (ถ้าเปิดใช้งาน)
1. Settings > Update & Security > Backup > More Options
2. ค้นหาไฟล์ใน Restore files from a current backup

### 4. กู้จาก Server (ถ้าไฟล์อยู่ใน Drive กลาง)
- แจ้ง IT พร้อมระบุ: ชื่อไฟล์, โฟลเดอร์, วันที่ลบ/แก้ไข

> **สำคัญ:** หยุดบันทึกไฟล์ใหม่ในโฟลเดอร์นั้นทันที เพื่อป้องกันทับ Sector ที่ยังกู้ได้"],

    // ===== Network =====
    ['cat'=>'Network','title'=>'อินเทอร์เน็ตใช้ไม่ได้ / หลุดบ่อย — แก้เบื้องต้น','tags'=>'internet,network,หลุด,ใช้ไม่ได้','content'=>
"# อินเทอร์เน็ตใช้ไม่ได้ / หลุดบ่อย

## ตรวจสอบตามลำดับ

### 1. ตรวจสาย LAN
- สายเสียบแน่นทั้งสองฝั่ง
- ไฟที่ Port สีเขียว = มีสัญญาณ, ไม่มีไฟ = สายหลวมหรือสายเสีย

### 2. Flush DNS และ Renew IP
1. เปิด Command Prompt (Admin)
2. พิมพ์:
```
ipconfig /release
ipconfig /flushdns
ipconfig /renew
```

### 3. รีสตาร์ท Network Adapter
1. คลิกขวา Start > Device Manager
2. ขยาย Network Adapters
3. คลิกขวา Adapter > **Disable** รอ 5 วินาที > **Enable**

### 4. รีเซ็ต TCP/IP Stack
```
netsh int ip reset
netsh winsock reset
```
(รีสตาร์ทเครื่องหลังจากนี้)

### 5. แจ้ง IT
บอก: อาการ, เครื่องที่มีปัญหา, ห้อง/ชั้น"],

    ['cat'=>'Network','title'=>'วิธีเชื่อมต่อ WiFi สำหรับพนักงาน','tags'=>'wifi,wireless,เชื่อมต่อ,ไวไฟ','content'=>
"# วิธีเชื่อมต่อ WiFi พนักงาน

## ขั้นตอน
1. คลิกไอคอน WiFi ที่ Taskbar มุมขวาล่าง
2. เลือก SSID: **Powertech-Staff**
3. กด **Connect**
4. กรอก Password (ขอรับจาก IT)
5. เลือก **Connect automatically** เพื่อเชื่อมอัตโนมัติในครั้งถัดไป

## หากเชื่อมต่อไม่ได้
- ลอง **Forget Network** แล้วเชื่อมต่อใหม่:
  คลิกขวา SSID > Forget > เชื่อมต่อใหม่
- ตรวจว่า WiFi บนเครื่องเปิดอยู่ (กด **Fn + F2** หรือปุ่ม WiFi)
- รีสตาร์ทเครื่อง

## ข้อสำคัญ
- WiFi **Powertech-Guest** สำหรับแขกภายนอกเท่านั้น ไม่มีสิทธิ์เข้า Server ภายใน
- ห้ามแชร์รหัส WiFi พนักงานให้บุคคลภายนอก"],

    ['cat'=>'Network','title'=>'วิธีเชื่อมต่อ VPN เพื่อทำงาน Remote','tags'=>'vpn,remote,work from home,ทำงานจากบ้าน','content'=>
"# การเชื่อมต่อ VPN

## ซอฟต์แวร์ที่ใช้
บริษัทใช้ **FortiClient VPN** (ขอติดตั้งได้ที่ IT)

## ขั้นตอนการเชื่อมต่อ
1. เปิดโปรแกรม **FortiClient**
2. คลิก **Remote Access**
3. เลือก VPN Profile: **Powertech-VPN**
4. กรอก Username และ Password (เดียวกับที่ Login Windows)
5. คลิก **Connect**
6. รอจนขึ้น **Connected**

## เมื่อเชื่อมต่อสำเร็จ
- สามารถเข้าถึง Drive กลาง, ระบบ ERP, และ Printer ได้เหมือนอยู่ที่ออฟฟิศ

## ข้อสำคัญ
- ตัด VPN ออกเมื่อไม่ใช้งาน
- ห้ามเชื่อม VPN บนเครื่องส่วนตัวโดยไม่ได้รับอนุญาต
- ถ้ารหัสผ่านหมดอายุ ต้องเปลี่ยนรหัสก่อนถึงจะ VPN ได้"],

    ['cat'=>'Network','title'=>'เข้า Drive กลาง (Shared Drive / NAS) ไม่ได้','tags'=>'shared drive,nas,network drive,drive กลาง','content'=>
"# เข้า Drive กลางไม่ได้

## ตรวจสอบเบื้องต้น

### 1. ตรวจสอบการเชื่อมต่อเครือข่าย
- ตรวจว่าอินเทอร์เน็ต/LAN ปกติก่อน (ลองเปิดเว็บดู)

### 2. Map Drive ใหม่
1. เปิด File Explorer > คลิกขวา **This PC** > **Map network drive**
2. เลือก Drive Letter (เช่น Z:)
3. กรอก Path: `\\\\[IP Server]\\[ชื่อ Share]`
4. ติ๊ก **Reconnect at sign-in**
5. กรอก Username/Password

### 3. เคลียร์ Credential เก่า
1. เปิด **Credential Manager** (ค้นหาใน Start)
2. ลบ Credential ของ Server นั้น
3. ลอง Map Drive ใหม่

### 4. ตรวจสิทธิ์
- บางโฟลเดอร์มีสิทธิ์เข้าถึงเฉพาะแผนก
- แจ้ง IT เพื่อขอสิทธิ์เพิ่ม

> **เมื่อทำงาน Remote:** ต้องเชื่อม VPN ก่อนถึงจะเข้า Drive กลางได้"],

    // ===== Email =====
    ['cat'=>'Email','title'=>'Outlook ไม่สามารถรับ-ส่งอีเมลได้','tags'=>'outlook,email,sync,ส่งอีเมล,รับอีเมล','content'=>
"# Outlook รับ-ส่งอีเมลไม่ได้

## ตรวจสอบตามลำดับ

### 1. ตรวจสถานะที่ Status Bar
- มุมล่างขวาของ Outlook ดูว่าขึ้น **Connected** หรือ **Disconnected**
- ถ้า Disconnected ตรวจอินเทอร์เน็ตก่อน

### 2. กด Send/Receive
กด **F9** หรือ Send/Receive All Folders เพื่อ Sync

### 3. รีสตาร์ท Outlook
บางครั้ง Outlook ค้าง — ปิดแล้วเปิดใหม่

### 4. ตรวจ Outbox
ถ้าส่งไม่ออก อีเมลจะติดอยู่ใน **Outbox** — ลองเปิดดูว่ามีอยู่หรือไม่
ลองเปิดอีเมลนั้นแล้ว Send อีกครั้ง

### 5. สร้าง Profile ใหม่
1. เปิด Control Panel > Mail > Show Profiles
2. เพิ่ม Profile ใหม่ และตั้งค่า Account อีกครั้ง

> หาก Account ถูก Block หรือ Password หมดอายุ ต้องแจ้ง IT เพื่อ Unlock"],

    ['cat'=>'Email','title'=>'วิธีตั้งค่าลายเซ็น (Signature) ใน Outlook','tags'=>'signature,outlook,ลายเซ็น,email','content'=>
"# วิธีตั้งค่า Email Signature ใน Outlook

## ขั้นตอน
1. เปิด Outlook ไปที่ **File > Options**
2. เลือกหัวข้อ **Mail** > คลิก **Signatures...**
3. คลิก **New** ตั้งชื่อ Signature (เช่น 'มาตรฐาน')
4. แก้ไข Signature ในกล่องข้อความ
5. ตั้งให้ใช้ Signature นี้กับ New Message และ Reply/Forward
6. คลิก **OK**

## รูปแบบ Signature มาตรฐาน
```
ชื่อ-นามสกุล
ตำแหน่ง | แผนก
บริษัท พาวเวอร์เทค จำกัด
โทร: 02-XXX-XXXX ต่อ XXXX
อีเมล: xxx@powertech.co.th
```

> **หมายเหตุ:** ห้ามใส่คำพูดส่วนตัว หรือ Emoji ใน Signature บริษัท"],

    ['cat'=>'Email','title'=>'วิธีตั้ง Out of Office (แจ้งพักร้อน)','tags'=>'out of office,auto reply,ลา,พักร้อน,outlook','content'=>
"# ตั้งค่า Out of Office Reply

## วิธีตั้งค่าใน Outlook
1. เปิด Outlook > **File > Automatic Replies (Out of Office)**
2. เลือก **Send automatic replies**
3. ติ๊ก **Only send during this time range** แล้วกรอกวันที่เริ่มต้น-สิ้นสุด
4. พิมพ์ข้อความตอบกลับ (ทั้ง Inside และ Outside Organization)
5. คลิก **OK**

## ตัวอย่างข้อความ Out of Office
```
ขอบคุณสำหรับอีเมลของท่าน
ขณะนี้ข้าพเจ้าลาหยุดพักร้อนในช่วง [วันที่] ถึง [วันที่]
และจะกลับมาตอบกลับในวันที่ [วันที่กลับ]

กรณีเร่งด่วน กรุณาติดต่อ [ชื่อผู้รับแทน] ที่ [อีเมล/โทร]

ขอบคุณ
[ชื่อ]
```

## ยกเลิก Out of Office
กลับไปที่ File > Automatic Replies > เลือก **Do not send automatic replies**"],

    ['cat'=>'Email','title'=>'อีเมลถูกส่งไป Junk/Spam ทำอย่างไร','tags'=>'junk,spam,อีเมลขยะ,whitelist','content'=>
"# อีเมลดีๆ ถูกส่งไป Junk/Spam

## กู้อีเมลจาก Junk Folder
1. เปิดโฟลเดอร์ **Junk Email** ใน Outlook
2. คลิกขวาอีเมลที่ต้องการ
3. เลือก **Junk > Not Junk** — Outlook จะย้ายกลับ Inbox และจำ

## เพิ่มผู้ส่งใน Safe Senders List
1. File > Options > Mail > **Junk Email**
2. แท็บ **Safe Senders** > Add
3. กรอกอีเมลหรือ Domain ที่ต้องการ (เช่น `@powertech.co.th`)

## ป้องกันระยะยาว
- แจ้ง IT หากอีเมลจาก Domain เดียวกันถูก Block ซ้ำๆ
- IT สามารถเพิ่ม Whitelist ที่ Email Server ได้"],

    // ===== Security =====
    ['cat'=>'Security','title'=>'ลืมรหัสผ่าน Windows / เข้าระบบไม่ได้','tags'=>'password,reset,login,ลืมรหัส,windows','content'=>
"# ลืมรหัสผ่าน / เข้าระบบไม่ได้

## ขั้นตอน

### ถ้ายังอยู่ในระบบ (Login อยู่)
1. กด **Ctrl + Alt + Del** > Change a password
2. เปลี่ยนรหัสผ่านใหม่ได้ทันที

### ถ้าออกจากระบบแล้ว (Login ไม่ได้)
1. โทรหา IT ภายใน **1234** หรือแจ้งผ่าน Ticket
2. IT จะ Reset รหัสผ่านชั่วคราวให้
3. Login ด้วยรหัสชั่วคราว แล้วเปลี่ยนรหัสทันที

### บัญชีถูก Lock (ป้อนรหัสผิดหลายครั้ง)
- รอ 30 นาที บัญชีจะ Unlock อัตโนมัติ (นโยบายบริษัท)
- หรือแจ้ง IT เพื่อ Unlock ทันที

## ข้อควรระวัง
- ห้ามแจ้งรหัสผ่านทางโทรศัพท์หรืออีเมล แม้แต่ IT จริงๆ ก็ไม่ต้องการรหัสผ่านของคุณ"],

    ['cat'=>'Security','title'=>'ระวัง! Phishing Email — วิธีสังเกตและรับมือ','tags'=>'phishing,email scam,หลอกลวง,security','content'=>
"# อีเมลหลอกลวง (Phishing) — วิธีรับมือ

## สัญญาณอันตราย
- ชื่อผู้ส่งแปลก แต่ปลอมทำเป็นธนาคาร/บริษัทดังๆ
- **ด่วนมาก** — กดลิงก์เดี๋ยวนี้ บัญชีจะถูกระงับ
- ลิงก์ URL แปลกๆ ไม่ตรงกับที่แสดง
- ขอ Username/Password หรือข้อมูลส่วนตัว
- ไฟล์แนบที่ไม่ได้ขอ เช่น .exe, .zip แปลกๆ

## สิ่งที่ต้องทำ
1. **อย่าคลิก** ลิงก์หรือเปิดไฟล์แนบ
2. **Mark as Junk** ใน Outlook
3. **แจ้ง IT ทันที** เพื่อ Blacklist ผู้ส่ง
4. ถ้าคลิกไปแล้ว — ตัดอินเทอร์เน็ตและแจ้ง IT ด่วน

## สิ่งที่ IT และผู้บริหารจะไม่ขอทางอีเมล
- รหัสผ่าน
- ข้อมูลบัตรเครดิต
- ข้อมูล OTP"],

    ['cat'=>'Security','title'=>'คอมพิวเตอร์ติดไวรัส — สังเกตและรับมืออย่างไร','tags'=>'virus,malware,ไวรัส,trojan,ransomware','content'=>
"# คอมพิวเตอร์ติดไวรัส

## อาการที่น่าสงสัย
- คอมช้าผิดปกติ แม้ไม่ได้เปิดโปรแกรม
- Pop-up โฆษณาขึ้นมากมายผิดปกติ
- ไฟล์หายหรือถูกเปลี่ยนชื่อ
- โปรแกรมแปลกๆ ที่ไม่เคยติดตั้ง
- เครือข่ายช้า เพราะมีการส่งข้อมูลออก

## สิ่งที่ต้องทำทันที
1. **ตัดเครือข่าย** — ถอดสาย LAN หรือปิด WiFi
2. **ห้ามปิดเครื่องกระทันหัน** (อาจทำลายหลักฐาน)
3. **แจ้ง IT ทันที** พร้อมอธิบายอาการ

## สิ่งที่ IT จะดำเนินการ
- สแกนด้วย Antivirus เต็มรูปแบบ
- ตรวจสอบ Process แปลกๆ
- กู้คืนไฟล์หากเป็น Ransomware (จาก Backup)

> **ห้ามดาวน์โหลดโปรแกรมสแกนไวรัสเอง** — บางตัวเป็น Malware ปลอม"],

    ['cat'=>'Security','title'=>'นโยบายรหัสผ่านบริษัท — ข้อกำหนดที่ต้องรู้','tags'=>'password policy,นโยบาย,รหัสผ่าน,security','content'=>
"# นโยบายรหัสผ่านบริษัท

## ข้อกำหนดรหัสผ่าน

| เงื่อนไข | รายละเอียด |
|---------|-----------|
| ความยาวขั้นต่ำ | 8 ตัวอักษร |
| ตัวพิมพ์ใหญ่ | อย่างน้อย 1 ตัว |
| ตัวพิมพ์เล็ก | อย่างน้อย 1 ตัว |
| ตัวเลข | อย่างน้อย 1 ตัว |
| อักขระพิเศษ | อย่างน้อย 1 ตัว (!@#$%^&*) |
| อายุรหัสผ่าน | 90 วัน |
| ห้ามซ้ำ | 3 รหัสล่าสุด |
| ล็อคบัญชี | ป้อนผิด 5 ครั้ง / ล็อค 30 นาที |

## ข้อห้าม
- ห้ามใช้ชื่อ, ชื่อบริษัท, หรือวันเกิด
- ห้ามเขียนรหัสผ่านบนกระดาษ
- ห้ามแชร์รหัสผ่านกับผู้อื่น แม้จะเป็นหัวหน้า"],

    // ===== Meeting & AV =====
    ['cat'=>'Meeting & AV','title'=>'วิธีใช้โปรเจคเตอร์ในห้องประชุม','tags'=>'projector,ห้องประชุม,hdmi,presentation','content'=>
"# วิธีใช้โปรเจคเตอร์ห้องประชุม

## เปิดใช้งาน
1. กดปุ่ม **Power** บน Remote หรือที่โปรเจคเตอร์
2. รอประมาณ 30-60 วินาทีให้หลอดไฟติดเต็มที่
3. เสียบสาย **HDMI** จากโน้ตบุ๊คหรือคอมพิวเตอร์

## ตั้งค่าหน้าจอบนโน้ตบุ๊ค (Windows)
- กด **Windows + P** เลือกโหมดที่ต้องการ:
  - **Duplicate** — แสดงเหมือนกันทั้งสองจอ
  - **Extend** — โปรเจคเตอร์เป็นจอที่สอง
  - **Second screen only** — แสดงเฉพาะโปรเจคเตอร์

## ปรับภาพ
- ปรับ Focus ที่แหวนรอบเลนส์
- ปรับ Zoom ตามระยะห่าง
- Keystone (ภาพบิดเบี้ยว): กดปุ่ม Keystone บน Remote

## ปิดใช้งาน
1. กดปุ่ม Power บน Remote **2 ครั้ง** เพื่อยืนยัน
2. **อย่าถอดสายไฟทันที** รอให้พัดลมหยุดก่อน (2-3 นาที)"],

    ['cat'=>'Meeting & AV','title'=>'ใช้ Microsoft Teams ประชุมออนไลน์ — เบื้องต้น','tags'=>'teams,meeting,online,ประชุม,video call','content'=>
"# Microsoft Teams — ประชุมออนไลน์

## เริ่มประชุม (Host)
1. เปิด Teams > คลิก **Calendar**
2. คลิก **New meeting** ตั้งชื่อ เวลา และเชิญผู้เข้าร่วม
3. หรือคลิก **Meet now** เพื่อเริ่มทันที
4. คัดลอก Meeting Link ส่งให้ผู้เข้าร่วม

## เข้าร่วมประชุม (Join)
1. รับ Link ทาง Email หรือ Calendar
2. คลิก Link > เลือก **Open Teams** หรือ **Join on the web**
3. ตั้งค่ากล้องและไมค์ก่อนเข้า

## ฟีเจอร์สำคัญระหว่างประชุม
| ปุ่ม | ฟังก์ชัน |
|------|---------|
| 🎤 | เปิด/ปิดไมค์ (Ctrl+Shift+M) |
| 📷 | เปิด/ปิดกล้อง (Ctrl+Shift+O) |
| 💬 | แชทระหว่างประชุม |
| ⬆️ | แชร์หน้าจอ |
| ✋ | ยกมือ |

## เมื่อมีปัญหาเสียง/ภาพ
- ตรวจสอบ Settings > Devices ใน Teams
- ลองออกแล้วเข้าใหม่"],

    ['cat'=>'Meeting & AV','title'=>'แก้ไขปัญหา Zoom ไม่มีเสียง / กล้องไม่ทำงาน','tags'=>'zoom,microphone,camera,เสียง,กล้อง','content'=>
"# Zoom ไม่มีเสียง / กล้องไม่ทำงาน

## ปัญหาไมค์ไม่ได้ยิน

### 1. ตรวจสอบใน Zoom
- คลิกลูกศรข้างปุ่มไมค์ > เลือก Microphone ที่ถูกต้อง
- ทดสอบ: Settings > Audio > Test Mic

### 2. ตรวจสอบ Windows
- คลิกขวา Speaker icon > Open Sound settings
- Input: ตรวจว่าเลือก Microphone ถูกตัว
- ลอง Speak แล้วดูว่า Level bar ขยับ

### 3. สิทธิ์การเข้าถึง
- Settings > Privacy > Microphone — ต้องเปิดให้ Zoom

## ปัญหากล้องไม่ทำงาน

### 1. ตรวจสอบใน Zoom
- คลิกลูกศรข้างปุ่มกล้อง > เลือก Camera ที่ถูกต้อง

### 2. ปิดโปรแกรมอื่นที่ใช้กล้อง
- Teams, Skype หรือโปรแกรมอื่นอาจจับกล้องค้างอยู่

### 3. Driver กล้อง
- Device Manager > Cameras > Update Driver"],

    // ===== Phone & Mobile =====
    ['cat'=>'Phone & Mobile','title'=>'วิธีโทรภายใน (Internal Extension)','tags'=>'extension,phone,โทรภายใน,ต่อสาย','content'=>
"# วิธีโทรภายใน (Internal Call)

## โทรระหว่างแผนก
- กดเบอร์ต่อโดยตรง เช่น **1234** (ไม่ต้องกด 0 นำหน้า)

## โทรออกนอกบริษัท
- กด **9** ก่อน แล้วตามด้วยเบอร์โทรศัพท์
- ตัวอย่าง: `9-02-XXX-XXXX`

## โทรระหว่างสาขา
- กด **8** + รหัสสาขา + เบอร์ต่อ
- ขอรายละเอียดรหัสสาขาได้จาก IT หรือ Receptionist

## ฟีเจอร์อื่น
| ฟังก์ชัน | วิธีใช้ |
|---------|--------|
| โอนสาย | กด Transfer > กดเบอร์ปลายทาง > วางหู |
| Hold | กด Hold > หยิบขึ้นมาค้างสาย |
| Conference | กด Conference > กดเบอร์ > Merge |
| Voicemail | กด \* หรือดูคู่มือรุ่นโทรศัพท์ |"],

    ['cat'=>'Phone & Mobile','title'=>'ตั้งค่าอีเมลบริษัทบนมือถือ (iOS/Android)','tags'=>'email,mobile,iphone,android,outlook app','content'=>
"# ตั้งค่าอีเมลบริษัทบนมือถือ

## ใช้แอป Outlook (แนะนำ)

### Android
1. ดาวน์โหลด **Microsoft Outlook** จาก Play Store
2. เปิดแอป > Add Account
3. กรอกอีเมลบริษัท > Continue
4. กรอกรหัสผ่าน > Sign In

### iOS (iPhone/iPad)
1. ดาวน์โหลด **Microsoft Outlook** จาก App Store
2. เปิดแอป > Add Account
3. กรอกอีเมลบริษัท > Add Account
4. กรอกรหัสผ่าน > Sign In

## หากเชื่อมต่อไม่ได้
- ตรวจว่ารหัสผ่านถูกต้อง (ลอง Login บนคอมก่อน)
- ถ้า IT เปิด 2FA (Multi-Factor Authentication) ต้องยืนยันตัวตนเพิ่มเติม
- แจ้ง IT เพื่อตรวจสอบ ActiveSync Policy

## ข้อสำคัญ
- ถ้าลาออก/สิ้นสุดสัญญา บัญชีอีเมลจะถูกปิดและข้อมูลจะหาย — Backup ก่อนออก"],
];

// --- Insert articles ---
$added = 0;
$skipped = 0;
foreach ($articles as $art) {
    $catId = $catMap[$art['cat']] ?? 0;
    if (!$catId) {
        echo "<p style='color:orange'>⚠️ ไม่พบหมวด: {$art['cat']}</p>";
        continue;
    }
    $stmt = $conn->prepare("INSERT INTO kb_articles (category_id, title, content, tags, is_published, view_count, created_at, updated_at)
        SELECT ?, ?, ?, ?, 1, 0, NOW(), NOW()
        WHERE NOT EXISTS (SELECT 1 FROM kb_articles WHERE title = ?)");
    $stmt->bind_param("issss", $catId, $art['title'], $art['content'], $art['tags'], $art['title']);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        echo "<p style='color:#2563eb'>✅ เพิ่ม: {$art['title']}</p>";
        $added++;
    } else {
        $skipped++;
    }
}

$total = $conn->query("SELECT COUNT(*) as c FROM kb_articles WHERE is_published=1")->fetch_assoc()['c'];
echo "<hr><h3>เสร็จสิ้น — เพิ่ม $added บทความ (ข้ามซ้ำ $skipped) / รวมในระบบ $total บทความ</h3>";
echo "<a href='kb.html' style='font-size:16px;font-weight:bold;color:#2563eb'>→ ไปดู Knowledge Base</a>";
?>
