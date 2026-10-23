/**
 * stock-print.js — ใบพิมพ์ระบบเบิกของ (หน้าตามาตรฐานจาก js/doc-print.js ต้องโหลดก่อนไฟล์นี้)
 *
 *   StockPrint.issue(request)          ใบเบิกวัสดุ — แถวจาก stock_get / stock_my / stock_board
 *   StockPrint.receive(result)         ใบรับของเข้าคลัง — ผลจาก stock_receive
 *   StockPrint.countSheet(items, co)   ใบนับสต็อก (ช่องว่างให้กรอกยอดนับ) — แถวจาก stock_inventory
 */
(function () {
    const DEPT = 'ฝ่ายธุรการ / คลังวัสดุ';
    const baht = n => '฿' + Number(n || 0).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = n => Number(n || 0).toLocaleString('th-TH');
    const ST = { pending: 'รออนุมัติ', approved: 'กำลังจัดของ', ready: 'พร้อมรับ', issued: 'จ่ายแล้ว', rejected: 'ไม่อนุมัติ', cancelled: 'ยกเลิก' };
    const base = () => location.origin + location.pathname.replace(/\/[^/]*$/, '/');

    function issueSpec(r) {
        const { esc, thDate, section } = DocPrint;
        const given = ['approved', 'ready', 'issued'].includes(r.status);
        let total = 0;
        const rows = r.items.map((i, n) => {
            const q = given ? i.qty_approved : i.qty_requested, v = q * i.price_per_unit; total += v;
            return `<tr><td style="text-align:center">${n + 1}</td><td style="white-space:nowrap;font-size:.92em">${esc(i.item_code || '')}</td><td>${esc(i.name)}</td><td style="text-align:right">${num(i.qty_requested)}</td>
                <td style="text-align:right;font-weight:700">${given ? num(i.qty_approved) : '-'}</td><td>${esc(i.unit)}</td><td style="text-align:right">${baht(i.price_per_unit)}</td><td style="text-align:right">${baht(v)}</td></tr>`;
        }).join('');
        const tbl = `<table class="dp-tbl"><tr><th style="width:22px">#</th><th style="width:108px">รหัส</th><th>รายการ</th><th style="width:44px">ขอ</th><th style="width:44px">จ่าย</th><th style="width:52px">หน่วย</th><th style="width:72px">ราคา/หน่วย</th><th style="width:82px">มูลค่า</th></tr>${rows}
            <tr><td colspan="7" style="text-align:right;font-weight:700">รวม</td><td style="text-align:right;font-weight:800">${baht(total)}</td></tr></table>`;
        const sigImg = p => p ? `<img class="sig-img" src="${esc(base() + p)}" alt="">` : '';
        const blocks = [section('รายการ / ITEMS', tbl, { html: true })];
        if (r.notes) blocks.push(section('หมายเหตุ / REMARKS', esc(r.notes), { clamp: 3 }));
        if (r.status === 'rejected' || r.status === 'cancelled') blocks.push(section('เหตุผล / REASON', esc(r.deny_reason || r.closed_reason || '-'), { cls: 'bad' }));
        return {
            company: r.company, formKey: 'stock_issue', docNo: r.code, dept: DEPT,
            dateText: 'ขอเบิกเมื่อ ' + thDate(r.request_date), statusText: ST[r.status] || r.status,
            title: { th: 'ใบเบิกวัสดุ', en: 'Material Requisition', color: '#0891b2' },
            info: [['ผู้เบิก', r.requester_name], ['แผนก', r.department], ['ประเภท', r.type_th], ['บริษัท', r.company],
                ['อนุมัติโดย', r.approved_by && r.status !== 'rejected' ? `${r.approved_by} · ${thDate(r.approved_at, true)}` : '-'], ['จ่ายของโดย', r.issued_by ? `${r.issued_by} · ${thDate(r.issued_at, true)}` : '-']],
            blocks,
            sigs: [{ role: 'ผู้เบิก (รับของ) / Received by', name: r.requester_name, date: r.issued_at ? thDate(r.issued_at) : '', stamp: sigImg(r.receiver_sig) },
                { role: 'ผู้อนุมัติ / Approved by', name: r.status === 'rejected' ? '' : r.approved_by, date: r.approved_at && r.status !== 'rejected' ? thDate(r.approved_at) : '' },
                { role: 'ผู้จ่ายของ / Issued by', name: r.issued_by, date: r.issued_at ? thDate(r.issued_at) : '' }],
            qr: base() + 'stock_request.html?tab=mine',
        };
    }

    function receiveSpec(x) {
        const { esc, thDate, section } = DocPrint;
        let total = 0;
        const rows = x.rows.map((i, n) => {
            const v = i.qty * i.cost; total += v;
            return `<tr><td style="text-align:center">${n + 1}</td><td>${esc(i.name)}</td><td style="text-align:right">${i.pack_unit ? `${num(i.entered)} ${esc(i.pack_unit)}` : '-'}</td>
                <td style="text-align:right;font-weight:700">${num(i.qty)} ${esc(i.unit)}</td><td style="text-align:right">${i.cost ? baht(i.cost) : '-'}</td><td style="text-align:right">${i.cost ? baht(v) : '-'}</td><td>${i.expiry ? thDate(i.expiry) : ''}</td></tr>`;
        }).join('');
        const tbl = `<table class="dp-tbl"><tr><th style="width:22px">#</th><th>รายการ</th><th style="width:80px">รับเป็นแพ็ค</th><th style="width:90px">เข้าคลัง</th><th style="width:72px">ทุน/หน่วย</th><th style="width:82px">มูลค่า</th><th style="width:72px">หมดอายุ</th></tr>${rows}
            <tr><td colspan="5" style="text-align:right;font-weight:700">รวม</td><td style="text-align:right;font-weight:800">${baht(total)}</td><td></td></tr></table>`;
        return {
            company: x.company, formKey: 'stock_receive', docNo: x.ref, dept: DEPT,
            dateText: 'รับเมื่อ ' + thDate(new Date(), true),
            title: { th: 'ใบรับของเข้าคลัง', en: 'Goods Received Note', color: '#16a34a' },
            info: [['ผู้ขาย / ร้าน', x.supplier], ['เลขที่ใบส่งของ', x.doc_no], ['บริษัท', x.company], ['ผู้รับเข้าคลัง', x.by]],
            blocks: [section('รายการที่รับ / ITEMS RECEIVED', tbl, { html: true })],
            sigs: [{ role: 'ผู้ส่งของ / Delivered by', name: '' }, { role: 'ผู้รับของ / Received by', name: x.by, date: thDate(new Date()) }, { role: 'ผู้ตรวจสอบ / Checked by', name: '' }],
        };
    }

    function countSpec(items, co, withSystem) {
        const { esc } = DocPrint;
        const rows = items.map((i, n) => `<tr><td style="text-align:center">${n + 1}</td><td>${esc(i.item_code)}</td><td>${esc(i.name)}</td><td>${esc(i.unit)}${i.pack_size > 1 ? ` <small style="color:#64748b">(${esc(i.pack_unit)}ละ ${i.pack_size})</small>` : ''}</td>
            ${withSystem ? `<td style="text-align:right">${num(i.balance)}</td>` : ''}<td style="width:70px"></td><td style="width:60px"></td><td style="width:110px"></td></tr>`).join('');
        return {
            company: co, formKey: 'stock_count', docNo: 'CNT-' + new Date().toISOString().slice(0, 10).replace(/-/g, ''), dept: DEPT, flow: true,
            dateText: 'วันที่นับ ......./......./.......',
            title: { th: 'ใบนับสต็อก', en: 'Physical Stock Count', color: '#7c3aed' },
            info: [['บริษัท', co], ['จำนวนรายการ', items.length + ' รายการ']],
            blocks: [`<table class="dp-tbl"><thead><tr><th style="width:22px">#</th><th style="width:92px">รหัส</th><th>รายการ</th><th style="width:110px">หน่วย</th>${withSystem ? '<th style="width:56px">ยอดระบบ</th>' : ''}<th>นับได้</th><th>ต่าง</th><th>หมายเหตุ</th></tr></thead><tbody>${rows}</tbody></table>`],
            sigs: [{ role: 'ผู้นับ / Counted by', name: '' }, { role: 'ผู้ตรวจสอบ / Verified by', name: '' }],
        };
    }

    window.StockPrint = {
        issue: r => DocPrint.preview(issueSpec(r)),
        receive: x => DocPrint.preview(receiveSpec(x)),
        countSheet: (items, co, withSystem) => DocPrint.preview(countSpec(items, co, withSystem)),
        issueSpec, receiveSpec, countSpec,
    };
})();
