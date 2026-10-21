/**
 * IT Repair Log v2 — lightweight list + core actions (no Tailwind CDN)
 */
(function () {
    'use strict';

    const PER_PAGE = 15;
    const SWAL_FIELD = 'ptc-swal-textarea w-full';
    const SWAL_SELECT = 'ptc-swal-select w-full';

    let state = {
        tickets: [],
        pagination: null,
        statusCounts: {},
        page: 1,
        perPage: PER_PAGE,
        filterStatus: '',
        filterCompany: '',
        filterSearch: '',
        myQueueOnly: false,
        userId: '',
        userName: '',
        userRole: '',
        technicians: null,
        actionLock: null,
    };

    const MAX_JSON_BYTES = 1_500_000;

    async function fetchJson(url, opts) {
        const res = await fetch(url, { cache: 'no-store', credentials: 'same-origin', ...opts });
        const text = await res.text();
        if (text.length > MAX_JSON_BYTES) {
            throw new Error('ข้อมูลจากเซิร์ฟเวอร์ใหญ่เกินไป (' + Math.round(text.length / 1024) + ' KB) — แจ้ง IT');
        }
        let json;
        try { json = JSON.parse(text); } catch (_) { throw new Error('ตอบกลับจากเซิร์ฟเวอร์ไม่ถูกต้อง'); }
        if (!res.ok) throw new Error(json?.message || ('HTTP ' + res.status));
        return json;
    }

    const $ = (id) => document.getElementById(id);

    function ptcSwal() { return window.PtcSwal || Swal; }

    function escapeHtml(t) {
        return String(t ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function ptcWorkflowHead(opts) {
        const { step, title, ticketId, icon = 'fa-circle-info', tone = 'blue' } = opts || {};
        const tones = { blue: '#2563eb', amber: '#d97706', emerald: '#059669', violet: '#7c3aed', rose: '#e11d48' };
        const color = tones[tone] || tones.blue;
        return `<div class="ptc-swal-flow-head" style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #e2e8f0">
            ${step ? `<div style="font-size:10px;font-weight:700;text-transform:uppercase;color:${color}">${escapeHtml(step)}</div>` : ''}
            <div style="display:flex;gap:10px;align-items:flex-start;margin-top:6px">
                <span style="width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:${color}18;color:${color}"><i class="fas ${icon}"></i></span>
                <div><div style="font-weight:600;font-size:14px">${escapeHtml(title)}</div>
                ${ticketId ? `<div style="font-size:11px;color:#64748b;font-family:monospace;margin-top:2px">${escapeHtml(ticketId)}</div>` : ''}</div>
            </div></div>`;
    }

    function toast(icon, title, text) {
        const T = window.PtcToast || Swal;
        return T.fire({ icon: icon || 'success', title, text: text || undefined, toast: true, position: 'top-end', showConfirmButton: false, timer: text ? 2800 : 2000, timerProgressBar: true });
    }

    function alertBox(icon, title, text) {
        return ptcSwal().fire({ icon: icon || 'info', title, text: text || undefined, confirmButtonText: 'ตกลง' });
    }

    async function workflow(opts) {
        return ptcSwal().fire({ width: opts.width || 'min(480px, 96vw)', showCancelButton: true, cancelButtonText: 'ยกเลิก', ...opts });
    }

    async function withLoading(title, fn) {
        ptcSwal().fire({ title: title || 'กำลังดำเนินการ...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
        try { return await fn(); } finally { if (Swal.isVisible?.()) Swal.close(); }
    }

    async function withAction(key, fn) {
        if (state.actionLock) {
            toast('info', 'กรุณารอสักครู่');
            return;
        }
        state.actionLock = key;
        try { return await fn(); } finally { state.actionLock = null; }
    }

    function statusBadgeClass(status) {
        const map = {
            Pending: 'logv2-st-pending',
            Received: 'logv2-st-received',
            'In Progress': 'logv2-st-progress',
            'Waiting for User': 'logv2-st-waiting',
            'Sent to Vendor': 'logv2-st-other',
            'Temporary Fix': 'logv2-st-temp',
            'Awaiting Confirmation': 'logv2-st-await',
            Completed: 'logv2-st-done',
            Cancelled: 'logv2-st-cancel',
        };
        return map[status] || 'logv2-st-other';
    }

    function companyClass(id) {
        if (String(id).startsWith('PT4')) return 'logv2-co-pt4';
        if (String(id).startsWith('PTA')) return 'logv2-co-pta';
        if (String(id).startsWith('PTE')) return 'logv2-co-pte';
        return 'logv2-co-other';
    }

    function shortName(name) {
        if (!name) return '-';
        const n = String(name).replace(/^(Mr\.|Ms\.|Mrs\.|Miss|Dr\.|Khun)\s+/i, '').trim().split(/\s+/);
        return n.length > 1 ? `${n[0]} ${n[1].charAt(0)}.` : (n[0] || '-');
    }

    async function fetchTechnicians() {
        if (state.technicians) return state.technicians;
        const data = await fetchJson(`api.php?action=get&file=employees&scope=technicians&v=${Date.now()}`);
        state.technicians = Array.isArray(data) ? data : [];
        return state.technicians;
    }

    function buildParams() {
        const p = new URLSearchParams({
            action: 'get', file: 'it_logs', paginate: '1',
            page: String(state.page), per_page: String(state.perPage),
            sort_field: 'date', sort_dir: 'desc', v: Date.now(),
        });
        if (state.filterCompany) p.set('filter_company', state.filterCompany);
        if (state.filterStatus) p.set('filter_status', state.filterStatus);
        if (state.filterSearch) p.set('filter_search', state.filterSearch);
        if (state.myQueueOnly && state.userId) p.set('filter_assignee', state.userId);
        return p;
    }

    async function fetchTickets() {
        const json = await fetchJson(`api.php?${buildParams()}`);
        if (!json || !Array.isArray(json.data)) {
            throw new Error('API ไม่ได้ส่งแบบแบ่งหน้า — ติดต่อ IT');
        }
        state.tickets = json.data;
        state.pagination = {
            total: json.total,
            page: json.page,
            totalPages: json.total_pages,
        };
        state.statusCounts = json.status_counts || {};
        state.page = json.page || state.page;
    }

    async function fetchTicketById(id) {
        const found = state.tickets.find((t) => t && t.id === id);
        if (found) return found;
        const p = new URLSearchParams({
            action: 'get', file: 'it_logs', id: String(id),
            paginate: '1', page: '1', per_page: '1', v: Date.now(),
        });
        const json = await fetchJson(`api.php?${p}`);
        return (json?.data?.[0] && json.data[0].id === id) ? json.data[0] : null;
    }

    function renderKpi() {
        const el = $('logv2-kpi');
        if (!el) return;
        const c = state.statusCounts;
        const total = state.pagination?.total ?? 0;
        const items = [
            ['ทั้งหมด', total, ''],
            ['รอรับ', c.Pending || 0, 'logv2-st-pending'],
            ['กำลังทำ', c['In Progress'] || 0, 'logv2-st-progress'],
            ['รอยืนยัน', c['Awaiting Confirmation'] || 0, 'logv2-st-await'],
            ['เสร็จ', c.Completed || 0, 'logv2-st-done'],
        ];
        el.innerHTML = items.map(([label, n, cls]) =>
            `<div class="logv2-kpi-item"><strong>${n}</strong><span class="${cls}">${label}</span></div>`
        ).join('');
    }

    function renderActions(log) {
        const id = escapeHtml(log.id);
        const b = (cls, label, action) =>
            `<button type="button" class="logv2-btn-act ${cls}" data-action="${action}" data-id="${id}">${label}</button>`;

        if (log.status === 'Pending') {
            return b('logv2-btn-primary', '<i class="fas fa-inbox"></i> รับปัญหา', 'accept') +
                b('logv2-btn-ghost', '<i class="fas fa-ban"></i> ยกเลิก', 'cancel');
        }
        if (log.status === 'Received') {
            return b('logv2-btn-amber', '<i class="fas fa-play"></i> เริ่มงาน', 'start') +
                b('logv2-btn-ghost', 'ยกเลิก', 'cancel');
        }
        if (['In Progress', 'Waiting for User', 'Sent to Vendor', 'Temporary Fix', 'PR Submitted', 'PO Issued'].includes(log.status)) {
            return b('logv2-btn-green', '<i class="fas fa-check"></i> ปิดงาน', 'finish') +
                b('logv2-btn-amber', 'อัปเดต', 'pause') +
                b('logv2-btn-ghost', 'ยกเลิก', 'cancel');
        }
        if (log.status === 'Awaiting Confirmation') {
            return b('logv2-btn-green', 'ยืนยันปิด', 'confirm') + b('logv2-btn-ghost', 'เปิดใหม่', 'reopen');
        }
        return `<button type="button" class="logv2-btn-act logv2-btn-ghost" data-action="detail" data-id="${id}">รายละเอียด</button>`;
    }

    function renderTable() {
        const body = $('logv2-tbody');
        const empty = $('logv2-empty');
        if (!body) return;

        if (!state.tickets.length) {
            body.innerHTML = '';
            if (empty) empty.classList.remove('hidden');
            return;
        }
        if (empty) empty.classList.add('hidden');

        const rows = state.tickets.map((log) => {
            const dateStr = log.date ? new Date(log.date).toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit' }) : '-';
            const problem = escapeHtml(String(log.problem || '-').slice(0, 300));
            return `<tr data-id="${escapeHtml(log.id)}">
                <td><span class="logv2-ticket-id ${companyClass(log.id)}">${escapeHtml(log.id)}</span>
                    <div class="logv2-meta">${dateStr}</div></td>
                <td class="logv2-hide-sm">${escapeHtml(shortName(log.requester))}<div class="logv2-meta">${escapeHtml(log.department || '-')}</div></td>
                <td><div class="logv2-problem">${problem}</div>
                    <div class="logv2-meta">${escapeHtml(log.urgency || '')} · ${escapeHtml(log.type || '')}</div></td>
                <td><span class="logv2-badge ${statusBadgeClass(log.status)}">${escapeHtml(log.status)}</span></td>
                <td class="logv2-hide-sm">${escapeHtml(log.servicedBy || '-')}</td>
                <td><div class="logv2-actions">${renderActions(log)}</div></td>
            </tr>`;
        }).join('');

        body.innerHTML = rows;
    }

    function renderPager() {
        const p = state.pagination;
        const info = $('logv2-page-info');
        const prev = $('logv2-prev');
        const next = $('logv2-next');
        if (!p || !info) return;
        const start = p.total === 0 ? 0 : (p.page - 1) * state.perPage + 1;
        const end = Math.min(p.page * state.perPage, p.total);
        info.textContent = `${start}–${end} จาก ${p.total}`;
        if (prev) prev.disabled = p.page <= 1;
        if (next) next.disabled = p.page >= p.totalPages;
    }

    async function reload() {
        const loading = $('logv2-loading');
        const wrap = $('logv2-table-wrap');
        if (loading) loading.classList.remove('hidden');
        if (wrap) wrap.classList.add('hidden');
        try {
            await fetchTickets();
            renderKpi();
            renderTable();
            renderPager();
        } catch (e) {
            console.error(e);
            alertBox('error', 'โหลดข้อมูลไม่สำเร็จ', e.message);
        } finally {
            if (loading) loading.classList.add('hidden');
            if (wrap) wrap.classList.remove('hidden');
        }
    }

    /**
     * Show a lightweight inline accept panel (no SweetAlert — avoids OOM on low-RAM Chrome)
     * Returns {id, name} of chosen technician, or null if cancelled.
     */
    async function showAcceptPanel(ticketId) {
        return new Promise((resolve) => {
            // Remove any existing panel
            const old = document.getElementById('lv2-accept-panel');
            if (old) old.remove();

            const panel = document.createElement('div');
            panel.id = 'lv2-accept-panel';
            panel.style.cssText = 'position:fixed;inset:0;z-index:9000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45)';
            panel.innerHTML = `
                <div style="background:#fff;border-radius:16px;padding:24px;width:min(440px,92vw);box-shadow:0 20px 60px rgba(0,0,0,.2);font-family:inherit">
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#2563eb;margin-bottom:8px">รับปัญหา</div>
                    <div style="font-size:15px;font-weight:700;margin-bottom:4px">รับงานเข้าระบบ</div>
                    <div style="font-size:12px;color:#64748b;font-family:monospace;margin-bottom:16px">${escapeHtml(ticketId)}</div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">มอบหมายให้</label>
                    <select id="lv2-ap-select" style="width:100%;padding:9px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;margin-bottom:16px"></select>
                    <div style="display:flex;gap:10px">
                        <button id="lv2-ap-ok" style="flex:1;padding:10px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:14px;cursor:pointer;font-family:inherit">รับปัญหา</button>
                        <button id="lv2-ap-cancel" style="padding:10px 16px;background:#f1f5f9;border:none;border-radius:8px;font-size:14px;cursor:pointer;font-family:inherit">ยกเลิก</button>
                    </div>
                </div>`;

            const sel = panel.querySelector('#lv2-ap-select');
            const opts = [{ id: state.userId, name: state.userName }];
            if (state.technicians) {
                state.technicians.forEach((t) => {
                    if (String(t.id) !== String(state.userId)) opts.push(t);
                });
            }
            opts.forEach((t) => {
                const o = document.createElement('option');
                o.value = t.id;
                o.textContent = t.name;
                sel.appendChild(o);
            });

            const cleanup = () => panel.remove();
            panel.querySelector('#lv2-ap-cancel').onclick = () => { cleanup(); resolve(null); };
            panel.querySelector('#lv2-ap-ok').onclick = () => {
                const id = sel.value;
                const name = opts.find((t) => String(t.id) === String(id))?.name || '';
                cleanup();
                resolve({ id, name });
            };
            panel.addEventListener('click', (e) => { if (e.target === panel) { cleanup(); resolve(null); } });
            document.body.appendChild(panel);
        });
    }

    async function postUpdate(data, extra = {}) {
        const fd = new FormData();
        fd.append('action', 'update_item');
        fd.append('key', 'it_logs');
        fd.append('data', JSON.stringify(data));
        Object.entries(extra).forEach(([k, v]) => { if (v != null && v !== '') fd.append(k, v); });
        const json = await fetchJson('api.php', { method: 'POST', body: fd });
        if (json.status !== 'success') throw new Error(json.message || 'บันทึกไม่สำเร็จ');
        return json;
    }

    async function acceptTicket(id) {
        await withAction('accept:' + id, async () => {
            const log = state.tickets.find((t) => t && t.id === id);
            if (!log) return;
            if (log.status !== 'Pending') return;

            // Pre-fetch technicians silently (tiny API call) before showing any UI
            await fetchTechnicians().catch(() => null);

            const assignee = await showAcceptPanel(id);
            if (!assignee) return;

            // Inline status text instead of SweetAlert loading
            const row = document.querySelector(`tr[data-id="${CSS.escape(id)}"]`);
            if (row) row.style.opacity = '0.4';

            try {
                await postUpdate(
                    { id, status: 'Received', servicedBy: assignee.name || '' },
                    { servicedById: assignee.id, expected_current_status: 'Pending' }
                );
                showInlineToast('รับปัญหาแล้ว ✓', 'green');
                await reload();
            } catch (e) {
                if (row) row.style.opacity = '';
                showInlineToast('รับปัญหาไม่สำเร็จ: ' + e.message, 'red');
            }
        });
    }

    async function startTicket(id) {
        await withAction('start:' + id, async () => {
            const log = state.tickets.find((t) => t && t.id === id);
            if (!log) return;
            if (log.status === 'Pending') { showInlineToast('กรุณารับปัญหาก่อน', 'orange'); return; }

            let assigneeId = log.servicedById || state.userId;
            let assigneeName = log.servicedBy || state.userName;

            const row = document.querySelector(`tr[data-id="${CSS.escape(id)}"]`);
            if (row) row.style.opacity = '0.4';

            try {
                await postUpdate(
                    { id, status: 'In Progress', servicedBy: assigneeName },
                    { servicedById: assigneeId, expected_current_status: String(log.status) }
                );
                showInlineToast('เริ่มงานแล้ว ✓', 'green');
                await reload();
            } catch (e) {
                if (row) row.style.opacity = '';
                showInlineToast('เกิดข้อผิดพลาด: ' + e.message, 'red');
            }
        });
    }

    async function pauseTicket(id) {
        await withAction('pause:' + id, async () => {
            const log = await fetchTicketById(id);
            if (!log) return alertBox('warning', 'ไม่พบ Ticket');

            const { value: formValues, isConfirmed } = await workflow({
                title: 'อัปเดตสถานะ',
                html: ptcWorkflowHead({ step: 'อัปเดตสถานะ', title: 'พักงาน / เปลี่ยนสถานะ', ticketId: id, icon: 'fa-pause', tone: 'amber' }) + `<div class="text-left" style="display:grid;gap:12px">
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">เลือกสถานะใหม่</label>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">
                            <label><input type="radio" name="lv2-status" value="Waiting for Parts" checked> รออะไหล่</label>
                            <label><input type="radio" name="lv2-status" value="Waiting for User"> รอผู้ใช้ตอบกลับ</label>
                            <label><input type="radio" name="lv2-status" value="Sent to Vendor"> ส่งซ่อมภายนอก</label>
                            <label><input type="radio" name="lv2-status" value="Temporary Fix"> แก้ชั่วคราว</label>
                            <label><input type="radio" name="lv2-status" value="PR Submitted"> ส่ง PR แล้ว</label>
                            <label><input type="radio" name="lv2-status" value="PO Issued"> ออก PO แล้ว</label>
                            <label><input type="radio" name="lv2-status" value="Parts Received"> ได้อะไหล่แล้ว</label>
                            <label><input type="radio" name="lv2-status" value="Unrepairable"> ซ่อมไม่ได้ (แนะนำเปลี่ยน)</label>
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">หมายเหตุ</label>
                        <textarea id="lv2-note" class="${SWAL_FIELD}" rows="3" placeholder="ระบุเหตุผล / หมายเหตุ"></textarea>
                    </div>
                </div>`,
                confirmButtonText: '<i class="fas fa-save"></i> บันทึกสถานะ',
                preConfirm: () => {
                    const checked = document.querySelector('input[name="lv2-status"]:checked');
                    if (!checked) { Swal.showValidationMessage('กรุณาเลือกสถานะ'); return false; }
                    const note = document.getElementById('lv2-note')?.value || '';
                    return { status: checked.value, note: note.trim() };
                }
            });

            if (!isConfirmed || !formValues) return;
            const dateStr = new Date().toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit', hour: '2-digit', minute: '2-digit' });
            const noteText = formValues.note ? `[${dateStr}] ${formValues.note}` : '';
            const updatedSolution = noteText ? ((log.solution ? log.solution + '\n' : '') + noteText) : (log.solution || '');

            const row = document.querySelector(`tr[data-id="${CSS.escape(id)}"]`);
            if (row) row.style.opacity = '0.4';
            try {
                await postUpdate(
                    { id, status: formValues.status, solution: updatedSolution },
                    { expected_current_status: String(log.status || '') }
                );
                showInlineToast('อัปเดตสถานะแล้ว ✓', 'green');
                await reload();
            } catch (e) {
                if (row) row.style.opacity = '';
                showInlineToast('อัปเดตสถานะไม่สำเร็จ: ' + e.message, 'red');
            }
        });
    }

    async function cancelTicket(id) {
        const reason = prompt('เหตุผลการยกเลิก (จำเป็น):');
        if (!reason || !reason.trim()) return;

        await withAction('cancel:' + id, async () => {
            const row = document.querySelector(`tr[data-id="${CSS.escape(id)}"]`);
            if (row) row.style.opacity = '0.4';
            try {
                const fd = new FormData();
                fd.append('action', 'cancel_ticket');
                fd.append('id', id);
                fd.append('reason', `[CANCELLED BY IT] ${reason.trim()}`);
                const json = await fetchJson('api.php', { method: 'POST', body: fd });
                if (json.status !== 'success') throw new Error(json.message || 'ยกเลิกไม่สำเร็จ');
                showInlineToast('ยกเลิกแล้ว ✓', 'green');
                await reload();
            } catch (e) {
                if (row) row.style.opacity = '';
                showInlineToast('ยกเลิกไม่สำเร็จ: ' + e.message, 'red');
            }
        });
    }

    async function showDetail(id) {
        const log = await fetchTicketById(id);
        if (!log) return alertBox('warning', 'ไม่พบ Ticket');
        const fields = [
            ['วันที่', log.date ? new Date(log.date).toLocaleString('th-TH') : '-'],
            ['สถานะ', log.status],
            ['ผู้แจ้ง', log.requester],
            ['แผนก', log.department],
            ['ผู้รับผิดชอบ', log.servicedBy || '-'],
            ['ความเร่งด่วน', log.urgency],
            ['ประเภท', log.type],
        ];
        const grid = fields.map(([k, v]) =>
            `<div style="font-size:11px;color:#64748b">${escapeHtml(k)}</div><div style="font-size:13px;margin-bottom:8px">${escapeHtml(v)}</div>`
        ).join('');
        await workflow({
            title: 'รายละเอียด',
            html: ptcWorkflowHead({ step: 'Ticket', title: 'รายละเอียดงาน', ticketId: id, icon: 'fa-ticket' }) +
                grid + `<div style="font-size:11px;color:#64748b;margin-top:8px">ปัญหา</div>
                <div style="font-size:13px;background:#f8fafc;padding:10px;border-radius:8px;white-space:pre-wrap;max-height:200px;overflow:auto">${escapeHtml(String(log.problem || '-').slice(0, 3000))}</div>
                <p style="font-size:11px;color:#94a3b8;margin-top:12px">ฟีเจอร์เต็ม (พิมพ์/แก้ไข) ใช้ <a href="log.html?ticket_id=${encodeURIComponent(id)}">log เดิม</a> ชั่วคราว</p>`,
            showCancelButton: false,
            confirmButtonText: 'ปิด',
        });
    }

    function onTableClick(e) {
        const btn = e.target.closest('[data-action]');
        if (!btn || btn.disabled) return;
        const action = btn.dataset.action;
        const id = btn.dataset.id;
        if (!id) return;
        switch (action) {
            case 'accept': acceptTicket(id); break;
            case 'start': startTicket(id); break;
            case 'pause': pauseTicket(id); break;
            case 'cancel': cancelTicket(id); break;
            case 'detail': showDetail(id); break;
            case 'finish':
            case 'confirm':
            case 'reopen':
                toast('info', 'Phase 2', 'ใช้ log.html ชั่วคราว หรือรออัปเดต v2');
                break;
            default: break;
        }
    }

    function bindUi() {
        $('logv2-search')?.addEventListener('input', debounce(() => {
            state.filterSearch = $('logv2-search').value.trim();
            state.page = 1;
            reload();
        }, 400));

        $('logv2-status')?.addEventListener('change', (e) => {
            state.filterStatus = e.target.value;
            state.page = 1;
            reload();
        });

        $('logv2-company')?.addEventListener('change', (e) => {
            state.filterCompany = e.target.value;
            state.page = 1;
            reload();
        });

        $('logv2-my-queue')?.addEventListener('click', (e) => {
            state.myQueueOnly = !state.myQueueOnly;
            e.currentTarget.classList.toggle('active', state.myQueueOnly);
            state.page = 1;
            reload();
        });

        $('logv2-refresh')?.addEventListener('click', () => reload());
        $('logv2-prev')?.addEventListener('click', () => { if (state.page > 1) { state.page--; reload(); } });
        $('logv2-next')?.addEventListener('click', () => {
            if (state.pagination && state.page < state.pagination.totalPages) {
                state.page++;
                reload();
            }
        });

        $('logv2-tbody')?.addEventListener('click', onTableClick);
    }

    function showInlineToast(msg, color) {
        const colors = { green: '#059669', red: '#dc2626', orange: '#d97706' };
        const t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = `position:fixed;top:70px;right:16px;z-index:9999;background:${colors[color]||'#334155'};color:#fff;padding:10px 16px;border-radius:10px;font-size:13px;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.15);transition:opacity .3s`;
        document.body.appendChild(t);
        setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 320); }, 2500);
    }

    function debounce(fn, ms) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    }

    async function init() {
        if ('serviceWorker' in navigator && document.querySelector('meta[name="ptc-no-sw"]')) {
            const regs = await navigator.serviceWorker.getRegistrations();
            await Promise.all(regs.map((r) => r.unregister()));
        }

        const sess = await fetchJson('api.php?action=check_session');
        if (!sess.loggedin) {
            location.href = 'login.html';
            return;
        }

        const isIt = sess.role === 'admin' || sess.primary_department === 'IT';
        if (!isIt) {
            await alertBox('error', 'ไม่มีสิทธิ์', 'หน้านี้สำหรับทีม IT เท่านั้น');
            location.href = 'index.html';
            return;
        }

        state.userId = sess.user_id;
        state.userName = sess.display_name || '';
        state.userRole = sess.role;
        $('logv2-user-label').textContent = state.userName;

        bindUi();
        await reload();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
