/**
 * Task Center HR&GA — board UI for task_center_hrga.html
 */
(function (global) {
    const HRGA_DEPT = 'HR and GA';
    const CO_BADGE = { PTA: 'b-pta', PT4: 'b-pt4', PTE: 'b-pte' };
    const CO_LABEL = { pta: 'PTA', pt4: 'PT4', pte: 'PTE' };

    let ctx = null;
    let board = null;
    let routineBoard = null;
    let screen = 'meeting';
    let companyFilter = 'all';
    let routineUserId = '';

    function getRoot() {
        return document.getElementById('hrgaApp') || document.getElementById('hrgaTaskCenter');
    }

    function isHrGaDept(dept) {
        let d = String(dept || '').trim().replace(/\s+/g, ' ');
        if (!d) return false;
        if (d.toLowerCase() === HRGA_DEPT.toLowerCase()) return true;
        if (/^hr\s*(?:&|and|และ)\s*ga$/i.test(d)) return true;
        d = d.replace(/^(?:ฝ่าย|แผนก|department|dept\.?)\s+/i, '').trim();
        if (d.toLowerCase() === HRGA_DEPT.toLowerCase()) return true;
        if (/^hr\s*(?:&|and|และ)\s*ga$/i.test(d)) return true;
        return /\bhr\b/i.test(d) && /\bga\b/i.test(d);
    }

    function getTasks() {
        const list = [];
        if (board && Array.isArray(board.tasks)) list.push(...board.tasks);
        if (routineBoard && Array.isArray(routineBoard.tasks)) {
            routineBoard.tasks.forEach(t => {
                if (!list.find(x => String(x.id) === String(t.id))) list.push(t);
            });
        }
        return list;
    }

    function taskCo(t) {
        const c = String(t.company || '').trim().toUpperCase();
        if (c) return c;
        const rc = String(t.responsible_companies || '').split(',')[0];
        return (rc || '').trim().toUpperCase();
    }

    function coKey(co) {
        return String(co || '').trim().toLowerCase();
    }

    function matchesCoFilter(t) {
        if (companyFilter === 'all') return true;
        const f = companyFilter.toLowerCase();
        if (coKey(taskCo(t)) === f) return true;
        return String(t.responsible_companies || '').split(',').map(s => coKey(s)).includes(f);
    }

    function homeByUserId() {
        const map = {};
        (board?.team || routineBoard?.team || []).forEach(m => {
            const id = String(m.id || '');
            if (id && !map[id]) map[id] = String(m.company || '').toUpperCase();
        });
        return map;
    }

    function teamDisplayName(m) {
        return trimName(m.name_th) || trimName(m.name) || String(m.id || '');
    }

    function trimName(v) {
        const s = String(v || '').trim();
        return s || '';
    }

    function loadPercent(openCount) {
        const loads = board?.load || [];
        const max = Math.max(...loads.map(r => Number(r.open_count || 0)), 1);
        return Math.min(100, Math.round((Number(openCount || 0) / max) * 100));
    }

    function loadClassFromPct(pct, overdue) {
        if (Number(overdue) > 0 && pct >= 70) return 'load-high';
        if (pct >= 50) return 'load-warn';
        return 'load-ok';
    }

    function isOverdueTask(t) {
        if (!t.due_date || t.status === 'done') return false;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const d = new Date(t.due_date);
        d.setHours(0, 0, 0, 0);
        return d < today;
    }

    function statusBadge(st) {
        const map = { todo: 'รอทำ', doing: 'กำลังทำ', approve: 'รออนุมัติ', done: 'เสร็จ' };
        const cls = { todo: 'b-todo', doing: 'b-doing', approve: 'b-doing', done: 'b-todo' };
        const s = st || 'todo';
        const label = s === 'doing' ? 'กำลังทำ' : s === 'done' ? 'เสร็จ' : 'รอทำ';
        return `<span class="badge ${cls[s] || 'b-todo'}">${map[s] || label}</span>`;
    }

    function coBadge(co) {
        const key = coKey(co);
        const up = String(co || '').toUpperCase();
        return `<span class="badge ${CO_BADGE[up] || 'b-adhoc'}">${CO_LABEL[key] || co || '-'}</span>`;
    }

    function kindBadge(t) {
        return Number(t.is_recurring || 0) === 1
            ? '<span class="badge b-routine">Routine</span>'
            : '<span class="badge b-adhoc">พิเศษ</span>';
    }

    function peopleForMeeting() {
        const homes = homeByUserId();
        return (board?.by_person || []).map(p => {
            const tasks = (p.tasks || []).filter(matchesCoFilter);
            const pct = loadPercent(p.open_count);
            return { ...p, tasks, home: homes[String(p.user_id)] || '', loadPct: pct, loadCls: loadClassFromPct(pct, p.overdue_count) };
        }).filter(p => companyFilter === 'all' || p.tasks.length > 0);
    }

    function suggestForCompany(co) {
        const loads = [...(board?.load || [])].sort((a, b) => Number(a.open_count) - Number(b.open_count));
        const homes = homeByUserId();
        if (!loads.length) return null;
        if (co === 'all') return loads[0];
        return loads.find(r => homes[String(r.user_id)] === co.toUpperCase()) || loads[0];
    }

    function renderAssignTips() {
        return [
            { situation: 'งาน PTA ใหม่', co: 'PTA' },
            { situation: 'งาน PTE เร่ง', co: 'PTE' },
            { situation: 'งาน PT4 ประจำ', co: 'PT4' }
        ].map(({ situation, co }) => {
            const pick = suggestForCompany(co);
            if (!pick) return `<tr><td>${situation}</td><td>—</td></tr>`;
            const homes = homeByUserId();
            const home = homes[String(pick.user_id)] || '';
            const pct = loadPercent(pick.open_count);
            const note = pct >= 60 ? ' — ดู load ก่อน (ตอนนี้สูง)' : (home === co ? ' — คุ้น ' + co : ' — load ต่ำ');
            return `<tr><td>${situation}</td><td><strong>${ctx.escapeHtml(pick.owner_name)}</strong>${note}</td></tr>`;
        }).join('');
    }

    function renderKpis() {
        const s = board?.summary || {};
        const overdue = Number(s.overdue_total || 0);
        return `<div class="kpi-meet">
            <div class="km"><div class="v">${s.team_count || 0}</div><div class="l">คนในทีม</div></div>
            <div class="km"><div class="v">${s.open_total || 0}</div><div class="l">งานค้าง</div></div>
            <div class="km ${overdue ? 'danger' : ''}"><div class="v">${overdue}</div><div class="l">เลยกำหนด</div></div>
            <div class="km"><div class="v">${s.routine_open || 0}</div><div class="l">Routine รอบนี้</div></div>
            <div class="km"><div class="v">${s.adhoc_open || 0}</div><div class="l">งานพิเศษ</div></div>
        </div>`;
    }

    function renderCoTabs() {
        const tabs = [{ f: 'all', label: 'ทุกบริษัท' }, { f: 'pta', label: 'PTA' }, { f: 'pt4', label: 'PT4' }, { f: 'pte', label: 'PTE' }];
        return `<div class="tabs" id="meetCoTabs">${tabs.map(t =>
            `<button type="button" class="tab ${companyFilter === t.f ? 'on' : ''}" data-f="${t.f}">${t.label}</button>`
        ).join('')}</div>`;
    }

    function renderMeetingBody() {
        const people = [...peopleForMeeting()].sort((a, b) => b.loadPct - a.loadPct);
        if (!people.length) return '<div class="hrga-empty">ไม่มีงานค้างตามตัวกรอง</div>';
        return people.map(p => {
            const lines = p.tasks.length ? p.tasks.map(t => {
                const over = isOverdueTask(t);
                const cKey = coKey(taskCo(t));
                return `<div class="task-line" data-task-id="${t.id}">
                    <div>
                        <strong>${ctx.escapeHtml(t.title || '-')}</strong>
                        ${over ? ' <span class="badge b-over">เลยกำหนด</span>' : ''}
                        <div style="margin-top:4px">${kindBadge(t)}</div>
                    </div>
                    <span class="badge ${CO_BADGE[taskCo(t)] || 'b-adhoc'}">${CO_LABEL[cKey] || taskCo(t)}</span>
                    ${statusBadge(t.status)}
                </div>`;
            }).join('') : '<div class="task-line" style="color:#94a3b8;grid-template-columns:1fr">ไม่มีงานในบริษัทที่เลือก</div>';
            return `<div class="person-block">
                <div class="person-head">
                    <div class="person-name">${ctx.escapeHtml(p.owner_name || p.user_id)}${p.home ? ' ' + coBadge(p.home) : ''}</div>
                    <span class="load-pill ${p.loadCls}">Load ${p.loadPct}%</span>
                </div>${lines}</div>`;
        }).join('');
    }

    function agendaOverdueNames() {
        const names = [];
        (board?.by_person || []).forEach(p => {
            if (Number(p.overdue_count || 0) > 0) names.push(p.owner_name);
        });
        if (!names.length) return '—';
        return names.slice(0, 3).join(', ') + (names.length > 3 ? ' …' : '');
    }

    function renderMeetingScreen() {
        const s = board?.summary || {};
        const overdue = Number(s.overdue_total || 0);
        const headName = ctx.escapeHtml(ctx.session.display_name || 'หัวหน้าทีม');
        const headCo = ctx.session.company ? ` (${ctx.escapeHtml(ctx.session.company)})` : '';
        const badge = overdue > 0
            ? `<div class="meet-badge"><i class="fas fa-circle-exclamation"></i> คุย ${overdue} เรื่อง: เลยกำหนด · มอบงาน PTA · Routine</div>` : '';
        return `<div class="meet-header">
                <div>
                    <div class="meet-title"><i class="fas fa-display" style="color:#2563eb"></i> ประชุมทีม ${HRGA_DEPT}</div>
                    <div class="meet-meta">หัวหน้า: ${headName}${headCo} · อัปเดต ${new Date().toLocaleString('th-TH')} · ข้อมูลจาก Task Center</div>
                </div>${badge}
            </div>
            ${renderKpis()}${renderCoTabs()}
            <div class="layout-2">
                <div class="panel">
                    <div class="ph"><h2><i class="fas fa-list-check"></i> งานแยกตามคน — ใครเอาอะไรไปทำ</h2>
                    <span style="font-size:12px;color:#64748b">เรียงตาม load สูง → ต่ำ</span></div>
                    <div id="meetByPerson">${renderMeetingBody()}</div>
                </div>
                <div>
                    <div class="side-card"><h3><i class="fas fa-clipboard-list"></i> วาระประชุม</h3>
                    <ul class="agenda">
                        <li>เคลียร์งานเลยกำหนด ${overdue} รายการ (${ctx.escapeHtml(agendaOverdueNames())})</li>
                        <li>มอบ onboarding PTA → ใครรับได้? (ดู load)</li>
                        <li>เช็ค Routine รอบนี้ — ใครยังไม่อัปเดต</li>
                    </ul></div>
                    <div class="side-card"><h3><i class="fas fa-user-plus"></i> มอบงานใหม่ (หลังประชุม)</h3>
                    <p style="font-size:12px;color:#64748b;margin:0 0 10px">เลือกคนที่ load ต่ำกว่า — ดูแท็บภาระงานทีม</p>
                    <button type="button" class="btn-primary" onclick="location.href='job_ticket.html?new=hrga'">+ บันทึกงานที่มอบในที่ประชุม</button>
                    <p class="note">บันทึกแล้วทุกคนเห็นในตารางทันที — ไม่ต้องจดกระดาษ</p></div>
                    <div class="side-card summary-boss"><h3>สรุปสำหรับหัวหน้า</h3>
                    <p>เปิดจอนี้ในประชุม → ชี้แถว → ตกลงว่าใครรับ → กดบันทึก → ลูกน้องอัปเดตก่อนประชุมครั้งหน้า</p></div>
                </div>
            </div>`;
    }

    function renderLoadScreen() {
        const homes = homeByUserId();
        const rows = [...(board?.load || [])].sort((a, b) => loadPercent(b.open_count) - loadPercent(a.open_count)).map(r => {
            const pct = loadPercent(r.open_count);
            const cls = loadClassFromPct(pct, r.overdue_count);
            const home = homes[String(r.user_id)] || '';
            const rPct = Number(r.routine_pct || 0);
            const aPct = Number(r.adhoc_pct || 0);
            return `<div class="load-row"><div class="load-label">
                <span>${ctx.escapeHtml(r.owner_name)}${home ? ` (${home})` : ''}</span>
                <span class="load-pill ${cls}">${pct}%</span></div>
                <div class="load-bar">
                    <div class="load-routine" style="width:${rPct}%"></div>
                    <div class="load-adhoc" style="width:${aPct}%"></div>
                </div></div>`;
        }).join('');
        return `<div class="meet-header"><div>
            <div class="meet-title">ภาระงานทีม (Load)</div>
            <div class="meet-meta">ม่วง = Routine · ส้ม = งานพิเศษ · ใช้ตัดสินใจแจกงาน</div></div></div>
            <div class="legend"><span><i class="load-routine"></i> Routine</span><span><i class="load-adhoc"></i> งานพิเศษ</span></div>
            <div class="panel" style="padding:16px">${rows || '<div class="hrga-empty">ไม่มีข้อมูล</div>'}</div>
            <div class="panel" style="margin-top:14px"><div class="ph"><h2>แนะนำการมอบงาน</h2></div>
            <table><thead><tr><th>สถานการณ์</th><th>แนะนำ</th></tr></thead><tbody>${renderAssignTips()}</tbody></table></div>`;
    }

    function renderDashScreen() {
        const homes = homeByUserId();
        let rows = '';
        (board?.by_person || []).forEach(p => {
            (p.tasks || []).filter(matchesCoFilter).forEach(t => {
                const over = isOverdueTask(t);
                const home = homes[String(p.user_id)] || '';
                rows += `<tr data-task-id="${t.id}">
                    <td>${ctx.escapeHtml(t.title || '-')}${over ? ' <span class="badge b-over">!</span>' : ''}</td>
                    <td>${kindBadge(t)}</td><td>${ctx.escapeHtml(p.owner_name || '-')}</td>
                    <td>${home ? coBadge(home) : '-'}</td><td>${coBadge(taskCo(t))}</td>
                    <td>${statusBadge(t.status)}</td><td>${ctx.escapeHtml(ctx.formatDate(t.due_date))}</td></tr>`;
            });
        });
        return `<div class="panel"><div class="ph"><h2>ตารางภาพรวมทุกงาน</h2></div>
            <div class="table-scroll"><table><thead><tr>
            <th>งาน</th><th>ประเภท</th><th>ผู้ทำ</th><th>บ้าน</th><th>บริษัทงาน</th><th>สถานะ</th><th>Due</th>
            </tr></thead><tbody>${rows || '<tr><td colspan="7" class="hrga-empty">ไม่มีงาน</td></tr>'}</tbody></table></div></div>`;
    }

    function routineTeamOptions() {
        const team = board?.team || routineBoard?.team || [];
        const uid = routineUserId || String(ctx.session.user_id || '');
        return team.map(m => {
            const id = String(m.id || '');
            const label = teamDisplayName(m);
            const home = String(m.company || '').toUpperCase();
            return `<option value="${ctx.escapeHtml(id)}" ${id === uid ? 'selected' : ''}>${ctx.escapeHtml(label)}${home ? ' (' + home + ')' : ''}</option>`;
        }).join('');
    }

    function renderRoutineScreen() {
        const uid = routineUserId || String(ctx.session.user_id || '');
        const homes = homeByUserId();
        const home = homes[uid] || ctx.session.company || '';
        const tasks = routineBoard?.tasks || [];
        const routines = tasks.filter(t => Number(t.is_recurring || 0) === 1);
        const adhoc = tasks.filter(t => Number(t.is_recurring || 0) !== 1 && t.status !== 'done');
        const member = (board?.team || []).find(m => String(m.id) === uid);
        const name = member ? teamDisplayName(member) : ctx.escapeHtml(ctx.session.display_name || 'ฉัน');
        const showPicker = (board?.team || []).length > 1;

        const line = t => `<div class="routine-item" data-task-id="${t.id}">
            <input type="checkbox" class="routine-check" data-task-id="${t.id}" ${t.status === 'done' ? 'checked' : ''}>
            <span>${ctx.escapeHtml(t.title || '-')}</span>${coBadge(taskCo(t))}
            <span style="margin-left:auto">${statusBadge(t.status)}</span></div>`;

        return `<div class="meet-header"><div>
            <div class="meet-title">ลูกน้อง — Routine + อัปเดตรอบนี้</div>
            <div class="meet-meta" id="routineMeta">${ctx.escapeHtml(name)} · บ้าน ${home || '—'} · แชร์แผนก ${HRGA_DEPT}</div></div>
            ${showPicker ? `<div><label style="font-size:11px;font-weight:600;color:#64748b">ดูงานของ</label>
            <select id="routineStaffPick" class="hrga-staff-pick">${routineTeamOptions()}</select></div>` : ''}</div>
            <div class="panel"><div class="ph"><h2>งานประจำของฉัน (Routine)</h2><span class="badge b-routine">รายเดือน</span></div>
            <div id="routineList">${routines.map(line).join('') || '<div class="hrga-empty">ยังไม่มี Routine</div>'}</div>
            <div style="padding:12px 14px"><button type="button" class="btn-primary" style="width:auto" onclick="location.href='job_ticket.html?new=hrga&recurring=1'">+ เพิ่ม Routine</button></div></div>
            <div class="panel" style="margin-top:14px"><div class="ph"><h2>งานพิเศษ (Ad-hoc)</h2></div>
            <table><tbody id="adhocList">${adhoc.map(t => `<tr data-task-id="${t.id}"><td>${ctx.escapeHtml(t.title)}</td><td>${coBadge(taskCo(t))}</td><td>${statusBadge(t.status)}</td></tr>`).join('') || '<tr><td colspan="3" class="hrga-empty">ไม่มีงานพิเศษค้าง</td></tr>'}</tbody></table></div>
            <p class="note" style="padding:0 4px">หัวหน้าเห็น Routine + พิเศษที่แชร์แผนก — ไม่ต้องถาม “ทำอะไรอยู่”</p>`;
    }

    async function saveTaskStatus(task, statusOrDone) {
        const status = typeof statusOrDone === 'boolean'
            ? (statusOrDone ? 'done' : (task.status === 'done' ? 'todo' : (task.status || 'todo')))
            : (statusOrDone || task.status || 'todo');
        const wf = status === 'done' ? 'done' : status === 'doing' ? 'doing' : status === 'approve' ? 'approve' : 'todo';
        const payload = {
            id: task.id,
            task_type: task.task_type || 'personal',
            title: task.title || '',
            description: task.description || '',
            status,
            status_detail: task.status_detail || '',
            workflow_stage: wf,
            priority: task.priority || 'normal',
            due_date: task.due_date || null,
            tags: task.tags || '',
            share_scope: task.share_scope || 'department',
            company: task.company || '',
            responsible_companies: task.responsible_companies || task.company || '',
            department: task.department || HRGA_DEPT,
            assignee_name: task.assignee_name || task.owner_name || '',
            building: task.building || '',
            is_recurring: Number(task.is_recurring || 0) === 1 ? 1 : 0,
            recurring_interval_months: Number(task.recurring_interval_months || 12),
            remind_days_before: Number(task.remind_days_before || 0)
        };
        const fd = new FormData();
        fd.append('data', JSON.stringify(payload));
        await ctx.apiFetch('api.php?action=personal_tasks_save', { method: 'POST', body: fd });
    }

    function bindRoutineChecks(container) {
        container.querySelectorAll('.routine-check').forEach(cb => {
            cb.addEventListener('click', async (e) => {
                e.stopPropagation();
                const id = cb.dataset.taskId;
                const task = getTasks().find(t => String(t.id) === String(id));
                if (!task) return;
                cb.disabled = true;
                try {
                    await saveTaskStatus(task, cb.checked);
                    await Promise.all([loadBoard(), loadRoutineBoard()]);
                    paintScreens();
                } catch (err) {
                    cb.checked = !cb.checked;
                    alert(err.message || 'บันทึกไม่สำเร็จ');
                } finally {
                    cb.disabled = false;
                }
            });
        });
    }

    function shellHtml() {
        return `<div class="shell"><div class="wrap">
            <div id="screen-meeting" class="screen"></div>
            <div id="screen-load" class="screen"></div>
            <div id="screen-dash" class="screen"></div>
            <div id="screen-routine" class="screen"></div>
        </div></div>`;
    }

    function bindClicks(container) {
        container.querySelectorAll('[data-task-id]').forEach(el => {
            if (el.classList.contains('routine-item') && el.querySelector('.routine-check')) return;
            el.addEventListener('click', (e) => {
                if (e.target.closest('.routine-check')) return;
                ctx.openDetailModal(el.dataset.taskId);
            });
        });
    }

    function bindCoTabs(meetEl) {
        const coTabs = meetEl.querySelector('#meetCoTabs');
        if (!coTabs) return;
        coTabs.querySelectorAll('[data-f]').forEach(tab => {
            tab.addEventListener('click', () => {
                companyFilter = tab.getAttribute('data-f') || 'all';
                const box = meetEl.querySelector('#meetByPerson');
                if (box) box.innerHTML = renderMeetingBody();
                coTabs.querySelectorAll('[data-f]').forEach(t => t.classList.toggle('on', t.getAttribute('data-f') === companyFilter));
                bindClicks(meetEl);
                const root = getRoot();
                const load = root?.querySelector('#screen-load');
                const dash = root?.querySelector('#screen-dash');
                if (load) { load.innerHTML = renderLoadScreen(); bindClicks(load); }
                if (dash) { dash.innerHTML = renderDashScreen(); bindClicks(dash); }
            });
        });
    }

    function bindRoutinePicker(routineEl) {
        const pick = routineEl.querySelector('#routineStaffPick');
        if (!pick) return;
        pick.addEventListener('change', async () => {
            routineUserId = pick.value;
            await loadRoutineBoard();
            routineEl.innerHTML = renderRoutineScreen();
            bindClicks(routineEl);
            bindRoutineChecks(routineEl);
            bindRoutinePicker(routineEl);
        });
    }

    function paintScreens() {
        const root = getRoot();
        if (!root || !board || board.status !== 'success') return;
        const meet = root.querySelector('#screen-meeting');
        const load = root.querySelector('#screen-load');
        const dash = root.querySelector('#screen-dash');
        const routine = root.querySelector('#screen-routine');
        if (meet) {
            meet.innerHTML = renderMeetingScreen();
            bindClicks(meet);
            bindCoTabs(meet);
        }
        if (load) { load.innerHTML = renderLoadScreen(); bindClicks(load); }
        if (dash) { dash.innerHTML = renderDashScreen(); bindClicks(dash); }
        if (routine) {
            routine.innerHTML = renderRoutineScreen();
            bindClicks(routine);
            bindRoutineChecks(routine);
            bindRoutinePicker(routine);
        }
        showActiveScreen();
    }

    function showActiveScreen() {
        const root = getRoot();
        if (!root) return;
        root.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
        const el = root.querySelector('#screen-' + screen);
        if (el) el.classList.add('active');
        document.querySelectorAll('[data-hrga-screen]').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.hrgaScreen === screen);
        });
    }

    async function loadBoard() {
        const q = new URLSearchParams({ action: 'task_center_hrga_board', open_only: '1', view: 'board' });
        if (['pta', 'pt4', 'pte'].includes(companyFilter)) q.set('company', companyFilter.toUpperCase());
        board = await ctx.apiFetch(`api.php?${q}`);
    }

    async function loadRoutineBoard() {
        const uid = routineUserId || String(ctx.session.user_id || '');
        const q = new URLSearchParams({
            action: 'task_center_hrga_board',
            view: 'routine',
            user_id: uid,
            open_only: '0'
        });
        routineBoard = await ctx.apiFetch(`api.php?${q}`);
    }

    async function refreshAll() {
        const root = getRoot();
        if (!root) return;
        root.innerHTML = '<div class="shell"><div class="wrap"><div class="hrga-loading"><div class="ptc-spinner"></div> กำลังโหลด…</div></div></div>';
        try {
            await loadBoard();
            if (board.status !== 'success') throw new Error(board.message || 'โหลดไม่สำเร็จ');
            if (!routineUserId) routineUserId = String(ctx.session.user_id || '');
            await loadRoutineBoard();
            root.innerHTML = shellHtml();
            paintScreens();
        } catch (err) {
            root.innerHTML = `<div class="shell"><div class="wrap"><div class="hrga-empty">${ctx.escapeHtml(err.message)}</div></div></div>`;
        }
    }

    function setScreen(next) {
        if (next === 'standard') {
            window.location.href = 'task_center.html?standard=1';
            return;
        }
        screen = next;
        if (ctx?.onScreenChange) ctx.onScreenChange(screen);
        if (board && board.status === 'success') {
            showActiveScreen();
            if (next === 'routine' && !routineBoard) {
                loadRoutineBoard().then(() => {
                    const routine = getRoot()?.querySelector('#screen-routine');
                    if (routine) {
                        routine.innerHTML = renderRoutineScreen();
                        bindClicks(routine);
                        bindRoutineChecks(routine);
                        bindRoutinePicker(routine);
                    }
                }).catch(console.warn);
            }
        } else {
            refreshAll().catch(console.warn);
        }
    }

    function bindNav() {
        document.querySelectorAll('[data-hrga-screen]').forEach(btn => {
            btn.addEventListener('click', () => setScreen(btn.dataset.hrgaScreen));
        });
        const refresh = document.getElementById('hrgaRefreshBtn');
        if (refresh) refresh.addEventListener('click', () => refreshAll().catch(e => alert(e.message)));
    }

    function init(options) {
        ctx = options;
        const root = getRoot();
        if (!root) return;
        if (!options.standalone && !ctx.session.hrga_mode && !isHrGaDept(ctx.session.primary_department)) return;
        ctx.session.hrga_mode = true;
        routineUserId = String(ctx.session.user_id || '');
        document.body.classList.add('hrga-page-body');
        bindNav();
        refreshAll();
    }

    async function patchTaskStatus(taskId, status) {
        const task = getTasks().find(t => String(t.id) === String(taskId));
        if (!task) throw new Error('ไม่พบงาน');
        await saveTaskStatus(task, status);
        await Promise.all([loadBoard(), loadRoutineBoard()]);
        paintScreens();
        return task;
    }

    global.TaskCenterHrga = {
        init,
        isHrGaDept,
        getTasks,
        loadBoard,
        setScreen,
        refreshAll,
        patchTaskStatus
    };
})(window);
