/**
 * PTC Shared Sidebar — js/sidebar.js
 * ใช้ใน admin.html และทุกหน้า admin
 * ต้องการ: Font Awesome 6 (โหลดแยก)
 */
(function () {
    const STORAGE_KEY = 'ptc_sidebar_collapsed';
    const FOLD_KEY = 'ptc_sidebar_fold_'; // + group.key → '1' พับ / '0' กาง

    // perm: null = ทุก admin/staff เห็นได้, 'admin' = admin role เท่านั้น, string = ต้องมี permission นั้น
    // source of truth: includes/permissions_registry.php → getAdminMenuPermissionMap()
    // จัดกลุ่มตาม "งานที่ทำ" (2026-10-21) — ของใช้ทุกวันอยู่บน, ตั้งค่าพับเก็บไว้ · เมนูเดิมสำรองที่ js/sidebar_BK.js
    // foldable: true = กดหัวกลุ่มเพื่อพับ/กาง (ค่าเริ่มต้นพับ, กางเองถ้าหน้าปัจจุบันอยู่ในกลุ่มนั้น)
    const GROUPS = [
        {
            key: 'overview', label: 'ภาพรวม', color: '#2563eb',
            items: [
                { id: 'dashboard',       icon: 'fa-gauge-high',         label: 'Dashboard',            tab: 'dashboard',        page: 'admin',             perm: null },
            ],
        },
        {
            key: 'daily', label: 'งานประจำวัน', color: '#8b5cf6',
            items: [
                { id: 'log',             icon: 'fa-clipboard-list',     label: 'งานช่าง IT',            href: 'log.html',        page: 'log',               perm: 'it_log.access' },
                { id: 'task_center',     icon: 'fa-list-check',         label: 'Task Center',          href: 'task_center.html',page: 'task_center',       perm: null },
                { id: 'loan',            icon: 'fa-rotate',             label: 'ยืม-คืนอุปกรณ์',        href: 'borrow_management.html', page: 'loan', perm: 'loan.manage' },
                { id: 'stock',           icon: 'fa-boxes-stacked',      label: 'สต็อก & เบิกของ',       href: 'stock_management.html', page: 'stock_management', perm: 'stock.manage' },
                { id: 'server_check_daily', icon: 'fa-server',          label: 'ตรวจห้อง Server',       href: 'server_check.html', page: 'server_check',    perm: 'it_log.access' },
                { id: 'setup',           icon: 'fa-laptop-medical',     label: 'ติดตั้ง / คืนเครื่อง',   href: 'setup_list.html', page: 'setup_list',        perm: 'it_log.access' },
            ],
        },
        {
            key: 'master', label: 'ข้อมูลหลัก', color: '#10b981',
            items: [
                { id: 'employees',       icon: 'fa-users',              label: 'พนักงาน',              tab: 'employees',        page: 'admin',             perm: 'employee.manage' },
                { id: 'it_assets',       icon: 'fa-laptop',             label: 'ทะเบียนพัสดุ',          tab: 'it_assets',        page: 'admin',             perm: 'asset.manage' },
                { id: 'kb_articles',     icon: 'fa-book-open',          label: 'คลังความรู้ (KB)',       tab: 'kb_articles',      page: 'admin',             perm: 'kb.manage' },
                { id: 'directory',       icon: 'fa-address-book',       label: 'Contact List',         href: 'directory.html',  page: 'directory',         perm: null },
            ],
        },
        {
            key: 'settings', label: 'ตั้งค่า', color: '#f59e0b', foldable: true,
            items: [
                { id: 'job_positions',   icon: 'fa-briefcase',          label: 'ตำแหน่งงาน',            tab: 'job_positions',    page: 'admin',             perm: 'employee.manage' },
                { id: 'departments',     icon: 'fa-building',           label: 'แผนก',                 tab: 'departments',      page: 'admin',             perm: 'employee.manage' },
                { id: 'asset_categories',icon: 'fa-tags',               label: 'หมวดหมู่อุปกรณ์',        tab: 'asset_categories', page: 'admin',             perm: 'asset.manage' },
                { id: 'locations',       icon: 'fa-location-dot',       label: 'สถานที่',               tab: 'locations',        page: 'admin',             perm: 'asset.manage' },
                { id: 'kb_categories',   icon: 'fa-folder-tree',        label: 'หมวดหมู่ KB',           tab: 'kb_categories',    page: 'admin',             perm: 'kb.manage' },
                { id: 'services',        icon: 'fa-link',               label: 'ลิงก์บริการหน้าแรก',     tab: 'services',         page: 'admin',             perm: 'employee.manage' },
                { id: 'server_check',    icon: 'fa-door-closed',        label: 'ห้อง Server',           tab: 'server_check',     page: 'admin',             perm: 'admin.manage_permissions' },
                { id: 'admin_logo',      icon: 'fa-image',              label: 'โลโก้บริษัท',           href: 'admin_logo.html', page: 'admin_logo',        perm: 'settings.notifications' },
                { id: 'settings_notify', icon: 'fa-bell',               label: 'การแจ้งเตือน',          href: 'settings_notify.html', page: 'settings_notify', perm: 'settings.notifications' },
                { id: 'settings_system', icon: 'fa-gear',               label: 'ตั้งค่าระบบ',            href: 'settings_system.html', page: 'settings_system', perm: 'admin' },
            ],
        },
        {
            key: 'admin', label: 'ดูแลระบบ', color: '#64748b',
            items: [
                { id: 'permissions',     icon: 'fa-user-shield',        label: 'สิทธิ์ผู้ใช้',            tab: 'permissions',      page: 'admin',             perm: 'admin.manage_permissions' },
                { id: 'audit_log',       icon: 'fa-clock-rotate-left',  label: 'ประวัติการใช้งาน',       href: 'audit_log.html',  page: 'audit_log',         perm: 'audit.view' },
                { id: 'backup',          icon: 'fa-floppy-disk',        label: 'สำรองข้อมูล',           special: 'backup',                                  perm: 'admin.manage_permissions' },
                { id: 'update_db',       icon: 'fa-database',           label: 'อัปเดตฐานข้อมูล',        href: 'update_db.php',   page: 'update_db',         perm: 'admin.manage_permissions', newTab: true },
            ],
        },
    ];

    // ── CSS (ใช้ CSS variables รองรับ dark mode) ─────────────────────────
    const CSS = `
#ptc-sidebar {
    width: 220px;
    flex-shrink: 0;
    background: var(--ptc-surface, #fff);
    border-right: 1px solid var(--ptc-border, #e2e8f0);
    display: flex;
    flex-direction: column;
    transition: width .2s ease;
    overflow: hidden;
    position: relative;
    z-index: 10;
}
#ptc-sidebar.collapsed { width: 56px; }

.psb-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 14px;
    height: 56px;
    border-bottom: 1px solid var(--ptc-border, #e2e8f0);
    flex-shrink: 0;
}
.psb-logo {
    width: 28px; height: 28px;
    background: #2563eb;
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    font-size: 13px; color: #fff; font-weight: 800;
}
.psb-title {
    font-size: 13px; font-weight: 700;
    color: var(--ptc-text, #0f172a);
    white-space: nowrap; overflow: hidden;
    transition: opacity .15s ease;
}
#ptc-sidebar.collapsed .psb-title { opacity: 0; pointer-events: none; width: 0; overflow: hidden; }
#ptc-sidebar.collapsed .psb-header { padding: 0 1px; gap: 0; justify-content: space-between; }
.psb-toggle {
    margin-left: auto;
    width: 26px; height: 26px;
    border: none; background: none;
    border-radius: 6px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: var(--ptc-text-muted, #94a3b8);
    flex-shrink: 0;
    transition: background .15s;
}
.psb-toggle:hover { background: var(--ptc-surface-2, #f1f5f9); color: var(--ptc-text, #475569); }
#ptc-sidebar.collapsed .psb-toggle { margin-left: 0; }

.psb-nav {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 8px 0 16px;
}
.psb-nav::-webkit-scrollbar { width: 3px; }
.psb-nav::-webkit-scrollbar-thumb { background: var(--ptc-border, #e2e8f0); border-radius: 3px; }

.psb-group-label {
    display: flex;
    align-items: center;
    gap: 7px;
    width: 100%;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .02em;
    color: var(--ptc-text-muted, #94a3b8);
    padding: 16px 16px 6px;
    white-space: nowrap;
    overflow: hidden;
    transition: opacity .15s;
}
.psb-g-dot { width: 6px; height: 6px; border-radius: 2px; background: var(--g-color, #94a3b8); flex-shrink: 0; }
.psb-g-text { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; text-align: left; }
.psb-fold-btn { border: 0; background: transparent; font-family: inherit; cursor: pointer; border-radius: 8px; margin: 10px 0 0; padding: 8px 16px 6px; }
.psb-fold-btn:hover { color: var(--ptc-text, #0f172a); }
.psb-g-count { font-size: 10px; font-weight: 600; padding: 1px 6px; border-radius: 99px; background: var(--ptc-surface-3, #f1f5f9); color: var(--ptc-text-faint, #94a3b8); opacity: 0; transition: opacity .15s; }
.psb-fold-btn.folded .psb-g-count { opacity: 1; }
.psb-g-chev { font-size: 9px; transition: transform .2s; }
.psb-fold-btn.folded .psb-g-chev { transform: rotate(-90deg); }
.psb-fold-items { display: grid; grid-template-rows: 1fr; transition: grid-template-rows .2s ease; }
.psb-fold-inner { min-height: 0; overflow: hidden; }
.psb-fold-items.folded { grid-template-rows: 0fr; }
#ptc-sidebar.collapsed .psb-fold-items.folded { grid-template-rows: 1fr; } /* โหมดไอคอน: แสดงทุกเมนู */
#ptc-sidebar.collapsed .psb-fold-btn { pointer-events: none; }
#ptc-sidebar.collapsed .psb-group-label { opacity: 0; }

.psb-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 12px;
    height: 36px;
    margin: 1px 6px;
    border-radius: 8px;
    cursor: pointer;
    border: none;
    background: none;
    width: calc(100% - 12px);
    text-align: left;
    text-decoration: none;
    color: var(--ptc-text-muted, #475569);
    font-size: 13px;
    font-family: inherit;
    font-weight: 500;
    white-space: nowrap;
    position: relative;
    transition: background .12s, color .12s;
}
.psb-item:hover { background: var(--ptc-surface-2, #f1f5f9); color: var(--ptc-text, #0f172a); }
.psb-item.active {
    background: color-mix(in srgb, var(--g-color, #2563eb) 10%, transparent);
    color: var(--g-color, #2563eb);
    font-weight: 600;
}
.psb-item.active::before {
    content: '';
    position: absolute;
    left: 0; top: 5px; bottom: 5px;
    width: 3px;
    border-radius: 0 3px 3px 0;
    background: var(--g-color, #2563eb);
}
.psb-item.active .psb-icon { color: var(--g-color, #2563eb); }
.psb-icon {
    width: 16px; text-align: center;
    font-size: 13px;
    flex-shrink: 0;
    color: var(--g-color, #64748b);
    transition: color .12s;
}
.psb-item:hover .psb-icon { color: var(--g-color, #334155); }
.psb-label {
    overflow: hidden;
    transition: opacity .15s, max-width .2s;
    max-width: 160px;
}
#ptc-sidebar.collapsed .psb-label { opacity: 0; max-width: 0; }
.psb-badge {
    margin-left: auto;
    background: #ef4444;
    color: #fff;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 6px;
    flex-shrink: 0;
    transition: opacity .15s;
}
#ptc-sidebar.collapsed .psb-badge { opacity: 0; }

/* Tooltip เมื่อ collapsed */
#ptc-sidebar.collapsed .psb-item::after {
    content: attr(data-tooltip);
    position: absolute;
    left: 100%;
    top: 50%; transform: translateY(-50%);
    margin-left: 10px;
    background: var(--ptc-text, #0f172a);
    color: var(--ptc-surface, #fff);
    font-size: 11px;
    padding: 4px 10px;
    border-radius: 6px;
    white-space: nowrap;
    pointer-events: none;
    opacity: 0;
    z-index: 9999;
    transition: opacity .12s;
    box-shadow: 0 2px 8px rgba(0,0,0,.2);
}
#ptc-sidebar.collapsed .psb-item:hover::after { opacity: 1; }

.psb-divider {
    height: 1px;
    background: var(--ptc-border, #f1f5f9);
    margin: 6px 12px;
}
    `;

    // ── CSS variable (lets fixed-layout pages like stock shift themselves) ─
    function _setCssVar(isCollapsed) {
        document.documentElement.style.setProperty('--ptc-sb-w', isCollapsed ? '56px' : '220px');
    }

    // ── Session (cached promise) ──────────────────────────────────────────
    function getSession() {
        if (!window._ptcSessPromise) {
            window._ptcSessPromise = fetch('api.php?action=check_session', { credentials: 'same-origin' })
                .then(r => r.json())
                .catch(() => ({ role: 'guest', loggedin: false }));
        }
        return window._ptcSessPromise;
    }

    // ── Detect current page ──────────────────────────────────────────────
    function detectCurrentPage() {
        const bodyPage = document.body.dataset.page;
        if (bodyPage) return bodyPage;
        const path = location.pathname.replace(/.*\//, '').replace(/\.[^.]+$/, '');
        return path || 'admin';
    }

    function detectCurrentTab() {
        return document.body.dataset.tab || new URLSearchParams(location.search).get('tab') || '';
    }

    // ── Permission check ─────────────────────────────────────────────────
    function canSeeItem(item, session) {
        if (!session || !session.role) return false;
        const role = session.role;
        if (role !== 'admin' && role !== 'staff') return false;
        const perms = session.effective_permissions || [];
        const hasAll = role === 'admin' || perms.includes('*');
        if (item.perm === 'admin') return hasAll;
        if (item.perm === null) return true;  // visible to all admin/staff
        return hasAll || perms.includes(item.perm);
    }

    // ── Render ───────────────────────────────────────────────────────────
    // isFirstRender=true → skip loadBadges (will re-render with real session shortly)
    function render(el, session, isFirstRender) {
        if (typeof session === 'string') session = { role: session, effective_permissions: [] };
        if (!session || typeof session !== 'object') session = { role: 'guest', effective_permissions: [] };
        const currentPage = detectCurrentPage();
        const currentTab  = detectCurrentTab();
        const collapsed   = localStorage.getItem(STORAGE_KEY) === '1';
        const isAdmin     = session.role === 'admin';

        let html = `
        <div class="psb-header">
            <div class="psb-logo">P</div>
            <span class="psb-title">Admin</span>
            <button class="psb-toggle" id="psb-toggle-btn" title="ย่อ/ขยาย sidebar">
                <i class="fas fa-bars" style="font-size:13px"></i>
            </button>
        </div>
        <nav class="psb-nav">`;

        const isItemActive = (item) => (item.tab && currentPage === 'admin' && currentTab === item.tab)
                                    || (item.page && !item.tab && currentPage === item.page);

        GROUPS.forEach((group) => {
            const visibleItems = group.items.filter(item => canSeeItem(item, session));
            if (!visibleItems.length) return;

            // กลุ่มพับได้: จำสถานะไว้ต่อเครื่อง · ถ้าหน้าที่เปิดอยู่เป็นของกลุ่มนี้ ให้กางเสมอ (ไม่งั้นหาเมนูที่ active ไม่เจอ)
            const hasActive = visibleItems.some(isItemActive);
            const folded = group.foldable && !hasActive && localStorage.getItem(FOLD_KEY + group.key) !== '0';
            if (group.foldable) {
                html += `<button type="button" class="psb-group-label psb-fold-btn${folded ? ' folded' : ''}" data-fold="${group.key}" style="--g-color:${group.color}" aria-expanded="${!folded}">
                    <span class="psb-g-dot"></span><span class="psb-g-text">${group.label}</span>
                    <span class="psb-g-count">${visibleItems.length}</span><i class="fas fa-chevron-down psb-g-chev"></i>
                </button><div class="psb-fold-items${folded ? ' folded' : ''}" data-fold-items="${group.key}"><div class="psb-fold-inner">`;
            } else {
                html += `<div class="psb-group-label" style="--g-color:${group.color}"><span class="psb-g-dot"></span><span class="psb-g-text">${group.label}</span></div>`;
            }

            visibleItems.forEach(item => {
                const isActive = isItemActive(item);

                const cls = `psb-item${isActive ? ' active' : ''}`;
                const tooltip = item.label;
                const badgeId = `psb-badge-${item.id}`;
                const badge = ['loan','stock','log','permissions','kb_articles'].includes(item.id)
                    ? `<span class="psb-badge" id="${badgeId}" style="display:none">0</span>` : '';

                const gStyle = `style="--g-color:${group.color}"`;
                if (item.special === 'backup') {
                    html += `<button class="${cls}" data-tooltip="${tooltip}" id="psb-item-backup" ${gStyle} onclick="if(window.performBackup)performBackup()">
                        <i class="fas ${item.icon} psb-icon"></i>
                        <span class="psb-label">${item.label}</span>
                    </button>`;
                } else if (item.tab) {
                    html += `<button class="${cls}" data-tooltip="${tooltip}" data-tab="${item.tab}" ${gStyle} onclick="ptcSidebarNav('${item.tab}','${item.page}')">
                        <i class="fas ${item.icon} psb-icon"></i>
                        <span class="psb-label">${item.label}</span>
                        ${badge}
                    </button>`;
                } else {
                    const target = item.newTab ? ' target="_blank"' : '';
                    html += `<a class="${cls}" data-tooltip="${tooltip}" href="${item.href}"${target} ${gStyle}>
                        <i class="fas ${item.icon} psb-icon"></i>
                        <span class="psb-label">${item.label}</span>
                        ${badge}
                    </a>`;
                }
            });
            if (group.foldable) html += `</div></div>`;
        });

        html += `</nav>`;
        el.innerHTML = html;
        if (collapsed) el.classList.add('collapsed');
        _setCssVar(collapsed);

        document.getElementById('psb-toggle-btn')?.addEventListener('click', () => {
            const isNowCollapsed = el.classList.toggle('collapsed');
            localStorage.setItem(STORAGE_KEY, isNowCollapsed ? '1' : '0');
            _setCssVar(isNowCollapsed);
        });

        el.querySelectorAll('[data-fold]').forEach(btn => btn.addEventListener('click', () => {
            const key = btn.dataset.fold;
            const box = el.querySelector(`[data-fold-items="${key}"]`);
            const nowFolded = !box.classList.contains('folded');
            box.classList.toggle('folded', nowFolded);
            btn.classList.toggle('folded', nowFolded);
            btn.setAttribute('aria-expanded', String(!nowFolded));
            localStorage.setItem(FOLD_KEY + key, nowFolded ? '1' : '0');
        }));

        if (!isFirstRender) loadBadges(isAdmin, session);
    }

    // ── Navigation ───────────────────────────────────────────────────────
    window.ptcSidebarNav = function(tab, page) {
        if (page === 'admin') {
            // Only use in-page switchTab when actually on admin.html
            if (detectCurrentPage() === 'admin' && typeof window.switchTab === 'function') {
                window.switchTab(tab);
                document.body.dataset.tab = tab;
                updateActiveState();
            } else {
                location.href = `admin.html?tab=${tab}`;
            }
        }
    };

    function updateActiveState() {
        const currentTab = document.body.dataset.tab || '';
        document.querySelectorAll('#ptc-sidebar .psb-item[data-tab]').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === currentTab);
        });
    }

    window.ptcSidebarSetActive = function(tab) {
        document.body.dataset.tab = tab;
        updateActiveState();
    };

    // ── Badges ───────────────────────────────────────────────────────────
    function setBadge(id, n) {
        const el = document.getElementById(`psb-badge-${id}`);
        if (!el) return;
        el.textContent = n > 99 ? '99+' : n;
        el.style.display = n > 0 ? '' : 'none';
    }

    async function loadBadges(isAdmin, session) {
        try {
            const res = await fetch('api.php?action=get_pending_counts', { credentials: 'same-origin' });
            const d   = await res.json();
            const perms = (session && session.effective_permissions) || [];
            const hasAll = isAdmin || perms.includes('*');
            if (hasAll || perms.includes('loan.manage'))  setBadge('loan',  d.loan_pending      || 0);
            if (hasAll)                                   setBadge('log',   d.ticket_unassigned || 0);
            if (hasAll)                                   setBadge('stock', d.stock_low         || 0);
            if (hasAll || perms.includes('admin.manage_permissions')) setBadge('permissions', d.users_no_role || 0);
            if (hasAll || perms.includes('kb.manage')) setBadge('kb_articles', d.kb_draft_count || 0);
        } catch {}
    }

    // ── Init ─────────────────────────────────────────────────────────────
    async function init() {
        if (!document.getElementById('ptc-sidebar-css')) {
            const style = document.createElement('style');
            style.id = 'ptc-sidebar-css';
            style.textContent = CSS;
            document.head.appendChild(style);
        }

        const el = document.getElementById('ptc-sidebar');
        if (!el) return;

        // First render with cached role (no permissions) so sidebar appears instantly
        const cachedRole = sessionStorage.getItem('ptc_sess_role') || 'staff';
        render(el, cachedRole, true);

        const session = await getSession();
        const role = session?.role ?? 'guest';

        if (role !== 'admin' && role !== 'staff') {
            el.style.display = 'none';
            el.innerHTML = '';
            return;
        }

        sessionStorage.setItem('ptc_sess_role', role);

        // Re-render with full session (includes effective_permissions for perm filtering)
        render(el, session);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
