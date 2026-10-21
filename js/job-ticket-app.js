const API_PATH = 'api.php';
        let allData = [];
        let currentCompany = 'PT4';
        let userSession = {}; // เก็บข้อมูล User
        let selectedBuildings = [];

        let personalTasks = [];
        let personalSettings = { hideDone: false, defaultSort: 'due_date', viewMode: 'kanban' };
        let personalDraftKey = 'personalTaskDraft';
        let personalSettingsKey = 'personalTaskSettings';
        let currentMode = 'personal';
        let personalScope = 'mine';
        let personalCompanyFilter = 'all';
        let personalAlertFilter = 'all';
        let personalPollInterval = null;
        let personalSnapshot = {};
        let personalStatusPending = new Set();
        let personalStatusChart = null;
        let personalOwnerChart = null;

        const BUILDING_CONFIG = {
            'PT4': ['F5', 'F6', 'F7', 'F8'],
            'PTA': ['F16', 'F17', 'F20'],
            'PTE': ['PTE']
        };

        const HRGA_DEPT = 'HR and GA';
        const HRGA_COMPANIES = ['PTA', 'PT4', 'PTE'];

        function isHrGaUser() {
            if (userSession.hrga_mode) return true;
            const d = String(userSession.primary_department || '').trim().replace(/\s+/g, ' ');
            if (d.toLowerCase() === HRGA_DEPT.toLowerCase()) return true;
            return /^hr\s*(?:&|and|และ)\s*ga$/i.test(d);
        }

        /** ค่าเริ่มต้นงานใหม่สำหรับแผนก HR&GA — แชร์แผนก + รับผิดชอบ 3 บริษัท */
        function applyHrGaNewTaskDefaults() {
            if (!isHrGaUser()) return;
            const shareInput = document.getElementById('personalShare');
            if (shareInput) shareInput.value = 'department';
            setSelectedResponsibleCompanies(HRGA_COMPANIES.slice());
        }

        window.onload = initPage;

        async function initPage() {
            try {
                const res = await fetch(API_PATH + '?action=check_session');
                userSession = await res.json();
                
                if (!userSession.loggedin) {
                    window.location.href = 'login.html';
                    return;
                }

                const hrgaLink = document.getElementById('navHrgaLink');
                if (hrgaLink && userSession.hrga_mode) hrgaLink.style.display = '';

                if (userSession.role !== 'admin') {
                    currentCompany = userSession.company;
                    document.querySelectorAll('.tab-btn').forEach(btn => {
                        if (!btn.classList.contains(currentCompany.toLowerCase())) {
                            btn.style.display = 'none';
                        }
                    });
                }
            } catch(e) { console.error("Auth Error", e); }

            hydratePersonalCompanyControls();
            loadPersonalSettingsFromStorage();
            bindPersonalUIEvents();
            setupScopeButtons();
            await loadPersonalTasks(false);
            const pageQs = new URLSearchParams(window.location.search);
            const openTaskId = pageQs.get('task');
            if (openTaskId) {
                setJobPageMode('personal');
                const tid = parseInt(openTaskId, 10);
                if (tid > 0) openPersonalTaskModal(tid);
            } else if (pageQs.get('new') === 'hrga' || pageQs.get('hrga') === '1') {
                setJobPageMode('personal');
                applyHrGaNewTaskDefaults();
                if (pageQs.get('recurring') === '1') {
                    const rec = document.getElementById('personalIsRecurring');
                    if (rec) rec.checked = true;
                    if (typeof toggleRecurringFields === 'function') toggleRecurringFields();
                }
                openPersonalTaskModal();
            }
            if (!personalPollInterval) {
                personalPollInterval = setInterval(() => {
                    if (currentMode === 'personal') {
                        loadPersonalTasks(true);
                    }
                }, 15000);
            }
        }

        function setJobPageMode(mode) {
            currentMode = mode;
            const personalSection = document.getElementById('personalSection');
            const multiSection = document.getElementById('multiSiteSection');
            const dashSection = document.getElementById('dashboardSection');
            const btnPersonal = document.getElementById('btn-mode-personal');
            const btnProject = document.getElementById('btn-mode-project');
            const btnDash = document.getElementById('btn-mode-dashboard');

            const allBtns = [btnPersonal, btnProject, btnDash];
            allBtns.forEach(b => b && b.classList.remove('active'));

            if (personalSection) personalSection.classList.add('hidden');
            if (multiSection) multiSection.classList.add('hidden');
            if (dashSection) dashSection.classList.add('hidden');

            if (mode === 'personal') {
                if (personalSection) personalSection.classList.remove('hidden');
                if (btnPersonal) btnPersonal.classList.add('active');
            } else if (mode === 'project') {
                if (multiSection) multiSection.classList.remove('hidden');
                if (btnProject) btnProject.classList.add('active');
                if (allData.length === 0 && typeof initMultiSiteBoard === 'function') initMultiSiteBoard();
            } else if (mode === 'dashboard') {
                if (dashSection) dashSection.classList.remove('hidden');
                if (btnDash) btnDash.classList.add('active');
                if (typeof loadDashboard === 'function') loadDashboard();
            }
        }

        function setupScopeButtons() {
            const btnMine = document.getElementById('btn-scope-mine');
            const btnDept = document.getElementById('btn-scope-dept');
            const btnCompany = document.getElementById('btn-scope-company');
            const companies = getAccessibleCompanies();
            if (userSession.primary_department) {
                btnDept.classList.remove('hidden');
            }
            if (userSession.role === 'admin' || userSession.role === 'staff' || companies.length > 1) {
                btnCompany.classList.remove('hidden');
            }
            btnMine.addEventListener('click', () => changeScope('mine'));
            btnDept.addEventListener('click', () => changeScope('department'));
            btnCompany.addEventListener('click', () => changeScope('company'));
        }

        function toggleJnAnalytics() {
            const body = document.getElementById('jnAnalyticsBody');
            const icon = document.getElementById('jnAnalyticsIcon');
            if (!body) return;
            const open = body.classList.toggle('open');
            if (icon) icon.style.transform = open ? 'rotate(180deg)' : '';
        }

        function updateJnKpis(taskList) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            let todo = 0, doing = 0, done = 0, overdue = 0;
            (taskList || []).forEach(t => {
                const st = t.status || 'todo';
                if (st === 'done') done++;
                else if (st === 'todo') todo++;
                else doing++;
                if (st !== 'done' && t.due_date) {
                    const d = new Date(t.due_date + 'T00:00:00');
                    d.setHours(0, 0, 0, 0);
                    if (d < today) overdue++;
                }
            });
            const fmt = n => Number(n).toLocaleString('th-TH');
            const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = fmt(v); };
            set('jn-kpi-total', (taskList || []).length);
            set('jn-kpi-todo', todo);
            set('jn-kpi-doing', doing);
            set('jn-kpi-overdue', overdue);
            set('jn-kpi-done', done);
        }

        function changeScope(scope) {
            personalScope = scope;
            const btnMine = document.getElementById('btn-scope-mine');
            const btnDept = document.getElementById('btn-scope-dept');
            const btnCompany = document.getElementById('btn-scope-company');
            [btnMine, btnDept, btnCompany].forEach(b => { if (b) b.classList.remove('active'); });
            if (scope === 'mine' && btnMine) btnMine.classList.add('active');
            if (scope === 'department' && btnDept) btnDept.classList.add('active');
            if (scope === 'company' && btnCompany) btnCompany.classList.add('active');
            const filterSelect = document.getElementById('personalFilterCompany');
            if (filterSelect) {
                filterSelect.classList.toggle('hidden', scope !== 'company' || getAccessibleCompanies().length <= 1);
            }
            loadPersonalTasks(false);
        }

        async function loadPersonalTasks(isAuto) {
            document.getElementById('loading').style.display = 'flex';
            try {
                const companyParam = personalScope === 'company' ? ('&company=' + encodeURIComponent(personalCompanyFilter || 'all')) : '';
                const res = await fetch(API_PATH + '?action=personal_tasks_get&scope=' + encodeURIComponent(personalScope) + companyParam);
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error(text);
                }
                if (Array.isArray(data)) {
                    applyPersonalData(data, !!isAuto);
                }
            } catch (e) {
                console.error(e);
                showToast(e.message || 'โหลดข้อมูลงานส่วนตัวไม่สำเร็จ', true);
            }
            document.getElementById('loading').style.display = 'none';
        }

        function applyPersonalData(newList, isAuto) {
            const normalizedList = (newList || []).map(normalizeTaskRecord);
            const newMap = {};
            normalizedList.forEach(t => {
                newMap[t.id] = t.updated_at || '';
            });
            if (isAuto && Object.keys(personalSnapshot).length) {
                let changedCount = 0;
                normalizedList.forEach(t => {
                    const prev = personalSnapshot[t.id];
                    if (prev && prev !== (t.updated_at || '')) {
                        changedCount++;
                    }
                });
                if (changedCount > 0) {
                    showToast('มีงาน ' + changedCount + ' รายการถูกอัปเดต');
                }
            }
            personalTasks = normalizedList;
            personalSnapshot = newMap;
            renderPersonalBoard();
        }

        function normalizeTaskRecord(task) {
            if (!task || typeof task !== 'object') return task;
            return Object.assign({}, task, {
                id: Number(task.id || 0),
                progress_pct: Number(task.progress_pct || 0),
                remind_month_start: Number(task.remind_month_start || 0),
                remind_month_end: Number(task.remind_month_end || 0),
                is_recurring: Number(task.is_recurring || 0),
                recurring_interval_months: Number(task.recurring_interval_months || 12),
                remind_days_before: Number(task.remind_days_before || 0),
                recurring_parent_id: task.recurring_parent_id !== null && task.recurring_parent_id !== undefined ? Number(task.recurring_parent_id) : null
            });
        }

        function getAccessibleCompanies() {
            const allowed = Array.isArray(userSession.available_companies) ? userSession.available_companies.filter(Boolean) : [];
            const base = allowed.length ? allowed : [userSession.company].filter(Boolean);
            return Array.from(new Set(base));
        }

        function getSelectedResponsibleCompanies() {
            const checks = document.querySelectorAll('#personalCompanyChecklist input[type="checkbox"]:checked');
            const values = Array.from(checks).map(el => el.value).filter(Boolean);
            if (values.length) return values;
            const fallback = getAccessibleCompanies();
            return fallback.length ? [fallback[0]] : ['PTA'];
        }

        function setSelectedResponsibleCompanies(companies) {
            const selectedSet = new Set((companies || []).filter(Boolean));
            const checks = document.querySelectorAll('#personalCompanyChecklist input[type="checkbox"]');
            checks.forEach(el => {
                el.checked = selectedSet.has(el.value);
            });
            const selected = getSelectedResponsibleCompanies();
            const hiddenCompany = document.getElementById('personalCompany');
            if (hiddenCompany) hiddenCompany.value = selected[0] || '';
        }

        function hydratePersonalCompanyControls() {
            const companies = getAccessibleCompanies();
            const filterSelect = document.getElementById('personalFilterCompany');
            const checklist = document.getElementById('personalCompanyChecklist');
            if (filterSelect) {
                filterSelect.innerHTML = '<option value="all">ทุกบริษัท</option>' + companies.map(code => `<option value="${code}">${code}</option>`).join('');
                if (!companies.length || companies.length === 1) {
                    filterSelect.classList.add('hidden');
                    personalCompanyFilter = companies[0] || 'all';
                } else {
                    filterSelect.classList.remove('hidden');
                    if (!companies.includes(personalCompanyFilter) && personalCompanyFilter !== 'all') {
                        personalCompanyFilter = 'all';
                    }
                }
                filterSelect.value = personalCompanyFilter;
            }
            if (checklist) {
                checklist.innerHTML = companies.map(code => `
                    <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 cursor-pointer text-sm text-slate-700">
                        <input type="checkbox" value="${code}" class="personal-company-check">
                        <span><i class="fas fa-building text-slate-500"></i> ${code}</span>
                    </label>
                `).join('');
                checklist.querySelectorAll('input[type="checkbox"]').forEach(el => {
                    el.addEventListener('change', () => {
                        const selected = getSelectedResponsibleCompanies();
                        const hiddenCompany = document.getElementById('personalCompany');
                        if (hiddenCompany) hiddenCompany.value = selected[0] || '';
                        savePersonalDraftToStorage();
                    });
                });
                setSelectedResponsibleCompanies(companies.length ? [companies[0]] : ['PTA']);
            }
        }

        function getTaskDeadlineMeta(task) {
            if (!task || !task.due_date || task.status === 'done') {
                return { category: 'none', days: null, label: '', color: '#94a3b8', bg: '#f8fafc', border: '#e2e8f0' };
            }
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const due = new Date(task.due_date + 'T00:00:00');
            due.setHours(0, 0, 0, 0);
            const diff = Math.round((due - today) / 86400000);
            if (diff < 0) {
                return {
                    category: 'overdue',
                    days: diff,
                    label: 'เกินกำหนด ' + Math.abs(diff) + ' วัน',
                    color: '#b91c1c',
                    bg: '#fef2f2',
                    border: '#fecaca'
                };
            }
            if (diff === 0) {
                return {
                    category: 'today',
                    days: diff,
                    label: 'ครบกำหนดวันนี้',
                    color: '#c2410c',
                    bg: '#fff7ed',
                    border: '#fdba74'
                };
            }
            if (diff <= 3) {
                return {
                    category: 'soon',
                    days: diff,
                    label: 'ใกล้ครบกำหนดอีก ' + diff + ' วัน',
                    color: '#a16207',
                    bg: '#fefce8',
                    border: '#fde68a'
                };
            }
            return { category: 'none', days: diff, label: '', color: '#94a3b8', bg: '#f8fafc', border: '#e2e8f0' };
        }

        function getTaskRenewalReminderMeta(task) {
            if (!task || (task.status || 'todo') === 'done' || Number(task.is_recurring || 0) !== 1 || !task.due_date) return null;
            const beforeDays = Math.max(0, Number(task.remind_days_before || 0));
            if (beforeDays <= 0) return null;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const due = new Date(task.due_date + 'T00:00:00');
            due.setHours(0, 0, 0, 0);
            const diff = Math.round((due - today) / 86400000);
            if (diff < 0 || diff > beforeDays) return null;
            return {
                category: 'renewal_due',
                active: true,
                label: diff === 0 ? 'เตือนต่ออายุวันนี้' : `เตือนต่ออายุอีก ${diff} วัน`,
                color: '#7c3aed',
                bg: '#f5f3ff',
                border: '#ddd6fe',
                icon: 'fa-repeat'
            };
        }

        function getTaskRecurringReminderMeta(task) {
            if (!task || (task.status || 'todo') === 'done') return [];
            const today = new Date();
            const day = today.getDate();
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate();
            const list = [];
            if (Number(task.remind_month_start || 0) === 1) {
                list.push({
                    category: 'month_start',
                    active: day <= 3,
                    label: day === 1 ? 'เตือนต้นเดือนวันนี้' : 'เตือนต้นเดือน',
                    color: '#1d4ed8',
                    bg: '#eff6ff',
                    border: '#bfdbfe',
                    icon: 'fa-calendar-day'
                });
            }
            if (Number(task.remind_month_end || 0) === 1) {
                list.push({
                    category: 'month_end',
                    active: day >= (lastDay - 2),
                    label: day === lastDay ? 'เตือนสิ้นเดือนวันนี้' : 'เตือนสิ้นเดือน',
                    color: '#7c2d12',
                    bg: '#fff7ed',
                    border: '#fed7aa',
                    icon: 'fa-calendar-check'
                });
            }
            return list;
        }

        function getTaskAlertCategories(task) {
            const categories = [];
            const deadlineMeta = getTaskDeadlineMeta(task);
            if (deadlineMeta.category !== 'none') categories.push(deadlineMeta.category);
            const renewalMeta = getTaskRenewalReminderMeta(task);
            if (renewalMeta && renewalMeta.active) categories.push(renewalMeta.category);
            getTaskRecurringReminderMeta(task).forEach(meta => {
                if (meta.active) categories.push(meta.category);
            });
            return categories;
        }

        function filterTasksByAlert(tasks, filterKey) {
            if (!filterKey || filterKey === 'all') return tasks;
            return tasks.filter(task => getTaskAlertCategories(task).includes(filterKey));
        }

        function setPersonalAlertFilter(filterKey) {
            personalAlertFilter = filterKey || 'all';
            renderPersonalBoard();
        }

        function getStatusOptionsByTaskType(taskType) {
            return [
                { value: 'todo', label: 'ยังไม่เริ่ม' },
                { value: 'doing', label: 'กำลังดำเนินการ' },
                { value: 'approve', label: 'รออนุมัติ' },
                { value: 'done', label: 'เสร็จสิ้น' }
            ];
        }

        function getStatusDisplayMap(taskType) {
            return {
                todo: {
                    label: 'ยังไม่เริ่ม',
                    color: '#475569',
                    bg: '#f1f5f9',
                    border: '#cbd5e1'
                },
                doing: {
                    label: 'กำลังดำเนินการ',
                    color: '#b45309',
                    bg: '#fef3c7',
                    border: '#fcd34d'
                },
                approve: {
                    label: 'รออนุมัติ',
                    color: '#7c3aed',
                    bg: '#ede9fe',
                    border: '#c4b5fd'
                },
                done: {
                    label: 'เสร็จสิ้น',
                    color: '#15803d',
                    bg: '#dcfce7',
                    border: '#86efac'
                }
            };
        }

        function getStatusDetailPresets(status) {
            const presetMap = {
                todo: ['รอเปิดงาน', 'รอนัดหมาย', 'รอจัดคิว', 'รอเริ่มดำเนินการ'],
                doing: ['ติดต่อราชการ', 'รอผู้รับเหมา', 'รอข้อมูลจากผู้แจ้ง', 'รออะไหล่', 'ติดตามผล', 'กำลังประสานงาน'],
                approve: ['รอหัวหน้าอนุมัติ', 'รอผู้บริหารอนุมัติ', 'รอเซ็นเอกสาร', 'รอตรวจรับ'],
                done: ['งานเสร็จเรียบร้อย', 'ส่งมอบแล้ว', 'ปิดงานแล้ว', 'ดำเนินการครบถ้วน']
            };
            return presetMap[status] || [];
        }

        function updatePersonalStatusBadge() {
            const taskType = document.getElementById('personalTaskType')?.value || 'personal';
            const status = document.getElementById('personalStatus')?.value || 'todo';
            const badge = document.getElementById('personalStatusBadge');
            if (!badge) return;
            const displayMap = getStatusDisplayMap(taskType);
            const info = displayMap[status] || displayMap.todo;
            badge.style.background = info.bg;
            badge.style.color = info.color;
            badge.style.borderColor = info.border;
            badge.innerHTML = `<i class="fas fa-circle" style="font-size:8px;"></i><span>${info.label}</span>`;
        }

        function applyStatusDetailPreset(value) {
            const input = document.getElementById('personalStatusDetail');
            if (!input) return;
            input.value = value;
            savePersonalDraftToStorage();
        }

        function escapeForSingleQuoteJs(value) {
            return String(value || '')
                .replace(/\\/g, '\\\\')
                .replace(/'/g, "\\'")
                .replace(/\r?\n/g, ' ');
        }

        function getCompanyVisual(code) {
            const map = {
                PTA: { color: '#15803d', bg: '#dcfce7', border: '#86efac' },
                PT4: { color: '#b91c1c', bg: '#fee2e2', border: '#fca5a5' },
                PTE: { color: '#1d4ed8', bg: '#dbeafe', border: '#93c5fd' }
            };
            return map[String(code || '').toUpperCase()] || { color: '#475569', bg: '#f1f5f9', border: '#cbd5e1' };
        }

        function renderStatusDetailPresets() {
            const status = document.getElementById('personalStatus')?.value || 'todo';
            const listEl = document.getElementById('personalStatusDetailList');
            const quickEl = document.getElementById('personalStatusDetailQuick');
            const presets = getStatusDetailPresets(status);
            if (listEl) {
                listEl.innerHTML = presets.map(item => `<option value="${item}"></option>`).join('');
            }
            if (quickEl) {
                quickEl.innerHTML = presets.map(item =>
                    `<button type="button" onclick="applyStatusDetailPreset('${escapeForSingleQuoteJs(item)}')" style="padding:5px 10px;border:1px solid #dbeafe;background:#f8fbff;color:#2563eb;border-radius:999px;font-size:11px;font-weight:600;cursor:pointer;">${item}</button>`
                ).join('');
            }
        }

        function deriveTaskStateForType(taskType, sourceTask) {
            const currentStatus = sourceTask?.status || 'todo';
            const currentStage = sourceTask?.workflow_stage || '';
            let status = ['todo', 'doing', 'approve', 'done'].includes(currentStatus)
                ? currentStatus
                : (currentStage === 'approve' ? 'approve' : (currentStage === 'done' ? 'done' : (currentStage === 'todo' ? 'todo' : 'doing')));
            return {
                status,
                workflow_stage: status === 'approve' ? 'approve' : (status === 'done' ? 'done' : (status === 'doing' ? 'doing' : 'todo'))
            };
        }

        function bindPersonalUIEvents() {
            const searchInput = document.getElementById('personalSearch');
            const prioritySelect = document.getElementById('personalFilterPriority');
            const sortSelect = document.getElementById('personalSort');
            const companyFilterSelect = document.getElementById('personalFilterCompany');
            const settingsBtn = document.getElementById('btn-personal-settings');
            const form = document.getElementById('personalForm');
            const taskTypeInput = document.getElementById('personalTaskType');
            const companyInput = document.getElementById('personalCompany');
            const buildingInput = document.getElementById('personalBuilding');
            const titleInput = document.getElementById('personalTitle');
            const assigneeInput = document.getElementById('personalAssignee');
            const descInput = document.getElementById('personalDescription');
            const dueInput = document.getElementById('personalDueDate');
            const remindStartInput = document.getElementById('personalRemindMonthStart');
            const remindEndInput = document.getElementById('personalRemindMonthEnd');
            const recurringInput = document.getElementById('personalIsRecurring');
            const recurringIntervalInput = document.getElementById('personalRecurringIntervalMonths');
            const remindDaysBeforeInput = document.getElementById('personalRemindDaysBefore');
            const statusInput = document.getElementById('personalStatus');
            const statusDetailInput = document.getElementById('personalStatusDetail');
            const priorityInput = document.getElementById('personalPriority');
            const tagsInput = document.getElementById('personalTags');
            const shareInput = document.getElementById('personalShare');
            if (shareInput) {
                shareInput.addEventListener('change', updatePersonalShareInfo);
            }
            if (taskTypeInput) {
                taskTypeInput.addEventListener('change', updateTaskTypeFormState);
            }
            if (statusInput) {
                statusInput.addEventListener('change', () => {
                    updatePersonalStatusBadge();
                    renderStatusDetailPresets();
                    savePersonalDraftToStorage();
                });
            }

            searchInput.addEventListener('input', renderPersonalBoard);
            prioritySelect.addEventListener('change', renderPersonalBoard);
            if (companyFilterSelect) {
                companyFilterSelect.addEventListener('change', () => {
                    personalCompanyFilter = companyFilterSelect.value || 'all';
                    loadPersonalTasks(false);
                });
            }
            sortSelect.addEventListener('change', () => {
                personalSettings.defaultSort = sortSelect.value;
                savePersonalSettingsToStorage();
                renderPersonalBoard();
            });
            if (settingsBtn) settingsBtn.addEventListener('click', openPersonalSettings);

            const inputs = [taskTypeInput, companyInput, buildingInput, titleInput, assigneeInput, descInput, dueInput, statusInput, statusDetailInput, priorityInput, tagsInput, shareInput];
            inputs.filter(Boolean).forEach(el => el.addEventListener('input', savePersonalDraftToStorage));
            if (remindStartInput) remindStartInput.addEventListener('change', savePersonalDraftToStorage);
            if (remindEndInput) remindEndInput.addEventListener('change', savePersonalDraftToStorage);
            if (recurringInput) recurringInput.addEventListener('change', () => {
                toggleRecurringFields();
                savePersonalDraftToStorage();
            });
            if (recurringIntervalInput) recurringIntervalInput.addEventListener('change', savePersonalDraftToStorage);
            if (remindDaysBeforeInput) remindDaysBeforeInput.addEventListener('change', savePersonalDraftToStorage);

            hydratePersonalCompanyControls();
            updateTaskTypeFormState();
            toggleRecurringFields();
            form.addEventListener('submit', handlePersonalFormSubmit);
        }

        function toggleRecurringFields() {
            const recurringInput = document.getElementById('personalIsRecurring');
            const intervalInput = document.getElementById('personalRecurringIntervalMonths');
            const remindDaysInput = document.getElementById('personalRemindDaysBefore');
            if (!recurringInput || !intervalInput || !remindDaysInput) return;
            const enabled = !!recurringInput.checked;
            intervalInput.disabled = !enabled;
            remindDaysInput.disabled = !enabled;
            if (!enabled) {
                intervalInput.value = intervalInput.value || '12';
                remindDaysInput.value = '0';
            } else {
                // Reasonable defaults for renewal tasks
                if (!intervalInput.value) intervalInput.value = '12';
                if (!remindDaysInput.value) remindDaysInput.value = '30';
            }
        }

        function isDarkMode() { return document.documentElement.classList.contains('dark'); }

        function renderPersonalDeadlineAlerts(tasks) {
            const host = document.getElementById('personalDeadlineAlerts');
            if (!host) return;
            const dark = isDarkMode();

            const actionableTasks = (tasks || []).filter(t => (t.status || 'todo') !== 'done');
            const grouped = { overdue: [], today: [], soon: [], renewal_due: [], month_start: [], month_end: [] };
            actionableTasks.forEach(task => {
                const meta = getTaskDeadlineMeta(task);
                if (grouped[meta.category]) grouped[meta.category].push(Object.assign({}, task, { deadline_meta: meta }));
                const renewalMeta = getTaskRenewalReminderMeta(task);
                if (renewalMeta && renewalMeta.active && grouped[renewalMeta.category]) {
                    grouped[renewalMeta.category].push(Object.assign({}, task, { deadline_meta: renewalMeta }));
                }
                getTaskRecurringReminderMeta(task).forEach(remindMeta => {
                    if (remindMeta.active && grouped[remindMeta.category]) {
                        grouped[remindMeta.category].push(Object.assign({}, task, { deadline_meta: remindMeta }));
                    }
                });
            });

            const totalAlerts = grouped.overdue.length + grouped.today.length + grouped.soon.length + grouped.renewal_due.length + grouped.month_start.length + grouped.month_end.length;
            if (!totalAlerts) {
                host.innerHTML = `
                    <div class="bg-white rounded-2xl shadow-sm border border-emerald-100 p-4">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:42px;height:42px;border-radius:14px;background:#ecfdf5;color:#16a34a;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-slate-800">ไม่มีงานใกล้ครบกำหนดในช่วงนี้</div>
                                <div class="text-xs text-slate-500">งานที่มีกำหนดและยังไม่เสร็จจะขึ้นเตือนตรงนี้อัตโนมัติ</div>
                            </div>
                        </div>
                    </div>
                `;
                return;
            }

            const alertCards = dark ? [
                { key: 'overdue', label: 'เกินกำหนด', count: grouped.overdue.length, icon: 'fa-triangle-exclamation', bg: '#4a1515', color: '#f87171', border: '#7f1d1d' },
                { key: 'today', label: 'ครบกำหนดวันนี้', count: grouped.today.length, icon: 'fa-clock', bg: '#4a2a10', color: '#fb923c', border: '#7c3a10' },
                { key: 'soon', label: 'ใกล้ครบกำหนด', count: grouped.soon.length, icon: 'fa-bell', bg: '#4a3510', color: '#fbbf24', border: '#7c5a10' },
                { key: 'renewal_due', label: 'เตือนต่ออายุ', count: grouped.renewal_due.length, icon: 'fa-repeat', bg: '#2e1f4a', color: '#a78bfa', border: '#4c3575' },
                { key: 'month_start', label: 'เตือนต้นเดือน', count: grouped.month_start.length, icon: 'fa-calendar-day', bg: '#15294a', color: '#60a5fa', border: '#1e3a6e' },
                { key: 'month_end', label: 'เตือนสิ้นเดือน', count: grouped.month_end.length, icon: 'fa-calendar-check', bg: '#3a2410', color: '#e08a4c', border: '#5c3a1a' }
            ] : [
                { key: 'overdue', label: 'เกินกำหนด', count: grouped.overdue.length, icon: 'fa-triangle-exclamation', bg: '#fef2f2', color: '#b91c1c', border: '#fecaca' },
                { key: 'today', label: 'ครบกำหนดวันนี้', count: grouped.today.length, icon: 'fa-clock', bg: '#fff7ed', color: '#c2410c', border: '#fdba74' },
                { key: 'soon', label: 'ใกล้ครบกำหนด', count: grouped.soon.length, icon: 'fa-bell', bg: '#fefce8', color: '#a16207', border: '#fde68a' },
                { key: 'renewal_due', label: 'เตือนต่ออายุ', count: grouped.renewal_due.length, icon: 'fa-repeat', bg: '#f5f3ff', color: '#7c3aed', border: '#ddd6fe' },
                { key: 'month_start', label: 'เตือนต้นเดือน', count: grouped.month_start.length, icon: 'fa-calendar-day', bg: '#eff6ff', color: '#1d4ed8', border: '#bfdbfe' },
                { key: 'month_end', label: 'เตือนสิ้นเดือน', count: grouped.month_end.length, icon: 'fa-calendar-check', bg: '#fff7ed', color: '#7c2d12', border: '#fed7aa' }
            ];
            const cardBgIdle = dark ? '#1e293b' : '#fff';
            const cardTextColor = dark ? '#f1f5f9' : '#0f172a';
            const cardMetaColor = dark ? '#94a3b8' : '#64748b';
            const chipBorderIdle = dark ? '#334155' : '#e2e8f0';
            const chipBgIdle = dark ? '#1e293b' : '#fff';
            const chipTextIdle = dark ? '#cbd5e1' : '#475569';
            const chipBorderActive = dark ? '#1e3a6e' : '#bfdbfe';
            const chipBgActive = dark ? '#15294a' : '#eff6ff';
            const chipTextActive = dark ? '#60a5fa' : '#1d4ed8';

            const topItems = [...grouped.overdue, ...grouped.today, ...grouped.soon, ...grouped.renewal_due, ...grouped.month_start, ...grouped.month_end]
                .sort((a, b) => {
                    const da = a.due_date ? new Date(a.due_date + 'T00:00:00').getTime() : Number.MAX_SAFE_INTEGER;
                    const db = b.due_date ? new Date(b.due_date + 'T00:00:00').getTime() : Number.MAX_SAFE_INTEGER;
                    return da - db;
                })
                .slice(0, 5);

            host.innerHTML = `
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4" style="background:${cardBgIdle} !important;border-color:${chipBorderIdle} !important;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
                        <div>
                            <div class="text-sm font-semibold" style="color:${cardTextColor};">แจ้งเตือนกำหนดงาน</div>
                            <div class="text-xs" style="color:${cardMetaColor};">คลิกการ์ดเพื่อกรองงานที่ต้องติดตามทันที</div>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="button" onclick="setPersonalAlertFilter('all')" style="padding:7px 12px;border-radius:999px;border:1px solid ${personalAlertFilter === 'all' ? chipBorderActive : chipBorderIdle};background:${personalAlertFilter === 'all' ? chipBgActive : chipBgIdle};color:${personalAlertFilter === 'all' ? chipTextActive : chipTextIdle};font-size:12px;font-weight:700;">ทั้งหมด</button>
                            ${personalAlertFilter !== 'all' ? `<button type="button" onclick="setPersonalAlertFilter('all')" style="padding:7px 12px;border-radius:999px;border:1px solid ${chipBorderIdle};background:${chipBgIdle};color:${chipTextIdle};font-size:12px;font-weight:600;">ล้างตัวกรองเตือน</button>` : ''}
                        </div>
                    </div>
                    <div class="grid gap-3 md:grid-cols-5" style="margin-bottom:14px;">
                        ${alertCards.map(card => `
                            <button type="button" onclick="setPersonalAlertFilter('${card.key}')" style="text-align:left;padding:14px 16px;border-radius:18px;border:1px solid ${personalAlertFilter === card.key ? card.color : card.border};background:${personalAlertFilter === card.key ? card.bg : cardBgIdle};transition:.15s;">
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                                    <div>
                                        <div style="font-size:12px;font-weight:700;color:${card.color};">${card.label}</div>
                                        <div style="font-size:24px;font-weight:800;color:${cardTextColor};line-height:1.1;margin-top:4px;">${card.count}</div>
                                    </div>
                                    <div style="width:42px;height:42px;border-radius:14px;background:${card.bg};color:${card.color};display:flex;align-items:center;justify-content:center;border:1px solid ${card.border};">
                                        <i class="fas ${card.icon}"></i>
                                    </div>
                                </div>
                            </button>
                        `).join('')}
                    </div>
                    <div style="border-top:1px solid ${chipBorderIdle};padding-top:12px;">
                        <div class="text-xs font-semibold" style="margin-bottom:8px;color:${cardMetaColor};">รายการที่ควรติดตามก่อน</div>
                        <div style="display:grid;gap:8px;">
                            ${topItems.map(item => `
                                <button type="button" onclick="openPersonalTaskModal(${item.id})" style="text-align:left;padding:10px 12px;border:1px solid ${item.deadline_meta.border};background:${item.deadline_meta.bg};border-radius:14px;">
                                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
                                        <div style="min-width:0;">
                                            <div style="font-size:13px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${item.title || ''}</div>
                                            <div style="font-size:11px;color:#64748b;margin-top:2px;">${item.company || '-'}${item.building ? ' / ' + item.building : ''}${item.assignee_name ? ' / ' + item.assignee_name : ''}</div>
                                        </div>
                                        <div style="font-size:11px;font-weight:700;color:${item.deadline_meta.color};white-space:nowrap;">${item.deadline_meta.label}</div>
                                    </div>
                                </button>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `;
        }

        function renderPersonalBoard() {
            const grpLists = {
                overdue: document.getElementById('list-overdue'),
                doing: document.getElementById('list-doing'),
                todo: document.getElementById('list-todo'),
                done: document.getElementById('list-done')
            };
            const grpContainers = {
                overdue: document.getElementById('grp-overdue'),
                doing: document.getElementById('grp-doing'),
                todo: document.getElementById('grp-todo'),
                done: document.getElementById('grp-done')
            };
            const grpCounts = {
                overdue: document.getElementById('grp-count-overdue'),
                doing: document.getElementById('grp-count-doing'),
                todo: document.getElementById('grp-count-todo'),
                done: document.getElementById('grp-count-done')
            };
            const emptyState = document.getElementById('personalEmpty');
            const search = document.getElementById('personalSearch').value.toLowerCase();
            const priorityFilter = document.getElementById('personalFilterPriority').value;
            const sortBy = document.getElementById('personalSort').value;
            const hideDone = personalSettings.hideDone;

            Object.values(grpLists).forEach(el => { if (el) el.innerHTML = ''; });

            let tasks = personalTasks.slice();

            if (search) {
                tasks = tasks.filter(t => {
                    const text = (t.title || '') + ' ' + (t.description || '') + ' ' + (t.status_detail || '') + ' ' + (t.tags || '');
                    return text.toLowerCase().includes(search);
                });
            }

            if (priorityFilter) {
                tasks = tasks.filter(t => (t.priority || 'normal') === priorityFilter);
            }

            // Pill filter from metrics strip (all / todo / doing / done / overdue)
            const pillFilter = (document.getElementById('personalSection') || {}).dataset?.pillFilter || '';
            if (pillFilter && pillFilter !== 'all') {
                if (pillFilter === 'overdue') {
                    const today = new Date(); today.setHours(0,0,0,0);
                    tasks = tasks.filter(t => t.due_date && new Date(t.due_date) < today && (t.status || 'todo') !== 'done');
                } else {
                    tasks = tasks.filter(t => {
                        const s = t.status || 'todo';
                        const bucket = s === 'done' ? 'done' : (s === 'todo' ? 'todo' : 'doing');
                        return bucket === pillFilter;
                    });
                }
            }

            renderPersonalDeadlineAlerts(tasks);
            tasks = filterTasksByAlert(tasks, personalAlertFilter);
            updateJnKpis(tasks);

            tasks.sort((a, b) => {
                if (sortBy === 'priority') {
                    const order = { urgent: 3, high: 2, normal: 1, low: 0 };
                    return (order[(b.priority || 'normal')] || 0) - (order[(a.priority || 'normal')] || 0);
                }
                if (sortBy === 'created_at') {
                    return new Date(b.created_at) - new Date(a.created_at);
                }
                if (sortBy === 'updated_at') {
                    return new Date(b.updated_at) - new Date(a.updated_at);
                }
                const da = a.due_date ? new Date(a.due_date) : null;
                const db = b.due_date ? new Date(b.due_date) : null;
                if (da && db) return da - db;
                if (da && !db) return -1;
                if (!da && db) return 1;
                return new Date(b.created_at) - new Date(a.created_at);
            });

            const counts = { todo: 0, doing: 0, done: 0 };
            const today = new Date(); today.setHours(0, 0, 0, 0);
            const groupBuckets = { overdue: [], doing: [], todo: [], done: [] };

            tasks.forEach(t => {
                const status = t.status || 'todo';
                if (hideDone && status === 'done') return;
                const bucket = status === 'done' ? 'done' : (status === 'todo' ? 'todo' : 'doing');
                counts[bucket] = (counts[bucket] || 0) + 1;

                const due = t.due_date ? new Date(t.due_date + 'T00:00:00') : null;
                const isOverdue = due && due < today && status !== 'done';
                if (isOverdue) groupBuckets.overdue.push(t);
                else groupBuckets[bucket].push(t);
            });

            Object.keys(groupBuckets).forEach(key => {
                const list = grpLists[key];
                if (!list) return;
                groupBuckets[key].forEach(t => list.appendChild(buildPersonalCard(t)));
                if (grpCounts[key]) grpCounts[key].textContent = String(groupBuckets[key].length);
                if (grpContainers[key]) grpContainers[key].style.display = groupBuckets[key].length ? '' : 'none';
            });

            const totalVisible = counts.todo + counts.doing + (hideDone ? 0 : counts.done);
            emptyState.classList.toggle('hidden', totalVisible > 0);

            updatePersonalCharts(tasks, counts);
        }

        function buildPersonalCard(task) {
            const card = document.createElement('div');
            const priority = task.priority || 'normal';
            const companies = (task.responsible_companies || task.company || '')
                .split(',')
                .map(s => s.trim().toUpperCase())
                .filter(Boolean);
            const mainCompany = companies[0] || String(task.company || '').toUpperCase();
            const companyClass = ['PTA', 'PT4', 'PTE'].includes(mainCompany) ? ` company-${mainCompany}` : '';
            card.className = `personal-card priority-${priority}${companyClass}`;
            const isJobTicket = (task.task_type || 'personal') === 'job_ticket';
            const isStatusPending = personalStatusPending.has(Number(task.id));

            // Priority
            const priorityMap = { urgent: { label: '🔴 เร่งด่วน' }, high: { label: '🟡 สำคัญ' }, normal: { label: '🔵 ปกติ' }, low: { label: '⚪ ต่ำ' } };
            const pInfo = priorityMap[priority] || priorityMap.normal;

            // Deadline
            let deadlineHtml = '';
            let deadlineAlertBadge = '';
            if (task.due_date) {
                const today = new Date(); today.setHours(0,0,0,0);
                const due = new Date(task.due_date + 'T00:00:00'); due.setHours(0,0,0,0);
                const diff = (due - today) / 86400000;
                let cls = 'pc-deadline';
                let icon = '📅';
                let txt = task.due_date;
                if (diff < 0) { cls += ' late'; icon = '🚨'; txt = task.due_date + ' (เกิน ' + Math.abs(Math.round(diff)) + ' วัน)'; }
                else if (diff === 0) { cls += ' soon'; icon = '⏰'; txt = 'วันนี้'; }
                else if (diff <= 3) { cls += ' soon'; icon = '⚡'; txt = 'อีก ' + Math.round(diff) + ' วัน'; }
                deadlineHtml = `<span class="${cls}">${icon} ${txt}</span>`;
            } else {
                deadlineHtml = `<span class="pc-deadline">📅 ไม่ระบุวัน</span>`;
            }
            const deadlineMeta = getTaskDeadlineMeta(task);
            if (deadlineMeta.category !== 'none') {
                deadlineAlertBadge = `<span class="pc-tag" style="background:${deadlineMeta.bg};color:${deadlineMeta.color};border:1px solid ${deadlineMeta.border};">${deadlineMeta.label}</span>`;
            }

            // Tags
            const tags = (task.tags || '').split(',').map(t => t.trim()).filter(Boolean);
            const tagsHtml = tags.map(t => `<span class="pc-tag">#${t}</span>`).join('');

            // Progress
            const status = task.status || 'todo';
            const fallbackPct = { todo: 0, doing: 50, approve: 90, done: 100 };
            const rawPct = Number(task.progress_pct || 0);
            const pct = Math.max(0, Math.min(100, (status !== 'todo' && rawPct === 0) ? (fallbackPct[status] ?? 0) : rawPct));
            const progColor = pct >= 70 ? '#22c55e' : pct >= 40 ? '#f59e0b' : '#3b82f6';

            // Status
            const statusMap = {
                todo: { label: 'ยังไม่เริ่ม', cls: '' },
                doing: { label: 'กำลังดำเนินการ', cls: 'doing' },
                approve: { label: 'รออนุมัติ', cls: 'doing' },
                done: { label: 'เสร็จสิ้น', cls: 'done' }
            };
            const sInfo = statusMap[status] || statusMap.todo;
            const canManage = canManageTask(task);
            const quickStatuses = [
                { value: 'todo', label: 'ยังไม่เริ่ม' },
                { value: 'doing', label: 'กำลังทำ' },
                { value: 'approve', label: 'รออนุมัติ' },
                { value: 'done', label: 'เสร็จสิ้น' }
            ];

            // Meta (compact)
            const owner = task.owner_name_th || task.owner_name || '';
            const assignee = task.assignee_name || '';
            const shareLabel = getShareLabel(task.share_scope, task.company, task.department);
            const updatedAt = task.updated_at ? new Date(task.updated_at).toLocaleDateString('th-TH', {day:'numeric',month:'short'}) : '';

            const metaItems = [];
            if (owner) metaItems.push(`<span class="pc-meta-item"><i class="fas fa-user" style="font-size:9px"></i> ${owner}</span>`);
            if (assignee && assignee !== owner) metaItems.push(`<span class="pc-meta-item"><i class="fas fa-user-check" style="font-size:9px"></i> ${assignee}</span>`);
            if (companies.length) metaItems.push(`<span class="pc-meta-item"><i class="fas fa-building" style="font-size:9px"></i> ${companies.join(', ')}${task.building ? ' / ' + task.building : ''}</span>`);
            const renewalMeta = getTaskRenewalReminderMeta(task);
            const recurringBaseBadge = Number(task.is_recurring || 0) === 1
                ? `<span class="pc-tag" style="background:#f5f3ff;color:#7c3aed;border:1px solid #ddd6fe;"><i class="fas fa-repeat" style="font-size:10px;"></i> งานประจำทุก ${Number(task.recurring_interval_months || 12)} เดือน</span>`
                : '';
            const renewalBadge = renewalMeta
                ? `<span class="pc-tag pc-reminder-strong" style="background:${renewalMeta.bg};color:${renewalMeta.color};border:1px solid ${renewalMeta.border};"><i class="fas ${renewalMeta.icon}" style="font-size:10px;"></i> ${renewalMeta.label}</span>`
                : '';
            const recurringBadges = (recurringBaseBadge ? [recurringBaseBadge] : [])
                .concat(renewalBadge ? [renewalBadge] : [])
                .concat(getTaskRecurringReminderMeta(task).map(meta =>
                `<span class="pc-tag ${meta.active ? 'pc-reminder-strong' : ''}" style="background:${meta.bg};color:${meta.color};border:1px solid ${meta.border};"><i class="fas ${meta.icon}" style="font-size:10px;"></i> ${meta.label}</span>`
            )).join('');
            const companyChips = companies.map(code => {
                const visual = getCompanyVisual(code);
                return `<span class="pc-company-chip" style="background:${visual.bg};color:${visual.color};border:1px solid ${visual.border};"><i class="fas fa-building"></i>${code}</span>`;
            }).join('');
            if (task.status_detail) metaItems.push(`<span class="pc-meta-item"><i class="fas fa-comment-dots" style="font-size:9px"></i> ${task.status_detail}</span>`);
            if (shareLabel !== 'เฉพาะฉัน') metaItems.push(`<span class="pc-meta-item"><i class="fas fa-share-alt" style="font-size:9px"></i> ${shareLabel}</span>`);
            if (updatedAt) metaItems.push(`<span class="pc-meta-item"><i class="fas fa-history" style="font-size:9px"></i> ${updatedAt}</span>`);

            card.innerHTML = `
                <div style="display:flex; height:100%;">
                    <div class="card-stripe"></div>
                    <div style="flex:1; padding:14px 16px; min-width:0;">
                        <div class="pc-header">
                            <div style="min-width:0; flex:1;">
                                <div class="pc-title">${task.title || ''}</div>
                                <div style="display:flex; gap:6px; align-items:center; margin-top:5px; flex-wrap:wrap;">
                                    <span class="pc-tag" style="${isJobTicket ? 'background:#ede9fe;color:#7c3aed;' : 'background:#eff6ff;color:#2563eb;'}">${isJobTicket ? 'งานส่วนกลาง' : 'งานส่วนตัว'}</span>
                                    ${companyChips}
                                    <span class="pc-priority">${pInfo.label}</span>
                                    ${deadlineAlertBadge}
                                    ${deadlineHtml}
                                </div>
                            </div>
                            <div class="pc-actions">
                                ${canManage ? `
                                    <span class="pc-status-btn ${sInfo.cls}" style="cursor:default;pointer-events:none;">${sInfo.label}</span>
                                    <button type="button" class="pc-delete-btn" onclick="event.stopPropagation(); deletePersonalTask(${task.id})"><i class="fas fa-times"></i></button>
                                ` : `<span style="font-size:10px; color:#94a3b8">อ่านอย่างเดียว</span>`}
                            </div>
                        </div>

                        ${task.description ? `<div class="pc-desc">${task.description}</div>` : ''}
                        ${(tagsHtml || recurringBadges) ? `<div style="margin-bottom:8px;display:flex;gap:6px;flex-wrap:wrap;">${tagsHtml}${recurringBadges}</div>` : ''}

                        <div style="margin-bottom:10px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                <span style="font-size:10px; color:#94a3b8; font-weight:500;">ความคืบหน้า</span>
                                <span style="font-size:11px; font-weight:700; color:${progColor};">${pct}%</span>
                            </div>
                            <div class="pc-progress-bar">
                                <div class="pc-progress-fill" style="width:${pct}%; background:${progColor};"></div>
                            </div>
                        </div>

                        ${canManage ? `
                            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;">
                                ${quickStatuses.map(item => `
                                    <button
                                        type="button"
                                        onclick="event.stopPropagation(); quickSetTaskStatus(${task.id}, '${item.value}')"
                                        class="pc-quick-btn ${status === item.value ? 'active' : ''} ${isStatusPending ? 'is-pending' : ''}"
                                        ${isStatusPending ? 'disabled' : ''}
                                    >${item.label}</button>
                                `).join('')}
                            </div>
                        ` : ''}

                        <div class="pc-footer">
                            <div class="pc-meta">${metaItems.join('')}</div>
                            <button type="button" class="pc-update-btn" onclick="event.stopPropagation(); openCommentModal(${task.id}, '${escapeForSingleQuoteJs(task.title || '')}')">
                                <i class="fas fa-pen"></i> แก้ไข
                            </button>
                        </div>
                    </div>
                </div>
            `;

            card.onclick = () => openPersonalTaskModal(task.id);
            return card;
        }

        function openPersonalTaskModal(id) {
            const modal = document.getElementById('personalModal');
            const titleEl = document.getElementById('personalModalTitle');
            const idEl = document.getElementById('personalTaskId');
            const taskTypeInput = document.getElementById('personalTaskType');
            const companyInput = document.getElementById('personalCompany');
            const buildingInput = document.getElementById('personalBuilding');
            const titleInput = document.getElementById('personalTitle');
            const assigneeInput = document.getElementById('personalAssignee');
            const descInput = document.getElementById('personalDescription');
            const dueInput = document.getElementById('personalDueDate');
            const remindStartInput = document.getElementById('personalRemindMonthStart');
            const remindEndInput = document.getElementById('personalRemindMonthEnd');
            const recurringInput = document.getElementById('personalIsRecurring');
            const recurringIntervalInput = document.getElementById('personalRecurringIntervalMonths');
            const remindDaysBeforeInput = document.getElementById('personalRemindDaysBefore');
            const statusInput = document.getElementById('personalStatus');
            const statusDetailInput = document.getElementById('personalStatusDetail');
            const priorityInput = document.getElementById('personalPriority');
            const tagsInput = document.getElementById('personalTags');
            const metaEl = document.getElementById('personalMeta');
            const shareInfoEl = document.getElementById('personalShareInfo');

            let task = null;
            if (id) task = personalTasks.find(t => t.id == id);

            if (task) {
                titleEl.textContent = 'แก้ไขบันทึก';
                idEl.value = task.id;
                taskTypeInput.value = task.task_type || 'personal';
                setSelectedResponsibleCompanies((task.responsible_companies || task.company || '').split(',').map(s => s.trim()).filter(Boolean));
                buildingInput.value = task.building || '';
                titleInput.value = task.title || '';
                assigneeInput.value = task.assignee_name || '';
                descInput.value = task.description || '';
                dueInput.value = task.due_date || '';
                if (remindStartInput) remindStartInput.checked = Number(task.remind_month_start || 0) === 1;
                if (remindEndInput) remindEndInput.checked = Number(task.remind_month_end || 0) === 1;
                if (recurringInput) recurringInput.checked = Number(task.is_recurring || 0) === 1;
                if (recurringIntervalInput) recurringIntervalInput.value = String(task.recurring_interval_months || 12);
                if (remindDaysBeforeInput) remindDaysBeforeInput.value = String(task.remind_days_before || 0);
                renderStatusOptions(task.task_type || 'personal', task);
                statusDetailInput.value = task.status_detail || '';
                priorityInput.value = task.priority || 'normal';
                tagsInput.value = task.tags || '';
                const shareInput = document.getElementById('personalShare');
                if (shareInput && task.share_scope) shareInput.value = task.share_scope;
                const meta = [];
                if (task.created_at) meta.push('สร้าง ' + new Date(task.created_at).toLocaleString('th-TH'));
                if (task.updated_at && task.updated_at !== task.created_at) meta.push('แก้ไข ' + new Date(task.updated_at).toLocaleString('th-TH'));
                metaEl.textContent = meta.join(' • ');
                loadPersonalTaskHistory(task.id);
                updateTaskTypeFormState();
                toggleRecurringFields();
                setPersonalFormEditable(canManageTask(task));
            } else {
                titleEl.textContent = 'เพิ่มงานใหม่';
                idEl.value = '';
                const draft = loadPersonalDraftFromStorage();
                taskTypeInput.value = draft.task_type || 'personal';
                const defaultCompanies = Array.isArray(draft.responsible_companies) && draft.responsible_companies.length
                    ? draft.responsible_companies
                    : [draft.company || (personalScope === 'company' && personalCompanyFilter !== 'all' ? personalCompanyFilter : (getAccessibleCompanies()[0] || userSession.company || 'PTA'))];
                setSelectedResponsibleCompanies(defaultCompanies);
                buildingInput.value = draft.building || '';
                titleInput.value = draft.title || '';
                assigneeInput.value = draft.assignee_name || '';
                descInput.value = draft.description || '';
                dueInput.value = draft.due_date || '';
                if (remindStartInput) remindStartInput.checked = Number(draft.remind_month_start || 0) === 1;
                if (remindEndInput) remindEndInput.checked = Number(draft.remind_month_end || 0) === 1;
                if (recurringInput) recurringInput.checked = Number(draft.is_recurring || 0) === 1;
                if (recurringIntervalInput) recurringIntervalInput.value = String(draft.recurring_interval_months || 12);
                if (remindDaysBeforeInput) remindDaysBeforeInput.value = String(draft.remind_days_before || (Number(draft.is_recurring || 0) === 1 ? 30 : 0));
                renderStatusOptions(taskTypeInput.value || 'personal', { status: draft.status || 'todo', workflow_stage: draft.workflow_stage || draft.status || 'todo' });
                statusDetailInput.value = draft.status_detail || '';
                priorityInput.value = draft.priority || 'normal';
                tagsInput.value = draft.tags || '';
                const shareInput = document.getElementById('personalShare');
                if (shareInput) shareInput.value = draft.share_scope || (isHrGaUser() ? 'department' : 'private');
                if (!draft.share_scope && isHrGaUser()) applyHrGaNewTaskDefaults();
                metaEl.textContent = '';
                const historyEl = document.getElementById('personalHistory');
                if (historyEl) historyEl.innerHTML = '';
                updateTaskTypeFormState();
                toggleRecurringFields();
                setPersonalFormEditable(true);
            }

            if (shareInfoEl) {
                const scope = task ? task.share_scope : document.getElementById('personalShare').value;
                shareInfoEl.textContent = 'กำลังแชร์กับ: ' + getShareLabel(scope, task ? task.company : userSession.company, task ? task.department : userSession.primary_department);
            }

            modal.style.display = 'flex';
        }

        function closePersonalTaskModal() {
            document.getElementById('personalModal').style.display = 'none';
        }

        function savePersonalDraftToStorage() {
            const selectedCompanies = getSelectedResponsibleCompanies();
            const draft = {
                task_type: document.getElementById('personalTaskType').value,
                company: selectedCompanies[0] || '',
                responsible_companies: selectedCompanies,
                building: document.getElementById('personalBuilding').value,
                title: document.getElementById('personalTitle').value,
                assignee_name: document.getElementById('personalAssignee').value,
                description: document.getElementById('personalDescription').value,
                due_date: document.getElementById('personalDueDate').value,
                remind_month_start: document.getElementById('personalRemindMonthStart').checked ? 1 : 0,
                remind_month_end: document.getElementById('personalRemindMonthEnd').checked ? 1 : 0,
                is_recurring: document.getElementById('personalIsRecurring')?.checked ? 1 : 0,
                recurring_interval_months: Number(document.getElementById('personalRecurringIntervalMonths')?.value || 12),
                remind_days_before: Number(document.getElementById('personalRemindDaysBefore')?.value || 0),
                status: document.getElementById('personalStatus').value,
                workflow_stage: document.getElementById('personalStatus').value,
                status_detail: document.getElementById('personalStatusDetail').value,
                priority: document.getElementById('personalPriority').value,
                tags: document.getElementById('personalTags').value,
                share_scope: document.getElementById('personalShare').value
            };
            localStorage.setItem(personalDraftKey, JSON.stringify(draft));
        }

        function loadPersonalDraftFromStorage() {
            try {
                const raw = localStorage.getItem(personalDraftKey);
                if (!raw) return {};
                return JSON.parse(raw) || {};
            } catch (e) {
                return {};
            }
        }

        function clearPersonalDraft() {
            localStorage.removeItem(personalDraftKey);
            document.getElementById('personalTaskType').value = 'personal';
            setSelectedResponsibleCompanies([(personalScope === 'company' && personalCompanyFilter !== 'all' ? personalCompanyFilter : (getAccessibleCompanies()[0] || userSession.company || 'PTA'))]);
            document.getElementById('personalBuilding').value = '';
            document.getElementById('personalTitle').value = '';
            document.getElementById('personalAssignee').value = '';
            document.getElementById('personalDescription').value = '';
            document.getElementById('personalDueDate').value = '';
            document.getElementById('personalRemindMonthStart').checked = false;
            document.getElementById('personalRemindMonthEnd').checked = false;
            if (document.getElementById('personalIsRecurring')) document.getElementById('personalIsRecurring').checked = false;
            if (document.getElementById('personalRecurringIntervalMonths')) document.getElementById('personalRecurringIntervalMonths').value = '12';
            if (document.getElementById('personalRemindDaysBefore')) document.getElementById('personalRemindDaysBefore').value = '0';
            renderStatusOptions('personal', { status: 'todo', workflow_stage: 'todo' });
            document.getElementById('personalStatusDetail').value = '';
            renderStatusDetailPresets();
            document.getElementById('personalPriority').value = 'normal';
            document.getElementById('personalTags').value = '';
            document.getElementById('personalShare').value = isHrGaUser() ? 'department' : 'private';
            if (isHrGaUser()) applyHrGaNewTaskDefaults();
            updateTaskTypeFormState();
            toggleRecurringFields();
        }

        async function loadPersonalTaskHistory(id) {
            const historyEl = document.getElementById('personalHistory');
            if (!historyEl) return;
            historyEl.innerHTML = '';
            try {
                const res = await fetch(API_PATH + '?action=personal_task_history_get&task_id=' + encodeURIComponent(id));
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error(text);
                }
                if (data.status === 'success' && Array.isArray(data.items) && data.items.length) {
                    historyEl.innerHTML = data.items.map(h => {
                        const ts = h.created_at ? new Date(h.created_at).toLocaleString('th-TH') : '';
                        const user = h.user_name || h.user_id || '';
                        const parts = [];
                        if (h.old_status || h.new_status) {
                            parts.push('สถานะ: ' + (h.old_status || '-') + ' → ' + (h.new_status || '-'));
                        }
                        if (h.old_priority || h.new_priority) {
                            parts.push('ความสำคัญ: ' + (h.old_priority || '-') + ' → ' + (h.new_priority || '-'));
                        }
                        const detail = parts.join(' | ');
                        return `<div class="flex flex-col mb-1"><span>${ts} • ${user}</span>${detail ? `<span class="text-slate-400">${detail}</span>` : ''}</div>`;
                    }).join('');
                }
            } catch (e) {}
        }

        async function handlePersonalFormSubmit(e) {
            e.preventDefault();
            const id = document.getElementById('personalTaskId').value;
            const shareInput = document.getElementById('personalShare');
            const existing = id ? personalTasks.find(t => t.id == id) : null;
            const oldShare = existing ? (existing.share_scope || 'private') : null;
            const newShare = shareInput ? (shareInput.value || 'private') : 'private';
            if (oldShare && oldShare !== newShare) {
                const msg = 'ต้องการเปลี่ยนการแชร์จาก "' + getShareLabel(oldShare, existing.company, existing.department) +
                    '" เป็น "' + getShareLabel(newShare, existing.company, existing.department) + '" ใช่หรือไม่?';
                if (!confirm(msg)) {
                    return;
                }
            }
            const isRecurring = document.getElementById('personalIsRecurring')?.checked ? 1 : 0;
            const intervalMonths = Number(document.getElementById('personalRecurringIntervalMonths')?.value || 12);
            const remindDaysBefore = Number(document.getElementById('personalRemindDaysBefore')?.value || 0);
            const payload = {
                id: id || null,
                task_type: document.getElementById('personalTaskType').value || 'personal',
                company: getSelectedResponsibleCompanies()[0] || userSession.company || '',
                responsible_companies: getSelectedResponsibleCompanies(),
                building: document.getElementById('personalBuilding').value || '',
                title: document.getElementById('personalTitle').value,
                assignee_name: document.getElementById('personalAssignee').value,
                description: document.getElementById('personalDescription').value,
                due_date: document.getElementById('personalDueDate').value || null,
                remind_month_start: document.getElementById('personalRemindMonthStart').checked ? 1 : 0,
                remind_month_end: document.getElementById('personalRemindMonthEnd').checked ? 1 : 0,
                is_recurring: isRecurring,
                recurring_interval_months: intervalMonths,
                remind_days_before: remindDaysBefore,
                status_detail: document.getElementById('personalStatusDetail').value || '',
                priority: document.getElementById('personalPriority').value || 'normal',
                tags: document.getElementById('personalTags').value || '',
                share_scope: newShare
            };
            const selectedStatus = document.getElementById('personalStatus').value || 'todo';
            payload.status = selectedStatus;
            payload.workflow_stage = selectedStatus === 'approve' ? 'approve' : (selectedStatus === 'done' ? 'done' : (selectedStatus === 'doing' ? 'doing' : 'todo'));

            const btn = e.target.querySelector('button[type="submit"]');
            btn.disabled = true;
            try {
                const fd = new FormData();
                fd.append('data', JSON.stringify(payload));
                const res = await fetch(API_PATH + '?action=personal_tasks_save', { method: 'POST', body: fd });
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (err) {
                    throw new Error(text);
                }
                if (data.status === 'success' && data.task) {
                    const savedTask = normalizeTaskRecord(data.task);
                    const idx = personalTasks.findIndex(t => t.id == savedTask.id);
                    if (idx >= 0) personalTasks[idx] = savedTask;
                    else personalTasks.push(savedTask);
                    clearPersonalDraft();
                    renderPersonalBoard();
                    closePersonalTaskModal();
                    showToast('บันทึกงานเรียบร้อยแล้ว');
                } else {
                    throw new Error(data.message || 'บันทึกไม่สำเร็จ');
                }
            } catch (err) {
                console.error(err);
                showToast(err.message, true);
            }
            btn.disabled = false;
        }

        async function deletePersonalTask(id) {
            if (!confirm('คุณต้องการลบบันทึกนี้หรือไม่?')) return;
            try {
                const fd = new FormData();
                fd.append('id', id);
                const res = await fetch(API_PATH + '?action=personal_tasks_delete', { method: 'POST', body: fd });
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (err) {
                    throw new Error(text);
                }
                if (data.status === 'success') {
                    personalTasks = personalTasks.filter(t => t.id != id);
                    renderPersonalBoard();
                    showToast('ลบบันทึกเรียบร้อยแล้ว');
                } else {
                    throw new Error(data.message || 'ลบไม่สำเร็จ');
                }
            } catch (err) {
                console.error(err);
                showToast(err.message, true);
            }
        }

        async function quickSetTaskStatus(id, nextStatus) {
            const task = personalTasks.find(t => t.id == id);
            if (!task || !canManageTask(task)) return;
            if ((task.status || 'todo') === nextStatus) return;
            const taskId = Number(id);
            if (personalStatusPending.has(taskId)) return;

            const previous = Object.assign({}, task);
            const optimisticPct = { todo: 0, doing: 50, approve: 90, done: 100 };
            task.status = nextStatus;
            task.workflow_stage = nextStatus === 'approve' ? 'approve' : (nextStatus === 'done' ? 'done' : (nextStatus === 'doing' ? 'doing' : 'todo'));
            task.progress_pct = optimisticPct[nextStatus] ?? task.progress_pct;
            personalStatusPending.add(taskId);
            renderPersonalBoard();
            const cardEl = document.querySelector(`.pc-update-btn[onclick*="openCommentModal(${taskId},"]`)?.closest('.personal-card');
            if (cardEl) cardEl.classList.add('status-flash');

            const payload = Object.assign({}, task, {
                status: nextStatus,
                workflow_stage: nextStatus === 'approve' ? 'approve' : (nextStatus === 'done' ? 'done' : (nextStatus === 'doing' ? 'doing' : 'todo'))
            });
            payload.is_recurring = Number(task.is_recurring || 0);
            payload.recurring_interval_months = Number(task.recurring_interval_months || 12);
            payload.remind_days_before = Number(task.remind_days_before || 0);
            // Let backend recalculate progress based on new status to keep % consistent.
            delete payload.progress_pct;

            try {
                const fd = new FormData();
                fd.append('data', JSON.stringify(payload));
                const res = await fetch(API_PATH + '?action=personal_tasks_save', { method: 'POST', body: fd });
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (err) {
                    throw new Error(text);
                }
                if (data.status === 'success' && data.task) {
                    const savedTask = normalizeTaskRecord(data.task);
                    const idx = personalTasks.findIndex(t => t.id == savedTask.id);
                    if (idx >= 0) personalTasks[idx] = savedTask;
                    showToast('อัปเดตสถานะเรียบร้อยแล้ว');
                } else {
                    throw new Error(data.message || 'อัปเดตสถานะไม่สำเร็จ');
                }
            } catch (err) {
                const idx = personalTasks.findIndex(t => t.id == taskId);
                if (idx >= 0) personalTasks[idx] = previous;
                console.error(err);
                showToast(err.message, true);
            } finally {
                personalStatusPending.delete(taskId);
                renderPersonalBoard();
            }
        }

        function openPersonalSettings() {
            const modal = document.getElementById('personalSettingsModal');
            document.getElementById('setting-hide-done').checked = personalSettings.hideDone;
            document.getElementById('setting-default-sort').value = personalSettings.defaultSort || 'due_date';
            document.getElementById('setting-view-mode').value = personalSettings.viewMode || 'kanban';
            modal.style.display = 'flex';
        }

        function closePersonalSettings() {
            document.getElementById('personalSettingsModal').style.display = 'none';
        }

        function savePersonalSettings() {
            personalSettings.hideDone = document.getElementById('setting-hide-done').checked;
            personalSettings.defaultSort = document.getElementById('setting-default-sort').value || 'due_date';
            personalSettings.viewMode = document.getElementById('setting-view-mode').value || 'kanban';
            document.getElementById('personalSort').value = personalSettings.defaultSort;
            savePersonalSettingsToStorage();
            closePersonalSettings();
            renderPersonalBoard();
        }

        function resetPersonalSettings() {
            personalSettings = { hideDone: false, defaultSort: 'due_date', viewMode: 'kanban' };
            document.getElementById('setting-hide-done').checked = false;
            document.getElementById('setting-default-sort').value = 'due_date';
            document.getElementById('setting-view-mode').value = 'kanban';
            document.getElementById('personalSort').value = 'due_date';
            savePersonalSettingsToStorage();
            renderPersonalBoard();
        }

        function savePersonalSettingsToStorage() {
            try {
                localStorage.setItem(personalSettingsKey, JSON.stringify(personalSettings));
            } catch (e) {}
        }

        function loadPersonalSettingsFromStorage() {
            try {
                const raw = localStorage.getItem(personalSettingsKey);
                if (!raw) return;
                const parsed = JSON.parse(raw);
                personalSettings = Object.assign({}, personalSettings, parsed || {});
                document.getElementById('personalSort').value = personalSettings.defaultSort || 'due_date';
            } catch (e) {}
        }

        function shareCurrentTask() {
            const id = document.getElementById('personalTaskId').value;
            const task = personalTasks.find(t => t.id == id);
            const title = task ? task.title : document.getElementById('personalTitle').value;
            const desc = task ? task.description : document.getElementById('personalDescription').value;
            const due = task ? task.due_date : document.getElementById('personalDueDate').value;
            const text = `งาน: ${title || '-'}\nรายละเอียด: ${desc || '-'}\nกำหนด: ${due || '-'}`;

            if (navigator.share) {
                navigator.share({ title: title || 'Job Note', text }).catch(function() {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    showToast('คัดลอกข้อความสำหรับแชร์แล้ว');
                }).catch(function() {
                    showToast('ไม่สามารถคัดลอกข้อความได้', true);
                });
            } else {
                alert(text);
            }
        }

        function getShareLabel(scope, company, department) {
            if (scope === 'department') {
                return 'แผนก ' + (department || '');
            }
            if (scope === 'company') {
                return 'บริษัท ' + (company || '');
            }
            return 'เฉพาะฉัน';
        }

        function updatePersonalShareInfo() {
            const el = document.getElementById('personalShareInfo');
            const select = document.getElementById('personalShare');
            if (!el || !select) return;
            const scope = select.value || 'private';
            const company = getSelectedResponsibleCompanies()[0] || userSession.company;
            el.textContent = 'กำลังแชร์กับ: ' + getShareLabel(scope, company, userSession.primary_department);
        }

        function updateTaskTypeFormState() {
            const taskType = document.getElementById('personalTaskType')?.value || 'personal';
            const buildingWrap = document.getElementById('personalBuildingWrap');
            const shareSelect = document.getElementById('personalShare');
            const prioritySelect = document.getElementById('personalPriority');
            const statusSelect = document.getElementById('personalStatus');
            const currentTaskId = document.getElementById('personalTaskId')?.value;
            const existingTask = currentTaskId ? personalTasks.find(t => t.id == currentTaskId) : null;
            const currentSelection = statusSelect?.value || '';
            const currentFormState = currentSelection
                ? { status: currentSelection, workflow_stage: currentSelection === 'approve' ? 'approve' : (currentSelection === 'done' ? 'done' : (currentSelection === 'doing' ? 'doing' : 'todo')) }
                : deriveTaskStateForType(taskType, existingTask);
            if (buildingWrap) buildingWrap.classList.remove('hidden');
            if (shareSelect) shareSelect.disabled = false;
            if (prioritySelect) prioritySelect.disabled = false;
            renderStatusOptions(taskType, currentFormState);
            updatePersonalShareInfo();
        }

        function renderStatusOptions(taskType, sourceTask) {
            const select = document.getElementById('personalStatus');
            if (!select) return;
            const options = getStatusOptionsByTaskType(taskType);
            const derived = deriveTaskStateForType(taskType, sourceTask || {});
            const selectedValue = derived.status || 'todo';
            select.innerHTML = options.map(opt => `<option value="${opt.value}">${opt.label}</option>`).join('');
            select.value = options.some(opt => opt.value === selectedValue) ? selectedValue : options[0].value;
            updatePersonalStatusBadge();
            renderStatusDetailPresets();
        }

        function canManageTask(task) {
            if (!task) return false;
            const taskUserId = String(task.user_id || '').trim();
            const sessionUserId = String((userSession && userSession.user_id) || '').trim();
            if (!taskUserId || (sessionUserId && taskUserId === sessionUserId)) {
                return true;
            }
            const role = (userSession && userSession.role) || 'guest';
            if (!['admin', 'staff'].includes(role)) return false;
            if (role === 'admin') return true;

            const allowedCompanies = getAccessibleCompanies();
            const taskCompanies = (task.responsible_companies || task.company || '').split(',').map(s => s.trim()).filter(Boolean);
            const sameCompany = taskCompanies.some(code => allowedCompanies.includes(code));
            if (!sameCompany) return false;

            const scope = task.share_scope || 'private';
            if (scope === 'company') return true;
            if (scope === 'department') {
                return (task.department || '') === (userSession.primary_department || '');
            }
            return false;
        }

        function setPersonalFormEditable(canEdit) {
            const form = document.getElementById('personalForm');
            if (!form) return;
            const controls = form.querySelectorAll('input, textarea, select');
            const alwaysEditableIds = new Set([
                'personalCompany',
                'personalRemindMonthStart',
                'personalRemindMonthEnd',
                'personalIsRecurring',
                'personalRecurringIntervalMonths',
                'personalRemindDaysBefore'
            ]);
            controls.forEach(el => {
                if (el.id === 'personalTaskId') return;
                if (alwaysEditableIds.has(el.id) || (el.classList && el.classList.contains('personal-company-check'))) {
                    el.disabled = false;
                    return;
                }
                el.disabled = !canEdit;
            });
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.style.display = canEdit ? 'block' : 'none';
        }

        function updatePersonalCharts(tasks, counts) {
            const statusCanvas = document.getElementById('personalStatusChart');
            const ownerCanvas = document.getElementById('personalOwnerChart');
            if (!statusCanvas || !ownerCanvas) return;
            const statusCtx = statusCanvas.getContext('2d');
            const ownerCtx = ownerCanvas.getContext('2d');
            const statusData = [
                counts.todo || 0,
                counts.doing || 0,
                counts.done || 0
            ];
            if (personalStatusChart) personalStatusChart.destroy();
            personalStatusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['ยังไม่เริ่ม', 'กำลังดำเนินการ', 'เสร็จสิ้น'],
                    datasets: [{
                        data: statusData,
                        backgroundColor: ['#e5e7eb', '#fbbf24', '#22c55e']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
            const ownerMap = {};
            tasks.forEach(t => {
                const owner = t.owner_name_th || t.owner_name || t.user_id || 'ไม่ระบุ';
                ownerMap[owner] = (ownerMap[owner] || 0) + 1;
            });
            const ownerLabels = Object.keys(ownerMap);
            const ownerValues = ownerLabels.map(k => ownerMap[k]);
            if (personalOwnerChart) personalOwnerChart.destroy();
            personalOwnerChart = new Chart(ownerCtx, {
                type: 'bar',
                data: {
                    labels: ownerLabels,
                    datasets: [{
                        data: ownerValues,
                        backgroundColor: '#3b82f6'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: { beginAtZero: true }
                    }
                }
            });
        }

        // ==========================================
        // COMMENT & PROGRESS
        // ==========================================
        let commentTaskId = null;

        function openCommentModal(taskId, taskTitle) {
            commentTaskId = Number(taskId);
            document.getElementById('commentModalTitle').textContent = taskTitle || 'อัปเดตความคืบหน้า';
            document.getElementById('commentText').value = '';
            document.getElementById('commentHistory').innerHTML = '<div style="color:#94a3b8; font-size:12px">กำลังโหลด...</div>';

            const task = personalTasks.find(t => t.id == commentTaskId);
            const pct = task ? (task.progress_pct || 0) : 0;
            updateCommentProg(pct);
            document.getElementById('commentProgSlider').value = pct;

            document.getElementById('commentModal').style.display = 'flex';
            loadCommentHistory(commentTaskId);
        }

        function closeCommentModal() {
            document.getElementById('commentModal').style.display = 'none';
            commentTaskId = null;
        }

        function updateCommentProg(v) {
            const pct = parseInt(v);
            document.getElementById('commentProgFill').style.width = pct + '%';
            document.getElementById('commentProgNum').textContent = pct + '%';
            const col = pct >= 70 ? '#22c55e' : pct >= 40 ? '#f59e0b' : '#ef4444';
            document.getElementById('commentProgFill').style.background = col;
        }

        async function loadCommentHistory(taskId) {
            try {
                const res = await fetch(API_PATH + '?action=task_comment_get&task_id=' + taskId);
                const text = await res.text();
                let data;
                try { data = JSON.parse(text); } catch(e) { throw new Error(text); }
                if (data.status === 'success') {
                    // อัปเดต progress จาก server
                    const serverPct = data.progress_pct || 0;
                    updateCommentProg(serverPct);
                    document.getElementById('commentProgSlider').value = serverPct;

                    const histEl = document.getElementById('commentHistory');
                    if (!data.comments || data.comments.length === 0) {
                        histEl.innerHTML = '<div style="color:#94a3b8; font-size:12px; padding:8px 0">ยังไม่มี comment</div>';
                        return;
                    }
                    histEl.innerHTML = data.comments.map(c => {
                        const ts = c.created_at ? new Date(c.created_at).toLocaleString('th-TH') : '';
                        const initials = (c.user_name||'?').slice(0,2);
                        const progBadge = c.progress_pct !== null ? `<span style="font-size:10px; padding:1px 6px; background:#dcfce7; color:#16a34a; border-radius:999px; margin-left:6px;">${c.progress_pct}%</span>` : '';
                        return `<div style="display:flex; gap:8px; align-items:flex-start;">
                            <div style="width:28px; height:28px; border-radius:50%; background:#dbeafe; color:#1d4ed8; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:600; flex-shrink:0;">${initials}</div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-size:11px; color:#94a3b8; margin-bottom:2px;">${c.user_name||''} ${progBadge} · ${ts}</div>
                                <div style="font-size:13px; color:#334155;">${c.comment||''}</div>
                            </div>
                        </div>`;
                    }).join('');
                    histEl.scrollTop = histEl.scrollHeight;
                }
            } catch(e) {
                document.getElementById('commentHistory').innerHTML = '<div style="color:#ef4444; font-size:12px">โหลดไม่สำเร็จ</div>';
            }
        }

        async function saveComment() {
            if (!commentTaskId) return;
            const comment = document.getElementById('commentText').value.trim();
            const progress = parseInt(document.getElementById('commentProgSlider').value);

            if (!comment && progress === (personalTasks.find(t => t.id == commentTaskId)?.progress_pct || 0)) {
                showToast('กรุณาใส่ comment หรือเปลี่ยน %', true);
                return;
            }

            try {
                const fd = new FormData();
                fd.append('task_id', commentTaskId);
                fd.append('comment', comment);
                fd.append('progress', progress);
                const res = await fetch(API_PATH + '?action=task_comment_save', { method:'POST', body:fd });
                const text = await res.text();
                let data;
                try { data = JSON.parse(text); } catch(e) { throw new Error(text); }
                if (data.status === 'success') {
                    // อัปเดต local task
                    const idx = personalTasks.findIndex(t => t.id == commentTaskId);
                    if (idx >= 0) personalTasks[idx].progress_pct = progress;
                    showToast('บันทึก comment เรียบร้อยแล้ว');
                    document.getElementById('commentText').value = '';
                    loadCommentHistory(commentTaskId);
                    renderPersonalBoard();
                } else {
                    throw new Error(data.message || 'บันทึกไม่สำเร็จ');
                }
            } catch(e) {
                showToast(e.message, true);
            }
        }

        function showToast(msg, isError=false) {
            const t = document.getElementById('toast');
            t.innerHTML = isError ? `<i class="fas fa-exclamation-circle"></i> ${msg}` : `<i class="fas fa-check-circle"></i> ${msg}`;
            t.className = isError ? 'error show' : 'success show';
            setTimeout(() => t.className = t.className.replace('show', ''), 3000);
        }

        function toggleDark() {
            const html = document.documentElement;
            const dark = html.classList.toggle('dark');
            html.classList.toggle('dark-theme', dark);
            localStorage.setItem('theme', dark ? 'dark' : 'light');
            const icon = document.getElementById('themeIcon');
            if (icon) icon.className = dark ? 'fa fa-sun' : 'fa fa-moon';
        }
        (function initThemeIcon() {
            const dark = document.documentElement.classList.contains('dark');
            const icon = document.getElementById('themeIcon');
            if (icon) icon.className = dark ? 'fa fa-sun' : 'fa fa-moon';
        })();
