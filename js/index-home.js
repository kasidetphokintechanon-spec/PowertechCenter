/**
 * PowertechCenter — index.html home dashboard (lightweight bootstrap)
 */
(function () {
    'use strict';

    const API = 'api.php';
    const $ = (id) => document.getElementById(id);

    const STATUS_LABEL = {
        Pending: 'รอรับเรื่อง', Received: 'รับเรื่องแล้ว', 'In Progress': 'กำลังซ่อม',
        'Waiting for Parts': 'รออะไหล่', 'Temporary Fix': 'แก้ชั่วคราว',
        'PR Submitted': 'ส่ง PR', 'PO Issued': 'ออก PO', 'Parts Received': 'ได้อะไหล่',
        'Awaiting Confirmation': 'รอยืนยัน', Completed: 'เสร็จสิ้น', Cancelled: 'ยกเลิก', Unrepairable: 'ซ่อมไม่ได้'
    };
    const STATUS_DOT = {
        Pending: '#f59e0b', Received: '#d97706', 'In Progress': '#3b82f6',
        'Waiting for Parts': '#ea580c', 'Temporary Fix': '#6366f1',
        'PR Submitted': '#0284c7', 'PO Issued': '#0d9488', 'Parts Received': '#0891b2',
        'Awaiting Confirmation': '#10b981', Completed: '#10b981', Cancelled: '#94a3b8', Unrepairable: '#ef4444'
    };

    let session = {};
    let isAdmin   = false;
    let isItStaff = false;
    let serviceHealthRunId = 0;
    const serviceLinkStateCache = Object.create(null);
    const FAILOVER_DEBUG = (() => {
        try {
            const q = new URLSearchParams(window.location.search);
            const flag = String(q.get('debugFailover') || '').toLowerCase();
            if (flag === '1' || flag === 'true' || flag === 'yes') return true;
            return window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        } catch (_) {
            return false;
        }
    })();

    function logFailover(event, meta) {
        if (!FAILOVER_DEBUG) return;
        console.info('[ServiceFailover]', event, meta || {});
    }

    function esc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    async function purgeServiceWorker() {
        try {
            if ('serviceWorker' in navigator) {
                const regs = await navigator.serviceWorker.getRegistrations();
                await Promise.all(regs.map((r) => r.unregister()));
            }
            if ('caches' in window) {
                const keys = await caches.keys();
                await Promise.all(keys.map((k) => caches.delete(k)));
            }
        } catch (_) { /* ignore */ }
    }

    async function fetchJson(url, ms) {
        const ctrl = new AbortController();
        const t = setTimeout(() => ctrl.abort(), ms || 15000);
        try {
            const res = await fetch(url, { signal: ctrl.signal, cache: 'no-store', credentials: 'same-origin' });
            const text = await res.text();
            // 401 = ยังไม่ล็อกอิน (ด่านกลางใน api.php) → ส่ง JSON กลับให้ boot() พาไปหน้า login แทนขึ้น "โหลดไม่สำเร็จ"
            if (!res.ok && res.status !== 401) throw new Error('HTTP ' + res.status);
            return JSON.parse(text);
        } finally {
            clearTimeout(t);
        }
    }

    function showStatusMessage(title, sub, spin) {
        const el = $('my-status-content');
        if (!el) return;
        el.innerHTML = '<div class="status-card-loading">' +
            (spin ? '<i class="fas fa-spinner fa-spin"></i>' : '<i class="fas fa-circle-info"></i>') +
            '</div><div style="margin-top:8px;font-weight:600">' + esc(title) + '</div>' +
            '<div style="font-size:13px;opacity:.75;margin-top:4px">' + esc(sub) + '</div>';
    }

    function sessionHasPerm(key) {
        const eff = session.effective_permissions || [];
        if (eff.includes('*')) return true;
        return eff.includes(key);
    }

    const ROLE_LABEL = {
        admin: 'ผู้ดูแลระบบ', it: 'IT Staff', technician: 'ช่างเทคนิค',
        manager: 'ผู้จัดการ', supervisor: 'หัวหน้างาน', user: 'พนักงาน'
    };
    const ROLE_COLOR = {
        admin: '#2563eb', it: '#10b981', technician: '#f59e0b',
        manager: '#8b5cf6', supervisor: '#0284c7', user: '#64748b'
    };

    function applySession(data) {
        session = data || {};
        const name = session.display_name || 'User';
        const role = String(session.role || '').toLowerCase();

        // User chip
        const HONORIFICS = /^(mr\.?|mrs\.?|ms\.?|dr\.?|นาย|นาง|น\.ส\.?|นางสาว)\s+/i;
        const cleanName  = name.replace(HONORIFICS, '').trim() || name;
        const parts      = cleanName.split(/\s+/);
        const displayName = parts.length >= 2 ? parts[0] + ' ' + parts[1] : cleanName;
        const initials   = parts.map(w => w[0] || '').join('').toUpperCase().slice(0, 2) || 'U';
        const avatarEl = $('navUserAvatar');
        if (avatarEl) {
            avatarEl.textContent = initials;
            avatarEl.style.background = ROLE_COLOR[role] || '#64748b';
        }
        if ($('navUserName')) $('navUserName').textContent = displayName;
        if ($('navUserRole')) $('navUserRole').textContent = ROLE_LABEL[role] || session.primary_department || 'พนักงาน';

        if ($('welcomeText')) $('welcomeText').textContent = 'สวัสดี, ' + name + ' 👋';

        isAdmin   = role === 'admin';
        isItStaff = String(session.primary_department || '').toUpperCase() === 'IT';

        const adminLink = $('adminLink');
        const perms = session.effective_permissions || [];
        const hasAll = isAdmin || perms.includes('*');

        // ตรงกับ permissions_registry.php → getAdminMenuPermissionMap()
        const PERM_LINKS = [
            { perm: 'employee.manage',          icon: 'fa-users',              label: 'จัดการพนักงาน',      href: 'admin.html?tab=employees',    ib: '#d1fae5', ic: '#059669' },
            { perm: 'asset.manage',             icon: 'fa-laptop',             label: 'ทะเบียนพัสดุ IT',    href: 'admin.html?tab=it_assets',    ib: '#fef3c7', ic: '#d97706' },
            { perm: 'loan.manage',              icon: 'fa-rotate',             label: 'ยืม-คืน',             href: 'borrow_management.html',      ib: '#ffedd5', ic: '#ea580c' },
            { perm: 'stock.manage',             icon: 'fa-boxes-stacked',      label: 'Stock',               href: 'stock_management.html',       ib: '#dbeafe', ic: '#2563eb' },
            { perm: 'kb.manage',                icon: 'fa-book-open',          label: 'คลังความรู้ (KB)',    href: 'admin.html?tab=kb_articles',  ib: '#e0f2fe', ic: '#0284c7' },
            { perm: 'it_log.access',            icon: 'fa-clipboard-list',     label: 'งานช่าง IT',          href: 'log.html',                    ib: '#ede9fe', ic: '#7c3aed' },
            { perm: 'it_report.access',         icon: 'fa-chart-bar',          label: 'IT Report',           href: 'it_report.html',              ib: '#ffe4e6', ic: '#e11d48' },
            { perm: 'settings.notifications',   icon: 'fa-bell',               label: 'ตั้งค่าแจ้งเตือน',    href: 'settings_notify.html',        ib: '#fef3c7', ic: '#d97706' },
            { perm: 'audit.view',               icon: 'fa-clock-rotate-left',  label: 'Audit Log',           href: 'audit_log.html',              ib: '#e2e8f0', ic: '#475569' },
            { perm: 'admin.manage_permissions', icon: 'fa-user-shield',        label: 'จัดการสิทธิ์',        href: 'admin.html?tab=permissions',  ib: '#fee2e2', ic: '#dc2626' },
        ];

        // เข้าระบบจัดการได้ (admin หรือมีสิทธิ์ admin.access_admin) → ทางเข้าช่องเดียว เมนูข้างในจัดหมวดให้แล้ว
        if (hasAll || session.can_access_admin) {
            if (adminLink) {
                adminLink.classList.remove('index-admin-link-hidden');
                adminLink.classList.remove('hidden');
            }
        } else if (role === 'staff') {
            const staffLinks = PERM_LINKS.filter(p => perms.includes(p.perm));
            if (staffLinks.length) {
                // Hide generic Admin link, inject specific links (guard against double-call)
                if (adminLink) adminLink.style.display = 'none';
                const nav = document.querySelector('#sidebar-left .sidebar-nav-stack');
                if (nav && !nav.querySelector('[data-perm-link]')) {
                    staffLinks.forEach(p => {
                        const a = document.createElement('a');
                        a.href = p.href;
                        a.className = 'snav snav-item index-sidebar-mgmt-link';
                        a.dataset.permLink = p.perm;
                        a.innerHTML = `<span class="snav-icon" style="--icon-bg:${p.ib};--icon-color:${p.ic}"><i class="fa ${p.icon}"></i></span><span class="snav-label">${p.label}</span>`;
                        // mobile: close sidebar on click (mirrors initSidebar behaviour)
                        a.addEventListener('click', () => { if (window.innerWidth < 1024) window.closeSidebar?.(); });
                        nav.appendChild(a);
                    });
                }
            }
        }

        // ตัวเลขบน "ระบบจัดการ" = งานค้างรวม (งานซ่อมรอรับ + ยืมรออนุมัติ + ของใกล้หมด)
        if (adminLink && !adminLink.classList.contains('index-admin-link-hidden')) {
            fetch('api.php?action=get_pending_counts', { credentials: 'same-origin' }).then((r) => r.json()).then((d) => {
                const n = (+d.ticket_unassigned || 0) + (+d.loan_pending || 0) + (+d.stock_low || 0);
                const b = $('mgmtBadge');
                if (b) { b.textContent = n > 99 ? '99+' : n; b.classList.toggle('hidden', !n); b.title = 'งานซ่อมรอรับ ' + (d.ticket_unassigned || 0) + ' · ยืมรออนุมัติ ' + (d.loan_pending || 0) + ' · ของใกล้หมด ' + (d.stock_low || 0); }
            }).catch(() => {});
        }

        const hasMgmt = Array.from(document.querySelectorAll('.index-sidebar-mgmt-link')).some((el) => {
            return !el.classList.contains('hidden') && !el.classList.contains('index-admin-link-hidden') && el.style.display !== 'none';
        });
        if ($('sidebarMgmtDivider')) $('sidebarMgmtDivider').classList.toggle('hidden', !hasMgmt);
        if ($('sidebarMgmtLabel')) $('sidebarMgmtLabel').classList.toggle('hidden', !hasMgmt);

        const logos = { PT4: 'uploads/logos/pt4_logo.png', PTA: 'uploads/logos/pta_logo.png', PTE: 'uploads/logos/pte_logo.png' };
        const img = $('navLogoImg');
        const icon = $('navLogoIcon');
        if (session.company && logos[session.company] && img) {
            img.src = logos[session.company];
            img.style.display = '';
            if (icon) icon.style.display = 'none';
        }
        const hr = new Date().getHours();
        const greet = hr < 12 ? 'สวัสดีตอนเช้า' : hr < 17 ? 'สวัสดีตอนบ่าย' : 'สวัสดีตอนเย็น';
        if ($('welcomeText')) $('welcomeText').textContent = greet + ', ' + (displayName.split(' ')[0] || '') + ' 👋';
        if ($('companyTitle') && session.company_full_name) {
            $('companyTitle').textContent = session.company_full_name;
            if (session.company) $('companyTitle').setAttribute('data-short', session.company);
        }
        const now = new Date();
        if ($('dateText')) {
            $('dateText').textContent = now.toLocaleDateString('th-TH', {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
            });
        }
    }

    function renderKpi(kpi) {
        const strip = $('kpiStrip');
        if (!strip || !kpi) return;
        const items = [
            { val: kpi.pending, lbl: 'รอดำเนินการ', icon: 'fa-clock', bg: '#fff7ed', color: '#f97316', link: 'log.html' },
            { val: kpi.in_progress, lbl: 'กำลังซ่อม', icon: 'fa-tools', bg: '#eff6ff', color: '#2563eb', link: 'log.html' },
            { val: kpi.completed_today, lbl: 'เสร็จวันนี้', icon: 'fa-check-circle', bg: '#f0fdf4', color: '#16a34a', link: 'log.html' },
            { val: kpi.loan_pending, lbl: 'ยืมรออนุมัติ', icon: 'fa-laptop', bg: '#faf5ff', color: '#9333ea', link: 'borrow_management.html' }
        ];
        strip.innerHTML = items.map((k) =>
            '<div class="kpi-card" data-href="' + esc(k.link) + '" style="min-width:100px;cursor:pointer">' +
            '<div class="kpi-icon" style="background:' + k.bg + ';color:' + k.color + '"><i class="fas ' + k.icon + '"></i></div>' +
            '<div><div class="kpi-val" style="color:' + k.color + '">' + esc(k.val) + '</div><div class="kpi-lbl">' + esc(k.lbl) + '</div></div></div>'
        ).join('');
        strip.querySelectorAll('.kpi-card').forEach((card) => {
            card.addEventListener('click', () => {
                const href = card.getAttribute('data-href');
                if (href) window.location.href = href;
            });
        });
    }

    function renderMyStatus(tickets, loans) {
        const container = $('my-status-content');
        if (!container) return;
        const ticketList = Array.isArray(tickets) ? tickets : [];
        const loanList = Array.isArray(loans) ? loans : [];
        const total = ticketList.length + loanList.length;

        const uidMe = String(session.user_id || '');
        const mineReq = ticketList.filter((l) => String(l.requester_id ?? '') === uidMe);
        // ตัวเลขบนเมนู "งานแจ้งซ่อมของฉัน" = งานที่ยังไม่ปิด
        const myOpen = mineReq.filter((l) => !['Completed', 'Cancelled', 'Unrepairable'].includes(l.status)).length;
        const myBadge = $('myTicketBadge');
        if (myBadge) { myBadge.textContent = myOpen; myBadge.classList.toggle('hidden', !myOpen); }
        const kp = [
            ['รอรับเรื่อง', mineReq.filter((l) => l.status === 'Pending').length, 'var(--ptc-orange, #ea580c)'],
            ['กำลังดำเนินการ', mineReq.filter((l) => l.status !== 'Pending' && l.status !== 'Awaiting Confirmation').length, 'var(--ptc-primary, #2563eb)'],
            ['รอคุณยืนยัน', mineReq.filter((l) => l.status === 'Awaiting Confirmation').length, 'var(--ptc-success, #16a34a)'],
            ['ยืมอยู่', loanList.length, 'var(--ptc-purple, #7c3aed)'],
        ];
        const head = '<div class="h2-panel-h">งานของฉัน<a href="request.html?tab=tickets">ดูทั้งหมด <i class="fas fa-arrow-right"></i></a></div>' +
            '<div class="h2-kp">' + kp.map((k) => '<div><b style="color:' + k[2] + '">' + k[1] + '</b><span>' + k[0] + '</span></div>').join('') + '</div>';
        if (total === 0) {
            container.innerHTML = head + '<div class="status-clear"><i class="fas fa-circle-check"></i>' +
                '<div><b>ไม่มีงานค้าง</b><span>งานแจ้งซ่อมและการยืมของคุณเรียบร้อยทั้งหมด</span></div></div>';
            return;
        }
        const STEP = { Pending: 1, Received: 2, 'Awaiting Confirmation': 4 };
        const steps = (n, cls) => '<div class="h2-steps ' + (cls || '') + '">' + [1, 2, 3, 4, 5].map((i) => '<i class="' + (i <= n ? 'on' : '') + '"></i>').join('') + '</div>';
        const shortName = (v) => String(v || '').replace(/^(Mr\.|Ms\.|Mrs\.|Dr\.|นาย|นาง|นางสาว)\s*/i, '').split(' ')[0];
        const jobs = [];
        ticketList.slice(0, 5).forEach((l) => {
            const lbl = STATUS_LABEL[l.status] || l.status;
            const dot = STATUS_DOT[l.status] || '#94a3b8';
            const isTech = String(l.servicedById ?? '') === uidMe;
            const who = l.status === 'Awaiting Confirmation' && !isTech ? 'กดยืนยันปิดงานได้เลย'
                : isTech ? 'คุณเป็นช่างที่รับผิดชอบ' : (l.servicedBy ? 'ช่าง ' + esc(shortName(l.servicedBy)) : 'ยังไม่มีช่างรับเรื่อง');
            jobs.push('<a class="h2-job" href="request.html?tab=tickets"><div class="h2-job-top"><b>' + esc(l.problem || ('งานซ่อม ' + l.id)) + '</b>' +
                '<span class="h2-pill" style="--c:' + dot + '">' + esc(lbl) + '</span></div>' +
                '<div class="h2-job-sub">' + esc(l.id) + ' · ' + who + '</div>' + steps(STEP[l.status] || 3) + '</a>');
        });
        loanList.forEach((l) => {
            const pending = l.loan_status === 'Pending';
            jobs.push('<a class="h2-job" href="my_borrow_history.html"><div class="h2-job-top"><b>ยืม ' + esc(l.asset_name || '-') + '</b>' +
                '<span class="h2-pill" style="--c:' + (pending ? '#d97706' : '#7c3aed') + '">' + (pending ? 'รออนุมัติ' : 'ยืมอยู่') + '</span></div>' +
                '<div class="h2-job-sub">' + (pending ? 'รอ IT อนุมัติ' : 'อย่าลืมคืนตามกำหนด') + '</div>' + steps(pending ? 2 : 5, pending ? '' : 'loan') + '</a>');
        });
        const more = ticketList.length > 5 ? '<a class="h2-more" href="request.html?tab=tickets">ดูอีก ' + (ticketList.length - 5) + ' งาน</a>' : '';
        container.innerHTML = head + jobs.join('') + more;
    }

    function canonicalizeRawServiceUrl(raw) {
        let v = String(raw || '').trim();
        // Decode common legacy escape forms from admin/DB.
        v = v.replace(/&amp;/gi, '&');
        v = v.replace(/^['"]+|['"]+$/g, '');
        v = v.replace(/\\\//g, '/').replace(/\\/g, '/').trim();
        if (!v) return '';
        // Normalize accidental extra slashes after scheme.
        const m = v.match(/^([a-z]+:)(.*)$/i);
        if (m) {
            const scheme = m[1];
            let rest = m[2].trim().replace(/^\/+/, '//');
            if (!rest.startsWith('//')) rest = '//' + rest;
            v = scheme + rest;
        }
        return v;
    }

    function isInternalServicePath(v) {
        if (!v) return false;
        if (/^\/|^\.\.?\//.test(v)) return true;
        if (/^[^?#]+\.(?:html?|php)(?:[?#].*)?$/i.test(v)) return true;
        // Plain app routes without host/scheme should remain internal.
        return !/^https?:\/\//i.test(v) && !/[.:]/.test(v.split('/')[0] || '');
    }

    function isPrivateIPv4(host) {
        const m = String(host || '').match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/);
        if (!m) return false;
        const a = parseInt(m[1], 10);
        const b = parseInt(m[2], 10);
        if (a === 10) return true;
        if (a === 172 && b >= 16 && b <= 31) return true;
        if (a === 192 && b === 168) return true;
        if (a === 127) return true;
        return false;
    }

    function isLocalOrPrivateHost(host) {
        const h = String(host || '').toLowerCase();
        if (!h) return false;
        if (h === 'localhost' || h.endsWith('.local')) return true;
        return isPrivateIPv4(h);
    }

    function isPrivateNetworkUrl(url) {
        try {
            const u = new URL(url);
            return isLocalOrPrivateHost(u.hostname);
        } catch (_) {
            return false;
        }
    }

    function isPrivateNetworkLikeInput(value) {
        const v = String(value || '').trim();
        if (!v) return false;
        // Supports inputs like 192.168.0.11/app, 10.0.0.5:8080, localhost/app
        const hostCandidate = v.replace(/^https?:\/\//i, '').split('/')[0].split(':')[0];
        return isLocalOrPrivateHost(hostCandidate);
    }

    function isLikelyOutsideCompanyNetwork() {
        return !isLocalOrPrivateHost(window.location.hostname);
    }

    function normalizeServiceUrlInput(raw, required) {
        let v = canonicalizeRawServiceUrl(raw);
        if (!v) {
            if (required) throw new Error('missing primary');
            return '';
        }
        if (isInternalServicePath(v)) return v;
        // Allow flexible inputs (path/program links) without forcing scheme.
        if (!/^https?:\/\//i.test(v)) return v;
        const parsed = new URL(v);
        if (!['http:', 'https:'].includes(parsed.protocol)) throw new Error('invalid protocol');
        if (!parsed.hostname) throw new Error('missing hostname');
        return parsed.toString();
    }

    async function probeUrl(url, timeoutMs) {
        if (!url) return false;
        const ctrl = new AbortController();
        const timer = setTimeout(() => ctrl.abort(), timeoutMs || 1300);
        try {
            try {
                await fetch(url, { method: 'HEAD', mode: 'no-cors', cache: 'no-store', signal: ctrl.signal });
            } catch (_) {
                // Some services reject HEAD; fallback to lightweight GET probe.
                await fetch(url, { method: 'GET', mode: 'no-cors', cache: 'no-store', signal: ctrl.signal });
            }
            return true;
        } catch (_) {
            return false;
        } finally {
            clearTimeout(timer);
        }
    }

    async function resolveServiceState(primary, backup, timeoutMs) {
        let primaryUrl = '';
        let backupUrl = '';
        try {
            primaryUrl = normalizeServiceUrlInput(primary, true);
            backupUrl = normalizeServiceUrlInput(backup, false);
        } catch (_) {
            return { state: 'down', primaryUrl: String(primary || '').trim(), backupUrl: String(backup || '').trim(), reason: 'invalid-url' };
        }

        // Business rule: if user is likely outside company network and primary is private IP,
        // prefer backup immediately when available.
        const outsideCompany = isLikelyOutsideCompanyNetwork();
        const primaryIsPrivate = isPrivateNetworkUrl(primaryUrl) || isPrivateNetworkLikeInput(primaryUrl);
        if (outsideCompany && primaryIsPrivate) {
            if (backupUrl) {
                return { state: 'backup', primaryUrl, backupUrl, reason: 'outside-company-prefer-backup' };
            }
            return { state: 'down', primaryUrl, backupUrl, reason: 'outside-company-private-primary-no-backup' };
        }

        // Internal app routes/files and flexible non-http paths are treated as primary-ready.
        if (!/^https?:\/\//i.test(primaryUrl)) {
            return { state: 'primary', primaryUrl, backupUrl, reason: 'internal-or-flex-primary' };
        }



        const primaryOk = await probeUrl(primaryUrl, timeoutMs || 1300);
        if (primaryOk) return { state: 'primary', primaryUrl, backupUrl, reason: 'primary-ok' };

        if (backupUrl) {
            const backupOk = await probeUrl(backupUrl, timeoutMs || 1300);
            if (backupOk) return { state: 'backup', primaryUrl, backupUrl, reason: 'primary-failed-backup-ok' };
        }
        return { state: 'down', primaryUrl, backupUrl, reason: backupUrl ? 'both-unreachable' : 'primary-unreachable-no-backup' };
    }

    function renderServices(list) {
        const grid = $('appGrid');
        if (!grid) return;
        const items = (Array.isArray(list) && list.length) ? list : [
            { title: 'สมุดรายชื่อพนักงาน', sub: 'Directory', icon: 'fa-address-book', color: '#f97316', url: 'directory.html' },
            { title: 'ระบบแจ้งซ่อม ไอที', sub: 'IT Service', icon: 'fa-cog', color: '#2563eb', url: 'request.html' },
            { title: 'ยืม-คืนอุปกรณ์ IT', sub: 'IT Loan', icon: 'fa-exchange-alt', color: '#7c3aed', url: 'loan.html' },
            { title: 'ศูนย์งานช่าง', sub: 'Task Center', icon: 'fa-tools', color: '#0891b2', url: 'task_center.html' }
        ];
        grid.innerHTML = '';
        const checkableItems = [];
        const GROUPS = [
            { key: 'it', t: 'แจ้งปัญหา & IT', c: '#6366f1' },
            { key: 'booking', t: 'จอง', c: '#14b8a6' },
            { key: 'stock', t: 'เบิก / ยืม', c: '#f59e0b' },
            { key: 'info', t: 'ข้อมูล & ระบบงาน', c: '#3b82f6' },
        ];
        const groupOf = (tone) => tone === 'itops' || tone === 'knowledge' ? 'it' : tone === 'booking' ? 'booking' : tone === 'stock' ? 'stock' : 'info';
        const boxes = {};
        GROUPS.forEach((g) => {
            const box = document.createElement('div');
            box.className = 'h2-group';
            box.innerHTML = '<div class="h2-group-h"><span style="background:' + g.c + '"></span>' + g.t + '<small></small></div><div class="h2-svc"></div>';
            boxes[g.key] = box;
        });
        const resolveCardTone = (item) => {
            const text = (String(item.title || '') + ' ' + String(item.sub || '') + ' ' + String(item.url || '')).toLowerCase();
            if (/directory|รายชื่อ|พนักงาน|hr/.test(text)) return 'people';
            if (/request|log|แจ้งซ่อม|task|ticket|mt/.test(text)) return 'itops';
            if (/erp|program|ระบบงาน|เอกสาร|workflow/.test(text)) return 'erp';
            if (/room|car|จองห้อง|จองรถ|booking/.test(text)) return 'booking';
            if (/kb|knowledge|ความรู้|guide|help/.test(text)) return 'knowledge';
            if (/loan|stock|ยืม|คืน|เบิก|คลัง|maid|stationery|asset/.test(text)) return 'stock';
            return 'default';
        };
        items.forEach((item, idx) => {
            const card = document.createElement('a');
            card.className = 'app-card';
            const tone = resolveCardTone(item);
            card.classList.add('app-card-tone-' + tone);
            const serviceKey = String(item.id || 'service') + '-' + idx;
            card.setAttribute('data-service-key', serviceKey);
            const backup = item.backup && String(item.backup).trim();
            const url = item.url || '#';
            if (backup) {
                card.href = '#';
                card.addEventListener('click', (e) => {
                    e.preventDefault();
                    openServiceLink(url, backup, serviceKey);
                });
            } else {
                card.href = url;
            }
            const iconParts = String(item.icon || 'fa-link').trim().split(/\s+/);
            const iconClass = iconParts.filter((p) => p.indexOf('fa-') === 0).pop() || 'fa-link';
            card.innerHTML =
                '<span class="app-link-status" title="กำลังตรวจสอบ"><span class="app-link-dot is-checking" aria-hidden="true"></span></span>' +
                '<div class="app-icon" style="background:' + esc(item.color || '#2563eb') + '"><i class="fas ' + esc(iconClass) + '"></i></div>' +
                '<div class="app-txt"><div class="app-name">' + esc(item.title) + '</div>' +
                '<div class="app-sub">' + esc(item.sub) + '</div></div>' +
                // ระบบภายนอก (เปิดเว็บอื่น) บอกด้วยไอคอน ต่างจากหน้าในระบบเรา
                (/^https?:\/\//i.test(url) ? '<i class="fas fa-arrow-up-right-from-square h2-ext" title="เปิดระบบภายนอก"></i>' : '<i class="fas fa-chevron-right h2-go"></i>');
            boxes[groupOf(tone)].querySelector('.h2-svc').appendChild(card);
            quickSrc[item.id] = card;
            checkableItems.push({ key: serviceKey, url: url, backup: backup });
        });
        GROUPS.forEach((g) => {
            const n = boxes[g.key].querySelectorAll('.app-card').length;
            if (!n) return;
            boxes[g.key].querySelector('small').textContent = n + ' ระบบ';
            grid.appendChild(boxes[g.key]);
        });
        renderQuick();
        refreshServiceHealthBadges(checkableItems);
    }

    // ปุ่มลัด: ใช้ลิงก์เดียวกับการ์ดบริการ (รวมลิงก์สำรอง) · ระบบไหนปิดอยู่ ปุ่มนั้นไม่แสดง
    const quickSrc = Object.create(null);
    function renderQuick() {
        const row = $('quickRow');
        if (!row) return;
        const QUICK = [
            { svc: 'IT -Service', href: 'request.html?tab=form', icon: 'fa-plus', t: 'แจ้งซ่อม IT', s: 'คอม / โปรแกรม / เน็ต', pri: true },
            { svc: 'service-it-loan', icon: 'fa-right-left', t: 'ยืมอุปกรณ์', s: 'โน้ตบุ๊ก โปรเจกเตอร์', tone: 'stock' },
            { svc: 'service-room', icon: 'fa-calendar-check', t: 'จองห้องประชุม', s: 'ดูห้องว่างวันนี้', tone: 'booking' },
            { svc: 'service-contact', icon: 'fa-address-book', t: 'หาเบอร์เพื่อน', s: 'สมุดรายชื่อ', tone: 'people' },
        ];
        row.innerHTML = '';
        QUICK.forEach((q) => {
            const src = quickSrc[q.svc];
            if (!src) return;
            const a = document.createElement('a');
            a.className = 'h2-qa' + (q.pri ? ' pri' : ' app-card-tone-' + q.tone);
            a.href = q.href || src.getAttribute('href');
            if (!q.href && src.getAttribute('href') === '#') a.addEventListener('click', (e) => { e.preventDefault(); src.click(); });
            a.innerHTML = '<i class="fas ' + q.icon + '"></i><div><b>' + q.t + '</b><span>' + q.s + '</span></div>';
            row.appendChild(a);
        });
    }

    function setServiceHealthBadge(serviceKey, state) {
        const card = document.querySelector('.app-card[data-service-key="' + serviceKey + '"]');
        if (!card) return;
        const badge = card.querySelector('.app-link-status');
        if (!badge) return;
        const dot = badge.querySelector('.app-link-dot');
        if (!dot) return;
        let title = 'กำลังตรวจสอบ';
        let dotClass = 'is-checking';
        if (state === 'primary') {
            title = 'ลิงก์หลักพร้อมใช้งาน';
            dotClass = 'is-primary';
        } else if (state === 'backup') {
            title = 'ใช้งานลิงก์สำรอง';
            dotClass = 'is-backup';
        } else if (state === 'down') {
            title = 'เชื่อมต่อไม่ได้';
            dotClass = 'is-down';
        }
        badge.title = title;
        dot.className = 'app-link-dot ' + dotClass;
        serviceLinkStateCache[serviceKey] = state;
    }

    async function detectServiceLinkState(item) {
        const primaryRaw = canonicalizeRawServiceUrl(item.url);
        const backupRaw = canonicalizeRawServiceUrl(item.backup);
        if (!primaryRaw && !backupRaw) return 'down';
        if (!primaryRaw) return 'backup';
        // Internal paths (.html/.php / relative) are always reachable if the server is up
        if (isInternalServicePath(primaryRaw)) return 'primary';
        try {
            const resolved = await resolveServiceState(primaryRaw, backupRaw, 1300);
            return resolved.state;
        } catch (_) {
            return 'primary';
        }
    }

    async function refreshServiceHealthBadges(items) {
        const runId = ++serviceHealthRunId;
        const queue = Array.isArray(items) ? items.slice() : [];
        if (!queue.length) return;

        const workerCount = Math.min(3, queue.length);
        const workers = Array.from({ length: workerCount }, async () => {
            while (queue.length > 0) {
                const item = queue.shift();
                if (!item || runId !== serviceHealthRunId) return;
                const state = await detectServiceLinkState(item);
                if (runId !== serviceHealthRunId) return;
                setServiceHealthBadge(item.key, state);
            }
        });
        await Promise.all(workers);
    }

    async function openServiceLink(primary, backup, serviceKey) {
        const cached = serviceLinkStateCache[serviceKey];
        try {
            const primaryUrl = normalizeServiceUrlInput(primary, true);
            const backupUrl = normalizeServiceUrlInput(backup, false);
            if (cached === 'backup' && backupUrl) {
                window.open(backupUrl, '_blank');
                return;
            }
            if (cached === 'primary') {
                window.open(primaryUrl, '_blank');
                return;
            }

            const resolved = await resolveServiceState(primaryUrl, backupUrl, 1100);
            setServiceHealthBadge(serviceKey, resolved.state);
            if (resolved.state === 'primary') {
                window.open(resolved.primaryUrl, '_blank');
                return;
            }
            if (resolved.state === 'backup' && resolved.backupUrl) {
                logFailover('primary_to_backup', {
                    serviceKey: serviceKey || '',
                    reason: resolved.reason || '',
                    primary: resolved.primaryUrl || '',
                    backup: resolved.backupUrl || ''
                });
                window.open(resolved.backupUrl, '_blank');
                return;
            }
            if (resolved.backupUrl) {
                logFailover('primary_to_backup_forced', {
                    serviceKey: serviceKey || '',
                    reason: resolved.reason || '',
                    primary: resolved.primaryUrl || '',
                    backup: resolved.backupUrl || ''
                });
                window.open(resolved.backupUrl, '_blank');
                return;
            }
            logFailover('both_down', {
                serviceKey: serviceKey || '',
                reason: resolved.reason || '',
                primary: resolved.primaryUrl || '',
                backup: resolved.backupUrl || ''
            });
            window.open(resolved.primaryUrl || primaryUrl, '_blank');
        } catch (_) {
            const fallbackTarget = String(backup || '').trim() || String(primary || '').trim();
            logFailover('open_fallback_after_exception', {
                serviceKey: serviceKey || '',
                primary: String(primary || '').trim(),
                backup: String(backup || '').trim()
            });
            if (fallbackTarget) window.open(fallbackTarget, '_blank');
        }
    }

    function renderNotifList(kpi) {
        const list = $('notifList');
        if (!list) return;
        let html = '';
        if ((kpi.pending || 0) > 0) {
            html += '<div class="notif-item" data-href="log.html">งานซ่อมรอดำเนินการ ' + kpi.pending + ' รายการ</div>';
        }
        if ((kpi.loan_pending || 0) > 0) {
            html += '<div class="notif-item" data-href="borrow_management.html">คำขอยืมรออนุมัติ ' + kpi.loan_pending + ' รายการ</div>';
        }
        if (!html) html = '<div class="notif-empty">ไม่มีการแจ้งเตือนใหม่</div>';
        list.innerHTML = html;
        list.querySelectorAll('.notif-item').forEach((el) => {
            el.addEventListener('click', () => {
                const href = el.getAttribute('data-href');
                if (href) window.location.href = href;
            });
        });
        const badge = $('notifBadge');
        const total = (kpi.pending || 0) + (kpi.loan_pending || 0);
        if (badge) {
            if (total > 0) {
                badge.textContent = total > 99 ? '99+' : String(total);
                badge.style.display = 'flex';
                badge.classList.remove('hidden');
            } else {
                badge.style.display = 'none';
            }
        }
    }

    async function boot() {
        showStatusMessage('กำลังโหลด', 'เตรียมข้อมูลแดชบอร์ด...', true);
        try {
            const data = await fetchJson(API + '?action=get_home_dashboard&_t=' + Date.now(), 20000);
            if (!data || data.loggedin === false || data.status === 'error') {
                window.location.href = 'login.html';
                return;
            }
            applySession(data.session || {});
            renderKpi(data.kpi || {});
            renderMyStatus(data.my_tickets, data.my_loans);

            renderServices(data.services || []);
            renderNotifList(data.kpi || {});
        } catch (e) {
            console.error('[index-home]', e);
            showStatusMessage('โหลดไม่สำเร็จ', 'ลองรีเฟรชหน้า (Ctrl+Shift+R)', false);
        }
    }

    window.toggleDark = function () {
        const isDark = document.documentElement.classList.toggle('dark');
        document.documentElement.classList.toggle('dark-theme', isDark);
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        const icon = $('themeIcon');
        if (icon) icon.className = isDark ? 'fa fa-sun' : 'fa fa-moon';
    };

    window.toggleUserDropdown = function (e) {
        e && e.stopPropagation();
        const chip = $('navUserChip');
        const dd   = $('navUserDropdown');
        if (!chip || !dd) return;
        const isOpen = dd.classList.contains('open');
        if (isOpen) { closeUserDropdown(); } else {
            dd.classList.add('open');
            chip.classList.add('open');
            chip.setAttribute('aria-expanded', 'true');
        }
    };

    window.closeUserDropdown = function () {
        const chip = $('navUserChip');
        const dd   = $('navUserDropdown');
        if (dd)   dd.classList.remove('open');
        if (chip) { chip.classList.remove('open'); chip.setAttribute('aria-expanded', 'false'); }
    };

    document.addEventListener('click', function (e) {
        const chip = $('navUserChip');
        if (chip && !chip.contains(e.target)) closeUserDropdown();
    });

    window.handleLogout = async function () {
        try { await fetch(API + '?action=logout', { method: 'POST', credentials: 'same-origin' }); } catch (_) {}
        window.location.href = 'login.html';
    };
    window.logout = window.handleLogout;

    window.openNotifModal = function () {
        const m = $('notifModal');
        if (m) m.style.display = 'flex';
    };
    window.closeNotifModal = function () {
        const m = $('notifModal');
        if (m) m.style.display = 'none';
    };

    window.toggleSidebar = function () {
        const sb = $('sidebar-left');
        const ov = $('sidebar-overlay');
        if (!sb || !ov) return;
        const isOpen = sb.classList.toggle('sidebar-open');
        ov.classList.toggle('active', isOpen);
    };
    window.closeSidebar = function () {
        const sb = $('sidebar-left');
        const ov = $('sidebar-overlay');
        if (sb) sb.classList.remove('sidebar-open');
        if (ov) ov.classList.remove('active');
    };

    function initSidebar() {
        const sb = $('sidebar-left');
        if (!sb) return;
        sb.querySelectorAll('a').forEach((a) => {
            a.addEventListener('click', () => {
                if (window.innerWidth < 1024) window.closeSidebar();
            });
        });
    }

    function initSearch() {
        const input = $('searchInput');
        if (!input) return;
        const apply = (term) => {
            term = String(term || '').toLowerCase().trim();
            let shown = 0;
            document.querySelectorAll('#appGrid .h2-group').forEach((g) => {
                let n = 0;
                g.querySelectorAll('.app-card').forEach((card) => {
                    const ok = card.textContent.toLowerCase().includes(term);
                    card.style.display = ok ? '' : 'none';
                    if (ok) n++;
                });
                g.style.display = n ? '' : 'none';
                shown += n;
            });
            if ($('noResult')) $('noResult').style.display = shown ? 'none' : '';
            const qr = $('quickRow');
            if (qr) qr.style.display = term ? 'none' : '';
        };
        input.addEventListener('input', function () { apply(this.value); });
        const mInput = $('mSearchInput');
        if (mInput) mInput.addEventListener('input', function () { apply(this.value); });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { input.value = ''; input.dispatchEvent(new Event('input')); }
        });
        document.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                input.focus();
            }
        });
        const hint = $('searchShortcutHint');
        if (hint) {
            hint.textContent = /Mac|iPhone|iPad|iPod/.test(navigator.platform || '') ? '⌘K' : 'Ctrl+K';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        purgeServiceWorker().finally(() => {
            initSidebar();
            initSearch();
            const icon = $('themeIcon');
            if (icon && document.documentElement.classList.contains('dark')) icon.className = 'fa fa-sun';
            boot();
        });
    });
})();
