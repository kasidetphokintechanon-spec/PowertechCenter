/**
 * Stock Management UI — scope clarity, breadcrumbs, report print helpers
 */
(function (global) {
    'use strict';

    const CO_LABELS = { PTA: 'PTA', PT4: 'PT4', PTE: 'PTE' };
    const TYPE_LABELS = { stationery: 'เครื่องเขียน', maid: 'แม่บ้าน' };
    const STORAGE_KEY = 'ptc_stock_scope_v1';

    let hooks = {};
    let reportMeta = null;
    let reportItems = [];

    function fmtMoney(n) {
        if (typeof global.fmtMoney === 'function') return global.fmtMoney(n);
        const v = parseFloat(n);
        if (isNaN(v)) return '0.00';
        return v.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function setReportMeta(meta, items) {
        reportMeta = meta || null;
        if (items !== undefined) {
            reportItems = Array.isArray(items) ? items : [];
        }
    }

    function escHtml(s) {
        if (typeof global.escHtml === 'function') return global.escHtml(s);
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function fmtDateFull(s) {
        if (typeof global.fmtDateFull === 'function') return global.fmtDateFull(s);
        if (!s) return '-';
        const d = new Date(s);
        return d.toLocaleDateString('th-TH', { year: 'numeric', month: 'short', day: 'numeric' }) +
            ' ' + d.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
    }

    function saveScope(company, type) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ company, type }));
        } catch (e) { /* ignore */ }
    }

    function loadScope() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function getRepTabLabel() {
        const tab = global.REP_TAB || 'monthly';
        const map = {
            monthly: 'รายงานรายเดือน',
            txlog: 'ประวัติรายการ',
            requests: 'ประวัติคำขอ',
            stats: 'สถิติ'
        };
        return map[tab] || tab;
    }

    function updateBreadcrumb() {
        const el = document.getElementById('stock-breadcrumb');
        if (!el || !hooks.companyFilter || !hooks.typeFilter) return;

        const co = hooks.companyFilter.value;
        const type = hooks.typeFilter.value;
        const view = global.currentView || 'dashboard';
        const viewTitle = (global.VIEW_TITLES && global.VIEW_TITLES[view]) || view;

        const parts = [
            '<strong>Stock Center</strong>',
            `<strong>${escHtml(CO_LABELS[co] || co)}</strong>`,
            `<strong>${escHtml(TYPE_LABELS[type] || type)}</strong>`,
            `<span>${escHtml(viewTitle)}</span>`
        ];

        if (view === 'stock' && global.currentStockTab) {
            const st = (global.STK_TITLES && global.STK_TITLES[global.currentStockTab]) || global.currentStockTab;
            parts.push(`<span>${escHtml(st)}</span>`);
        }
        if (view === 'report') {
            parts.push(`<span>${escHtml(getRepTabLabel())}</span>`);
        }

        el.innerHTML = parts.join('<span class="stock-breadcrumb-sep">›</span>');
    }

    function updateTopbarScope() {
        if (!hooks.companyFilter || !hooks.typeFilter) return;

        const co = hooks.companyFilter.value;
        const type = hooks.typeFilter.value;

        const qCo = document.getElementById('quota-company-filter');
        const qType = document.getElementById('quota-type-filter');
        if (qCo && qCo.value !== co) qCo.value = co;
        if (qType && qType.value !== type) qType.value = type;
    }

    function updateScopePills() {
        if (!hooks.companyFilter || !hooks.typeFilter) return;
        const co = hooks.companyFilter.value;
        const type = hooks.typeFilter.value;

        document.querySelectorAll('.stock-scope-pill[data-co]').forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.co === co);
        });
        document.querySelectorAll('.stock-scope-pill[data-type]').forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.type === type);
        });

        const warn = document.getElementById('stock-scope-warning');
        if (warn) {
            const needsCo = co === 'All' && global.currentView !== 'dashboard';
            warn.classList.toggle('is-visible', needsCo);
        }

        updateBreadcrumb();
        updateTopbarScope();
    }

    function pickCompany(co) {
        if (!hooks.companyFilter) return;
        if (hooks.companyFilter.value === co) return;
        hooks.companyFilter.value = co;
        if (typeof global.applyCompanyTheme === 'function') global.applyCompanyTheme(co);
        saveScope(co, hooks.typeFilter.value);
        updateScopePills();
        if (typeof global.closeSidePanel === 'function') global.closeSidePanel();
        if (typeof global.onFilterChange === 'function') global.onFilterChange();
    }

    function pickType(type) {
        if (!hooks.typeFilter) return;
        if (hooks.typeFilter.value === type) return;
        hooks.typeFilter.value = type;
        if (typeof global.setTypeFilter === 'function') {
            global.setTypeFilter(type);
        } else if (typeof global.updateTypeToggleUI === 'function') {
            global.updateTypeToggleUI();
            if (typeof global.onFilterChange === 'function') global.onFilterChange();
        }
        saveScope(hooks.companyFilter.value, type);
        updateScopePills();
    }

    function mountScopeBar() {
        const slot = document.getElementById('stock-scope-inline');
        if (!slot || slot.dataset.mounted) return;
        slot.dataset.mounted = '1';

        slot.innerHTML = `
            <div class="stock-scope-pills" id="scope-co-pills">
                <button type="button" class="stock-scope-pill co-PTA" data-co="PTA" onclick="StockMgmtUI.pickCompany('PTA')">PTA</button>
                <button type="button" class="stock-scope-pill co-PT4" data-co="PT4" onclick="StockMgmtUI.pickCompany('PT4')">PT4</button>
                <button type="button" class="stock-scope-pill co-PTE" data-co="PTE" onclick="StockMgmtUI.pickCompany('PTE')">PTE</button>
            </div>
            <div class="stock-scope-sep" aria-hidden="true"></div>
            <div class="stock-scope-pills" id="scope-type-pills">
                <button type="button" class="stock-scope-pill type-stationery" data-type="stationery" onclick="StockMgmtUI.pickType('stationery')">📎 เครื่องเขียน</button>
                <button type="button" class="stock-scope-pill type-maid" data-type="maid" onclick="StockMgmtUI.pickType('maid')">🧹 แม่บ้าน</button>
            </div>
        `;

        const topbar = document.getElementById('topbar');
        if (topbar && !document.getElementById('stock-scope-warning')) {
            const warn = document.createElement('div');
            warn.id = 'stock-scope-warning';
            warn.className = 'stock-scope-warning';
            warn.innerHTML = '<i class="fas fa-triangle-exclamation"></i><span>เลือกบริษัท (PTA / PT4 / PTE) ก่อนบันทึกหรืออนุมัติ — ตอนนี้ดูได้อย่างเดียว</span>';
            topbar.insertAdjacentElement('afterend', warn);
        }
    }

    function initScopeDefaults(session) {
        const saved = loadScope();
        let co = saved?.company;
        let type = saved?.type || 'stationery';

        if (session?.role === 'admin') {
            if (!co || co === 'All') co = 'PTA';
        } else if (!co || co === 'All') {
            // saved scope has priority; fall back to session.company then PTA
            co = (session?.company && CO_LABELS[session.company]) ? session.company : 'PTA';
        }

        if (hooks.companyFilter) hooks.companyFilter.value = co;
        if (hooks.typeFilter) hooks.typeFilter.value = type;
        if (typeof global.applyCompanyTheme === 'function') global.applyCompanyTheme(co);
        if (typeof global.updateTypeToggleUI === 'function') global.updateTypeToggleUI();
        saveScope(co, type);
        updateScopePills();
        updateTopbarScope();
    }

    async function openReqDetail(requestId) {
        const row = (global.REP_REQ_DATA || []).find(r => parseInt(r.id, 10) === parseInt(requestId, 10));
        if (!row) return;

        document.querySelectorAll('#rep-req-tbody tr.req-row-selected').forEach(tr => tr.classList.remove('req-row-selected'));
        const tr = document.querySelector(`#rep-req-tbody tr[data-req-id="${requestId}"]`);
        if (tr) tr.classList.add('req-row-selected');

        let items = [];
        try {
            const res = await fetch(`api.php?action=getRequestItems&request_id=${requestId}`);
            items = await res.json();
        } catch (e) {
            items = [];
        }

        const sb = (global.REQ_BADGE && global.REQ_BADGE[row.status]) || { label: row.status };
        const itemsHtml = items.length
            ? `<table style="width:100%;font-size:12px;border-collapse:collapse;margin-top:8px;">
                <tr style="background:#f1f5f9;"><th style="padding:6px;text-align:left;">รหัส</th><th style="padding:6px;text-align:left;">ชื่อ</th><th style="padding:6px;text-align:center;">ขอ</th><th style="padding:6px;text-align:center;">อนุมัติ</th></tr>
                ${items.map(it => `<tr><td style="padding:6px;border-top:1px solid #e2e8f0;font-family:monospace;">${escHtml(it.item_code)}</td><td style="padding:6px;border-top:1px solid #e2e8f0;">${escHtml(it.name)}</td><td style="padding:6px;border-top:1px solid #e2e8f0;text-align:center;">${it.qty_requested}</td><td style="padding:6px;border-top:1px solid #e2e8f0;text-align:center;font-weight:700;">${it.qty_approved ?? '-'}</td></tr>`).join('')}
               </table>`
            : '<p style="font-size:12px;color:#64748b;margin-top:8px;">ไม่มีรายการ</p>';

        const actions = [];
        if (row.status === 'approved') {
            actions.push(`<button type="button" class="ptc-btn ptc-btn-primary" id="req-detail-print" style="font-size:12px;"><i class="fas fa-print"></i> พิมพ์ใบเบิก</button>`);
            actions.push(`<button type="button" class="ptc-btn" id="req-detail-pdf" style="font-size:12px;"><i class="fas fa-file-pdf"></i> เปิดแบบฟอร์ม (PDF)</button>`);
        }

        await global.Swal.fire({
            title: `คำขอ #${row.id}`,
            width: 640,
            html: `
                <div style="text-align:left;font-size:13px;line-height:1.5;">
                    <div><b>สถานะ:</b> ${escHtml(sb.label)}</div>
                    <div><b>ผู้ขอ:</b> ${escHtml(row.requester_name || '-')} · ${escHtml(row.department || '-')}</div>
                    <div><b>บริษัท:</b> ${escHtml(row.company || '-')} · ${escHtml(row.stock_type || '-')}</div>
                    <div><b>วันที่ขอ:</b> ${fmtDateFull(row.request_date)}</div>
                    <div><b>อนุมัติ:</b> ${escHtml(row.approved_by || '-')} ${row.approved_at ? '· ' + fmtDateFull(row.approved_at) : ''}</div>
                    ${row.notes ? `<div><b>หมายเหตุ:</b> ${escHtml(row.notes)}</div>` : ''}
                    ${itemsHtml}
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;flex-wrap:wrap;">${actions.join('')}</div>
            `,
            showConfirmButton: false,
            showCloseButton: true,
            didOpen: () => {
                const btnPrint = document.getElementById('req-detail-print');
                const btnPdf = document.getElementById('req-detail-pdf');
                if (btnPrint) btnPrint.addEventListener('click', () => printVoucherFromRow(row, items));
                if (btnPdf) btnPdf.addEventListener('click', () => openServerVoucher(row.id));
            }
        });
    }

    function printVoucherFromRow(req, items) {
        if (typeof global.printIssueVoucherWithData === 'function') {
            global.printIssueVoucherWithData(req, items);
            return;
        }
        global.AP_CURRENT_REQ_ID = req.id;
        global.AP_SEL_ITEMS = items;
        if (typeof global.printIssueVoucher === 'function') global.printIssueVoucher();
    }

    function openServerVoucher(requestId) {
        window.open(`api.php?action=getIssueVoucherPrint&request_id=${encodeURIComponent(requestId)}`, '_blank', 'noopener');
    }

    /** Signature rows at bottom of printed stock reports */
    function buildPrintSignBlock(roles) {
        const labels = roles && roles.length ? roles : ['ผู้จัดทำรายงาน', 'ผู้ตรวจสอบ', 'ผู้อนุมัติ'];
        return `
            <div class="stock-print-sign-block">
                ${labels.map(role => `
                    <div class="stock-print-sign-cell">
                        <div class="stock-print-sign-line"></div>
                        <div class="stock-print-sign-role">${escHtml(role)}</div>
                        <div class="stock-print-sign-name">(............................................)</div>
                        <div class="stock-print-sign-date">วันที่ ......./......./.......</div>
                    </div>`).join('')}
            </div>`;
    }

    const CO_PRINT_COLORS = { PTA: '#059669', PT4: '#dc2626', PTE: '#2563eb' };
    const CO_PRINT_NAMES = {
        PTA: 'Powertech Engine Assembly Co., Ltd.',
        PT4: 'Powertech 2004 Co., Ltd.',
        PTE: 'Powertech Energy Solutions Co., Ltd.'
    };
    const CO_PRINT_NAMES_TH = {
        PTA: 'บริษัท พาวเวอร์เทค เอนจิ้น แอสเซมบลี จำกัด',
        PT4: 'บริษัท พาวเวอร์เทค 2004 จำกัด',
        PTE: 'บริษัท พาวเวอร์เทค เอนเนอร์ยี โซลูชั่นส์ จำกัด',
    };
    const CO_LOGOS = {
        PTA: 'uploads/logos/pta_logo.png',
        PT4: 'uploads/logos/pt4_logo.png',
        PTE: 'uploads/logos/pte_logo.png',
    };
    // Cached company info (address/phone) loaded once from API
    let _coInfoCache = null;
    async function fetchCoInfo() {
        if (_coInfoCache) return _coInfoCache;
        try {
            const r = await fetch('api.php?action=get_company_info');
            const j = await r.json();
            if (j.status === 'success') _coInfoCache = j.data;
        } catch(e) { /* silent */ }
        return _coInfoCache || {};
    }

    function injectPrintColgroup(clone, kind) {
        const ths = clone.querySelectorAll('thead th');
        if (!ths.length) return;
        const n = ths.length;
        const widths = kind === 'txlog'
            ? (n >= 12
                ? ['9%', '7%', '7%', '12%', '6%', '5%', '4%', '6%', '6%', '8%', '8%', '12%']
                : ['11%', '9%', '14%', '8%', '6%', '5%', '13%', '20%'])
            : kind === 'monthly'
                ? (n >= 15
                    ? ['7%', '11%', '4%', '5%', '4%', '6%', '4%', '6%', '4%', '6%', '4%', '5%', '4%', '6%', '5%']
                    : ['10%', '17%', '6%', '9%', '9%', '9%', '8%', '9%', '8%'])
            : kind === 'stocktake'
                ? ['8%', '14%', '4%', '5%', '5%', '5%', '5%', '5%', '5%', '12%']
                : [];
        if (!widths.length) return;
        const cg = document.createElement('colgroup');
        ths.forEach((th, i) => {
            const col = document.createElement('col');
            if (widths[i]) col.style.width = widths[i];
            cg.appendChild(col);
        });
        clone.insertBefore(cg, clone.firstChild);
    }

    function preparePrintTable(table, kind) {
        const clone = table.cloneNode(true);
        clone.classList.remove('ptc-table');
        clone.classList.add('stock-print-table');
        if (kind) clone.classList.add('stock-print-table--' + kind);
        clone.querySelectorAll('[style]').forEach(el => {
            if (el.tagName === 'SPAN') return;
            el.removeAttribute('style');
        });
        clone.querySelectorAll('th, td').forEach(cell => {
            cell.style.whiteSpace = 'normal';
            cell.style.wordBreak = 'break-word';
            cell.style.overflowWrap = 'anywhere';
        });
        injectPrintColgroup(clone, kind);
        return clone.outerHTML;
    }

    function buildReconcileBlock(meta) {
        if (!meta?.totals) return '';
        const t = meta.totals;
        const qtyOk = (t.opening_balance + t.received - t.issued + t.adjusted) === t.closing_balance;
        const valCalc = Math.round((t.opening_value + t.received_value - t.issued_value + t.adjusted_value) * 100) / 100;
        const valOk = Math.abs(valCalc - t.closing_value) < 0.02;
        return `
            <div class="stock-print-reconcile">
                <div class="stock-print-reconcile-title">สรุปกระทบยอด (Audit)</div>
                <div class="stock-print-reconcile-formula">${escHtml(meta.formula || 'ยอดยกมา + รับ − เบิก + ปรับ = คงเหลือ')}</div>
                ${meta.value_basis ? `<div class="stock-print-reconcile-basis">${escHtml(meta.value_basis)}</div>` : ''}
                <table class="stock-print-reconcile-table">
                    <thead><tr>
                        <th>รายการ</th><th>จำนวน</th><th>มูลค่า (บาท)</th>
                    </tr></thead>
                    <tbody>
                        <tr><td>ยอดยกมา</td><td>${(t.opening_balance || 0).toLocaleString()}</td><td>${fmtMoney(t.opening_value)}</td></tr>
                        <tr><td>รับเข้า (+)</td><td>${(t.received || 0).toLocaleString()}</td><td>${fmtMoney(t.received_value)}</td></tr>
                        <tr><td>เบิกออก (−)</td><td>${(t.issued || 0).toLocaleString()}</td><td>${fmtMoney(t.issued_value)}</td></tr>
                        <tr><td>ปรับสต็อก (±)</td><td>${(t.adjusted || 0).toLocaleString()}</td><td>${fmtMoney(t.adjusted_value)}</td></tr>
                        <tr class="total"><td>ยอดคงเหลือ</td><td>${(t.closing_balance || 0).toLocaleString()}</td><td>${fmtMoney(t.closing_value)}</td></tr>
                    </tbody>
                </table>
                <div class="stock-print-reconcile-check">
                    <span class="${qtyOk ? 'ok' : 'bad'}">✓ จำนวนกระทบยอด${qtyOk ? 'ถูกต้อง' : 'ไม่ตรง'}</span>
                    <span class="${valOk ? 'ok' : 'bad'}">✓ มูลค่ากระทบยอด${valOk ? 'ถูกต้อง' : 'ไม่ตรง'}</span>
                </div>
            </div>`;
    }

    function rowHasMonthlyActivity(r) {
        return (r.opening_balance || 0) !== 0 || (r.received || 0) !== 0 ||
            (r.issued || 0) !== 0 || (r.adjusted || 0) !== 0 || (r.closing_balance || 0) !== 0;
    }

    function statusLabel(r) {
        if (r.is_oos == 1) return 'หมด';
        if (r.is_low == 1) return 'ใกล้หมด';
        return 'ปกติ';
    }

    /** Dedicated audit print — split qty / value tables (readable for audit) */
    function buildMonthlyAuditPrintHtml(meta, items) {
        const rows = (items || []).filter(rowHasMonthlyActivity);
        const t = meta?.totals || {};
        const qtyBody = rows.map(r => `
            <tr>
                <td>${escHtml(r.item_code)}</td>
                <td>${escHtml(r.name)}</td>
                <td class="c">${escHtml(r.unit)}</td>
                <td class="r">${r.opening_balance}</td>
                <td class="r">${r.received}</td>
                <td class="r">${r.issued}</td>
                <td class="r">${r.adjusted > 0 ? '+' : ''}${r.adjusted}</td>
                <td class="r b">${r.closing_balance}</td>
                <td class="c">${escHtml(statusLabel(r))}</td>
            </tr>`).join('');
        const valBody = rows.map(r => `
            <tr>
                <td>${escHtml(r.item_code)}</td>
                <td>${escHtml(r.name)}</td>
                <td class="c">${escHtml(r.unit)}</td>
                <td class="r">${fmtMoney(r.price_per_unit)}</td>
                <td class="r">${fmtMoney(r.opening_value)}</td>
                <td class="r">${fmtMoney(r.received_value)}</td>
                <td class="r">${fmtMoney(r.issued_value)}</td>
                <td class="r">${fmtMoney(r.adjusted_value)}</td>
                <td class="r b">${fmtMoney(r.closing_value)}</td>
            </tr>`).join('');

        return `
            <div class="stock-print-section">
                <div class="stock-print-section-title">ตารางที่ 1 — กระทบยอดจำนวน (${rows.length} รายการที่มี movement/คงเหลือ)</div>
                <table class="stock-print-table stock-print-table--monthly-qty">
                    <thead><tr>
                        <th>รหัส</th><th>ชื่อสินค้า</th><th>หน่วย</th>
                        <th>ยกมา</th><th>รับ</th><th>เบิก</th><th>ปรับ</th><th>คงเหลือ</th><th>สถานะ</th>
                    </tr></thead>
                    <tbody>${qtyBody}</tbody>
                    <tfoot><tr>
                        <td colspan="3" class="r b">รวมทั้งสิ้น</td>
                        <td class="r b">${(t.opening_balance || 0).toLocaleString()}</td>
                        <td class="r b">${(t.received || 0).toLocaleString()}</td>
                        <td class="r b">${(t.issued || 0).toLocaleString()}</td>
                        <td class="r b">${(t.adjusted || 0).toLocaleString()}</td>
                        <td class="r b">${(t.closing_balance || 0).toLocaleString()}</td>
                        <td></td>
                    </tr></tfoot>
                </table>
            </div>
            <div class="stock-print-section">
                <div class="stock-print-section-title">ตารางที่ 2 — กระทบยอดมูลค่า (บาท)</div>
                <div class="stock-print-section-sub">${escHtml(meta?.value_basis || 'มูลค่า = ราคามาตรฐาน/หน่วย × จำนวน')}</div>
                <table class="stock-print-table stock-print-table--monthly-val">
                    <thead><tr>
                        <th>รหัส</th><th>ชื่อสินค้า</th><th>หน่วย</th><th>ราคา/หน่วย</th>
                        <th>มูลค่ายกมา</th><th>มูลค่ารับ</th><th>มูลค่าเบิก</th><th>มูลค่าปรับ</th><th>มูลค่าคงเหลือ</th>
                    </tr></thead>
                    <tbody>${valBody}</tbody>
                    <tfoot><tr>
                        <td colspan="4" class="r b">รวมทั้งสิ้น</td>
                        <td class="r b">${fmtMoney(t.opening_value)}</td>
                        <td class="r b">${fmtMoney(t.received_value)}</td>
                        <td class="r b">${fmtMoney(t.issued_value)}</td>
                        <td class="r b">${fmtMoney(t.adjusted_value)}</td>
                        <td class="r b">${fmtMoney(t.closing_value)}</td>
                    </tr></tfoot>
                </table>
            </div>`;
    }

    function buildPrintDocument(title, subtitle, tableHtml, signRoles, options) {
        const opts = options || {};
        const meta = opts.meta || reportMeta || null;
        const co = hooks.companyFilter?.value || 'PTA';
        const type = TYPE_LABELS[hooks.typeFilter?.value] || '-';
        const coColor = CO_PRINT_COLORS[co] || '#2563eb';
        const coName   = CO_PRINT_NAMES[co]    || co;
        const coNameTh = CO_PRINT_NAMES_TH[co] || '';
        const logoSrc  = CO_LOGOS[co] || '';
        const printedAt = new Date().toLocaleString('th-TH');
        const tbodyMatch = tableHtml.match(/<tbody[\s\S]*?<\/tbody>/i)?.[0] || '';
        const rowCount = Math.max(0, (tbodyMatch.match(/<tr/gi) || []).length);
        const reportNo = meta?.report_no || opts.reportNo || '-';
        const sess = hooks.session || global.SESSION || {};
        const preparedBy = meta?.prepared_by || sess.name || sess.display_name || sess.username || '-';
        const auditNote = 'เอกสารนี้จัดทำจากระบบ PowertechCenter Stock สำหรับตรวจสอบ audit / ขึ้นทะเบียนสินค้าคงคลัง · งวด ' + escHtml(meta?.period_label || '');
        // Company info from cache (loaded async before print)
        const coInfo    = (_coInfoCache && _coInfoCache[co]) || {};
        const addrTh    = coInfo.address_th || coInfo.address_en || '';
        const phone     = coInfo.phone      || '';
        const fax       = coInfo.fax        || '';
        const contactLine = (phone || fax)
            ? `<div style="font-size:8pt;color:#475569;margin-top:1px;">${phone ? 'โทร. '+phone : ''}${phone && fax ? ' | ' : ''}${fax ? 'Fax '+fax : ''}</div>`
            : '';

        const logoHtml = logoSrc
            ? `<img src="${logoSrc}" alt="${escHtml(co)}" style="height:48px;width:auto;object-fit:contain;flex-shrink:0;margin-right:12px;" onerror="this.style.display='none'">`
            : '';

        return `
            <div class="stock-print-doc" style="--print-co:${coColor};">
                <div class="stock-print-head">
                    <div class="stock-print-head-main">
                        <div style="display:flex;align-items:center;gap:0;margin-bottom:6px;padding-bottom:8px;border-bottom:2px solid ${coColor};">
                            ${logoHtml}
                            <div style="border-left:3px solid ${coColor};padding-left:10px;">
                                <div style="font-size:11pt;font-weight:800;color:#1e293b;line-height:1.2;">${escHtml(coName)}</div>
                                <div style="font-size:8pt;color:#475569;margin-top:1px;">${escHtml(coNameTh)}</div>
                                ${addrTh ? `<div style="font-size:8pt;color:#64748b;margin-top:1px;">${escHtml(addrTh)}</div>` : ''}
                                ${contactLine}
                            </div>
                        </div>
                        <h1 class="stock-print-title">${escHtml(title)}</h1>
                        <div class="stock-print-subtitle">${escHtml(subtitle)}</div>
                        <div class="stock-print-docno">เลขที่เอกสาร: <strong>${escHtml(reportNo)}</strong></div>
                    </div>
                    <div class="stock-print-head-meta">
                        <div><span class="k">บริษัท</span><span class="v">${escHtml(co)} — ${escHtml(coName)}</span></div>
                        <div><span class="k">ประเภทสต็อก</span><span class="v">${escHtml(type)}</span></div>
                        <div><span class="k">งวดรายงาน</span><span class="v">${escHtml(meta?.period_label || '—')}</span></div>
                        <div><span class="k">ผู้จัดทำ</span><span class="v">${escHtml(preparedBy)}</span></div>
                        <div><span class="k">พิมพ์เมื่อ</span><span class="v">${escHtml(printedAt)}</span></div>
                    </div>
                </div>
                ${meta?.totals ? buildReconcileBlock(meta) : ''}
                <div class="stock-print-table-wrap">${tableHtml}</div>
                <div class="stock-print-audit-note">${auditNote}</div>
                <div class="stock-print-sign-section">${buildPrintSignBlock(signRoles)}</div>
                <div class="stock-print-foot">PowertechCenter Stock System · ${escHtml(co)} · ${escHtml(type)} · ${escHtml(reportNo)} · หน้า __/__</div>
            </div>`;
    }

    async function printTableArea(title, subtitle, tableHtml, signRoles, options) {
        // Pre-load company info (address/phone) so buildPrintDocument can use it
        await fetchCoInfo();

        let root = document.getElementById('stock-print-root');
        if (!root) {
            root = document.createElement('div');
            root.id = 'stock-print-root';
            root.className = 'stock-print-root stock-print-area';
            document.body.appendChild(root);
        }
        root.innerHTML = buildPrintDocument(title, subtitle, tableHtml, signRoles, options);
        root.style.display = 'block';
        document.body.classList.add('stock-printing');

        const cleanup = () => {
            document.body.classList.remove('stock-printing');
            root.style.display = 'none';
            root.innerHTML = '';
        };
        const onAfterPrint = () => cleanup();
        if (typeof window.addEventListener === 'function') {
            window.addEventListener('afterprint', onAfterPrint, { once: true });
        }
        if (window.matchMedia) {
            const mq = window.matchMedia('print');
            const onMq = (e) => {
                if (!e.matches) {
                    cleanup();
                    if (mq.removeEventListener) mq.removeEventListener('change', onMq);
                    else if (mq.removeListener) mq.removeListener(onMq);
                }
            };
            if (mq.addEventListener) mq.addEventListener('change', onMq);
            else if (mq.addListener) mq.addListener(onMq);
        }
        const triggerPrint = () => {
            try {
                window.print();
            } catch (err) {
                cleanup();
                global.Swal?.fire('Error', err.message || 'ไม่สามารถเปิดหน้าต่างพิมพ์ได้', 'error');
            }
        };
        requestAnimationFrame(() => requestAnimationFrame(triggerPrint));
        if (!window.matchMedia && typeof window.addEventListener !== 'function') {
            setTimeout(cleanup, 1500);
        }
    }

    function printMonthlyReport() {
        const meta = reportMeta;
        const items = reportItems;
        if (!meta?.report_no && !items.length) {
            const wrap = document.getElementById('rep-monthly-wrap');
            const table = wrap?.querySelector('table');
            if (table && wrap?.classList.contains('is-visible')) {
                const year = document.getElementById('report-year')?.value || '';
                const month = document.getElementById('report-month');
                const monthLabel = month?.options[month.selectedIndex]?.text || month?.value || '';
                printTableArea(
                    `รายงานสต็อกรายเดือน ${monthLabel} ${year}`,
                    'สำเนาจากตารางบนหน้าจอ — กระทบยอด audit',
                    preparePrintTable(table, 'monthly'),
                    ['ผู้จัดทำรายงาน', 'ผู้ตรวจสอบ', 'ผู้อนุมัติ', 'ผู้สอบบัญชี'],
                    { meta: reportMeta }
                );
                return;
            }
            global.Swal.fire('แจ้งเตือน', 'กรุณากด "ดูรายงาน" โหลดข้อมูลก่อนพิมพ์', 'warning');
            return;
        }
        if (!items.length) {
            global.Swal.fire('แจ้งเตือน', 'ไม่มีรายการในงวดนี้ — ไม่สามารถพิมพ์ได้', 'info');
            return;
        }
        const year = document.getElementById('report-year')?.value || '';
        const month = document.getElementById('report-month');
        const monthLabel = month?.options[month.selectedIndex]?.text || month?.value || '';
        const tableHtml = buildMonthlyAuditPrintHtml(meta, items);
        printTableArea(
            `รายงานสต็อกรายเดือน ${monthLabel} ${year}`,
            'กระทบยอดจำนวนและมูลค่า — เอกสารสำหรับ Audit / ขึ้นทะเบียน',
            tableHtml,
            ['ผู้จัดทำรายงาน', 'ผู้ตรวจสอบ', 'ผู้อนุมัติ', 'ผู้สอบบัญชี'],
            { meta: reportMeta || meta }
        );
    }

    function printTxLog() {
        const wrap = document.getElementById('rep-txlog-wrap');
        const table = wrap?.querySelector('table');
        if (!table || !wrap?.classList.contains('is-visible')) {
            global.Swal.fire('แจ้งเตือน', 'กรุณากด "ค้นหา" โหลดประวัติรายการก่อนพิมพ์', 'warning');
            return;
        }
        const from = document.getElementById('tx-from')?.value || '';
        const to = document.getElementById('tx-to')?.value || '';
        printTableArea(
            'ประวัติรายการเคลื่อนไหวสต็อก',
            `ช่วงวันที่ ${from || '—'} ถึง ${to || '—'} — อ้างอิง audit`,
            preparePrintTable(table, 'txlog'),
            ['ผู้จัดทำรายงาน', 'ผู้ตรวจสอบ', 'ผู้อนุมัติ', 'ผู้สอบบัญชี'],
            { meta: reportMeta }
        );
    }

    function printStocktakeTable(tableEl, title, docNo) {
        if (!tableEl) return;
        printTableArea(
            title || 'ใบนับสต็อก (Count Sheet)',
            'ยอดระบบ vs ยอดจริง — สำหรับ audit / ตรวจนับ',
            preparePrintTable(tableEl, 'stocktake'),
            ['ผู้นับสต็อก', 'ผู้ตรวจสอบ', 'ผู้อนุมัติ', 'ผู้สอบบัญชี'],
            { reportNo: docNo }
        );
    }

    function init(options) {
        hooks = options || {};
        mountScopeBar();
    }

    global.StockMgmtUI = {
        init,
        initScopeDefaults,
        updateScopePills,
        updateTopbarScope,
        updateBreadcrumb,
        pickCompany,
        pickType,
        openReqDetail,
        printMonthlyReport,
        printTxLog,
        printStocktakeTable,
        setReportMeta,
        openServerVoucher
    };
})(window);
