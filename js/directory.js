// Function to apply theme (light/dark)
function applyTheme(theme) {
    document.documentElement.classList.toggle('dark-theme', theme === 'dark');
    document.body.classList.toggle('dark-theme', theme === 'dark');
    document.documentElement.classList.remove('dark-theme-active');
}

// Function to initialize theme toggle button
function initializeThemeToggle() {
    const themeToggleBtn = document.getElementById('theme-toggle');
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const isDark = document.documentElement.classList.toggle('dark');
            document.documentElement.classList.toggle('dark-theme', isDark);
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        });
    }
}
// ... (Global variables) ...
let isManagementCollapsed = true; // <<< เพิ่มบรรทัดนี้: เริ่มต้นให้แสดงผล (ไม่พับ)
// ...
// Global variables
let selectedEmployees = new Set();
let employees = [];
let activeCompany = 'All';
let lightbox;
let isEditing = false;
let isInSelectionMode = false;
let collapsedDepartments = new Set();
let favoriteEmployees = new Set();
// let currentView = 'list'; // Removed: No longer needed
let isAdmin = false;
let isStaff = false;
let departmentPhones = {};
let selectedImageFile = null; // For image upload
let sessionInfoCache = null;

// Department colors map
const departmentColors = {
    "Management": "pink", "Accounting": "pink", "HR and GA": "pink", "IT": "pink", "Safety & Environment": "pink",
    "Production": "green", "Production Engineer": "green",
    "Maintenance": "blue",
    "Logistics": "red",
    "Quality Management": "yellow"
};

// Position ranking map
const positionRanks = { "Managing Director": 1, "Executive Director and General Manager For Administration Division": 2, "Head of General Administratoin Division": 5, "Head of Operations": 6, "Digital Transformation Analyst Manager": 7, "Manager": 8, "Manager (Acting)": 9, "Plan Manager": 10, "Assistant Manger": 11, "Head of Testing":20, "Senior Supervisor": 20, "Supervisor (Lv3)": 21, "Supervisor (Lv2)": 22, "Supervisor (Lv1)": 23, "Senior Officer": 30, "Senior Programmer": 31, "Engineer(Lv3)": 32, "Senior Technician": 33, "Senior Springer": 34, "Officer": 40, "Programmer": 41, "Engineer(Lv2)": 42, "Engineer(Lv1)": 43, "Springer": 44, "Staff": 50, "Factory and Mainteanace": 51, };

// Utility functions for styling based on company
const getCompanyBorderClass = (c) => !c ? '' : (c.toLowerCase().includes('pta') ? 'card-border-pta' : (c.toLowerCase().includes('pt4') ? 'card-border-pt4' : (c.toLowerCase().includes('pte') ? 'card-border-pte' : '')));
const getInitials = (n) => !n ? '' : (n.split(' ').length > 1 ? (n.split(' ')[0][0] + (n.split(' ')[1][0] || '')) : (n[0] || '')).toUpperCase();
const getCompanyClass = (c) => !c ? '' : (c.toLowerCase().includes('pta') ? 'pta-color' : (c.toLowerCase().includes('pt4') ? 'pt4-color' : (c.toLowerCase().includes('pte') ? 'pte-color' : '')));
const getCompanyBgClass = (c) => !c ? '' : (c.toLowerCase().includes('pta') ? 'pta-bg' : (c.toLowerCase().includes('pt4') ? 'pt4-bg' : (c.toLowerCase().includes('pte') ? 'pte-bg' : '')));

// Utility function to get relevant department based on active company filter
function getRelevantDepartment(emp) {
    if (activeCompany !== 'All') {
        const relevantAssignment = emp.assignments.find(a => a.company === activeCompany);
        if (relevantAssignment) return relevantAssignment.department;
    }
    // Fallback to primary or first assignment if 'All' companies or no match
    return (emp.assignments.find(a => a.is_primary == 1) || emp.assignments[0] || {}).department;
}

// Load/Save favorite employees from/to localStorage
function loadFavoritesFromStorage() {
    const favorites = JSON.parse(localStorage.getItem('favoriteEmployees')) || [];
    favoriteEmployees = new Set(favorites);
}
function saveFavoritesToStorage() {
    localStorage.setItem('favoriteEmployees', JSON.stringify([...favoriteEmployees]));
}

// Function to add an assignment row in the edit modal
function addAssignmentRow(assignment = {}) {
    const container = document.getElementById('assignmentsContainer');
    const assignmentId = 'assignment-radio-' + Date.now();
    const isChecked = assignment.is_primary == 1 ? 'checked' : '';
    const newRow = document.createElement('div');
    newRow.className = 'grid grid-cols-12 gap-3 items-center p-2 bg-slate-50 dark:bg-slate-700/50 rounded-lg assignment-row border border-slate-200 dark:border-slate-600'; 
    newRow.innerHTML = `<div class="col-span-12 sm:col-span-3"><select class="assignment-company w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-md py-2 px-3 text-sm"><option value="PTA">PTA</option><option value="PT4">PT4</option><option value="PTE">PTE</option></select></div><div class="col-span-12 sm:col-span-3"><select class="assignment-department w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-md py-2 px-3 text-sm"><option value="">เลือกแผนก</option><option value="Management">Management</option><option value="Accounting">Accounting</option><option value="HR and GA">HR and GA</option><option value="IT">IT</option><option value="Production">Production</option><option value="Production Engineer">Production Engineer</option><option value="Maintenance">Maintenance</option><option value="Logistics">Logistics</option><option value="Quality Management">Quality Management</option><option value="Safety & Environment">Safety & Environment</option></select></div><div class="col-span-12 sm:col-span-4"><input type="email" class="assignment-email w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-md py-2 px-3 text-sm" placeholder="อีเมล" value="${assignment.email || ''}"></div><div class="col-span-6 sm:col-span-1 text-center"><input type="radio" id="${assignmentId}" name="is_primary_assignment" class="assignment-primary h-5 w-5 text-blue-600" ${isChecked}><label for="${assignmentId}" class="text-sm ml-1 dark:text-slate-300">หลัก</label></div><div class="col-span-6 sm:col-span-1 text-right"><button type="button" class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 remove-assignment-btn"><i class="fas fa-trash-alt"></i></button></div>`;
    if (assignment.company) newRow.querySelector('.assignment-company').value = assignment.company;
    if (assignment.department) newRow.querySelector('.assignment-department').value = assignment.department;
    container.appendChild(newRow);
}

// Function to get position abbreviation
function getPositionAbbreviation(position) {
    if (!position || position === 'N/A') return 'N/A';
    const abbreviations = {
        'Managing Director': 'MD',
        'Executive Director and General Manager For Administration Division': 'ED/GM',
        'Head of General Administratoin Division': 'Head/GA',
        'Head of Operations': 'Head/OP',
        'Head of Testing': 'Head/Test',
        'Manager': 'Mgr',
        'Manager (Acting)': 'Mgr(Act)',
        'Plan Manager': 'Plan Mgr',
        'Assistant Manger': 'Asst Mgr',
        'Senior Supervisor': 'Sr Sup',
        'Supervisor (Lv3)': 'Sup(3)',
        'Supervisor (Lv2)': 'Sup(2)',
        'Supervisor (Lv1)': 'Sup(1)',
        'Senior Officer': 'Sr Off',
        'Senior Programmer': 'Sr Prog',
        'Engineer(Lv3)': 'Eng(3)',
        'Engineer(Lv2)': 'Eng(2)',
        'Engineer(Lv1)': 'Eng(1)',
        'Senior Technician': 'Sr Tech',
        'Senior Springer': 'Sr Spr',
        'Springer': 'Spr',
        'Officer': 'Off',
        'Programmer': 'Prog',
        'Staff': 'Staff',
        'Factory and Mainteanace': 'F&M',
    };
    return abbreviations[position] || position.replace(/(\w)\w*\s*/g, (match, p1) => p1.toUpperCase()).substring(0, 5).trim();
}

// Helper function to check if today is birthday
function isBirthday(dateString) {
    if (!dateString) return false;
    const today = new Date();
    const birthDate = new Date(dateString);
    return today.getDate() === birthDate.getDate() && today.getMonth() === birthDate.getMonth();
}

// Helper function to calculate service year
function calculateServiceYear(startDateString) {
    if (!startDateString) return null;
    const start = new Date(startDateString);
    const now = new Date();
    if (start > now) return "ยังไม่เริ่มงาน";

    let years = now.getFullYear() - start.getFullYear();
    let months = now.getMonth() - start.getMonth();
    let days = now.getDate() - start.getDate();

    if (days < 0) {
        months--;
        // Get days in previous month
        const prevMonth = new Date(now.getFullYear(), now.getMonth(), 0);
        days += prevMonth.getDate();
    }
    if (months < 0) {
        years--;
        months += 12;
    }

    let result = [];
    if (years > 0) result.push(`${years} ปี`);
    if (months > 0) result.push(`${months} เดือน`);
    // if (days > 0) result.push(`${days} วัน`); // Optional: show days

    return result.length > 0 ? result.join(' ') : "น้อยกว่า 1 เดือน";
}

// Function to create HTML for an employee card (List View only)
function createEmployeeCardHtml(employee, index = 0) {
    let relevantAssignment;
    if (activeCompany !== 'All' && employee.assignments.some(a => a.company === activeCompany)) {
        relevantAssignment = employee.assignments.find(a => a.company === activeCompany);
    } else {
        relevantAssignment = employee.assignments.find(a => a.is_primary == 1) || employee.assignments[0];
    }
    if (!relevantAssignment) relevantAssignment = {};
    const isSelected = selectedEmployees.has(employee.id);
    const isFavorited = favoriteEmployees.has(employee.id);
    const selectionIndicator = `<div class="selection-indicator"><i class="fas fa-check"></i></div>`;
    const imageUrl = employee.image ? `uploads/employees/${employee.image}` : '';
    const imageContent = employee.image 
        ? `<a href="${imageUrl}" class="employee-lightbox" title="${employee.name}"><img src="${imageUrl}" alt="${employee.name}"></a>` 
        : `<span>${getInitials(employee.name)}</span>`;

    const favoriteBtn = `<button class="favorite-btn user-action-btn has-tooltip ${isFavorited ? 'favorited' : ''}" data-id="${employee.id}" data-tooltip-text="ปักหมุด" onclick="toggleFavorite(event, '${employee.id}')"><i class="${isFavorited ? 'fas' : 'far'} fa-star"></i></button>`;
    const vcardBtn = `<button class="user-action-btn has-tooltip" data-tooltip-text="บันทึก vCard" onclick="event.stopPropagation(); exportVCard('${employee.id}');"><i class="fas fa-address-card"></i></button>`; // Added vCard Button
    const historyBtn = `<a href="log.html?requester=${encodeURIComponent(employee.name)}" onclick="event.stopPropagation()" class="user-action-btn admin-only has-tooltip" data-tooltip-text="ดูประวัติแจ้งซ่อม"><i class="fas fa-history"></i></a>`;
    const adminButtons = `<div class="admin-controls admin-only staff-only"><button class="admin-btn btn-edit has-tooltip" data-tooltip-text="แก้ไข" onclick="event.stopPropagation(); openEditModal('${employee.id}');"><i class="fas fa-pencil-alt"></i></button><button class="admin-btn btn-delete has-tooltip" data-tooltip-text="ลบ" onclick="event.stopPropagation(); deleteEmployee('${employee.id}');"><i class="fas fa-trash-alt"></i></button></div>`;

    const isNew = employee.created_at && (new Date() - new Date(employee.created_at)) / (1000 * 60 * 60 * 24) < 7;
    const isInactive = employee.employment_status === 'inactive';
    let nameContent = employee.name;
    if (isNew) { nameContent += ` <span class="status-badge status-badge-new">New</span>`; }
    else if (isInactive) { nameContent += ` <span class="status-badge status-badge-inactive">ลาออกแล้ว</span>`; }
    const wasUpdated = employee.updated_at && employee.created_at && new Date(employee.updated_at).getTime() > new Date(employee.created_at).getTime() + 5000;
    const isRecentlyUpdated = wasUpdated && (new Date() - new Date(employee.updated_at)) / (1000 * 60 * 60 * 24) < 14;
    let recentlyUpdatedText = isRecentlyUpdated && !isNew ? `<span class="text-xs text-gray-400 italic">(อัปเดต: ${new Date(employee.updated_at).toLocaleDateString('th-TH')})</span>` : '';
    const otherAssignments = employee.assignments.filter(a => a.company !== relevantAssignment.company);
    let companyInfoHtml = `<p class="text-sm font-medium ${getCompanyClass(relevantAssignment.company)}">${relevantAssignment.company || ''} - ${relevantAssignment.department || ''}</p>`;
    if (otherAssignments.length > 0) companyInfoHtml += `<p class="text-xs text-gray-500 dark:text-gray-400 mt-1">ดูแล: ${otherAssignments.map(a => a.company).join(', ')}</p>`;

    const companyBorderClass = getCompanyBorderClass(relevantAssignment.company);
const baseClasses = `card-bordered transition relative ${isSelected ? 'selected' : ''} ${isInactive ? 'opacity-50' : ''} ${companyBorderClass}`;
    const viewClasses = 'list-view-row rounded-lg shadow px-6 py-4 flex items-center gap-4 animate-fade-in-up';
    const animStyle = `style="animation-delay: ${index * 0.03}s"`; // Stagger delay 30ms per item

    // Birthday Logic
    const isBirthdayToday = isBirthday(employee.birthdate);
    const birthdayCrown = isBirthdayToday ? `<div class="absolute -top-4 -right-2 text-2xl text-yellow-400 z-10 animate-bounce" style="animation-duration: 2s; filter: drop-shadow(0 0 6px rgba(250, 204, 21, 0.9));" title="Happy Birthday!"><i class="fas fa-crown"></i></div>` : '';

    const avatarHtml = `<div class="relative inline-block"><div class="profile-avatar">${imageContent}</div>${birthdayCrown}</div>`;
    const contactInfoHtml = `<div class="flex-1 text-sm text-gray-600 dark:text-gray-300"> <div class="flex items-center contact-item">
                                    <i class="fas fa-envelope w-4 mr-1 text-gray-400"></i>
                                    <span class="truncate">${relevantAssignment.email || 'N/A'}</span>
                                    ${relevantAssignment.email ? `<button class="quick-copy-btn has-tooltip" data-tooltip-text="คัดลอกอีเมล" data-copy="${relevantAssignment.email}"><i class="far fa-copy"></i></button>` : ''}
                                </div>
                                <div class="flex items-center contact-item">
                                    <i class="fas fa-phone w-4 mr-1 text-gray-400"></i>
                                    <span>${employee.phone || 'N/A'}</span>
                                    ${employee.phone ? `<button class="quick-copy-btn has-tooltip" data-tooltip-text="คัดลอกเบอร์โทร" data-copy="${employee.phone}"><i class="far fa-copy"></i></button>` : ''}
                                </div>
                            </div>`;
    const abbrPosition = getPositionAbbreviation(employee.position);
    const positionHtml = employee.position ? `<p class="text-xs text-green-600 font-medium"><span class="has-tooltip" data-tooltip-text="${employee.position}">${abbrPosition}</span> ${recentlyUpdatedText}</p>` : '';
    const nameThHtml = employee.name_th ? `<p class="text-xs text-gray-500 dark:text-gray-400 truncate">${employee.name_th}</p>` : '';
    
    const serviceYear = calculateServiceYear(employee.start_date);
    const serviceYearHtml = serviceYear ? `<p class="text-[10px] text-indigo-500 dark:text-indigo-400 mt-0.5"><i class="fas fa-briefcase mr-1"></i>อายุงาน: ${serviceYear}</p>` : '';

    const mainInfoHtml = `<div class="flex-1 min-w-0 space-y-0.5">
                            <p class="font-semibold text-gray-900 dark:text-gray-100 truncate flex items-center">${nameContent}</p>
                            ${nameThHtml}
                            <p class="text-sm text-gray-600 dark:text-gray-400 truncate">ID: ${employee.id || 'N/A'}</p>
                            ${positionHtml}
                            ${serviceYearHtml}
                          </div>`;

    return `
        <div class="${baseClasses} ${viewClasses}" ${animStyle} data-id="${employee.id}" onclick="handleCardClick(event)">
            ${selectionIndicator}
            ${avatarHtml}
            <div class="flex-1 min-w-0 w-4/12">
                ${mainInfoHtml}
            </div>
            <div class="flex-1 min-w-0 w-3/12">
                ${companyInfoHtml}
            </div>
            <div class="flex-1 min-w-0 w-3/12">
                ${contactInfoHtml}
            </div>
            <div class="flex items-center justify-end gap-2 w-1/12 action-cell">
                <div class="flex items-center justify-end gap-2">
                    ${favoriteBtn}
                    ${vcardBtn}
                    ${historyBtn}
                    ${adminButtons}
                </div>
            </div>
        </div>
     `;
}

// Function to render the employee list
/**
 * Renders the employee list based on current filters and sort mode.
 * Displays a "Management Level" group at the top, followed by Favorites,
 * then employees grouped by department (including managers), and finally inactive employees.
 * @param {Array} data - The array of employee objects to render.
 * @param {string} sortMode - The current sorting mode ('name_asc', 'name_desc', 'department').
 */
function renderData(data, sortMode) {
    const listContainer = document.getElementById('departmentList');
    listContainer.innerHTML = ''; // Clear previous content
    document.getElementById('noResults').classList.toggle('hidden', data.length > 0);

    const deptFilterValue = document.getElementById('departmentFilter')?.value || 'all';

    // If a specific department is selected via the filter, OR if the sort mode is not 'department' (grouped), render a simple flat list.
    if (sortMode !== 'department' || deptFilterValue !== 'all') {
        // For flat list views, only show active employees.
        const activeData = data.filter(emp => emp.employment_status === 'active');
        const cardsHtml = activeData.map((employee, index) => createEmployeeCardHtml(employee, index)).join('');
        listContainer.innerHTML = `<div class="flex flex-col gap-3">${cardsHtml}</div>`;

        // Since we are not rendering department checkboxes in this view, this is safe but might not be needed.
        document.querySelectorAll('.department-checkbox[data-indeterminate="true"]').forEach(cb => { cb.indeterminate = true; });

        // Update visibility of admin elements
        checkAdminStatusAndSetupUI();

        // Initialize Lightbox
        if (lightbox) lightbox.destroy();
        lightbox = new SimpleLightbox('.employee-lightbox', { sourceAttr: 'href', overlay: true, showCounter: false });
        return; // IMPORTANT: Exit the function here to prevent the grouping logic below from running.
    }

    // 1. Separate Active and Inactive Employees
    const activeEmployees = data.filter(emp => emp.employment_status !== 'inactive');
    const inactiveEmployees = data.filter(emp => emp.employment_status === 'inactive');
    inactiveEmployees.sort((a, b) => a.name.localeCompare(b.name, 'th')); // Sort inactive by name

    // 2. Define and Filter Management Level Employees
    // --- Adjust this criteria as needed ---
    const managementPositions = Object.keys(positionRanks).filter(p => positionRanks[p] < 20); // e.g., Rank < 20 for Manager and above
    // const managementPositions = ["Managing Director", "Executive Director...", "Head...", "Manager", "Manager (Acting)", "Plan Manager", "Assistant Manger"]; // Alternative: Specify names

    let managementList = activeEmployees.filter(emp =>
        emp.position && managementPositions.includes(emp.position)
    );

    // Filter managementList based on activeCompany filter
    if (activeCompany !== 'All') {
        managementList = managementList.filter(emp =>
            emp.assignments.some(a => a.company === activeCompany)
        );
    }

    // Sort Management Level list (Rank ascending, then Name ascending)
    managementList.sort((a, b) => {
        const rankDiff = (positionRanks[a.position] || 99) - (positionRanks[b.position] || 99);
        if (rankDiff !== 0) return rankDiff;
        return a.name.localeCompare(b.name, 'th');
    });

    // 3. Separate Favorites from *all* active employees
    const favoritesList = activeEmployees
        .filter(emp => favoriteEmployees.has(emp.id))
        .sort((a, b) => a.name.localeCompare(b.name, 'th'));

    // 4. Get the remaining active employees (including managers who are not favorites) for department grouping
    const regularEmployeesForDeptGrouping = activeEmployees.filter(emp => !favoriteEmployees.has(emp.id));

    // 5. Group remaining employees by their relevant department
    const groupedByDept = regularEmployeesForDeptGrouping.reduce((acc, emp) => {
        const dept = getRelevantDepartment(emp) || 'Uncategorized';
        (acc[dept] = acc[dept] || []).push(emp);
        return acc;
    }, {});

    // Sort employees *within* each department by rank, then name
    Object.values(groupedByDept).forEach(deptEmployees => {
        deptEmployees.sort((a, b) => {
            const rankDiff = (positionRanks[a.position] || 99) - (positionRanks[b.position] || 99);
            if (rankDiff !== 0) return rankDiff;
            return a.name.localeCompare(b.name, 'th');
        });
    });

    // Sort department names (Management department first if exists, then alphabetically)
    const sortedDepts = Object.keys(groupedByDept).sort((a, b) => {
        // Keep the original 'Management' department sort logic if it exists in data
        if (a === 'Management') return -1;
        if (b === 'Management') return 1;
        // Otherwise, sort alphabetically
        return a.localeCompare(b, 'th');
    });


    // --- Assemble the final HTML ---
    let finalHtml = '';

    // A. Add Management Level Section (if any) - Collapsible
    if (managementList.length > 0) {
        // Collapsible Header for Management Level
        // Calculate selection state for Management Level
const selectedInManagement = managementList.filter(e => selectedEmployees.has(e.id)).length;
const isManagementChecked = managementList.length > 0 && selectedInManagement === managementList.length;
const isManagementIndeterminate = selectedInManagement > 0 && selectedInManagement < managementList.length;

// 🎨 GOGO UPDATE: เปลี่ยนหัวข้อ Management ให้ดูพรีเมียม (สีทอง/ส้ม) แยกจากแผนกปกติ
    const managementHeaderHtml = `
        <div class="management-header px-4 py-3 font-bold text-lg flex items-center justify-between mt-0 mb-4 cursor-pointer rounded-lg shadow-sm border border-amber-200 bg-gradient-to-r from-amber-50 to-white dark:from-amber-900/30 dark:to-transparent ${isManagementCollapsed ? 'collapsed' : ''}" data-section="management-level">
            <div class="flex items-center gap-3">
                <i class="chevron-icon fas fa-chevron-down text-amber-500 transition-transform duration-300 ${isManagementCollapsed ? '-rotate-90' : ''}"></i>
                
                <div class="w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center">
                    <i class="fas fa-crown text-amber-500 text-sm"></i>
                </div>

                <span class="text-amber-800 dark:text-amber-200">Management Level</span>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-800 dark:text-amber-200">
                    VIP ${managementList.length}
                </span>
            </div>
            
            <div class="flex items-center gap-4">
                <span class="copy-email-hint text-xs italic text-amber-500 opacity-0 transition-opacity duration-300">copy email -&gt;</span>
                <input type="checkbox" class="department-checkbox h-5 w-5 rounded border-amber-300 text-amber-600 focus:ring-amber-500 cursor-pointer" data-department="management-level" title="เลือกผู้บริหารทั้งหมด" ${isManagementChecked ? 'checked' : ''} ${isManagementIndeterminate ? 'data-indeterminate="true"' : ''}>
            </div>
        </div>`;

        const managementCardsHtml = managementList.map((employee, index) => createEmployeeCardHtml(employee, index)).join('');
        // Wrap content for collapse animation
        const managementContentHtml = `<div class="flex flex-col gap-3 pt-3 mb-6">${managementCardsHtml}</div>`; // Added mb-6 here for spacing when open

        finalHtml += managementHeaderHtml + `<div class="management-content overflow-hidden transition-all duration-500 ease-in-out" style="max-height: ${isManagementCollapsed ? '0px' : '20000px'}; opacity: ${isManagementCollapsed ? '0' : '1'}">${managementContentHtml}</div>`;
    }


    // B. Add Favorites Section (if any)
    if (favoritesList.length > 0) {
        const favoritesHeaderHtml = `
            <div class="favorites-header px-4 py-3 font-bold text-lg flex items-center gap-3 mb-3 rounded-md shadow">
                <i class="fas fa-star text-yellow-500"></i>
                <span>รายการโปรด (Favorites)</span>
                <span class="text-sm font-medium text-gray-500">(${favoritesList.length} คน)</span>
            </div>`;
        const favoritesCardsHtml = favoritesList.map((employee, index) => createEmployeeCardHtml(employee, index)).join('');
        finalHtml += favoritesHeaderHtml + `<div class="flex flex-col gap-3 mb-4">${favoritesCardsHtml}</div>`;
    }

    // C. Add Department Sections
    sortedDepts.forEach(dept => {
        const deptEmployees = groupedByDept[dept];
        if (!deptEmployees || deptEmployees.length === 0) return; // Skip empty departments

        const isCollapsed = collapsedDepartments.has(dept);
        const isNewInDept = deptEmployees.some(emp => emp.created_at && (new Date() - new Date(emp.created_at)) / (1000 * 60 * 60 * 24) < 7);
        const deptNewBadge = isNewInDept ? `<span class="inline-block bg-green-100 text-green-800 text-xs font-semibold ml-2 px-2 py-0.5 rounded-full">NEW!</span>` : ''; // Simple New Badge
        const selectedInDept = deptEmployees.filter(e => selectedEmployees.has(e.id)).length;
        const isDeptChecked = deptEmployees.length > 0 && selectedInDept === deptEmployees.length;
        const isDeptIndeterminate = selectedInDept > 0 && selectedInDept < deptEmployees.length;
        const colorName = departmentColors[dept] || 'default'; // Use your color mapping
        // Define CSS variable for color dot background based on colorName
        // No need to define inline style here if variables are in CSS
        const colorDotHtml = `<span class="inline-block w-3 h-3 rounded-full mr-2" style="background-color: var(--department-color-${colorName}, var(--text-secondary));"></span>`; // Simple color dot

        // Department Header HTML
        const headerHtml = `
            <div class="department-header font-bold text-lg px-4 py-3 flex items-center justify-between mt-6 mb-1 ${isCollapsed ? 'collapsed' : ''} bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 cursor-pointer hover:shadow-md hover:border-blue-300 dark:hover:border-blue-700 transition-all" data-department="${dept}">
                
                <div class="flex items-center gap-3" style="padding-left: 2rem;">
                
                    <i class="chevron-icon fas fa-chevron-down text-gray-400 dark:text-gray-500 transform transition-transform duration-300 ${isCollapsed ? '-rotate-90' : ''}"></i>
                    ${colorDotHtml}
                    <i class="fas fa-building text-gray-500 dark:text-gray-400"></i>
                    <span class="text-slate-800 dark:text-slate-100">${dept}</span>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">(${deptEmployees.length} คน)</span>
                    ${deptNewBadge}
                    <button class="print-btn ml-4 text-gray-400 hover:text-blue-500 has-tooltip" data-tooltip-text="พิมพ์รายชื่อแผนก" data-department="${dept}">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
                
                <div class="flex items-center gap-4">
                    <span class="copy-email-hint text-xs italic text-red-500 dark:text-red-400 opacity-0 transition-opacity duration-300">copy email -&gt;</span>
                    <input type="checkbox" class="department-checkbox h-5 w-5 rounded border-gray-400 text-blue-600 focus:ring-blue-500 cursor-pointer" data-department="${dept}" title="เลือกทั้งหมดในแผนก ${dept}" ${isDeptChecked ? 'checked' : ''} ${isDeptIndeterminate ? 'data-indeterminate="true"' : ''}>
                </div>
            </div>`;


        // Employee Cards HTML for this department
        const deptCardsHtml = deptEmployees.map((employee, index) => createEmployeeCardHtml(employee, index)).join('');
        const contentHtml = `<div class="flex flex-col gap-3 pt-3">${deptCardsHtml}</div>`;

        // Combine header and content for this department
        finalHtml += headerHtml + `<div class="department-content overflow-hidden transition-all duration-500 ease-in-out" style="max-height: ${isCollapsed ? '0px' : '20000px'}; opacity: ${isCollapsed ? '0' : '1'}">${contentHtml}</div>`;
    });

    // D. Add Inactive Section (if admin and any exist)
    if (isAdmin && inactiveEmployees.length > 0) {
        const isInactiveCollapsed = collapsedDepartments.has('inactive-section');
        // Calculate state for Inactive
const selectedInInactive = inactiveEmployees.filter(e => selectedEmployees.has(e.id)).length;
const isInactiveChecked = inactiveEmployees.length > 0 && selectedInInactive === inactiveEmployees.length;
const isInactiveIndeterminate = selectedInInactive > 0 && selectedInInactive < inactiveEmployees.length;

const inactiveHeaderHtml = `
    <div class="department-header font-bold text-lg px-4 py-3 flex items-center justify-between mt-8 mb-0 ${isInactiveCollapsed ? 'collapsed' : ''} border-b border-gray-200 dark:border-gray-700 cursor-pointer" data-department="inactive-section">

        <div class="flex items-center gap-3" style="padding-left: 2rem;">

            <i class="chevron-icon fas fa-chevron-down text-gray-400 dark:text-gray-500 transform transition-transform duration-300 ${isInactiveCollapsed ? '-rotate-90' : ''}"></i>
            <i class="fas fa-user-slash text-gray-500 dark:text-gray-400"></i>
            <span>พนักงานที่ลาออกแล้ว</span>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">(${inactiveEmployees.length} คน)</span>
        </div>

        <div class="flex items-center gap-4">
            <span class="copy-email-hint text-xs italic text-red-500 dark:text-red-400 opacity-0 transition-opacity duration-300">copy email -&gt;</span>
            <input type="checkbox" class="department-checkbox h-5 w-5 rounded border-gray-400 text-blue-600 focus:ring-blue-500 cursor-pointer" data-department="inactive-section" title="เลือกทั้งหมดใน พนักงานที่ลาออกแล้ว" ${isInactiveChecked ? 'checked' : ''} ${isInactiveIndeterminate ? 'data-indeterminate="true"' : ''}>
        </div>
        </div>`;
        const inactiveCardsHtml = inactiveEmployees.map((employee, index) => createEmployeeCardHtml(employee, index)).join('');
        const inactiveContentHtml = `<div class="flex flex-col gap-3 pt-3">${inactiveCardsHtml}</div>`;
        finalHtml += inactiveHeaderHtml + `<div class="department-content overflow-hidden transition-all duration-500 ease-in-out" style="max-height: ${isInactiveCollapsed ? '0px' : '20000px'}; opacity: ${isInactiveCollapsed ? '0' : '1'}">${inactiveContentHtml}</div>`;
    }

    // --- Render the final HTML ---
    listContainer.innerHTML = finalHtml;

    // Re-apply indeterminate state to checkboxes
    document.querySelectorAll('.department-checkbox[data-indeterminate="true"]').forEach(cb => { cb.indeterminate = true; });

    // Update visibility of admin elements
    checkAdminStatusAndSetupUI();

    // Initialize Lightbox
    if (lightbox) lightbox.destroy();
    lightbox = new SimpleLightbox('.employee-lightbox', { sourceAttr: 'href', overlay: true, showCounter: false });
} // <--- นี่คือ } ปิดท้ายสุดของฟังก์ชัน renderData

// Function to render company filter buttons
function renderCompanyFilters() {
    const container = document.getElementById('companyFilterButtons');
    const companies = ['All', ...new Set(employees.flatMap(e => e.assignments.map(a => a.company)))].filter(Boolean).sort();
    container.innerHTML = [...new Set(companies)].map(company => `<button class="filter-btn px-4 py-2 border rounded-full text-sm font-medium inline-flex items-center ${getCompanyBgClass(company)} ${company === 'All' ? 'active' : ''}" data-company="${company}">${company !== 'All' ? `<i class="fas fa-building fa-fade mr-2" style="--fa-animation-duration: 2s;"></i>` : ''}<span>${company}</span></button>`).join('');
}

// Function to render department filter dropdown
function renderDepartmentFilter() {
    const container = document.getElementById('departmentFilterContainer');
    if (!container) return;

    // Get unique departments from ACTIVE employees, filtered by company
    const activeEmployees = employees.filter(emp => emp.employment_status === 'active');
    const filteredByCompany = activeEmployees.filter(emp => activeCompany === 'All' || emp.assignments.some(a => a.company === activeCompany));
    const departments = [...new Set(filteredByCompany.flatMap(e => e.assignments.map(a => a.department)))].filter(Boolean).sort();

    if (departments.length <= 1) {
        container.innerHTML = ''; // Hide if only one or zero departments
        return;
    }

    let optionsHtml = '<option value="all">ทุกแผนก</option>';
    optionsHtml += departments.map(dept => `<option value="${dept}">${dept}</option>`).join('');

    container.innerHTML = `
        <span class="text-xs font-bold text-slate-400 px-2 uppercase">Dept</span>
        <select id="departmentFilter" class="bg-transparent border-none text-sm font-medium text-slate-700 dark:text-slate-200 focus:ring-0 cursor-pointer py-1 pr-8">
            ${optionsHtml}
        </select>`;
    
    document.getElementById('departmentFilter')?.addEventListener('change', filterAndSortData);
}

// Function to render department phone bar
function renderDepartmentPhoneBar() {
    const phoneBar = document.getElementById('departmentPhoneBar');
    if (activeCompany === 'All' || !departmentPhones[activeCompany] || Object.keys(departmentPhones[activeCompany]).length === 0) {
        phoneBar.classList.add('hidden');
        return;
    }
    const companyPhones = departmentPhones[activeCompany];
    let headerHtml = `<div class="flex justify-between items-center mb-4"><h3 class="text-lg font-semibold text-gray-800 dark:text-white">เบอร์โทรศัพท์แผนก (${activeCompany})</h3><button id="editDeptPhoneBtn" class="admin-only staff-only text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 font-semibold flex items-center gap-2 text-sm px-3 py-1 rounded-md hover:bg-gray-100 dark:hover:bg-slate-700" title="แก้ไขเบอร์โทรศัพท์"><i class="fas fa-pencil-alt"></i> แก้ไข</button></div>`;
    let phoneHtml = '<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-8 gap-y-4">';
    let hasContent = false;
    Object.entries(companyPhones).forEach(([dept, phoneData]) => {
        const colorName = departmentColors[dept] || 'default';
        let itemHtml = `<div class="flex flex-col">`;
        let itemHasContent = false;
        if (typeof phoneData === 'string' && phoneData) {
            itemHtml += `<div class="font-semibold text-sm flex items-center gap-2 dark:text-gray-200"><i class="fas fa-building fa-xs phone-icon-${colorName}"></i> ${dept}</div>`;
            itemHtml += `<div class="text-sm text-gray-600 dark:text-gray-400 pl-5">${phoneData}</div>`;
            itemHasContent = true;
        } else if (typeof phoneData === 'object' && phoneData !== null && Object.values(phoneData).some(p => p)) {
            itemHtml += `<div class="font-semibold text-sm flex items-center gap-2 dark:text-gray-200"><i class="fas fa-building fa-xs phone-icon-${colorName}"></i> ${dept}</div>`;
            const subDepts = Object.entries(phoneData).filter(([_, subPhone]) => subPhone).map(([subDept, subPhone]) => `<div class="text-sm text-gray-600 dark:text-gray-400 pl-5 flex items-baseline gap-2"><span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span> <span>${subDept}: ${subPhone}</span></div>`).join('');
            if(subDepts) {
                itemHtml += subDepts;
                itemHasContent = true;
            }
        }
        itemHtml += `</div>`;
        if (itemHasContent) {
            phoneHtml += itemHtml;
            hasContent = true;
        }
    });
    phoneHtml += '</div>';
    if (hasContent) {
       phoneBar.innerHTML = headerHtml + phoneHtml;
       phoneBar.classList.remove('hidden');
       document.getElementById('editDeptPhoneBtn')?.addEventListener('click', openPhoneEditModal);
       checkAdminStatusAndSetupUI();
    } else {
       phoneBar.classList.add('hidden');
    }
}

// Function to filter and sort data based on search and sort selection
function filterAndSortData() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const sortValue = document.getElementById('sortSelect').value;
    const deptFilterValue = document.getElementById('departmentFilter')?.value || 'all';

    let filteredData = employees.filter(emp => {
        const matchesCompany = activeCompany === 'All' || emp.assignments.some(a => a.company === activeCompany);
        const relevantDept = getRelevantDepartment(emp);
        const matchesDept = deptFilterValue === 'all' || relevantDept === deptFilterValue;
        const matchesSearch = !searchTerm || [emp.id, emp.name, emp.name_th, emp.position, ...emp.assignments.flatMap(a => [a.company, a.department, a.email])].some(v => v && v.toLowerCase().includes(searchTerm));
        return matchesCompany && matchesDept && matchesSearch;
    });

    // If a specific department is filtered, the primary sort is ALWAYS by position rank.
    if (deptFilterValue !== 'all') {
        filteredData.sort((a, b) => {
            const rankA = positionRanks[a.position] || 99;
            const rankB = positionRanks[b.position] || 99;
            if (rankA !== rankB) return rankA - rankB;
            return a.name.localeCompare(b.name, 'th'); // Sort by name within the same department
        });
    } else {
        // Otherwise, use the selected sort option from the dropdown.
        if (sortValue === 'name_asc') {
             filteredData.sort((a, b) => a.name.localeCompare(b.name, 'th'));
        } else if (sortValue === 'name_desc') {
             filteredData.sort((a, b) => b.name.localeCompare(a.name, 'th'));
        } else if (sortValue === 'department_asc') {
            filteredData.sort((a, b) => {
                const deptA = getRelevantDepartment(a) || 'zz'; // Put employees without dept at the end
                const deptB = getRelevantDepartment(b) || 'zz';
                if (deptA.localeCompare(deptB, 'th') !== 0) return deptA.localeCompare(deptB, 'th');
                return a.name.localeCompare(b.name, 'th'); // Sort by name within the same department
            });
        }
    }
    renderDepartmentPhoneBar();
    renderData(filteredData, sortValue); // Render list view only
}

// Function to toggle favorite status
function toggleFavorite(event, employeeId) {
    event.stopPropagation();
    if (favoriteEmployees.has(employeeId)) {
        favoriteEmployees.delete(employeeId);
    } else {
        favoriteEmployees.add(employeeId);
    }
    saveFavoritesToStorage();
    filterAndSortData();
}

// Function to handle click on an employee card
function handleCardClick(event) {
    const card = event.target.closest('.card-bordered');
    if (!card) return;
    const employeeId = card.dataset.id;
    // Ignore clicks on action buttons
    if (event.target.closest('.admin-controls') || event.target.closest('.user-action-btn') || event.target.closest('.employee-lightbox')) { // Changed favorite-btn to user-action-btn
        return;
    }
    // Handle selection mode or open view modal
    if (isInSelectionMode) {
        if (selectedEmployees.has(employeeId)) {
            selectedEmployees.delete(employeeId);
        } else {
            selectedEmployees.add(employeeId);
        }
        filterAndSortData(); // Re-render to show selection change
        updateSelectionBar();
    } else {
        openViewModal(employeeId);
    }
}

// Function to initialize all event listeners
function initEventListeners() {
    document.body.classList.remove('selection-mode-active'); // Ensure selection mode is off on load

    document.getElementById('searchInput').addEventListener('input', filterAndSortData);
    document.getElementById('sortSelect').addEventListener('change', filterAndSortData);

    // Company filter button listener
    document.getElementById('companyFilterButtons').addEventListener('click', (e) => {
        const btn = e.target.closest('.filter-btn');
        if (btn) {
            document.querySelectorAll('#companyFilterButtons .filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeCompany = btn.dataset.company;
            document.querySelectorAll('#companyAddressContainer > div').forEach(div => div.classList.add('hidden'));
            if (activeCompany !== 'All') {
                const addressDiv = document.getElementById(`address-${activeCompany.toLowerCase()}`);
                if (addressDiv) {
                    addressDiv.classList.remove('hidden');
                }
            }
            filterAndSortData();
            renderDepartmentFilter(); // Re-render department filter when company changes
        }
    });

    // Admin/Modal/Action button listeners
    document.getElementById('addEmployeeBtn').addEventListener('click', () => openEditModal());
    document.getElementById('employeeForm').addEventListener('submit', handleFormSubmit);
    document.getElementById('logoutBtn').addEventListener('click', logout);
    document.getElementById('editModal').addEventListener('click', (e) => { if (e.target.closest('.remove-assignment-btn')) e.target.closest('.assignment-row').remove(); });
    document.getElementById('addAssignmentBtn')?.addEventListener('click', () => addAssignmentRow());

    // Use event delegation on parent container for card clicks
    document.getElementById('departmentList').addEventListener('click', handleCardClick);

    // Use event delegation on main content for checkboxes and headers
    const mainContent = document.querySelector('main');
    mainContent.addEventListener('change', (e) => {
    if (e.target.matches('.department-checkbox')) {
        const departmentOrGroup = e.target.dataset.department; // Renamed variable
        let employeesToSelect = []; // Array to hold the employees we need to affect

        // --- GOGO UPDATE: Re-filter data just like in renderData() to get the correct lists ---
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        let filteredData = employees.filter(emp => {
            const matchesCompany = activeCompany === 'All' || emp.assignments.some(a => a.company === activeCompany);
            const matchesSearch = !searchTerm || [emp.id, emp.name, emp.name_th, emp.position, ...emp.assignments.flatMap(a => [a.company, a.department, a.email])].some(v => v && v.toLowerCase().includes(searchTerm));
            return matchesCompany && matchesSearch;
        });
        // --- End GOGO UPDATE ---

        const activeFiltered = filteredData.filter(emp => emp.employment_status !== 'inactive');

        if (departmentOrGroup === 'management-level') {
            // --- GOGO UPDATE: Handle Management Level Checkbox ---
            const managementPositions = Object.keys(positionRanks).filter(p => positionRanks[p] < 20);
            employeesToSelect = activeFiltered.filter(emp =>
                emp.position && managementPositions.includes(emp.position)
            );

        } else if (departmentOrGroup === 'inactive-section') {
             // --- GOGO UPDATE: Handle Inactive Checkbox ---
             const inactiveFiltered = filteredData.filter(emp => emp.employment_status === 'inactive');
             employeesToSelect = inactiveFiltered;

        } else {
            // --- GOGO UPDATE: Updated Logic for Regular Departments ---
            // Select only from active, non-favorite employees matching the department
            const nonFavoriteActiveFiltered = activeFiltered.filter(emp => !favoriteEmployees.has(emp.id));
            employeesToSelect = nonFavoriteActiveFiltered.filter(emp =>
                getRelevantDepartment(emp) === departmentOrGroup
            );
        }

        // --- This part remains the same ---
        if (e.target.checked) {
            employeesToSelect.forEach(emp => selectedEmployees.add(emp.id));
        } else {
            employeesToSelect.forEach(emp => selectedEmployees.delete(emp.id));
        }
        filterAndSortData(); // Re-render to update checkbox state and selection indicator
        updateSelectionBar();
    }
});

    // --- START: MODIFIED CLICK LISTENER FOR HEADERS ---
    mainContent.addEventListener('click', (e) => {
        // --- Department/Management header collapse/expand ---
        // Changed selector to include .management-header
        const header = e.target.closest('.department-header, .management-header');

        // Added checks to prevent triggering collapse when clicking inner buttons
        if (header && !e.target.closest('.department-checkbox') && !e.target.closest('.print-btn') && !e.target.closest('.admin-controls') && !e.target.closest('.user-action-btn')) {
            const isManagementSection = header.dataset.section === 'management-level'; // Check if it's the Management header
            const department = header.dataset.department; // For regular departments
            const content = header.nextElementSibling; // The element to collapse/expand
            const chevron = header.querySelector('.chevron-icon'); // The chevron icon

            if (content) { // Ensure the content element exists
                if (isManagementSection) {
                    // --- Logic for Management Section ---
                    isManagementCollapsed = !isManagementCollapsed; // Toggle the global state variable
                    if (isManagementCollapsed) {
                        content.style.maxHeight = "0px";
                        content.style.opacity = 0;
                    } else {
                        content.style.maxHeight = content.scrollHeight + "px"; // Use scrollHeight for expand animation
                        content.style.opacity = 1;
                        // Optional: Reset max-height after animation for dynamic content
                        // setTimeout(() => { if (!isManagementCollapsed) content.style.maxHeight = 'none'; }, 500);
                    }
                    header.classList.toggle('collapsed', isManagementCollapsed); // Toggle 'collapsed' class on the header
                    if(chevron) chevron.classList.toggle('-rotate-90', isManagementCollapsed); // Rotate the chevron icon

                } else if (department) {
                    // --- Logic for Regular Departments (Existing) ---
                    const shouldCollapse = !collapsedDepartments.has(department);
                    if (shouldCollapse) {
                        collapsedDepartments.add(department);
                        content.style.maxHeight = "0px";
                        content.style.opacity = 0;
                    } else {
                        collapsedDepartments.delete(department);
                        content.style.maxHeight = content.scrollHeight + "px";
                        content.style.opacity = 1;
                        // Optional: Reset max-height after animation
                        // setTimeout(() => { if (!collapsedDepartments.has(department)) content.style.maxHeight = 'none'; }, 500);
                    }
                    header.classList.toggle('collapsed', shouldCollapse); // Toggle 'collapsed' class on the header
                    if(chevron) chevron.classList.toggle('-rotate-90', shouldCollapse); // Rotate the chevron icon
                }
            }
        }

        // --- Quick copy buttons ---
        if (e.target.closest('.quick-copy-btn')) {
            const textToCopy = e.target.closest('.quick-copy-btn').dataset.copy;
            if (textToCopy) {
                copyTextToClipboard(textToCopy, 'คัดลอกแล้ว!');
            }
        }

        // --- Print department button ---
        if (e.target.closest('.print-btn')) {
            const dept = e.target.closest('.print-btn').dataset.department;
            printDepartment(dept);
        }
    });
    // --- END: MODIFIED CLICK LISTENER FOR HEADERS ---

    // Selection mode toggle button
    document.getElementById('toggleSelectModeBtn').addEventListener('click', (e) => {
        const btn = e.currentTarget;
        const icon = btn.querySelector('i');
        isInSelectionMode = !isInSelectionMode;
        document.body.classList.toggle('selection-mode-active', isInSelectionMode);
        btn.classList.toggle('active', isInSelectionMode);
        if (isInSelectionMode) {
            icon.className = 'fa-solid fa-xmark fa-bounce';
            btn.title = "ยกเลิกโหมดเลือก";
        } else {
            icon.className = 'fa-regular fa-copy';
            btn.title = "เลือกรายการเพื่อคัดลอก";
            selectedEmployees.clear();
            filterAndSortData(); // Re-render to remove selection indicators
            updateSelectionBar();
        }
    });

    // --- START: MODIFIED Collapse/Expand all button ---
    document.getElementById('toggleCollapseAllBtn').addEventListener('click', e => {
        const allDeptHeaders = document.querySelectorAll('#departmentList .department-header'); // Select only regular department headers
        const managementHeader = document.querySelector('#departmentList .management-header'); // Select the management header separately

        if (allDeptHeaders.length === 0 && !managementHeader) return; // Exit if there are no headers at all

        // Determine if we should collapse or expand based on the state of the first regular department OR the management section
        let shouldCollapse;
        if (allDeptHeaders.length > 0) {
            const firstHeader = allDeptHeaders[0];
            const firstDept = firstHeader.dataset.department;
            shouldCollapse = !collapsedDepartments.has(firstDept); // If the first regular department is NOT collapsed, then collapse all
        } else if (managementHeader) {
            shouldCollapse = !isManagementCollapsed; // If only management exists, base decision on its state
        } else {
            return; // Should not happen based on the check above
        }

        // Update the state for all regular departments
        allDeptHeaders.forEach(header => {
            const dept = header.dataset.department;
            if (dept) { // Ensure it's a regular department header with data-department
                if (shouldCollapse) {
                    collapsedDepartments.add(dept);
                } else {
                    collapsedDepartments.delete(dept);
                }
            }
        });

        // Update the state for the management section
        isManagementCollapsed = shouldCollapse;

        // Re-render the data. The renderData function will use the updated states (collapsedDepartments and isManagementCollapsed).
        filterAndSortData();
    });
    // --- END: MODIFIED Collapse/Expand all button ---

    // Export Excel Button
    document.getElementById('exportExcelBtn').addEventListener('click', exportToExcel);

    // Export PDF Button
    document.getElementById('exportPdfBtn').addEventListener('click', exportToPDF);

    // Print All Button (New)
    const printAllBtn = document.getElementById('printAllBtn');
    if (printAllBtn) {
        printAllBtn.addEventListener('click', printDirectory);
    }

    // Copy Head Emails Button (with Debugging Logs)
    document.getElementById('copyHeadsMailBtn').addEventListener('click', () => {
        // Use positionRanks to determine heads (Rank <= 11: Assistant Manager and above)
        // This is more dynamic and maintainable than a hardcoded list
        const heads = employees.filter(emp => {
            if (emp.employment_status === 'inactive') return false;
            if (activeCompany !== 'All' && !emp.assignments.some(a => a.company === activeCompany)) return false;
            const rank = positionRanks[emp.position] || 99;
            return rank <= 11; // Adjust threshold as needed (11 = Asst Mgr)
        });

        if (heads.length === 0) {
            Swal.fire({ icon: 'info', title: 'ไม่พบข้อมูล', text: 'ไม่พบหัวหน้าแผนก (ที่ยังทำงานอยู่) ในกลุ่มที่เลือก' });
            return;
        }

        let headsToDisplay = [];

        heads.forEach(head => {
            let assignments = head.assignments;
            if (activeCompany !== 'All') {
                assignments = assignments.filter(a => a.company === activeCompany);
            }
            
            assignments.forEach(a => {
                if (a.email) {
                    headsToDisplay.push({ 
                        name: head.name, email: a.email, 
                        company: a.company, department: a.department, position: head.position 
                    });
                }
            });
        });

        // Filter out duplicate heads based on email before displaying
        const uniqueHeads = headsToDisplay.filter((head, index, self) =>
            index === self.findIndex(h => h.email === head.email)
        );

        if (uniqueHeads.length > 0) {
            showHeadsPreview(uniqueHeads);
        } else {
            Swal.fire({ icon: 'info', title: 'ไม่มีอีเมล', text: `ไม่พบอีเมลของหัวหน้าในบริษัท "${activeCompany}" หรือในทุกบริษัท` });
        }
    });

    // Selection bar buttons
    document.getElementById('copySelectedBtn').addEventListener('click', () => {
        if (selectedEmployees.size === 0) return;
        const selectedEmps = employees.filter(e => selectedEmployees.has(e.id));
        let emails;
        if (activeCompany === 'All') {
            emails = [...new Set(selectedEmps.flatMap(e => e.assignments.map(a => a.email)).filter(Boolean))];
        } else {
            emails = [...new Set(selectedEmps.flatMap(e => e.assignments.filter(a => a.company === activeCompany).map(a => a.email)).filter(Boolean))];
        }
        if (emails.length > 0) {
            copyTextToClipboard(emails.join('; '), `คัดลอก ${emails.length} อีเมล (${activeCompany === 'All' ? 'ทั้งหมด' : activeCompany}) แล้ว!`);
        } else {
            Swal.fire({ icon: 'info', title: 'ไม่มีอีเมล', text: `ไม่พบอีเมลที่ตรงกับบริษัท "${activeCompany}" ในรายชื่อที่เลือก` });
        }
    });
    document.getElementById('deselectAllBtn').addEventListener('click', () => {
        selectedEmployees.clear();
        filterAndSortData();
        updateSelectionBar();
    });
    document.getElementById('selectionBar').addEventListener('click', (e) => {
        if (e.target.matches('.remove-tag-btn')) {
            const employeeIdToRemove = e.target.dataset.id;
            if (employeeIdToRemove && selectedEmployees.has(employeeIdToRemove)) {
                selectedEmployees.delete(employeeIdToRemove);
                filterAndSortData(); // Re-render to remove selection indicator
                updateSelectionBar();
            }
        }
    });

    // Other buttons
    document.getElementById('showHelpBtn').addEventListener('click', showHelpModal);
    document.getElementById('saveDeptPhonesBtn').addEventListener('click', saveDepartmentPhones);

    // Initialize Dropzone functionality
    setupDropzone();
}
// Function to open any modal
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
    } else {
        console.error('Modal element not found:', modalId); // เพิ่ม log เผื่อหา id ไม่เจอ
    }
}

// Function to close any modal
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
    } else {
        console.error('Modal element not found:', modalId);
    }
}
// Function to load last updated timestamp
async function loadLastUpdatedTimestamp() {
    const timestampEl = document.getElementById('lastUpdatedTimestamp');
    try {
        const response = await fetch(`api.php?action=get_last_update_timestamp&v=${Date.now()}`);
        const result = await response.json();
        if (result.status === 'success' && result.last_update) {
            const latestDate = new Date(result.last_update);
             const formattedDate = latestDate.toLocaleDateString('th-TH', {
                year: 'numeric', month: 'long', day: 'numeric',
                hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Bangkok'
            });
            timestampEl.textContent = `${formattedDate} น.`;
        } else {
             timestampEl.textContent = 'ไม่สามารถโหลดได้';
        }
    } catch (error) {
        console.error("Failed to load timestamp:", error);
        timestampEl.textContent = 'เกิดข้อผิดพลาด';
    }
}

// Function to load employee data
async function loadEmployees() {
    const gridSkeleton = document.getElementById('skeletonGrid');
    const listContainer = document.getElementById('departmentList');
    // const gridContainer = document.getElementById('departmentGrid'); // Removed

    listContainer.classList.add('hidden');
    // gridContainer.classList.add('hidden'); // Removed
    gridSkeleton.classList.remove('hidden');
    gridSkeleton.innerHTML = Array(6).fill('<div class="bg-white rounded-lg shadow p-6"><div class="flex items-center gap-4"><div class="skeleton w-12 h-12 rounded-full"></div><div class="flex-1 flex flex-col gap-3"><div class="skeleton h-4 w-3/5 rounded"></div><div class="skeleton h-3 w-2/5 rounded"></div><div class="skeleton h-3 w-4/5 rounded"></div></div></div>').join('');

    try {
        const sess = await getSessionInfo();
        const usePublicEndpoint = !sess.loggedin || sess.role !== 'admin';
        const endpoint = usePublicEndpoint
            ? `api.php?action=get_public_directory&status_filter=active&v=${Date.now()}`
            : `api.php?action=get&file=employees&status_filter=all&v=${Date.now()}`;
        const response = await fetch(endpoint);
        if (!response.ok) {
            throw new Error(`HTTP error ${response.status}`);
        }
        employees = await response.json();
        if (!Array.isArray(employees)) {
            employees = [];
        }
    } catch (error) {
        console.error("Failed to load data from API:", error);
        document.getElementById('noResults').textContent = 'โหลดข้อมูลล้มเหลว กรุณาลองอีกครั้ง';
        employees = []; // Ensure employees is an empty array on error
    } finally {
        // Collapse all departments by default on load
        const allDepts = [...new Set(employees.map(emp => getRelevantDepartment(emp) || 'Uncategorized'))];
        allDepts.forEach(dept => collapsedDepartments.add(dept));
        collapsedDepartments.add('inactive-section'); // Also collapse inactive section

        // Always show collapse button (since it's always list view)
        document.querySelector('#toggleCollapseAllBtn').style.display = 'inline-flex';

        renderCompanyFilters();
        filterAndSortData(); // This will call renderData
        checkAdminStatusAndSetupUI();

        gridSkeleton.classList.add('hidden');
        listContainer.classList.remove('hidden'); // Always show list container
        // document.getElementById(currentView === 'grid' ? 'departmentGrid' : 'departmentList').classList.remove('hidden'); // Replaced
    }
}


// Function to load department phone numbers
async function loadDepartmentPhones() {
    try {
        const response = await fetch(`api.php?action=get_department_phones&v=${Date.now()}`);
        const result = await response.json();
        if (result.status === 'success') {
            departmentPhones = result.data;
        } else {
            console.error("Failed to load department phones:", result.message);
            departmentPhones = {}; // Ensure it's an object on error
        }
    } catch (error) {
        console.error("Error fetching department phones:", error);
        departmentPhones = {};
    }
}

// Function to open the view employee modal
function openViewModal(employeeId) {
    const employee = employees.find(emp => emp.id === employeeId);
    if (!employee) {
        console.error("Employee not found for ID:", employeeId);
        return; // Exit if employee data not found
    }

    const modalContainer = document.getElementById('viewModal');
    if (!modalContainer) {
        console.error("Modal container 'viewModal' not found.");
        return; // Exit if modal container is missing
    }

    // --- ส่วนที่ดึงข้อมูลจำเป็นสำหรับสร้าง HTML ---
    const primaryAssignment = employee.assignments.find(a => a.is_primary == 1) || employee.assignments[0] || {};
    const imageUrl = employee.image ? `uploads/employees/${employee.image}` : '';
    const imageHtml = employee.image
        ? `<img src="${imageUrl}" class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-lg -mt-12">`
        : `<div class="w-24 h-24 bg-gray-200 rounded-full flex items-center justify-center border-4 border-white shadow-lg -mt-12"><span class="text-3xl font-medium text-gray-600">${getInitials(employee.name)}</span></div>`;
    const primaryEmail = primaryAssignment.email || 'N/A';
    const primaryPhone = employee.phone || 'N/A';
    // 🎨 GOGO UPDATE: ปรับแต่งรายการอีเมล (แก้ปัญหาอีเมลยาวล้นจอ + จัดป้ายบริษัทใหม่)
    const allEmailsHtml = employee.assignments.map(a => {
        if (!a.email) return '';
        const companyAbbr = a.company || 'N/A';
        const companyClass = getCompanyClass(a.company); // ใช้ฟังก์ชันเดิมดึงสี
        
        // เช็คว่าเป็นอีเมลหลักไหม ถ้าใช่ใส่ดาว ★
        const primaryBadge = a.is_primary == 1 ? '<i class="fas fa-star text-yellow-400 text-[10px] ml-1"></i>' : ''; 

        return `
        <div class="group flex flex-col sm:flex-row sm:items-baseline gap-1 mb-2 last:mb-0" onclick="copyTextToClipboard('${a.email}', 'คัดลอกอีเมลแล้ว!')">
            <span class="email-address text-blue-600 dark:text-blue-400 font-medium hover:underline cursor-pointer break-all leading-tight transition-colors">
                ${a.email}
            </span>
            
            <div class="flex-shrink-0">
                <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide border ${companyClass} border-current opacity-70 group-hover:opacity-100 transition-opacity select-none">
                    ${companyAbbr}${primaryBadge}
                </span>
            </div>
        </div>`;
    }).join('');
    const phoneRowHtml = primaryPhone !== 'N/A'
        ? `<div class="flex items-center text-sm font-semibold phone-text-color"><i class="fas fa-phone fa-fw w-4 mr-2 text-gray-500"></i> ${primaryPhone}</div>`
        : '';
    const idRowHtml = employee.id
    ? `<div class="flex items-center text-sm font-semibold text-gray-800 dark:text-gray-100"><i class="far fa-id-card fa-fw w-4 mr-2 text-gray-500"></i> ID: ${employee.id}</div>`
    : '';
    const nameThHtml = employee.name_th
        ? `<p class="text-sm font-semibold text-gray-500">${employee.name_th}</p>`
        : '';
// --- 🔽 GOGO UPDATE: สร้าง QR Code 🔽 ---
// สร้างข้อความสำหรับ QR Code (Format: MECARD) เพื่อให้มือถือสแกนแล้วเมมเบอร์ได้เลย
const qrName = employee.name || '';
const qrPhone = employee.phone || '';
const qrEmail = primaryAssignment.email || '';
// สร้าง String แบบ MECARD (รูปแบบมาตรฐานนามบัตรแบบย่อ)
const mecardString = `MECARD:N:${qrName};TEL:${qrPhone};EMAIL:${qrEmail};;`;
const encodedQrData = encodeURIComponent(mecardString);
const qrCodeUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodedQrData}`;

// ==================================================================================
    // 🎨 GOGO UPDATE: REDESIGNED MODAL LAYOUT (ส่วนดีไซน์ใหม่)
    // ==================================================================================

    // --- 1. กำหนดธีมสี Gradient ตามบริษัท ---
    let gradientClass = 'bg-gradient-to-r from-gray-500 to-gray-300';
    let accentColorClass = 'text-gray-600 dark:text-gray-400';

    if (primaryAssignment && primaryAssignment.company) {
        const companyAbbr = primaryAssignment.company.toUpperCase();
        if (companyAbbr === 'PTA') {
            gradientClass = 'bg-gradient-to-r from-emerald-600 to-emerald-400'; // PTA สีเขียว
            accentColorClass = 'text-emerald-600 dark:text-emerald-400';
        } else if (companyAbbr === 'PT4') {
            gradientClass = 'bg-gradient-to-r from-red-600 to-red-400'; // PT4 สีแดง
            accentColorClass = 'text-red-600 dark:text-red-400';
        } else if (companyAbbr === 'PTE') {
            gradientClass = 'bg-gradient-to-r from-blue-600 to-blue-400'; // PTE สีฟ้า
            accentColorClass = 'text-blue-600 dark:text-blue-400';
        }
    }

    // Birthday Message Logic
    const isBirthdayToday = isBirthday(employee.birthdate);
    const birthdayMessageHtml = isBirthdayToday 
        ? `<div class="mb-2">
             <span class="text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 via-orange-500 to-red-500 drop-shadow-sm animate-pulse">
               🎉 Happy Birthday! 🎂
             </span>
           </div>` 
        : '';

    const serviceYear = calculateServiceYear(employee.start_date);
    const serviceYearBadge = serviceYear 
        ? `<div class="mt-3 inline-flex items-center px-3 py-1 rounded-full bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-100 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 text-xs font-medium">
             <i class="fas fa-medal mr-1.5 text-indigo-500"></i> อายุงาน ${serviceYear}
           </div>` 
        : '';

    // --- 1.5 สร้าง HTML สำหรับแสดงสังกัดทั้งหมด (Work Details) ---
    const assignmentsListHtml = employee.assignments.map(a => {
        const isPrimary = a.is_primary == 1;
        const companyClass = getCompanyClass(a.company); // ใช้ helper class สีตามบริษัท
        const borderClass = isPrimary ? 'border-indigo-500' : 'border-gray-300 dark:border-gray-600';
        const primaryLabel = isPrimary ? '<span class="text-[10px] bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded ml-2">Primary</span>' : '';

        return `
        <div class="pl-3 border-l-2 ${borderClass} mb-3 last:mb-0">
            <div class="text-xs text-gray-400 mb-0.5">Company / Dept ${primaryLabel}</div>
            <div class="font-bold text-sm ${companyClass}">${a.company || '-'}</div>
            <div class="text-sm font-medium text-gray-700 dark:text-gray-200">${a.department || '-'}</div>
        </div>`;
    }).join('') || '<div class="text-sm text-gray-500">ไม่ระบุสังกัด</div>';

    // --- 2. สร้าง HTML โครงสร้างใหม่ ---
    modalContainer.innerHTML = `
    <div class="bg-white dark:bg-gray-800 rounded-xl w-full max-w-2xl mx-4 shadow-2xl overflow-hidden flex flex-col max-h-[90vh] animate-fade-in-up">
        
        <div class="overflow-y-auto"> <div class="relative h-32 ${gradientClass}">
                <button type="button" class="absolute top-4 right-4 text-white/80 hover:text-white focus:outline-none transition-colors bg-black/20 hover:bg-black/40 rounded-full p-2 z-10" onclick="closeModal('viewModal')">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <div class="absolute bottom-2 right-4 text-white/20 text-5xl font-black select-none pointer-events-none">
                    ${primaryAssignment.company || ''}
                </div>
            </div>

            <div class="relative px-6 pb-8 -mt-12">

                <div class="flex flex-col sm:flex-row justify-between items-end gap-6 mb-8">
                    <div class="flex flex-col sm:flex-row items-center sm:items-end gap-5 w-full">
                        <div class="relative group">
                            <div class="border-4 border-white dark:border-gray-800 rounded-full shadow-md bg-white overflow-hidden h-32 w-32 flex-shrink-0">
                                ${employee.image 
                                    ? `<a href="uploads/employees/${employee.image}" class="employee-modal-lightbox" title="${employee.name}"><img src="uploads/employees/${employee.image}" class="h-full w-full object-cover"></a>` 
                                    : `<div class="h-full w-full flex items-center justify-center bg-gray-100 text-gray-400 text-3xl font-bold">${getInitials(employee.name)}</div>`
                                }
                            </div>
                            ${isBirthdayToday ? `<div class="absolute -top-6 -right-4 text-4xl text-yellow-400 z-20 animate-bounce" style="animation-duration: 2s; filter: drop-shadow(0 0 12px rgba(250, 204, 21, 0.9));"><i class="fas fa-crown"></i></div>` : ''}
                        </div>
                        
                        <div class="text-center sm:text-left mb-2">
                            ${birthdayMessageHtml}
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white leading-tight">${employee.name}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">${employee.name_th || ''}</p>
                            <div class="flex flex-wrap justify-center sm:justify-start items-center gap-2 mt-2 w-full">
                                <span class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 ${accentColorClass} text-xs font-bold uppercase tracking-wider border border-gray-200 dark:border-gray-600">
                                    ${employee.position || 'N/A'}
                                </span>
                                ${employee.id ? `<span class="text-gray-400 text-xs flex items-center"><i class="far fa-id-card mr-1"></i>${employee.id}</span>` : ''}
                            </div>
                            ${serviceYearBadge}
                        </div>
                    </div>

                    <div class="hidden sm:block flex-shrink-0 group relative self-center sm:self-end">
                        <div class="bg-white p-1 rounded-lg shadow-sm border border-gray-200">
                            <img src="${qrCodeUrl}" alt="Contact QR" class="w-24 h-24 rounded cursor-pointer hover:opacity-90 transition-opacity">
                        </div>
                        <div class="absolute -top-8 left-1/2 transform -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity text-[10px] text-white bg-gray-800 px-2 py-1 rounded shadow whitespace-nowrap pointer-events-none">
                            สแกนเมมเบอร์
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-5 border border-gray-100 dark:border-gray-700">
                        <h3 class="text-xs font-bold text-gray-400 uppercase mb-4 tracking-wider flex items-center gap-2">
                            <i class="fas fa-address-book"></i> Contact Info
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-start gap-3">
                                <div class="w-8 flex justify-center text-gray-400 mt-0.5"><i class="fas fa-phone-alt"></i></div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-200">${primaryPhone}</div>
                                    <div class="text-xs text-gray-500">เบอร์โทรศัพท์</div>
                                </div>
                                <a href="tel:${primaryPhone}" class="text-green-500 hover:text-green-600 p-1"><i class="fas fa-phone-square-alt text-xl"></i></a>
                            </div>
                            
                            <div class="flex items-start gap-3">
                                <div class="w-8 flex justify-center text-gray-400 mt-1"><i class="fas fa-envelope"></i></div>
                                <div class="flex-1 space-y-2 min-w-0">
                                    ${allEmailsHtml}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-5 border border-gray-100 dark:border-gray-700 flex flex-col justify-between">
                        <div>
                            <h3 class="text-xs font-bold text-gray-400 uppercase mb-4 tracking-wider flex items-center gap-2">
                                <i class="fas fa-building"></i> Work Details
                            </h3>
                            <div class="space-y-4">
                                <!-- แสดงรายการสังกัดทั้งหมด -->
                                <div class="max-h-40 overflow-y-auto custom-scrollbar pr-1">
                                    ${assignmentsListHtml}
                                </div>
                                <div>
                                    <div class="text-xs text-gray-400 mb-1">Start Date</div>
                                    <div class="font-semibold text-gray-700 dark:text-gray-200">
                                        ${employee.start_date ? new Date(employee.start_date).toLocaleDateString('th-TH', {year: 'numeric', month: 'long', day: 'numeric'}) : '-'}
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex gap-2">
                             <a href="mailto:${primaryEmail}" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg text-sm font-medium transition shadow-sm hover:shadow flex items-center justify-center gap-2">
                                <i class="fas fa-paper-plane"></i> Email
                             </a>
                             <a href="tel:${primaryPhone}" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg text-sm font-medium transition shadow-sm hover:shadow flex items-center justify-center gap-2">
                                <i class="fas fa-phone"></i> Call
                             </a>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>`;

    // --- แสดง Modal และอัปเดต UI อื่นๆ ---
    modalContainer.classList.remove('hidden');
    checkAdminStatusAndSetupUI(); // ตรวจสอบสถานะ Admin เพื่อแสดง/ซ่อนปุ่มที่เกี่ยวข้อง (ถ้ามีใน Modal นี้)
    new SimpleLightbox('.employee-modal-lightbox', { sourceAttr: 'href', overlay: true, showCounter: false });
}

// Function to open the add/edit employee modal
function openEditModal(employeeId = null) {
    isEditing = !!employeeId;
    const form = document.getElementById('employeeForm'); form.reset();
    const modalTitle = document.getElementById('modalTitle');
    const idInput = document.getElementById('form-id');
    const assignmentsContainer = document.getElementById('assignmentsContainer'); assignmentsContainer.innerHTML = '';
    modalTitle.textContent = isEditing ? 'แก้ไขข้อมูลพนักงาน' : 'เพิ่มพนักงานใหม่';
    idInput.disabled = isEditing; // Disable ID field when editing

    const employee = isEditing ? employees.find(emp => emp.id === employeeId) : {};
    if (isEditing && !employee) {
        console.error('Employee not found for editing:', employeeId);
        return; // Don't open modal if employee data is missing
    }

    // Populate basic fields
    idInput.value = employee?.id || '';
    console.log("Employee Data:", employee); // เช็คข้อมูลที่ได้จาก API ว่ามี birthdate หรือ name_th หรือไม่
    const fieldMap = {
        'name': 'form-name',
        'name_th': 'form-name-th',
        'position': 'form-position',
        'phone': 'form-phone',
        'birthdate': 'form-birthdate',
        'start_date': 'form-start-date'
    };
    Object.entries(fieldMap).forEach(([key, elementId]) => {
        const el = document.getElementById(elementId);
        if(el) {
            let val = employee?.[key] || '';
            if(key === 'birthdate' && String(val).includes(' ')) val = String(val).split(' ')[0]; // แปลงเป็น String ก่อนตัดเวลา
            if(key === 'start_date' && String(val).includes(' ')) val = String(val).split(' ')[0];
            el.value = val;
        }
    });
    document.getElementById('form-status').value = employee?.employment_status || 'active';

    // Reset Dropzone and display existing image if available
    const dropzoneLabel = document.getElementById('dropzone-label');
    const previewImage = document.getElementById('currentImagePreview');
    const filenameText = document.getElementById('image-filename');
    const fileInput = document.getElementById('form-image-upload');

    if (dropzoneLabel && previewImage && filenameText && fileInput) {
        fileInput.value = null;     // Reset <input>
        selectedImageFile = null; // Reset stored file variable

        if (employee?.image) {
            previewImage.src = `uploads/employees/${employee.image}`;
            dropzoneLabel.classList.add('has-preview');
            filenameText.textContent = employee.image;
        } else {
            previewImage.src = '';
            dropzoneLabel.classList.remove('has-preview');
            filenameText.textContent = '';
        }
    }

    // Populate assignments
    (employee?.assignments?.length ? employee.assignments : [{ is_primary: 1 }]).forEach(addAssignmentRow);

    // Populate admin fields if admin and editing
    document.getElementById('admin-credential-section').style.display = (isAdmin || isStaff) ? 'block' : 'none';
    if (isEditing && (isAdmin || isStaff)) {
        document.getElementById('form-username').value = employee?.username || '';
        document.getElementById('form-role').value = employee?.role || 'user';
        document.getElementById('form-password').value = ''; // Always clear password field
    }

    document.getElementById('editModal').classList.remove('hidden');
}

// Function to close any modal
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Function to handle form submission for adding/editing employees
async function handleFormSubmit(event) {
    event.preventDefault();
    const employeeId = document.getElementById('form-id').value;
    // Prevent duplicate ID submission when adding new employee
    if (!isEditing && employees.some(emp => emp.id === employeeId)) {
        Swal.fire({ icon: 'error', title: 'ID ซ้ำ', text: 'Employee ID นี้มีอยู่แล้วในระบบ' });
        return;
    }

    // --- START: Validation ---
    // 1. Validate Birthdate (cannot be in the future)
    const birthdateValue = document.getElementById('form-birthdate').value;
    if (birthdateValue) {
        const birthdate = new Date(birthdateValue);
        const today = new Date();
        today.setHours(0, 0, 0, 0); // Compare date part only
        if (birthdate > today) {
            Swal.fire({ icon: 'error', title: 'ข้อมูลไม่ถูกต้อง', text: 'วันเกิดต้องไม่เป็นวันที่ในอนาคต' });
            return;
        }
    }

    // 2. Validate Primary Assignment (at least one must be checked if assignments exist)
    const assignmentRows = document.querySelectorAll('.assignment-row');
    const primaryAssignmentsChecked = document.querySelectorAll('.assignment-primary:checked');
    if (assignmentRows.length > 0 && primaryAssignmentsChecked.length === 0) {
        Swal.fire({ icon: 'error', title: 'ข้อมูลไม่สมบูรณ์', text: 'กรุณากำหนดสังกัดหลัก (Primary) อย่างน้อย 1 รายการ' });
        return;
    }
    // --- END: Validation ---

    const form = document.getElementById('employeeForm');
    const formData = new FormData(form);

    // Manually handle the image file from our stored variable
    formData.delete('imageFile'); // Remove any file potentially picked up by FormData
    if (selectedImageFile) {
        // Append the file stored in selectedImageFile if it exists
        formData.append('imageFile', selectedImageFile, selectedImageFile.name);
    }

    // Append action and ID
    formData.append('action', isEditing ? 'update_employee' : 'add_employee');
    if (isEditing) {
        formData.append('id', employeeId);
    }

    // Process and append assignments
    const assignments = Array.from(document.querySelectorAll('.assignment-row')).map(row => ({
        company: row.querySelector('.assignment-company').value,
        department: row.querySelector('.assignment-department').value,
        email: row.querySelector('.assignment-email').value.trim(), // Trim email
        is_primary: row.querySelector('.assignment-primary').checked ? 1 : 0,
    })).filter(a => a.company); // Ensure company is selected
    formData.append('assignments', JSON.stringify(assignments));

    // --- Add console log to see what's being sent ---
    console.log('--- Submitting Form Data ---');
    for (let [key, value] of formData.entries()) {
        console.log(`${key}:`, value);
    }
    // --- End console log ---

    try {
        const response = await fetch('api.php', { method: 'POST', body: formData });
        const result = await response.json();
        console.log('API Response:', result); // Log API response

        if (!response.ok || result.status !== 'success') {
            throw new Error(result.message || 'Save failed');
        }

        await Promise.all([loadEmployees(), loadLastUpdatedTimestamp()]); // Reload data
        closeModal('editModal');
        Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ!', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });

    } catch (error) {
        console.error('Form Submit Error:', error); // Log detailed error
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: error.message });
    }
}

// Function to set up Dropzone functionality
function setupDropzone() {
    const dropzoneLabel = document.getElementById('dropzone-label');
    const fileInput = document.getElementById('form-image-upload');
    const previewImage = document.getElementById('currentImagePreview');
    const promptText = document.getElementById('dropzone-prompt');
    const filenameText = document.getElementById('image-filename');

    if (!dropzoneLabel || !fileInput || !previewImage || !promptText || !filenameText) {
        console.error('Dropzone elements not found. Cannot initialize.');
        return;
    }

    const showPreview = (file) => {
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImage.src = e.target.result;
                dropzoneLabel.classList.add('has-preview');
                filenameText.textContent = file.name;
            };
            reader.readAsDataURL(file);
            selectedImageFile = file; // Store the valid file
        } else {
            // Reset if the file is not an image or no file
            previewImage.src = '';
            dropzoneLabel.classList.remove('has-preview');
            filenameText.textContent = file ? 'กรุณาเลือกไฟล์รูปภาพ (JPG, PNG)' : ''; // Show error or clear
            selectedImageFile = null; // Clear stored file
            fileInput.value = null;   // Clear the hidden input value
        }
    };

    // Handle file selection via click
    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        showPreview(file);
    });

    // Handle drag over
    dropzoneLabel.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzoneLabel.classList.add('dragover');
    });

    // Handle drag leave
    dropzoneLabel.addEventListener('dragleave', () => {
        dropzoneLabel.classList.remove('dragover');
    });

    // Handle drop
    dropzoneLabel.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzoneLabel.classList.remove('dragover');
        const file = e.dataTransfer.files[0];
        if (file) {
            // IMPORTANT: Set the files for the hidden input
            // This might still be needed if some part relies on it, though we manually append later
            try {
                 const dataTransfer = new DataTransfer();
                 dataTransfer.items.add(file);
                 fileInput.files = dataTransfer.files;
            } catch (err) {
                 console.warn("Could not set fileInput.files directly (might be ok).", err);
                 // If direct assignment fails, it's okay because we use selectedImageFile
            }
            showPreview(file); // This will store the file in selectedImageFile
        }
    });
}

// Function to delete an employee
async function deleteEmployee(employeeId) {
    const employee = employees.find(emp => emp.id === employeeId);
    if (!employee) {
        Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: 'ไม่พบข้อมูลพนักงานที่จะลบ' });
        return;
    }

    const { isConfirmed } = await Swal.fire({
        title: `ลบข้อมูล ${employee.name}?`,
        text: "การกระทำนี้จะลบข้อมูลสังกัดทั้งหมดของพนักงานคนนี้ด้วย ไม่สามารถย้อนกลับได้!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonText: 'ยกเลิก',
        confirmButtonText: 'ใช่, ลบเลย!'
    });

    if (isConfirmed) {
        const formData = new FormData();
        formData.append('action', 'delete_item');
        formData.append('key', 'employees');
        formData.append('id', employeeId);

        try {
            const response = await fetch(`api.php`, { method: 'POST', body: formData });
            const res = await response.json();
            if (!response.ok || res.status !== 'success') {
                throw new Error(res.message || 'Delete failed');
            }
            // Reload data after successful deletion
            await Promise.all([loadEmployees(), loadLastUpdatedTimestamp()]);
            Swal.fire('ลบแล้ว!', `ข้อมูลของ ${employee.name} ถูกลบแล้ว`, 'success');
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: error.message });
        }
    }
}

// Function to handle user logout
async function logout() {
    try {
        await fetch('api.php?action=logout');
        isAdmin = false;
        checkAdminStatusAndSetupUI(); // Update UI after logout
        Swal.fire({ icon: 'success', title: 'ออกจากระบบสำเร็จ', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
    } catch (error) {
        console.error("Logout failed:", error);
        // Optionally show an error message
    }
}

// Function to check admin status via API and update UI elements
async function checkAdminStatusAndSetupUI() {
    try {
        const session = await getSessionInfo();
        const userRole = session.role || 'user';
        isAdmin = (session.loggedin && userRole === 'admin');
        isStaff = (session.loggedin && userRole === 'staff');

        if (!session.loggedin) {
            document.body.classList.add('guest-mode');
        } else {
            document.body.classList.remove('guest-mode');
        }

        // Toggle visibility based on admin status
        document.querySelectorAll('.admin-only').forEach(el => {
            // Use 'inline-flex' for buttons/links, 'block' for sections like credentials
            const displayStyle = (el.tagName === 'DIV' || el.tagName === 'SECTION') ? 'block' : 'inline-flex';
            el.style.display = isAdmin ? displayStyle : 'none';
        });
        document.querySelectorAll('.staff-only').forEach(el => {
            const displayStyle = (el.tagName === 'DIV' || el.tagName === 'SECTION') ? 'block' : 'inline-flex';
            if(isStaff) el.style.display = displayStyle;
        });

        // Toggle login/logout button visibility
        document.getElementById('loginBtn').style.display = session.loggedin ? 'none' : 'flex';
        const userInfo = document.getElementById('userInfo');
        if(session.loggedin) {
            userInfo.style.display = 'flex';
            const roleClass = session.role === 'admin' ? 'text-green-500' : (session.role === 'staff' ? 'text-blue-500' : 'text-gray-500');
            userInfo.querySelector('span').innerHTML = `<i class="fas fa-user-shield ${roleClass}"></i> ${session.display_name || session.username}`;
        } else {
            userInfo.style.display = 'none';
        }
    } catch (error) {
        console.error("Failed to check session status:", error);
        // Default to non-admin state on error
        isAdmin = false;
        isStaff = false;
        document.body.classList.add('guest-mode');
        document.querySelectorAll('.admin-only').forEach(el => el.style.display = 'none');
        document.getElementById('loginBtn').style.display = 'flex';
        document.getElementById('userInfo').style.display = 'none';
    }
}

async function getSessionInfo() {
    if (sessionInfoCache) return sessionInfoCache;
    try {
        const response = await fetch('api.php?action=check_session', { cache: 'no-store' });
        const session = await response.json();
        sessionInfoCache = session || { loggedin: false };
    } catch (error) {
        sessionInfoCache = { loggedin: false, role: 'guest' };
    }
    return sessionInfoCache;
}

// Function to update the selection bar at the bottom
function updateSelectionBar() {
    const bar = document.getElementById('selectionBar');
    const countEl = document.getElementById('selectionCount');
    const detailsEl = document.getElementById('selectionDetails');
    const statusEl = document.getElementById('selectionStatus');
    const count = selectedEmployees.size;

    bar.classList.toggle('translate-y-full', count === 0); // Hide if count is 0
    countEl.textContent = count;

    if (count > 0) {
        statusEl.classList.remove('hidden');
        statusEl.textContent = activeCompany === 'All' ? 'เลือกจาก: ทุกบริษัท' : `เลือกจาก: ${activeCompany}`;

        // Get details of selected employees
        const selectedEmps = employees.filter(e => selectedEmployees.has(e.id));
        let emailsToShow = [];

        // Collect relevant emails based on active company filter
        if (activeCompany === 'All') {
            emailsToShow = selectedEmps.flatMap(e => e.assignments.map(a => ({
                email: a.email,
                company: a.company,
                employeeId: e.id
            }))).filter(item => item.email);
        } else {
            emailsToShow = selectedEmps.map(e => {
                const assignment = e.assignments.find(a => a.company === activeCompany);
                return {
                    email: assignment?.email,
                    company: assignment?.company,
                    employeeId: e.id
                };
            }).filter(item => item.email);
        }

        // Generate HTML for email tags
        detailsEl.innerHTML = emailsToShow.map(item => {
            const companyClass = item.company ? `email-tag-${item.company.toLowerCase()}` : '';
            const emailUser = item.email.split('@')[0];
            // Add tooltip to the tag showing full email
            return `<span class="email-tag ${companyClass} has-tooltip" data-tooltip-text="${item.email}">
                        ${emailUser}
                        <button class="remove-tag-btn" data-id="${item.employeeId}" title="นำออก">&times;</button>
                    </span>`;
        }).join('');
    } else {
        detailsEl.innerHTML = '';
        statusEl.classList.add('hidden');
    }
}

// Utility function to copy text to clipboard
function copyTextToClipboard(text, successMessage) {
    if (!navigator.clipboard) {
        // Fallback for older browsers
        const textArea = document.createElement("textarea");
        textArea.value = text;
        Object.assign(textArea.style, { position: 'fixed', top: '-9999px', left: '-9999px' });
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            Swal.fire({ icon: 'success', title: successMessage, toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถคัดลอกได้ (เบราว์เซอร์เก่า)' });
        }
        document.body.removeChild(textArea);
        return;
    }
    // Modern way using Clipboard API
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({ icon: 'success', title: successMessage, toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
    }).catch(err => {
        console.error('Failed to copy text: ', err);
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถคัดลอกได้' });
    });
}

// --- START: MODIFIED FUNCTION (Remove NOTE) ---
// Function to export employee data as vCard (VCF) file
function exportVCard(employeeId) {
    const employee = employees.find(emp => emp.id === employeeId);
    if (!employee) {
        Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: 'ไม่พบข้อมูลพนักงาน' });
        return;
    }

    const primaryAssignment = employee.assignments.find(a => a.is_primary == 1) || employee.assignments[0] || {};

    // Helper to escape characters for vCard
    const escapeVCard = (str) => str ? str.replace(/\\/g, '\\\\').replace(/;/g, '\\;').replace(/,/g, '\\,').replace(/\n/g, '\\n') : '';

    // Construct vCard string (Version 3.0)
    let vCard = `BEGIN:VCARD\nVERSION:3.0\n`;
    vCard += `N:${escapeVCard((employee.name || '').split(' ').reverse().join(';'))}\n`; // Last Name;First Name
    vCard += `FN:${escapeVCard(employee.name || '')}\n`; // Full Name

    // Combine Primary Company/Department/Position
    let orgParts = [];
    if (primaryAssignment.company) orgParts.push(escapeVCard(primaryAssignment.company));
    if (primaryAssignment.department) orgParts.push(escapeVCard(primaryAssignment.department));
    if (orgParts.length > 0) vCard += `ORG:${orgParts.join(';')}\n`; // Organization;Department

    if (employee.position) vCard += `TITLE:${escapeVCard(employee.position)}\n`; // Job Title

    if (employee.phone) vCard += `TEL;TYPE=WORK,VOICE:${escapeVCard(employee.phone)}\n`; // Work Phone

    // Add all emails
    employee.assignments.forEach((assignment, index) => {
        if (assignment.email) {
            let emailType = 'EMAIL;TYPE=WORK';
            if (assignment.is_primary == 1) {
                emailType += ',PREF'; // Preferred email
            }
            vCard += `${emailType}:${escapeVCard(assignment.email)}\n`;
        }
    });

    // NOTE field removed entirely
    // if (employee.id) {
    //     vCard += `NOTE:Employee ID: ${escapeVCard(employee.id)}\n`; // Add Employee ID to notes
    // }

    vCard += `END:VCARD`;

    // Create blob and download link
    const blob = new Blob([vCard], { type: "text/vcard;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    // Sanitize filename
    const safeName = (employee.name || 'contact').replace(/[^a-z0-9]/gi, '_').toLowerCase();
    link.download = `${safeName}.vcf`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
// --- END: MODIFIED FUNCTION ---


// Function to show the modal for previewing and copying head emails
function showHeadsPreview(heads) { // Expects unique heads list
    const modal = document.getElementById('headsPreviewModal');
    const title = document.getElementById('headsModalTitle');
    const content = document.getElementById('headsModalContent');
    const confirmBtn = document.getElementById('confirmCopyHeadsBtn');
    const composeBtn = document.getElementById('composeHeadsMailBtn');
    const downloadBtn = document.getElementById('downloadHeadsCsvBtn');
    const companyFilter = document.getElementById('companyFilterModal');
    const departmentFilter = document.getElementById('departmentFilterModal');
    const positionFilter = document.getElementById('positionFilterModal');
    const searchFilter = document.getElementById('searchFilterModal');
    const selectAllBtn = document.getElementById('selectAllHeadsBtn');
    const deselectAllBtn = document.getElementById('deselectAllHeadsBtn');
    const selectedCountSpan = document.getElementById('selectedHeadsCount');

    // Use a copy of the unique heads list to allow removal within the modal
    let currentHeadsInModal = [...heads];
    let filteredHeadsInModal = []; // To store the list after filtering in modal
    let currentModalFilters = { company: 'all', department: 'all', position: 'all', search: '' };

    function populateModalFilters() {
        // Filter based on company selection *within the modal* first
        const tempHeads = currentHeadsInModal.filter(h => (currentModalFilters.company === 'all' || h.company === currentModalFilters.company));

        // Calculate counts for Companies (based on full list)
        const companyCounts = currentHeadsInModal.reduce((acc, h) => { acc[h.company] = (acc[h.company] || 0) + 1; return acc; }, {});
        const companies = Object.keys(companyCounts).sort();

        // Calculate counts for Departments (based on tempHeads - filtered by company)
        const departmentCounts = tempHeads.reduce((acc, h) => { acc[h.department] = (acc[h.department] || 0) + 1; return acc; }, {});
        const departments = Object.keys(departmentCounts).sort();

        // Calculate counts for Positions (based on tempHeads)
        const positionCounts = tempHeads.reduce((acc, h) => { acc[h.position] = (acc[h.position] || 0) + 1; return acc; }, {});
        const positions = Object.keys(positionCounts).sort((a,b) => (positionRanks[a]||99) - (positionRanks[b]||99));

        // Update HTML with counts
        companyFilter.innerHTML = `<option value="all">ทุกบริษัท (${currentHeadsInModal.length})</option>` + companies.map(c => `<option value="${c}">${c} (${companyCounts[c]})</option>`).join('');
        
        departmentFilter.innerHTML = `<option value="all">ทุกแผนก (${tempHeads.length})</option>` + departments.map(d => `<option value="${d}">${d} (${departmentCounts[d]})</option>`).join('');
        
        positionFilter.innerHTML = `<option value="all">ทุกตำแหน่ง (${tempHeads.length})</option>
                                    <option value="manager_up">Manager ขึ้นไป</option>
                                    <option value="supervisor_up">Supervisor ขึ้นไป</option>
                                    <option disabled>---</option>` + 
                                    positions.map(p => `<option value="${p}">${p} (${positionCounts[p]})</option>`).join('');

        // Restore previous filter selections
        companyFilter.value = currentModalFilters.company;
        departmentFilter.value = currentModalFilters.department;
        positionFilter.value = currentModalFilters.position;
    }

    function renderModalHeadsList() {
        // Filter the current list in the modal based on selections
        filteredHeadsInModal = currentHeadsInModal.filter(h => {
            const companyMatch = currentModalFilters.company === 'all' || h.company === currentModalFilters.company;
            const departmentMatch = currentModalFilters.department === 'all' || h.department === currentModalFilters.department;
            
            const searchTerm = (currentModalFilters.search || '').toLowerCase();
            const searchMatch = !searchTerm || 
                                h.name.toLowerCase().includes(searchTerm) || 
                                h.email.toLowerCase().includes(searchTerm) ||
                                (h.department && h.department.toLowerCase().includes(searchTerm));

            let positionMatch = true;
            if (currentModalFilters.position !== 'all') {
                if (currentModalFilters.position === 'manager_up') {
                    positionMatch = (positionRanks[h.position] || 99) < 20; // Rank < 20
                } else if (currentModalFilters.position === 'supervisor_up') {
                    positionMatch = (positionRanks[h.position] || 99) < 30; // Rank < 30
                } else {
                    positionMatch = h.position === currentModalFilters.position;
                }
            }
            return companyMatch && departmentMatch && positionMatch && searchMatch;
        });

        // Sort the filtered list by position rank
        filteredHeadsInModal.sort((a, b) => (positionRanks[a.position] || 99) - (positionRanks[b.position] || 99));

        // Update title
        title.textContent = `รายชื่ออีเมลหัวหน้า (${filteredHeadsInModal.length} คน)`;
        
        // Update content with Checkboxes
        content.innerHTML = `<ul class="space-y-2">${filteredHeadsInModal.map(h =>
            `<li class="flex justify-between items-center text-sm p-2 rounded-md hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors" style="background-color: var(--bg-color)">
                <label class="flex items-center gap-3 flex-grow cursor-pointer">
                    <input type="checkbox" class="head-checkbox w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 dark:bg-slate-700 dark:border-slate-600" value="${h.email}" checked>
                    <span class="dark:text-gray-200 select-none"><strong class="${getCompanyClass(h.company)}">${h.company}</strong> (${h.department || 'N/A'}) - ${h.name}</span>
                </label>
                <div class="flex items-center gap-2 ml-4">
                    <span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">${h.email}</span>
                    <button class="text-red-400 hover:text-red-600 remove-head-btn" data-email="${h.email}" title="นำออก"><i class="fas fa-times-circle"></i></button>
                </div>
             </li>`
        ).join('')}</ul>`;
        
        updateSelectedCount();
    }

    function updateSelectedCount() {
        const checked = content.querySelectorAll('.head-checkbox:checked').length;
        const total = content.querySelectorAll('.head-checkbox').length;
        if(selectedCountSpan) selectedCountSpan.textContent = `เลือก ${checked}/${total} คน`;
    }

    function getSelectedHeads() {
        const checkedEmails = Array.from(content.querySelectorAll('.head-checkbox:checked')).map(cb => cb.value);
        const checkedSet = new Set(checkedEmails);
        return filteredHeadsInModal.filter(h => checkedSet.has(h.email));
    }

    // --- Event Listeners specific to this modal instance ---
    // Use named functions for easier removal if needed, though not strictly necessary here
    const handleFilterChange = (e) => {
        currentModalFilters[e.target.id.replace('FilterModal', '')] = e.target.value;
        // Reset department filter if company changes
        if(e.target.id === 'companyFilterModal') {
            currentModalFilters.department = 'all';
        }
        populateModalFilters(); // Repopulate filters (especially department/position)
        renderModalHeadsList(); // Re-render the list
    };

    const handleRemoveClick = (e) => {
         if (e.target.closest('.remove-head-btn')) {
            const emailToRemove = e.target.closest('.remove-head-btn').dataset.email;
            // Remove from the modal's current list
            currentHeadsInModal = currentHeadsInModal.filter(h => h.email !== emailToRemove);
            populateModalFilters(); // Repopulate filters based on the reduced list
            renderModalHeadsList(); // Re-render the list
        }
    };

    const handleConfirmClick = () => {
        const finalEmails = getSelectedHeads().map(h => h.email);
        if (finalEmails.length > 0) {
            copyTextToClipboard(finalEmails.join('; '), `คัดลอก ${finalEmails.length} อีเมล (ระดับหัวหน้า) แล้ว!`);
        } else {
            Swal.fire({ icon: 'warning', title: 'ไม่ได้เลือกรายการ', text: 'กรุณาเลือกรายชื่ออย่างน้อย 1 คน', timer: 1500, showConfirmButton: false });
        }
        closeModal('headsPreviewModal');
        // Clean up listeners if they were attached outside this function scope
        // (Not strictly needed here as they are attached within the function)
    };

    const handleComposeClick = () => {
        const finalEmails = getSelectedHeads().map(h => h.email);
        const type = document.getElementById('mailToType').value;
        if (finalEmails.length > 0) {
            const emailString = finalEmails.join(';');
            if (type === 'to') {
                window.location.href = `mailto:${emailString}`;
            } else {
                window.location.href = `mailto:?${type}=${emailString}`;
            }
        } else {
            Swal.fire({ icon: 'warning', title: 'ไม่ได้เลือกรายการ', text: 'กรุณาเลือกรายชื่ออย่างน้อย 1 คน', timer: 1500, showConfirmButton: false });
        }
    };

    const handleDownloadClick = () => {
        const selected = getSelectedHeads();
        if (selected.length === 0) { return Swal.fire({ icon: 'warning', title: 'ไม่ได้เลือกรายการ', text: 'กรุณาเลือกรายชื่ออย่างน้อย 1 คน', timer: 1500, showConfirmButton: false }); }
        let csvContent = "\uFEFFName,Email,Company,Position,Department\n";
        selected.forEach(h => {
            csvContent += `"${h.name}","${h.email}","${h.company}","${h.position}","${h.department}"\n`;
        });
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", `Management_Contacts_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    // Attach listeners
    companyFilter.addEventListener('change', handleFilterChange);
    departmentFilter.addEventListener('change', handleFilterChange);
    positionFilter.addEventListener('change', handleFilterChange);
    searchFilter.addEventListener('input', handleFilterChange);
    content.addEventListener('click', handleRemoveClick); // Use event delegation
    confirmBtn.addEventListener('click', handleConfirmClick);
    if(composeBtn) composeBtn.onclick = handleComposeClick;
    if(downloadBtn) downloadBtn.onclick = handleDownloadClick;
    
    // Checkbox listeners
    if(selectAllBtn) selectAllBtn.onclick = () => {
        content.querySelectorAll('.head-checkbox').forEach(cb => cb.checked = true);
        updateSelectedCount();
    };
    if(deselectAllBtn) deselectAllBtn.onclick = () => {
        content.querySelectorAll('.head-checkbox').forEach(cb => cb.checked = false);
        updateSelectedCount();
    };
    content.addEventListener('change', (e) => {
        if(e.target.classList.contains('head-checkbox')) {
            updateSelectedCount();
        }
    });

    // Initial population and rendering
    searchFilter.value = ''; // Reset search input
    populateModalFilters();
    renderModalHeadsList();
    openModal('headsPreviewModal');
}

// Function to export data to CSV (Excel compatible)
function exportToExcel() {
    // Filter data based on current search and company filter
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const deptFilterValue = document.getElementById('departmentFilter')?.value || 'all'; // รับค่าแผนกที่เลือก

    const filteredData = employees.filter(emp => {
        const matchesCompany = activeCompany === 'All' || emp.assignments.some(a => a.company === activeCompany);
        const matchesSearch = !searchTerm || [emp.id, emp.name, emp.name_th, emp.position, ...emp.assignments.flatMap(a => [a.company, a.department, a.email])].some(v => v && v.toLowerCase().includes(searchTerm));
        
        // เพิ่มเงื่อนไขกรองแผนก
        const relevantDept = getRelevantDepartment(emp);
        const matchesDept = deptFilterValue === 'all' || relevantDept === deptFilterValue;

        return matchesCompany && matchesSearch && matchesDept;
    });

    if (filteredData.length === 0) {
        Swal.fire({ icon: 'info', title: 'ไม่พบข้อมูล', text: 'ไม่มีรายชื่อพนักงานที่ตรงกับเงื่อนไขปัจจุบัน' });
        return;
    }

    // CSV Header with BOM for Thai support
    let csvContent = "\uFEFF"; 
    csvContent += "ID,Name (EN),Name (TH),Position,Company,Department,Email,Phone,Status\n";

    filteredData.forEach(emp => {
        // Determine relevant assignment based on active filter
        let assignment = emp.assignments.find(a => a.is_primary == 1) || emp.assignments[0] || {};
        if (activeCompany !== 'All') {
            const relevant = emp.assignments.find(a => a.company === activeCompany);
            if (relevant) assignment = relevant;
        }

        const row = [
            emp.id, emp.name, emp.name_th || '', emp.position,
            assignment.company || '', assignment.department || '', assignment.email || '',
            emp.phone || '', emp.employment_status
        ].map(e => `"${String(e || '').replace(/"/g, '""')}"`); // Escape quotes

        csvContent += row.join(",") + "\n";
    });

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", `Employee_Directory_${activeCompany}_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Function to export data to PDF
function exportToPDF() {
    // 1. Filter data based on current search and company filter (Same logic as Excel)
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const deptFilterValue = document.getElementById('departmentFilter')?.value || 'all'; // รับค่าแผนกที่เลือก

    const filteredData = employees.filter(emp => {
        const matchesCompany = activeCompany === 'All' || emp.assignments.some(a => a.company === activeCompany);
        const matchesSearch = !searchTerm || [emp.id, emp.name, emp.name_th, emp.position, ...emp.assignments.flatMap(a => [a.company, a.department, a.email])].some(v => v && v.toLowerCase().includes(searchTerm));
        
        // เพิ่มเงื่อนไขกรองแผนก
        const relevantDept = getRelevantDepartment(emp);
        const matchesDept = deptFilterValue === 'all' || relevantDept === deptFilterValue;

        return matchesCompany && matchesSearch && matchesDept;
    });

    if (filteredData.length === 0) {
        Swal.fire({ icon: 'info', title: 'ไม่พบข้อมูล', text: 'ไม่มีรายชื่อพนักงานที่ตรงกับเงื่อนไขปัจจุบัน' });
        return;
    }

    // 2. Show Loading
    Swal.fire({ title: 'กำลังสร้าง PDF...', text: 'กรุณารอสักครู่ ข้อมูลจำนวนมากอาจใช้เวลา', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    // 3. Create HTML Content for PDF
    const container = document.createElement('div');
    container.style.width = '100%'; // ใช้ความกว้างเต็มที่
    
    const deptTitle = deptFilterValue !== 'all' ? ` - แผนก ${deptFilterValue}` : '';
    
    let html = `
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;700&display=swap');
            body { font-family: 'Kanit', sans-serif; color: #333; }
            .header { text-align: center; margin-bottom: 20px; }
            .header h2 { margin: 0; font-size: 18px; font-weight: bold; }
            .header p { margin: 5px 0 0; font-size: 12px; color: #666; }
            table { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 10px; }
            th { background-color: #f3f4f6; color: #333; font-weight: bold; border: 1px solid #ccc; padding: 8px; text-align: left; }
            td { border: 1px solid #ccc; padding: 6px; text-align: left; vertical-align: top; }
            /* ป้องกันการตัดแถวตารางครึ่งๆ กลางๆ */
            tr { page-break-inside: avoid; page-break-after: auto; }
            /* แสดงหัวตารางซ้ำในทุกหน้า */
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
        </style>
        <div class="header">
            <h2>Employee Directory - ${activeCompany}${deptTitle}</h2>
            <p>Export Date: ${new Date().toLocaleDateString('th-TH', { dateStyle: 'long' })}</p>
            <p>Total: ${filteredData.length} records</p>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width: 8%;">ID</th>
                    <th style="width: 22%;">Name</th>
                    <th style="width: 15%;">Position</th>
                    <th style="width: 8%;">Comp.</th>
                    <th style="width: 15%;">Dept.</th>
                    <th style="width: 20%;">Email</th>
                    <th style="width: 12%;">Phone</th>
                </tr>
            </thead>
            <tbody>
    `;

    filteredData.forEach(emp => {
        let assignment = emp.assignments.find(a => a.is_primary == 1) || emp.assignments[0] || {};
        if (activeCompany !== 'All') {
            const relevant = emp.assignments.find(a => a.company === activeCompany);
            if (relevant) assignment = relevant;
        }

        html += `
            <tr>
                <td>${emp.id || ''}</td>
                <td>
                    <div style="font-weight:bold;">${emp.name}</div>
                    <div style="color:#666; font-size:9px;">${emp.name_th || ''}</div>
                </td>
                <td>${emp.position || ''}</td>
                <td>${assignment.company || ''}</td>
                <td>${assignment.department || ''}</td>
                <td>${assignment.email || ''}</td>
                <td>${emp.phone || ''}</td>
            </tr>`;
    });

    html += `</tbody></table>`;
    container.innerHTML = html;

    // 4. Generate PDF
    const opt = {
        margin: [10, 10, 10, 10], // ขอบกระดาษ บน ขวา ล่าง ซ้าย (mm)
        filename: `Employee_Directory_${activeCompany}${deptFilterValue !== 'all' ? '_' + deptFilterValue : ''}_${new Date().toISOString().slice(0,10)}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, letterRendering: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' },
        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] } // จัดการการตัดหน้ากระดาษ
    };

    html2pdf().set(opt).from(container).save().then(() => {
        Swal.close();
    }).catch(err => {
        console.error(err);
        Swal.fire('Error', 'เกิดข้อผิดพลาดในการสร้าง PDF', 'error');
    });
}

// Function to generate content and trigger print dialog for a department
function printDepartment(departmentName) {
    // Filter employees for the specific department and active company filter
    const deptEmployees = employees.filter(emp =>
        getRelevantDepartment(emp) === departmentName &&
        (activeCompany === 'All' || emp.assignments.some(a => a.company === activeCompany)) &&
        emp.employment_status !== 'inactive' // Exclude inactive employees from print
    );

    // Sort by position rank
    deptEmployees.sort((a,b) => (positionRanks[a.position] || 99) - (positionRanks[b.position] || 99));

    // Generate table rows HTML
    const tableRows = deptEmployees.map(emp => {
        let emailCellHtml = 'N/A'; // Default
        if (activeCompany === 'All') {
            // Show all emails with company if filter is 'All'
            emailCellHtml = emp.assignments.filter(a => a.email).map(a => `${a.email} (${a.company})`).join('<br>') || 'N/A';
        } else {
            // Show only the email for the active company filter
            const relevantAssignment = emp.assignments.find(a => a.company === activeCompany);
            emailCellHtml = relevantAssignment?.email || 'N/A';
        }
        return `<tr><td>${emp.name}</td><td>${emp.position || '-'}</td><td>${emailCellHtml}</td><td>${emp.phone || '-'}</td></tr>`;
    }).join('');

    // Prepare print content
    const companyName = activeCompany === 'All' ? 'ทุกบริษัท' : activeCompany;
    const printDate = new Date().toLocaleDateString('th-TH', { year: 'numeric', month: 'long', day: 'numeric' });
    const printContent = `<div class="print-header"><h2>รายชื่อพนักงาน แผนก ${departmentName}</h2><p>บริษัท: ${companyName} | พิมพ์เมื่อ: ${printDate}</p></div><table class="print-table"><thead><tr><th>ชื่อ-สกุล</th><th>ตำแหน่ง</th><th>อีเมล</th><th>เบอร์โทร</th></tr></thead><tbody>${tableRows.length > 0 ? tableRows : '<tr><td colspan="4" style="text-align:center;">ไม่พบข้อมูลพนักงาน</td></tr>'}</tbody></table>`;

    // Put content in print container and print
    const printContainer = document.querySelector('.print-container');
    if (printContainer) {
        printContainer.innerHTML = printContent;
        window.print(); // Trigger browser print dialog
    } else {
        console.error("Print container not found.");
    }
}

// Function to print the current directory view (Filtered List)
function printDirectory() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const deptFilterValue = document.getElementById('departmentFilter')?.value || 'all';

    // Filter employees (Logic เดียวกับ Export)
    const filteredData = employees.filter(emp => {
        const matchesCompany = activeCompany === 'All' || emp.assignments.some(a => a.company === activeCompany);
        const matchesSearch = !searchTerm || [emp.id, emp.name, emp.name_th, emp.position, ...emp.assignments.flatMap(a => [a.company, a.department, a.email])].some(v => v && v.toLowerCase().includes(searchTerm));
        const relevantDept = getRelevantDepartment(emp);
        const matchesDept = deptFilterValue === 'all' || relevantDept === deptFilterValue;
        return matchesCompany && matchesSearch && matchesDept;
    });

    if (filteredData.length === 0) {
        Swal.fire({ icon: 'info', title: 'ไม่พบข้อมูล', text: 'ไม่มีรายชื่อพนักงานที่ตรงกับเงื่อนไขปัจจุบัน' });
        return;
    }

    // Sort by department then rank then name
    filteredData.sort((a, b) => {
        const deptA = getRelevantDepartment(a) || 'zz';
        const deptB = getRelevantDepartment(b) || 'zz';
        if (deptA !== deptB) return deptA.localeCompare(deptB, 'th');
        
        const rankA = positionRanks[a.position] || 99;
        const rankB = positionRanks[b.position] || 99;
        if (rankA !== rankB) return rankA - rankB;
        
        return a.name.localeCompare(b.name, 'th');
    });

    // Generate HTML
    const companyName = activeCompany === 'All' ? 'ทุกบริษัท' : activeCompany;
    const deptName = deptFilterValue === 'all' ? 'ทุกแผนก' : deptFilterValue;
    const printDate = new Date().toLocaleDateString('th-TH', { year: 'numeric', month: 'long', day: 'numeric' });
    
    const tableRows = filteredData.map((emp, idx) => {
        let assignment = emp.assignments.find(a => a.is_primary == 1) || emp.assignments[0] || {};
        if (activeCompany !== 'All') {
            const relevant = emp.assignments.find(a => a.company === activeCompany);
            if (relevant) assignment = relevant;
        }
        
        return `
            <tr>
                <td style="text-align: center;">${idx + 1}</td>
                <td>${emp.id || ''}</td>
                <td><strong>${emp.name}</strong><br><span style="font-size: 0.9em; color: #555;">${emp.name_th || ''}</span></td>
                <td>${emp.position || '-'}</td>
                <td>${assignment.department || '-'}</td>
                <td>${emp.phone || '-'}</td>
                <td>${assignment.email || '-'}</td>
            </tr>
        `;
    }).join('');

    // Inject Print Styles dynamically
    let printStyle = document.getElementById('directory-print-style');
    if (!printStyle) {
        printStyle = document.createElement('style');
        printStyle.id = 'directory-print-style';
        document.head.appendChild(printStyle);
    }
    
    printStyle.innerHTML = `
        @media print {
            @page { size: A4 landscape; margin: 1cm; }
            body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; background: white !important; }
            body.printing-directory > *:not(.print-container) { display: none !important; }
            body.printing-directory .print-container { display: block !important; width: 100%; height: auto; position: absolute; top: 0; left: 0; }
            
            .print-table { width: 100%; border-collapse: collapse; font-size: 10pt; color: #000; }
            .print-table th, .print-table td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
            .print-table th { background-color: #f3f4f6 !important; font-weight: bold; text-align: center; }
            .print-table tr { page-break-inside: avoid; page-break-after: auto; }
            .print-table thead { display: table-header-group; }
            .print-table tfoot { display: table-footer-group; }
            
            .print-header { text-align: center; margin-bottom: 20px; }
            .print-header h2 { margin: 0; font-size: 18pt; font-weight: bold; }
            .print-header p { margin: 5px 0 0; font-size: 12pt; color: #666; }
        }
    `;

    const printContent = `<div class="print-header"><h2>รายชื่อพนักงาน (Employee Directory)</h2><p>บริษัท: ${companyName} | แผนก: ${deptName}</p><p style="font-size: 12px;">พิมพ์เมื่อ: ${printDate} | จำนวน: ${filteredData.length} คน</p></div><table class="print-table"><thead><tr><th width="5%">No.</th><th width="10%">ID</th><th width="25%">Name</th><th width="20%">Position</th><th width="15%">Dept</th><th width="10%">Ext.</th><th width="15%">Email</th></tr></thead><tbody>${tableRows}</tbody></table>`;

    const printContainer = document.querySelector('.print-container');
    if (printContainer) {
        printContainer.innerHTML = printContent;
        document.body.classList.add('printing-directory');
        setTimeout(() => {
            window.print();
            document.body.classList.remove('printing-directory');
            printContainer.innerHTML = ''; // Clear after print
        }, 500);
    } else {
        console.error("Print container not found.");
    }
}

// Function to show the help modal using SweetAlert
function showHelpModal() {
    Swal.fire({
        title: '<div class="flex items-center justify-center gap-2 text-indigo-600"><i class="fas fa-book-reader"></i> คู่มือการใช้งาน</div>',
        html: `
            <div class="text-left space-y-4 p-2 text-sm text-slate-600 dark:text-slate-300">
                
                <div class="bg-indigo-50 dark:bg-indigo-900/20 p-3 rounded-lg border border-indigo-100 dark:border-indigo-800">
                    <h4 class="font-bold text-indigo-700 dark:text-indigo-300 mb-2"><i class="fas fa-search"></i> การค้นหาและกรอง</h4>
                    <ul class="list-disc list-inside space-y-1 ml-1">
                        <li><strong>ค้นหา:</strong> พิมพ์ชื่อ, ID, หรือเบอร์โทร ในช่องค้นหา</li>
                        <li><strong>กรองบริษัท:</strong> คลิกปุ่ม <span class="px-2 py-0.5 bg-white border rounded text-xs">PTA</span> <span class="px-2 py-0.5 bg-white border rounded text-xs">PT4</span> เพื่อดูเฉพาะบริษัทนั้น</li>
                    </ul>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="p-3 border rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                        <h4 class="font-bold text-slate-800 dark:text-white mb-1"><i class="fas fa-user-tie text-blue-500"></i> เมลหัวหน้า</h4>
                        <p class="text-xs">กดปุ่มนี้เพื่อดูและคัดลอกอีเมลระดับ Manager ขึ้นไป สามารถเลือกส่งเข้า Outlook ได้ทันที</p>
                    </div>
                    <div class="p-3 border rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                        <h4 class="font-bold text-slate-800 dark:text-white mb-1"><i class="far fa-copy text-green-500"></i> โหมดเลือก</h4>
                        <p class="text-xs">กดปุ่มนี้เพื่อติ๊กเลือกพนักงานหลายคน แล้วกด "คัดลอกอีเมล" ที่แถบด้านล่างทีเดียว</p>
                    </div>
                    <div class="p-3 border rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                        <h4 class="font-bold text-slate-800 dark:text-white mb-1"><i class="fas fa-file-excel text-emerald-500"></i> Export</h4>
                        <p class="text-xs">ดาวน์โหลดรายชื่อที่แสดงอยู่เป็นไฟล์ Excel หรือ PDF เพื่อนำไปใช้งานต่อ</p>
                    </div>
                    <div class="p-3 border rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                        <h4 class="font-bold text-slate-800 dark:text-white mb-1"><i class="fas fa-address-card text-orange-500"></i> vCard</h4>
                        <p class="text-xs">กดไอคอนนี้บนการ์ดพนักงาน เพื่อโหลดไฟล์ Contact ไปเก็บในมือถือหรือ Outlook</p>
                    </div>
                </div>

                <div class="text-xs text-slate-400 mt-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                    <i class="fas fa-info-circle"></i> <strong>Tip:</strong> คลิกที่รูปพนักงานเพื่อดูรูปขนาดใหญ่ หรือคลิกที่อีเมล/เบอร์โทร เพื่อคัดลอกทันที
                </div>
            </div>
        `,
        width: '600px',
        showCloseButton: true,
        showConfirmButton: false,
        customClass: { // Apply dark theme to Swal modal if needed
            popup: document.documentElement.classList.contains('dark-theme') ? 'dark-theme' : ''
        }
    });
}

// Function to open the modal for editing department phone numbers
function openPhoneEditModal() {
    const container = document.getElementById('phoneEditFormContainer');
    let formHtml = '<div class="phone-edit-grid">';

    // Define all possible departments to ensure consistent order
    const allDepartmentsOrder = [
        "Management", "Accounting", "HR and GA", "IT", "Safety & Environment",
        "Production", "Production Engineer", "Maintenance", "Logistics", "Quality Management"
    ];

    ['PTA', 'PT4', 'PTE'].forEach(company => {
        formHtml += `<div class="phone-edit-card"><h4>${company}</h4>`;
        const companyPhones = departmentPhones[company] || {};

        // Use the defined order, including any departments from data not in the order
        const departmentsToDisplay = [
            ...allDepartmentsOrder,
            ...Object.keys(companyPhones).filter(dept => !allDepartmentsOrder.includes(dept))
        ];

        departmentsToDisplay.forEach(dept => {
            // Skip if department is not relevant (e.g., doesn't exist in colors or data for this company)
            // if (!departmentColors[dept] && !companyPhones[dept]) return;

            const phoneData = companyPhones[dept];

            // Handle sub-departments (nested object)
            if (typeof phoneData === 'object' && phoneData !== null && Object.keys(phoneData).length > 0) {
                 Object.entries(phoneData).sort().forEach(([subDept, phone]) => { // Sort sub-departments alphabetically
                    formHtml += `<div class="input-group"><label for="phone-${company}-${dept}-${subDept}">${dept} (${subDept})</label><input type="text" id="phone-${company}-${dept}-${subDept}" data-company="${company}" data-department="${dept}" data-sub_department="${subDept}" value="${phone || ''}"></div>`;
                });
            } else { // Handle main department (string or empty)
                 formHtml += `<div class="input-group"><label for="phone-${company}-${dept}">${dept}</label><input type="text" id="phone-${company}-${dept}" data-company="${company}" data-department="${dept}" data-sub_department="null" value="${typeof phoneData === 'string' ? phoneData : ''}"></div>`;
            }
        });
        formHtml += `</div>`; // Close phone-edit-card
    });
    formHtml += '</div>'; // Close phone-edit-grid
    container.innerHTML = formHtml;
    openModal('phoneEditModal');
}

// Function to save department phone numbers from the modal
async function saveDepartmentPhones() {
    const inputs = document.querySelectorAll('#phoneEditFormContainer input');
    const updatePromises = [];
    const saveBtn = document.getElementById('saveDeptPhonesBtn');

    // Disable button and show spinner
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> กำลังบันทึก...';

    // Create promises for each update request
    inputs.forEach(input => {
        const formData = new FormData();
        formData.append('action', 'update_department_phone');
        formData.append('company', input.dataset.company);
        formData.append('department', input.dataset.department);
        // Ensure 'null' string is sent if no sub-department
        formData.append('sub_department', input.dataset.sub_department === 'null' ? '' : input.dataset.sub_department);
        formData.append('phone', input.value.trim());

        const promise = fetch('api.php', { method: 'POST', body: formData })
                        .then(res => res.ok ? res.json() : Promise.reject(`HTTP error ${res.status}`))
                        .catch(err => ({ status: 'error', message: err.message || 'Network error' })); // Catch network errors too
        updatePromises.push(promise);
    });

    try {
        const results = await Promise.all(updatePromises);
        console.log("Save Phone Results:", results); // Log results for debugging

        // Check if *any* request failed
        const hasError = results.some(res => res.status !== 'success');

        if (hasError) {
            // Find the first error message
            const firstError = results.find(res => res.status !== 'success');
            throw new Error(firstError?.message || 'เกิดข้อผิดพลาดในการบันทึกบางรายการ');
        }

        // All successful
        Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ!', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
        closeModal('phoneEditModal');
        await loadDepartmentPhones(); // Reload phone data
        filterAndSortData(); // Re-render the phone bar and list

    } catch (error) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: error.message });

    } finally {
        // Re-enable button and restore text
        saveBtn.disabled = false;
        saveBtn.innerHTML = 'บันทึกข้อมูล';
    }
}

// --- Main Execution ---
// Run functions after the DOM is fully loaded
document.addEventListener('DOMContentLoaded', async () => {
    initializeThemeToggle(); // Apply theme first
    loadFavoritesFromStorage(); // Load favorites before initial render

    // Load initial data concurrently
    await Promise.all([
        loadEmployees(),
        loadLastUpdatedTimestamp(),
        loadDepartmentPhones()
    ]);
    renderDepartmentFilter();

    // Setup event listeners after data is loaded and initial render is done
    initEventListeners();

    // Add listener to clear print container after printing
    window.addEventListener('afterprint', () => {
        const printContainer = document.querySelector('.print-container');
        if (printContainer) {
            printContainer.innerHTML = ''; // Clear content
        }
    });

    // Add listeners to close modals when clicking on the overlay background
    ['viewModal', 'editModal', 'headsPreviewModal', 'phoneEditModal'].forEach(modalId => {
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
             modalElement.addEventListener('click', (e) => {
                // Close only if the click is directly on the overlay (the element with the ID)
                if (e.target.id === modalId) {
                    closeModal(modalId);
                }
            });
        }
    });
    // Disabled: Close only on X button click
    // ['viewModal', 'editModal', 'headsPreviewModal', 'phoneEditModal'].forEach(modalId => {
    //     const modalElement = document.getElementById(modalId);
    //     if (modalElement) {
    //          modalElement.addEventListener('click', (e) => {
    //             // Close only if the click is directly on the overlay (the element with the ID)
    //             if (e.target.id === modalId) {
    //                 closeModal(modalId);
    //             }
    //         });
    //     }
    // });
});

