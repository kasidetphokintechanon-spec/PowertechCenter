// js/shared.js

// --- START: Added Global Variable ---
window.allEmployeesData = []; // Global variable to store employee data
// --- END: Added Global Variable ---

/**
 * Fetches employee data and stores it globally.
 * @returns {Promise<boolean>} A promise that resolves to true if data is loaded successfully, false otherwise.
 */
async function loadEmployeeData() {
    // Avoid fetching if data already exists
    if (window.allEmployeesData && window.allEmployeesData.length > 0) {
        // console.log("Employee data already loaded.");
        return true;
    }
    try {
        const response = await fetch(`api.php?action=get&file=employees&status_filter=all&v=${Date.now()}`); // Fetch all statuses
        if (!response.ok) {
            throw new Error(`HTTP error ${response.status}`);
        }
        const employees = await response.json();
        window.allEmployeesData = Array.isArray(employees) ? employees : [];
        // console.log("Employee data loaded successfully:", window.allEmployeesData.length, "records");
        return true;
    } catch (error) {
        console.error("Failed to load employee data:", error);
        window.allEmployeesData = []; // Ensure it's an empty array on failure
        // Optionally show an error message to the user here
        // Swal.fire('Error', 'Could not load required employee data.', 'error');
        return false;
    }
}


/**
 * Renders the sidebar navigation and UI elements.
 * @param {string} activePage - The identifier for the currently active page (e.g., 'index', 'directory').
 * @returns {Promise<object>} A promise that resolves with the session data, allowing other scripts to wait for it.
 */
async function renderSidebar(activePage) {
    const sidebarContainer = document.getElementById('sidebar');
    if (!sidebarContainer) {
        try {
            // Even without a sidebar, try to load session and employee data if needed elsewhere
            const session = await fetch('api.php?action=check_session').then(res => res.json());
            await loadEmployeeData(); // Load employee data
            return session;
        } catch (e) {
            console.error("Session/Employee check failed on a page without a sidebar.", e);
            return { loggedin: false, name: null, role: null, department: null, user_id: null };
        }
    }

    // Basic sidebar structure
    const sidebarHTML = `
        <div class="sidebar-header">
            <a href="index.html" class="flex items-center gap-3">
                 <i id="default-logo-icon" class="fas fa-bolt fa-lg text-blue-600"></i>
                 <img id="company-logo-img" src="" alt="Company Logo" class="w-10 h-10 object-contain bg-white dark:bg-slate-800 rounded-lg p-1 shadow-sm border border-slate-200 dark:border-slate-700" style="display: none;">
                <h1 class="text-2xl font-bold">Powertech Center</h1>
            </a>
        </div>
        <nav class="sidebar-nav flex-grow" id="menu-container"> {/* Added flex-grow */}
            {/* Menus will be populated by populateSidebarContent */}
        </nav>
        <div class="sidebar-footer">
            <div id="user-info-container" class="w-full mb-4"> {/* Added margin bottom */}
                {/* User info will be populated */}
            </div>
            <div class="text-center text-xs text-gray-400">
                <p>&copy; ${new Date().getFullYear()} Powertech Group</p> {/* Dynamic Year */}
                <p>Developed by Kasidet P.</p>
            </div>
        </div>
    `;
    sidebarContainer.innerHTML = sidebarHTML;

    // Fetch session, load employee data, and populate menus
    return await populateSidebarContent(activePage);
}

/**
 * Fetches session status, employee data, pending counts, and populates sidebar menus and user info.
 * @param {string} activePage - The identifier for the currently active page.
 * @returns {Promise<object>} A promise that resolves with the session data.
 */
async function populateSidebarContent(activePage) {
    const menuContainer = document.getElementById('menu-container');
    const userInfoContainer = document.getElementById('user-info-container');
    menuContainer.innerHTML = ''; // Clear previous menus
    userInfoContainer.innerHTML = ''; // Clear user info

    try {
        const response = await fetch('api.php?action=check_session');
        const session = await response.json();
        
        // --- START: Apply Company Theme ---
        if (session.company) applyCompanyTheme(session.company);
        // --- END: Apply Company Theme ---

        // --- START: Load Employee Data After Session Check ---
        const employeeDataLoaded = await loadEmployeeData();
        if (!employeeDataLoaded && (activePage === 'request' || activePage === 'log' || activePage === 'loan' || activePage === 'directory')) {
             // If critical pages fail to load employee data, show error and potentially halt
             Swal.fire('Critical Error', 'Could not load employee data. Some features may not work.', 'error');
        }
        // --- END: Load Employee Data ---


        let mainMenuHTML = `
            <div class="nav-section">
                <h3>เมนูหลัก</h3>
                <a href="index.html" id="nav-index" class="nav-link"><i class="fas fa-home fa-fw"></i><span>Dashboard</span></a>
                
            </div>`;

        let userMenuHTML = '';
        let officerMenuHTML = '';
        let adminMenuHTML = '';
        let adminBadgeHTML = '';

        if (session.loggedin) {
            userMenuHTML = `
                 <div class="nav-section">
                     <h3>ส่วนตัว</h3>
                     <a href="my_tickets.html" id="nav-my_tickets" class="nav-link"><i class="fas fa-ticket-alt fa-fw"></i><span>รายการแจ้งซ่อม</span></a>
                     <a href="my_borrow_history.html" id="nav-my_borrow_history" class="nav-link"><i class="fas fa-box-open fa-fw"></i><span>ประวัติการยืม</span></a>
                     <a href="daily_checklist.html" id="nav-daily_checklist" class="nav-link"><i class="fas fa-clipboard-check fa-fw"></i><span>Daily Checklist</span></a>
                 </div>`;

            // --- START: Fetch and Calculate Pending Counts ---
            let loanPendingCount = 0;
            let repairPendingCount = 0;
            if (session.role === 'admin' || session.role === 'staff') {
                try {
                    const countsRes = await fetch('api.php?action=get_pending_counts').then(res => res.json());
                    if (countsRes.status === 'success') {
                        loanPendingCount = countsRes.loan_pending_count || 0;
                        repairPendingCount = countsRes.repair_pending_count || 0;
                    }
                } catch (countError) {
                    console.warn("Could not fetch pending counts:", countError);
                }
            }
            // --- END: Fetch and Calculate Pending Counts ---

            if (session.role === 'admin' || session.role === 'staff') {
                const repairBadge = repairPendingCount > 0 ? `<span class="pending-badge">${repairPendingCount}</span>` : '';
                const loanBadge = loanPendingCount > 0 ? `<span class="pending-badge">${loanPendingCount}</span>` : '';

                let itMenuHTML = '';
                // แสดงเมนู IT เฉพาะ Admin หรือแผนก IT เท่านั้น
                if (session.role === 'admin' || session.primary_department === 'IT') {
                    itMenuHTML = `
                        <a href="dashboard.html" id="nav-dashboard" class="nav-link relative"><i class="fas fa-tachometer-alt fa-fw"></i><span>Dashboard งานซ่อม</span>${repairBadge}</a>
                        <a href="log.html" id="nav-log" class="nav-link"><i class="fas fa-book fa-fw"></i><span>บันทึกงาน IT</span></a>`;
                }

                officerMenuHTML = `
                    <div class="nav-section">
                        <h3>เมนูเจ้าหน้าที่</h3>
                        ${itMenuHTML}
                        <a href="borrow_management.html" id="nav-borrow_management" class="nav-link relative"><i class="fas fa-dolly fa-fw"></i><span>จัดการยืม-คืน</span>${loanBadge}</a>
                    </div>`;
            }

            if (session.role === 'admin') {
                const totalAdminPending = loanPendingCount + repairPendingCount;
                adminBadgeHTML = totalAdminPending > 0 ? `<span class="pending-badge">${totalAdminPending}</span>` : '';

                adminMenuHTML = `
                    <div class="nav-section">
                        <h3>การจัดการ</h3>
                        <a href="admin.html" id="nav-admin" class="nav-link relative"><i class="fas fa-cogs fa-fw"></i><span>Admin Panel</span>${adminBadgeHTML}</a>
                        <a href="reg_generator.html" id="nav-reg_generator" class="nav-link"><i class="fas fa-file-code fa-fw"></i><span>สร้างไฟล์ .reg</span></a>
                    </div>`;
            }

            userInfoContainer.innerHTML = `
                <div class="flex items-center gap-3 p-2 bg-gray-100 dark:bg-gray-700 rounded-lg">
                     <i class="fas fa-user-circle fa-2x text-gray-400"></i>
                     <div class="flex-grow">
                         <div class="font-semibold text-sm">${session.display_name || session.username}</div>
                         <div class="text-xs text-gray-500 dark:text-gray-400">${session.role} ${session.primary_department ? `(${session.primary_department})` : ''}</div>
                     </div>
                    <a href="#" onclick="logout(event)" title="Logout" class="text-gray-500 hover:text-red-500 px-2"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            `;

        } else { // Not logged in
             mainMenuHTML = `
                <div class="nav-section">
                     <h3>เมนูหลัก</h3>
                     <a href="index.html" id="nav-index" class="nav-link"><i class="fas fa-home fa-fw"></i><span>Dashboard</span></a>
                     <a href="directory.html" id="nav-directory" class="nav-link"><i class="fas fa-address-book fa-fw"></i><span>สมุดรายชื่อพนักงาน</span></a>
                     <a href="loan.html" id="nav-loan" class="nav-link"><i class="fas fa-boxes-stacked fa-fw"></i><span>แคตตาล็อกอุปกรณ์</span></a>
                     {/* No request link for logged out users */}
                 </div>`;

            userInfoContainer.innerHTML = `
                <a href="login.html?returnUrl=${window.location.pathname.split('/').pop() || 'index.html'}" class="w-full text-center bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 font-bold py-2 px-4 rounded-lg inline-flex items-center justify-center gap-2">
                    <i class="fas fa-sign-in-alt"></i><span>Login</span>
                </a>
            `;
        }

        // Combine all menus
        menuContainer.innerHTML = mainMenuHTML + userMenuHTML + officerMenuHTML + adminMenuHTML;

        // Highlight active link
        const navLink = document.getElementById(`nav-${activePage}`);
        if (navLink) navLink.classList.add('active');

        // Add logout listener if logged in
        if (session.loggedin) {
            const logoutBtn = userInfoContainer.querySelector('a[onclick="logout(event)"]');
            if (logoutBtn) {
                 logoutBtn.addEventListener('click', logout);
            }
        }

        return session; // Return session data

    } catch (error) {
        console.error("Session check or data loading failed", error);
        userInfoContainer.innerHTML = `<a href="login.html" class="w-full text-center bg-red-100 text-red-700 font-bold py-2 px-4 rounded-lg">Error Loading Session</a>`;
        // Return a default logged-out state on error
        return { loggedin: false, name: null, role: null, department: null, user_id: null };
    }
}

async function logout(event) {
    if (event) event.preventDefault();
    try {
        await fetch('api.php?action=logout');
    } catch (e) {
        console.error("Logout API call failed, proceeding with client-side logout.", e);
    } finally {
        // Always redirect or reload, even if API call fails
        window.location.href = 'index.html'; // Redirect to index after logout
    }
}

// --- START: Company Theme Function ---
function applyCompanyTheme(company) {
    let color = '#2563eb'; // Default Blue (PTE)
    let hoverColor = '#1d4ed8';
    let lightBg = '#eff6ff';

    if (company === 'PT4') { // Red
        color = '#dc2626'; // red-600
        hoverColor = '#b91c1c'; // red-700
        lightBg = '#fef2f2'; // red-50
    } else if (company === 'PTA') { // Green
        color = '#16a34a'; // green-600
        hoverColor = '#15803d'; // green-700
        lightBg = '#f0fdf4'; // green-50
    } else if (company === 'PTE') { // Blue
        color = '#2563eb'; // blue-600
        hoverColor = '#1d4ed8'; // blue-700
        lightBg = '#eff6ff'; // blue-50
    }

    // Inject Dynamic CSS to override Tailwind/Bootstrap classes
    let styleTag = document.getElementById('company-theme-style');
    if (!styleTag) {
        styleTag = document.createElement('style');
        styleTag.id = 'company-theme-style';
        document.head.appendChild(styleTag);
    }

    styleTag.innerHTML = `
        :root { --theme-primary: ${color}; }
        
        /* Override Text Colors */
        .text-blue-600, .text-primary { color: ${color} !important; }
        .text-blue-500 { color: ${color} !important; }
        
        /* Override Backgrounds */
        .bg-blue-600, .bg-primary, .btn-primary { background-color: ${color} !important; }
        .bg-blue-500 { background-color: ${color} !important; }
        
        /* Sidebar Active State */
        .nav-link.active {
            background-color: ${lightBg} !important;
            color: ${color} !important;
            border-right: 3px solid ${color} !important;
        }
        
        /* Sidebar Icon */
        .sidebar-header i { color: ${color} !important; }
    `;

    // --- START: Logo Change Logic ---
    const logoIcon = document.getElementById('default-logo-icon');
    const logoImg = document.getElementById('company-logo-img');
    
    // กำหนด Path ของไฟล์ Logo ที่นี่ (Upload ไฟล์ไปที่ uploads/logos/ หรือโฟลเดอร์ที่ต้องการ)
    const logoPaths = {
        'PT4': 'uploads/logos/pt4_logo.png',
        'PTA': 'uploads/logos/pta_logo.png',
        'PTE': 'uploads/logos/pte_logo.png'
    };

    if (logoImg && logoPaths[company]) {
        // ปรับสีพื้นหลังและขอบของโลโก้ตามธีมบริษัท
        logoImg.style.backgroundColor = lightBg;
        logoImg.style.borderColor = color;

        // เพิ่ม ?t=... เพื่อป้องกัน Browser Cache รูปเก่า
        logoImg.src = logoPaths[company] + '?t=' + new Date().getTime();
        
        logoImg.onload = function() {
            logoImg.style.display = 'block'; // โหลดสำเร็จค่อยแสดง
            if (logoIcon) logoIcon.style.display = 'none';
        };
        logoImg.onerror = function() {
            logoImg.style.display = 'none'; // โหลดไม่สำเร็จ (เช่นยังไม่อัปโหลด) ให้ซ่อน
            if (logoIcon) logoIcon.style.display = 'block'; // และกลับไปใช้ไอคอนเดิม
        };
    }

    // --- START: Favicon Change Logic ---
    let link = document.querySelector("link[rel~='icon']");
    if (!link) {
        link = document.createElement('link');
        link.rel = 'icon';
        document.head.appendChild(link);
    }
    if (logoPaths[company]) {
        link.href = logoPaths[company] + '?t=' + new Date().getTime();
    }
    // --- END: Favicon Change Logic ---
    // --- END: Logo Change Logic ---
}
// --- END: Company Theme Function ---

// --- Theme functions remain the same ---
function applyTheme(theme) {
    document.documentElement.classList.toggle('dark-theme', theme === 'dark');
    document.documentElement.classList.remove('dark-theme-active'); // Compatibility
}

function initializeThemeToggle() {
    const savedTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    applyTheme(savedTheme);

    // Use event delegation on the body for the theme toggle button
    document.body.addEventListener('click', function(event) {
        const toggleButton = event.target.closest('#theme-toggle');
        if (toggleButton) {
            const isDark = document.documentElement.classList.toggle('dark-theme');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        }
    });
}
// --- End Theme Functions ---

// Initialize theme on initial load for pages including this script
document.addEventListener('DOMContentLoaded', () => {
    initializeThemeToggle();
    // Note: renderSidebar is typically called by the specific page's script
});

// Helper function to check admin status (can be called by other scripts)
async function checkAdminLoginStatus(pageName = 'page') {
     try {
         const response = await fetch('api.php?action=check_session');
         const session = await response.json();
         if (session.loggedin && (session.role === 'admin' || session.role === 'staff')) {
             return true; // Is admin or staff
         } else if (session.loggedin) {
             // Logged in but not admin/staff - redirect away from restricted pages
             console.warn(`User is logged in but lacks required role (${session.role}) for ${pageName}. Redirecting.`);
             window.location.href = 'index.html'; // Or a 'permission denied' page
             return false;
         } else {
             // Not logged in - redirect to login
             window.location.href = `login.html?returnUrl=${pageName}.html`;
             return false;
         }
     } catch (error) {
         console.error("Session check failed", error);
         // Redirect to login on error as well
         window.location.href = `login.html?returnUrl=${pageName}.html`;
         return false;
     }
 }

 // Set Greeting function (if needed globally, otherwise keep in index.html)
 function setGreeting() {
    const greetingText = document.getElementById('greeting-text');
    if (!greetingText) return;
    const hour = new Date().getHours();
    if (hour < 12) greetingText.textContent = 'สวัสดีตอนเช้า';
    else if (hour < 18) greetingText.textContent = 'สวัสดีตอนบ่าย';
    else greetingText.textContent = 'สวัสดีตอนเย็น';
}

// --- Access Log Logic ---
document.addEventListener('DOMContentLoaded', () => {
    // ส่ง Log เฉพาะเมื่อไม่ใช่หน้า Login (เพื่อลดขยะ Log)
    if (!window.location.pathname.includes('login.html')) {
        const currentPage = document.title + " (" + window.location.pathname.split('/').pop() + ")";
        const fd = new FormData();
        fd.append('action', 'log_access');
        fd.append('page', currentPage);
        fetch('api.php', { method: 'POST', body: fd }).catch(() => {});
    }
});
