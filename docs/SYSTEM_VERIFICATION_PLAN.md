# แผนการตรวจสอบความสมบูรณ์ของระบบ (System Verification Plan)

## วัตถุประสงค์
- ยืนยันว่าระบบ Powertech System Center ทำงานถูกต้อง ครบถ้วน เสถียร และตอบโจทย์ธุรกิจ 100%
- ครอบคลุมทุกโมดูล ทุกเส้นทางการใช้งาน และทุกสถานะที่เป็นไปได้ รวมถึงกรณีผิดปกติ
- นิยามเกณฑ์ผ่าน/ไม่ผ่าน และเอกสารรายงานผลเพื่อพร้อมปล่อยใช้งานจริง

## ขอบเขตระบบที่ทดสอบ
- Authentication/Session: [check_session.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/auth/check_session.php), [login.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/auth/login.php), [logout.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/auth/logout.php)
- Dashboard & Services: [get_pending_counts.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/dashboard/get_pending_counts.php), [get_dashboard_stats.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/dashboard/get_dashboard_stats.php)
- Chat (ส่วนติดต่อ และ API):
  - ผู้ใช้ & กลุ่ม: [get_chat_users.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/chat/get_chat_users.php)
  - ประวัติ & ข้อความ: [get_chat_history.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/chat/get_chat_history.php), [send_chat_message.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/chat/send_chat_message.php)
  - ไฟล์แชท: [upload_chat_file.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/chat/upload_chat_file.php)
  - กลุ่มแชท: [create_chat_group.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/chat/create_chat_group.php), [invite_chat_group_members.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/chat/invite_chat_group_members.php), [get_chat_group_members.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/chat/get_chat_group_members.php), [leave_chat_group.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/chat/leave_chat_group.php), [delete_chat_group.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/chat/delete_chat_group.php)
  - Polling/WS Update: [get_chat_updates.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/chat/get_chat_updates.php), [bin/chat-server.php](file:///c:/xampp/htdocs/PowertechCenter/bin/chat-server.php), [websocket-server.js](file:///c:/xampp/htdocs/PowertechCenter/websocket-server.js)
- Ticket (IT Logs): [update_item.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/ticket/update_item.php), [upload_attachment.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/ticket/upload_attachment.php), [rate_ticket.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/ticket/rate_ticket.php)
- Loan: [requestEquipment.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/loan/requestEquipment.php), [update_loan_status.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/loan/update_loan_status.php), [getLoanRequests.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/loan/getLoanRequests.php)
- Directory: [search_chat_directory.php](file:///c:/xampp/htdocs/PowertechCenter/api/actions/employee/search_chat_directory.php)
- ระบบเสริม: PWA, Notifications (Telegram/LINE) ใน [api.php](file:///c:/xampp/htdocs/PowertechCenter/api.php)

## สภาพแวดล้อมการทดสอบ
- OS/Runtime: Windows + XAMPP (Apache + PHP + MySQL)
- Browser: Chrome ล่าสุด, Edge ล่าสุด, Firefox ล่าสุด
- Mobile: iOS Safari/Chrome, Android Chrome
- เครือข่าย: LAN ภายในสำนักงาน + จำลองเน็ตช้า/ขาดช่วง
- ข้อมูลทดสอบ: ใช้ไฟล์ seed และบัญชีผู้ใช้ของจริง/ทดสอบที่มีอยู่ในระบบ

## ประเภทการทดสอบ
- Functional: ทดสอบฟังก์ชันทุกโมดูลและทุกเส้นทางใช้งาน
- Integration: ตรวจการทำงานร่วมกันระหว่างหน้าเว็บและ API/DB/WebSocket
- Security: Authentication/Authorization, Input Validation, Upload/Path Safety, XSS/SQL Injection
- Performance: Response time, Concurrency, Peak load สำหรับ Chat/แดชบอร์ด
- Reliability: Fault tolerance, Polling fallback, Reconnect WS, Offline (PWA), Resume
- Usability: Responsive/Mobile UX, Accessibility เบื้องต้น
- Data Integrity: ความถูกต้องของข้อมูล/ความสัมพันธ์, รายงาน/สรุปสถิติ
- Observability: Error logs, API JSON consistency, Alert/Badge correctness

## เกณฑ์ผ่าน/ไม่ผ่าน (Exit Criteria)
- ไม่มี Critical/High defects ที่ยังไม่แก้
- Test cases ผ่าน ≥ 99% และกรณี failed ต้องมีวิธีแก้พร้อมแผนและไม่กระทบธุรกิจ
- ผ่าน Security checks ที่กำหนดทั้งหมด
- ผ่าน Performance baseline: หน้า/ฟังก์ชันสำคัญตอบสนอง < 2s ในเงื่อนไขปกติ
- เอกสารผลทดสอบครบถ้วน พร้อมลงนามรับรองก่อนปล่อยใช้จริง

---

## Functional Test Cases (ตัวอย่างสำคัญ)

### 1) Authentication/Session
- เข้าระบบด้วยบัญชีที่ถูกต้อง/ไม่ถูกต้อง/ล็อกบัญชี/ข้ามบริษัท
- Session timeout/รีเฟรชและกลับเข้าสู่ระบบ
- เปลี่ยนบริษัท (switch_company) แล้วข้อมูลแดชบอร์ด/Badge อัปเดตถูกต้อง

### 2) Dashboard & Services
- โหลดประกาศ, สถานะงานของฉัน, แผนภูมิแผนก
- Badge รวม Pending ถูกต้องสำหรับ Admin/IT
- ปุ่มบริการเปิดหน้าที่ถูกต้อง ทั้ง primary/fallback

### 3) Chat: ผู้ใช้/แชทส่วนตัว
- โหลดรายชื่อแชท: เห็น IT ทั้งระบบสำหรับผู้ใช้ทั่วไป, เห็นทั้งหมดสำหรับ Admin/IT
- เปิดห้องคุย: ประวัติข้อความแสดงถูกต้อง, เลื่อนอัตโนมัติ, วันที่/เวลา
- ส่งข้อความ Text: optimistic UI, ยืนยัน message_id จาก API
- แนบไฟล์: รูป/เอกสาร, แสดงไอคอน/ขนาด, ลิงก์ดาวน์โหลด
- วางรูปจาก clipboard: แสดง preview และส่งสำเร็จ
- ทดสอบกรณีผิดปกติ: API error/network error/ไฟล์ขนาดใหญ่/ประเภทไม่อนุญาต

### 4) Chat: กลุ่มแชท
- สร้างกลุ่ม: ชื่อซ้ำ/ชื่อว่าง/ผู้ใช้ว่าง → validate ถูกต้อง
- เชิญสมาชิก: เฉพาะเจ้าของ/แอดมิน, validate คนที่เป็นสมาชิกอยู่แล้ว/มีคำเชิญค้าง
- ดูสมาชิกกลุ่ม: แสดงชื่อ/แผนก/บริษัท, แสดง “(คุณ)” ถูกต้อง
- ออกจากกลุ่ม: สมาชิกทั่วไปออกเองได้, รายการแชทอัปเดต
- ลบกลุ่ม: เฉพาะเจ้าของ/แอดมิน, ลบหลายส่วน (messages/members/invites/logs/groups)
- Fallback Polling/WS:
  - ต่อ WS สำเร็จ → ได้ online users/typing/new_message
  - WS ล่ม → แสดงแถบแจ้งเตือน, สลับเป็น Polling ทุก 1.5s, รับข้อความ/ใบอ่าน

### 5) Directory
- ค้นหาชื่อ/ชื่อไทย/แผนก: แสดงผล ≤ 20 รายการ, เลือกคนเพื่อสร้างแชทใหม่ได้
- กรณีค้นหาสั้น < 2 ตัว, ไม่พบผล, error จาก API

### 6) Ticket (IT Logs)
- สร้าง/อัปเดตสถานะ/แนบรูป/พิมพ์ใบงาน
- แสดงไทม์ไลน์/Badge/สรุปในแดชบอร์ด
- แทรกหมายเลข Ticket ในข้อความแชท แล้วกดไฮไลต์ย้อนหลังได้

### 7) Loan
- ขออุปกรณ์/อนุมัติ/รับคืน, สิทธิ์สำหรับ Admin/Staff/User
- Badge Pending ในแดชบอร์ดและหน้าเมนูถูกต้อง

### 8) PWA/มือถือ
- ติดตั้ง PWA, เปิดใช้งาน offline/online
- UI บนมือถือ: เปิด/ปิดแชท, ซ่อน/แสดง sidebar, ปุ่มย้อนกลับ, พิมพ์ข้อความ, แนบไฟล์

---

## Security Test Checklist
- Authentication bypass: เข้าถึงหน้า/API โดยไม่มี session
- Authorization: ผู้ใช้ทั่วไปไม่สามารถเข้าถึง API สำหรับ Admin/IT
- Input Validation: `action` ปลอดภัย, JSON/ข้อความ escape ถูกต้อง
- Upload Safety: ประเภทไฟล์, ขนาด, บังคับแปลง/บีบอัดภาพ, ป้องกัน path traversal
- XSS: ช่องแชท/ประกาศ/ฟอร์ม, render ด้วย `escapeHtml`
- SQL Injection: ใช้ prepared statement ทุกจุด, ตรวจสอบ query ที่มี dynamic parts
- Session: cookie/config, logout แล้วห้ามเรียก API สำเร็จ

---

## Performance & Reliability
- Response time หน้า/ฟังก์ชันสำคัญ < 2s ในเงื่อนไขปกติ
- Chat concurrency: ผู้ใช้พร้อมกัน ≥ 25 คน, ส่งข้อความพร้อมกัน → ไม่มี duplicate/หาย
- WS reconnect: ตัดเน็ต/หยุดเซิร์ฟเวอร์ WS → เห็นแถบแจ้งเตือน และ fallback ทำงาน
- Uploads: ไฟล์รูปขนาดใหญ่ → บีบอัด/แสดง meta ขนาดหลังบีบถูกต้อง

---

## วิธีบันทึกผลทดสอบ
- ใช้ตาราง “Test Case ID / ขั้นตอน / ข้อมูลทดสอบ / ผลคาดหวัง / ผลจริง / ผ่าน/ไม่ผ่าน / หมายเหตุ”
- บันทึกสครินช็อต/วิดีโอสำหรับเคสสำคัญ/ผิดปกติ
- รวมสรุป defect (ระดับ, โมดูล, ความรุนแรง, วิธีแก้, สถานะ)
- ผู้ทดสอบและผู้รับรองลงนามในฉบับสุดท้าย

---

## แผนการดำเนินงานทดสอบ
- เตรียมสภาพแวดล้อมและบัญชีทดสอบ
- รันทดสอบตามกลุ่ม: Auth → Dashboard → Chat → Ticket → Loan → Directory → PWA/Mobile → Security → Performance
- ทดสอบทั้ง “กรณีปกติ” และ “กรณีผิดปกติ”
- สรุปผล/แก้ไขข้อบกพร่อง/ทดสอบซ้ำจนผ่านเกณฑ์ทั้งหมด

---

## ข้อเสนอแนะเพิ่มเติม
- เพิ่ม smoke tests script สำหรับ endpoint สำคัญ (Auth/Chat/Ticket/Loan) เพื่อรันอัตโนมัติในสภาพแวดล้อม staging
- เปิด log file สำหรับ WS/Chat เพื่อช่วยสอบสวนปัญหาความเสถียร
- กำหนด SLA ภายในทีม (เวลาตอบสนอง/เวลาปรับแก้ defect ก่อนปล่อย)

