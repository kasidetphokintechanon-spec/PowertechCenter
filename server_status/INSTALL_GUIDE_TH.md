# คู่มือย่อ (1 หน้า) – ติดตั้งระบบตรวจสอบสถานะเซิร์ฟเวอร์แต่ละสาขา

## 1) ไฟล์ที่ต้องมีในเครื่องสาขา
คัดลอกโฟลเดอร์นี้ไปไว้ในเครื่องสาขา (ภายใต้ XAMPP htdocs)
- `PowertechCenter/server_status/`

ในโฟลเดอร์ต้องมีอย่างน้อย
- `server_status/index.html`
- `server_status/api.php`
- `server_status/data/config.json`
- `server_status/data/logs.json`
- `server_status/telegram_bot.js`
- `run_telegram_bot.bat`

## 2) ติดตั้งโปรแกรมที่จำเป็น
- ติดตั้ง **XAMPP** (ใช้ Apache/PHP)
- ติดตั้ง **Node.js** (ใช้รัน Telegram Bot)

## 3) เปิดใช้งาน Web UI (ตั้งค่า IP/ชื่อเครื่อง)
เปิดเบราว์เซอร์:
```
http://localhost/PowertechCenter/server_status/index.html
```
บันทึก IP/ชื่อเครื่องในกลุ่ม PTA / PT4 / PTE ตามที่ต้องการ

ถ้าเข้าจากเครื่องอื่นในเครือข่าย:
```
http://<IP-เครื่องสาขา>/PowertechCenter/server_status/index.html
```

## 4) ตั้งค่าและรัน Telegram Bot
1) เปิดไฟล์ `run_telegram_bot.bat` แล้วแก้ค่า:
```
TELEGRAM_BOT_TOKEN=ใส่โทเคน
TELEGRAM_CHAT_ID=ใส่ chat id
```

2) ดับเบิลคลิก `run_telegram_bot.bat` เพื่อรันบอท

## 5) คำสั่งที่ใช้ใน Telegram
- `pta`
- `pt4`
- `pte`
- `all`

ตัวอย่างผลลัพธ์:
```
Status PTA: ✅ Server Main: ONLINE ❌ Router: OFFLINE
```

## 6) ตรวจสอบ Log ย้อนหลัง
เปิดหน้า UI แล้วดูหัวข้อ “ประวัติการตรวจสอบ”

---
### เช็กลิสต์ก่อนใช้งาน
- [ ] เปิด Apache (XAMPP) แล้ว
- [ ] รัน Telegram Bot แล้ว (หน้าต่างไม่ปิด)
- [ ] ใส่ IP/ชื่อเครื่องเรียบร้อยใน UI
- [ ] ทดสอบพิมพ์คำสั่งใน Telegram ได้ผล
