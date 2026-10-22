/**
 * loan-print.js — ใบยืม-คืนอุปกรณ์ (A4 แผ่นเดียว) · หน้าตามาตรฐานจาก js/doc-print.js (ต้องโหลดก่อนไฟล์นี้)
 * ใช้ทั้งหน้าพนักงาน (loan.html) และหน้า IT (borrow_management.html)
 *
 *   LoanPrint.preview(loan)   — loan = แถวจาก loan_get / loan_my / loan_board
 */
(function () {
    const COND = { ok: 'ปกติ', minor: 'มีรอย / ชำรุดเล็กน้อย', broken: 'เสีย ต้องซ่อม' };
    const ST = { requested: 'รออนุมัติ', approved: 'อนุมัติแล้ว รอรับของ', active: 'กำลังยืม', returned: 'คืนแล้ว', rejected: 'ไม่อนุมัติ', cancelled: 'ยกเลิก' };

    function spec(l) {
        const { esc, thDate, section } = DocPrint;
        const base = location.origin + location.pathname.replace(/\/[^/]*$/, '/');
        const returned = l.status === 'returned';
        const items = list => (list || []).length
            ? `<table class="dp-tbl">${list.map((i, n) => `<tr><td style="width:18px;text-align:center">${n + 1}</td><td>${esc(i.name)}</td><td style="width:70px;text-align:center;font-weight:700;color:${i.ok ? '#16a34a' : '#dc2626'}">${i.ok ? '✓ มี' : '✗ ไม่มี'}</td></tr>`).join('')}</table>`
            : '<div style="color:#94a3b8">— ไม่มีรายการของในชุด —</div>';
        const info = [
            ['ผู้ยืม', l.borrower_name], ['แผนก', l.department],
            ['อุปกรณ์', l.equipment_name], ['รหัสพัสดุ', l.equipment_id],
            ['ช่วงยืม', thDate(l.start_date) + ' – ' + (l.end_date ? thDate(l.end_date) : 'ไม่มีกำหนดคืน'), true], ['ติดต่อ', l.contact],
            ['อนุมัติโดย', l.decided_by], ['ส่งมอบโดย', l.handover_by ? `${l.handover_by} · ${thDate(l.handover_at, true)}` : '-'],
        ];
        if (returned) info.push(['รับคืนโดย', `${l.return_by} · ${thDate(l.return_at, true)}`], ['สภาพตอนคืน', COND[l.return_condition] || l.return_condition]);
        const blocks = [section('วัตถุประสงค์ / PURPOSE', esc(l.purpose || '-'), { clamp: 3 }),
            section('ของในชุดตอนส่งมอบ / ITEMS OUT', items(l.handover_items), { html: true })];
        if (returned) {
            blocks.push(section('ของในชุดตอนรับคืน / ITEMS IN', items(l.return_items), { html: true }));
            blocks.push(section('ผลการรับคืน / RETURN CHECK', `สภาพ: <b>${esc(COND[l.return_condition] || '-')}</b> · ล้างข้อมูลผู้ยืมแล้ว: <b>${+l.wiped ? '✓ ใช่' : '✗ ไม่'}</b>${l.return_notes ? '<br>หมายเหตุ: ' + esc(l.return_notes) : ''}`, { html: true, cls: l.return_condition === 'broken' ? 'bad' : 'ok' }));
        }
        const sigImg = p => p ? `<img class="sig-img" src="${esc(base + p)}" alt="">` : '';
        const sigs = [{ role: 'ผู้ยืม (รับของ) / Borrower', name: l.borrower_name, date: thDate(l.handover_at), stamp: sigImg(l.handover_sig) },
            { role: 'ฝ่าย IT (ส่งมอบ) / IT Staff', name: l.handover_by, date: thDate(l.handover_at) }];
        if (returned) sigs.push({ role: 'ผู้คืน / Returned by', name: l.borrower_name, date: thDate(l.return_at), stamp: sigImg(l.return_sig) }, { role: 'ฝ่าย IT (รับคืน) / IT Staff', name: l.return_by, date: thDate(l.return_at) });
        return {
            company: l.asset_company, formKey: 'loan', docNo: l.code,
            dateText: 'ขอยืมเมื่อ ' + thDate(l.created_at), statusText: ST[l.status] || l.status,
            title: { th: returned ? 'ใบยืม-คืนอุปกรณ์' : 'ใบยืมอุปกรณ์', en: returned ? 'Equipment Loan & Return' : 'Equipment Loan', color: '#ea580c' },
            info, blocks, sigs, qr: base + 'loan.html?tab=mine',
        };
    }
    window.LoanPrint = { spec, preview: l => DocPrint.preview(spec(l)), print: l => DocPrint.print(spec(l)) };
})();
