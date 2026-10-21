# CLAUDE.md — PowertechCenter

@AGENTS.md

## Stack
- PHP + MySQL (`powertech_system`) บน XAMPP · หน้าเว็บเป็น `*.html` + vanilla JS + Tailwind CDN + `css/design-system.css`
- ทุก request ผ่าน `api.php?action=<name>` → โหลด `api/actions/<folder>/<name>.php` ตามรายชื่อ `$subfolders` ใน `api.php`
- เพิ่ม action ใหม่ = สร้างไฟล์ในโฟลเดอร์ที่ถูกต้อง (ชื่อไฟล์ = ชื่อ action) ไม่ต้องแก้ router

## Theme — อย่าทำหลุด theme
- สไตล์ enterprise (แนว Jira): การ์ดขาว เส้นขอบ 1px พื้นเรียบ `#f1f5f9` สีบริษัทใช้เป็น accent เท่านั้น — ห้าม gradient/glassmorphism
- ใช้ class/token ที่มีอยู่ใน `css/design-system.css` ก่อนเสมอ (`ptc-badge ptc-badge--*`, `ptc-btn-ghost`, `ptc-table-td`, `var(--ptc-*)`) แทนการใส่ hex ใหม่
- CSS ที่เพิ่มเองต้องมีเวอร์ชัน `html.dark` ด้วย
- เลียนแบบ markup ของโค้ดข้างเคียงในหน้าเดียวกัน
- ห้ามใช้ Tailwind `hidden` กับ element ที่มี CSS `display` ผูกกับ ID (เช่น modal) — ใช้ `el.style.display` แทน

## PHP / API
- **ด่านกลาง (TISAX)** ใน `api.php`: ทุก action ต้องล็อกอิน ยกเว้นที่อยู่ใน `$PUBLIC_ACTIONS` · action ที่ต้องมีสิทธิ์เฉพาะใส่ใน `$ACTION_PERMS` — เพิ่ม action ใหม่ที่หน้าไม่ล็อกอินต้องใช้ ต้องเพิ่มชื่อใน `$PUBLIC_ACTIONS` ด้วย
- action ที่ต้องมีสิทธิ์: `requirePermission($conn, '<perm>')` / `hasPermission()` (หรือใส่ใน `$ACTION_PERMS`)
- ห้ามเก็บรหัสผ่านผู้ใช้เป็นข้อความธรรมดา · ห้ามลบประวัติพัสดุ (ใช้ "ตัดจำหน่าย" แทน)
- SQL ใช้ prepared statement เท่านั้น ห้ามต่อ string จาก input
- ตอบกลับเป็น JSON `{status: 'success'|'error', message, ...}`
- แจ้งเตือน LINE/Telegram ผ่าน `sendNotify()` ใน `includes/notify_helper.php`
- ตรวจ syntax: `C:\xampp\php\php.exe -l <file>`

## Front-end
- ข้อมูลจากผู้ใช้/DB ต้องผ่าน `escapeHtml()` ก่อนใส่ใน HTML, attribute หรือ `<textarea>`
- dialog/แจ้งเตือนใช้ helper ที่มีอยู่ (`js/ptc-swal-ui.js`, `js/ptc-toast.js`) ไม่สร้างใหม่
- เมนูและ badge อยู่ใน `js/sidebar.js` (นับจาก `get_pending_counts`)

## Style ของโค้ด
- คอมเมนต์เป็นภาษาไทย อธิบาย "ทำไม" ไม่ใช่ "ทำอะไร"
- แก้เฉพาะจุดที่ต้องแก้ ไม่ refactor/จัดรูปแบบไฟล์ใหญ่ทั้งไฟล์ (`admin.html`, `log.html` ใหญ่มาก)

## ห้ามทำถ้าไม่ได้รับอนุมัติ
- `deploy.bat` / robocopy ขึ้น production, `migrate.php`, `seed_stock.php`, `ALTER TABLE`, git commit/push
