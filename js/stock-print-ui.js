/**
 * PowertechCenter — shared print-slip function for stock request pages
 */
(function (global) {
    const CO_NAMES_EN = {
        PTA: 'Powertech Engine Assembly Co., Ltd.',
        PT4: 'Powertech 2004 Co., Ltd.',
        PTE: 'Powertech Energy Solutions Co., Ltd.',
    };
    const CO_NAMES_TH = {
        PTA: 'บริษัท พาวเวอร์เทค เอนจิ้น แอสเซมบลี จำกัด',
        PT4: 'บริษัท พาวเวอร์เทค 2004 จำกัด',
        PTE: 'บริษัท พาวเวอร์เทค เอนเนอร์ยี โซลูชั่นส์ จำกัด',
    };
    const CO_PALETTE = {
        PTA: { accent: '#059669', grad: 'linear-gradient(135deg,#065f46,#059669)' },
        PT4: { accent: '#dc2626', grad: 'linear-gradient(135deg,#991b1b,#dc2626)' },
        PTE: { accent: '#2563eb', grad: 'linear-gradient(135deg,#1d4ed8,#2563eb)' },
    };
    const CO_LOGOS = {
        PTA: location.origin + '/PowertechCenter/uploads/logos/pta_logo.png',
        PT4: location.origin + '/PowertechCenter/uploads/logos/pt4_logo.png',
        PTE: location.origin + '/PowertechCenter/uploads/logos/pte_logo.png',
    };

    /**
     * @param {number} requestId
     * @param {Array}  items        — [{ item_code, name, unit, qty }]
     * @param {string} notes
     * @param {object} opts         — { formNo, titleTh, titleEn, session, getCompanyFn }
     */
    async function openPrintSlip(requestId, items, notes, opts) {
        opts = opts || {};
        const formNo  = opts.formNo  || 'FM-XX-001';
        const titleTh = opts.titleTh || 'ใบขอเบิกสินค้า / Material Requisition';
        const titleEn = opts.titleEn || 'General Services Dept.';
        const session = opts.session || (global.SESSION || {});
        const company = opts.getCompanyFn ? opts.getCompanyFn() : (session.company || 'PTA');
        const name    = session.display_name || session.username || '-';
        const dept    = session.primary_department || '-';

        if (!global._coInfoCache) global._coInfoCache = {};
        if (!global._coInfoCache[company]) {
            try {
                const r = await fetch('api.php?action=get_company_info');
                const j = await r.json();
                if (j.status === 'success') global._coInfoCache = j.data;
            } catch (e) { /* silent */ }
        }

        const coInfo   = (global._coInfoCache && global._coInfoCache[company]) || {};
        const palette  = CO_PALETTE[company] || CO_PALETTE.PTE;
        const logoUrl  = CO_LOGOS[company]   || CO_LOGOS.PTA;
        const coNameEn = CO_NAMES_EN[company] || company;
        const coNameTh = CO_NAMES_TH[company] || company;
        const _addr    = coInfo.address_th || coInfo.address_en || '';
        const addrLine = _addr ? `<div style="font-size:.6rem;color:#64748b;margin-top:1px;">${_addr}</div>` : '';
        const phoneLine = (coInfo.phone || coInfo.fax)
            ? `<div style="font-size:.6rem;color:#64748b;">${coInfo.phone ? 'โทร.&nbsp;' + coInfo.phone : ''}${coInfo.phone && coInfo.fax ? '&nbsp;|&nbsp;' : ''}${coInfo.fax ? 'Fax&nbsp;' + coInfo.fax : ''}</div>`
            : '';

        const d = new Date();
        const dateStr = d.toLocaleDateString('th-TH', { year: 'numeric', month: 'long', day: 'numeric' });
        const yy = String(d.getFullYear()).slice(-2);
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const jobNum = `${company}-${yy}${mm}-${String(requestId).padStart(3, '0')}`;
        const qrUrl  = `https://api.qrserver.com/v1/create-qr-code/?size=60x60&data=${encodeURIComponent(location.href)}`;

        const rowsHtml = items.map((it, i) => `
        <tr style="border-bottom:1px solid #e2e8f0;">
            <td style="text-align:center;padding:5px 8px;font-size:.75rem;">${i + 1}</td>
            <td style="padding:5px 8px;font-size:.75rem;">${it.item_code || '-'}</td>
            <td style="padding:5px 8px;font-size:.775rem;">${it.name || '-'}</td>
            <td style="text-align:center;padding:5px 8px;font-size:.75rem;">${it.unit || '-'}</td>
            <td style="text-align:center;padding:5px 8px;font-size:.75rem;">${it.qty}</td>
            <td style="padding:5px 8px;"></td>
        </tr>`).join('');

        const sigBox = (title, val) => `<div style="border:1px solid #e2e8f0;border-radius:5px;padding:8px 10px 6px;text-align:center;background:#fafafa;"><div style="font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:24px;">${title}</div><div style="border-bottom:1.5px solid #cbd5e1;margin:0 8px 5px;"></div><div style="font-size:.67rem;color:#64748b;">( ${val || '................................'} )</div><div style="font-size:.62rem;color:#94a3b8;margin-top:1px;">วันที่ ........./........./.........&nbsp;</div></div>`;

        const win = window.open('', '_blank', 'width=820,height=1050');
        if (!win) { Swal.fire('แจ้งเตือน', 'กรุณาอนุญาต Popup ในเบราว์เซอร์เพื่อพิมพ์ใบเบิก', 'warning'); return; }

        win.document.write(`<!DOCTYPE html><html lang="th"><head>
<meta charset="UTF-8">
<title>ใบขอเบิกสินค้า ${jobNum}</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Sarabun',sans-serif;font-size:12.5px;color:#1e293b;background:#fff;padding:28px 36px 32px;line-height:1.5;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}
@page{size:A4 portrait;margin:1cm}
@media print{.btn-bar{display:none!important}}
</style></head><body>

<div style="display:grid;grid-template-columns:1fr auto;align-items:start;gap:10px;border-bottom:2.5px solid ${palette.accent};padding-bottom:12px;margin-bottom:10px;">
  <div style="display:flex;align-items:center;gap:10px;border-left:4px solid ${palette.accent};padding-left:12px;">
    <img src="${logoUrl}" alt="${company}" style="height:52px;width:auto;object-fit:contain;flex-shrink:0;" onerror="this.style.display='none'">
    <div>
      <div style="font-size:1.05rem;font-weight:800;color:#1e293b;letter-spacing:-.01em;">${coNameEn}</div>
      <div style="font-size:.72rem;color:#475569;margin-top:2px;font-weight:500;">${coNameTh}</div>
      ${addrLine}${phoneLine}
    </div>
  </div>
  <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:3px;">
      <div style="font-size:.6rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;">เลขที่ใบเบิก / Doc No.</div>
      <div style="font-size:.9375rem;font-weight:700;color:${palette.accent};">${jobNum}</div>
      <div style="font-size:.68rem;color:#64748b;">วันที่: ${dateStr}</div>
    </div>
    <table style="border:1px solid #e2e8f0;border-radius:5px;font-size:.6rem;border-collapse:collapse;">
      <tr><td style="padding:2px 7px;background:#f1f5f9;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;border:1px solid #e2e8f0;">Form No.</td><td style="padding:2px 7px;border:1px solid #e2e8f0;color:#334155;font-weight:600;">${formNo}</td></tr>
      <tr><td style="padding:2px 7px;background:#f1f5f9;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;border:1px solid #e2e8f0;">Revision</td><td style="padding:2px 7px;border:1px solid #e2e8f0;color:#dc2626;font-weight:600;">Rev. 01</td></tr>
      <tr><td style="padding:2px 7px;background:#f1f5f9;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;border:1px solid #e2e8f0;">Eff. Date</td><td style="padding:2px 7px;border:1px solid #e2e8f0;color:#334155;font-weight:600;">01/01/2569</td></tr>
    </table>
    <div style="display:flex;flex-direction:column;align-items:center;gap:3px;">
      <img src="${qrUrl}" style="width:60px;height:60px;border:1px solid #e2e8f0;border-radius:4px;padding:2px;">
      <span style="font-size:.55rem;color:#94a3b8;">Scan เพื่อดูสถานะ</span>
    </div>
  </div>
</div>

<div style="background:${palette.grad};color:#fff;text-align:center;padding:7px 16px;border-radius:5px;margin-bottom:12px;-webkit-print-color-adjust:exact;print-color-adjust:exact;">
  <div style="font-size:.9375rem;font-weight:700;letter-spacing:.02em;">${titleTh}</div>
  <div style="font-size:.67rem;opacity:.85;margin-top:1px;">${titleEn} | ${formNo} Rev.01</div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;border:1px solid #e2e8f0;border-radius:5px;overflow:hidden;margin-bottom:12px;">
  <div style="display:flex;align-items:center;padding:6px 10px;border-bottom:1px solid #e2e8f0;border-right:1px solid #e2e8f0;gap:7px;"><span style="font-size:.62rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;min-width:82px;">ผู้ขอเบิก</span><span style="font-size:.775rem;color:#1e293b;border-bottom:1px dashed #cbd5e1;flex:1;min-height:17px;">${name}</span></div>
  <div style="display:flex;align-items:center;padding:6px 10px;border-bottom:1px solid #e2e8f0;gap:7px;"><span style="font-size:.62rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;min-width:82px;">วันที่</span><span style="font-size:.775rem;color:#1e293b;border-bottom:1px dashed #cbd5e1;flex:1;min-height:17px;">${dateStr}</span></div>
  <div style="display:flex;align-items:center;padding:6px 10px;border-right:1px solid #e2e8f0;gap:7px;"><span style="font-size:.62rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;min-width:82px;">แผนก</span><span style="font-size:.775rem;color:#1e293b;border-bottom:1px dashed #cbd5e1;flex:1;min-height:17px;">${dept}</span></div>
  <div style="display:flex;align-items:center;padding:6px 10px;gap:7px;"><span style="font-size:.62rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;min-width:82px;">บริษัท</span><span style="font-size:.775rem;color:#1e293b;border-bottom:1px dashed #cbd5e1;flex:1;min-height:17px;">${coNameEn}</span></div>
</div>

<div style="border:1px solid #e2e8f0;border-radius:5px;overflow:hidden;margin-bottom:10px;">
  <div style="background:${palette.grad};color:#fff;padding:5px 10px;font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;-webkit-print-color-adjust:exact;print-color-adjust:exact;">รายการสินค้า / Item List</div>
  <table style="width:100%;border-collapse:collapse;">
    <thead><tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
      <th style="text-align:center;padding:5px 8px;font-size:.65rem;font-weight:700;color:#64748b;text-transform:uppercase;width:36px;border-right:1px solid #e2e8f0;">ลำดับ</th>
      <th style="padding:5px 8px;font-size:.65rem;font-weight:700;color:#64748b;text-transform:uppercase;width:100px;border-right:1px solid #e2e8f0;">รหัสสินค้า</th>
      <th style="padding:5px 8px;font-size:.65rem;font-weight:700;color:#64748b;text-transform:uppercase;border-right:1px solid #e2e8f0;">ชื่อรายการ</th>
      <th style="text-align:center;padding:5px 8px;font-size:.65rem;font-weight:700;color:#64748b;text-transform:uppercase;width:56px;border-right:1px solid #e2e8f0;">หน่วย</th>
      <th style="text-align:center;padding:5px 8px;font-size:.65rem;font-weight:700;color:#64748b;text-transform:uppercase;width:72px;border-right:1px solid #e2e8f0;">จำนวนขอ</th>
      <th style="text-align:center;padding:5px 8px;font-size:.65rem;font-weight:700;color:#64748b;text-transform:uppercase;width:72px;">จำนวนจ่าย</th>
    </tr></thead>
    <tbody>${rowsHtml}</tbody>
  </table>
</div>
${notes ? `<div style="border:1px solid #fde68a;border-left:3px solid #d97706;border-radius:5px;padding:8px 10px;margin-bottom:10px;background:#fffbeb;"><div style="font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:4px;">หมายเหตุ / Notes</div><div style="font-size:.775rem;color:#1e293b;">${notes}</div></div>` : ''}

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:20px;">
  ${sigBox('ผู้ขอเบิก / Requester', name)}
  ${sigBox('หัวหน้าแผนก / Supervisor', '')}
  ${sigBox('ผู้จ่ายของ / Issuer', '')}
</div>

<div style="margin-top:16px;border-top:1px solid #e2e8f0;padding-top:6px;display:flex;justify-content:space-between;align-items:center;">
  <div style="font-size:.6rem;color:#94a3b8;">ออกโดยระบบ Powertech System Center · พิมพ์: ${dateStr}</div>
  <div style="font-size:.6rem;color:#94a3b8;text-align:right;">${formNo} Rev.01 · หน้า 1/1 · ${jobNum}</div>
</div>

<div class="btn-bar" style="margin-top:20px;display:flex;gap:10px;justify-content:center;">
  <button style="padding:8px 24px;border-radius:8px;border:none;font-family:'Sarabun',sans-serif;font-size:13px;cursor:pointer;font-weight:600;background:${palette.accent};color:#fff;" onclick="window.print()">🖨 พิมพ์</button>
  <button style="padding:8px 24px;border-radius:8px;border:none;font-family:'Sarabun',sans-serif;font-size:13px;cursor:pointer;font-weight:600;background:#f1f5f9;color:#333;" onclick="window.close()">ปิด</button>
</div>
</body></html>`);
        win.document.close();
    }

    global.StockPrintUI = { openPrintSlip };
})(window);
