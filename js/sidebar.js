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
    // จัดใหม่ 23 ต.ค. 2026: เมนูเหลือเฉพาะ "งานที่ทำทุกวัน" + ตัวเลขงานค้าง · ของตั้งค่า 14 รายการย้ายไปหน้า settings.html
    // also: หน้าอื่นที่ให้เมนูนี้ติดไฮไลต์ ('page' หรือ 'admin:tab')
    const GROUPS = [
        {
            key: 'home', label: '', color: '#2563eb',
            items: [
                { id: 'dashboard',       icon: 'fa-gauge-high',         label: 'ภาพรวม',               tab: 'dashboard',        page: 'admin',             perm: null },
            ],
        },
        {
            key: 'it', label: 'งาน IT', color: '#2563eb',
            items: [
                { id: 'log',             icon: 'fa-clipboard-list',     label: 'งานช่าง IT',            href: 'log.html',        page: 'log',               perm: 'it_log.access' },
                { id: 'server_check_daily', icon: 'fa-server',          label: 'ตรวจห้อง Server',       href: 'server_check.html', page: 'server_check',    perm: 'it_log.access', badgeTone: 'warn', badgeTitle: 'ห้องที่ยังไม่ตรวจวันนี้' },
                { id: 'setup',           icon: 'fa-laptop-medical',     label: 'ติดตั้ง / คืนเครื่อง',   href: 'setup_list.html', page: 'setup_list',        perm: 'it_log.access', also: ['setup', 'setup_offboard'] },
                { id: 'it_links',        icon: 'fa-network-wired',      label: 'อุปกรณ์เครือข่าย',       href: 'it_links.html',   page: 'it_links',          perm: 'it_log.access' },
                { id: 'it_report',       icon: 'fa-chart-pie',          label: 'รายงาน IT',            href: 'it_report.html',  page: 'it_report',         perm: 'it_report.access' },
            ],
        },
        {
            key: 'office', label: 'งานธุรการ', color: '#8b5cf6',
            items: [
                { id: 'stock',           icon: 'fa-boxes-stacked',      label: 'สต็อก & เบิกของ',       href: 'stock_management.html', page: 'stock_management', perm: 'stock.manage', badgeTitle: 'ใบเบิกรออนุมัติ' },
                { id: 'loan',            icon: 'fa-rotate',             label: 'ยืม-คืนอุปกรณ์',        href: 'borrow_management.html', page: 'loan', perm: 'loan.manage', badgeTitle: 'รออนุมัติยืม' },
                { id: 'rooms',           icon: 'fa-calendar-check',     label: 'ห้องประชุม',            href: 'rooms.html?tab=keep', page: 'rooms', perm: 'room.manage', badgeTitle: 'การจองรออนุมัติ + ต้องเตรียมของวันนี้' },
                { id: 'task_center',     icon: 'fa-list-check',         label: 'Task Center',          href: 'task_center.html',page: 'task_center',       perm: null },
            ],
        },
        {
            key: 'data', label: 'ข้อมูล', color: '#10b981',
            items: [
                { id: 'it_assets',       icon: 'fa-laptop',             label: 'ทะเบียนพัสดุ',          tab: 'it_assets',        page: 'admin',             perm: 'asset.manage' },
                { id: 'employees',       icon: 'fa-users',              label: 'พนักงาน',              tab: 'employees',        page: 'admin',             perm: 'employee.manage' },
                { id: 'permissions',     icon: 'fa-user-shield',        label: 'สิทธิ์ผู้ใช้',            tab: 'permissions',      page: 'admin',             perm: 'admin.manage_permissions' },
                { id: 'kb_articles',     icon: 'fa-book-open',          label: 'คลังความรู้',           tab: 'kb_articles',      page: 'admin',             perm: 'kb.manage', badgeTone: 'muted', badgeTitle: 'บทความร่างรอตรวจ' },
                { id: 'directory',       icon: 'fa-address-book',       label: 'Contact List',         href: 'directory.html',  page: 'directory',         perm: null },
            ],
        },
        {
            key: 'settings', label: '', color: '#2563eb', divider: true,
            items: [
                { id: 'settings',        icon: 'fa-gear',               label: 'ตั้งค่า',               href: 'settings.html',   page: 'settings',          perm: 'any-setting', badgeTone: 'muted', badgeTitle: 'ผู้ใช้รอกำหนดสิทธิ์',
                  also: ['settings_system', 'settings_rooms', 'settings_notify', 'audit_log', 'admin_logo', 'admin:job_positions', 'admin:departments', 'admin:asset_categories', 'admin:locations', 'admin:kb_categories', 'admin:services', 'admin:server_check'] },
            ],
        },
    ];

    // รายการตั้งค่าทั้งหมด — หน้า settings.html ใช้ชุดนี้วาดการ์ด และช่องค้นหาในเมนูใช้ค้น (แหล่งเดียว ไม่ต้องแก้ 2 ที่)
    const SETTINGS = [
        { group: 'องค์กร', gIcon: 'fa-building', gColor: '#2563eb', items: [
            { id: 'company',          icon: 'fa-building',        title: 'บริษัทและโลโก้',           desc: 'ชื่อ ที่อยู่ โลโก้ หัวใบพิมพ์ LINE ประจำบริษัท', href: 'settings_system.html#company', perm: 'admin', keys: 'logo โลโก้ ที่อยู่ เลขภาษี company' },
            { id: 'departments',      icon: 'fa-sitemap',         title: 'แผนก',                    desc: 'รายชื่อแผนกของแต่ละบริษัท',                tab: 'departments',      perm: 'employee.manage', keys: 'department ฝ่าย' },
            { id: 'job_positions',    icon: 'fa-briefcase',       title: 'ตำแหน่งงาน',               desc: 'ตำแหน่งและระดับในผังองค์กร',               tab: 'job_positions',    perm: 'employee.manage', keys: 'position org chart ผังองค์กร' },
        ] },
        { group: 'อุปกรณ์และสถานที่', gIcon: 'fa-laptop', gColor: '#0f766e', items: [
            { id: 'asset_categories', icon: 'fa-tags',            title: 'หมวดหมู่อุปกรณ์',          desc: 'ใช้ในทะเบียนพัสดุ อายุการใช้งาน',          tab: 'asset_categories', perm: 'asset.manage', keys: 'category ประเภท' },
            { id: 'locations',        icon: 'fa-location-dot',    title: 'สถานที่',                  desc: 'อาคาร ชั้น ห้อง',                          tab: 'locations',        perm: 'asset.manage', keys: 'location อาคาร ห้อง' },
            { id: 'server_rooms',     icon: 'fa-door-closed',     title: 'ห้อง Server และรายการตรวจ', desc: 'ห้อง หัวข้อตรวจ จำนวนกล้อง',              tab: 'server_check',     perm: 'admin.manage_permissions', keys: 'server cctv กล้อง dvr' },
            { id: 'asset_qr',         icon: 'fa-qrcode',          title: 'QR สติกเกอร์พัสดุ',         desc: 'ลิงก์ที่ฝังใน QR บนสติกเกอร์',             href: 'settings_system.html#qr', perm: 'admin', keys: 'sticker สติกเกอร์ scan' },
        ] },
        { group: 'บริการและการแจ้งเตือน', gIcon: 'fa-headset', gColor: '#7c3aed', items: [
            { id: 'notify',           icon: 'fa-bell',            title: 'ศูนย์แจ้งเตือน',            desc: 'LINE / Telegram เรื่องไหนเข้ากลุ่มไหน',     href: 'settings_notify.html', perm: 'settings.notifications', keys: 'line telegram แจ้งเตือน token กุญแจ' },
            { id: 'rooms',            icon: 'fa-calendar-check',  title: 'ห้องประชุม',                desc: 'ห้องแต่ละบริษัท กติกาการจอง ของที่ขอเตรียม การอนุมัติ', href: 'settings_rooms.html', perm: 'room.manage' },
            { id: 'it_sla',           icon: 'fa-stopwatch',       title: 'ทีม IT และเวลาตอบสนอง',     desc: 'เบอร์ติดต่อ เวลารับเรื่อง (SLA)',           href: 'settings_system.html#it', perm: 'admin', keys: 'sla เบอร์ ติดต่อ เวลาทำการ' },
            { id: 'services',         icon: 'fa-link',            title: 'ลิงก์บริการหน้าแรก',        desc: 'ปุ่มทางลัดบนหน้าแรกของพนักงาน',           tab: 'services',         perm: 'employee.manage', keys: 'service link หน้าแรก ทางลัด' },
            { id: 'kb_categories',    icon: 'fa-folder-tree',     title: 'หมวดหมู่คลังความรู้',       desc: 'จัดกลุ่มบทความ KB',                        tab: 'kb_categories',    perm: 'kb.manage', keys: 'kb category คู่มือ' },
            { id: 'iso',              icon: 'fa-file-lines',      title: 'เลขที่เอกสาร (ISO)',        desc: 'เลขฟอร์มบนหัวใบพิมพ์',                    href: 'settings_system.html#iso', perm: 'admin', keys: 'iso form เลขฟอร์ม เอกสาร' },
            { id: 'app_icons',        icon: 'fa-mobile-screen',   title: 'ไอคอนแอป',                 desc: 'ไอคอนตอนติดตั้งเป็นแอป และบนแท็บ',        href: 'settings_system.html#icons', perm: 'admin', keys: 'icon pwa favicon' },
        ] },
        { group: 'ความปลอดภัยและดูแลระบบ', gIcon: 'fa-shield-halved', gColor: '#475569', items: [
            { id: 'permissions',      icon: 'fa-user-shield',     title: 'สิทธิ์ผู้ใช้',               desc: 'ใครเข้าหน้าไหนได้ บทบาท',                  tab: 'permissions',      perm: 'admin.manage_permissions', keys: 'role permission บทบาท สิทธิ์', badge: 'users_no_role' },
            { id: 'audit_log',        icon: 'fa-clock-rotate-left', title: 'ประวัติการใช้งาน',        desc: 'ใครทำอะไร เมื่อไหร่ (TISAX)',               href: 'audit_log.html',  perm: 'audit.view', keys: 'audit log ประวัติ tisax' },
            { id: 'backup',           icon: 'fa-floppy-disk',     title: 'สำรองข้อมูล',               desc: 'ดาวน์โหลดฐานข้อมูลและไฟล์เป็น .zip',       special: 'backup',        perm: 'admin.manage_permissions', keys: 'backup สำรอง' },
            { id: 'update_db',        icon: 'fa-database',        title: 'อัปเดตฐานข้อมูล',           desc: 'เครื่องมือขั้นสูง — ใช้ตอนอัปเดตระบบเท่านั้น', href: 'update_db.php', perm: 'admin.manage_permissions', danger: true, newTab: true, keys: 'database migrate' },
        ] },
    ];
    window.PTC_SETTINGS = SETTINGS;

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

/* ── มือถือ (≤768px): เมนูซ่อน กด ☰ แล้วเลื่อนออกจากซ้าย (เดิมกว้าง 220px ตลอด บังเนื้อหาทั้งจอ) ── */
.psb-mobile-btn { display: none; }
#psb-scrim { display: none; }
@media (max-width: 768px) {
    #ptc-sidebar, #ptc-sidebar.collapsed {
        position: fixed; top: 0; left: 0; bottom: 0; width: 272px; z-index: 1200;
        transform: translateX(-100%); transition: transform .22s ease; box-shadow: none;
    }
    #ptc-sidebar.mobile-open { transform: none; box-shadow: 8px 0 24px rgba(15,23,42,.18); }
    #ptc-sidebar.collapsed .psb-label, #ptc-sidebar.collapsed .psb-title, #ptc-sidebar.collapsed .psb-group-label { opacity: 1; max-width: none; width: auto; pointer-events: auto; }
    #ptc-sidebar .psb-toggle { display: none; }
    #psb-scrim { display: block; position: fixed; inset: 0; z-index: 1199; background: rgba(15,23,42,.4); opacity: 0; pointer-events: none; transition: opacity .2s; }
    #psb-scrim.on { opacity: 1; pointer-events: auto; }
    .psb-mobile-btn { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; flex-shrink: 0; border-radius: 9px; border: 1px solid var(--ptc-border, #e2e8f0); background: var(--ptc-surface, #fff); color: var(--ptc-text, #0f172a); cursor: pointer; }
    /* แถบบนให้พอดีจอ */
    .ptc-nav .ptc-nav-sub, .ptc-nav .nav-search-wrap, #warranty-alert-btn > :not(i):not(span) { display: none !important; }
    .ptc-nav { gap: 8px; padding-left: 10px !important; padding-right: 10px !important; }
    .ptc-nav .nav-user-info, .ptc-nav .nav-user-chip > i, .ptc-nav .nav-user-chip > .fa-chevron-down { display: none !important; } /* เหลือแค่รูปย่อผู้ใช้ */
    .ptc-nav-title { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
}

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
.psb-search { display:flex; align-items:center; gap:8px; margin:8px 10px 4px; padding:0 9px; height:34px; border:1px solid var(--ptc-border, #e2e8f0); border-radius:8px; color:var(--ptc-text-muted, #94a3b8); font-size:12px; background:var(--ptc-surface, #fff); }
.psb-search input { flex:1; min-width:0; border:0; outline:0; background:transparent; color:var(--ptc-text, #0f172a); font:inherit; font-size:12.5px; }
.psb-search input::-webkit-search-cancel-button { display:none; }
.psb-search kbd { font-family:inherit; font-size:10px; border:1px solid var(--ptc-border, #e2e8f0); border-radius:4px; padding:0 4px; white-space:nowrap; }
.psb-search:focus-within { border-color:var(--ptc-primary, #2563eb); box-shadow:0 0 0 3px rgba(37,99,235,.12); }
#ptc-sidebar.collapsed .psb-search { display:none; }
@media (hover: none) { .psb-search kbd { display:none; } }
.psb-results { padding:4px 8px; overflow-y:auto; flex:1; }
.psb-hit { display:flex; gap:10px; align-items:center; padding:7px 9px; border-radius:8px; text-decoration:none; color:var(--ptc-text-2, #334155); }
.psb-hit i { width:16px; text-align:center; color:var(--ptc-text-muted, #94a3b8); font-size:13px; }
.psb-hit b { display:block; font-size:12.5px; font-weight:600; } .psb-hit small { display:block; font-size:10.5px; color:var(--ptc-text-muted, #94a3b8); }
.psb-hit.sel, .psb-hit:hover { background:var(--ptc-primary-light, #eff6ff); }
.psb-hit.sel b { color:var(--ptc-primary, #2563eb); }
.psb-nohit { padding:14px 10px; font-size:12px; color:var(--ptc-text-muted, #94a3b8); text-align:center; }
.psb-badge.warn { background:#d97706 !important; color:#fff !important; }
.psb-badge.muted { background:var(--ptc-surface-2, #f1f5f9) !important; color:var(--ptc-text-2, #475569) !important; border:1px solid var(--ptc-border, #e2e8f0); }
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
    window.ptcCanSee = (item, session) => canSeeItem(item, session);
    function canSeeItem(item, session) {
        if (!session || !session.role) return false;
        const role = session.role;
        if (role !== 'admin' && role !== 'staff') return false;
        const perms = session.effective_permissions || [];
        const hasAll = role === 'admin' || perms.includes('*');
        if (item.perm === 'admin') return hasAll;
        if (item.perm === null) return true;  // visible to all admin/staff
        if (item.perm === 'any-setting') return SETTINGS.some(g => g.items.some(x => canSeeItem(x, session)));
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
            <span class="psb-title">ระบบจัดการ</span>
            <button class="psb-toggle" id="psb-toggle-btn" title="ย่อ/ขยาย sidebar">
                <i class="fas fa-bars" style="font-size:13px"></i>
            </button>
        </div>
        <div class="psb-search"><i class="fas fa-magnifying-glass"></i><input id="psb-q" type="search" placeholder="ค้นหาเมนู / ตั้งค่า…" autocomplete="off">${window.ptcCmd ? '' : '<kbd>Ctrl K</kbd>'}</div>
        <div class="psb-results" id="psb-results" style="display:none"></div>
        <nav class="psb-nav">`;

        const isItemActive = (item) => (item.tab && currentPage === 'admin' && currentTab === item.tab)
                                    || (item.page && !item.tab && currentPage === item.page)
                                    || (item.also || []).some(a => a === currentPage || a === `${currentPage}:${currentTab}`);

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
            } else if (group.divider) {
                html += `<div class="psb-divider"></div>`;
            } else if (group.label) {
                html += `<div class="psb-group-label" style="--g-color:${group.color}"><span class="psb-g-dot"></span><span class="psb-g-text">${group.label}</span></div>`;
            }

            visibleItems.forEach(item => {
                const isActive = isItemActive(item);

                const cls = `psb-item${isActive ? ' active' : ''}`;
                const tooltip = item.label;
                const badgeId = `psb-badge-${item.id}`;
                const badge = ['loan','stock','rooms','log','server_check_daily','kb_articles','settings'].includes(item.id)
                    ? `<span class="psb-badge${item.badgeTone ? ' ' + item.badgeTone : ''}" id="${badgeId}" style="display:none"${item.badgeTitle ? ` title="${item.badgeTitle}"` : ''}>0</span>` : '';

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

        setupSearch(el, session);
        if (!isFirstRender) loadBadges(isAdmin, session);
    }

    // ── ค้นหาเมนู + การตั้งค่าทุกตัว (Ctrl+K) ──
    function setupSearch(el, session) {
        const q = el.querySelector('#psb-q'), box = el.querySelector('#psb-results'), nav = el.querySelector('.psb-nav');
        if (!q) return;
        const norm = v => String(v || '').toLowerCase();
        const pool = [];
        GROUPS.forEach(g => g.items.filter(i => canSeeItem(i, session)).forEach(i => pool.push({ icon: i.icon, title: i.label, sub: g.label || 'เมนู', href: i.href || (i.tab ? `admin.html?tab=${i.tab}` : '#'), keys: '' })));
        SETTINGS.forEach(g => g.items.filter(i => canSeeItem(i, session) && !i.special).forEach(i => pool.push({ icon: i.icon, title: i.title, sub: 'ตั้งค่า · ' + g.group, href: i.href || `admin.html?tab=${i.tab}`, keys: (i.desc || '') + ' ' + (i.keys || ''), newTab: i.newTab })));
        let sel = 0, hits = [];
        const show = () => {
            const t = norm(q.value.trim());
            if (!t) { box.style.display = 'none'; nav.style.display = ''; return; }
            hits = pool.filter(x => norm(x.title + ' ' + x.sub + ' ' + x.keys).includes(t)).slice(0, 12);
            sel = Math.min(sel, Math.max(0, hits.length - 1));
            nav.style.display = 'none'; box.style.display = '';
            box.innerHTML = hits.length ? hits.map((h, i) => `<a class="psb-hit${i === sel ? ' sel' : ''}" href="${h.href}"${h.newTab ? ' target="_blank"' : ''}><i class="fas ${h.icon}"></i><span><b>${h.title}</b><small>${h.sub}</small></span></a>`).join('')
                : '<div class="psb-nohit">ไม่พบ — ลองคำอื่น</div>';
        };
        q.addEventListener('input', () => { sel = 0; show(); });
        q.addEventListener('keydown', e => {
            if (e.key === 'ArrowDown') { sel = Math.min(sel + 1, hits.length - 1); show(); e.preventDefault(); }
            else if (e.key === 'ArrowUp') { sel = Math.max(sel - 1, 0); show(); e.preventDefault(); }
            else if (e.key === 'Enter' && hits[sel]) { e.preventDefault(); hits[sel].newTab ? window.open(hits[sel].href) : (location.href = hits[sel].href); }
            else if (e.key === 'Escape') { q.value = ''; show(); q.blur(); }
        });
        if (!window._psbKeyBound) {
            window._psbKeyBound = true;
            document.addEventListener('keydown', e => {
                // หน้าที่มีเมนูลัดของตัวเอง (admin.html → js/ptc-cmd.js) ให้ตัวนั้นรับ Ctrl+K
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k' && !window.ptcCmd) {
                    const inp = document.getElementById('psb-q'); if (!inp) return;
                    e.preventDefault();
                    const sb = document.getElementById('ptc-sidebar');
                    if (sb.classList.contains('collapsed')) { sb.classList.remove('collapsed'); localStorage.setItem(STORAGE_KEY, '0'); _setCssVar(false); }
                    if (window.innerWidth < 900) document.querySelector('.psb-mobile-btn')?.click();
                    inp.focus(); inp.select();
                }
            });
        }
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
            if (hasAll || perms.includes('it_log.access')) setBadge('log',   d.ticket_unassigned || 0);
            if (hasAll || perms.includes('it_log.access')) setBadge('server_check_daily', d.server_check_missing || 0);
            if (hasAll || perms.includes('stock.manage')) setBadge('stock', d.stock_pending     || 0);
            if (hasAll || perms.includes('room.manage'))  setBadge('rooms', (d.room_pending || 0) + (d.room_prep || 0));
            if (hasAll || perms.includes('admin.manage_permissions')) setBadge('settings', d.users_no_role || 0);
            if (hasAll || perms.includes('kb.manage')) setBadge('kb_articles', d.kb_draft_count || 0);
            window.ptcPendingCounts = d;
            document.dispatchEvent(new CustomEvent('ptc:pending-counts', { detail: d }));
        } catch {}
    }

    // ── Init ─────────────────────────────────────────────────────────────
    function setupMobile(el) {
        if (document.getElementById('psb-scrim')) return;
        const scrim = document.createElement('div');
        scrim.id = 'psb-scrim';
        document.body.appendChild(scrim);
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'psb-mobile-btn';
        btn.title = 'เมนู';
        btn.setAttribute('aria-label', 'เปิดเมนู');
        btn.innerHTML = '<i class="fas fa-bars"></i>';
        const nav = document.querySelector('.ptc-nav');
        if (nav) nav.insertBefore(btn, nav.firstChild);
        else { btn.style.cssText = 'position:fixed;top:10px;left:10px;z-index:1100'; document.body.appendChild(btn); }
        const open = on => { el.classList.toggle('mobile-open', on); scrim.classList.toggle('on', on); };
        btn.addEventListener('click', () => open(!el.classList.contains('mobile-open')));
        scrim.addEventListener('click', () => open(false));
        el.addEventListener('click', e => { if (e.target.closest('.psb-item')) open(false); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') open(false); });
    }

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
        setupMobile(el); // ปุ่ม ☰ เฉพาะคนที่เห็นเมนู

        // Re-render with full session (includes effective_permissions for perm filtering)
        render(el, session);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
