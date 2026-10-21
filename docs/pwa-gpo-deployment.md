# Deploy PowertechCenter PWA Apps ผ่าน GPO (Force-Install)

สรุปขั้นตอนติดตั้งแอป **Powertech System Center** และ **Contact List (Directory)** ให้ user ทุกเครื่องอัตโนมัติผ่าน Group Policy โดยไม่ต้องกด Install เอง

ทดสอบสำเร็จแล้วบน 2 โดเมน: `PTASSEMBLY.COM` และ `pte.powertechenergy.co.th`

---

## ภาพรวม

- ใช้ **Microsoft Edge เท่านั้น** (ตัดสินใจไม่รองรับ Chrome — ลดความซับซ้อนการดูแล, Edge มากับ Windows อยู่แล้ว)
- ใช้ policy `WebAppInstallForceList` ของ Edge ผูกกับ URL คงที่ 2 อัน (Main App + Directory)
- ใช้ **ลิงก์ภายนอก** `https://powertecha.myqnapcloud.com:8081/PowertechCenter/` (ไม่ใช้ IP ภายใน 192.168.0.2) เพราะ:
  - มี cert จริงจาก **Let's Encrypt** (ไม่ใช่ self-signed) → ไม่ต้อง trust cert เพิ่มที่เครื่อง user เลย
  - ใช้ได้ทั้ง 3 บริษัทโดยไม่ต้องพึ่งเส้นทาง VPN ภายในระหว่างสาขา

## ค่า Policy ที่ใช้ (คงที่ ใช้ซ้ำได้ทุกโดเมน)

```json
{"url":"https://powertecha.myqnapcloud.com:8081/PowertechCenter/login.html","default_launch_container":"window","create_desktop_shortcut":true}
{"url":"https://powertecha.myqnapcloud.com:8081/PowertechCenter/directory.html?public=1","default_launch_container":"window","create_desktop_shortcut":true}
```

Registry path: `HKEY_LOCAL_MACHINE\SOFTWARE\Policies\Microsoft\Edge\WebAppInstallForceList`
Value name `1` = อัน Main App, Value name `2` = อัน Directory, ทั้งคู่ type `REG_SZ`

---

## ขั้นตอน Setup (ทำครั้งแรกต่อโดเมน)

### 1. เตรียม ADMX Template (ทำครั้งเดียว ใช้ไฟล์เดิมได้กับทุกโดเมน)

1. โหลดจาก https://www.microsoft.com/edge/business/download
   - **ระวัง**: หน้าเว็บมีปุ่ม "Download" ใหญ่ (= ตัวติดตั้งเบราว์เซอร์ ไม่ใช่ที่ต้องการ)
   - ต้องมองหาลิงก์เล็กๆ เขียนว่า **"Policy"** อยู่ใต้/ข้างปุ่ม Download หลัก
2. แตกไฟล์ที่โหลดมา (ผ่านตัวติดตั้ง/cab) → เข้าไปที่ `windows\admx\`
3. Copy ไฟล์ `.admx` ทั้ง 3 ไฟล์ (`msedge.admx`, `msedgeupdate.admx`, `msedgewebview2.admx`) + โฟลเดอร์ `en-US` (มี `.adml`)

### 2. วางไฟล์ที่ SYSVOL (Central Store)

```
\\<โดเมน>\SYSVOL\<โดเมน>\Policies\PolicyDefinitions\
```

**ถ้าไม่มีโฟลเดอร์ `PolicyDefinitions` อยู่แล้ว ต้องสร้างเอง** (ชื่อสะกดต้องตรงเป๊ะ) — เป็นเรื่องปกติถ้าโดเมนนั้นไม่เคยตั้ง Central Store มาก่อน

โครงสร้างสุดท้ายต้องได้:
```
PolicyDefinitions\msedge.admx
PolicyDefinitions\msedgeupdate.admx
PolicyDefinitions\msedgewebview2.admx
PolicyDefinitions\en-US\msedge.adml
PolicyDefinitions\en-US\msedgeupdate.adml
PolicyDefinitions\en-US\msedgewebview2.adml
```

### 3. สร้าง GPO

1. `gpmc.msc` → สร้าง GPO ใหม่ ตั้งชื่อสื่อความหมาย เช่น `GPO_Force Install Powertech Apps (Edge)`
2. Edit → **Computer Configuration → Preferences → Windows Settings → Registry**

**ใช้ Preferences → Registry ไม่ใช่ Administrative Templates** เพราะ:
- Administrative Templates มี setting ชื่อคล้ายกันสับสนง่าย (`Web App management settings` ≠ `Configure list of force-installed Web Apps` — ตัวหลังขึ้นต้นด้วย **"C"** ไม่ใช่ "W" ต้องหาในหมวด Configure)
- กล่องกรอก list ใน Administrative Templates UI เป็น single-line, กด Enter จะปิดหน้าต่างไปเลยแทนที่จะขึ้นบรรทัดใหม่ — ใช้งานสับสน
- Preferences → Registry เขียนตรงเข้า path เดียวกันกับที่ Administrative Templates จะเขียน → Edge อ่านแล้วบังคับ (Mandatory, uninstall ไม่ได้) เหมือนกันทุกประการ ไม่มีข้อเสีย

3. New → Registry Item ×2 (ดูค่าที่ต้องกรอกในหัวข้อ "ค่า Policy" ด้านบน)
4. Link GPO ไปยัง OU ที่มี user จริง

---

## วิธีทดสอบ

1. เครื่องทดสอบ: `gpupdate /force`
2. เช็คว่า policy ไปถึงเครื่องจริงไหม: `gpresult /r` (ดูว่า GPO นี้อยู่ใน Applied GPOs)
3. **ปิด Edge ให้หมดจริง** — เช็ค Task Manager ว่าไม่มี `msedge.exe` ค้างเบื้องหลังเลยสักตัว (**จุดที่พลาดบ่อยที่สุด** — ถ้า Edge ไม่ปิดสนิท จะไม่ reload policy ใหม่ แม้ registry จะถูกต้อง 100% แล้วก็ตาม)
4. เปิด Edge ใหม่ → รอ 10-20 วิ
5. เช็คผล 2 ทาง:
   - `edge://policy` → Reload policies → `WebAppInstallForceList` ต้องไม่ใช่ "Not set"
   - `edge://apps` → ควรเห็นทั้ง 2 แอปพร้อมไอคอนถูกต้อง

**เกณฑ์ง่ายสำหรับ user ทั่วไป (ไม่ต้องเข้า edge:// อะไรเลย)**: restart เครื่อง → เปิด Edge ครั้งแรก → เห็นไอคอน 2 ตัวขึ้นเองที่ Desktop = ใช้ได้แล้ว

---

## สำหรับเครื่องที่ไม่ได้ join domain

ใช้ไฟล์ (เก็บไว้ที่ `C:\Users\Kasidet.p\Desktop\` บนเครื่อง dev):
- `install_pwa_edge_manual.bat` — kill Edge + import registry + เปิด Edge ใหม่ ในคลิกเดียว (ต้องมีไฟล์ `.reg` คู่กันในโฟลเดอร์เดียวกัน)
- `install_pwa_edge_manual.reg` — ค่า registry ดิบ
- `remove_pwa_edge_manual.reg` — ใช้ถอนออก

**ห้ามใช้วิธี force-kill Edge อัตโนมัติกับเครื่อง user จริงที่ join domain** — เสี่ยงทำงานที่ยังไม่ได้ save หายถ้า Edge ถูกฆ่ากลางคัน ปล่อยให้ policy มีผลตอน user restart/เปิด Edge ใหม่ตามธรรมชาติแทน

---

## ความเสี่ยงที่ต้องติดตาม

1. **Cert ของ `myqnapcloud.com`** — Let's Encrypt หมดอายุทุก 90 วัน ต้องเช็คว่า QNAP ตั้ง auto-renew ไว้จริง ถ้า cert หมดอายุ = ทุกเครื่องที่ยังไม่เคย install จะติดตั้งไม่ได้พร้อมกันทันที
2. **Rollout แนะนำให้ทำทีละ OU** — เริ่มจากกลุ่มเล็กก่อน (แผนกเดียว) ค่อยขยายเป็นทั้งบริษัท กันปัญหาที่ไม่เคยเจอ (เช่น เครื่องรุ่นเก่า, Edge เวอร์ชันเก่ากว่าที่ policy รองรับ — ต้องการ Edge version 80+ เป็นอย่างน้อย)

---

## เรื่องที่เกี่ยวข้อง (แก้ไปพร้อมกันในเซสชันเดียวกัน)

- แก้บั๊ก GD extension ปิดอยู่ใน php.ini ทำให้ favicon/manifest sync ไม่ทำงานเวลาอัปโหลดโลโก้ใหม่ (`api/actions/system/upload_company_logo.php`)
- แก้ resize logic ให้ Main App/Directory icon เป็นสี่เหลี่ยมจัตุรัสเสมอ (scale-to-fit + center) ไม่งั้น Chrome/Edge จะ reject icon ที่ขนาดไม่ตรงกับที่ manifest ประกาศ แล้ว fallback เป็นตัวอักษรแทน
- เพิ่ม cache-busting (`?v=`) ผ่าน `js/favicon-version.js` + `.htaccess` no-cache header สำหรับ `app-manifest.json`/`directory-manifest.json`/`favicon.ico`
- แก้ `directory-manifest.json` ให้ `scope` แคบลงเฉพาะ `./directory.html` (เดิมเป็น `./` ชนกับ scope ของ Main App ทำให้ install ตัวที่สองไม่ได้)
