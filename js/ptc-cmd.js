/**
 * ptc-cmd.js — Command Palette (Ctrl+K / Cmd+K)
 * Usage: ptcCmd.register([{ label, icon, color, action|href, desc? }])
 *        ptcCmd.open() / ptcCmd.close()
 */
(function () {
    'use strict';

    var PAGES = [
        { label: 'Dashboard หน้าแรก',      icon: 'fa-home',             color: '#2563eb', href: 'index.html' },
        { label: 'Dashboard งานซ่อม',      icon: 'fa-tachometer-alt',   color: '#6366f1', href: 'dashboard.html' },
        { label: 'บันทึกงาน IT',           icon: 'fa-book',             color: '#10b981', href: 'log.html' },
        { label: 'IT Report',               icon: 'fa-chart-bar',        color: '#f59e0b', href: 'it_report.html' },
        { label: 'แจ้ง Ticket ซ่อม',      icon: 'fa-wrench',           color: '#ef4444', href: 'job_ticket.html' },
        { label: 'สมุดรายชื่อพนักงาน',    icon: 'fa-address-book',     color: '#8b5cf6', href: 'directory.html' },
        { label: 'ยืม-คืนอุปกรณ์',        icon: 'fa-hand-holding-box', color: '#ec4899', href: 'loan.html' },
        { label: 'Admin Panel',             icon: 'fa-cogs',             color: '#ef4444', href: 'admin.html' },
        { label: 'รายการแจ้งซ่อมของฉัน', icon: 'fa-ticket-alt',       color: '#0ea5e9', href: 'my_tickets.html' },
        { label: 'ประวัติการยืมของฉัน',   icon: 'fa-box-open',         color: '#d97706', href: 'my_borrow_history.html' },
        { label: 'Daily Checklist',         icon: 'fa-clipboard-check',  color: '#16a34a', href: 'daily_checklist.html' },
        { label: 'Audit Log',               icon: 'fa-history',          color: '#64748b', href: 'audit_log.html' },
        { label: 'Knowledge Base',          icon: 'fa-book-open',        color: '#f59e0b', href: 'kb.html' },
        { label: 'Stock Management',        icon: 'fa-boxes',            color: '#0ea5e9', href: 'stock_management.html' },
        { label: 'Routing แจ้งเตือน',     icon: 'fa-bell',             color: '#d97706', href: 'settings_notify.html' },
        { label: 'ตั้งค่าระบบ',           icon: 'fa-cog',              color: '#64748b', href: 'settings_system.html' },
        { label: 'สร้างไฟล์ .reg',        icon: 'fa-file-code',        color: '#7c3aed', href: 'reg_generator.html' },
    ];

    var extraCmds = [];
    var isOpen    = false;
    var overlay   = null;
    var inputEl   = null;
    var listEl    = null;
    var activeIdx = -1;
    var items     = [];

    function register(cmds) {
        extraCmds = extraCmds.concat(cmds || []);
    }

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function buildItems(query) {
        var q = (query || '').toLowerCase().trim();
        var actions = extraCmds.map(function (c) { return Object.assign({}, c, { _type: 'action' }); });
        var pages   = PAGES.map(function (p) { return Object.assign({}, p, { _type: 'page'   }); });
        var all = actions.concat(pages);
        if (!q) return all;
        return all.filter(function (item) { return (item.label || '').toLowerCase().includes(q); });
    }

    function renderList(query) {
        items = buildItems(query);
        activeIdx = items.length > 0 ? 0 : -1;
        listEl.innerHTML = '';

        if (items.length === 0) {
            listEl.innerHTML = '<div style="padding:24px;text-align:center;font-size:13px;color:#94a3b8">ไม่พบคำสั่ง</div>';
            return;
        }

        var lastType = null;
        items.forEach(function (item, idx) {
            if (item._type !== lastType) {
                var sep = document.createElement('div');
                sep.style.cssText = 'font-size:10px;font-weight:700;letter-spacing:.07em;color:#94a3b8;padding:10px 14px 4px;text-transform:uppercase;user-select:none';
                sep.textContent = item._type === 'action' ? 'ACTIONS' : 'NAVIGATE';
                listEl.appendChild(sep);
                lastType = item._type;
            }

            var row = document.createElement('div');
            row.setAttribute('data-idx', idx);
            row.style.cssText = [
                'display:flex',
                'align-items:center',
                'gap:10px',
                'padding:8px 10px',
                'cursor:pointer',
                'border-radius:6px',
                'margin:1px 6px',
                'transition:background .1s',
                idx === 0 ? 'background:var(--ptc-surface-2,#f8fafc)' : '',
            ].join(';');

            var color = item.color || '#64748b';
            row.innerHTML =
                '<span style="width:28px;height:28px;border-radius:6px;background:' + color + '1a;display:flex;align-items:center;justify-content:center;flex-shrink:0">' +
                    '<i class="fa ' + (item.icon || 'fa-circle') + '" style="color:' + color + ';font-size:12px"></i>' +
                '</span>' +
                '<div style="flex:1;min-width:0">' +
                    '<div style="font-size:13px;font-weight:500;color:var(--ptc-text,#0f172a);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + esc(item.label) + '</div>' +
                    (item.desc ? '<div style="font-size:11px;color:#94a3b8;margin-top:1px">' + esc(item.desc) + '</div>' : '') +
                '</div>' +
                (item._type === 'page' ? '<i class="fa fa-arrow-right" style="font-size:10px;color:#cbd5e1;flex-shrink:0"></i>' : '');

            row.addEventListener('mouseenter', function () { setActive(parseInt(this.getAttribute('data-idx'))); }.bind(row));
            row.addEventListener('click', (function (i) { return function () { execute(items[i]); }; })(idx));
            listEl.appendChild(row);
        });
    }

    function setActive(idx) {
        listEl.querySelectorAll('[data-idx]').forEach(function (el) {
            el.style.background = parseInt(el.getAttribute('data-idx')) === idx
                ? 'var(--ptc-surface-2,#f8fafc)' : '';
        });
        activeIdx = idx;
    }

    function execute(item) {
        close();
        if (!item) return;
        if (item.action) {
            setTimeout(function () { item.action(); }, 80);
        } else if (item.href) {
            window.location.href = item.href;
        }
    }

    function open() {
        if (isOpen) return;
        isOpen = true;
        buildDOM();
        document.body.appendChild(overlay);
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                overlay.style.opacity = '1';
                var d = overlay.querySelector('[data-cmd-dialog]');
                if (d) { d.style.transform = 'translateY(0)'; d.style.opacity = '1'; }
            });
        });
        inputEl.focus();
        renderList('');
    }

    function close() {
        if (!isOpen) return;
        isOpen = false;
        if (!overlay) return;
        var d = overlay.querySelector('[data-cmd-dialog]');
        overlay.style.opacity = '0';
        if (d) { d.style.transform = 'translateY(-8px)'; d.style.opacity = '0'; }
        var o = overlay;
        overlay = null;
        setTimeout(function () { if (o && o.parentNode) o.remove(); }, 160);
    }

    function kbd(label) {
        return '<kbd style="background:var(--ptc-surface-2,#f8fafc);border:1px solid var(--ptc-border,#e2e8f0);border-radius:3px;padding:1px 5px;font-family:monospace;font-size:10px">' + label + '</kbd>';
    }

    function buildDOM() {
        overlay = document.createElement('div');
        overlay.style.cssText = [
            'position:fixed', 'inset:0', 'z-index:99998',
            'background:rgba(15,23,42,.45)',
            'display:flex', 'align-items:flex-start', 'justify-content:center',
            'padding-top:15vh',
            'opacity:0', 'transition:opacity .15s ease',
        ].join(';');
        overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });

        var dialog = document.createElement('div');
        dialog.setAttribute('data-cmd-dialog', '1');
        dialog.style.cssText = [
            'width:100%', 'max-width:540px',
            'background:var(--ptc-surface,#fff)',
            'border:1px solid var(--ptc-border,#e2e8f0)',
            'border-radius:14px',
            'box-shadow:0 24px 60px rgba(0,0,0,.22)',
            'overflow:hidden',
            'opacity:0', 'transform:translateY(-10px)',
            'transition:opacity .15s ease,transform .15s ease',
        ].join(';');

        // Search bar
        var bar = document.createElement('div');
        bar.style.cssText = 'display:flex;align-items:center;gap:10px;padding:12px 14px;border-bottom:1px solid var(--ptc-border,#e2e8f0)';
        bar.innerHTML = '<i class="fa fa-search" style="color:#94a3b8;font-size:13px;flex-shrink:0"></i>';

        inputEl = document.createElement('input');
        inputEl.type = 'text';
        inputEl.placeholder = 'ค้นหาเมนู หรือพิมพ์คำสั่ง...';
        inputEl.style.cssText = 'flex:1;border:none;outline:none;font-size:14px;background:transparent;color:var(--ptc-text,#0f172a);font-family:inherit';
        inputEl.addEventListener('input', function () { renderList(inputEl.value); });
        inputEl.addEventListener('keydown', onKey);

        var escHint = document.createElement('kbd');
        escHint.textContent = 'ESC';
        escHint.style.cssText = 'font-size:10px;color:#94a3b8;background:var(--ptc-surface-2,#f8fafc);border:1px solid var(--ptc-border,#e2e8f0);border-radius:4px;padding:2px 6px;font-family:monospace;cursor:pointer;flex-shrink:0';
        escHint.addEventListener('click', close);

        bar.appendChild(inputEl);
        bar.appendChild(escHint);

        // List
        listEl = document.createElement('div');
        listEl.style.cssText = 'max-height:360px;overflow-y:auto;padding:6px 0';

        // Footer
        var footer = document.createElement('div');
        footer.style.cssText = 'padding:8px 14px;border-top:1px solid var(--ptc-border,#e2e8f0);display:flex;gap:14px;font-size:11px;color:#94a3b8;align-items:center';
        footer.innerHTML =
            '<span>' + kbd('↑↓') + ' เลือก</span>' +
            '<span>' + kbd('↵') + ' เปิด</span>' +
            '<span>' + kbd('ESC') + ' ปิด</span>';

        dialog.appendChild(bar);
        dialog.appendChild(listEl);
        dialog.appendChild(footer);
        overlay.appendChild(dialog);
    }

    function onKey(e) {
        var rows = listEl.querySelectorAll('[data-idx]');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            var next = Math.min(activeIdx + 1, rows.length - 1);
            setActive(next);
            if (rows[next]) rows[next].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            var prev = Math.max(activeIdx - 1, 0);
            setActive(prev);
            if (rows[prev]) rows[prev].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            if (rows[activeIdx]) rows[activeIdx].click();
        } else if (e.key === 'Escape') {
            close();
        }
    }

    // Global Ctrl+K / Cmd+K
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            isOpen ? close() : open();
        }
    });

    window.ptcCmd = { open: open, close: close, register: register };
})();
