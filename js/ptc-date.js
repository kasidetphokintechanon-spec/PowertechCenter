/**
 * ptc-date.js — ช่องวันที่ วว/ดด/ปปปป (พ.ศ.) และเวลา 24 ชม. ที่เหมือนกันทุกเครื่อง
 *
 * ช่อง <input type="date|time"> ของเบราว์เซอร์แสดงตามภาษาเครื่อง — เครื่องภาษา English (US) ขึ้น เดือน/วัน/ปี และ AM/PM
 * ตัวนี้ซ่อนช่องเดิมไว้ (ค่ายังเป็น YYYY-MM-DD / HH:MM เหมือนเดิม) แล้ววางช่องที่ผู้ใช้เห็นทับ
 * เปลี่ยนค่าแล้วยิง event 'change' + 'input' ที่ช่องเดิม → onchange/oninput ที่เขียนไว้ทำงานต่อได้โดยไม่ต้องแก้
 *
 *   PTCDate.enhance(root)   เรียกหลังวาด HTML ใหม่ทุกครั้ง (ช่องที่แปลงแล้วจะข้าม)
 *   พิมพ์ปีได้ทั้ง พ.ศ. และ ค.ศ. (ปีเกิน 2400 = พ.ศ.) · พิมพ์ 241069 / 24102569 ก็ได้
 */
(function () {
    const pad = n => String(n).padStart(2, '0');
    const toShow = iso => { const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || ''); return m ? `${m[3]}/${m[2]}/${+m[1] + 543}` : ''; };
    function parse(txt) {
        const d = String(txt || '').replace(/[^\d]/g, ' ').trim().split(/\s+/);
        let dd, mm, yy;
        if (d.length === 1 && /^\d{6}$|^\d{8}$/.test(d[0])) { dd = d[0].slice(0, 2); mm = d[0].slice(2, 4); yy = d[0].slice(4); }
        else if (d.length === 3) [dd, mm, yy] = d;
        else return null;
        let y = +yy; if (yy.length === 2) y += 2500; if (y > 2400) y -= 543;
        const dt = new Date(y, +mm - 1, +dd);
        if (dt.getFullYear() !== y || dt.getMonth() !== +mm - 1 || dt.getDate() !== +dd) return null;
        return `${y}-${pad(+mm)}-${pad(+dd)}`;
    }
    const fire = el => { el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); };
    const CSS = `.pd-w{position:relative;display:flex;align-items:center}.pd-w>input.pd-t{flex:1;min-width:0;padding-right:34px!important}
        .pd-w>button{position:absolute;right:4px;top:50%;transform:translateY(-50%);border:0;background:none;color:var(--ptc-text-muted);cursor:pointer;padding:5px 7px;font-size:13px}
        .pd-w>input[type=date]{position:absolute;left:0;bottom:0;width:100%;height:0;opacity:0;pointer-events:none;border:0;padding:0}
        .pt-w{display:flex;gap:4px;align-items:center}.pt-w select{flex:1;min-width:0}.pt-w>input[type=time]{display:none}
        .pd-t.bad{border-color:var(--ptc-danger,#dc2626)!important}`;
    function enhanceDate(el) {
        el.dataset.pd = 1;
        const w = document.createElement('div'); w.className = 'pd-w';
        const t = document.createElement('input'); t.type = 'text'; t.className = 'pd-t'; t.inputMode = 'numeric'; t.placeholder = 'วว/ดด/ปปปป'; t.autocomplete = 'off';
        t.value = toShow(el.value); if (el.style.cssText) t.style.cssText = el.style.cssText;
        const b = document.createElement('button'); b.type = 'button'; b.title = 'เลือกจากปฏิทิน'; b.innerHTML = '<i class="fa fa-calendar"></i>';
        el.parentNode.insertBefore(w, el); w.append(t, b, el);
        const commit = () => {
            const iso = parse(t.value);
            if (!t.value.trim()) return;
            if (!iso || (el.min && iso < el.min) || (el.max && iso > el.max)) { t.classList.add('bad'); return; }
            t.classList.remove('bad'); t.value = toShow(iso);
            if (iso !== el.value) { el.value = iso; fire(el); }
        };
        t.addEventListener('change', commit);
        t.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); commit(); } });
        b.addEventListener('click', () => { try { el.showPicker(); } catch (_) { el.focus(); el.click(); } });
        el.addEventListener('change', () => { t.value = toShow(el.value); t.classList.remove('bad'); });
    }
    function enhanceTime(el) {
        el.dataset.pd = 1;
        const step = Math.max(60, +el.step || 900), mins = [];
        for (let m = 0; m < 60; m += step / 60) mins.push(pad(m));
        const [h0, m0] = (el.value || '00:00').split(':');
        const w = document.createElement('div'); w.className = 'pt-w';
        const hs = document.createElement('select'), ms = document.createElement('select');
        hs.innerHTML = Array.from({ length: 24 }, (_, i) => `<option ${pad(i) === h0 ? 'selected' : ''}>${pad(i)}</option>`).join('');
        // ค่าที่ไม่ตรงช่วง (เช่น 10:20 จากข้อมูลเดิม) ยังเลือกค้างไว้ได้ ไม่เด้งเป็นค่าอื่นเอง
        ms.innerHTML = (mins.includes(m0) ? mins : [...mins, m0].sort()).map(m => `<option ${m === m0 ? 'selected' : ''}>${m}</option>`).join('');
        hs.setAttribute('aria-label', 'ชั่วโมง'); ms.setAttribute('aria-label', 'นาที');
        const sep = document.createElement('span'); sep.textContent = ':'; sep.style.fontWeight = '700';
        el.parentNode.insertBefore(w, el); w.append(hs, sep, ms, el);
        const set = () => { el.value = hs.value + ':' + ms.value; fire(el); };
        hs.addEventListener('change', set); ms.addEventListener('change', set);
    }
    function enhance(root) {
        if (!document.getElementById('pd-css')) { const s = document.createElement('style'); s.id = 'pd-css'; s.textContent = CSS; document.head.appendChild(s); }
        (root || document).querySelectorAll('input[type=date]:not([data-pd]):not([data-native])').forEach(enhanceDate);
        (root || document).querySelectorAll('input[type=time]:not([data-pd]):not([data-native])').forEach(enhanceTime);
    }
    window.PTCDate = { enhance, toShow, parse };
    // แปลงเองทุกครั้งที่หน้าวาดใหม่ / เปิดหน้าต่าง (SweetAlert) — หน้าไม่ต้องเรียกเองทุกจุด
    let queued = false;
    const run = () => { queued = false; enhance(); };
    const start = () => { enhance(); new MutationObserver(() => { if (!queued) { queued = true; queueMicrotask(run); } }).observe(document.body, { childList: true, subtree: true }); };
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
})();
