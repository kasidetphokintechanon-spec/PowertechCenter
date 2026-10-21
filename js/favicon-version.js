// js/favicon-version.js
// favicon.ico และ <link rel="icon"/apple-touch-icon"> ถูก request ด้วย URL คงที่ทุกหน้า
// เบราว์เซอร์เลยแคชไว้ถาวรแม้ไฟล์จริงบนดิสก์จะเปลี่ยนไปแล้ว (อัปโหลดโลโก้ Main App ใหม่)
// ไฟล์นี้อ่านเลขเวอร์ชันล่าสุด (อัปเดตทุกครั้งที่อัปโหลดผ่าน upload_company_logo.php)
// แล้วแปะ ?v= ต่อท้าย href ของ icon link ทุกอันในหน้า ให้ browser มองว่าเป็น URL ใหม่ ไม่ใช้แคชเก่า
//
// ทุกหน้า HTML ของโปรเจกต์นี้อยู่ที่ root เดียวกับโฟลเดอร์ uploads/ จึงอ้างอิงแบบ relative
// กับตัวหน้าเอง (document.baseURI) ได้เลย ไม่ต้องอิงตำแหน่งของ <script> ตัวนี้
(function () {
    fetch('uploads/logos/favicon-version.txt', { cache: 'no-store' })
        .then(function (res) { return res.ok ? res.text() : null; })
        .then(function (version) {
            version = (version || '').trim();
            if (!version) return;
            document.querySelectorAll('link[rel~="icon"], link[rel="apple-touch-icon"]').forEach(function (el) {
                if (!el.href) return;
                try {
                    var url = new URL(el.href, document.baseURI);
                    url.searchParams.set('v', version);
                    el.href = url.toString();
                } catch (e) { /* ignore malformed href */ }
            });
        })
        .catch(function () { /* เงียบไว้ — ไม่ใช่ของสำคัญพอจะแจ้ง error ให้ผู้ใช้เห็น */ });
})();
