/**
 * doc-print.js — ตัวสร้างใบพิมพ์มาตรฐาน (หน้าตาจาก css/doc-print.css)
 * ทุกใบในระบบสร้างผ่านตัวนี้ เพื่อให้หน้าตาเหมือนกันและอยู่ A4 แผ่นเดียว
 *
 *   const html = await DocPrint.build(spec);  DocPrint.print(spec);  DocPrint.preview(spec);
 *   spec = {
 *     company:'PTA', formKey:'repair', docNo, dateText, statusText, classified:true,
 *     title:{ th, en, color }, info:[[label, value, isHtml]], blocks:[html...],
 *     images:[url...], sigs:[{ role, name, date, stamp }], qr:'url', size:'a4'|'a5l'|'land', fit:true,
 *     flow:true = เอกสารยาวหลายหน้า (ใบตรวจนับ) — ไม่ย่อให้พอดีแผ่น เลขหน้าพิมพ์ที่ขอบกระดาษ,
 *     barHtml = ปุ่ม/ตัวเลือกเพิ่มในแถบเครื่องมือ (ใช้คู่ toolbar:true)
 *     printLog = { url, data:{...}, next } นับการพิมพ์จริง (ตอนกดพิมพ์/Ctrl+P ไม่ใช่ตอนเปิดดู) · ใบที่ 2 ขึ้นไปขึ้น "สำเนา"
 *   }
 *   ตัวช่วยสร้างกล่อง: DocPrint.section(title, body, { cls, clamp, html, lined, height })
 */
(function () {
    const CO_COLOR = { PTA: '#059669', PT4: '#dc2626', PTE: '#2563eb' };
    const LOGO = { PTA: 'pta_logo.png', PT4: 'pt4_logo.png', PTE: 'pte_logo.png' };
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const base = () => location.origin + location.pathname.replace(/\/[^/]*$/, '/');
    const thDate = (v, time) => {
        if (!v) return '-';
        const d = v instanceof Date ? v : new Date(String(v).replace(' ', 'T'));
        if (isNaN(d)) return esc(v);
        return d.toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' }) + (time ? ' ' + d.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' }) : '');
    };

    let _co = null, _forms = null;
    async function companyInfo() {
        if (_co) return _co;
        try { _co = (await (await fetch('api.php?action=get_company_info')).json()).data || {}; } catch (_) { _co = {}; }
        return _co;
    }
    async function forms() {
        if (_forms) return _forms;
        try { const j = await (await fetch('api.php?action=get_doc_forms')).json(); _forms = j.status === 'success' ? j.forms || {} : {}; } catch (_) { _forms = {}; }
        return _forms;
    }
    function qrDataUrl(text) {
        if (!text || typeof QRCode === 'undefined') return '';
        const box = document.createElement('div');
        box.style.cssText = 'position:fixed;left:-9999px;top:0';
        document.body.appendChild(box);
        try { new QRCode(box, { text, width: 160, height: 160, correctLevel: QRCode.CorrectLevel.M }); } catch (_) { box.remove(); return ''; }
        const c = box.querySelector('canvas');
        const url = c ? c.toDataURL('image/png') : (box.querySelector('img')?.src || '');
        box.remove();
        return url;
    }

    // กล่องหัวข้อ "ไทย / ENGLISH" — clamp = จำนวนบรรทัดสูงสุด (ตัดเป็นบรรทัดเต็ม + "…")
    function section(title, body, o) {
        o = o || {};
        const cls = ['dp-sec', o.cls, o.lined && 'lined'].filter(Boolean).join(' ');
        const sb = `<div class="dp-sb${o.html ? ' html' : ''}"${o.height ? ` style="height:${o.height}"` : ''}>${o.clamp ? `<div class="dp-tx" style="--clamp:${o.clamp}">${body}</div>` : body}</div>`;
        return `<div class="${cls}"><div class="dp-sh">${title}</div>${sb}</div>`;
    }

    async function build(spec) {
        const s = Object.assign({ size: 'a4', fit: true, info: [], blocks: [], images: [], sigs: [] }, spec);
        if (s.flow) s.fit = false;
        const co = String(s.company || '').toUpperCase();
        const acc = CO_COLOR[co] || '#334155';
        const [coAll, fm] = await Promise.all([companyInfo(), forms()]);
        const ci = coAll[co] || {};
        const form = (s.formKey && fm[s.formKey]) || {};
        const nameEn = ci.name_en || '', nameTh = ci.name_th || (co ? co : 'Powertech Group');
        const addr = [ci.address_th || ci.address_en, ci.phone].filter(Boolean).join(' · ');
        const qr = qrDataUrl(s.qr);
        const b = base();

        const info = s.info.slice();
        if (info.length % 2) info.push(['', '']);
        const infoHtml = info.length ? `<div class="dp-grid">${info.map(([l, v, raw]) => `<div class="dp-f"><span class="l">${esc(l)}</span><span class="v">${raw ? v : (v == null || String(v).trim() === '' ? '-' : esc(v))}</span></div>`).join('')}</div>` : '';
        const imgs = s.images.slice(0, 4);
        const imgHtml = imgs.length ? `<div class="dp-sec"><div class="dp-sh">รูปแนบ / ATTACHMENTS${s.images.length > 4 ? ` · แสดง 4 จาก ${s.images.length} รูป` : ''}</div><div class="dp-ig">${imgs.map(u => `<div class="dp-im"><img src="${esc(u)}" alt=""></div>`).join('')}</div></div>` : '';
        const sigHtml = s.sigs.length ? `<div class="dp-sigs" style="grid-template-columns:repeat(${s.sigs.length},1fr)">${s.sigs.map(g => `<div class="dp-sig"><div class="sr">${g.role}</div><div class="sp">${g.stamp || ''}</div><div class="sl"></div>
            <div class="sn">( ${g.name ? esc(g.name) : '................................................'} )</div><div class="sd">วันที่ ${g.date || '......../......../........'}</div></div>`).join('')}</div>` : '';
        const formBox = form.no ? `<table class="dp-fm"><tr><td>เลขที่ฟอร์ม</td><td>${esc(form.no)}</td></tr>${form.rev ? `<tr><td>Rev.</td><td>${esc(form.rev)}</td></tr>` : ''}${form.eff ? `<tr><td>วันที่มีผล</td><td>${esc(form.eff)}</td></tr>` : ''}</table>` : '';
        const sizeCls = (s.size === 'a5l' ? ' a5l' : s.size === 'land' ? ' land' : '') + (s.flow ? ' flow' : '');
        // หลายหน้า: ใช้ขอบกระดาษจริง + เลขหน้า/เลขเอกสารที่ขอบล่างทุกหน้า (หัวใบมีแค่หน้าแรก หัวตารางซ้ำเอง)
        const pageCss = s.flow ? `@page{size:A4${s.size === 'land' ? ' landscape' : ''};margin:9mm 10mm 11mm;@bottom-left{content:"${String(s.docNo || '').replace(/["\\]/g, '')}${s.classified ? ' · ข้อมูลภายใน (Internal)' : ''}";font:7pt Sarabun,sans-serif;color:#94a3b8}@bottom-right{content:"หน้า " counter(page) " / " counter(pages);font:7pt Sarabun,sans-serif;color:#94a3b8}}`
            : s.size === 'a5l' ? '@page{size:A5 landscape;margin:0}' : s.size === 'land' ? '@page{size:A4 landscape;margin:0}' : '';

        return `<!DOCTYPE html><html lang="th"><head><meta charset="utf-8"><title>${esc(s.title.th)} ${esc(s.docNo || '')}</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="${b}css/doc-print.css?v=20261022b">${pageCss ? `<style>${pageCss}</style>` : ''}
</head><body>
${s.toolbar ? `<div class="dp-bar">${s.barHtml || ''}<button class="pri" onclick="window.print()">พิมพ์ / บันทึก PDF</button><button onclick="window.close()">ปิด</button></div>` : ''}
<div class="dp-page${sizeCls}" style="--acc:${acc};--doc:${s.title.color || acc}"><div class="dp-fit" id="dp-fit">
  <div class="dp-hd">
    ${LOGO[co] ? `<img class="dp-logo" src="${esc(b + 'uploads/logos/' + LOGO[co])}" alt="" onerror="this.remove()">` : ''}
    <div class="dp-co"><b>${esc(nameEn || nameTh)}</b><span>${nameEn ? esc(nameTh) + ' · ' : ''}ฝ่ายเทคโนโลยีสารสนเทศ</span>${addr ? `<small>${esc(addr)}</small>` : ''}</div>
    <div class="dp-meta"><div>${s.classified ? '<div class="dp-class">ข้อมูลภายใน · INTERNAL</div>' : ''}${s.docNo ? `<div class="t">เลขที่</div><div class="id">${esc(s.docNo)}</div>` : ''}
      ${s.dateText ? `<div class="t">${esc(s.dateText)}</div>` : ''}${s.statusText ? `<div class="st">${esc(s.statusText)}</div>` : ''}${s.printLog ? '<div class="dp-copy" id="dp-copy"></div>' : ''}${formBox}</div>
      ${qr ? `<img class="dp-qr" src="${qr}" alt="">` : ''}</div>
  </div>
  <div class="dp-title"><b>${esc(s.title.th)}</b><span>${esc(s.title.en || '')}</span></div>
  ${infoHtml}
  ${s.blocks.join('')}
  ${imgHtml}
  <div class="dp-grow"></div>
  ${sigHtml}
  <div class="dp-ft"><span>ออกโดยระบบ Powertech Center · พิมพ์ ${thDate(new Date(), true)}</span><span>${form.no ? esc(form.no) + (form.rev ? ' Rev.' + esc(form.rev) : '') + ' · ' : ''}${esc(s.docNo || '')}${s.flow ? '' : (s.docNo ? ' · ' : '') + 'หน้า 1/1'}</span></div>
</div></div>
<script>
${s.printLog ? `// ป้ายต้นฉบับ/สำเนา — อัปเดตก่อนพิมพ์ทุกครั้ง แล้วส่งบันทึกไปเซิร์ฟเวอร์ (keepalive: ปิดหน้าทันทีก็ยังส่งถึง)
var PL=${JSON.stringify(s.printLog).replace(/</g, '\\u003c')};
function dpCopy(){ var e=document.getElementById('dp-copy'); if(!e) return; var c=PL.next>1; e.textContent=c?'สำเนา · ครั้งที่ '+PL.next:'ต้นฉบับ'; e.className='dp-copy'+(c?' is-copy':''); }
dpCopy();
window.addEventListener('beforeprint',function(){ dpCopy(); try{ var fd=new FormData(); for(var k in PL.data) fd.append(k,PL.data[k]); fetch(PL.url,{method:'POST',body:fd,credentials:'same-origin',keepalive:true}); }catch(e){} });
window.addEventListener('afterprint',function(){ PL.next++; dpCopy(); });
` : ''}// ยังล้นหน้า → ย่อทั้งใบให้พอดีแผ่นเดียว
function dpFit(){ ${s.fit ? '' : 'return;'} var f=document.getElementById('dp-fit'); f.style.transform=''; f.style.width='';
  var p=f.parentNode, cs=getComputedStyle(p), avail=p.clientHeight-parseFloat(cs.paddingTop)-parseFloat(cs.paddingBottom);
  var h=f.scrollHeight; if(h>avail+1){ var k=Math.max(.7,avail/h); f.style.transform='scale('+k+')'; f.style.width=(100/k)+'%'; } }
window.__ready=new Promise(function(res){ var go=function(){ dpFit(); res(); };
  var imgs=[].slice.call(document.images), n=imgs.length, done=0;
  var wait=function(){ if(document.fonts&&document.fonts.ready){ document.fonts.ready.then(function(){ setTimeout(go,60); }); } else setTimeout(go,150); };
  if(!n) return wait(); imgs.forEach(function(i){ var f=function(){ if(++done>=n) wait(); }; if(i.complete) f(); else { i.onload=f; i.onerror=f; } });
  setTimeout(go,3000); });
</script></body></html>`;
    }

    async function printHtml(html) {
        const old = document.getElementById('doc-print-frame');
        if (old) old.remove();
        const f = document.createElement('iframe');
        f.id = 'doc-print-frame';
        f.style.cssText = 'position:fixed;right:0;bottom:0;width:210mm;height:297mm;border:0;opacity:0;pointer-events:none;';
        document.body.appendChild(f);
        f.contentDocument.open(); f.contentDocument.write(html); f.contentDocument.close();
        await new Promise(r => { const w = f.contentWindow; (w.__ready ? w.__ready.then(r) : setTimeout(r, 800)); setTimeout(r, 4000); });
        f.contentWindow.focus();
        f.contentWindow.print();
    }
    async function print(spec) { return printHtml(await build(spec)); }
    async function preview(spec) {
        const w = window.open('', '_blank');
        if (!w) return print(spec);
        w.document.write('<p style="font-family:sans-serif;padding:40px;color:#64748b">กำลังเตรียมเอกสาร...</p>');
        const html = await build(Object.assign({}, spec, { toolbar: true }));
        w.document.open(); w.document.write(html); w.document.close();
    }

    window.DocPrint = { build, print, preview, printHtml, section, esc, thDate, companyInfo, forms, CO_COLOR };
})();
