/**
 * ticket-print.js — ใบแจ้งซ่อม / ใบงานซ่อม / ใบปิดงาน / ใบยกเลิก (A4 แผ่นเดียว)
 * ใช้ร่วมกันทั้งหน้าช่าง (log.html), หน้าผู้ใช้ (request.html) และหน้าทะเบียนพัสดุ (admin.html)
 * หน้าตามาตรฐานจาก js/doc-print.js + css/doc-print.css (ต้องโหลด doc-print.js ก่อนไฟล์นี้)
 *
 *   TicketPrint.print(log, { includeImages:true })   TicketPrint.preview(log, …)   TicketPrint.spec(log, …)
 */
(function () {
    const STATUS_TH = {
        'Pending': 'รอรับเรื่อง', 'Received': 'รับเรื่องแล้ว', 'In Progress': 'กำลังซ่อม', 'Waiting for Parts': 'รออะไหล่',
        'Waiting Parts': 'รออะไหล่', 'Parts Received': 'ได้อะไหล่แล้ว', 'Waiting for User': 'รอผู้แจ้งตอบกลับ',
        'Sent to Vendor': 'ส่งซ่อมภายนอก', 'Temporary Fix': 'แก้ชั่วคราว', 'PR Submitted': 'ขอซื้อ (PR)', 'PO Issued': 'สั่งซื้อ (PO)',
        'Awaiting Confirmation': 'รอผู้แจ้งยืนยัน', 'Completed': 'เสร็จสิ้น', 'Unrepairable': 'ซ่อมไม่ได้', 'Cancelled': 'ยกเลิก',
    };
    const DOC = {
        request:    { th: 'ใบแจ้งซ่อม',  en: 'Service Request',            color: '#2563eb' },
        workorder:  { th: 'ใบงานซ่อม',   en: 'Work Order',                 color: '#d97706' },
        completion: { th: 'ใบปิดงานซ่อม', en: 'Job Completion Certificate', color: '#059669' },
        cancelled:  { th: 'ใบยกเลิกงาน', en: 'Cancellation Notice',        color: '#64748b' },
    };
    const WORK = ['Received', 'In Progress', 'Waiting for User', 'Sent to Vendor', 'Temporary Fix', 'PR Submitted', 'PO Issued', 'Waiting for Parts', 'Waiting Parts', 'Parts Received', 'Awaiting Confirmation'];

    const duration = (a, b) => {
        if (!a || !b) return '-';
        let m = Math.round((new Date(String(b).replace(' ', 'T')) - new Date(String(a).replace(' ', 'T'))) / 60000);
        if (!(m > 0)) return '-';
        const d = Math.floor(m / 1440); m -= d * 1440;
        const h = Math.floor(m / 60); m -= h * 60;
        return [d && d + ' วัน', h && h + ' ชม.', m && m + ' นาที'].filter(Boolean).join(' ') || '-';
    };

    async function images(id) {
        if (!id) return [];
        try {
            const files = await (await fetch(`api.php?action=get&file=it_log_attachments&log_id=${encodeURIComponent(id)}&v=${Date.now()}`)).json();
            return Array.isArray(files) ? files.filter(f => /\.(jpe?g|png|gif|webp)$/i.test(f.stored_filename || '')) : [];
        } catch (_) { return []; }
    }

    async function spec(log, opts) {
        const o = Object.assign({ includeImages: true }, opts || {});
        const { esc, thDate, section } = DocPrint;
        // ข้อความงาน IT มีแท็กระบบปน เช่น [Reopened …] — ขึ้นบรรทัดใหม่ให้อ่านง่าย
        const text = v => esc(String(v || '').trim()).replace(/\s*(\[(?:Reopened|CANCELLED)[^\]]*\])/g, '\n$1');
        const docType = log.status === 'Pending' ? 'request' : log.status === 'Cancelled' ? 'cancelled' : WORK.includes(log.status) ? 'workorder' : 'completion';
        const doc = DOC[docType];
        const base = location.origin + location.pathname.replace(/\/[^/]*$/, '/');
        const imgs = o.includeImages ? await images(log.id) : [];

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

        const blocks = [section('ปัญหา / อาการที่แจ้ง · PROBLEM', text(log.problem) || '-', { clamp: 7 })];
        if (docType === 'workorder') blocks.push(section('บันทึกการซ่อม / WORK NOTES', text(solution), { lined: true, clamp: 6, height: '32mm' }));
        else if (docType === 'completion') {
            blocks.push(section('วิธีแก้ไข / SOLUTION', text(solution) || '-', { cls: 'ok', clamp: 7 }));
            if (log.root_cause) blocks.push(section('สาเหตุ / ROOT CAUSE', text(log.root_cause), { clamp: 3 }));
        } else if (docType === 'cancelled') blocks.push(section('เหตุผลที่ยกเลิก / REASON', text(cancelReason) || '-', { cls: 'bad', clamp: 5 }));

        // ผู้แจ้งยืนยันผ่านระบบแล้ว → ตรา/ลายเซ็นดิจิทัล (ไม่ต้องเซ็นกระดาษซ้ำ)
        const confirmed = docType === 'completion' && Number(log.user_confirmed) === 1;
        const stamp = confirmed ? (/^data:image\//.test(String(log.user_signature || '')) ? `<img class="sig-img" src="${esc(log.user_signature)}" alt="">` : '<div class="dp-stamp">✓ ยืนยันผ่านระบบแล้ว</div>') : '';
        const sigs = docType === 'request' ? [{ role: 'ผู้แจ้ง / Requester', name: log.requester, date: thDate(log.date) }]
            : docType === 'cancelled' ? [{ role: 'ผู้แจ้ง / Requester', name: log.requester }, { role: 'ฝ่าย IT / IT Staff', name: log.servicedBy, date: thDate(log.closed_date || log.date) }]
            : docType === 'workorder' ? [{ role: 'ผู้แจ้ง / Requester', name: log.requester }, { role: 'ช่างผู้ซ่อม / Technician', name: log.servicedBy }]
            : [{ role: 'ผู้แจ้ง / Requester', name: log.user_confirmed_by || log.requester, date: confirmed ? thDate(log.user_confirmed_at) : '', stamp },
               { role: 'ช่างผู้ซ่อม / Technician', name: log.servicedBy, date: thDate(log.completed_date || log.closed_date) },
               { role: 'หัวหน้าแผนก / Head of Dept.' }];

        return {
            company: log.company, formKey: 'repair', docNo: log.id || 'ยังไม่บันทึก',
            dateText: 'แจ้งเมื่อ ' + thDate(log.date), statusText: STATUS_TH[log.status] || log.status,
            title: doc, info, blocks, sigs,
            images: imgs.map(f => base + 'uploads/attachments/' + f.stored_filename),
            qr: base + 'request.html?tab=tickets',
        };
    }

    window.TicketPrint = {
        spec,
        build: async (log, o) => DocPrint.build(await spec(log, o)),
        print: async (log, o) => DocPrint.print(await spec(log, o)),
        preview: async (log, o) => DocPrint.preview(await spec(log, o)),
    };
})();
