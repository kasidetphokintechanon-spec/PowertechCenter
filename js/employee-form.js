/* js/employee-form.js — ฟอร์มเพิ่ม/แก้ไขพนักงาน ใช้ร่วมกัน admin.html + directory.html
 *
 * PTCEmployeeForm.open({
 *   mode: 'add' | 'edit',
 *   employee: {...}            // แถวจาก api.php?action=get&file=employees (ตอน edit)
 *   canEditSensitive: bool,    // แก้ Username/Password ได้ (admin / admin.manage_permissions)
 *   canDelete: bool,
 *   onSaved({ id, isNew, hasSystemUser }),
 *   onDeleted(id),
 *   onOpenPermissions(id),     // ปุ่ม "กำหนดสิทธิ์ระบบ"
 *   presetSystemUser: bool,    // เพิ่มใหม่พร้อมเปิดส่วนบัญชีเข้าระบบไว้เลย
 * })
 *
 * ลำดับช่อง: ข้อมูลหลัก → สังกัด & ติดต่อ (สิ่งที่ Contact List ใช้) → ข้อมูลเสริม → บัญชีเข้าระบบ (IT)
 * ส่งข้อมูลรูปแบบเดียวกับฟอร์มเดิม (add_employee / update_employee) + nickname, phone_ext
 */
(function () {
    'use strict';

    const COMPANIES = ['PTA', 'PT4', 'PTE'];
    const CO_COLOR = { PTA: '#059669', PT4: '#dc2626', PTE: '#2563eb' };
    const LV = { 1: ['ผู้บริหาร', '#d97706'], 2: ['ผู้จัดการ', '#7c3aed'], 3: ['หัวหน้างาน', '#2563eb'], 4: ['วิศวกร / ผู้ชำนาญ', '#0891b2'], 5: ['พนักงาน', '#64748b'] };
    const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;

    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const day = v => (v && String(v).length >= 10 ? String(v).slice(0, 10) : '');
    const initials = n => (String(n || '').replace(/^(mr|ms|mrs|miss)\.?\s*/i, '').trim().split(/\s+/).map(w => w[0] || '').join('').slice(0, 2) || '?').toUpperCase();

    // ข้อมูลอ้างอิง โหลดครั้งเดียวต่อหน้า (ตำแหน่ง+ระดับ, แผนก, รหัสที่มีแล้ว, โดเมนอีเมลของแต่ละบริษัท)
    let refPromise = null;
    function loadRefs() {
        if (refPromise) return refPromise;
        const j = url => fetch(url, { cache: 'no-store' }).then(r => r.json()).catch(() => null);
        refPromise = Promise.all([
            j('api.php?action=get_job_positions'),
            j('api.php?action=get&file=departments'),
            j('api.php?action=get_public_directory'),
        ]).then(([jp, depts, emps]) => {
            const ref = { positions: [], levelOf: {}, depts: [], ids: new Set(), domains: {} };
            ((jp && jp.positions) || []).forEach(p => { ref.positions.push(p.name); ref.levelOf[String(p.name).toLowerCase()] = parseInt(p.org_level, 10) || 5; });
            ref.depts = (Array.isArray(depts) ? depts : []).map(d => d.dept_name || d.name).filter(Boolean);
            const dom = {};
            (Array.isArray(emps) ? emps : []).forEach(e => {
                ref.ids.add(String(e.id));
                (e.assignments || []).forEach(a => {
                    const d = String(a.email || '').split('@')[1];
                    if (a.company && d) { dom[a.company] = dom[a.company] || {}; dom[a.company][d] = (dom[a.company][d] || 0) + 1; }
                    if (a.department && !ref.depts.includes(a.department)) ref.depts.push(a.department);
                });
            });
            Object.keys(dom).forEach(c => { ref.domains[c] = Object.entries(dom[c]).sort((a, b) => b[1] - a[1])[0][0]; });
            ref.depts.sort((a, b) => a.localeCompare(b));
            return ref;
        });
        return refPromise;
    }

    // ย่อรูปในเบราว์เซอร์ก่อนส่ง (ด้านยาวสุด 400px, JPEG) — รูป 5MB จากมือถือเหลือไม่กี่สิบ KB
    function shrinkImage(file) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => {
                const s = Math.min(1, 400 / Math.max(img.width, img.height));
                const c = document.createElement('canvas');
                c.width = Math.round(img.width * s);
                c.height = Math.round(img.height * s);
                const ctx = c.getContext('2d');
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, c.width, c.height);
                ctx.drawImage(img, 0, 0, c.width, c.height);
                c.toBlob(b => (b ? resolve(b) : reject(new Error('ย่อรูปไม่สำเร็จ'))), 'image/jpeg', 0.85);
                URL.revokeObjectURL(img.src);
            };
            img.onerror = () => reject(new Error('เปิดไฟล์รูปไม่ได้'));
            img.src = URL.createObjectURL(file);
        });
    }

    function open(opts) {
        opts = opts || {};
        const isEdit = opts.mode === 'edit';
        const emp = (isEdit && opts.employee) || {};
        const canSys = !!opts.canEditSensitive;
        const hasSysUser = String(emp.username || '').trim() !== '';
        const presetSys = !isEdit && canSys && !!opts.presetSystemUser; // ปุ่ม "เพิ่มผู้ใช้ระบบ" ในหน้า admin
        const asgIn = Array.isArray(emp.assignments) ? emp.assignments : [];
        const st = {
            asg: asgIn.length
                ? asgIn.map(a => ({ company: a.company || '', dept: a.department || '', email: a.email || '', primary: Number(a.is_primary) === 1 }))
                : [{ company: '', dept: '', email: '', primary: true }],
            photoBlob: null,
            photoUrl: emp.image ? 'uploads/employees/' + emp.image : '',
            ref: { positions: [], levelOf: {}, depts: [], ids: new Set(), domains: {} },
            dirty: false,
            saving: false,
        };
        if (!st.asg.some(a => a.primary)) st.asg[0].primary = true;
        const flag = k => String(emp[k] == null ? '0' : emp[k]) === '1';

        const root = document.createElement('div');
        root.className = 'pef-backdrop';
        root.innerHTML = `
<div class="pef" role="dialog" aria-modal="true" aria-labelledby="pef-title">
  <div class="pef-head">
    <div><h2 id="pef-title">${isEdit ? 'แก้ไขข้อมูลพนักงาน' : 'เพิ่มพนักงานใหม่'}</h2>
      <div class="pef-sub">${isEdit ? 'รหัส ' + esc(emp.id) + ' · ' : ''}ข้อมูลนี้จะขึ้นใน Contact List ทุกเครื่องทันทีที่บันทึก</div></div>
    <button type="button" class="pef-x" data-act="close" title="ปิด (Esc)"><i class="fa fa-xmark"></i></button>
  </div>
  <nav class="pef-steps">
    <button type="button" data-go="1" class="on"><span class="n">1</span>ข้อมูลหลัก</button>
    <button type="button" data-go="2"><span class="n">2</span>สังกัด &amp; ติดต่อ</button>
    <button type="button" data-go="3"><span class="n">3</span>ข้อมูลเสริม</button>
    <button type="button" data-go="4"><span class="n">4</span>บัญชีเข้าระบบ (IT)</button>
  </nav>
  <div class="pef-body">
    <!-- autocomplete=off + ชื่อช่องไม่ใช่ username/password → เบราว์เซอร์ไม่เติม "admin"/รหัสผ่านของคนกรอกลงมาเอง -->
    <form class="pef-form" autocomplete="off" novalidate>
      <section class="pef-sec" data-sec="1">
        <div class="pef-sec-h"><span class="n">1</span>ข้อมูลหลัก</div>
        <div class="pef-grid">
          <div class="pef-f"><label>รหัสพนักงาน <span class="req">*</span></label><input class="pef-in" data-f="id" value="${esc(emp.id)}" ${isEdit ? 'readonly' : ''} autocomplete="off" inputmode="numeric" placeholder="เช่น 6901234"><div class="pef-msg" data-m="id"></div></div>
          <div class="pef-f"><label>สถานะ</label><select class="pef-in" data-f="status"><option value="active">ทำงานอยู่</option><option value="inactive" ${emp.employment_status === 'inactive' ? 'selected' : ''}>ลาออก / Inactive</option></select><div class="pef-msg"></div></div>
          <div class="pef-f pef-full pef-hidden" data-resign>
            <div class="pef-note warn"><i class="fa fa-circle-info"></i> ตั้งเป็นลาออกแล้ว ระบบจะสร้างงาน "ปิดสิทธิ์" ให้ IT อัตโนมัติ</div>
            <label>วันที่ออก</label><input class="pef-in" type="date" data-f="resign" value="${esc(day(emp.resign_date))}"><div class="pef-msg"></div></div>
          <div class="pef-f"><label>ชื่อ-สกุล (อังกฤษ) <span class="req">*</span></label><input class="pef-in" data-f="name" value="${esc(emp.name)}" autocomplete="off" placeholder="Mr. Somchai Jaidee"><div class="pef-msg"></div></div>
          <div class="pef-f"><label>ชื่อ-สกุล (ไทย)</label><input class="pef-in" data-f="name_th" value="${esc(emp.name_th)}" autocomplete="off" placeholder="นายสมชาย ใจดี"><div class="pef-msg"></div></div>
          <div class="pef-f"><label>ชื่อเล่น <span class="opt">(ใช้ค้นหาได้)</span></label><input class="pef-in" data-f="nickname" value="${esc(emp.nickname)}" autocomplete="off" placeholder="ชาย"><div class="pef-msg"></div></div>
          <div class="pef-f"><label>ตำแหน่ง <span class="req">*</span><span data-lv></span></label><input class="pef-in" data-f="position" value="${esc(emp.position)}" list="pef-poslist" autocomplete="off" placeholder="พิมพ์เพื่อค้นหา…"><datalist id="pef-poslist"></datalist><div class="pef-msg" data-m="position"></div></div>
        </div>
      </section>

      <section class="pef-sec" data-sec="2">
        <div class="pef-sec-h"><span class="n">2</span>สังกัด &amp; ติดต่อ <span class="hint">· สิ่งที่คนอื่นใช้ติดต่อ</span></div>
        <div class="pef-grid" style="margin-bottom:12px">
          <div class="pef-f"><label>เบอร์มือถือ</label><input class="pef-in" data-f="phone" value="${esc(emp.phone)}" inputmode="tel" autocomplete="off" placeholder="08x-xxx-xxxx"><div class="pef-msg" data-m="phone"></div></div>
          <div class="pef-f"><label>เบอร์ภายใน (Ext.) <span class="opt">(ถ้ามี)</span></label><input class="pef-in" data-f="phone_ext" value="${esc(emp.phone_ext)}" inputmode="numeric" autocomplete="off" placeholder="เช่น 1234"><div class="pef-msg"></div></div>
        </div>
        <div class="pef-f"><label>บริษัท / แผนก / อีเมล <span class="req">*</span> <span class="opt">— เลือกบริษัทแล้วระบบช่วยเติมอีเมล</span></label></div>
        <div data-asgs></div>
        <button type="button" class="pef-add" data-act="add-asg"><i class="fa fa-plus"></i> เพิ่มสังกัด (คนที่ดูแลหลายบริษัท)</button>
      </section>

      <section class="pef-sec" data-sec="3">
        <div class="pef-sec-h"><span class="n">3</span>ข้อมูลเสริม <span class="hint">· ไม่บังคับ</span></div>
        <div class="pef-grid">
          <div class="pef-f pef-full"><label>รูปพนักงาน</label>
            <div class="pef-photo"><span data-av></span>
              <div><button type="button" class="pef-btn outline sm" data-act="photo"><i class="fa fa-camera"></i> ${st.photoUrl ? 'เปลี่ยนรูป' : 'เลือกรูป'}</button>
                <input type="file" accept="image/*" data-file hidden>
                <div class="meta" data-photo-meta>ระบบย่อรูปให้อัตโนมัติ — รูปใหญ่แค่ไหนก็โหลดเร็ว</div></div></div></div>
          <div class="pef-f"><label>วันเริ่มงาน</label><input class="pef-in" type="date" data-f="start" value="${esc(day(emp.start_date))}"><div class="pef-msg"></div></div>
          <div class="pef-f"><label>วันเกิด <span class="opt">(ขึ้นป้าย 🎂)</span></label><input class="pef-in" type="date" data-f="bday" value="${esc(day(emp.birthdate))}"><div class="pef-msg"></div></div>
          <div class="pef-f pef-full"><label>แสดงในหมวด</label><div class="pef-chips">
            <button type="button" class="pef-chip ${flag('is_executive') ? 'on' : ''}" data-t="is_executive"><i class="fa fa-crown"></i> ผู้บริหาร</button>
            <button type="button" class="pef-chip ${flag('is_management') ? 'on' : ''}" data-t="is_management"><i class="fa fa-briefcase"></i> Management</button>
            <button type="button" class="pef-chip ${flag('is_technician') ? 'on' : ''}" data-t="is_technician"><i class="fa fa-wrench"></i> ช่างเทคนิค</button></div>
            <div class="pef-msg">ผู้บริหาร/Management ไม่บังคับแผนก · ช่างต้องมีอย่างน้อย 1 สังกัด</div></div>
        </div>
      </section>

      <section class="pef-sec" data-sec="4">
        <div class="pef-sec-h"><span class="n">4</span>บัญชีเข้าระบบ <span class="hint">· IT เป็นคนตั้ง</span><button type="button" class="tog" data-act="tog-sys">${hasSysUser || presetSys || !canSys ? 'ซ่อน ▴' : 'เปิด ▾'}</button></div>
        <div data-sys class="${hasSysUser || presetSys || !canSys ? '' : 'pef-hidden'}">
          ${!canSys ? '<div class="pef-note warn"><i class="fa fa-lock"></i> แก้ Username / รหัสผ่านได้เฉพาะผู้มีสิทธิ์จัดการสิทธิ์</div>' : ''}
          ${isEdit && hasSysUser ? `<div class="pef-note">บัญชีปัจจุบัน: <b>${esc(emp.username)}</b></div>` : ''}
          ${canSys ? `<label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:10px;font-size:13px;color:var(--ptc-text-2)"><input type="checkbox" data-f="sys" ${hasSysUser || presetSys ? 'checked' : ''}> ${isEdit ? 'แก้ไขบัญชีผู้ใช้ระบบ' : 'สร้างบัญชีเข้าระบบพร้อมกัน (ค่าเริ่มต้น = รหัสพนักงาน)'}</label>` : ''}
          <div class="pef-grid ${hasSysUser || presetSys ? '' : 'pef-hidden'}" data-sys-fields>
            <div class="pef-f"><label>Username</label><input class="pef-in" data-f="user" name="ptc-emp-login" value="${esc(emp.username)}" autocomplete="off" ${canSys ? '' : 'disabled'}><div class="pef-msg"></div></div>
            <div class="pef-f"><label>รหัสผ่าน${isEdit ? ' <span class="opt">(เว้นว่าง = ไม่เปลี่ยน)</span>' : ''}</label><input class="pef-in" type="password" data-f="pass" name="ptc-emp-secret" autocomplete="new-password" ${canSys ? '' : 'disabled'}><div class="pef-msg"></div></div>
          </div>
          ${isEdit && opts.onOpenPermissions ? '<button type="button" class="pef-btn outline sm" data-act="perm" style="margin-top:4px"><i class="fa fa-key"></i> กำหนดสิทธิ์ระบบ</button>' : ''}
        </div>
      </section>
    </form>
    <aside class="pef-side"><h4>ตัวอย่างใน Contact List</h4><div class="pef-card" data-card></div><div class="pef-check" data-check></div></aside>
  </div>
  <div class="pef-foot">
    ${isEdit && opts.canDelete ? '<button type="button" class="pef-btn danger sm" data-act="delete"><i class="fa fa-trash"></i> ลบ</button>' : ''}
    <div class="left" data-foot></div>
    <div class="btns">
      <button type="button" class="pef-btn ghost" data-act="close">ยกเลิก</button>
      <button type="button" class="pef-btn primary" data-act="save" disabled><i class="fa fa-check"></i> บันทึก</button>
    </div>
  </div>
</div>`;

        const q = s => root.querySelector(s);
        const f = k => q(`[data-f="${k}"]`);
        const formEl = q('.pef-form');

        // ── สังกัด ──
        function suggestEmail(a) {
            const dom = st.ref.domains[a.company];
            const parts = f('name').value.trim().replace(/^(mr|ms|mrs|miss)\.?\s*/i, '').toLowerCase().replace(/[^a-z\s]/g, '').split(/\s+/).filter(Boolean);
            if (!dom || !parts.length) return '';
            return (parts.length > 1 ? parts[0] + '.' + parts[parts.length - 1] : parts[0]) + '@' + dom;
        }
        function renderAsg() {
            const deptOpts = sel => '<option value="">แผนก</option>' + st.ref.depts.map(d => `<option ${sel === d ? 'selected' : ''}>${esc(d)}</option>`).join('')
                + (sel && !st.ref.depts.includes(sel) ? `<option selected>${esc(sel)}</option>` : '');
            q('[data-asgs]').innerHTML = st.asg.map((a, i) => {
                const sg = !a.email ? suggestEmail(a) : '';
                return `<div class="pef-asg">
                    <select class="pef-in" data-i="${i}" data-k="company"><option value="">บริษัท</option>${COMPANIES.map(c => `<option ${a.company === c ? 'selected' : ''}>${c}</option>`).join('')}</select>
                    <select class="pef-in" data-i="${i}" data-k="dept">${deptOpts(a.dept)}</select>
                    <div class="pef-email"><input class="pef-in" data-i="${i}" data-k="email" value="${esc(a.email)}" placeholder="อีเมล" autocomplete="off" inputmode="email">${sg ? `<button type="button" class="sugg" data-sugg="${i}" title="กดเพื่อใช้">ใช้ ${esc(sg)}</button>` : ''}</div>
                    <label class="prim"><input type="radio" name="pef-prim" data-i="${i}" ${a.primary ? 'checked' : ''}> หลัก</label>
                    <button type="button" class="del" data-del="${i}" title="ลบสังกัดนี้" ${st.asg.length === 1 ? 'disabled' : ''}><i class="fa fa-trash"></i></button>
                </div>`;
            }).join('');
        }
        q('[data-asgs]').addEventListener('change', e => {
            const i = +e.target.dataset.i, k = e.target.dataset.k;
            if (e.target.type === 'radio') st.asg.forEach((a, j) => { a.primary = j === i; });
            else if (k) { st.asg[i][k] = e.target.value; if (k === 'company') renderAsg(); }
            touch();
        });
        q('[data-asgs]').addEventListener('input', e => {
            if (e.target.dataset.k !== 'email') return;
            st.asg[+e.target.dataset.i].email = e.target.value.trim();
            const sg = e.target.parentElement.querySelector('.sugg');
            if (sg) sg.remove();
            touch();
        });
        q('[data-asgs]').addEventListener('click', e => {
            const s = e.target.closest('[data-sugg]');
            if (s) { const a = st.asg[+s.dataset.sugg]; a.email = suggestEmail(a); renderAsg(); touch(); return; }
            const d = e.target.closest('[data-del]');
            if (d && st.asg.length > 1) { st.asg.splice(+d.dataset.del, 1); if (!st.asg.some(a => a.primary)) st.asg[0].primary = true; renderAsg(); touch(); }
        });

        // ── ตรวจระหว่างพิมพ์ ──
        function setMsg(key, text, kind) {
            const m = q(`[data-m="${key}"]`), inp = f(key);
            if (m) { m.textContent = text; m.className = 'pef-msg ' + (kind || ''); }
            if (inp) { inp.classList.toggle('bad', kind === 'bad'); inp.classList.toggle('good', kind === 'good'); }
        }
        function chipOn(k) { return q(`.pef-chip[data-t="${k}"]`).classList.contains('on'); }
        function validate() {
            const id = f('id').value.trim();
            const dup = !isEdit && id && st.ref.ids.has(id);
            if (!isEdit) setMsg('id', !id ? '' : dup ? 'รหัสนี้มีในระบบแล้ว' : '✓ ใช้ได้', !id ? '' : dup ? 'bad' : 'good');
            const ph = f('phone').value.replace(/\D/g, '');
            setMsg('phone', ph && (ph.length < 9 || ph.length > 10) ? 'เบอร์ควรมี 9–10 หลัก' : '', ph && (ph.length < 9 || ph.length > 10) ? 'bad' : '');
            const lv = st.ref.levelOf[f('position').value.trim().toLowerCase()];
            q('[data-lv]').innerHTML = lv ? `<span class="pef-lv" style="--lvc:${LV[lv][1]}">${LV[lv][0]}</span>` : '';
            const looseAsg = chipOn('is_executive') || chipOn('is_management');
            const filled = st.asg.filter(a => a.company || a.dept || a.email);
            const asgOk = looseAsg ? filled.every(a => a.company) : (filled.length > 0 && filled.every(a => a.company && a.dept));
            const techOk = !chipOn('is_technician') || filled.some(a => a.company);
            const emailOk = st.asg.every(a => !a.email || EMAIL_RE.test(a.email));
            const sys = f('sys') && f('sys').checked;
            const sysOk = !sys || (f('user').value.trim() && (isEdit || f('pass').value.trim()));
            q('[data-resign]').classList.toggle('pef-hidden', f('status').value !== 'inactive');
            return { id: !!id && !dup, name: !!f('name').value.trim(), position: !!f('position').value.trim(), asg: asgOk && techOk, email: emailOk, phone: !(ph && (ph.length < 9 || ph.length > 10)), sys: !!sysOk };
        }

        // ── การ์ดตัวอย่าง + เช็กลิสต์ + ปุ่มบันทึก ──
        function avatarHtml(cls, name) {
            const src = st.photoBlobUrl || st.photoUrl;
            return src ? `<img class="${cls}" src="${esc(src)}" alt="" onerror="this.outerHTML='<span class=&quot;${cls}&quot;>${esc(initials(name))}</span>'">` : `<span class="${cls}">${esc(initials(name))}</span>`;
        }
        function update() {
            const v = validate();
            const p = st.asg.find(a => a.primary) || st.asg[0];
            const name = f('name').value.trim(), pos = f('position').value.trim(), nick = f('nickname').value.trim();
            const phone = f('phone').value.trim(), ext = f('phone_ext').value.trim();
            q('[data-av]').innerHTML = avatarHtml('pef-av', name);
            const card = q('[data-card]');
            card.style.setProperty('--co', CO_COLOR[p.company] || 'var(--ptc-border-strong)');
            card.innerHTML = `${avatarHtml('pef-av', name)}
                <div style="min-width:0">
                  <div class="nm"><span>${name ? esc(name) + (nick ? ` (${esc(nick)})` : '') : '<span class="pef-ph">ชื่อพนักงาน</span>'}</span>${p.company ? `<span class="co">${esc(p.company)}</span>` : ''}</div>
                  <div class="sub">${esc([pos, p.dept].filter(Boolean).join(' · ')) || '<span class="pef-ph">ตำแหน่ง · แผนก</span>'}</div>
                  <div class="ct">
                    ${phone || ext ? `<span class="pill"><i class="fa fa-phone"></i><span>${esc(phone)}${ext ? (phone ? ' · ' : '') + 'ต่อ ' + esc(ext) : ''}</span></span>` : ''}
                    ${p.email ? `<span class="pill"><i class="fa fa-envelope"></i><span>${esc(p.email)}</span></span>` : ''}
                    ${!phone && !ext && !p.email ? '<span class="pef-ph" style="font-size:12px">ยังไม่มีเบอร์/อีเมล</span>' : ''}
                  </div>
                </div>`;
            const items = [['id', isEdit ? 'รหัสพนักงาน' : 'รหัสพนักงาน (ไม่ซ้ำ)'], ['name', 'ชื่อภาษาอังกฤษ'], ['position', 'ตำแหน่ง'], ['asg', 'บริษัท + แผนก'], ['email', 'อีเมลถูกรูปแบบ'], ['phone', 'เบอร์ถูกรูปแบบ'], ['sys', 'บัญชีเข้าระบบครบ']];
            q('[data-check]').innerHTML = items.map(([k, t]) => `<div class="${v[k] ? 'y' : ''}"><i class="fa ${v[k] ? 'fa-circle-check' : 'fa-circle'}"></i>${t}</div>`).join('');
            const left = Object.values(v).filter(x => !x).length;
            q('[data-act="save"]').disabled = left > 0 || st.saving;
            const foot = q('[data-foot]');
            if (!foot.classList.contains('err')) foot.textContent = left ? `เหลืออีก ${left} รายการ` : (matchMedia('(pointer: coarse)').matches ? 'พร้อมบันทึก' : 'พร้อมบันทึก — กด Ctrl+Enter ได้');
            q('[data-go="1"]').classList.toggle('ok', v.id && v.name && v.position);
            q('[data-go="2"]').classList.toggle('ok', v.asg && v.email && v.phone);
            return left === 0;
        }
        function touch() { st.dirty = true; const foot = q('[data-foot]'); foot.classList.remove('err'); update(); }
        let nameT;
        formEl.addEventListener('input', e => {
            if (!e.target.dataset.f) return;
            if (e.target.dataset.f === 'id' && !isEdit) syncDefaultLogin();
            touch();
            if (e.target.dataset.f === 'name') { clearTimeout(nameT); nameT = setTimeout(renderAsg, 250); } // อัปเดตอีเมลแนะนำ
        });
        formEl.addEventListener('change', e => { if (e.target.dataset.f) touch(); });

        // ── บัญชีเข้าระบบ: ค่าเริ่มต้น = รหัสพนักงาน (ตอนเพิ่มใหม่) ──
        const autoLogin = { user: !isEdit, pass: !isEdit };
        function syncDefaultLogin() {
            if (!f('sys') || !f('sys').checked) return;
            const id = f('id').value.trim();
            if (autoLogin.user) f('user').value = id;
            if (autoLogin.pass) f('pass').value = id;
        }
        if (f('user')) f('user').addEventListener('input', () => { autoLogin.user = false; });
        if (f('pass')) f('pass').addEventListener('input', () => { autoLogin.pass = false; });
        if (f('sys')) f('sys').addEventListener('change', () => {
            q('[data-sys-fields]').classList.toggle('pef-hidden', !f('sys').checked);
            if (!isEdit) syncDefaultLogin();
        });

        // ── ปุ่มต่างๆ ──
        root.addEventListener('click', async e => {
            const chip = e.target.closest('.pef-chip');
            if (chip) { chip.classList.toggle('on'); touch(); return; }
            const go = e.target.closest('[data-go]');
            if (go) { q(`[data-sec="${go.dataset.go}"]`).scrollIntoView({ behavior: 'smooth', block: 'start' }); return; }
            const act = e.target.closest('[data-act]')?.dataset.act;
            if (act === 'close') return close();
            if (act === 'add-asg') { st.asg.push({ company: '', dept: '', email: '', primary: false }); renderAsg(); q('[data-asgs]').lastElementChild?.querySelector('select')?.focus(); return; }
            if (act === 'photo') return q('[data-file]').click();
            if (act === 'tog-sys') { const hidden = q('[data-sys]').classList.toggle('pef-hidden'); q('[data-act="tog-sys"]').textContent = hidden ? 'เปิด ▾' : 'ซ่อน ▴'; return; }
            if (act === 'perm') { opts.onOpenPermissions && opts.onOpenPermissions(String(emp.id)); return; }
            if (act === 'save') return save();
            if (act === 'delete') return remove();
        });
        root.addEventListener('mousedown', e => { if (e.target === root) root.dataset.downOutside = '1'; });
        root.addEventListener('mouseup', e => { if (e.target === root && root.dataset.downOutside === '1') close(); root.dataset.downOutside = ''; });

        q('[data-file]').addEventListener('change', async e => {
            const file = e.target.files && e.target.files[0];
            if (!file) return;
            try {
                st.photoBlob = await shrinkImage(file);
                if (st.photoBlobUrl) URL.revokeObjectURL(st.photoBlobUrl);
                st.photoBlobUrl = URL.createObjectURL(st.photoBlob);
                q('[data-photo-meta]').textContent = `ย่อจาก ${Math.round(file.size / 1024)} KB เหลือ ${Math.round(st.photoBlob.size / 1024)} KB`;
                touch();
            } catch (err) { q('[data-photo-meta]').textContent = err.message; }
        });

        // แท็บขั้นตอนเลื่อนตามตอนเลื่อนฟอร์ม
        const io = new IntersectionObserver(es => es.forEach(en => {
            if (en.isIntersecting) root.querySelectorAll('.pef-steps button').forEach(b => b.classList.toggle('on', b.dataset.go === en.target.dataset.sec));
        }), { root: formEl, threshold: 0.35 });
        root.querySelectorAll('.pef-sec').forEach(s => io.observe(s));

        function onKey(e) {
            if (e.key === 'Escape') { e.preventDefault(); close(); }
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); save(); }
        }
        document.addEventListener('keydown', onKey, true);

        function close(force) {
            if (!force && st.dirty && !confirm('ยังไม่ได้บันทึก — ปิดฟอร์มเลยไหม?')) return;
            io.disconnect();
            document.removeEventListener('keydown', onKey, true);
            if (st.photoBlobUrl) URL.revokeObjectURL(st.photoBlobUrl);
            root.remove();
            document.body.style.overflow = prevOverflow;
        }

        function fail(msg) {
            const foot = q('[data-foot]');
            foot.textContent = msg;
            foot.classList.add('err');
        }

        async function save() {
            if (st.saving || !update()) return;
            for (const k of ['start', 'bday', 'resign']) {
                const v = f(k).value.trim();
                if (v && !DATE_RE.test(v)) return fail('รูปแบบวันที่ไม่ถูกต้อง');
            }
            const filled = st.asg.filter(a => a.company || a.dept || a.email);
            if (filled.length && !filled.some(a => a.primary)) filled[0].primary = true;
            const id = f('id').value.trim();
            const sys = !!(f('sys') && f('sys').checked);
            const fd = new FormData();
            fd.append('action', isEdit ? 'update_employee' : 'add_employee');
            fd.append('id', id);
            fd.append('name', f('name').value.trim());
            fd.append('name_th', f('name_th').value.trim());
            fd.append('nickname', f('nickname').value.trim());
            fd.append('position', f('position').value.trim());
            fd.append('phone', f('phone').value.trim());
            fd.append('phone_ext', f('phone_ext').value.trim());
            fd.append('employment_status', f('status').value);
            fd.append('start_date', f('start').value.trim());
            fd.append('birthdate', f('bday').value.trim());
            if (isEdit) fd.append('resign_date', f('status').value === 'inactive' ? f('resign').value.trim() : '');
            ['is_executive', 'is_management', 'is_technician'].forEach(k => fd.append(k, chipOn(k) ? '1' : '0'));
            fd.append('assignments', JSON.stringify(filled.map(a => ({ company: a.company, department: a.dept, email: a.email, is_primary: a.primary ? 1 : 0 }))));
            fd.append('manage_system_user', sys ? '1' : '0');
            if (sys) {
                fd.append('username', f('user').value.trim());
                if (f('pass').value) fd.append('password', f('pass').value);
            }
            if (st.photoBlob) fd.append('imageFile', st.photoBlob, 'photo.jpg');

            st.saving = true;
            const btn = q('[data-act="save"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> กำลังบันทึก…';
            try {
                // ตำแหน่งใหม่ที่ยังไม่มีในรายการ → เพิ่มเป็นตัวเลือก (เหมือนฟอร์มเดิม)
                const pos = f('position').value.trim();
                if (pos && !st.ref.positions.some(p => p.toLowerCase() === pos.toLowerCase())) {
                    const fp = new FormData();
                    fp.append('action', 'save_job_position');
                    fp.append('name', pos);
                    await fetch('api.php', { method: 'POST', body: fp }).catch(() => null);
                }
                const res = await fetch('api.php', { method: 'POST', body: fd });
                let data = null;
                try { data = await res.json(); } catch (_) { /* ตอบกลับไม่ใช่ JSON */ }
                if (!res.ok || !data || data.status !== 'success') throw new Error((data && data.message) || 'บันทึกไม่สำเร็จ');
                refPromise = null; // รหัส/ตำแหน่งเปลี่ยน — โหลดใหม่รอบหน้า
                close(true);
                opts.onSaved && opts.onSaved({ id, isNew: !isEdit, hasSystemUser: sys });
            } catch (err) {
                fail(err.message || 'บันทึกไม่สำเร็จ');
            } finally {
                st.saving = false;
                if (root.isConnected) { btn.innerHTML = '<i class="fa fa-check"></i> บันทึก'; update(); }
            }
        }

        async function remove() {
            const name = f('name').value.trim() || emp.id;
            const ok = typeof Swal !== 'undefined'
                ? (await Swal.fire({ icon: 'warning', title: 'ลบพนักงานคนนี้?', text: `${name} — ลบแล้วกู้คืนไม่ได้ (ถ้าแค่ลาออก ให้เปลี่ยนสถานะเป็น "ลาออก" แทน)`, showCancelButton: true, confirmButtonText: 'ลบ', cancelButtonText: 'ยกเลิก', confirmButtonColor: '#dc2626' })).isConfirmed
                : confirm(`ลบ ${name}? กู้คืนไม่ได้`);
            if (!ok) return;
            const fd = new FormData();
            fd.append('action', 'delete_item');
            fd.append('key', 'employees');
            fd.append('id', String(emp.id));
            try {
                const res = await fetch('api.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.status !== 'success') throw new Error(data.message || 'ลบไม่สำเร็จ');
                refPromise = null;
                close(true);
                opts.onDeleted && opts.onDeleted(String(emp.id));
            } catch (err) { fail(err.message || 'ลบไม่สำเร็จ'); }
        }

        const prevOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        document.body.appendChild(root);
        renderAsg();
        update();
        setTimeout(() => (isEdit ? f('name') : f('id')).focus(), 50);

        loadRefs().then(ref => {
            st.ref = ref;
            if (isEdit) st.ref.ids = new Set([...ref.ids].filter(x => x !== String(emp.id)));
            q('#pef-poslist').innerHTML = ref.positions.map(p => `<option value="${esc(p)}">`).join('');
            if (root.isConnected) { renderAsg(); update(); }
        });
    }

    window.PTCEmployeeForm = { open, preload: loadRefs };
})();
