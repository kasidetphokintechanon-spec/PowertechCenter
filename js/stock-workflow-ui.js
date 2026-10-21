/**
 * PowertechCenter — shared stock module UX (navigation + workflow chips)
 */
(function (global) {
    const MODULES = [
        { id: 'stationery', href: 'stationery.html', label: 'เครื่องเขียน', icon: 'fa-pen', requestType: true },
        { id: 'maid', href: 'maid_stock.html', label: 'Maid Stock', icon: 'fa-broom', requestType: true },
        { id: 'history', href: 'my_stock_history.html', label: 'ประวัติคำขอ', icon: 'fa-clock-rotate-left' },
        { id: 'management', href: 'stock_management.html', label: 'จัดการคลัง', icon: 'fa-boxes-stacked', approverOnly: true },
    ];

    const REQUEST_MODULE_IDS = ['stationery', 'maid'];

    function canManageStock(session) {
        if (!session) return false;
        return session.role === 'admin' || Number(session.can_manage_stock) === 1;
    }

    function setChip(el, state) {
        if (!el) return;
        const base = el.classList.contains('ap-flow-chip') ? 'ap-flow-chip' : 'wf-chip';
        el.className = base + (state ? ` ${state}` : '');
    }

    function syncRequesterWorkflowChips(opts) {
        const hasCart = !!opts?.hasCart;
        const hasPending = !!opts?.hasPending;
        setChip(document.getElementById('wf-create'), hasCart ? 'done' : 'active');
        setChip(document.getElementById('wf-approve'), hasPending ? 'active' : '');
        setChip(document.getElementById('wf-issue'), hasPending ? 'active' : '');
        setChip(document.getElementById('wf-adjust'), hasPending ? 'active' : '');
        setChip(document.getElementById('wf-report'), 'done');
    }

    function syncApproverFlowChips(tab) {
        const note = document.getElementById('ap-flow-note');
        const approveChip = document.getElementById('ap-flow-approve');
        const issueChip = document.getElementById('ap-flow-issue');
        if (!approveChip || !issueChip) return;

        setChip(approveChip, '');
        setChip(issueChip, '');

        if (tab === 'pending') {
            if (note) note.textContent = 'โหมดผู้อนุมัติ: ตรวจรายการ, ปรับจำนวนอนุมัติ, และบันทึกการอนุมัติ/ปฏิเสธอย่างมีเหตุผล';
            setChip(approveChip, 'active');
        } else if (tab === 'approved') {
            if (note) note.textContent = 'แสดงคำขอที่อนุมัติแล้ว สามารถพิมพ์ใบจ่ายของและตรวจสอบย้อนหลังได้';
            setChip(approveChip, 'done');
            setChip(issueChip, 'active');
        } else if (tab === 'rejected') {
            if (note) note.textContent = 'แสดงคำขอที่ถูกปฏิเสธ เพื่อทบทวนเหตุผลและสื่อสารกับผู้ขอ';
            setChip(approveChip, 'active');
        } else {
            if (note) note.textContent = 'ภาพรวมทุกสถานะในขั้นตอนอนุมัติและจ่ายของ';
            setChip(approveChip, 'active');
        }
    }

    function configureRequesterApproverUI(session, options) {
        const opts = options || {};
        const isApprover = canManageStock(session);
        const btn = document.getElementById(opts.approverBtnId || 'wf-approver-btn');
        const link = document.getElementById(opts.approverLinkId || 'req-approver-link');
        const note = document.getElementById(opts.roleNoteId || 'wf-role-note');
        if (btn && isApprover) btn.classList.remove('hidden');
        if (link && isApprover) link.classList.remove('hidden');
        if (note) {
            note.textContent = isApprover
                ? (opts.approverNote || 'บทบาทของคุณ: ผู้ขอเบิก + ผู้อนุมัติ · ใช้ปุ่ม "ไปหน้าอนุมัติ" เพื่ออนุมัติและจ่ายของ')
                : (opts.requesterNote || 'บทบาทของคุณ: ผู้ขอเบิก · ส่งคำขอและติดตามสถานะได้จากหน้านี้');
        }
        return isApprover;
    }

    const STATUS_BADGES = {
        pending: ['ps-badge-low', 'รออนุมัติ'],
        approved: ['ps-badge-ok', 'อนุมัติแล้ว'],
        rejected: ['ps-badge-critical', 'ปฏิเสธ'],
        cancelled: ['ps-badge-neutral', 'ยกเลิก'],
        completed: ['ps-badge-ok', 'เสร็จสิ้น'],
    };

    function stockStatusBadge(status, label) {
        const key = String(status || '').toLowerCase();
        const pair = STATUS_BADGES[key] || ['ps-badge-neutral', label || status || '-'];
        const text = label || pair[1];
        return `<span class="ps-badge ${pair[0]}">${text}</span>`;
    }

    function mountModuleNav(activeId, containerId, session) {
        const host = document.getElementById(containerId || 'stock-module-nav');
        if (!host) return;
        const isApprover = canManageStock(session);
        const onRequestPage = REQUEST_MODULE_IDS.includes(activeId);
        const parts = MODULES.filter((m) => {
            if (m.approverOnly && !isApprover) return false;
            /* หน้าเบิก: ไม่แสดงปุ่มไปอีกประเภท (stationery ↔ maid) */
            if (onRequestPage && m.requestType && m.id !== activeId) return false;
            return true;
        }).map((m) => {
            const active = m.id === activeId ? ' active' : '';
            return `<a href="${m.href}" class="stock-mod-link${active}"><i class="fas ${m.icon}"></i>${m.label}</a>`;
        });
        const usePs = document.body.classList.contains('stock-ps-ui');
        if (usePs) {
            host.innerHTML = `<div class="stock-ps-mod-nav"><span class="stock-ps-mod-label">Stock Center</span><div class="stock-mod-nav-inner">${parts.join('')}</div></div>`;
        } else {
            host.innerHTML = `<div class="stock-mod-nav-inner">${parts.join('')}</div>`;
        }
    }

    function toggleStockSidebar(forceOpen) {
        const page = document.body;
        if (!page.classList.contains('stock-mgmt-page')) return;
        const open = typeof forceOpen === 'boolean'
            ? forceOpen
            : !page.classList.contains('stock-sidebar-open');
        page.classList.toggle('stock-sidebar-open', open);
        document.getElementById('stock-sidebar-backdrop')?.setAttribute('aria-hidden', open ? 'false' : 'true');
    }

    function closeStockSidebar() {
        toggleStockSidebar(false);
    }

    function initStockManagementLayout() {
        if (!document.body.classList.contains('stock-mgmt-page')) return;
        document.querySelectorAll('#sidebar .nav-item').forEach((el) => {
            el.addEventListener('click', () => {
                if (window.matchMedia('(max-width: 1023px)').matches) closeStockSidebar();
            });
        });
        window.addEventListener('resize', () => {
            if (window.matchMedia('(min-width: 1024px)').matches) closeStockSidebar();
        });
    }

    global.StockWorkflowUI = {
        MODULES,
        STATUS_BADGES,
        canManageStock,
        setChip,
        stockStatusBadge,
        syncRequesterWorkflowChips,
        syncApproverFlowChips,
        configureRequesterApproverUI,
        mountModuleNav,
        toggleStockSidebar,
        closeStockSidebar,
        initStockManagementLayout,
    };
})(typeof window !== 'undefined' ? window : globalThis);
