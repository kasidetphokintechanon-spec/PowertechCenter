/**
 * nav-chip.js — Navbar user chip (avatar + name + role + dropdown)
 * Works on any page. Reuses window._ptcSessPromise if sidebar.js already fetched it.
 * Requires: <div id="nav-user-area"></div> somewhere in the navbar.
 */
(function () {
    'use strict';

    const ROLE_LABEL = {
        admin: 'ผู้ดูแลระบบ', it: 'IT Staff', technician: 'ช่างเทคนิค',
        manager: 'ผู้จัดการ', supervisor: 'หัวหน้างาน',
        staff: 'เจ้าหน้าที่', user: 'พนักงาน'
    };
    const ROLE_COLOR = {
        admin: '#2563eb', it: '#10b981', technician: '#f59e0b',
        manager: '#8b5cf6', supervisor: '#0284c7', staff: '#06b6d4', user: '#64748b'
    };
    const COMPANY_NAME = {
        PTA: 'Powertech Engine Assembly',
        PT4: 'Powertech 2004',
        PTE: 'Powertech Energy Solutions'
    };
    const HONORIFICS = /^(mr\.?|mrs\.?|ms\.?|dr\.?|นาย|นาง|น\.ส\.?|นางสาว)\s+/i;

    function getSession() {
        if (!window._ptcSessPromise) {
            window._ptcSessPromise = fetch('api.php?action=check_session', { credentials: 'same-origin' })
                .then(r => r.json())
                .catch(() => ({ loggedin: false }));
        }
        return window._ptcSessPromise;
    }

    function buildChipHTML() {
        const isDark = document.documentElement.classList.contains('dark');
        return `
<div class="nav-user-chip" id="navUserChip" onclick="window._navChipToggle(event)" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
    <div class="nav-user-avatar" id="navUserAvatar">U</div>
    <div class="nav-user-info">
        <div class="nav-user-name" id="navUserName">กำลังโหลด...</div>
        <div class="nav-user-role" id="navUserRole">—</div>
    </div>
    <i class="fa fa-chevron-down nav-user-chevron" id="navUserChevron"></i>
</div>
<div class="nav-user-dropdown" id="navUserDropdown">
    <button class="nav-ud-item" onclick="window._navChipDark()">
        <i class="fa ${isDark ? 'fa-sun' : 'fa-moon'}" id="navChipThemeIcon"></i><span>สลับธีม</span>
    </button>
    <div class="nav-ud-divider"></div>
    <button class="nav-ud-item nav-ud-logout" onclick="window._navChipLogout()">
        <i class="fa fa-sign-out-alt"></i><span>ออกจากระบบ</span>
    </button>
</div>`;
    }

    function applySession(session) {
        const name  = session.display_name || 'User';
        const role  = String(session.role || '').toLowerCase();
        const clean = name.replace(HONORIFICS, '').trim() || name;
        const parts = clean.split(/\s+/);
        const displayName = parts.length >= 2 ? parts[0] + ' ' + parts[1] : clean;
        const initials    = parts.map(w => w[0] || '').join('').toUpperCase().slice(0, 2) || 'U';

        const avatar = document.getElementById('navUserAvatar');
        if (avatar) { avatar.textContent = initials; avatar.style.background = ROLE_COLOR[role] || '#64748b'; }
        const nameEl = document.getElementById('navUserName');
        if (nameEl) nameEl.textContent = displayName;
        const roleEl = document.getElementById('navUserRole');
        if (roleEl) roleEl.textContent = ROLE_LABEL[role] || session.primary_department || 'พนักงาน';

        // Update company title if element exists and session has company
        const titleEl = document.getElementById('companyTitle');
        if (titleEl && session.company && COMPANY_NAME[session.company]) {
            titleEl.textContent = COMPANY_NAME[session.company];
        }
    }

    async function init() {
        const area = document.getElementById('nav-user-area');
        if (!area) return;

        area.innerHTML = buildChipHTML();

        const session = await getSession();
        if (session.loggedin) applySession(session);

        // Close dropdown on outside click
        document.addEventListener('click', function (e) {
            const chip = document.getElementById('navUserChip');
            if (chip && !chip.contains(e.target)) _closeChip();
        });
    }

    function _closeChip() {
        const chip = document.getElementById('navUserChip');
        const dd   = document.getElementById('navUserDropdown');
        if (dd)   dd.classList.remove('open');
        if (chip) { chip.classList.remove('open'); chip.setAttribute('aria-expanded', 'false'); }
    }

    window._navChipToggle = function (e) {
        e && e.stopPropagation();
        const chip = document.getElementById('navUserChip');
        const dd   = document.getElementById('navUserDropdown');
        if (!chip || !dd) return;
        if (dd.classList.contains('open')) { _closeChip(); return; }
        dd.classList.add('open');
        chip.classList.add('open');
        chip.setAttribute('aria-expanded', 'true');
    };

    window._navChipDark = function () {
        // Delegate to page's toggleDark if available, else do standard toggle
        if (typeof window.toggleDark === 'function') {
            window.toggleDark();
        } else {
            const isDark = document.documentElement.classList.toggle('dark');
            document.documentElement.classList.toggle('dark-theme', isDark);
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        }
        // Sync chip icon
        const isDarkNow = document.documentElement.classList.contains('dark');
        const icon = document.getElementById('navChipThemeIcon');
        if (icon) icon.className = isDarkNow ? 'fa fa-sun' : 'fa fa-moon';
        _closeChip();
    };

    window._navChipLogout = async function () {
        if (typeof window.handleLogout === 'function') { window.handleLogout(); return; }
        try { await fetch('api.php?action=logout', { method: 'POST', credentials: 'same-origin' }); } catch (_) {}
        window.location.href = 'login.html';
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
