/**
 * ticket-print.js — ใบแจ้งซ่อม / ใบงานซ่อม / ใบปิดงาน / ใบยกเลิก (A4 แผ่นเดียวเสมอ)
 * ใช้ร่วมกันทั้งหน้าช่าง (log.html) และหน้าผู้ใช้ (request.html) — เดิมมีโค้ดพิมพ์ 2 ชุดที่หน้าตาไม่ตรงกัน
 *
 *   TicketPrint.print(log, { includeImages:true })   พิมพ์ทันที (ผ่าน iframe ไม่ต้องเปิดหน้าต่างใหม่)
 *   TicketPrint.preview(log, { includeImages:true }) เปิดดูตัวอย่างในแท็บใหม่
 *
 * ให้อยู่ 1 หน้า: ข้อความยาวถูกจำกัดความสูง, รูปแนบแถวเดียวสูงสุด 4 รูป, ถ้ายังล้นจะย่อทั้งใบให้พอดี
 */
(function () {
    const STATUS_TH = {
        'Pending': 'รอรับเรื่อง', 'Received': 'รับเรื่องแล้ว', 'In Progress': 'กำลังซ่อม', 'Waiting for Parts': 'รออะไหล่',
        'Waiting Parts': 'รออะไหล่', 'Parts Received': 'ได้อะไหล่แล้ว', 'Waiting for User': 'รอผู้แจ้งตอบกลับ',
        'Sent to Vendor': 'ส่งซ่อมภายนอก', 'Temporary Fix': 'แก้ชั่วคราว', 'PR Submitted': 'ขอซื้อ (PR)', 'PO Issued': 'สั่งซื้อ (PO)',
        'Awaiting Confirmation': 'รอผู้แจ้งยืนยัน', 'Completed': 'เสร็จสิ้น', 'Unrepairable': 'ซ่อมไม่ได้', 'Cancelled': 'ยกเลิก',
    };
    const CO_COLOR = { PTA: '#059669', PT4: '#dc2626', PTE: '#2563eb' };
    const DOC = {
        request:    { th: 'ใบแจ้งซ่อม',  en: 'Service Request',          color: '#2563eb', form: 'repair' },
        workorder:  { th: 'ใบงานซ่อม',   en: 'Work Order',               color: '#d97706', form: 'repair' },
        completion: { th: 'ใบปิดงานซ่อม', en: 'Job Completion Certificate', color: '#059669', form: 'repair' },
        cancelled:  { th: 'ใบยกเลิกงาน', en: 'Cancellation Notice',      color: '#64748b', form: 'repair' },
    };
    const WORK = ['Received', 'In Progress', 'Waiting for User', 'Sent to Vendor', 'Temporary Fix', 'PR Submitted', 'PO Issued', 'Waiting for Parts', 'Waiting Parts', 'Parts Received', 'Awaiting Confirmation'];

    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const val = v => (v == null || String(v).trim() === '' ? '-' : esc(v));
    const thDate = (v, time) => {
        if (!v) return '-';
        const d = new Date(String(v).replace(' ', 'T'));
        if (isNaN(d)) return esc(v);
        return d.toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' }) + (time ? ' ' + d.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' }) : '');
    };
    const duration = (a, b) => {
        if (!a || !b) return '-';
        let m = Math.round((new Date(String(b).replace(' ', 'T')) - new Date(String(a).replace(' ', 'T'))) / 60000);
        if (!(m > 0)) return '-';
        const d = Math.floor(m / 1440); m -= d * 1440;
        const h = Math.floor(m / 60); m -= h * 60;
        return [d && d + ' วัน', h && h + ' ชม.', m && m + ' นาที'].filter(Boolean).join(' ') || '-';
    };
    // ข้อความงาน IT มีแท็กระบบปนอยู่ เช่น [CANCELLED BY IT] / [Reopened ...] — เก็บไว้แต่ขึ้นบรรทัดใหม่ให้อ่านง่าย
    const textBlock = v => esc(String(v || '').trim()).replace(/\s*(\[(?:Reopened|CANCELLED)[^\]]*\])/g, '\n$1');

    let _coCache = null, _formCache = null;
    async function companyInfo() {
        if (_coCache) return _coCache;
        try { const j = await (await fetch('api.php?action=get_company_info')).json(); _coCache = j.data || {}; } catch (_) { _coCache = {}; }
        return _coCache;
    }
    // เลขที่เอกสาร (ตั้งในหน้าตั้งค่า) — ยังไม่ตั้ง = ไม่แสดงกล่องเลขฟอร์ม
    async function formNumbers() {
        if (_formCache) return _formCache;
        try { const j = await (await fetch('api.php?action=get_doc_forms')).json(); _formCache = j.status === 'success' ? (j.forms || {}) : {}; } catch (_) { _formCache = {}; }
        return _formCache;
    }
    async function images(id) {
        try {
            const files = await (await fetch(`api.php?action=get&file=it_log_attachments&log_id=${encodeURIComponent(id)}&v=${Date.now()}`)).json();
            return Array.isArray(files) ? files.filter(f => /\.(jpe?g|png|gif|webp)$/i.test(f.stored_filename || '')) : [];
        } catch (_) { return []; }
    }
    function qrDataUrl(text) {
        if (typeof QRCode === 'undefined') return '';
        const box = document.createElement('div');
        box.style.cssText = 'position:fixed;left:-9999px;top:0';
        document.body.appendChild(box);
        try { new QRCode(box, { text, width: 160, height: 160, correctLevel: QRCode.CorrectLevel.M }); } catch (_) { box.remove(); return ''; }
        const c = box.querySelector('canvas');
        const url = c ? c.toDataURL('image/png') : (box.querySelector('img')?.src || '');
        box.remove();
        return url;
    }

    async function build(log, opts) {
        const o = Object.assign({ includeImages: true }, opts || {});
        const co = String(log.company || '').toUpperCase();
        const accent = CO_COLOR[co] || '#334155';
        const docType = log.status === 'Pending' ? 'request' : log.status === 'Cancelled' ? 'cancelled' : WORK.includes(log.status) ? 'workorder' : 'completion';
        const doc = DOC[docType];
        const [coAll, forms, imgs] = await Promise.all([companyInfo(), formNumbers(), o.includeImages ? images(log.id) : Promise.resolve([])]);
        const ci = coAll[co] || {};
        const form = forms[doc.form] || {};
        const base = location.origin + location.pathname.replace(/\/[^/]*$/, '/');
        const qr = qrDataUrl(base + 'request.html?tab=tickets');
        const logo = { PTA: 'pta_logo.png', PT4: 'pt4_logo.png', PTE: 'pte_logo.png' }[co];

        const cancelReason = (String(log.solution || '').match(/\[CANCELLED BY IT\]\s*เหตุผล:\s*([\s\S]+)/) || [])[1] || '';
        const solution = String(log.solution || '').replace(/\[CANCELLED BY IT\][\s\S]*/, '').trim();
        const cost = parseFloat(log.repair_cost || 0);

        const info = [
            ['ผู้แจ้ง', log.requester], ['แผนก', log.department],
            ['ช่างผู้รับผิดชอบ', log.servicedBy], ['ประเภทงาน', log.type],
            ['เครื่อง / รหัสพัสดุ', log.asset_id || log.asset], ['ความเร่งด่วน', log.urgency],
            ['วันที่แจ้ง', thDate(log.date, true), true], ['เบอร์ติดต่อ', log.contact],
        ];
        if (docType === 'completion') info.push(['วันที่ปิดงาน', thDate(log.completed_date || log.closed_date, true), true], ['ใช้เวลา', duration(log.date, log.completed_date || log.closed_date)]);
        if (cost > 0 || log.pr_no || log.po_no) info.push(['ค่าใช้จ่าย', cost > 0 ? cost.toLocaleString('th-TH', { minimumFractionDigits: 2 }) + ' บาท' : '-'], ['PR / PO', [log.pr_no, log.po_no].filter(Boolean).join(' / ') || '-']);
        if (info.length % 2) info.push(['', '']);
        const infoHtml = info.map(([l, v, raw]) => `<div class="f"><span class="l">${esc(l)}</span><span class="v">${raw ? v : val(v)}</span></div>`).join('');

        const section = (title, body, cls) => `<div class="sec ${cls || ''}"><div class="sh">${title}</div><div class="sb"><div class="tx">${body}</div></div></div>`;
        let work = '';
        if (docType === 'workorder') {
            work = section('บันทึกการซ่อม / WORK NOTES', solution ? textBlock(solution) : '', 'notes');
        } else if (docType === 'completion') {
            work = section('วิธีแก้ไข / SOLUTION', solution ? textBlock(solution) : '-', 'ok') +
                (log.root_cause ? section('สาเหตุ / ROOT CAUSE', textBlock(log.root_cause), 'rc') : '');
        } else if (docType === 'cancelled') {
            work = section('เหตุผลที่ยกเลิก / REASON', cancelReason ? textBlock(cancelReason) : '-', 'bad');
        }

        const shown = imgs.slice(0, 4);
        const imgHtml = shown.length ? `<div class="sec imgs"><div class="sh">รูปแนบ / ATTACHMENTS${imgs.length > 4 ? ` · แสดง 4 จาก ${imgs.length} รูป` : ''}</div>
            <div class="ig">${shown.map(f => `<div class="im"><img src="${esc(base + 'uploads/attachments/' + f.stored_filename)}" alt=""></div>`).join('')}</div></div>` : '';

        // ผู้แจ้งยืนยันผ่านระบบแล้ว → แสดงตรายืนยัน (ไม่ต้องเซ็นกระดาษซ้ำ)
        const confirmed = docType === 'completion' && Number(log.user_confirmed) === 1;
        const sigImg = confirmed && /^data:image\//.test(String(log.user_signature || '')) ? `<img class="sig-img" src="${esc(log.user_signature)}" alt="">` : '';
        const sig = (role, name, date, stamp) => `<div class="sig"><div class="sr">${role}</div>
            <div class="sp">${stamp || ''}</div><div class="sl"></div>
            <div class="sn">( ${name ? esc(name) : '................................................'} )</div>
            <div class="sd">วันที่ ${date || '......../......../........'}</div></div>`;
        const stamp = confirmed ? `${sigImg || `<div class="stamp"><i>✓</i> ยืนยันผ่านระบบแล้ว</div>`}` : '';
        const sigs = docType === 'request' ? [sig('ผู้แจ้ง / Requester', log.requester, thDate(log.date))]
            : docType === 'cancelled' ? [sig('ผู้แจ้ง / Requester', log.requester, ''), sig('ฝ่าย IT / IT Staff', log.servicedBy, thDate(log.closed_date || log.date))]
            : docType === 'workorder' ? [sig('ผู้แจ้ง / Requester', log.requester, ''), sig('ช่างผู้ซ่อม / Technician', log.servicedBy, '')]
            : [sig('ผู้แจ้ง / Requester', log.user_confirmed_by || log.requester, confirmed ? thDate(log.user_confirmed_at) : '', stamp),
               sig('ช่างผู้ซ่อม / Technician', log.servicedBy, thDate(log.completed_date || log.closed_date)),
               sig('หัวหน้าแผนก / Head of Dept.', '', '')];

        const formBox = form.no ? `<table class="fm"><tr><td>เลขที่ฟอร์ม</td><td>${esc(form.no)}</td></tr>${form.rev ? `<tr><td>Rev.</td><td>${esc(form.rev)}</td></tr>` : ''}${form.eff ? `<tr><td>วันที่มีผล</td><td>${esc(form.eff)}</td></tr>` : ''}</table>` : '';
        const nameTh = ci.name_th || co || 'Powertech Group';
        const nameEn = ci.name_en || '';
        const addr = [ci.address_th || ci.address_en, ci.phone].filter(Boolean).join(' · ');

        return `<!DOCTYPE html><html lang="th"><head><meta charset="utf-8"><title>${esc(doc.th)} ${esc(log.id)}</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
@page { size: A4 portrait; margin: 0; }
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { background: #e5e7eb; }
body { font-family: 'Sarabun', Tahoma, sans-serif; color: #0f172a; font-size: 9.5pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.page { width: 210mm; height: 297mm; margin: 0 auto; background: #fff; padding: 10mm 11mm; overflow: hidden; }
.fit { transform-origin: top left; display: flex; flex-direction: column; min-height: 277mm; }
.hd { display: flex; gap: 10px; align-items: flex-start; padding-bottom: 7px; border-bottom: 2px solid ${accent}; }
.hd .logo { height: 40px; width: auto; }
.co { flex: 1; min-width: 0; border-left: 3px solid ${accent}; padding-left: 9px; }
.co b { font-size: 12.5pt; display: block; line-height: 1.25; }
.co span { font-size: 8.5pt; color: #475569; display: block; }
.co small { font-size: 7.5pt; color: #64748b; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.meta { text-align: right; display: flex; gap: 8px; align-items: flex-start; }
.meta .t { font-size: 7.5pt; color: #64748b; line-height: 1.45; }
.meta .id { font-family: Consolas, monospace; font-size: 11.5pt; font-weight: 800; color: ${accent}; }
.meta .st { display: inline-block; margin-top: 2px; padding: 0 7px; border-radius: 99px; font-size: 7.5pt; font-weight: 700; background: #f1f5f9; color: #334155; }
.qr { width: 19mm; height: 19mm; }
.fm { border-collapse: collapse; font-size: 7pt; margin-top: 3px; margin-left: auto; }
.fm td { border: 1px solid #cbd5e1; padding: 1px 5px; } .fm td:first-child { background: #f1f5f9; color: #64748b; }
.title { margin: 7px 0; padding: 6px 12px; border-radius: 5px; background: ${doc.color}; color: #fff; display: flex; justify-content: space-between; align-items: baseline; }
.title b { font-size: 12.5pt; } .title span { font-size: 8pt; opacity: .9; }
.grid { display: grid; grid-template-columns: 1fr 1fr; border: 1px solid #e2e8f0; border-radius: 5px; overflow: hidden; }
.f { display: flex; gap: 8px; padding: 4px 9px; border-bottom: 1px solid #eef2f6; min-width: 0; }
.f:nth-child(odd) { border-right: 1px solid #eef2f6; }
.f .l { flex: 0 0 31mm; font-size: 7.8pt; color: #64748b; font-weight: 600; }
.f .v { flex: 1; min-width: 0; font-size: 9pt; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sec { margin-top: 6px; border: 1px solid #e2e8f0; border-radius: 5px; overflow: hidden; }
.sh { font-size: 7.5pt; font-weight: 700; letter-spacing: .03em; color: #475569; background: #f8fafc; padding: 3px 9px; border-bottom: 1px solid #e2e8f0; }
.sb { padding: 5px 9px; font-size: 9.2pt; line-height: 1.5; white-space: pre-line; overflow: hidden; }
/* ตัดเป็นบรรทัดเต็ม + "…" ที่ตัวข้อความ (กรอบมี padding แยก จึงไม่มีเศษบรรทัดโผล่) */
.tx { display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden; }
.prob .tx { -webkit-line-clamp: 7; }
.ok .sb { background: #f0fdf4; } .ok .tx { -webkit-line-clamp: 7; } .ok .sh { color: #15803d; }
.rc .tx { -webkit-line-clamp: 3; }
.bad .sb { background: #fef2f2; } .bad .tx { -webkit-line-clamp: 5; } .bad .sh { color: #b91c1c; }
.notes .tx { -webkit-line-clamp: 6; }
.notes .sb { height: 32mm; background: repeating-linear-gradient(#fff 0 6.9mm, #e2e8f0 6.9mm 7mm); }
.imgs .ig { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; padding: 5px 9px 7px; }
.im { height: 36mm; border: 1px solid #e2e8f0; border-radius: 4px; overflow: hidden; background: #f8fafc; }
.im img { width: 100%; height: 100%; object-fit: cover; display: block; }
.grow { flex: 1; }
.sigs { display: grid; grid-template-columns: repeat(${sigs.length}, 1fr); gap: 8px; margin-top: 8px; }
.sig { border: 1px solid #e2e8f0; border-radius: 5px; padding: 5px 9px 6px; text-align: center; }
.sr { font-size: 8pt; font-weight: 700; text-align: left; color: #334155; }
.sp { height: 15mm; display: flex; align-items: flex-end; justify-content: center; }
.sig-img { max-height: 14mm; max-width: 100%; }
.stamp { border: 1.5px solid #16a34a; color: #15803d; border-radius: 4px; padding: 1px 8px; font-size: 8pt; font-weight: 700; transform: rotate(-3deg); }
.sl { border-bottom: 1px solid #475569; margin: 2px 8px 3px; }
.sn { font-size: 8pt; color: #334155; } .sd { font-size: 7.5pt; color: #64748b; }
.ft { margin-top: 6px; padding-top: 4px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; font-size: 7pt; color: #94a3b8; }
@media print { html, body { background: #fff; } .page { margin: 0; } .bar { display: none !important; } }
.bar { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; padding: 10px; background: #0f172a; }
.bar button { font: inherit; font-weight: 700; padding: 6px 16px; border-radius: 6px; border: 0; cursor: pointer; }
</style></head><body>
${o.toolbar ? '<div class="bar"><button onclick="window.print()" style="background:#2563eb;color:#fff">พิมพ์ / บันทึก PDF</button><button onclick="window.close()">ปิด</button></div>' : ''}
<div class="page"><div class="fit" id="fit">
  <div class="hd">
    ${logo ? `<img class="logo" src="${esc(base + 'uploads/logos/' + logo)}" alt="" onerror="this.remove()">` : ''}
    <div class="co"><b>${esc(nameEn || nameTh)}</b><span>${nameEn ? esc(nameTh) + ' · ' : ''}ฝ่ายเทคโนโลยีสารสนเทศ</span>${addr ? `<small>${esc(addr)}</small>` : ''}</div>
    <div class="meta"><div><div class="t">เลขที่ใบงาน</div><div class="id">${esc(log.id)}</div>
      <div class="t">แจ้งเมื่อ ${thDate(log.date)}</div><div class="st">${esc(STATUS_TH[log.status] || log.status)}</div>${formBox}</div>
      ${qr ? `<img class="qr" src="${qr}" alt="">` : ''}</div>
  </div>
  <div class="title"><b>${esc(doc.th)}</b><span>${esc(doc.en)}</span></div>
  <div class="grid">${infoHtml}</div>
  ${section('ปัญหา / อาการที่แจ้ง · PROBLEM', textBlock(log.problem) || '-', 'prob')}
  ${work}
  ${imgHtml}
  <div class="grow"></div>
  <div class="sigs">${sigs.join('')}</div>
  <div class="ft"><span>ออกโดยระบบ Powertech Center · พิมพ์ ${thDate(new Date().toISOString(), true)}</span><span>${form.no ? esc(form.no) + (form.rev ? ' Rev.' + esc(form.rev) : '') + ' · ' : ''}${esc(log.id)} · หน้า 1/1</span></div>
</div></div>
<script>
// ยังล้นหน้า (ข้อความ/ช่องเยอะ) → ย่อทั้งใบให้พอดี A4 แผ่นเดียว
function fitPage(){ var f=document.getElementById('fit'); f.style.transform=''; f.style.width='';
  var avail=f.parentNode.clientHeight-parseFloat(getComputedStyle(f.parentNode).paddingTop)-parseFloat(getComputedStyle(f.parentNode).paddingBottom);
  var h=f.scrollHeight; if(h>avail){ var k=Math.max(.72,avail/h); f.style.transform='scale('+k+')'; f.style.width=(100/k)+'%'; } }
window.__ready = new Promise(function(res){ var imgs=[].slice.call(document.images); var n=imgs.length; if(!n){ setTimeout(function(){fitPage();res();},150); return; }
  var done=0, go=function(){ if(++done>=n){ fitPage(); res(); } }; imgs.forEach(function(i){ if(i.complete) go(); else { i.onload=go; i.onerror=go; } });
  setTimeout(function(){ fitPage(); res(); }, 2500); });
</script>
</body></html>`;
    }

    async function print(log, opts) {
        const html = await build(log, opts);
        const old = document.getElementById('ticket-print-frame');
        if (old) old.remove();
        const f = document.createElement('iframe');
        f.id = 'ticket-print-frame';
        f.style.cssText = 'position:fixed;right:0;bottom:0;width:210mm;height:297mm;border:0;opacity:0;pointer-events:none;';
        document.body.appendChild(f);
        f.contentDocument.open(); f.contentDocument.write(html); f.contentDocument.close();
        await new Promise(r => { const w = f.contentWindow; (w.__ready ? w.__ready.then(r) : setTimeout(r, 800)); setTimeout(r, 4000); });
        await (document.fonts && f.contentDocument.fonts ? f.contentDocument.fonts.ready.catch(() => {}) : Promise.resolve());
        f.contentWindow.focus();
        f.contentWindow.print();
    }

    async function preview(log, opts) {
        const w = window.open('', '_blank');
        if (!w) return print(log, opts);
        w.document.write('<p style="font-family:sans-serif;padding:40px;color:#64748b">กำลังเตรียมเอกสาร...</p>');
        const html = await build(log, Object.assign({}, opts, { toolbar: true }));
        w.document.open(); w.document.write(html); w.document.close();
    }

    window.TicketPrint = { print, preview, build };
})();
