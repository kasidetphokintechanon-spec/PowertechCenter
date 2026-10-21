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

// ชื่อในฐานข้อมูลพิมพ์ไม่เหมือนกัน (Mr.Anan / Ms. Ernie / Ms.Kanyanat.Patkulkan)
// → จัดรูปแบบตอนแสดงผลเท่านั้น ข้อมูลจริงไม่เปลี่ยน (ฟอร์มแก้ไขดึงจาก API ใหม่)
function tidyPersonName(name) {
    let n = String(name || '').trim().replace(/\s+/g, ' ');
    const m = n.match(/^(Mrs|Miss|Ms|Mr|Dr)\.?\s*(.*)$/i);
    if (!m || !m[2]) return n;
    let rest = m[2];
    if (!rest.includes(' ') && rest.includes('.')) rest = rest.replace(/\./g, ' ');
    return m[1].charAt(0).toUpperCase() + m[1].slice(1).toLowerCase() + (/^miss$/i.test(m[1]) ? ' ' : '. ') + rest.trim();
}

function mapEmployeeFromApi(row) {
    const assignments = Array.isArray(row.assignments) ? row.assignments : [];
    const primary = assignments.find(a => a.is_primary == 1) || assignments[0] || {};
    const company = (primary.company || '').toUpperCase();
    let department = primary.department || '';
    if (isCancelledManagementDepartmentName(department)) department = '';
    const email = primary.email || '';
    const nameTh = row.name_th || '';
    const nameEn = tidyPersonName(row.name || '');
    const fullName = nameEn || nameTh || '-';
    const position = row.position || '';
    const isExecutiveByFlag = String(row.is_executive ?? '0') === '1';
    const isManagementByFlag = String(row.is_management ?? '0') === '1';
    const isTechnicianByFlag = String(row.is_technician ?? '0') === '1';
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
        is_technician: isTechnicianByFlag ? 1 : 0,
        username: row.username || '',
        role: row.role || 'user',
        responsible_department: row.responsible_department || '',
        can_manage_stock: row.can_manage_stock ?? 0,
        start_date: row.start_date || '',
        birthdate: row.birthdate || '',
        avatar: row.image ? `uploads/employees/${row.image}` : null,
        assignments: assignments
    };
}

// ── Directory Cache (sessionStorage, TTL-based) ──────────────────────────────
const DIR_CACHE = {
    _key: k => 'dir_' + k,
    get(key, ttlMs = 5 * 60 * 1000) {
        try {
            const raw = sessionStorage.getItem(this._key(key));
            if (!raw) return null;
            const { ts, data } = JSON.parse(raw);
            if (Date.now() - ts > ttlMs) { sessionStorage.removeItem(this._key(key)); return null; }
            return data;
        } catch { return null; }
    },
    set(key, data) {
        try { sessionStorage.setItem(this._key(key), JSON.stringify({ ts: Date.now(), data })); } catch {}
    },
    del(key) { try { sessionStorage.removeItem(this._key(key)); } catch {} },
    delEmployees() { this.del('emp_a'); this.del('emp_p'); }
};

// State
let state = {
    employees: [],
    filteredEmployees: [],
    selectedEmployee: null,
    favorites: JSON.parse(localStorage.getItem('favorites') || '[]'),
    currentFilter: 'all',   // category: all | favorites | executives | management | inactive
    companyFilter: '',       // '' = ทั้งหมด | 'PTA' | 'PT4' | 'PTE'
    deptFilter: '',          // '' = ทั้งหมด | ชื่อแผนก
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
let jobPositionLevelMap = {}; // ชื่อตำแหน่ง (lowercase) → ระดับในผังองค์กร 1-5
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

let directorySession = {
    loggedin: false,
    role: 'guest',
    username: '',
    display_name: '',
    can_access_admin: false,
    effective_permissions: []
};
const isPublicMode = new URLSearchParams(window.location.search).get('public') === '1';
const isLocalDevHost = ['localhost', '127.0.0.1'].includes(window.location.hostname);
let deferredInstallPrompt = null;
let autoRefreshTimer = null;
let lastEmployeesFingerprint = '';
let lastUpdatedAt = null;
const DIRECTORY_AUTO_REFRESH_MS = 120000;
let employeeEditorState = null;
let employeeEditorModalBound = false;

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
    return days >= 0 && days <= 7;
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
    listCountChip: document.getElementById('listCountChip'),
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
    incomingNavItem: document.getElementById('incomingNavItem'),
    incomingSection: document.getElementById('incomingSection'),
    incomingBadge: document.getElementById('incomingBadge'),
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

// ── Pre-onboard helpers ──────────────────────────────────────────
function isPreOnboard(employee) {
    const sd = employee.start_date || employee.startDate || '';
    if (!sd) return false;
    const startMs = new Date(sd).setHours(0, 0, 0, 0);
    if (!Number.isFinite(startMs)) return false;
    return startMs > new Date().setHours(0, 0, 0, 0);
}

function daysUntilStart(employee) {
    const sd = employee.start_date || employee.startDate || '';
    if (!sd) return null;
    const startMs = new Date(sd).setHours(0, 0, 0, 0);
    if (!Number.isFinite(startMs)) return null;
    return Math.ceil((startMs - new Date().setHours(0, 0, 0, 0)) / 86400000);
}

// IT/Admin can see pre-onboard employees (uses existing permission check)
function canSeePreOnboard() {
    return canManageDirectory();
}

function updateIncomingNavVisibility() {
    const section = elements.incomingSection || document.getElementById('incomingSection');
    const navItem = elements.incomingNavItem || document.getElementById('incomingNavItem');
    const badge   = elements.incomingBadge  || document.getElementById('incomingBadge');
    if (!section || !navItem) return;

    const show = canSeePreOnboard();
    section.style.display = show ? '' : 'none';

    if (show) {
        const count = state.employees.filter(e => isPreOnboard(e) && isEmployeeActive(e) && (daysUntilStart(e) ?? 99) <= 7).length;
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? '' : 'none';
        }
        // Highlight active state
        navItem.classList.toggle('active', state.currentFilter === 'incoming');
    }
    if (!show && state.currentFilter === 'incoming') state.currentFilter = 'all';
}

function updateInactiveNavVisibility() {
    if (!elements.inactiveNavItem) return;
    const canManage = canManageDirectory();
    elements.inactiveNavItem.style.display = canManage ? '' : 'none';
    if (!canManage && state.currentFilter === 'inactive') {
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
    if (!directorySession.loggedin) return false;
    if (directorySession.role === 'admin') return true;
    const effective = Array.isArray(directorySession.effective_permissions) ? directorySession.effective_permissions : [];
    return effective.includes('*') || effective.includes('employee.manage');
}

function canManageUserPermissions() {
    if (!directorySession.loggedin) return false;
    if (directorySession.role === 'admin') return true;
    const effective = Array.isArray(directorySession.effective_permissions) ? directorySession.effective_permissions : [];
    return effective.includes('*') || effective.includes('admin.manage_permissions');
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
    const cached = DIR_CACHE.get('cat_cfg');
    if (cached) { state.categoryNavConfig = normalizeCategoryNavConfig(cached); return; }
    try {
        const res = await fetch('api.php?action=get_directory_categories&v=' + Date.now(), { cache: 'no-store' });
        const json = await res.json();
        if (json && json.status === 'success' && Array.isArray(json.items)) {
            DIR_CACHE.set('cat_cfg', json.items);
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
    DIR_CACHE.del('cat_cfg');
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
    if (state.companyFilter) return normalizeCompanyCode(state.companyFilter);
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
    updateIncomingNavVisibility();
    if (elements.categorySettingsBtn) elements.categorySettingsBtn.style.display = canManageDirectory() ? '' : 'none';

    // Stale-while-revalidate: show cached employees immediately while fresh data loads
    const empCacheKey = canManageDirectory() ? 'emp_a' : 'emp_p';
    const cachedEmps = DIR_CACHE.get(empCacheKey, 10 * 60 * 1000);
    if (cachedEmps && cachedEmps.length > 0) {
        state.employees = cachedEmps;
        filterEmployees(); // instant render from cache (applies active filter)
    }

    // Load all config + employees in parallel
    await Promise.all([
        loadCategoryNavConfig(),
        loadJobPositions(),
        loadCompanyOrder(),
        loadDepartments(),
        loadEmployeesFromAPI()
    ]);

    renderCompanyNav();
    applyTheme();
    updateStats();
    renderDepartments();
    applyCategoryNavConfig();
    filterEmployees();
    await selectEmployeeFromQuery();
    setupEventListeners();
    initEmployeeEditorModal();
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
    const cached = DIR_CACHE.get('co_order');
    if (cached) { state.companyOrder = cached; return; }
    try {
        const res = await fetch('api.php?action=get_company_order&v=' + Date.now(), { cache: 'no-store' });
        const json = await res.json();
        if (json && json.status === 'success' && Array.isArray(json.order)) {
            const normalized = json.order.map(normalizeCompanyCode).filter(Boolean);
            if (normalized.length) {
                state.companyOrder = [...new Set(normalized)];
                DIR_CACHE.set('co_order', state.companyOrder);
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
        <button class="nav-item ${state.companyFilter === code || state.currentFilter === `company-${code}` ? 'active' : ''}" data-filter="company-${code}">
            <span class="company-dot ${getCompanyDotClass(code)}"></span>
            <span>${code}</span>
            <span class="nav-badge" data-company-badge="${code}">0</span>
        </button>
    `).join('');

    // ปุ่มกรองบริษัทแถวบน (ใช้ filter ชุดเดียวกับเมนูซ้าย — "company-" ว่าง = ทุกบริษัท)
    const chips = document.getElementById('companyChips');
    if (chips) {
        chips.innerHTML = `<button type="button" class="dir-chip ${!state.companyFilter ? 'active' : ''}" data-filter="company-">ทั้งหมด <span class="dir-chip-n" data-company-badge="__all">0</span></button>`
            + companyCodes.map((code) => `
            <button type="button" class="dir-chip ${state.companyFilter === code ? 'active' : ''}" data-filter="company-${code}">
                <span class="company-dot ${getCompanyDotClass(code)}"></span>${code}
                <span class="dir-chip-n" data-company-badge="${code}">0</span>
            </button>`).join('');
    }
}

async function loadJobPositions() {
    const cached = DIR_CACHE.get('job_pos_v2');
    if (cached) {
        jobPositionOptions = cached.options;
        jobPositionSortMap = cached.sortMap;
        jobPositionLevelMap = cached.levelMap || {};
        return;
    }
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
            jobPositionLevelMap = json.positions.reduce((acc, p) => {
                const key = String(p.name || '').trim().toLowerCase();
                if (key) acc[key] = parseInt(p.org_level, 10) || 5;
                return acc;
            }, {});
            DIR_CACHE.set('job_pos_v2', { options: jobPositionOptions, sortMap: jobPositionSortMap, levelMap: jobPositionLevelMap });
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
        if (canManageDirectory()) {
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
        DIR_CACHE.set(canManageDirectory() ? 'emp_a' : 'emp_p', mapped);
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
    const prevCompanyCode = state.companyFilter || getCompanyFilterCodeFromCurrentFilter();
    await Promise.all([loadCompanyOrder(), loadJobPositions(), loadDepartments(), loadEmployeesFromAPI()]);
    renderCompanyNav();
    if (prevCompanyCode) {
        const hasCompanyFilter = !!document.querySelector(`.nav-item[data-filter="company-${prevCompanyCode}"]`);
        if (!hasCompanyFilter) { state.companyFilter = ''; state.currentFilter = 'all'; }
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
        directorySession = { loggedin: false, role: 'guest', username: '', display_name: '', can_access_admin: false, effective_permissions: [] };
        document.querySelectorAll('.dir-home-link--internal').forEach(el => el.style.display = 'none');
        return;
    }
    try {
        const res = await fetch('api.php?action=check_session');
        const sess = await res.json();
        directorySession = {
            loggedin: !!sess.loggedin,
            role: sess.role || 'guest',
            username: sess.username || '',
            display_name: sess.display_name || '',
            can_access_admin: !!sess.can_access_admin,
            effective_permissions: Array.isArray(sess.effective_permissions) ? sess.effective_permissions : []
        };
    } catch {
        directorySession = { loggedin: false, role: 'guest', username: '', display_name: '', can_access_admin: false, effective_permissions: [] };
    }
}

async function loadDepartments() {
    const cached = DIR_CACHE.get('depts');
    if (cached) { state.departments = cached; return; }
    try {
        const res = await fetch('api.php?action=get&file=departments&v=' + Date.now());
        let data = await res.json();
        if (!Array.isArray(data)) data = [];
        state.departments = data.filter(d => !isCancelledManagementDepartmentName(d && (d.dept_name || d.name)));
        DIR_CACHE.set('depts', state.departments);
    } catch {
        state.departments = [];
    }
}

// Theme
function applyTheme() {
    document.body.classList.toggle('dark', state.isDarkMode);
    // ธีมรวม (directory-unified-theme.css / design-system.css) ใช้ html.dark — ต้องสลับคู่กัน ไม่งั้นมืดแค่ครึ่งหน้า
    document.documentElement.classList.toggle('dark', state.isDarkMode);
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
    if (elements.listCountChip) elements.listCountChip.textContent = filtered;
    elements.favoriteCount.textContent = favCount;
    elements.allBadge.textContent = total;
    if (elements.inactiveBadge) elements.inactiveBadge.textContent = inactiveEmployees.length;
    elements.favBadge.textContent = favCount;
    elements.execBadge.textContent = execCount;
    updateManagementNavVisibility(false);
    companyCodes.forEach((code) => {
        const n = activeEmployees.filter(e => employeeHasCompanyAssignment(e, code)).length;
        document.querySelectorAll(`[data-company-badge="${code}"]`).forEach(badge => { badge.textContent = n; });
    });
    document.querySelectorAll('[data-company-badge="__all"]').forEach(badge => { badge.textContent = total; });
}

// Departments
function renderDepartments() {
    const activeEmployees = state.employees.filter(isEmployeeActive);
    const departments = [...new Set(activeEmployees.map(e => e.department).filter(Boolean))]
        .filter(d => !isCancelledManagementDepartmentName(d))
        .sort((a, b) => compareDepartmentNames(a, b));
    updateManagementNavVisibility(false);

    if (!elements.departmentList) return;
    elements.departmentList.innerHTML = departments.map(dept => {
        const color = DEPT_COLOR_MAP[dept] || '#6366f1';
        return `<button class="nav-item" data-filter="dept-${escapeHtmlText(dept)}">
            <span class="dept-nav-dot" style="background:${color}"></span>
            <span>${escapeHtmlText(dept)}</span>
            <span class="nav-badge">${activeEmployees.filter(e => e.department === dept).length}</span>
        </button>`;
    }).join('');
    applyCategoryNavConfig();
}

// Filter
function filterEmployees() {
    updateManagementNavVisibility(true);
    const showInactive = state.currentFilter === 'inactive';
    const showIncoming = state.currentFilter === 'incoming';

    let filtered;
    if (showIncoming) {
        // IT-only: show employees whose start_date is within next 7 days
        filtered = state.employees.filter(e => isPreOnboard(e) && isEmployeeActive(e) && daysUntilStart(e) <= 7);
    } else if (showInactive) {
        filtered = state.employees.filter(e => !isEmployeeActive(e));
    } else {
        filtered = state.employees.filter(isEmployeeActive);
        // Hide pre-onboard from regular users; IT sees within 7 days
        if (!canSeePreOnboard()) {
            filtered = filtered.filter(e => !isPreOnboard(e));
        } else {
            filtered = filtered.filter(e => !isPreOnboard(e) || daysUntilStart(e) <= 7);
        }
    }

    // 1. Search (ค้นหาจากทุก field)
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

    // 2. Company filter (อิสระ — ทำงานร่วมกับ dept และ category ได้)
    if (state.companyFilter) {
        filtered = filtered.filter(e => employeeHasCompanyAssignment(e, state.companyFilter));
    }

    // 3. Department filter (อิสระ — ทำงานร่วมกับ company และ category ได้)
    if (state.deptFilter) {
        filtered = filtered.filter(e => e.department === state.deptFilter);
    }

    // 4. Category filter
    switch (state.currentFilter) {
        case 'management':
            filtered = filtered.filter(e => !!e.isManagement);
            filtered.sort(compareEmployeesByRankThenName);
            break;
        case 'favorites':
            filtered = filtered.filter(e => state.favorites.includes(e.id));
            filtered.sort(compareEmployeesByRankThenName);
            break;
        case 'executives':
            filtered = filtered.filter(e => e.isExecutive);
            filtered.sort(compareEmployeesByRankThenName);
            break;
        case 'inactive':
            filtered.sort(compareEmployeesByRankThenName);
            break;
        case 'incoming':
            filtered.sort((a, b) => (daysUntilStart(a) || 99) - (daysUntilStart(b) || 99));
            break;
        default:
            // Legacy: ถ้า currentFilter ยังเป็น company-/dept- จากเวอร์ชันเก่า ให้ migrate
            if (state.currentFilter.startsWith('company-')) {
                state.companyFilter = state.currentFilter.replace('company-', '');
                state.currentFilter = 'all';
                filtered = filtered.filter(e => employeeHasCompanyAssignment(e, state.companyFilter));
            } else if (state.currentFilter.startsWith('dept-')) {
                state.deptFilter = state.currentFilter.replace('dept-', '');
                state.currentFilter = 'all';
                filtered = filtered.filter(e => e.department === state.deptFilter);
            }
            filtered.sort(state.deptFilter ? compareDepartmentMembers : compareEmployeesByRankThenName);
    }

    // 5a. Pin new employees (start_date ≤ 7 days ago) to the top
    //     Only for general views — not for special filters that have their own order
    const pinFilters = ['all', 'inactive', 'management', 'executives', 'favorites'];
    const skipPin = ['incoming', 'favorites', 'management', 'executives', 'inactive'];
    if (!skipPin.includes(state.currentFilter)) {
        const newOnes  = filtered.filter(e => isRecentlyAdded(e));
        const theRest  = filtered.filter(e => !isRecentlyAdded(e));
        if (newOnes.length > 0) {
            newOnes.sort((a, b) => {
                const da = new Date(a.start_date || a.startDate || a.createdAt || 0).getTime();
                const db = new Date(b.start_date || b.startDate || b.createdAt || 0).getTime();
                return db - da; // newest start first
            });
            filtered = [...newOnes, ...theRest];
        }
    }

    // 5. Build title dynamically from active filters
    const titleParts = [];
    if (state.currentFilter === 'management') titleParts.push(getCategoryLabelByKey('management') || 'Management');
    else if (state.currentFilter === 'favorites') titleParts.push(getCategoryLabelByKey('favorites') || 'ปักหมุด');
    else if (state.currentFilter === 'executives') titleParts.push(getCategoryLabelByKey('executives') || 'ผู้บริหาร');
    else if (state.currentFilter === 'inactive') titleParts.push(getCategoryLabelByKey('inactive') || 'Inactive');
    else if (state.currentFilter === 'incoming') titleParts.push('🔔 กำลังจะมา (7 วัน)');
    if (state.companyFilter) titleParts.push(state.companyFilter);
    if (state.deptFilter) titleParts.push('แผนก ' + state.deptFilter);
    elements.listTitle.textContent = titleParts.length > 0 ? titleParts.join(' · ') : (getCategoryLabelByKey('all') || 'พนักงานทั้งหมด');

    state.filteredEmployees = filtered;
    updateStats();
    updateNavActiveStates();
    updateIncomingNavVisibility();
    renderFilterPills();
    renderEmployeeList();
}

function updateNavActiveStates() {
    document.querySelectorAll('.nav-item[data-filter], .dir-chip[data-filter]').forEach(item => {
        const f = item.dataset.filter || '';
        let isActive = false;
        if (f.startsWith('company-')) {
            isActive = state.companyFilter === f.replace('company-', '');
        } else if (f.startsWith('dept-')) {
            isActive = state.deptFilter === f.replace('dept-', '');
        } else {
            isActive = f === state.currentFilter;
        }
        item.classList.toggle('active', isActive);
    });
}

// ── Filter Pills ──────────────────────────────────────────────────────────────
function renderFilterPills() {
    const bar = document.getElementById('activeFilterPills');
    if (!bar) return;
    const pills = [];

    if (state.searchQuery) {
        pills.push(`<span class="filter-pill">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            ค้นหา: <b>${escapeHtmlText(state.searchQuery)}</b>
            <button onclick="window.dirClearSearch()" aria-label="ล้างการค้นหา">×</button>
        </span>`);
    }

    if (state.companyFilter) {
        pills.push(`<span class="filter-pill filter-pill-company">
            🏢 ${escapeHtmlText(state.companyFilter)}
            <button onclick="window.dirClearCompany()" aria-label="ล้าง company">×</button>
        </span>`);
    }

    if (state.deptFilter) {
        pills.push(`<span class="filter-pill filter-pill-dept">
            📁 แผนก ${escapeHtmlText(state.deptFilter)}
            <button onclick="window.dirClearDept()" aria-label="ล้าง dept">×</button>
        </span>`);
    }

    if (state.currentFilter && state.currentFilter !== 'all') {
        const catLabels = { favorites:'⭐ ปักหมุด', executives:'🏆 ผู้บริหาร', management:'👔 Management', inactive:'Inactive' };
        const label = catLabels[state.currentFilter] || state.currentFilter;
        pills.push(`<span class="filter-pill filter-pill-category">
            ${escapeHtmlText(label)}
            <button onclick="window.dirSetCategory('all')" aria-label="ล้าง category">×</button>
        </span>`);
    }

    if (pills.length > 1) {
        pills.push(`<button class="filter-pill-clear-all" onclick="window.dirClearAll()">ล้างทั้งหมด</button>`);
    }

    bar.innerHTML = pills.join('');
    bar.style.display = pills.length > 0 ? 'flex' : 'none';
}

window.dirClearSearch = function() {
    const inp = document.getElementById('searchInput');
    if (inp) inp.value = '';
    state.searchQuery = '';
    filterEmployees();
};
window.dirClearCompany = function() { state.companyFilter = ''; filterEmployees(); };
window.dirClearDept    = function() { state.deptFilter = '';    filterEmployees(); };
window.dirSetCategory  = function(f) { state.currentFilter = f; filterEmployees(); };
window.dirClearAll     = function() {
    const inp = document.getElementById('searchInput');
    if (inp) inp.value = '';
    state.searchQuery = '';
    state.companyFilter = '';
    state.deptFilter = '';
    state.currentFilter = 'all';
    filterEmployees();
};
// Backward-compat alias
window.dirSetFilter = window.dirSetCategory;

// ── Department colour map (sidebar dots + org chart columns) ──────────────────
const DEPT_COLOR_MAP = {
    'Accounting':'#ec4899','HR and GA':'#ec4899',
    'Information Technology':'#ec4899',
    'Safety & Environment':'#ec4899',
    'Operation Plant':'#ec4899',
    'Project Management':'#a855f7',
    'Production':'#10b981','Production Engineer':'#10b981',
    'Maintenance':'#3b82f6',
    'Logistics':'#ef4444',
    'Quality Management':'#f59e0b'
};

// ── Org Chart ─────────────────────────────────────────────────────────────────
// ระดับในผังองค์กร (HR ตั้งได้ที่ admin.html?tab=job_positions) — ต้องตรงกับ includes/job_position_levels.php
const ORG_LEVELS = [
    { k: 1, name: 'ผู้บริหาร',        color: '#d97706' },
    { k: 2, name: 'ผู้จัดการ',         color: '#7c3aed' },
    { k: 3, name: 'หัวหน้างาน',        color: '#2563eb' },
    { k: 4, name: 'วิศวกร / ผู้ชำนาญ',  color: '#0891b2' },
    { k: 5, name: 'พนักงาน',           color: '#64748b' },
];
function getOrgLevel(position) {
    const lv = jobPositionLevelMap[String(position || '').trim().toLowerCase()];
    return ORG_LEVELS.find(l => l.k === lv) || ORG_LEVELS[ORG_LEVELS.length - 1];
}

// ผังองค์กรแบบ "ตามระดับ": ผู้บริหารอยู่บนสุด / แต่ละแผนกแบ่งชั้น ผู้จัดการ → หัวหน้างาน → วิศวกร/ผู้ชำนาญ → พนักงาน
function renderOrgChart(employees) {
    const el = elements.employeeList;
    el.className = 'employee-list org-view org-v3';

    const active = (employees || []).filter(e => (e.employment_status || e.status || 'active') === 'active');
    if (active.length === 0) {
        el.innerHTML = `<div class="empty-state"><p>ไม่พบข้อมูล</p></div>`;
        return;
    }

    const esc = escapeHtmlText;
    const execs = active.filter(e => getOrgLevel(e.position).k === 1).sort(compareEmployeesByRankThenName);
    const execIds = new Set(execs.map(e => String(e.id)));

    const grouped = {};
    active.filter(e => !execIds.has(String(e.id))).forEach(e => {
        const dept = String(getPreferredAssignmentForCurrentFilter(e)?.department || e.department || '').trim() || 'ไม่ระบุแผนก';
        (grouped[dept] = grouped[dept] || []).push(e);
    });
    const orderMap = getDepartmentSortOrderMap();
    const depts = Object.keys(grouped).sort((a, b) => compareDepartmentNames(a, b, orderMap));

    const person = (emp) => {
        const lv = getOrgLevel(emp.position);
        const co = esc(getDisplayCompanyForEmployee(emp) || emp.company || '');
        const initials = esc(getInitials(emp.name || ''));
        const id = esc(JSON.stringify(String(emp.id)));
        const av = emp.avatar
            ? `<img src="${esc(emp.avatar)}" class="org3-av" alt="" loading="lazy" onerror="this.outerHTML='<span class=&quot;org3-av&quot;>${initials}</span>'">`
            : `<span class="org3-av">${initials}</span>`;
        return `<div class="org3-p" style="--lvc:${lv.color}" data-org-tip="${esc(emp.id)}" onclick="selectEmployee(${id})">
            ${av}
            <div class="org3-tx"><div class="org3-nm">${esc(emp.name)}</div><div class="org3-ps">${esc(emp.position || '-')}</div></div>
            ${co ? `<span class="company-badge ${co}">${co}</span>` : ''}
        </div>`;
    };

    let html = '';
    if (execs.length) {
        html += `<div class="org3-exec">
            <div class="org3-exec-lbl">ผู้บริหารสูงสุด</div>
            <div class="org3-exec-row">${execs.map(person).join('')}</div>
            <div class="org3-exec-line"></div>
        </div>`;
    }
    html += `<div class="org3-depts">` + depts.map(dept => {
        const members = grouped[dept];
        const color = DEPT_COLOR_MAP[dept] || '#6366f1';
        const bands = ORG_LEVELS.filter(l => l.k !== 1).map(l => {
            const ps = members.filter(e => getOrgLevel(e.position).k === l.k).sort(compareEmployeesByRankThenName);
            if (!ps.length) return '';
            return `<div class="org3-lv" style="--lvc:${l.color}">
                <div class="org3-lv-lbl">${l.name} · ${ps.length}</div>
                <div class="org3-lv-people">${ps.map(person).join('')}</div>
            </div>`;
        }).join('');
        return `<section class="org3-dept">
            <div class="org3-dept-h" style="border-top-color:${color}"><span class="org-dept-dot" style="background:${color}"></span><span class="org3-dept-name">${esc(dept)}</span><span class="org3-dept-n">${members.length} คน</span></div>
            ${bands}
        </section>`;
    }).join('') + `</div>`;

    el.innerHTML = html;
}

// Render Employee List
function renderEmployeeList() {
    if (state.viewMode === 'org') {
        renderOrgChart(state.filteredEmployees.length > 0 ? state.filteredEmployees : state.employees.filter(e => (e.employment_status || e.status || 'active') === 'active'));
        return;
    }
    const el = elements.employeeList;
    const isGrid = state.viewMode === 'grid';
    // v3: การ์ดเรียงหลายคอลัมน์เต็มความกว้าง (CSS ใน css/directory-v3.css)
    el.className = `employee-list v3-grid ${isGrid ? 'grid-view' : ''}`;

    const hasExisting = el.children.length > 0;
    if (hasExisting) {
        el.style.transition = 'opacity 0.1s ease';
        el.style.opacity = '0';
    }

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

    // Group by department only when no specific dept is selected and not searching
    let html = '';
    const noSpecificFilter = state.currentFilter === 'all' && !state.deptFilter;
    if (noSpecificFilter && !state.searchQuery && !isGrid) {
        const newOnes  = state.filteredEmployees.filter(e => isRecentlyAdded(e));
        const theRest  = state.filteredEmployees.filter(e => !isRecentlyAdded(e));
        if (newOnes.length > 0) {
            html += `<div class="group-header" style="color:#c2410c;border-color:#fdba74;">✨ พนักงานใหม่</div>`;
            html += newOnes.map(e => renderEmployeeCard(e)).join('');
        }
        const grouped = groupByDepartment(theRest);
        for (const [dept, employees] of Object.entries(grouped)) {
            html += `<div class="group-header">${escapeHtmlText(dept)}</div>`;
            html += employees.map(e => renderEmployeeCard(e)).join('');
        }
    } else {
        html = state.filteredEmployees.map(e => renderEmployeeCard(e)).join('');
    }

    const doRender = () => {
        el.innerHTML = html;
        el.style.transition = 'opacity 0.14s ease';
        el.style.opacity = '1';
    };
    if (hasExisting) {
        setTimeout(doRender, 80);
    } else {
        doRender();
    }
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

function highlightText(text, query) {
    if (!query || !text) return text || '';
    const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return String(text).replace(new RegExp(`(${escaped})`, 'gi'), '<mark class="search-highlight">$1</mark>');
}

function renderEmployeeCard(employee) {
    // v3: การ์ดกระชับ — เห็นเบอร์/อีเมลทันทีพร้อมปุ่มโทร/คัดลอก ไม่ต้องเปิดรายละเอียด
    const isFavorite = state.favorites.includes(employee.id);
    const isSelected = state.selectedEmployee?.id === employee.id;
    const initials = escapeHtmlText(getInitials(employee.name || ''));
    const safeId = escapeHtmlText(employee.id);
    const isNewUser = isRecentlyAdded(employee);
    const preOnboard = isPreOnboard(employee);
    const daysLeft = preOnboard ? daysUntilStart(employee) : null;
    const displayCompany = escapeHtmlText(getDisplayCompanyForEmployee(employee) || employee.company || '');
    const contextAssign = getPreferredAssignmentForCurrentFilter(employee);
    const displayDepartment = String(contextAssign?.department || employee.department || '').trim();
    const q = state.searchQuery;
    const displayPhone = normalizePhoneValue(employee.phone || contextAssign?.phone || '');
    const displayEmail = String(contextAssign?.email || employee.email || '').trim();
    const isBDay = employee.birthday && (() => { const d = new Date(employee.birthday); const n = new Date(); return d.getDate() === n.getDate() && d.getMonth() === n.getMonth(); })();
    const jsPhone = escapeHtmlText(JSON.stringify(displayPhone)); // ใช้เป็นอาร์กิวเมนต์ JS ใน onclick ได้ปลอดภัย
    const jsEmail = escapeHtmlText(JSON.stringify(displayEmail));
    const isInactive = !isEmployeeActive(employee);
    const sub = [employee.position, displayDepartment].filter(Boolean).map(t => highlightText(escapeHtmlText(t), q)).join(' · ');

    const status = isInactive
        ? '<span class="inactive-badge">⊘ Inactive</span>'
        : (preOnboard && daysLeft !== null ? `<span class="incoming-badge" title="เริ่มงานอีก ${daysLeft} วัน">🔔 ${daysLeft}วัน</span>` : (isNewUser ? '<span class="new-badge">NEW</span>' : ''));

    const phonePill = displayPhone ? `
        <span class="v3-pill">
            <button type="button" class="v3-pill-main" onclick="event.stopPropagation(); handlePhoneAction(${jsPhone})" title="โทร / คัดลอกเบอร์">
                <i class="fa fa-phone"></i><span class="v3-pill-text">${highlightText(escapeHtmlText(displayPhone), q)}</span>
            </button>
            <button type="button" class="v3-copy" onclick="event.stopPropagation(); copyToClipboard(${jsPhone}, 'เบอร์โทร')" title="คัดลอกเบอร์"><i class="fa-regular fa-copy"></i></button>
        </span>` : '';
    const emailPill = displayEmail ? `
        <span class="v3-pill">
            <a class="v3-pill-main" href="mailto:${escapeHtmlText(displayEmail)}" onclick="event.stopPropagation()" title="ส่งอีเมล">
                <i class="fa fa-envelope"></i><span class="v3-pill-text">${highlightText(escapeHtmlText(displayEmail), q)}</span>
            </a>
            <button type="button" class="v3-copy" onclick="event.stopPropagation(); copyToClipboard(${jsEmail}, 'อีเมล')" title="คัดลอกอีเมล"><i class="fa-regular fa-copy"></i></button>
        </span>` : '';

    return `
        <div class="employee-card v3-card company-${displayCompany} ${isSelected ? 'active' : ''} ${isInactive ? 'is-inactive' : ''}"
             data-id="${safeId}"
             onclick="selectEmployee('${safeId}')">
            <div class="avatar ${isBDay ? 'birthday-avatar' : ''}">
                ${employee.avatar ? `<img src="${escapeHtmlText(employee.avatar)}" alt="" loading="lazy" onerror="this.outerHTML='${initials}'">` : initials}
                ${isBDay ? '<span class="birthday-crown" title="Happy Birthday!">🎂</span>' : ''}
            </div>
            <div class="v3-body">
                <div class="v3-top">
                    <span class="card-name">${highlightText(escapeHtmlText(employee.name), q)}</span>
                    ${displayCompany ? `<span class="company-badge ${displayCompany}">${displayCompany}</span>` : ''}
                    ${status}
                    <button class="favorite-btn ${isFavorite ? 'active' : ''}"
                            onclick="event.stopPropagation(); toggleFavorite('${safeId}')"
                            title="${isFavorite ? 'ยกเลิกปักหมุด' : 'ปักหมุด'}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="${isFavorite ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                    </button>
                </div>
                ${sub ? `<div class="v3-sub">${sub}</div>` : ''}
                ${(phonePill || emailPill) ? `<div class="v3-contact">${phonePill}${emailPill}</div>` : '<div class="v3-contact v3-none">ไม่มีเบอร์/อีเมล</div>'}
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
    // v3: แค่สลับไฮไลต์การ์ด ไม่วาดรายการใหม่ทั้งหมด (เดิมกระพริบทุกครั้งที่กด)
    elements.employeeList.querySelectorAll('.employee-card.active').forEach(c => c.classList.remove('active'));
    elements.employeeList.querySelector(`.employee-card[data-id="${CSS.escape(String(employee.id))}"]`)?.classList.add('active');
    renderEmployeeDetail(employee);

    // v3: รายละเอียดเป็นแผงเลื่อนออกจากขวาทุกขนาดจอ
    elements.employeeDetail.classList.add('mobile-show');
    document.body.classList.add('detail-open');
    elements.employeeDetail.scrollTop = 0;
}

// Render Employee Detail
function renderEmployeeDetail(employee) {
    const isFavorite = state.favorites.includes(employee.id);
    const initials = getInitials(employee.name);
    const yearsWorked = calculateYearsWorked(employee.startDate);
    const isAdminUser = canManageUserPermissions();
    const empId = employee.employeeId || employee.id || '';
    const thaiName = employee.nameTh || employee.name_th || (employee.name !== employee.nameEn ? employee.name : '');
    const showActions = canManageDirectory();
    const assignments = Array.isArray(employee.assignments) ? employee.assignments : [];
    const primaryAssign = getPreferredAssignmentForCurrentFilter(employee) || assignments.find(a => a.is_primary == 1) || assignments[0] || null;
    const contactPhone = normalizePhoneValue(employee.phone || '');
    const contactEmail = primaryAssign?.email || employee.email || '';
    const displayCompany = (primaryAssign?.company || getDisplayCompanyForEmployee(employee) || employee.company || '').trim();
    const displayDepartment = (primaryAssign?.department || employee.department || '').trim();
    const nameEn = employee.nameEn || employee.name || employee.nameTh || employee.name_th || '-';
    const isActive = isEmployeeActive(employee);
    const statusText = isActive ? 'ทำงานอยู่' : 'ลาออกแล้ว';
    // ข้อมูลพนักงานทุกช่องต้อง escape — หน้านี้เปิดดูได้โดยไม่ล็อกอิน
    const e = escapeHtmlText;
    // อีเมลยาว: ให้ขึ้นบรรทัดใหม่ที่ @ ไม่ใช่กลางคำ
    const emailHtml = v => e(v).replace('@', '<wbr>@');
    const jsArg = v => e(JSON.stringify(String(v ?? '')));

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
                    ${employee.avatar ? `<img src="${e(employee.avatar)}" alt="" loading="lazy" onerror="this.outerHTML='${e(initials)}'">` : e(initials)}
                </div>
                <h1 class="detail-name">${e(nameEn)}</h1>
                ${thaiName ? `<p class="detail-position">${e(thaiName)}</p>` : ''}
                <div class="detail-meta-row">
                    ${empId ? `<span class="detail-meta-pill">${e(empId)}</span>` : ''}
                    ${employee.position ? `<span class="detail-meta-pill meta-role">${e(employee.position)}</span>` : ''}
                </div>
                <div class="detail-badges">
                    <span class="detail-badge company ${e(displayCompany || employee.company)}">${e(displayCompany || employee.company)}</span>
                    ${displayDepartment ? `<span class="detail-badge department">${e(displayDepartment)}</span>` : ``}
                    ${employee.isExecutive ? '<span class="detail-badge department">ผู้บริหาร</span>' : ''}
                    ${!isEmployeeActive(employee) ? '<span class="detail-badge inactive-status">⊘ Inactive</span>' : (isRecentlyAdded(employee) ? '<span class="detail-badge new-user">พนักงานใหม่</span>' : '')}
                </div>
            </div>

            <div class="detail-section">
                <div class="detail-section-title">ข้อมูลติดต่อ</div>
                <div class="contact-cards">
                    <div class="contact-card" onclick="handlePhoneAction(${jsArg(contactPhone)})">
                        <div class="contact-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                        </div>
                        <div class="contact-info">
                            <div class="contact-label">โทรศัพท์</div>
                            <div class="contact-value">${e(contactPhone) || '-'}</div>
                        </div>
                        <button class="contact-action" title="โทรออกหรือคัดลอก">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="detail-section">
                <div class="detail-section-title">สังกัด</div>
                <div class="assignments-list">
                    ${assignments.map(a => `
                        <div class="assignment-card ${a.is_primary == 1 ? 'primary' : ''}">
                            <div class="assignment-header">
                                <span class="assignment-company">${e(a.company) || '-'}</span>
                                ${a.is_primary == 1 ? '<span class="assignment-badge">หลัก</span>' : ''}
                            </div>
                            <div class="assignment-dept">${e(a.department) || '-'}</div>
                            <div class="assignment-email-row">
                                <span class="assignment-email">${a.email ? emailHtml(a.email) : '-'}</span>
                                ${a.email ? `<button class="assignment-copy" onclick="event.stopPropagation(); copyToClipboard(${jsArg(a.email)},'อีเมล')">
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
                        <span class="info-label">ตำแหน่ง</span>
                        <span class="info-value">${e(employee.position) || '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">รหัสพนักงาน</span>
                        <span class="info-value">${e(empId) || '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">วันเริ่มงาน</span>
                        <span class="info-value">${isAdminUser ? formatDate(employee.startDate) : '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">อายุงาน</span>
                        <span class="info-value">${isAdminUser && yearsWorked !== '-' ? (yearsWorked < 1 ? 'ไม่ถึง 1 ปี' : yearsWorked + ' ปี') : '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">วันเกิด</span>
                        <span class="info-value">${isAdminUser ? formatDate(employee.birthday) : '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">สถานะ</span>
                        <span class="info-value" style="color: ${isActive ? 'var(--success)' : 'var(--text-secondary)'};">${statusText}</span>
                    </div>
                </div>
            </div>

            <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 16px;">
                <button onclick="toggleFavorite(${jsArg(employee.id)})" style="
                    display: flex; align-items: center; justify-content: center;
                    gap: 8px; padding: 10px 18px;
                    border: 1px solid var(--border);
                    background: var(--bg-card);
                    border-radius: var(--radius-lg);
                    font-size: 14px; font-weight: 500;
                    color: ${isFavorite ? '#ff9500' : 'var(--text-secondary)'};
                    cursor: pointer; transition: var(--transition);
                    flex: 1 1 auto; min-width: 110px;
                ">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="${isFavorite ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                    ${isFavorite ? 'ยกเลิกปักหมุด' : 'ปักหมุด'}
                </button>
                <a href="mailto:${e(contactEmail)}" style="
                    display: flex; align-items: center; justify-content: center;
                    gap: 8px; padding: 10px 18px;
                    border: none; background: var(--accent);
                    border-radius: var(--radius-lg);
                    font-size: 14px; font-weight: 500;
                    color: white; cursor: pointer;
                    text-decoration: none; transition: var(--transition);
                    flex: 1 1 auto; min-width: 110px;
                ">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    ส่งอีเมล
                </a>
                ${showActions ? `
                <button onclick="openAdminAddUser()" style="
                    display: flex; align-items: center; justify-content: center;
                    gap: 8px; padding: 10px 18px;
                    border: 1px dashed var(--border); background: var(--bg-card);
                    border-radius: var(--radius-lg);
                    font-size: 14px; font-weight: 500;
                    color: var(--text-secondary); cursor: pointer; transition: var(--transition);
                    flex: 1 1 auto; min-width: 110px;
                ">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14"></path>
                        <path d="M5 12h14"></path>
                    </svg>
                    เพิ่มพนักงาน
                </button>
                <button onclick="openAdminEdit(${jsArg(empId)})" style="
                    display: flex; align-items: center; justify-content: center;
                    gap: 8px; padding: 10px 18px;
                    border: 1px solid var(--border); background: var(--bg-secondary);
                    border-radius: var(--radius-lg);
                    font-size: 14px; font-weight: 500;
                    color: var(--text-secondary); cursor: pointer; transition: var(--transition);
                    flex: 1 1 auto; min-width: 110px;
                ">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 20h9"></path>
                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                    </svg>
                    แก้ไขพนักงาน
                </button>
                <a href="setup.html?user=${encodeURIComponent(employee.name || '')}&amp;dept=${encodeURIComponent(displayDepartment)}&amp;company=${encodeURIComponent(displayCompany)}&amp;empid=${encodeURIComponent(empId)}" style="
                    display: flex; align-items: center; justify-content: center;
                    gap: 8px; padding: 10px 18px;
                    border: 1px solid #2563eb; background: #eff6ff;
                    border-radius: var(--radius-lg);
                    font-size: 14px; font-weight: 500;
                    color: #1d4ed8; cursor: pointer;
                    transition: var(--transition); text-decoration: none;
                    flex: 1 1 auto; min-width: 110px;
                ">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2"></rect>
                        <path d="M8 21h8M12 17v4"></path>
                    </svg>
                    Setup เครื่อง
                </a>
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
    document.body.classList.remove('detail-open');
}

function closeEmployeeEditorModal() {
    const modal = document.getElementById('employee-editor-modal');
    if (modal) {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
    }
    employeeEditorState = null;
    const saveBtn = document.getElementById('employee-editor-save');
    if (saveBtn) saveBtn.classList.remove('is-loading');
}

function showEmployeeEditorError(messages) {
    const el = document.getElementById('employee-editor-error');
    if (!el) return;
    const list = Array.isArray(messages) ? messages.filter(Boolean) : (messages ? [messages] : []);
    if (!list.length) {
        el.hidden = true;
        el.innerHTML = '';
        return;
    }
    el.hidden = false;
    el.innerHTML = list.map(m => `<div>${escapeHtmlText(m)}</div>`).join('');
}

function buildEmployeeEditorFormHtml(opts) {
    const esc = escapeHtmlText;
    const {
        isEdit, empId, status, nameEnValue, nameThValue, empPosition, phone,
        isManagementFlag, isExecutiveFlag, isTechnicianFlag, canEditSensitive,
        startDateValue, birthdateValue, username,
        canAccessAdminPage, positionOptionsHtml, hasSystemUser, manageSystemDefault,
        currentPhoto
    } = opts;

    return `
        <form id="directory-employee-form" class="dir-popup">
            <input type="hidden" name="manage_system_user" value="0">

            <!-- รูปภาพ — ด้านบนสุด -->
            <div class="dir-popup-section dir-photo-section">
                <div style="display:flex;align-items:center;gap:16px;">
                    <div id="dir-photo-preview-wrap" style="position:relative;flex-shrink:0;">
                        ${currentPhoto
                            ? `<img id="dir-photo-preview" src="${esc(currentPhoto)}" alt="รูปพนักงาน"
                                style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid var(--border);display:block;"
                                onerror="this.src='';this.style.display='none';document.getElementById('dir-photo-initials').style.display='flex'">
                               <div id="dir-photo-initials" style="display:none;width:80px;height:80px;border-radius:50%;background:var(--accent);color:#fff;font-size:24px;font-weight:700;align-items:center;justify-content:center;">${esc((nameEnValue||'').split(' ').map(w=>w[0]||'').join('').substring(0,2).toUpperCase())}</div>`
                            : `<div id="dir-photo-initials" style="display:flex;width:80px;height:80px;border-radius:50%;background:var(--accent);color:#fff;font-size:24px;font-weight:700;align-items:center;justify-content:center;">${esc((nameEnValue||'').split(' ').map(w=>w[0]||'').join('').substring(0,2).toUpperCase())}</div>
                               <img id="dir-photo-preview" src="" alt="" style="display:none;width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid var(--border);">`
                        }
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:13px;font-weight:600;color:var(--text-primary);margin-bottom:2px;">${isEdit ? esc(nameEnValue) : 'พนักงานใหม่'}</div>
                        <div style="font-size:11px;color:var(--text-tertiary);margin-bottom:8px;">${isEdit ? `รหัส ${esc(empId)}` : 'กรอกข้อมูลด้านล่าง'}</div>
                        <label style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid var(--border);border-radius:var(--radius-md);cursor:pointer;font-size:12px;color:var(--text-secondary);background:var(--bg-secondary);">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            ${isEdit ? 'เปลี่ยนรูป' : 'อัปโหลดรูป'}
                            <input type="file" name="imageFile" accept="image/*" style="display:none;"
                                onchange="(function(i){if(!i.files[0])return;const r=new FileReader();r.onload=function(e){const p=document.getElementById('dir-photo-preview');const ini=document.getElementById('dir-photo-initials');if(p){p.src=e.target.result;p.style.display='block';}if(ini)ini.style.display='none';}; r.readAsDataURL(i.files[0]);})(this)">
                        </label>
                    </div>
                </div>
            </div>

            <div class="dir-popup-section">
                <div class="dir-popup-title">ข้อมูลพนักงาน</div>
                <div class="dir-popup-grid">
                    <div>
                        <div class="dir-field-label">รหัสพนักงาน</div>
                        <input type="text" name="id" class="dir-field-input ptc-input" value="${isEdit ? esc(empId) : ''}" ${isEdit ? 'readonly' : ''}>
                    </div>
                    <div>
                        <div class="dir-field-label">สถานะการทำงาน</div>
                        <select name="employment_status" class="dir-field-input ptc-select">
                            <option value="active" ${status === 'active' ? 'selected' : ''}>Active</option>
                            <option value="inactive" ${status === 'inactive' ? 'selected' : ''}>Inactive</option>
                        </select>
                    </div>
                    <div>
                        <div class="dir-field-label">ชื่อ-สกุล (EN)</div>
                        <input type="text" name="name" class="dir-field-input ptc-input" value="${esc(nameEnValue)}">
                    </div>
                    <div>
                        <div class="dir-field-label">ชื่อ-สกุล (TH)</div>
                        <input type="text" name="name_th" class="dir-field-input ptc-input" value="${esc(nameThValue)}">
                    </div>
                    <div>
                        <div class="dir-field-label">ตำแหน่ง</div>
                        <div style="display:flex;gap:8px;">
                            <input type="text" id="dir-position-input" name="position" class="dir-field-input ptc-input" value="${esc(empPosition)}" list="dir-position-list">
                            <button type="button" id="dir-save-position-btn" class="dir-assign-add-btn ptc-btn-ghost" style="max-width:140px;padding:0 10px;white-space:nowrap;">บันทึกตัวเลือก</button>
                        </div>
                        <datalist id="dir-position-list">${positionOptionsHtml}</datalist>
                    </div>
                    <div>
                        <div class="dir-field-label">เบอร์โทร</div>
                        <input type="text" name="phone" class="dir-field-input ptc-input" value="${esc(phone)}">
                    </div>
                    <div>
                        <div class="dir-field-label">ระดับ / บทบาท</div>
                        <div class="dir-role-pill-group">
                            <label class="dir-role-pill dir-role-pill--exec ${isExecutiveFlag ? 'is-active' : ''}">
                                <input type="checkbox" name="is_executive" value="1" ${isExecutiveFlag ? 'checked' : ''} class="dir-role-pill-cb">
                                <i class="fa fa-star"></i> ผู้บริหาร
                            </label>
                            <label class="dir-role-pill dir-role-pill--mgmt ${isManagementFlag ? 'is-active' : ''}">
                                <input type="checkbox" name="is_management" value="1" ${isManagementFlag ? 'checked' : ''} class="dir-role-pill-cb">
                                <i class="fa fa-user-tie"></i> Management
                            </label>
                            <label class="dir-role-pill dir-role-pill--tech ${isTechnicianFlag ? 'is-active' : ''}">
                                <input type="checkbox" name="is_technician" value="1" ${isTechnicianFlag ? 'checked' : ''} class="dir-role-pill-cb">
                                <i class="fa fa-wrench"></i> ช่างเทคนิค
                            </label>
                        </div>
                        <div class="dir-field-help" style="margin-top:4px;">ผู้บริหาร/Management อาจไม่บังคับแผนก — ช่างต้องมีอย่างน้อย 1 สังกัด</div>
                    </div>
                    <div style="display:${canEditSensitive ? 'block' : 'none'};">
                        <div class="dir-field-label">วันเริ่มงาน</div>
                        <input type="date" name="start_date" class="dir-field-input ptc-input" value="${esc(startDateValue)}">
                    </div>
                    <div style="display:${canEditSensitive ? 'block' : 'none'};">
                        <div class="dir-field-label">วันเกิด</div>
                        <input type="date" name="birthdate" class="dir-field-input ptc-input" value="${esc(birthdateValue)}">
                    </div>
                </div>
            </div>
            <div class="dir-popup-section">
                <div class="dir-popup-title">สังกัดและการติดต่อ</div>
                <div id="dir-assignments-container" class="dir-popup-assignments"></div>
                <button type="button" id="dir-add-assignment" class="dir-assign-add-btn">+ เพิ่มสังกัด</button>
            </div>
            ${canEditSensitive ? `
            <div class="dir-popup-section">
                <div class="dir-popup-title">บัญชีผู้ใช้ระบบ</div>
                ${isEdit && hasSystemUser ? `<p class="dir-field-help" style="margin:0 0 8px;">บัญชีปัจจุบัน: <strong>${esc(username)}</strong></p>` : ''}
                <label class="dir-popup-inline" style="margin-bottom:8px;">
                    <input type="checkbox" id="dir-manage-system-user-toggle" name="manage_system_user" value="1" ${manageSystemDefault ? 'checked' : ''}>
                    <span class="dir-field-label" style="margin:0;">${isEdit ? 'แก้ไขบัญชีผู้ใช้ระบบ' : 'สร้างบัญชีผู้ใช้ระบบพร้อมกัน'}</span>
                </label>
                ${!isEdit ? '<div class="dir-field-help" style="margin:0 0 8px;">ค่าเริ่มต้น: Username/Password = รหัสพนักงาน (แก้ไขได้)</div>' : ''}
                <div id="dir-system-user-section" class="dir-popup-grid" style="display:${manageSystemDefault ? 'grid' : 'none'};">
                    <div>
                        <div class="dir-field-label">Username</div>
                        <input type="text" name="username" class="dir-field-input ptc-input" value="${esc(username)}">
                    </div>
                    <div>
                        <div class="dir-field-label">Password</div>
                        <input type="password" name="password" class="dir-field-input ptc-input" placeholder="${isEdit ? 'เว้นว่างถ้าไม่เปลี่ยน' : 'ตั้งรหัสผ่านเริ่มต้น'}">
                    </div>
                    <div style="grid-column:1/-1;">
                        <div class="dir-field-help">บทบาทและสิทธิ์ให้ตั้งค่าที่ Admin → Permissions เท่านั้น</div>
                    </div>
                </div>
                ${isEdit ? `
                <button type="button" id="dir-open-permissions-btn" class="dir-assign-add-btn" style="margin-top:8px;" data-emp-id="${esc(empId)}">
                    เปิดหน้า Permissions ใน Admin
                </button>
                ` : ''}
            </div>
            ` : ''}
            ${!canEditSensitive && canAccessAdminPage && isEdit ? `
            <div class="dir-popup-section">
                <div class="dir-field-help">สิทธิ์ระบบจัดการในหน้า Admin → Permissions เท่านั้น</div>
                <button type="button" id="dir-open-permissions-btn" class="dir-assign-add-btn" data-emp-id="${esc(empId)}">
                    เปิดหน้า Permissions
                </button>
            </div>
            ` : ''}
        </form>
    `;
}

function initEmployeeEditorAssignments(ctx) {
    const { assignments, company, department, email, deptNames } = ctx;
    const container = document.getElementById('dir-assignments-container');
    const addBtn = document.getElementById('dir-add-assignment');
    if (!container) return;

    const deptOptsHtml = deptNames.length
        ? [''].concat(deptNames).map(name => `<option value="${escapeHtmlText(name)}">${escapeHtmlText(name || 'เลือกแผนก')}</option>`).join('')
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
            <div class="dir-assign-card">
                <div class="dir-assign-grid">
                    <div>
                        <div class="dir-field-label">บริษัท</div>
                        <select class="dir-field-input ptc-select dir-assign-company">${companyOptions}</select>
                    </div>
                    <div>
                        <div class="dir-field-label">แผนก</div>
                        <select class="dir-field-input ptc-select dir-assign-dept">${deptOptsHtml}</select>
                    </div>
                    <div>
                        <div class="dir-field-label">อีเมล</div>
                        <input type="email" class="dir-field-input ptc-input dir-assign-email" placeholder="email@company.com">
                    </div>
                </div>
                <div class="dir-assign-footer">
                    <label class="dir-popup-inline">
                        <input type="radio" name="primary_assignment" class="dir-assign-primary">
                        <span class="dir-field-label" style="margin:0;font-size:11px;">สังกัดหลัก</span>
                    </label>
                    <button type="button" class="dir-assign-remove">× ลบสังกัดนี้</button>
                </div>
            </div>
        `;
        const coSel = row.querySelector('.dir-assign-company');
        const deptSel = row.querySelector('.dir-assign-dept');
        const emailInput = row.querySelector('.dir-assign-email');
        const primaryInput = row.querySelector('.dir-assign-primary');
        if (coSel) coSel.value = assign.company || '';
        if (deptSel) deptSel.value = assign.department || '';
        if (emailInput) emailInput.value = assign.email || '';
        if (primaryInput) primaryInput.checked = !!isPrimary;
        row.querySelector('.dir-assign-remove')?.addEventListener('click', () => {
            if (container.querySelectorAll('.dir-assign-row').length <= 1) return;
            row.remove();
        });
        container.appendChild(row);
    };

    container.innerHTML = '';
    const baseAssignments = assignments.length
        ? assignments
        : [{ company: company || '', department: department || '', email: email || '', is_primary: 1 }];
    baseAssignments.forEach((a, idx) => {
        const isPrimary = a.is_primary == 1 || (!assignments.length && idx === 0);
        createRow(a, isPrimary);
    });
    if (addBtn) {
        addBtn.onclick = () => {
            createRow({ company: '', department: '', email: '' }, container.querySelectorAll('.dir-assign-row').length === 0);
        };
    }
}

function wireEmployeeEditorFormHandlers(ctx) {
    const { empId, canEditSensitive } = ctx;

    document.getElementById('dir-save-position-btn')?.addEventListener('click', async () => {
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

    document.querySelectorAll('.dir-role-pill-cb').forEach(cb => {
        cb.addEventListener('change', () => {
            cb.closest('.dir-role-pill')?.classList.toggle('is-active', cb.checked);
        });
    });

    document.getElementById('dir-open-permissions-btn')?.addEventListener('click', (e) => {
        const id = e.currentTarget.getAttribute('data-emp-id') || empId;
        window.open(`admin.html?tab=permissions&user_id=${encodeURIComponent(id)}`, '_blank');
    });

    const manageToggle = document.getElementById('dir-manage-system-user-toggle');
    const systemSection = document.getElementById('dir-system-user-section');
    if (manageToggle && systemSection) {
        const form = document.getElementById('directory-employee-form');
        const idInput = form?.querySelector('[name="id"]');
        const usernameInput = form?.querySelector('[name="username"]');
        const passwordInput = form?.querySelector('[name="password"]');
        const isAddMode = !ctx.empId;
        const applyDefaultCredentials = () => {
            if (!isAddMode || !manageToggle.checked) return;
            const empCode = String(idInput?.value || '').trim();
            if (!empCode) return;
            if (usernameInput && (usernameInput.dataset.autoDefault === '1' || !String(usernameInput.value || '').trim())) {
                usernameInput.value = empCode;
                usernameInput.dataset.autoDefault = '1';
            }
            if (passwordInput && (passwordInput.dataset.autoDefault === '1' || !String(passwordInput.value || '').trim())) {
                passwordInput.value = empCode;
                passwordInput.dataset.autoDefault = '1';
            }
        };
        const syncSystemSection = () => {
            systemSection.style.display = manageToggle.checked ? 'grid' : 'none';
            applyDefaultCredentials();
        };
        if (isAddMode) {
            if (usernameInput && !String(usernameInput.value || '').trim()) usernameInput.dataset.autoDefault = '1';
            if (passwordInput && !String(passwordInput.value || '').trim()) passwordInput.dataset.autoDefault = '1';
            idInput?.addEventListener('input', applyDefaultCredentials);
            usernameInput?.addEventListener('input', () => { usernameInput.dataset.autoDefault = '0'; });
            passwordInput?.addEventListener('input', () => { passwordInput.dataset.autoDefault = '0'; });
        }
        manageToggle.addEventListener('change', syncSystemSection);
        syncSystemSection();
    }

    const editorDeleteBtn = document.getElementById('employee-editor-delete');
    if (editorDeleteBtn) editorDeleteBtn.onclick = () => handleEmployeeEditorDelete(empId);
}

async function handleEmployeeEditorDelete(empId) {
    if (typeof Swal === 'undefined') return;
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
        DIR_CACHE.delEmployees();
        closeEmployeeEditorModal();
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
}

async function submitEmployeeEditorForm() {
    if (!employeeEditorState) return;
    const { isEdit, canEditSensitive } = employeeEditorState;
    const saveBtn = document.getElementById('employee-editor-save');
    showEmployeeEditorError([]);

    const form = document.getElementById('directory-employee-form');
    if (!form) {
        showEmployeeEditorError('ไม่พบฟอร์ม');
        return;
    }

    const id = (form.querySelector('[name="id"]').value || '').trim();
    const name = (form.querySelector('[name="name"]').value || '').trim();
    const employmentStatus = (form.querySelector('[name="employment_status"]').value || '').trim();
    const startDateInput = (form.querySelector('[name="start_date"]')?.value || '').trim();
    const birthdateInput = (form.querySelector('[name="birthdate"]')?.value || '').trim();
    const manageSystemUser = canEditSensitive && !!form.querySelector('#dir-manage-system-user-toggle')?.checked;
    const usernameInput = manageSystemUser ? (form.querySelector('[name="username"]')?.value || '').trim() : '';
    const passwordInput = manageSystemUser ? (form.querySelector('[name="password"]')?.value || '').trim() : '';
    const isExecutiveFlag = !!form.querySelector('input[name="is_executive"]')?.checked;
    const isManagementFlag = !!form.querySelector('input[name="is_management"]')?.checked;
    const isTechnicianFlag = !!form.querySelector('input[name="is_technician"]')?.checked;
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
    if (manageSystemUser && !usernameInput) {
        errors.push('กรุณากรอก Username สำหรับบัญชีผู้ใช้ระบบ');
    }
    if (manageSystemUser && !isEdit && !passwordInput) {
        errors.push('กรุณากรอกรหัสผ่านเมื่อสร้างบัญชีผู้ใช้ระบบ');
    }

    const positionInput = (form.querySelector('[name="position"]')?.value || '').trim();
    const rows = Array.from(document.querySelectorAll('.dir-assign-row'));
    const assignmentsToSave = [];
    let primaryCount = 0;
    rows.forEach(row => {
        const companyVal = (row.querySelector('.dir-assign-company')?.value || '').trim();
        const deptVal = (row.querySelector('.dir-assign-dept')?.value || '').trim();
        const emailVal = (row.querySelector('.dir-assign-email')?.value || '').trim();
        const isPrimary = row.querySelector('.dir-assign-primary')?.checked ? 1 : 0;
        if (!companyVal && !deptVal && !emailVal) return;
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
    if (isTechnicianFlag && !assignmentsToSave.length) {
        errors.push('ทีมช่างต้องมีอย่างน้อย 1 สังกัด');
    }
    if (assignmentsToSave.length) {
        if (primaryCount === 0) errors.push('กรุณาเลือกสังกัดหลักอย่างน้อย 1 รายการ');
        if (primaryCount > 1) errors.push('เลือกสังกัดหลักได้เพียง 1 รายการ');
    }

    if (errors.length) {
        showEmployeeEditorError([...new Set(errors)]);
        return;
    }

    if (saveBtn) saveBtn.classList.add('is-loading');
    try {
        if (positionInput) await saveJobPositionOption(positionInput);
        const fd = new FormData(form);
        fd.set('manage_system_user', manageSystemUser ? '1' : '0');
        fd.set('is_technician', isTechnicianFlag ? '1' : '0');
        fd.set('is_executive', isExecutiveFlag ? '1' : '0');
        fd.set('is_management', isManagementFlag ? '1' : '0');
        fd.delete('role');
        fd.delete('can_manage_stock');
        if (!manageSystemUser) {
            fd.delete('username');
            fd.delete('password');
            fd.delete('responsible_department');
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

        DIR_CACHE.delEmployees();
        closeEmployeeEditorModal();
        await loadEmployeesFromAPI();
        filterEmployees();
        selectEmployee(id);
        if (typeof Swal !== 'undefined') {
            await Swal.fire({
                icon: 'success',
                title: 'บันทึกสำเร็จ',
                text: isEdit ? 'แก้ไขข้อมูลพนักงานเรียบร้อยแล้ว' : 'เพิ่มพนักงานใหม่เรียบร้อยแล้ว',
                timer: 1800,
                showConfirmButton: false
            });
        }
        showToast('บันทึกข้อมูลพนักงานเรียบร้อยแล้ว');
    } catch (err) {
        showEmployeeEditorError(err.message || 'ไม่สามารถบันทึกข้อมูลได้');
    } finally {
        if (saveBtn) saveBtn.classList.remove('is-loading');
    }
}

function initEmployeeEditorModal() {
    if (employeeEditorModalBound) return;
    employeeEditorModalBound = true;

    document.getElementById('employee-editor-overlay')?.addEventListener('click', closeEmployeeEditorModal);
    document.getElementById('employee-editor-close')?.addEventListener('click', closeEmployeeEditorModal);
    document.getElementById('employee-editor-cancel')?.addEventListener('click', closeEmployeeEditorModal);
    document.getElementById('employee-editor-save')?.addEventListener('click', () => submitEmployeeEditorForm());

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        const modal = document.getElementById('employee-editor-modal');
        if (modal && modal.style.display === 'flex') closeEmployeeEditorModal();
    });
}

async function openEmployeeEditor(mode, baseEmployee) {
    // ฟอร์มกลาง (js/employee-form.js) — ใช้ร่วมกับ admin.html · ?oldform=1 = กลับไปใช้ฟอร์มเดิมด้านล่าง
    if (window.PTCEmployeeForm && !/[?&]oldform=1\b/.test(location.search)) {
        const reloadList = async () => { DIR_CACHE.delEmployees(); await loadEmployeesFromAPI(); filterEmployees(); };
        window.PTCEmployeeForm.open({
            mode,
            employee: mode === 'edit' ? baseEmployee : null, // เพิ่มใหม่ = ฟอร์มว่าง (เดิมดึงข้อมูลคนที่เลือกอยู่มาเติมให้)
            canEditSensitive: canManageUserPermissions(),
            canDelete: canManageUserPermissions(),
            onSaved: async ({ id, isNew }) => {
                await reloadList();
                selectEmployee(id);
                showToast(isNew ? 'เพิ่มพนักงานใหม่เรียบร้อยแล้ว' : 'บันทึกข้อมูลพนักงานเรียบร้อยแล้ว');
            },
            onDeleted: async () => { await reloadList(); closeDetail(); showToast('ลบพนักงานแล้ว'); },
            onOpenPermissions: (id) => window.open(`admin.html?tab=permissions&user_id=${encodeURIComponent(id)}`, '_blank'),
        });
        return;
    }
    const modal = document.getElementById('employee-editor-modal');
    if (!modal) {
        if (mode === 'edit') {
            const empId = baseEmployee && (baseEmployee.employeeId || baseEmployee.id || '');
            window.open(`admin.html?tab=employees&employeeId=${encodeURIComponent(empId || '')}`, '_blank');
        } else {
            window.open('admin.html?tab=employees&mode=add', '_blank');
        }
        return;
    }

    initEmployeeEditorModal();

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
    const canEditSensitive = canManageUserPermissions();
    const canAccessAdminPage = !!directorySession.can_access_admin || directorySession.role === 'admin';
    const isExecutiveFlag = String(emp.is_executive ?? (emp.isExecutive ? 1 : 0) ?? '0') === '1';
    const isManagementFlag = String(emp.is_management ?? (emp.isManagement ? 1 : 0) ?? '0') === '1';
    const isTechnicianFlag = String(emp.is_technician ?? '0') === '1';
    const hasSystemUser = String(username || '').trim() !== '';
    const manageSystemDefault = !isEdit ? false : hasSystemUser;
    const deptList = Array.isArray(state.departments) ? state.departments : [];
    const deptNames = deptList.map(d => d.dept_name || d.name || '').filter(Boolean);
    const empPosition = emp.position || '';
    const positionOptionsHtml = getPositionDatalistOptions(empPosition);
    const title = isEdit ? 'แก้ไขข้อมูลพนักงาน' : 'เพิ่มพนักงานใหม่';

    employeeEditorState = { mode, isEdit, empId, canEditSensitive };

    const titleEl = document.getElementById('employee-editor-title');
    const subEl = document.getElementById('employee-editor-subtitle');
    const bodyEl = document.getElementById('employee-editor-body');
    if (titleEl) titleEl.textContent = title;
    if (subEl) subEl.textContent = isEdit ? `รหัส ${empId}` : 'กรอกข้อมูลพนักงานใหม่';
    if (bodyEl) {
        bodyEl.innerHTML = buildEmployeeEditorFormHtml({
            isEdit, empId, status, nameEnValue, nameThValue, empPosition,
            phone: emp.phone || '', isManagementFlag, isExecutiveFlag, isTechnicianFlag,
            canEditSensitive, startDateValue, birthdateValue, username,
            canAccessAdminPage, positionOptionsHtml,
            hasSystemUser, manageSystemDefault,
            currentPhoto: emp.avatar || (emp.image ? `uploads/employees/${emp.image}` : '')
        });
    }

    const deleteBtn = document.getElementById('employee-editor-delete');
    if (deleteBtn) deleteBtn.style.display = (isEdit && canEditSensitive) ? '' : 'none';

    showEmployeeEditorError([]);
    initEmployeeEditorAssignments({ assignments, company, department, email, deptNames });
    wireEmployeeEditorFormHandlers({ empId, canEditSensitive });

    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    document.getElementById('employee-editor-body')?.scrollTo(0, 0);
}

async function openAdminEdit(empId) {
    if (!canManageDirectory()) {
        showToast('ต้องมีสิทธิ์ employee.manage');
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
    if (!canManageDirectory()) {
        showToast('ต้องมีสิทธิ์ employee.manage');
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

    // ให้คอลัมน์และฟิลด์ใกล้เคียง Directory รุ่นเดิม
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
    if (state.companyFilter) suffix = state.companyFilter;
    if (state.deptFilter) suffix = (suffix !== 'All' ? suffix + '_' : '') + state.deptFilter;
    if (suffix === 'All') { const c = getCompanyFilterCodeFromCurrentFilter(); if (c) suffix = c; }

    a.download = `Employee_Directory_${suffix}_${new Date().toISOString().slice(0,10)}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);

    showToast('Export Excel เสร็จแล้ว');
}

// สมุดรายชื่อ (A4 แนวนอน หลายหน้า — แกนกลาง js/doc-print.js หน้าตาเดียวกับใบพิมพ์ทุกใบ)
// พิมพ์ตามตัวกรองที่เลือกอยู่บนจอ · จัดกลุ่มตามแผนก
function exportDirectoryPdf() {
    const rows = state.filteredEmployees.length ? state.filteredEmployees : state.employees;
    if (!rows.length) {
        if (window.Swal) Swal.fire({ icon: 'info', title: 'ไม่พบข้อมูล', text: 'ไม่มีรายชื่อพนักงานที่ตรงกับเงื่อนไขปัจจุบัน' });
        else showToast('ไม่มีข้อมูลสำหรับพิมพ์');
        return;
    }
    const { esc } = DocPrint;
    const companyCode = state.companyFilter || getCompanyFilterCodeFromCurrentFilter() || '';
    const deptFilter = state.deptFilter || (state.currentFilter.startsWith('dept-') ? state.currentFilter.replace('dept-', '') : '');
    const catLabels = { executives: 'ผู้บริหาร', management: 'Management', inactive: 'พนักงาน Inactive', favorites: 'ปักหมุด' };
    const scope = [companyCode, deptFilter, catLabels[state.currentFilter]].filter(Boolean).join(' · ') || 'พนักงานทั้งหมด';

    const groups = {};
    rows.forEach(emp => { const d = getPreferredAssignmentForCurrentFilter(emp)?.department || emp.department || 'ไม่ระบุแผนก'; (groups[d] ||= []).push(emp); });
    let n = 0;
    const body = Object.keys(groups).sort((x, y) => compareDepartmentNames(x, y)).map(d => `<tr class="grp"><td colspan="7">${esc(d)} <span style="font-weight:400;color:#64748b">· ${groups[d].length} คน</span></td></tr>`
        + groups[d].map(emp => {
            const a = getPreferredAssignmentForCurrentFilter(emp) || {};
            return `<tr><td style="text-align:center;color:#64748b">${++n}</td><td class="mono">${esc(emp.employeeId || emp.id || '')}</td>
                <td><b>${esc(getPreferredDisplayName(emp))}</b>${emp.nameTh ? `<div style="font-size:7.6pt;color:#64748b">${esc(emp.nameTh)}</div>` : ''}</td>
                <td>${esc(emp.position || '')}</td><td style="text-align:center">${esc(getDisplayCompanyForEmployee(emp) || emp.company || '')}</td>
                <td class="mono" style="white-space:nowrap">${esc(normalizePhoneValue(emp.phone || ''))}</td><td style="font-size:8pt">${esc(a.email || emp.email || '')}</td></tr>`;
        }).join('')).join('');

    DocPrint.preview({
        company: companyCode, formKey: 'directory', classified: true, size: 'land', flow: true,
        docNo: `DIR-${companyCode || 'ALL'}-${new Date().toISOString().slice(0, 10).replace(/-/g, '')}`,
        dateText: 'ข้อมูล ณ ' + DocPrint.thDate(new Date(), true),
        title: { th: 'สมุดรายชื่อพนักงาน', en: `Employee Directory · ${rows.length} คน` },
        info: [['ขอบเขต', scope], ['จำนวน', rows.length + ' คน']],
        blocks: [`<div class="dp-sec" style="break-inside:auto"><table class="dp-tbl"><thead><tr><th style="width:26px">#</th><th style="width:22mm">รหัส</th><th>ชื่อ - นามสกุล</th>
            <th style="width:44mm">ตำแหน่ง</th><th style="width:14mm">บริษัท</th><th style="width:28mm">โทรศัพท์</th><th style="width:72mm">อีเมล</th></tr></thead><tbody>${body}</tbody></table></div>`],
    });
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

    // แสดงปุ่มเสมอ (ยกเว้นรันใน standalone อยู่แล้ว)
    if (isStandalone) { hideBtn(); return; }
    installBtn.style.display = '';

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
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
        // Clear directory sessionStorage cache
        try {
            Object.keys(sessionStorage).filter(k => k.startsWith('dir_')).forEach(k => sessionStorage.removeItem(k));
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

    // ── Sidebar nav — event delegation เพียงจุดเดียว ครอบทั้ง static + dynamic ──
    function handleNavClick(f) {
        if (!f) return;
        if (f.startsWith('company-')) {
            const code = f.replace('company-', '');
            state.companyFilter = state.companyFilter === code ? '' : code;
        } else if (f.startsWith('dept-')) {
            const dept = f.replace('dept-', '');
            state.deptFilter = state.deptFilter === dept ? '' : dept;
        } else {
            state.currentFilter = f;
            if (f === 'all') { state.companyFilter = ''; state.deptFilter = ''; }
        }
        filterEmployees();
        if (window.innerWidth <= 768) {
            elements.sidebar.classList.remove('open');
            elements.sidebarOverlay.classList.remove('show');
        }
    }

    // Static category items (all, favorites, executives, management, inactive)
    document.getElementById('categorySection')?.addEventListener('click', (e) => {
        const navItem = e.target.closest('.nav-item[data-filter]');
        if (navItem) handleNavClick(navItem.dataset.filter);
    });

    // Incoming section (IT-only, outside categorySection)
    document.getElementById('incomingSection')?.addEventListener('click', (e) => {
        const navItem = e.target.closest('.nav-item[data-filter]');
        if (navItem) handleNavClick(navItem.dataset.filter);
    });

    // Company items — dynamic, ใช้ delegation บน container
    elements.companyList?.addEventListener('click', (e) => {
        const navItem = e.target.closest('.nav-item[data-filter]');
        if (navItem) handleNavClick(navItem.dataset.filter);
    });

    // ปุ่มกรองบริษัทแถวบน — "ทั้งหมด" ล้างตัวกรองบริษัท, ปุ่มอื่นทำงานเหมือนเมนูซ้าย
    document.getElementById('companyChips')?.addEventListener('click', (e) => {
        const chip = e.target.closest('.dir-chip[data-filter]');
        if (!chip) return;
        if (chip.dataset.filter === 'company-') {
            state.companyFilter = '';
            filterEmployees();
        } else {
            handleNavClick(chip.dataset.filter);
        }
    });

    // Department items — dynamic, ใช้ delegation บน container
    elements.departmentList?.addEventListener('click', (e) => {
        const navItem = e.target.closest('.nav-item[data-filter]');
        if (navItem) handleNavClick(navItem.dataset.filter);
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

// ── More menu (··· dropdown) ──────────────────────────────────────────────────
function toggleListMoreMenu(e) {
    e.stopPropagation();
    const menu = document.getElementById('listMoreMenu');
    if (!menu) return;
    menu.classList.toggle('open');
}
function closeListMoreMenu() {
    const menu = document.getElementById('listMoreMenu');
    if (menu) menu.classList.remove('open');
}
document.addEventListener('click', (e) => {
    if (!e.target.closest('.list-more-wrap')) closeListMoreMenu();
});
window.toggleListMoreMenu = toggleListMoreMenu;
window.closeListMoreMenu = closeListMoreMenu;

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

// ── Org Chart Hover Tooltip ──────────────────────────────
(function() {
    let tip = null, showTimer = null, hideTimer = null;

    function createTip() {
        tip = document.createElement('div');
        tip.id = 'orgTip';
        tip.className = 'org-tip';
        document.body.appendChild(tip);
    }

    function positionTip(el) {
        const r = el.getBoundingClientRect();
        const tw = 260, gap = 10;
        let left = r.right + gap;
        let top  = r.top + r.height / 2;
        if (left + tw > window.innerWidth - 8) left = r.left - tw - gap;
        if (left < 8) left = r.left + r.width / 2 - tw / 2;
        top = Math.min(Math.max(top, 8), window.innerHeight - 8);
        tip.style.left = left + 'px';
        tip.style.top  = top + 'px';
        tip.style.transform = 'translateY(-50%)';
    }

    function showTip(el, emp) {
        if (!el.isConnected) return;
        if (!tip) createTip();
        clearTimeout(hideTimer);
        const e = escapeHtmlText;
        const ini = e(getInitials(emp.name));
        const safeIni = ini.replace(/'/g, '&#39;');
        const co  = (emp.company || '').toUpperCase();
        const coColor = co === 'PTA' ? '#34c759' : co === 'PT4' ? '#ff3b30' : co === 'PTE' ? '#007aff' : '#6366f1';
        const phone = e(emp.phone || emp.tel || emp.telephone || '');
        const dept  = e(emp.department || '');
        tip.innerHTML = `
            <div class="org-tip-inner">
                <div class="org-tip-header">
                    <div class="org-tip-photo">
                        ${emp.avatar
                            ? `<img src="${e(emp.avatar)}" onerror="this.outerHTML='<span class=\'org-tip-ini\'>${safeIni}</span>'">`
                            : `<span class="org-tip-ini">${ini}</span>`}
                    </div>
                    <div class="org-tip-title">
                        <div class="org-tip-name">${e(emp.name)}</div>
                        ${emp.nameTh || emp.name_th ? `<div class="org-tip-name-th">${e(emp.nameTh || emp.name_th)}</div>` : ''}
                        ${co ? `<span class="org-tip-badge" style="background:${coColor}">${e(co)}</span>` : ''}
                    </div>
                </div>
                <div class="org-tip-body">
                    ${emp.position ? `<div class="org-tip-row"><span class="org-tip-icon">💼</span><span>${e(emp.position)}</span></div>` : ''}
                    ${dept ? `<div class="org-tip-row"><span class="org-tip-icon">🏢</span><span>${dept}</span></div>` : ''}
                    ${phone ? `<div class="org-tip-row"><span class="org-tip-icon">📞</span><span>${phone}</span></div>` : ''}
                </div>
                <div class="org-tip-footer">คลิกเพื่อดูข้อมูลเพิ่มเติม</div>
            </div>`;
        positionTip(el);
        tip.classList.add('visible');
    }

    function hideTip() {
        if (tip) tip.classList.remove('visible');
    }

    document.addEventListener('mouseover', e => {
        const el = e.target.closest('[data-org-tip]');
        if (!el) return;
        clearTimeout(showTimer);
        clearTimeout(hideTimer);
        showTimer = setTimeout(() => {
            const id = el.dataset.orgTip;
            const emp = (state?.employees || []).find(x => String(x.id) === id);
            if (emp) showTip(el, emp);
        }, 180);
    });

    document.addEventListener('mouseout', e => {
        const el = e.target.closest('[data-org-tip]');
        if (!el) return;
        clearTimeout(showTimer);
        hideTimer = setTimeout(hideTip, 120);
    });

    document.addEventListener('mouseover', e => {
        if (e.target.closest('#orgTip')) clearTimeout(hideTimer);
    });
    document.addEventListener('mouseout', e => {
        if (e.target.closest('#orgTip')) hideTimer = setTimeout(hideTip, 120);
    });

    document.addEventListener('click', e => {
        if (e.target.closest('[data-org-tip]')) hideTip();
    });
})();
