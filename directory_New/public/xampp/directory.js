/**
 * Employee Directory - macOS Style Contact List
 * Version 2.0
 */

// Sample Employee Data - ใช้เป็นตัวอย่างหรือ fallback เมื่อโหลด API ไม่ได้
const employeeData = [
    {
        id: 1,
        name: "สมชาย ใจดี",
        nameEn: "Somchai Jaidee",
        position: "กรรมการผู้จัดการ",
        department: "ผู้บริหาร",
        company: "PTA",
        email: "somchai@pta.co.th",
        phone: "02-123-4567",
        mobile: "081-234-5678",
        extension: "1001",
        employeeId: "PTA001",
        startDate: "2010-01-15",
        birthday: "1975-03-20",
        isExecutive: true,
        avatar: null
    },
    {
        id: 2,
        name: "สมหญิง รักงาน",
        nameEn: "Somying Rakngarn",
        position: "ผู้อำนวยการฝ่ายการเงิน",
        department: "การเงิน",
        company: "PTA",
        email: "somying@pta.co.th",
        phone: "02-123-4568",
        mobile: "082-345-6789",
        extension: "1002",
        employeeId: "PTA002",
        startDate: "2012-05-01",
        birthday: "1980-07-15",
        isExecutive: true,
        avatar: null
    },
    {
        id: 3,
        name: "วิชัย เก่งกาจ",
        nameEn: "Wichai Kengkaj",
        position: "หัวหน้าแผนก IT",
        department: "IT",
        company: "PT4",
        email: "wichai@pt4.co.th",
        phone: "02-234-5678",
        mobile: "083-456-7890",
        extension: "2001",
        employeeId: "PT4001",
        startDate: "2015-03-10",
        birthday: "1985-11-25",
        isExecutive: false,
        avatar: null
    },
    {
        id: 4,
        name: "นันทนา ขยันเรียน",
        nameEn: "Nantana Khayanrien",
        position: "เจ้าหน้าที่บัญชี",
        department: "การเงิน",
        company: "PTE",
        email: "nantana@pte.co.th",
        phone: "02-345-6789",
        mobile: "084-567-8901",
        extension: "3001",
        employeeId: "PTE001",
        startDate: "2018-08-20",
        birthday: "1990-02-14",
        isExecutive: false,
        avatar: null
    },
    {
        id: 5,
        name: "ประสิทธิ์ มีสุข",
        nameEn: "Prasit Meesuk",
        position: "ผู้จัดการฝ่ายขาย",
        department: "การขาย",
        company: "PTA",
        email: "prasit@pta.co.th",
        phone: "02-123-4570",
        mobile: "085-678-9012",
        extension: "1010",
        employeeId: "PTA010",
        startDate: "2014-06-01",
        birthday: "1982-09-30",
        isExecutive: true,
        avatar: null
    },
    {
        id: 6,
        name: "มานะ ตั้งใจ",
        nameEn: "Mana Tangjai",
        position: "โปรแกรมเมอร์",
        department: "IT",
        company: "PT4",
        email: "mana@pt4.co.th",
        phone: "02-234-5680",
        mobile: "086-789-0123",
        extension: "2010",
        employeeId: "PT4010",
        startDate: "2020-01-15",
        birthday: "1995-05-10",
        isExecutive: false,
        avatar: null
    },
    {
        id: 7,
        name: "พิมพ์ใจ สดใส",
        nameEn: "Pimjai Sodsai",
        position: "เจ้าหน้าที่ HR",
        department: "ทรัพยากรบุคคล",
        company: "PTE",
        email: "pimjai@pte.co.th",
        phone: "02-345-6791",
        mobile: "087-890-1234",
        extension: "3010",
        employeeId: "PTE010",
        startDate: "2019-04-01",
        birthday: "1992-12-05",
        isExecutive: false,
        avatar: null
    },
    {
        id: 8,
        name: "อนุชา สายน้ำ",
        nameEn: "Anucha Sainam",
        position: "ผู้อำนวยการฝ่ายปฏิบัติการ",
        department: "ปฏิบัติการ",
        company: "PT4",
        email: "anucha@pt4.co.th",
        phone: "02-234-5682",
        mobile: "088-901-2345",
        extension: "2002",
        employeeId: "PT4002",
        startDate: "2013-09-15",
        birthday: "1978-08-18",
        isExecutive: true,
        avatar: null
    },
    {
        id: 9,
        name: "จิราภรณ์ งามตา",
        nameEn: "Jiraporn Ngamta",
        position: "เลขานุการ",
        department: "ผู้บริหาร",
        company: "PTA",
        email: "jiraporn@pta.co.th",
        phone: "02-123-4571",
        mobile: "089-012-3456",
        extension: "1003",
        employeeId: "PTA003",
        startDate: "2016-02-20",
        birthday: "1988-04-22",
        isExecutive: false,
        avatar: null
    },
    {
        id: 10,
        name: "ธนวัฒน์ รุ่งเรือง",
        nameEn: "Thanawat Rungruang",
        position: "นักวิเคราะห์ข้อมูล",
        department: "IT",
        company: "PTE",
        email: "thanawat@pte.co.th",
        phone: "02-345-6793",
        mobile: "090-123-4567",
        extension: "3020",
        employeeId: "PTE020",
        startDate: "2021-07-01",
        birthday: "1996-01-08",
        isExecutive: false,
        avatar: null
    }
];

function isCancelledManagementDepartmentName(value) {
    const name = String(value || '').trim().toLowerCase();
    return name === 'management' || name === 'mangement' || name === 'managment';
}

function mapEmployeeFromApi(row) {
    const assignments = Array.isArray(row.assignments) ? row.assignments : [];
    const primary = assignments.find(a => a.is_primary == 1) || assignments[0] || {};
    const company = (primary.company || '').toUpperCase();
    let department = primary.department || '';
    if (isCancelledManagementDepartmentName(department)) department = '';
    const email = primary.email || '';
    const nameTh = row.name_th || '';
    const nameEn = row.name || '';
    const fullName = nameEn || nameTh || '-';
    const position = row.position || '';
    const isExecutiveByFlag = String(row.is_executive ?? '0') === '1';
    const isManagementByFlag = String(row.is_management ?? '0') === '1';
    const createdAt = row.created_at || row.createdAt || '';
    const updatedAt = row.updated_at || row.updatedAt || '';

    return {
        id: row.id,
        name: fullName,
        nameEn: nameEn || nameTh || '',
        nameTh: nameTh,
        name_th: nameTh,
        position: position,
        department: department,
        company: company,
        email: email,
        phone: normalizePhoneValue(row.phone || ''),
        mobile: '', // not available from API
        extension: '', // not available from API
        employeeId: row.id || '',
        startDate: row.start_date || '',
        birthday: row.birthdate || '',
        status: row.employment_status || 'active',
        employment_status: row.employment_status || 'active',
        createdAt: createdAt,
        updatedAt: updatedAt,
        created_at: createdAt,
        updated_at: updatedAt,
        isExecutive: isExecutiveByFlag,
        isManagement: isManagementByFlag,
        is_executive: isExecutiveByFlag ? 1 : 0,
        is_management: isManagementByFlag ? 1 : 0,
        avatar: row.image ? `uploads/employees/${row.image}` : null,
        assignments: assignments
    };
}

// State
let state = {
    employees: [],
    filteredEmployees: [],
    selectedEmployee: null,
    favorites: JSON.parse(localStorage.getItem('favorites') || '[]'),
    currentFilter: 'all',
    searchQuery: '',
    viewMode: 'list',
    isDarkMode: localLocalStorageDark(),
    departments: [],
    companyOrder: ['PTA', 'PT4', 'PTE'],
    categoryNavConfig: []
};
const defaultPositionOptions = [
    'Managing Director',
    'Executive Director and General Manager For Administration Division',
    'Digital Transformation Analyst Manager',
    'Senior Manager',
    'Manager',
    'Manager (Acting)',
    'Plan Manager',
    'Assistant Manger',
    'Head of General Administratoin Division',
    'Head of Operations',
    'Head of Testing',
    'Senior Supervisor',
    'Supervisor (Lv3)',
    'Supervisor (Lv2)',
    'Supervisor (Lv1)',
    'Senior Officer',
    'Officer',
    'Programmer',
    'Senior Programmer',
    'Engineer(Lv3)',
    'Engineer(Lv2)',
    'Engineer(Lv1)',
    'Senior Technician',
    'Senior Springer',
    'Springer',
    'Staff',
    'Factory and Mainteanace'
];
let jobPositionOptions = [];
let jobPositionSortMap = {};
const positionRanks = {
    'Managing Director': 1,
    'Executive Director and General Manager For Administration Division': 2,
    'Head of General Administratoin Division': 5,
    'Head of Operations': 6,
    'Digital Transformation Analyst Manager': 7,
    'Senior Manager': 7,
    'Manager': 8,
    'Manager (Acting)': 9,
    'Plan Manager': 10,
    'Assistant Manger': 11,
    'Head of Testing': 20,
    'Senior Supervisor': 20,
    'Supervisor (Lv3)': 21,
    'Supervisor (Lv2)': 22,
    'Supervisor (Lv1)': 23,
    'Senior Officer': 30,
    'Senior Programmer': 31,
    'Engineer(Lv3)': 32,
    'Senior Technician': 33,
    'Senior Springer': 34,
    'Officer': 40,
    'Programmer': 41,
    'Engineer(Lv2)': 42,
    'Engineer(Lv1)': 43,
    'Springer': 44,
    'Staff': 50,
    'Factory and Mainteanace': 51
};

let directorySession = { loggedin: false, role: 'guest', username: '', display_name: '' };
const isPublicMode = new URLSearchParams(window.location.search).get('public') === '1';
const isLocalDevHost = ['localhost', '127.0.0.1'].includes(window.location.hostname);
let deferredInstallPrompt = null;
let autoRefreshTimer = null;
let lastEmployeesFingerprint = '';
let lastUpdatedAt = null;
const DIRECTORY_AUTO_REFRESH_MS = 120000;

function normalizePhoneValue(phone) {
    const raw = String(phone == null ? '' : phone).trim();
    if (!raw) return '';
    if (raw.startsWith('0') || raw.startsWith('+')) return raw;
    const digits = raw.replace(/\D/g, '');
    if (digits.length >= 8 && digits.length <= 9) {
        if (/^\d+$/.test(raw)) return `0${digits}`;
        return `0${raw}`;
    }
    return raw;
}

function getPreferredDisplayName(employee) {
    return String(employee.nameEn || employee.name || employee.nameTh || employee.name_th || '-').trim() || '-';
}

function getPositionRank(position) {
    const title = String(position || '').trim();
    if (!title) return 999;
    const dynamicRank = jobPositionSortMap[title.toLowerCase()];
    if (dynamicRank != null) return dynamicRank;
    if (positionRanks[title] != null) return positionRanks[title];

    const lower = title.toLowerCase();
    if (lower.includes('managing director')) return 1;
    if (lower.includes('executive director')) return 2;
    if (lower.includes('director') || title.includes('ผู้อำนวยการ')) return 4;
    if (lower.includes('head') || title.includes('หัวหน้า')) return 6;
    if (lower.includes('senior manager')) return 7;
    if (lower.includes('manager')) return 8;
    return 999;
}

function compareEmployeesByRankThenName(a, b) {
    const rankDiff = getPositionRank(a.position) - getPositionRank(b.position);
    if (rankDiff !== 0) return rankDiff;
    return getPreferredDisplayName(a).localeCompare(getPreferredDisplayName(b), 'en', { sensitivity: 'base' });
}

function isDepartmentHeadPosition(position) {
    const title = String(position || '').trim().toLowerCase();
    if (!title) return false;
    return title.includes('head') || title.includes('หัวหน้า');
}

function compareDepartmentMembers(a, b) {
    const rankDiff = getPositionRank(a.position) - getPositionRank(b.position);
    if (rankDiff !== 0) return rankDiff;
    const headA = isDepartmentHeadPosition(a.position) ? 1 : 0;
    const headB = isDepartmentHeadPosition(b.position) ? 1 : 0;
    if (headA !== headB) return headB - headA;
    return compareEmployeesByRankThenName(a, b);
}

function normalizeDeptName(v) {
    return String(v || '').trim().toLowerCase();
}

function getDepartmentSortOrderMap() {
    const map = {};
    (Array.isArray(state.departments) ? state.departments : []).forEach((d) => {
        const key = normalizeDeptName(d?.dept_name);
        if (!key) return;
        const order = parseInt(d?.sort_order, 10);
        map[key] = Number.isFinite(order) ? order : 999;
    });
    return map;
}

function compareDepartmentNames(a, b, orderMap = null) {
    const map = orderMap || getDepartmentSortOrderMap();
    const ao = map[normalizeDeptName(a)] ?? 999;
    const bo = map[normalizeDeptName(b)] ?? 999;
    if (ao !== bo) return ao - bo;
    return String(a || '').localeCompare(String(b || ''), 'en', { sensitivity: 'base' });
}

function isRecentlyAdded(employee) {
    const dateRaw = employee.createdAt || employee.startDate || '';
    if (!dateRaw) return false;
    const created = new Date(dateRaw);
    if (Number.isNaN(created.getTime())) return false;
    const days = (Date.now() - created.getTime()) / (1000 * 60 * 60 * 24);
    return days >= 0 && days <= 30;
}

function localLocalStorageDark() {
    try {
        return localStorage.getItem('darkMode') === 'true';
    } catch {
        return false;
    }
}

// DOM Elements
const elements = {
    sidebar: document.getElementById('sidebar'),
    sidebarOverlay: document.getElementById('sidebarOverlay'),
    menuToggle: document.getElementById('menuToggle'),
    themeToggle: document.getElementById('themeToggle'),
    themeToggleMobile: document.getElementById('themeToggleMobile'),
    installAppBtn: document.getElementById('installAppBtn'),
    categorySettingsBtn: document.getElementById('categorySettingsBtn'),
    searchInput: document.getElementById('searchInput'),
    employeeList: document.getElementById('employeeList'),
    employeeDetail: document.getElementById('employeeDetail'),
    listTitle: document.getElementById('listTitle'),
    lastUpdatedStamp: document.getElementById('lastUpdatedStamp'),
    companyList: document.getElementById('companyList'),
    departmentList: document.getElementById('departmentList'),
    categorySection: document.getElementById('categorySection'),
    managementNavItem: document.getElementById('managementNavItem'),
    managementNavLabel: document.getElementById('managementNavLabel'),
    managementBadge: document.getElementById('managementBadge'),
    allNavItem: document.getElementById('allNavItem'),
    favoritesNavItem: document.getElementById('favoritesNavItem'),
    executivesNavItem: document.getElementById('executivesNavItem'),
    inactiveNavItem: document.getElementById('inactiveNavItem'),
    toast: document.getElementById('toast'),
    toastMessage: document.getElementById('toastMessage'),
    // Stats
    totalCount: document.getElementById('totalCount'),
    filteredCount: document.getElementById('filteredCount'),
    favoriteCount: document.getElementById('favoriteCount'),
    // Badges
    allBadge: document.getElementById('allBadge'),
    inactiveBadge: document.getElementById('inactiveBadge'),
    favBadge: document.getElementById('favBadge'),
    execBadge: document.getElementById('execBadge')
};

function formatRelativeUpdatedTime(dt) {
    const diffMs = Date.now() - dt.getTime();
    const diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 1) return 'เมื่อสักครู่';
    if (diffMin < 60) return `${diffMin} นาทีที่แล้ว`;
    const diffHr = Math.floor(diffMin / 60);
    if (diffHr < 24) return `${diffHr} ชั่วโมงที่แล้ว`;
    const diffDay = Math.floor(diffHr / 24);
    return `${diffDay} วันที่แล้ว`;
}

function updateLastUpdatedStamp(dateObj = null) {
    if (!elements.lastUpdatedStamp) return;
    if (dateObj) lastUpdatedAt = new Date(dateObj);
    const dt = lastUpdatedAt ? new Date(lastUpdatedAt) : null;
    if (!dt) {
        elements.lastUpdatedStamp.textContent = 'อัปเดตล่าสุด -';
        return;
    }
    if (Number.isNaN(dt.getTime())) return;
    const dateText = dt.toLocaleDateString('th-TH', { day: '2-digit', month: '2-digit', year: 'numeric' });
    const timeText = dt.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
    const absoluteText = `${dateText} ${timeText}`;
    const relativeText = formatRelativeUpdatedTime(dt);
    elements.lastUpdatedStamp.textContent = `อัปเดตล่าสุด ${timeText} • ${dateText}`;
    elements.lastUpdatedStamp.title = `แก้ไขล่าสุด ${absoluteText} (${relativeText})`;
}

function getLatestEmployeesUpdatedAt(employees) {
    let max = 0;
    for (const e of Array.isArray(employees) ? employees : []) {
        const candidates = [e.updatedAt, e.updated_at, e.createdAt, e.created_at].filter(Boolean);
        for (const raw of candidates) {
            const t = new Date(raw).getTime();
            if (Number.isFinite(t) && t > max) max = t;
        }
    }
    return max ? new Date(max) : null;
}

function isEmployeeActive(employee) {
    const status = String(employee.employment_status || employee.status || 'active').toLowerCase();
    return status !== 'inactive';
}

function updateInactiveNavVisibility() {
    if (!elements.inactiveNavItem) return;
    const canManageDirectory = directorySession.loggedin && (directorySession.role === 'admin' || directorySession.role === 'staff');
    elements.inactiveNavItem.style.display = canManageDirectory ? '' : 'none';
    if (!canManageDirectory && state.currentFilter === 'inactive') {
        state.currentFilter = 'all';
    }
}

function updateManagementNavVisibility(allowFilterReset = true) {
    if (!elements.managementNavItem) return 0;
    const activeEmployees = state.employees.filter(isEmployeeActive);
    const mgmtCount = activeEmployees.filter(e => !!e.isManagement).length;
    elements.managementNavItem.style.display = mgmtCount > 0 ? '' : 'none';
    if (elements.managementBadge) elements.managementBadge.textContent = mgmtCount;
    elements.managementNavItem.classList.toggle('active', state.currentFilter === 'management');
    if (allowFilterReset && mgmtCount === 0 && state.currentFilter === 'management') {
        state.currentFilter = 'all';
    }
    return mgmtCount;
}

function canManageDirectory() {
    return directorySession.loggedin && (directorySession.role === 'admin' || directorySession.role === 'staff');
}

function getDefaultCategoryNavConfig() {
    return [
        { key: 'management', label: 'ระดับ Management' },
        { key: 'all', label: 'พนักงานทั้งหมด' },
        { key: 'favorites', label: 'ปักหมุด' },
        { key: 'executives', label: 'ผู้บริหาร' },
        { key: 'inactive', label: 'พนักงาน Inactive' }
    ];
}

function getAllowedCategoryKeys() {
    return ['management', 'all', 'favorites', 'executives', 'inactive'];
}

function normalizeCategoryKey(v) {
    return String(v || '').trim().toLowerCase();
}

function getCategoryElementByKey(key) {
    const k = normalizeCategoryKey(key);
    if (k === 'management') return elements.managementNavItem;
    if (k === 'all') return elements.allNavItem;
    if (k === 'favorites') return elements.favoritesNavItem;
    if (k === 'executives') return elements.executivesNavItem;
    if (k === 'inactive') return elements.inactiveNavItem;
    return null;
}

function getCategoryLabelByKey(key) {
    const k = normalizeCategoryKey(key);
    const config = normalizeCategoryNavConfig(state.categoryNavConfig);
    const found = config.find(it => it.key === k);
    return found ? String(found.label || '').trim() : '';
}

function normalizeCategoryNavConfig(input) {
    const allowed = new Set(getAllowedCategoryKeys());
    const defaults = getDefaultCategoryNavConfig();
    const defaultLabelMap = defaults.reduce((acc, it) => {
        acc[it.key] = it.label;
        return acc;
    }, {});

    const out = [];
    const seen = new Set();
    const list = Array.isArray(input) ? input : [];
    list.forEach((raw) => {
        const key = normalizeCategoryKey(raw?.key ?? raw);
        if (!allowed.has(key) || seen.has(key)) return;
        const labelRaw = String(raw?.label ?? '').trim();
        out.push({ key, label: labelRaw || defaultLabelMap[key] || key });
        seen.add(key);
    });

    defaults.forEach((it) => {
        if (!seen.has(it.key)) out.push({ key: it.key, label: it.label });
    });
    return out;
}

async function loadCategoryNavConfig() {
    try {
        const res = await fetch('api.php?action=get_directory_categories&v=' + Date.now(), { cache: 'no-store' });
        const json = await res.json();
        if (json && json.status === 'success' && Array.isArray(json.items)) {
            state.categoryNavConfig = normalizeCategoryNavConfig(json.items);
            return;
        }
    } catch {}
    state.categoryNavConfig = normalizeCategoryNavConfig([]);
}

function applyCategoryNavConfig() {
    const root = elements.categorySection;
    if (!root) return;
    const title = root.querySelector('.nav-section-title');
    if (!title) return;

    const config = normalizeCategoryNavConfig(state.categoryNavConfig);
    const buttons = root.querySelectorAll('button.nav-item[data-category-key]');
    buttons.forEach((btn) => btn.remove());

    config.forEach((item) => {
        const btn = getCategoryElementByKey(item.key);
        if (!btn) return;
        const labelEl = btn.querySelector(`[data-category-label="${item.key}"]`);
        if (labelEl) labelEl.textContent = item.label;
        root.appendChild(btn);
    });
}

async function saveCategoryNavConfig(items) {
    const normalized = normalizeCategoryNavConfig(items);
    const fd = new FormData();
    fd.append('action', 'save_directory_categories');
    fd.append('config', JSON.stringify(normalized));
    const res = await fetch('api.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (!res.ok || !json || json.status !== 'success') {
        throw new Error(json?.message || 'Save failed');
    }
    state.categoryNavConfig = normalizeCategoryNavConfig(json.items || normalized);
    applyCategoryNavConfig();
}

async function openCategorySettings() {
    if (!canManageDirectory()) {
        showToast('ต้องเข้าสู่ระบบในสิทธิ์ Admin หรือ Staff');
        return;
    }
    if (typeof Swal === 'undefined') {
        showToast('ไม่พบ SweetAlert2');
        return;
    }
    await loadCategoryNavConfig();
    const config = normalizeCategoryNavConfig(state.categoryNavConfig);
    const defaults = getDefaultCategoryNavConfig();
    const defaultLabelMap = defaults.reduce((acc, it) => {
        acc[it.key] = it.label;
        return acc;
    }, {});

    const rowsHtml = config.map((it) => `
        <div data-cat-row="1" data-key="${it.key}" style="display:flex; gap:8px; align-items:center; margin:8px 0;">
            <button type="button" data-move="up" style="width:34px; height:34px; border:1px solid var(--border); background:var(--bg-secondary); border-radius:10px; cursor:pointer;">↑</button>
            <button type="button" data-move="down" style="width:34px; height:34px; border:1px solid var(--border); background:var(--bg-secondary); border-radius:10px; cursor:pointer;">↓</button>
            <button type="button" data-remove="1" style="width:34px; height:34px; border:1px solid #ef4444; background:rgba(239,68,68,0.08); color:#ef4444; border-radius:10px; cursor:pointer;">✕</button>
            <div style="flex:1;">
                <div class="dir-field-label">${it.key}</div>
                <input class="dir-field-input" data-label-input="1" value="${String(it.label || '').replace(/"/g, '&quot;')}">
            </div>
        </div>
    `).join('');

    const html = `
        <div class="dir-popup">
            <div class="dir-popup-section">
                <div class="dir-popup-title">หมวดหมู่</div>
                <div id="dir-category-config">${rowsHtml}</div>
            </div>
        </div>
    `;

    const result = await Swal.fire({
        title: 'ตั้งค่าหมวดหมู่',
        html: html,
        focusConfirm: false,
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'บันทึก',
        cancelButtonText: 'ยกเลิก',
        denyButtonText: 'ค่าเริ่มต้น',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        didOpen: () => {
            const container = document.getElementById('dir-category-config');
            if (!container) return;
            container.addEventListener('click', (e) => {
                const removeBtn = e.target.closest('button[data-remove="1"]');
                if (removeBtn) {
                    const row = e.target.closest('[data-cat-row="1"]');
                    if (row) row.remove();
                    return;
                }
                const btn = e.target.closest('button[data-move]');
                if (!btn) return;
                const row = e.target.closest('[data-cat-row="1"]');
                if (!row) return;
                const dir = btn.getAttribute('data-move');
                if (dir === 'up') {
                    const prev = row.previousElementSibling;
                    if (prev) container.insertBefore(row, prev);
                } else if (dir === 'down') {
                    const next = row.nextElementSibling;
                    if (next) container.insertBefore(next, row);
                }
            });
        },
        preConfirm: async () => {
            const container = document.getElementById('dir-category-config');
            const rows = Array.from(container ? container.querySelectorAll('[data-cat-row="1"]') : []);
            const items = rows.map((row) => {
                const key = normalizeCategoryKey(row.getAttribute('data-key'));
                const input = row.querySelector('input[data-label-input="1"]');
                const labelRaw = String(input ? input.value : '').trim();
                return { key, label: labelRaw || defaultLabelMap[key] || key };
            });
            try {
                await saveCategoryNavConfig(items);
                return true;
            } catch (err) {
                Swal.showValidationMessage(err?.message || 'บันทึกไม่สำเร็จ');
                return false;
            }
        }
    });

    if (result.isDenied) {
        try {
            await saveCategoryNavConfig(getDefaultCategoryNavConfig());
            showToast('คืนค่าเริ่มต้นแล้ว');
        } catch {
            showToast('คืนค่าเริ่มต้นไม่สำเร็จ');
        }
    } else if (result.isConfirmed) {
        showToast('บันทึกแล้ว');
    }
}

function getCompanyFilterCodeFromCurrentFilter() {
    if (!String(state.currentFilter || '').startsWith('company-')) return '';
    return normalizeCompanyCode(String(state.currentFilter).replace('company-', '').trim());
}

function getEmployeeAssignments(employee) {
    return Array.isArray(employee?.assignments) ? employee.assignments : [];
}

function employeeHasCompanyAssignment(employee, companyCode) {
    const code = normalizeCompanyCode(companyCode);
    if (!code) return false;
    const assignments = getEmployeeAssignments(employee);
    if (!assignments.length) return normalizeCompanyCode(employee.company) === code;
    const byCompany = assignments.filter(a => normalizeCompanyCode(a?.company) === code);
    if (!byCompany.length) return false;
    if (byCompany.some(a => String(a?.email || '').trim() !== '')) return true;
    return true;
}

function getPreferredAssignmentForCurrentFilter(employee) {
    const assignments = getEmployeeAssignments(employee);
    if (!assignments.length) return null;
    const companyCode = getCompanyFilterCodeFromCurrentFilter();
    if (!companyCode) return assignments.find(a => a.is_primary == 1) || assignments[0] || null;
    const byCompany = assignments.filter(a => normalizeCompanyCode(a?.company) === companyCode);
    if (!byCompany.length) return assignments.find(a => a.is_primary == 1) || assignments[0] || null;
    return byCompany.find(a => String(a?.email || '').trim() !== '') || byCompany.find(a => a.is_primary == 1) || byCompany[0];
}

function getDisplayCompanyForEmployee(employee) {
    const companyCode = getCompanyFilterCodeFromCurrentFilter();
    if (companyCode && employeeHasCompanyAssignment(employee, companyCode)) return companyCode;
    const preferred = getPreferredAssignmentForCurrentFilter(employee);
    if (preferred?.company) return normalizeCompanyCode(preferred.company);
    return employee.company || '';
}

// Initialize
async function init() {
    await loadSession();
    updateInactiveNavVisibility();
    if (elements.categorySettingsBtn) elements.categorySettingsBtn.style.display = canManageDirectory() ? '' : 'none';
    await loadCategoryNavConfig();
    await loadJobPositions();
    await loadCompanyOrder();
    await loadDepartments();
    await loadEmployeesFromAPI();
    renderCompanyNav();
    applyTheme();
    updateStats();
    renderDepartments();
    applyCategoryNavConfig();
    renderEmployeeList();
    await selectEmployeeFromQuery();
    setupEventListeners();
    lastEmployeesFingerprint = createEmployeesFingerprint(state.employees);
    startAutoRefresh();
}

async function selectEmployeeFromQuery() {
    try {
        const params = new URLSearchParams(window.location.search || '');
        const emp = String(params.get('emp') || '').trim();
        if (!emp) return;
        const found = state.employees.find(e => String(e.id) === emp || String(e.employeeId || '') === emp);
        if (!found) return;
        selectEmployee(found.id);
    } catch (_) {}
}

function normalizeCompanyCode(v) {
    return String(v || '').trim().toUpperCase();
}

async function loadCompanyOrder() {
    try {
        const res = await fetch('api.php?action=get_company_order&v=' + Date.now(), { cache: 'no-store' });
        const json = await res.json();
        if (json && json.status === 'success' && Array.isArray(json.order)) {
            const normalized = json.order.map(normalizeCompanyCode).filter(Boolean);
            if (normalized.length) {
                state.companyOrder = [...new Set(normalized)];
                return;
            }
        }
    } catch (_) {}
    state.companyOrder = ['PTA', 'PT4', 'PTE'];
}

function getAvailableCompanyCodesFromEmployees() {
    const set = new Set();
    (Array.isArray(state.employees) ? state.employees : []).forEach((e) => {
        const primaryCode = normalizeCompanyCode(e?.company);
        if (primaryCode) set.add(primaryCode);
        const assignments = Array.isArray(e?.assignments) ? e.assignments : [];
        assignments.forEach((a) => {
            const code = normalizeCompanyCode(a?.company);
            if (code) set.add(code);
        });
    });
    return Array.from(set);
}

function getOrderedCompanyCodes() {
    const order = Array.isArray(state.companyOrder) ? state.companyOrder.map(normalizeCompanyCode).filter(Boolean) : [];
    const available = getAvailableCompanyCodesFromEmployees();
    const merged = [...new Set([...order, ...available])];
    return merged.sort((a, b) => {
        const ai = order.indexOf(a);
        const bi = order.indexOf(b);
        const aRank = ai === -1 ? 999 : ai;
        const bRank = bi === -1 ? 999 : bi;
        if (aRank !== bRank) return aRank - bRank;
        return a.localeCompare(b, 'en', { sensitivity: 'base' });
    });
}

function getCompanyDotClass(code) {
    const normalized = normalizeCompanyCode(code).toLowerCase();
    if (normalized === 'pta' || normalized === 'pt4' || normalized === 'pte') return normalized;
    return 'pta';
}

function getCompanyLogoUrl(code) {
    const normalized = normalizeCompanyCode(code).toLowerCase();
    if (!normalized) return '';
    return `uploads/logos/${normalized}_logo.png`;
}

function getCompanyTheme(code) {
    const normalized = normalizeCompanyCode(code);
    if (normalized === 'PTA') {
        return { primary: '#34c759', secondary: '#10b981', textOnPrimary: '#ffffff' };
    }
    if (normalized === 'PT4') {
        return { primary: '#ff3b30', secondary: '#ef4444', textOnPrimary: '#ffffff' };
    }
    if (normalized === 'PTE') {
        return { primary: '#007aff', secondary: '#2563eb', textOnPrimary: '#ffffff' };
    }
    return { primary: '#64748b', secondary: '#475569', textOnPrimary: '#ffffff' };
}

function getCompanyDisplayName(code) {
    const normalized = normalizeCompanyCode(code);
    if (normalized === 'PTA') return 'Powertech Engine Assembly Co., Ltd.';
    if (normalized === 'PT4') return 'Powertech 2004 Co., Ltd.';
    if (normalized === 'PTE') return 'Powertech Energy Solutions Co., Ltd.';
    return String(code || '').trim() || '-';
}

function renderCompanyNav() {
    if (!elements.companyList) return;
    const companyCodes = getOrderedCompanyCodes();
    elements.companyList.innerHTML = companyCodes.map((code) => `
        <button class="nav-item ${state.currentFilter === `company-${code}` ? 'active' : ''}" data-filter="company-${code}">
            <span class="company-dot ${getCompanyDotClass(code)}"></span>
            <span>${code}</span>
            <span class="nav-badge" data-company-badge="${code}">0</span>
        </button>
    `).join('');
}

async function loadJobPositions() {
    try {
        const res = await fetch('api.php?action=get_job_positions&v=' + Date.now(), { cache: 'no-store' });
        const json = await res.json();
        if (json && json.status === 'success' && Array.isArray(json.positions)) {
            jobPositionOptions = json.positions.map(p => String(p.name || '').trim()).filter(Boolean);
            jobPositionSortMap = json.positions.reduce((acc, p) => {
                const key = String(p.name || '').trim().toLowerCase();
                if (!key) return acc;
                const order = parseInt(p.sort_order, 10);
                acc[key] = Number.isFinite(order) && order > 0 ? order : 999;
                return acc;
            }, {});
            return;
        }
    } catch (_) {}
    jobPositionOptions = [];
    jobPositionSortMap = {};
}

function getPositionDatalistOptions(extra = '') {
    const merged = [...new Set([...(jobPositionOptions.length ? jobPositionOptions : defaultPositionOptions), extra].filter(Boolean))];
    return merged.map(name => `<option value="${name}"></option>`).join('');
}

async function saveJobPositionOption(name) {
    const n = String(name || '').trim();
    if (!n) return false;
    try {
        const fd = new FormData();
        fd.append('action', 'save_job_position');
        fd.append('name', n);
        const res = await fetch('api.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (!res.ok || json.status !== 'success') return false;
        await loadJobPositions();
        return true;
    } catch (_) {
        return false;
    }
}

// Load employees from API (fallback to sample data if failed)
async function loadEmployeesFromAPI() {
    try {
        let data;
        if (directorySession.loggedin && (directorySession.role === 'admin' || directorySession.role === 'staff')) {
            const res = await fetch('api.php?action=get&file=employees&v=' + Date.now(), { cache: 'no-store' });
            data = await res.json();
            if (!Array.isArray(data)) data = [];
            data.forEach(emp => {
                if (typeof emp.assignments === 'string') {
                    try { emp.assignments = JSON.parse(emp.assignments); } catch { emp.assignments = []; }
                } else if (!Array.isArray(emp.assignments)) {
                    emp.assignments = [];
                }
            });
        } else {
            const res = await fetch('api.php?action=get_public_directory&status_filter=active&v=' + Date.now(), { cache: 'no-store' });
            data = await res.json();
            if (!Array.isArray(data)) data = [];
        }
        const mapped = data.map(mapEmployeeFromApi);
        state.employees = mapped;
        updateLastUpdatedStamp(getLatestEmployeesUpdatedAt(mapped) || new Date());
    } catch (e) {
        console.error('Failed to load employees from API', e);
        if (!Array.isArray(state.employees) || state.employees.length === 0) {
            state.employees = isLocalDevHost ? employeeData.slice() : [];
        }
        if (state.employees.length > 0) {
            updateLastUpdatedStamp(getLatestEmployeesUpdatedAt(state.employees) || new Date());
        }
    }
    state.filteredEmployees = state.employees.slice();
}

function createEmployeesFingerprint(employees) {
    return (Array.isArray(employees) ? employees : [])
        .map(e => `${e.id}|${e.employeeId}|${e.name}|${e.position}|${e.department}|${e.company}|${e.status}|${e.createdAt}|${e.updatedAt}|${e.isExecutive ? 1 : 0}|${e.isManagement ? 1 : 0}`)
        .sort()
        .join('||');
}

async function refreshDirectoryDataSilently(showToast = false) {
    const selectedId = state.selectedEmployee ? state.selectedEmployee.id : null;
    const prevFingerprint = lastEmployeesFingerprint;
    const prevFilter = state.currentFilter;
    const prevCompanyCode = getCompanyFilterCodeFromCurrentFilter();
    await loadCompanyOrder();
    await loadJobPositions();
    await loadDepartments();
    await loadEmployeesFromAPI();
    renderCompanyNav();
    if (prevFilter.startsWith('company-') && prevCompanyCode) {
        const hasCompanyFilter = !!document.querySelector(`.nav-item[data-filter="company-${prevCompanyCode}"]`);
        if (!hasCompanyFilter) state.currentFilter = 'all';
    }
    const nextFingerprint = createEmployeesFingerprint(state.employees);
    const changed = nextFingerprint !== prevFingerprint;
    lastEmployeesFingerprint = nextFingerprint;
    if (changed) {
        renderDepartments();
        filterEmployees();
        if (selectedId != null) selectEmployee(selectedId);
        if (showToast && window.Swal) {
            Swal.fire({
                icon: 'success',
                title: 'อัปเดตข้อมูลล่าสุดแล้ว',
                timer: 1400,
                showConfirmButton: false
            });
        }
        return;
    }
    if (selectedId != null) {
        const latest = state.employees.find(e => String(e.id) === String(selectedId));
        if (latest) {
            state.selectedEmployee = latest;
            renderEmployeeDetail(latest);
        }
    }
}

function startAutoRefresh() {
    if (autoRefreshTimer) clearInterval(autoRefreshTimer);
    autoRefreshTimer = window.setInterval(() => {
        if (document.visibilityState !== 'visible') return;
        refreshDirectoryDataSilently(false);
    }, DIRECTORY_AUTO_REFRESH_MS);
    window.setInterval(() => updateLastUpdatedStamp(), 30000);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            updateLastUpdatedStamp();
            refreshDirectoryDataSilently(false);
        }
    });
    window.addEventListener('online', () => refreshDirectoryDataSilently(false));
}

async function loadSession() {
    if (isPublicMode) {
        directorySession = { loggedin: false, role: 'guest', username: '', display_name: '' };
        return;
    }
    try {
        const res = await fetch('api.php?action=check_session');
        const sess = await res.json();
        directorySession = {
            loggedin: !!sess.loggedin,
            role: sess.role || 'guest',
            username: sess.username || '',
            display_name: sess.display_name || ''
        };
    } catch {
        directorySession = { loggedin: false, role: 'guest', username: '', display_name: '' };
    }
}

async function loadDepartments() {
    try {
        const res = await fetch('api.php?action=get&file=departments&v=' + Date.now());
        let data = await res.json();
        if (!Array.isArray(data)) data = [];
        state.departments = data.filter(d => !isCancelledManagementDepartmentName(d && (d.dept_name || d.name)));
    } catch {
        state.departments = [];
    }
}

// Theme
function applyTheme() {
    document.body.classList.toggle('dark', state.isDarkMode);
}

function toggleTheme() {
    state.isDarkMode = !state.isDarkMode;
    localStorage.setItem('darkMode', state.isDarkMode);
    applyTheme();
}

// Stats
function updateStats() {
    const activeEmployees = state.employees.filter(isEmployeeActive);
    const inactiveEmployees = state.employees.filter(e => !isEmployeeActive(e));
    const total = activeEmployees.length;
    const filtered = state.filteredEmployees.length;
    const favCount = activeEmployees.filter(e => state.favorites.includes(e.id)).length;
    const execCount = activeEmployees.filter(e => e.isExecutive).length;
    const companyCodes = getOrderedCompanyCodes();

    elements.totalCount.textContent = total;
    elements.filteredCount.textContent = filtered;
    elements.favoriteCount.textContent = favCount;
    elements.allBadge.textContent = total;
    if (elements.inactiveBadge) elements.inactiveBadge.textContent = inactiveEmployees.length;
    elements.favBadge.textContent = favCount;
    elements.execBadge.textContent = execCount;
    updateManagementNavVisibility(false);
    companyCodes.forEach((code) => {
        const badge = document.querySelector(`[data-company-badge="${code}"]`);
        if (badge) badge.textContent = activeEmployees.filter(e => employeeHasCompanyAssignment(e, code)).length;
    });
}

// Departments
function renderDepartments() {
    const activeEmployees = state.employees.filter(isEmployeeActive);
    const departments = [...new Set(activeEmployees.map(e => e.department).filter(Boolean))]
        .filter(d => !isCancelledManagementDepartmentName(d))
        .sort((a, b) => compareDepartmentNames(a, b));
    updateManagementNavVisibility(false);

    if (!elements.departmentList) return;
    elements.departmentList.innerHTML = departments.map(dept => `
        <button class="nav-item" data-filter="dept-${dept}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
            </svg>
            <span>${dept}</span>
            <span class="nav-badge">${activeEmployees.filter(e => e.department === dept).length}</span>
        </button>
    `).join('');
    applyCategoryNavConfig();
}

// Filter
function filterEmployees() {
    updateManagementNavVisibility(true);
    const showInactive = state.currentFilter === 'inactive';
    let filtered = showInactive
        ? state.employees.filter(e => !isEmployeeActive(e))
        : state.employees.filter(isEmployeeActive);

    // Apply search
    if (state.searchQuery) {
        const query = state.searchQuery.toLowerCase();
        filtered = filtered.filter(e =>
            e.name.toLowerCase().includes(query) ||
            e.nameEn.toLowerCase().includes(query) ||
            e.position.toLowerCase().includes(query) ||
            e.department.toLowerCase().includes(query) ||
            e.email.toLowerCase().includes(query) ||
            e.phone.includes(query) ||
            e.mobile.includes(query)
        );
    }

    // Apply filter
    switch (state.currentFilter) {
        case 'management':
            filtered = filtered.filter(e => !!e.isManagement);
            filtered.sort(compareEmployeesByRankThenName);
            elements.listTitle.textContent = getCategoryLabelByKey('management') || 'ระดับ Management';
            break;
        case 'favorites':
            filtered = filtered.filter(e => state.favorites.includes(e.id));
            filtered.sort(compareEmployeesByRankThenName);
            elements.listTitle.textContent = getCategoryLabelByKey('favorites') || 'รายการปักหมุด';
            break;
        case 'executives':
            filtered = filtered.filter(e => e.isExecutive);
            filtered.sort(compareEmployeesByRankThenName);
            elements.listTitle.textContent = getCategoryLabelByKey('executives') || 'ผู้บริหาร';
            break;
        case 'inactive':
            filtered.sort(compareEmployeesByRankThenName);
            elements.listTitle.textContent = getCategoryLabelByKey('inactive') || 'พนักงาน Inactive';
            break;
        default:
            if (state.currentFilter.startsWith('company-')) {
                const code = getCompanyFilterCodeFromCurrentFilter();
                filtered = filtered.filter(e => employeeHasCompanyAssignment(e, code));
                filtered.sort(compareEmployeesByRankThenName);
                elements.listTitle.textContent = code ? `บริษัท ${code}` : 'พนักงานทั้งหมด';
            } else if (state.currentFilter.startsWith('dept-')) {
                const dept = state.currentFilter.replace('dept-', '');
                filtered = filtered.filter(e => e.department === dept);
                filtered.sort(compareDepartmentMembers);
                elements.listTitle.textContent = `แผนก ${dept}`;
            } else {
                filtered.sort(compareEmployeesByRankThenName);
                elements.listTitle.textContent = getCategoryLabelByKey('all') || 'พนักงานทั้งหมด';
            }
    }

    state.filteredEmployees = filtered;
    updateStats();
    renderEmployeeList();
}

// Render Employee List
function renderEmployeeList() {
    const isGrid = state.viewMode === 'grid';
    elements.employeeList.className = `employee-list ${isGrid ? 'grid-view' : ''}`;

    if (state.filteredEmployees.length === 0) {
        elements.employeeList.innerHTML = `
            <div class="empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.3-4.3"></path>
                </svg>
                <p>ไม่พบข้อมูลพนักงาน</p>
            </div>
        `;
        return;
    }

    // Group by department if showing all
    let html = '';
    if (state.currentFilter === 'all' && !state.searchQuery && !isGrid) {
        const grouped = groupByDepartment(state.filteredEmployees);
        for (const [dept, employees] of Object.entries(grouped)) {
            html += `<div class="group-header">${dept}</div>`;
            html += employees.map(e => renderEmployeeCard(e)).join('');
        }
    } else {
        html = state.filteredEmployees.map(e => renderEmployeeCard(e)).join('');
    }

    elements.employeeList.innerHTML = html;
}

function groupByDepartment(employees) {
    const grouped = employees.reduce((acc, emp) => {
        const deptKey = String(emp.department || '').trim() || 'ไม่ระบุแผนก';
        if (!acc[deptKey]) acc[deptKey] = [];
        acc[deptKey].push(emp);
        return acc;
    }, {});
    Object.keys(grouped).forEach(dept => {
        grouped[dept].sort(compareDepartmentMembers);
    });
    const orderMap = getDepartmentSortOrderMap();
    const ordered = {};
    Object.keys(grouped).sort((a, b) => compareDepartmentNames(a, b, orderMap)).forEach((dept) => {
        ordered[dept] = grouped[dept];
    });
    return ordered;
}

function renderEmployeeCard(employee) {
    const isFavorite = state.favorites.includes(employee.id);
    const isSelected = state.selectedEmployee?.id === employee.id;
    const initials = getInitials(employee.name);
    const safeId = String(employee.id).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    const isNewUser = isRecentlyAdded(employee);
    const displayCompany = getDisplayCompanyForEmployee(employee);
    const contextAssign = getPreferredAssignmentForCurrentFilter(employee);
    const displayDepartment = String(contextAssign?.department || employee.department || '').trim();

    return `
        <div class="employee-card company-${displayCompany || employee.company} ${isSelected ? 'active' : ''}" 
             data-id="${safeId}" 
             onclick="selectEmployee('${safeId}')"
             style="animation-delay: 0.03s">
            <div class="avatar">
                ${employee.avatar ? `<img src="${employee.avatar}" alt="${employee.name}">` : initials}
            </div>
            <div class="card-info">
                <div class="card-name">${employee.name}</div>
                <div class="card-position">${employee.position}</div>
                ${displayDepartment ? `<div class="card-department">${displayDepartment}</div>` : ``}
            </div>
            <div class="card-actions">
                ${isNewUser ? '<span class="new-badge">NEW</span>' : ''}
                <button class="favorite-btn ${isFavorite ? 'active' : ''}" 
                        onclick="event.stopPropagation(); toggleFavorite('${safeId}')"
                        title="${isFavorite ? 'ยกเลิกปักหมุด' : 'ปักหมุด'}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="${isFavorite ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                </button>
                <span class="company-badge ${displayCompany || employee.company}">${displayCompany || employee.company}</span>
            </div>
        </div>
    `;
}

function getInitials(name) {
    const parts = name.split(' ');
    if (parts.length >= 2) {
        return parts[0].charAt(0) + parts[1].charAt(0);
    }
    return name.substring(0, 2);
}

// Select Employee
function selectEmployee(id) {
    const employee = state.employees.find(e => String(e.id) === String(id));
    if (!employee) return;

    state.selectedEmployee = employee;
    renderEmployeeList();
    renderEmployeeDetail(employee);

    // Mobile: show detail
    if (window.innerWidth <= 1024) {
        elements.employeeDetail.classList.add('mobile-show');
    }
}

// Render Employee Detail
function renderEmployeeDetail(employee) {
    const isFavorite = state.favorites.includes(employee.id);
    const initials = getInitials(employee.name);
    const yearsWorked = calculateYearsWorked(employee.startDate);
    const empId = employee.employeeId || employee.id || '';
    const thaiName = employee.nameTh || employee.name_th || (employee.name !== employee.nameEn ? employee.name : '');
    const showActions = directorySession.loggedin && (directorySession.role === 'admin' || directorySession.role === 'staff');
    const assignments = Array.isArray(employee.assignments) ? employee.assignments : [];
    const primaryAssign = getPreferredAssignmentForCurrentFilter(employee) || assignments.find(a => a.is_primary == 1) || assignments[0] || null;
    const contactPhone = normalizePhoneValue(employee.phone || '');
    const contactEmail = primaryAssign?.email || employee.email || '';
    const displayCompany = (primaryAssign?.company || getDisplayCompanyForEmployee(employee) || employee.company || '').trim();
    const displayDepartment = (primaryAssign?.department || employee.department || '').trim();
    const nameEn = employee.nameEn || employee.name || employee.nameTh || employee.name_th || '-';
    const statusText = employee.status || 'ทำงานอยู่';
    const isAdminUser = directorySession.role === 'admin';
    const safeEmployeeId = String(employee.id).replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    elements.employeeDetail.innerHTML = `
        <div class="detail-content">
            <button class="detail-close" onclick="closeDetail()" style="position: absolute; top: 16px; right: 16px; width: 36px; height: 36px; border: none; background: var(--bg-hover); border-radius: 50%; cursor: pointer; color: var(--text-secondary);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="detail-header">
                <div class="detail-avatar">
                    ${employee.avatar ? `<img src="${employee.avatar}" alt="${employee.name}">` : initials}
                </div>
                <h1 class="detail-name">${nameEn}</h1>
                ${thaiName ? `<p class="detail-position">${thaiName}</p>` : ''}
                <div class="detail-meta-row">
                    ${empId ? `<span class="detail-meta-pill">${empId}</span>` : ''}
                    ${employee.position ? `<span class="detail-meta-pill meta-role">${employee.position}</span>` : ''}
                </div>
                <div class="detail-badges">
                    <span class="detail-badge company ${displayCompany || employee.company}">${displayCompany || employee.company}</span>
                    ${displayDepartment ? `<span class="detail-badge department">${displayDepartment}</span>` : ``}
                    ${employee.isExecutive ? '<span class="detail-badge department">ผู้บริหาร</span>' : ''}
                    ${isRecentlyAdded(employee) ? '<span class="detail-badge new-user">พนักงานใหม่</span>' : ''}
                </div>
            </div>

            <div class="detail-section">
                <div class="detail-section-title">ข้อมูลติดต่อ</div>
                <div class="contact-cards">
                    <div class="contact-card" onclick='handlePhoneAction(${JSON.stringify(contactPhone)})'>
                        <div class="contact-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                        </div>
                        <div class="contact-info">
                            <div class="contact-label">โทรศัพท์</div>
                            <div class="contact-value">${contactPhone || '-'}</div>
                        </div>
                        <button class="contact-action" title="โทรออกหรือคัดลอก">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                        </button>
                    </div>
            </div>

            <div class="detail-section">
                <div class="detail-section-title">สังกัด</div>
                <div class="assignments-list">
                    ${assignments.map(a => `
                        <div class="assignment-card ${a.is_primary == 1 ? 'primary' : ''}">
                            <div class="assignment-header">
                                <span class="assignment-company">${a.company || '-'}</span>
                                ${a.is_primary == 1 ? '<span class="assignment-badge">หลัก</span>' : ''}
                            </div>
                            <div class="assignment-dept">${a.department || '-'}</div>
                            <div class="assignment-email-row">
                                <span class="assignment-email">${a.email || '-'}</span>
                                ${a.email ? `<button class="assignment-copy" onclick="event.stopPropagation(); copyToClipboard('${a.email}','อีเมล')">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                </button>` : ''}
                            </div>
                        </div>
                    `).join('')}
                    ${assignments.length === 0 ? '<div class="assignment-empty">ยังไม่มีข้อมูลสังกัด</div>' : ''}
                </div>
            </div>

            <div class="info-section">
                <div class="info-section-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    ข้อมูลพนักงาน
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">ชื่อภาษาอังกฤษ</span>
                        <span class="info-value">${nameEn}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">รหัสพนักงาน</span>
                        <span class="info-value">${empId || '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">วันเริ่มงาน</span>
                        <span class="info-value">${isAdminUser ? formatDate(employee.startDate) : '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">อายุงาน</span>
                        <span class="info-value">${isAdminUser ? (yearsWorked + ' ปี') : '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">วันเกิด</span>
                        <span class="info-value">${isAdminUser ? formatDate(employee.birthday) : '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">สถานะ</span>
                        <span class="info-value" style="color: var(--success);">${statusText}</span>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; justify-content: center; margin-top: 16px;">
                <button onclick="toggleFavorite('${employee.id}')" style="
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 12px 24px;
                    border: 1px solid var(--border);
                    background: var(--bg-card);
                    border-radius: var(--radius-lg);
                    font-size: 14px;
                    font-weight: 500;
                    color: ${isFavorite ? '#ff9500' : 'var(--text-secondary)'};
                    cursor: pointer;
                    transition: var(--transition);
                ">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="${isFavorite ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                    ${isFavorite ? 'ยกเลิกปักหมุด' : 'ปักหมุด'}
                </button>
                <a href="mailto:${contactEmail}" style="
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 12px 24px;
                    border: none;
                    background: var(--accent);
                    border-radius: var(--radius-lg);
                    font-size: 14px;
                    font-weight: 500;
                    color: white;
                    cursor: pointer;
                    text-decoration: none;
                    transition: var(--transition);
                ">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    ส่งอีเมล
                </a>
                ${showActions ? `
                <button onclick="openAdminAddUser()" style="
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 12px 20px;
                    border: 1px dashed var(--border);
                    background: var(--bg-card);
                    border-radius: var(--radius-lg);
                    font-size: 14px;
                    font-weight: 500;
                    color: var(--text-secondary);
                    cursor: pointer;
                    transition: var(--transition);
                ">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14"></path>
                        <path d="M5 12h14"></path>
                    </svg>
                    เพิ่ม User
                </button>
                <button onclick="openAdminEdit('${empId}')" style="
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 12px 20px;
                    border: 1px solid var(--border);
                    background: var(--bg-secondary);
                    border-radius: var(--radius-lg);
                    font-size: 14px;
                    font-weight: 500;
                    color: var(--text-secondary);
                    cursor: pointer;
                    transition: var(--transition);
                ">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 20h9"></path>
                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                    </svg>
                    แก้ไข User
                </button>
                ` : ''}
            </div>
        </div>
    `;

    // Show close button on mobile
    if (window.innerWidth <= 1024) {
        elements.employeeDetail.querySelector('.detail-close').style.display = 'flex';
    }
}

function closeDetail() {
    elements.employeeDetail.classList.remove('mobile-show');
}

async function openEmployeeEditor(mode, baseEmployee) {
    if (typeof Swal === 'undefined') {
        if (mode === 'edit') {
            const empId = baseEmployee && (baseEmployee.employeeId || baseEmployee.id || '');
            const url = `admin.html?tab=employees&employeeId=${encodeURIComponent(empId || '')}`;
            window.open(url, '_blank');
        } else {
            const url = `admin.html?tab=employees&mode=add`;
            window.open(url, '_blank');
        }
        return;
    }

    const isEdit = mode === 'edit';
    const emp = baseEmployee || {};
    const empId = emp.employeeId || emp.id || '';
    const assignments = Array.isArray(emp.assignments) ? emp.assignments : [];
    const primary = assignments.find(a => a.is_primary == 1) || assignments[0] || {};
    const company = primary.company || emp.company || '';
    const department = primary.department || emp.department || '';
    const email = primary.email || emp.email || '';
    const status = emp.employment_status || emp.status || 'active';
    const nameEnValue = emp.nameEn || emp.name || '';
    const nameThValue = emp.name_th || (emp.nameEn && emp.name && emp.name !== emp.nameEn ? emp.name : '');
    const startDateRaw = emp.start_date || emp.startDate || '';
    const birthdateRaw = emp.birthdate || emp.birthday || '';
    const startDateValue = startDateRaw && startDateRaw.length >= 10 ? startDateRaw.substring(0, 10) : '';
    const birthdateValue = birthdateRaw && birthdateRaw.length >= 10 ? birthdateRaw.substring(0, 10) : '';
    const username = emp.username || '';
    const role = emp.role || 'user';
    const responsibleDepartment = emp.responsible_department || '';
    const isAdmin = directorySession.role === 'admin';
    const isExecutiveFlag = String(emp.is_executive ?? (emp.isExecutive ? 1 : 0) ?? '0') === '1';
    const isManagementFlag = String(emp.is_management ?? (emp.isManagement ? 1 : 0) ?? '0') === '1';
    const deptList = Array.isArray(state.departments) ? state.departments : [];
    const deptNames = deptList.map(d => d.dept_name || d.name || '').filter(Boolean);
    const empPosition = emp.position || '';
    const positionOptionsHtml = getPositionDatalistOptions(empPosition);

    const html = `
        <form id="directory-employee-form" class="dir-popup">
            <div class="dir-popup-section">
                <div class="dir-popup-title">ข้อมูลพนักงาน</div>
                <div class="dir-popup-grid">
                    <div>
                        <div class="dir-field-label">รหัสพนักงาน</div>
                        <input type="text" name="id" class="dir-field-input" value="${isEdit ? empId : ''}" ${isEdit ? 'readonly' : ''}>
                    </div>
                    <div>
                        <div class="dir-field-label">สถานะการทำงาน</div>
                        <select name="employment_status" class="dir-field-input">
                            <option value="active" ${status === 'active' ? 'selected' : ''}>Active</option>
                            <option value="inactive" ${status === 'inactive' ? 'selected' : ''}>Inactive</option>
                        </select>
                    </div>
                    <div>
                        <div class="dir-field-label">ชื่อ-สกุล (EN)</div>
                        <input type="text" name="name" class="dir-field-input" value="${isEdit ? nameEnValue : ''}">
                    </div>
                    <div>
                        <div class="dir-field-label">ชื่อ-สกุล (TH)</div>
                        <input type="text" name="name_th" class="dir-field-input" value="${isEdit ? nameThValue : ''}">
                    </div>
                    <div>
                        <div class="dir-field-label">ตำแหน่ง</div>
                        <div style="display:flex;gap:8px;">
                            <input type="text" id="dir-position-input" name="position" class="dir-field-input" value="${isEdit ? empPosition : ''}" list="dir-position-list">
                            <button type="button" id="dir-save-position-btn" class="dir-assign-add-btn" style="max-width:140px;padding:0 10px;">บันทึกตัวเลือก</button>
                        </div>
                        <datalist id="dir-position-list">${positionOptionsHtml}</datalist>
                    </div>
                    <div>
                        <div class="dir-field-label">เบอร์โทร</div>
                        <input type="text" name="phone" class="dir-field-input" value="${isEdit ? (emp.phone || '') : ''}">
                    </div>
                    <div>
                        <div class="dir-field-label">ระดับ</div>
                        <div style="display:flex;gap:12px;flex-wrap:wrap;">
                            <label class="dir-popup-inline">
                                <input type="checkbox" name="is_management" value="1" ${isManagementFlag ? 'checked' : ''} ${isAdmin ? '' : 'disabled'}>
                                <span class="dir-field-label" style="margin:0;">ระดับ Management</span>
                            </label>
                            <label class="dir-popup-inline">
                                <input type="checkbox" name="is_executive" value="1" ${isExecutiveFlag ? 'checked' : ''} ${isAdmin ? '' : 'disabled'}>
                                <span class="dir-field-label" style="margin:0;">ผู้บริหาร</span>
                            </label>
                        </div>
                    </div>
                    <div style="display:${isAdmin ? 'block' : 'none'};">
                        <div class="dir-field-label">วันเริ่มงาน</div>
                        <input type="date" name="start_date" class="dir-field-input" value="${startDateValue}">
                    </div>
                    <div style="display:${isAdmin ? 'block' : 'none'};">
                        <div class="dir-field-label">วันเกิด</div>
                        <input type="date" name="birthdate" class="dir-field-input" value="${birthdateValue}">
                    </div>
                </div>
            </div>
            <div class="dir-popup-section">
                <div class="dir-popup-title">สังกัดและการติดต่อ</div>
                <div id="dir-assignments-container" class="dir-popup-assignments"></div>
                <button type="button" id="dir-add-assignment" class="dir-assign-add-btn">+ เพิ่มสังกัด</button>
            </div>
            ${isAdmin ? `
            <div class="dir-popup-section">
                <div class="dir-popup-title">ข้อมูลการเข้าสู่ระบบ</div>
                <div class="dir-popup-grid">
                    <div>
                        <div class="dir-field-label">Username</div>
                        <input type="text" name="username" class="dir-field-input" value="${isEdit ? username : ''}">
                    </div>
                    <div>
                        <div class="dir-field-label">Password</div>
                        <input type="password" name="password" class="dir-field-input" placeholder="${isEdit ? 'เว้นว่างถ้าไม่เปลี่ยน' : ''}">
                    </div>
                    <div>
                        <div class="dir-field-label">Role</div>
                        <select name="role" class="dir-field-input">
                            <option value="user" ${role === 'user' ? 'selected' : ''}>User</option>
                            <option value="staff" ${role === 'staff' ? 'selected' : ''}>Staff</option>
                            <option value="admin" ${role === 'admin' ? 'selected' : ''}>Admin</option>
                        </select>
                    </div>
                    <div>
                        <div class="dir-field-label">แผนกที่รับผิดชอบ (สำหรับ Staff)</div>
                        <input type="text" name="responsible_department" class="dir-field-input" value="${isEdit ? responsibleDepartment : ''}" placeholder="เช่น IT, HR">
                        <div class="dir-field-help">คั่นด้วย , หากมีหลายแผนก</div>
                    </div>
                </div>
            </div>
            ` : ''}
            <div class="dir-popup-section">
                <div class="dir-popup-title">รูปภาพ</div>
                <input type="file" name="imageFile" accept="image/*" class="dir-field-input">
            </div>
            ${isEdit && isAdmin ? `
            <div class="dir-popup-section">
                <button type="button" data-dir-delete="1" class="dir-field-input" style="background:rgba(239,68,68,0.08);border-color:#ef4444;color:#ef4444;cursor:pointer;text-align:center;">
                    ลบผู้ใช้งานนี้
                </button>
            </div>
            ` : ''}
        </form>
    `;

    const title = isEdit ? 'แก้ไขข้อมูลพนักงาน' : 'เพิ่มพนักงานใหม่';

    const result = await Swal.fire({
        title: title,
        html: html,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'บันทึก',
        cancelButtonText: 'ยกเลิก',
        customClass: {
            popup: 'swal2-directory-employee'
        },
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        didOpen: () => {
            const container = document.getElementById('dir-assignments-container');
            const addBtn = document.getElementById('dir-add-assignment');
            if (container) {
                const deptOptsHtml = deptNames.length
                    ? [''].concat(deptNames).map(name => `<option value="${name}">${name || 'เลือกแผนก'}</option>`).join('')
                    : `<option value="">เลือกแผนก</option>`;
                const companyOptions = `
                    <option value="">เลือกบริษัท</option>
                    <option value="PTA">PTA</option>
                    <option value="PT4">PT4</option>
                    <option value="PTE">PTE</option>
                `;
                const createRow = (assign, isPrimary) => {
                    const row = document.createElement('div');
                    row.className = 'dir-assign-row';
                    row.innerHTML = `
                        <select class="dir-field-input dir-assign-company">
                            ${companyOptions}
                        </select>
                        <select class="dir-field-input dir-assign-dept">
                            ${deptOptsHtml}
                        </select>
                        <input type="email" class="dir-field-input dir-assign-email" placeholder="อีเมล">
                        <label class="dir-popup-inline">
                            <input type="radio" name="primary_assignment" class="dir-assign-primary">
                            <span class="dir-field-label" style="margin:0;">หลัก</span>
                        </label>
                        <button type="button" class="dir-assign-remove">ลบ</button>
                    `;
                    const coSel = row.querySelector('.dir-assign-company');
                    const deptSel = row.querySelector('.dir-assign-dept');
                    const emailInput = row.querySelector('.dir-assign-email');
                    const primaryInput = row.querySelector('.dir-assign-primary');
                    if (coSel) coSel.value = assign.company || '';
                    if (deptSel) deptSel.value = assign.department || '';
                    if (emailInput) emailInput.value = assign.email || '';
                    if (primaryInput) primaryInput.checked = !!isPrimary;
                    const removeBtn = row.querySelector('.dir-assign-remove');
                    if (removeBtn) {
                        removeBtn.addEventListener('click', () => {
                            const allRows = container.querySelectorAll('.dir-assign-row');
                            if (allRows.length <= 1) return;
                            row.remove();
                        });
                    }
                    container.appendChild(row);
                };
                const baseAssignments = assignments.length
                    ? assignments
                    : [{
                        company: company || '',
                        department: department || '',
                        email: email || '',
                        is_primary: 1
                    }];
                baseAssignments.forEach((a, idx) => {
                    const isPrimary = a.is_primary == 1 || (!assignments.length && idx === 0);
                    createRow(a, isPrimary);
                });
                if (addBtn) {
                    addBtn.addEventListener('click', () => {
                        createRow({ company: '', department: '', email: '' }, container.querySelectorAll('.dir-assign-row').length === 0);
                    });
                }
            }
            const posSaveBtn = document.getElementById('dir-save-position-btn');
            if (posSaveBtn) {
                posSaveBtn.addEventListener('click', async () => {
                    const posInput = document.getElementById('dir-position-input');
                    const ok = await saveJobPositionOption(posInput ? posInput.value : '');
                    if (ok) {
                        const dl = document.getElementById('dir-position-list');
                        if (dl) dl.innerHTML = getPositionDatalistOptions(posInput ? posInput.value : '');
                        showToast('บันทึกตำแหน่งเป็นตัวเลือกแล้ว');
                    } else {
                        showToast('บันทึกตำแหน่งไม่สำเร็จ');
                    }
                });
            }
            const delBtn = document.querySelector('[data-dir-delete="1"]');
            if (delBtn) {
                delBtn.addEventListener('click', async () => {
                    const confirmResult = await Swal.fire({
                        icon: 'warning',
                        title: 'ยืนยันการลบผู้ใช้งานนี้',
                        text: 'การลบจะไม่สามารถกู้คืนได้',
                        confirmButtonText: 'ลบ',
                        cancelButtonText: 'ยกเลิก',
                        showCancelButton: true,
                        confirmButtonColor: '#ef4444'
                    });
                    if (!confirmResult.isConfirmed) return;
                    try {
                        const fdDel = new FormData();
                        fdDel.append('action', 'delete_item');
                        fdDel.append('key', 'employees');
                        fdDel.append('id', empId);
                        const resDel = await fetch('api.php', { method: 'POST', body: fdDel });
                        let dataDel;
                        try {
                            dataDel = await resDel.json();
                        } catch {
                            throw new Error('การตอบกลับจากเซิร์ฟเวอร์ไม่ถูกต้อง');
                        }
                        if (!resDel.ok || dataDel.status !== 'success') {
                            throw new Error(dataDel.message || 'ไม่สามารถลบผู้ใช้ได้');
                        }
                        await Swal.fire({
                            icon: 'success',
                            title: 'ลบผู้ใช้เรียบร้อยแล้ว',
                            timer: 1600,
                            showConfirmButton: false
                        });
                        await loadEmployeesFromAPI();
                        filterEmployees();
                        closeDetail();
                    } catch (err) {
                        await Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: err.message || 'ไม่สามารถลบผู้ใช้ได้'
                        });
                    }
                });
            }
        },
        preConfirm: async () => {
            try {
                const form = document.getElementById('directory-employee-form');
                if (!form) {
                    Swal.showValidationMessage('ไม่พบฟอร์ม');
                    return false;
                }

                const id = (form.querySelector('[name="id"]').value || '').trim();
                const name = (form.querySelector('[name="name"]').value || '').trim();
                const employmentStatus = (form.querySelector('[name="employment_status"]').value || '').trim();
                const startDateInput = (form.querySelector('[name="start_date"]').value || '').trim();
                const birthdateInput = (form.querySelector('[name="birthdate"]').value || '').trim();
                const usernameInput = isAdmin ? (form.querySelector('[name="username"]').value || '').trim() : '';
                const passwordInput = isAdmin ? (form.querySelector('[name="password"]').value || '').trim() : '';
                const isExecutiveFlag = !!form.querySelector('input[name="is_executive"]')?.checked;
                const isManagementFlag = !!form.querySelector('input[name="is_management"]')?.checked;
                const allowEmptyAssignments = isExecutiveFlag || isManagementFlag;
                const errors = [];

                if (!id) errors.push('กรุณากรอกรหัสพนักงาน');
                if (!name) errors.push('กรุณากรอกชื่อภาษาอังกฤษ (EN)');
                if (!employmentStatus) errors.push('กรุณาเลือกสถานะการทำงาน');
                if (startDateInput && !/^\d{4}-\d{2}-\d{2}$/.test(startDateInput)) {
                    errors.push('วันเริ่มงานต้องอยู่ในรูปแบบ YYYY-MM-DD');
                }
                if (birthdateInput && !/^\d{4}-\d{2}-\d{2}$/.test(birthdateInput)) {
                    errors.push('วันเกิดต้องอยู่ในรูปแบบ YYYY-MM-DD');
                }
                if (!isEdit && usernameInput && !passwordInput) {
                    errors.push('กรุณากรอกรหัสผ่านเมื่อกำหนด Username');
                }

                if (errors.length) {
                    Swal.showValidationMessage(errors.join('<br>'));
                    return false;
                }

                const fd = new FormData(form);
                const positionInput = (form.querySelector('[name="position"]')?.value || '').trim();
                if (positionInput) {
                    await saveJobPositionOption(positionInput);
                }
                const rows = Array.from(document.querySelectorAll('.dir-assign-row'));
                const assignmentsToSave = [];
                let primaryCount = 0;
                rows.forEach(row => {
                    const coSel = row.querySelector('.dir-assign-company');
                    const deptSel = row.querySelector('.dir-assign-dept');
                    const emailInput = row.querySelector('.dir-assign-email');
                    const primaryInput = row.querySelector('.dir-assign-primary');
                    const companyVal = (coSel && coSel.value || '').trim();
                    const deptVal = (deptSel && deptSel.value || '').trim();
                    const emailVal = (emailInput && emailInput.value || '').trim();
                    const isPrimary = primaryInput && primaryInput.checked ? 1 : 0;
                    if (!companyVal && !deptVal && !emailVal) {
                        return;
                    }
                    if (!companyVal) errors.push('ทุกสังกัดต้องระบุบริษัท');
                    if (!deptVal && !allowEmptyAssignments) errors.push('ทุกสังกัดต้องระบุแผนก (ยกเว้นผู้บริหาร/ระดับ Management)');
                    if (emailVal && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
                        errors.push('รูปแบบอีเมลไม่ถูกต้องในบางสังกัด');
                    }
                    if (isPrimary) primaryCount++;
                    assignmentsToSave.push({
                        company: companyVal,
                        department: deptVal,
                        email: emailVal,
                        is_primary: isPrimary
                    });
                });
                if (!assignmentsToSave.length && !allowEmptyAssignments) {
                    errors.push('กรุณาระบุอย่างน้อย 1 สังกัด');
                }
                if (assignmentsToSave.length) {
                    if (primaryCount === 0) {
                        errors.push('กรุณาเลือกสังกัดหลักอย่างน้อย 1 รายการ');
                    }
                    if (primaryCount > 1) {
                        errors.push('เลือกสังกัดหลักได้เพียง 1 รายการ');
                    }
                }
                if (errors.length) {
                    Swal.showValidationMessage(errors.join('<br>'));
                    return false;
                }
                fd.append('assignments', JSON.stringify(assignmentsToSave));
                fd.append('action', isEdit ? 'update_employee' : 'add_employee');

                const res = await fetch('api.php', { method: 'POST', body: fd });
                let data;
                try {
                    data = await res.json();
                } catch {
                    throw new Error('การตอบกลับจากเซิร์ฟเวอร์ไม่ถูกต้อง');
                }
                if (!res.ok || data.status !== 'success') {
                    throw new Error(data.message || 'ไม่สามารถบันทึกข้อมูลได้');
                }
                return { id };
            } catch (err) {
                Swal.showValidationMessage(err.message || 'ไม่สามารถบันทึกข้อมูลได้');
                return false;
            }
        }
    });

    if (result.isConfirmed && result.value && result.value.id) {
        await loadEmployeesFromAPI();
        filterEmployees();
        selectEmployee(result.value.id);
        await Swal.fire({
            icon: 'success',
            title: 'บันทึกสำเร็จ',
            text: isEdit ? 'แก้ไขข้อมูลพนักงานเรียบร้อยแล้ว' : 'เพิ่มพนักงานใหม่เรียบร้อยแล้ว',
            timer: 1800,
            showConfirmButton: false
        });
        showToast('บันทึกข้อมูลพนักงานเรียบร้อยแล้ว');
    }
}

async function openAdminEdit(empId) {
    if (!directorySession.loggedin || (directorySession.role !== 'admin' && directorySession.role !== 'staff')) {
        showToast('ต้องเข้าสู่ระบบในสิทธิ์ Admin หรือ Staff');
        return;
    }
    const employee = state.employees.find(e => String(e.id) === String(empId));
    if (!employee) {
        showToast('ไม่พบข้อมูลพนักงาน');
        return;
    }
    let fullEmployee = null;
    try {
        const res = await fetch('api.php?action=get&file=employees&v=' + Date.now());
        if (res.ok) {
            const list = await res.json();
            if (Array.isArray(list)) {
                list.forEach(empItem => {
                    if (typeof empItem.assignments === 'string') {
                        try { empItem.assignments = JSON.parse(empItem.assignments); } catch { empItem.assignments = []; }
                    } else if (!Array.isArray(empItem.assignments)) {
                        empItem.assignments = [];
                    }
                });
                fullEmployee = list.find(e => String(e.id) === String(empId)) || null;
            }
        }
    } catch {
        fullEmployee = null;
    }
    await openEmployeeEditor('edit', fullEmployee || employee);
}

async function openAdminAddUser() {
    if (!directorySession.loggedin || (directorySession.role !== 'admin' && directorySession.role !== 'staff')) {
        showToast('ต้องเข้าสู่ระบบในสิทธิ์ Admin หรือ Staff');
        return;
    }
    const base = state.selectedEmployee || null;
    await openEmployeeEditor('add', base);
}

// Toggle Favorite
function toggleFavorite(id) {
    const index = state.favorites.indexOf(id);
    if (index > -1) {
        state.favorites.splice(index, 1);
        showToast('ยกเลิกปักหมุดแล้ว');
    } else {
        state.favorites.push(id);
        showToast('ปักหมุดแล้ว');
    }
    localStorage.setItem('favorites', JSON.stringify(state.favorites));
    updateStats();
    renderEmployeeList();
    if (state.selectedEmployee?.id === id) {
        renderEmployeeDetail(state.selectedEmployee);
    }
}

// Copy to Clipboard
async function copyToClipboard(text, label) {
    const value = String(text || '');
    if (!value) return false;
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(value);
            showToast(`คัดลอก${label}แล้ว`);
            return true;
        }
    } catch (_) {}
    try {
        const textarea = document.createElement('textarea');
        textarea.value = value;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        const copied = document.execCommand('copy');
        document.body.removeChild(textarea);
        if (copied) {
            showToast(`คัดลอก${label}แล้ว`);
            return true;
        }
    } catch (_) {}
    return false;
}

function escapeHtmlText(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function findEmployeeById(id) {
    const key = String(id || '').trim();
    if (!key) return null;
    return state.employees.find(e => String(e.id) === key || String(e.employeeId || '') === key) || null;
}

function buildEmployeeShareUrl(employee) {
    const empKey = String(employee?.id || employee?.employeeId || '').trim();
    const u = new URL(window.location.href);
    u.searchParams.set('emp', empKey);
    u.searchParams.delete('mode');
    return u.toString();
}

function shouldCallDirectlyOnThisDevice() {
    const ua = String(navigator.userAgent || '');
    return /Android|iPhone|iPad|iPod|Mobile/i.test(ua) || (navigator.maxTouchPoints > 1 && window.innerWidth <= 1024);
}

async function handlePhoneAction(phone) {
    const value = normalizePhoneValue(phone || '');
    if (!value) {
        showToast('ไม่พบเบอร์โทรศัพท์');
        return;
    }
    const telValue = value.replace(/[^\d+]/g, '');
    if (shouldCallDirectlyOnThisDevice()) {
        window.location.href = `tel:${telValue || value}`;
        return;
    }
    await copyToClipboard(value, 'เบอร์โทรศัพท์');
}

// Toast
function showToast(message) {
    elements.toastMessage.textContent = message;
    elements.toast.classList.add('show');
    setTimeout(() => {
        elements.toast.classList.remove('show');
    }, 2500);
}

// Export filtered employees to CSV (Excel-friendly)
function exportDirectoryExcel() {
    const rows = state.filteredEmployees.length ? state.filteredEmployees : state.employees;
    if (!rows.length) {
        if (window.Swal) {
            Swal.fire({ icon: 'info', title: 'ไม่พบข้อมูล', text: 'ไม่มีรายชื่อพนักงานที่ตรงกับเงื่อนไขปัจจุบัน' });
        } else {
            showToast('ไม่มีข้อมูลสำหรับ Export');
        }
        return;
    }

    // ให้คอลัมน์และฟิลด์ใกล้เคียงของเดิม (js/directory.js)
    const header = ['ID', 'Name (EN)', 'Name (TH)', 'Position', 'Company', 'Department', 'Email', 'Phone', 'Status'];
    const lines = [];

    // BOM สำหรับภาษาไทย
    lines.push(header.join(','));

    rows.forEach(emp => {
        const nameEn = getPreferredDisplayName(emp);
        const nameTh = emp.nameTh || emp.name_th || (emp.nameEn && emp.name !== emp.nameEn ? emp.name : '');
        const phoneValue = normalizePhoneValue(emp.phone || emp.mobile || '');

        const esc = (v) => {
            const s = (v == null ? '' : String(v)).replace(/"/g, '""').replace(/\r?\n/g, ' ');
            return `"${s}"`;
        };

        const row = [
            esc(emp.employeeId || emp.id || ''),
            esc(nameEn),
            esc(nameTh),
            esc(emp.position || ''),
            esc(emp.company || ''),
            esc(emp.department || ''),
            esc(emp.email || ''),
            esc(phoneValue ? `\t${phoneValue}` : ''),
            esc(emp.employment_status || emp.status || 'active')
        ];
        lines.push(row.join(','));
    });

    const csvContent = '\uFEFF' + lines.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;

    // ระบุ company/department ปัจจุบันในชื่อไฟล์คล้ายของเดิม
    let suffix = 'All';
    const selectedCompanyCode = getCompanyFilterCodeFromCurrentFilter();
    if (selectedCompanyCode) suffix = selectedCompanyCode;
    else if (state.currentFilter.startsWith('dept-')) suffix = state.currentFilter.replace('dept-', '');

    a.download = `Employee_Directory_${suffix}_${new Date().toISOString().slice(0,10)}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);

    showToast('Export Excel เสร็จแล้ว');
}

// Export filtered employees to PDF (ตามแนวคิดของเดิม)
function exportDirectoryPdf() {
    const rows = state.filteredEmployees.length ? state.filteredEmployees : state.employees;
    if (!rows.length) {
        if (window.Swal) {
            Swal.fire({ icon: 'info', title: 'ไม่พบข้อมูล', text: 'ไม่มีรายชื่อพนักงานที่ตรงกับเงื่อนไขปัจจุบัน' });
        } else {
            showToast('ไม่มีข้อมูลสำหรับ Export');
        }
        return;
    }

    if (window.Swal) {
        Swal.fire({
            title: 'กำลังสร้าง PDF...',
            text: 'กรุณารอสักครู่ ข้อมูลจำนวนมากอาจใช้เวลา',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
    }

    const win = window.open('', '_blank');
    if (!win) {
        if (window.Swal) Swal.close();
        showToast('ไม่สามารถเปิดหน้าต่างใหม่ได้ (อาจถูกบล็อก Popup)');
        return;
    }

    let companyLabel = 'All Companies';
    const selectedCompanyCode = getCompanyFilterCodeFromCurrentFilter();
    if (selectedCompanyCode) companyLabel = selectedCompanyCode;
    const deptFilter = state.currentFilter.startsWith('dept-') ? state.currentFilter.replace('dept-', '') : '';

    const title = `Employee Directory - ${companyLabel}${deptFilter ? ' / ' + deptFilter : ''}`;
    const dateStr = new Date().toLocaleDateString('th-TH', { dateStyle: 'long' });

    const tableRows = rows.map(emp => {
        const nameEn = getPreferredDisplayName(emp);
        const nameTh = emp.nameTh || emp.name_th || (emp.nameEn && emp.name !== emp.nameEn ? emp.name : '');
        const phoneValue = normalizePhoneValue(emp.phone || emp.mobile || '');
        return `
        <tr>
            <td>${emp.employeeId || emp.id || ''}</td>
            <td>${nameEn}</td>
            <td>${nameTh}</td>
            <td>${emp.position || ''}</td>
            <td>${emp.company || ''}</td>
            <td>${emp.department || ''}</td>
            <td>${emp.email || ''}</td>
            <td>${phoneValue}</td>
        </tr>`;
    }).join('');

    win.document.write(`
        <!DOCTYPE html>
        <html lang="th">
        <head>
            <meta charset="utf-8">
            <title>${title}</title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;700&display=swap');
                body { font-family: 'Kanit', sans-serif; margin: 20px; color: #111827; }
                .header { text-align: center; margin-bottom: 16px; }
                .header h2 { margin: 0; font-size: 18px; font-weight: 700; }
                .header p { margin: 4px 0 0; font-size: 11px; color: #6b7280; }
                table { width: 100%; border-collapse: collapse; font-size: 10px; }
                th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; }
                th { background-color: #f3f4f6; }
                tr { page-break-inside: avoid; page-break-after: auto; }
                thead { display: table-header-group; }
                tfoot { display: table-footer-group; }
                @media print {
                    body { margin: 10mm; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>${title}</h2>
                <p>Export Date: ${dateStr} • Total: ${rows.length} records</p>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width:7%;">ID</th>
                        <th style="width:18%;">Name (EN)</th>
                        <th style="width:18%;">Name (TH)</th>
                        <th style="width:18%;">Position</th>
                        <th style="width:10%;">Company</th>
                        <th style="width:12%;">Department</th>
                        <th style="width:12%;">Email</th>
                        <th style="width:10%;">Phone</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRows}
                </tbody>
            </table>
        </body>
        </html>
    `);
    win.document.close();
    if (window.Swal) Swal.close();
    win.focus();
    win.print();
}

// Copy directory link for HR to share
async function copyDirectoryLink() {
    const url = 'https://powertecha.myqnapcloud.com:8081/PowertechCenter/directory.html?public=1';
    const copied = await copyToClipboard(url, 'ลิงก์สมุดรายชื่อ');
    if (!copied) {
        if (window.Swal) {
            Swal.fire({
                icon: 'info',
                title: 'คัดลอกอัตโนมัติไม่สำเร็จ',
                html: `<div style="text-align:left;font-size:13px;color:#475569;">คัดลอกลิงก์ด้านล่างด้วยตนเอง</div><input value="${url}" readonly style="margin-top:8px;width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;">`,
                confirmButtonText: 'ตกลง'
            });
        } else {
            prompt('คัดลอกลิงก์สำหรับ HR:', url);
        }
        return;
    }
    if (window.Swal) {
        Swal.fire({
            icon: 'success',
            title: 'คัดลอกลิงก์แล้ว',
            text: url,
            timer: 1800,
            showConfirmButton: false
        });
    }
}

function setupInstallAppButton() {
    const installBtn = elements.installAppBtn;
    if (!installBtn) return;
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const hideBtn = () => { installBtn.style.display = 'none'; };
    const showBtn = () => {
        if (isStandalone) {
            hideBtn();
            return;
        }
        installBtn.style.display = '';
    };
    hideBtn();
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
        showBtn();
    });
    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        hideBtn();
    });
    installBtn.addEventListener('click', async () => {
        if (deferredInstallPrompt) {
            deferredInstallPrompt.prompt();
            const choice = await deferredInstallPrompt.userChoice;
            if (choice.outcome === 'accepted') {
                deferredInstallPrompt = null;
                hideBtn();
            }
            return;
        }
        if (window.Swal) {
            Swal.fire({
                icon: 'info',
                title: 'ติดตั้งแอป Contact List',
                html: 'มือถือ: กดเมนูเบราว์เซอร์แล้วเลือก Add to Home Screen<br>Desktop Chrome: กดเมนูมุมขวาบนแล้วเลือก Install app',
                confirmButtonText: 'เข้าใจแล้ว'
            });
            return;
        }
        alert('ติดตั้งแอป Contact List: มือถือเลือก Add to Home Screen หรือ Desktop Chrome เลือก Install app');
    });
}

async function forceRefreshDirectory() {
    const go = async () => {
        try {
            if ('serviceWorker' in navigator) {
                const regs = await navigator.serviceWorker.getRegistrations();
                await Promise.all(regs.map(r => r.unregister()));
            }
        } catch (_) {}
        try {
            if (window.caches && typeof window.caches.keys === 'function') {
                const keys = await window.caches.keys();
                await Promise.all(keys.map(k => window.caches.delete(k)));
            }
        } catch (_) {}
        const params = new URLSearchParams(window.location.search);
        if (isPublicMode) params.set('public', '1');
        params.set('v', String(Date.now()));
        const next = 'directory.html?' + params.toString();
        window.location.href = next;
    };
    if (window.Swal) {
        const rs = await Swal.fire({
            icon: 'question',
            title: 'รีโหลดเวอร์ชันล่าสุด',
            text: 'ระบบจะล้างแคชและโหลดหน้าใหม่',
            showCancelButton: true,
            confirmButtonText: 'รีโหลดเลย',
            cancelButtonText: 'ยกเลิก'
        });
        if (rs.isConfirmed) await go();
        return;
    }
    await go();
}

// Utilities
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return '-';
    return date.toLocaleDateString('th-TH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function calculateYearsWorked(startDate) {
    if (!startDate) return '-';
    const start = new Date(startDate);
    if (isNaN(start.getTime())) return '-';
    const now = new Date();
    return Math.floor((now - start) / (365.25 * 24 * 60 * 60 * 1000));
}

// Event Listeners
function setupEventListeners() {
    setupInstallAppButton();
    // Theme toggle
    elements.themeToggle.addEventListener('click', toggleTheme);
    elements.themeToggleMobile.addEventListener('click', toggleTheme);

    // Menu toggle (mobile)
    elements.menuToggle.addEventListener('click', () => {
        elements.sidebar.classList.add('open');
        elements.sidebarOverlay.classList.add('show');
    });

    elements.sidebarOverlay.addEventListener('click', () => {
        elements.sidebar.classList.remove('open');
        elements.sidebarOverlay.classList.remove('show');
    });

    // Search
    elements.searchInput.addEventListener('input', (e) => {
        state.searchQuery = e.target.value;
        filterEmployees();
    });

    // Keyboard shortcut
    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            elements.searchInput.focus();
        }
        if (e.key === 'Escape') {
            elements.searchInput.blur();
            elements.sidebar.classList.remove('open');
            elements.sidebarOverlay.classList.remove('show');
            closeDetail();
        }
    });

    // Nav items
    document.querySelectorAll('.nav-item[data-filter]').forEach(item => {
        item.addEventListener('click', () => {
            document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            state.currentFilter = item.dataset.filter;
            filterEmployees();

            // Close sidebar on mobile
            if (window.innerWidth <= 768) {
                elements.sidebar.classList.remove('open');
                elements.sidebarOverlay.classList.remove('show');
            }
        });
    });

    // Department nav items (dynamic)
    elements.departmentList.addEventListener('click', (e) => {
        const navItem = e.target.closest('.nav-item');
        if (navItem) {
            document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
            navItem.classList.add('active');
            state.currentFilter = navItem.dataset.filter;
            filterEmployees();

            if (window.innerWidth <= 768) {
                elements.sidebar.classList.remove('open');
                elements.sidebarOverlay.classList.remove('show');
            }
        }
    });

    // View toggle
    document.querySelectorAll('.view-btn[data-view]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.view-btn[data-view]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            state.viewMode = btn.dataset.view;
            renderEmployeeList();
        });
    });
}

// Make functions available globally
window.selectEmployee = selectEmployee;
window.toggleFavorite = toggleFavorite;
window.copyToClipboard = copyToClipboard;
window.closeDetail = closeDetail;
window.openAdminEdit = openAdminEdit;
window.openAdminAddUser = openAdminAddUser;
window.exportDirectoryExcel = exportDirectoryExcel;
window.exportDirectoryPdf = exportDirectoryPdf;
window.copyDirectoryLink = copyDirectoryLink;
window.forceRefreshDirectory = forceRefreshDirectory;
window.openCategorySettings = openCategorySettings;

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', init);
