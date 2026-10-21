# รายงานตรวจสอบก่อนปล่อยใช้งานจริง (Pre-Launch Audit)

**วันที่ตรวจ:** 2026-05-28  
**ขอบเขต:** Directory · แจ้งซ่อม IT · งานช่าง (Log) · ยืม-คืน · คลังสินค้า · KB · Admin  
**สถานะรวม:** ใช้งานได้ — มีรายการปรับปรุงและคู่มือที่ต้องแจกทีม

---

## สรุปผู้บริหาร (Executive Summary)

| หัวข้อ | สถานะ |
|--------|--------|
| Flow หลัก + API | ผ่าน — โครงสร้าง modular ใน `api/actions/` |
| Popup / Theme | ปรับแล้วบางหน้า — ดูรายละเอียดต่อโมดูล |
| Admin ใช้ครบทุกฟังก์ชัน | **เฉพาะ `role = admin`** ใช้ได้ทุกเมนู |
| ความเร็ว | Index แก้แล้ว · Log/Directory ใช้ pagination — บางหน้ายังโหลด Tailwind CDN |
| คู่มือผู้ใช้ | มีที่ `docs/USER_GUIDE_TH.md` + `docs/help.html` |

---

## 1. สิทธิ์ Admin

### ใช้ได้ทุกฟังก์ชันหรือไม่?

| บัญชี | เข้า Admin | เมนูครบ | โมดูลภายนอก Admin |
|--------|------------|---------|---------------------|
| **`role = admin`** | ใช่ | ใช่ (ทุกแท็บ) | ตามสิทธิ์ทั่วไป + stock ถ้า `can_manage_stock` |
| **`admin.access_admin` อย่างเดียว** | ใช่ | **ไม่** — ต้องมีสิทธิ์ย่อย เช่น `employee.manage`, `asset.manage` | ตามสิทธิ์ที่ติ๊ก |

**ข้อแนะนำก่อนปล่อยจริง:** หัวหน้า IT ที่ดูแลระบบควรเป็น `role = admin` หรือมีสิทธิ์ครบในแท็บ Permissions

### เมนู Admin ที่มี

- พนักงาน / ตำแหน่ง / แผนก  
- พัสดุ IT / หมวด / สถานที่  
- ยืม-คืน (ลิงก์ `borrow_management.html`)  
- บริการ (Services บน index)  
- KB บทความ / หมวด  
- ประกาศ / วันหยุด  
- สิทธิ์ผู้ใช้  
- Telegram / LINE / Logo  
- Audit log · Backup · Update DB  

**คลังสินค้า** อยู่ที่ `stock_management.html` (ไม่ใช่แท็บใน Admin) — ต้องมี `stock.manage` หรือ `role = admin`

---

## 2. ตรวจรายโมดูล

### 2.1 รายชื่อพนักงาน (`directory.html`)

| รายการ | สถานะ | หมายเหตุ |
|--------|--------|----------|
| Login / session | ผ่าน | ผ่าน `directory.js` |
| Theme | ผ่าน | enterprise + directory-editor |
| Popup | ผ่าน | SweetAlert2 + `enterprise-swal.js` |
| Error | ผ่าน | มี retry / แจ้งเตือนใน JS |
| ความเร็ว | ปรับแล้ว | ตัด CSS `task-center-theme` ที่ไม่ใช้ออก |

**ทดสอบ:** สลับ PTA/PT4/PTE · ผังองค์กร · ค้นหาชื่อ · แก้ไขพนักงาน (ถ้ามีสิทธิ์)

---

### 2.2 แจ้งซ่อม IT (`request.html`)

| รายการ | สถานะ | หมายเหตุ |
|--------|--------|----------|
| Theme | ผ่าน | design-system + enterprise |
| Popup | ผ่าน | enterprise-swal |
| QR / แนบรูป | ผ่าน | โหลด lib หนัก — ใช้เมื่อจำเป็น |
| Error | ผ่าน | Swal แจ้ง validation |

**ทดสอบ:** สร้าง ticket · แนบรูป · ดูสถานะใน request / my_tickets

---

### 2.3 งานช่าง IT (`log.html`)

| รายการ | สถานะ | หมายเหตุ |
|--------|--------|----------|
| Theme | ผ่าน | design-system (Tailwind ใน head) |
| Popup | **ปรับแล้ว** | เพิ่ม `enterprise-swal.js` |
| Pagination | ผ่าน | `paginate=1` ลดโหลดทั้งตาราง |
| Error | ผ่าน | Swal + fallback fetch |

**ช้าเมื่อ:** โหลดครั้งแรก + Chart.js + เปิด modal หนัก — ใช้ filter บริษัท/วันที่

**ทดสอบ:** รับงาน · เปลี่ยนสถานะ · แนบไฟล์ · พิมพ์ ticket

---

### 2.4 ยืม-คืน IT (`loan.html`)

| รายการ | สถานะ | หมายเหตุ |
|--------|--------|----------|
| Theme | ผ่าน | loan-theme + enterprise |
| Popup | ผ่าน | enterprise-swal |
| Session | ผ่าน | check_session → login |

**ทดสอบ:** ขอยืม · อนุมัติ (borrow_management) · คืน

---

### 2.5 คลังสินค้า (`stock_management.html`)

| รายการ | สถานะ | หมายเหตุ |
|--------|--------|----------|
| Theme | ผ่าน | powerstock-ui + design-system |
| Popup | ผ่าน | enterprise-swal |
| สิทธิ์ | ผ่าน | admin หรือ `can_manage_stock` |

**ช้าเมื่อ:** หน้าใหญ่มาก — โหลดรายการสต็อกครั้งแรก · ใช้ filter

---

### 2.6 คลังความรู้ (`kb.html`)

| รายการ | สถานะ | หมายเหตุ |
|--------|--------|----------|
| Theme | **ปรับแล้ว** | เพิ่ม enterprise-theme |
| Popup | **ปรับแล้ว** | SweetAlert2 + enterprise-swal + ข้อความไทยเมื่อโหลดไม่สำเร็จ |
| แก้ไขบทความ | Admin | แท็บ KB ใน admin.html |

---

### 2.7 หน้าแรก (`index.html`)

| รายการ | สถานะ |
|--------|--------|
| OOM / ค้าง | แก้แล้ว — API เดียว `get_home_dashboard` · ไม่ใช้ SW |
| งานช่าง vs Task Center | แยกชัด — log vs task_center |

---

## 3. ประสิทธิภาพ (Performance)

| หน้า | สาเหตุช้า | แนวทาง |
|------|-----------|--------|
| **index** | เคยโหลด it_logs ทั้งก้อน + SW | แก้แล้ว |
| **log** | Tailwind CDN + Chart + modal | ใช้ filter · pagination |
| **directory** | JS ใหญ่ + หลาย CSS | ตัด CSS ไม่ใช้ |
| **request** | Tailwind + QR libs | ยอมรับได้ |
| **stock** | ไฟล์ HTML/JS ใหญ่ | แบ่งแท็บ · ไม่เปิดทุกแท็บพร้อมกัน |
| **admin** | โหลดข้อมูลหลายตาราง | ใช้ retry ที่มี · แยกแท็บ |

**ไม่แนะนำก่อนปล่อยจริง:** เปิดทุกแท็บพร้อมกันหลายสิบแท็บ · ใช้ Chrome ที่ cache เต็ม

---

## 4. Popup / Error Flow มาตรฐาน

โปรเจกต์ใช้ **SweetAlert2** + **`js/enterprise-swal.js`** (ปุ่มธีมเดียวกัน)

หน้าที่ควรมี enterprise-swal:

- request, loan, stock, directory, admin — มีแล้ว  
- **log** — เพิ่มแล้ว 2026-05-28  
- **kb** — เพิ่มแล้ว 2026-05-28  

เมื่อ API คืน `status: error` — แสดง `message` จาก server ใน Swal

---

## 5. Checklist ก่อนส่งมอบผู้ใช้

- [ ] ทดสอบ login บัญชี admin + user ธรรมดา + ช่าง IT  
- [ ] ทดสอบสลับบริษัท PTA / PT4 / PTE  
- [ ] Directory — ผังองค์กร HR and GA (ข้ามบริษัท)  
- [ ] แจ้งซ่อม → ปรากฏใน Log  
- [ ] ยืม-คืน → อนุมัติใน borrow_management  
- [ ] Stock — เบิก/อนุมัติ (ถ้าเปิดใช้)  
- [ ] แจกคู่มือ `docs/USER_GUIDE_TH.md` หรือเปิด `docs/help.html`  
- [ ] Deploy ไฟล์ชุดเดียวกันทั้ง localhost และ server จริง  
- [ ] Backup DB ก่อน go-live  

---

## 6. งานที่เลื่อนไป (ไม่บล็อก go-live)

| รายการ | หมายเหตุ |
|--------|----------|
| Task Center HR&GA ข้ามบริษัท | Mockup v2 — รอ owner สั่งทำ |
| ถอด Tailwind CDN บางหน้า | ลดภาระระยะยาว |
| คู่มือวิดีโอ | ทำหลัง go-live ได้ |

---

## 7. ไฟล์อ้างอิง

- คู่มือผู้ใช้: [USER_GUIDE_TH.md](./USER_GUIDE_TH.md)  
- หน้ารวมช่วยเหลือ: [help.html](./help.html)  
- แผนทดสอบเดิม: [SYSTEM_VERIFICATION_PLAN.md](./SYSTEM_VERIFICATION_PLAN.md)
