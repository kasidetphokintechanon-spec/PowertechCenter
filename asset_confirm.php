<?php
// asset_confirm.php — public asset audit confirmation page (token-based, no login)
error_reporting(0);
$token = trim($_GET['token'] ?? '');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ยืนยันทรัพย์สิน — Powertech IT</title>
<link rel="stylesheet" href="css/fonts/inter-kanit-local.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter','IBM Plex Sans Thai',sans-serif;background:#f1f5f9;color:#1e293b;min-height:100vh}
.topbar{background:#fff;border-bottom:1px solid #e2e8f0;padding:14px 20px;display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:10}
.topbar-logo{width:32px;height:32px;object-fit:contain}
.topbar-title{font-size:15px;font-weight:700;color:#1e293b}
.topbar-sub{font-size:11px;color:#64748b;margin-top:1px}
.wrap{max-width:520px;margin:0 auto;padding:20px 16px 40px}
.card{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:18px;margin-bottom:14px}
.card-hd{font-size:13px;font-weight:700;color:#374151;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.round-banner{background:linear-gradient(135deg,#eff6ff,#f0fdf4);border:1px solid #bfdbfe;border-radius:12px;padding:16px 18px;margin-bottom:18px}
.round-name{font-size:16px;font-weight:800;color:#1d4ed8;margin-bottom:4px}
.round-meta{font-size:12px;color:#64748b}
.round-meta b{color:#374151}
.asset-row{border:1.5px solid #e2e8f0;border-radius:10px;padding:14px;margin-bottom:10px;transition:border-color .15s}
.asset-row.confirmed{border-color:#10b981;background:#f0fdf4}
.asset-row.missing  {border-color:#ef4444;background:#fef2f2}
.asset-row.transferred{border-color:#f59e0b;background:#fffbeb}
.asset-id{font-family:monospace;font-size:11.5px;font-weight:700;color:#2563eb;background:#eff6ff;padding:2px 7px;border-radius:5px}
.asset-name{font-size:14px;font-weight:700;color:#1e293b;margin:6px 0 2px}
.asset-meta{font-size:11.5px;color:#64748b}
.asset-img{width:56px;height:56px;object-fit:contain;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;flex-shrink:0}
.asset-img-placeholder{width:56px;height:56px;border-radius:8px;border:1px dashed #e2e8f0;background:#f8fafc;display:flex;align-items:center;justify-content:center;color:#cbd5e1;flex-shrink:0;font-size:20px}
.asset-top{display:flex;gap:12px;align-items:flex-start}
.asset-info{flex:1;min-width:0}
.status-btns{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}
.sbtn{flex:1;min-width:0;padding:8px 4px;border-radius:8px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;transition:all .15s;font-family:inherit;color:#475569}
.sbtn:hover{background:#f1f5f9}
.sbtn.active-ok{background:#10b981;border-color:#10b981;color:#fff}
.sbtn.active-miss{background:#ef4444;border-color:#ef4444;color:#fff}
.sbtn.active-move{background:#f59e0b;border-color:#f59e0b;color:#fff}
.note-area{width:100%;margin-top:10px;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:12.5px;resize:none;color:#374151;background:#fafafa;display:none}
.note-area:focus{outline:none;border-color:#94a3b8}
.submit-btn{width:100%;padding:14px;background:#2563eb;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:8px;transition:background .15s}
.submit-btn:hover{background:#1d4ed8}
.submit-btn:disabled{background:#94a3b8;cursor:not-allowed}
.msg{text-align:center;padding:40px 20px}
.msg-icon{font-size:52px;margin-bottom:16px}
.msg-title{font-size:20px;font-weight:800;margin-bottom:8px}
.msg-sub{font-size:13.5px;color:#64748b;line-height:1.7}
.progress-bar-wrap{height:4px;background:#e2e8f0;border-radius:99px;margin-bottom:16px;overflow:hidden}
#prog-bar{height:100%;background:#10b981;border-radius:99px;width:0%;transition:width .3s}
.prog-label{font-size:11.5px;color:#64748b;text-align:right;margin-bottom:8px}
</style>
</head>
<body>
<div class="topbar">
    <img class="topbar-logo" src="uploads/logos/pta_logo.png" onerror="this.style.display='none'">
    <div>
        <div class="topbar-title">Powertech IT</div>
        <div class="topbar-sub">ระบบยืนยันทรัพย์สิน</div>
    </div>
</div>

<div class="wrap" id="app">
    <div class="msg"><div class="msg-icon">⏳</div><div class="msg-title">กำลังโหลด...</div></div>
</div>

<script>
const TOKEN = <?= json_encode($token) ?>;
const BASE  = 'api.php';
let _data   = null;
let _states = {}; // asset_db_id → {status, note}

async function init() {
    const app = document.getElementById('app');
    if (!TOKEN) { renderError('ลิงก์ไม่ถูกต้อง', 'ไม่พบ token กรุณาใช้ลิงก์จาก LINE ที่ได้รับ'); return; }
    try {
        const res  = await fetch(`${BASE}?action=get_audit_confirm&token=${encodeURIComponent(TOKEN)}`);
        const json = await res.json();
        if (json.status !== 'success') { renderError('ไม่พบข้อมูล', json.message || ''); return; }
        _data = json;
        if (json.already_done) { renderDone(); return; }
        json.items.forEach(i => { _states[i.asset_db_id] = { status: i.status === 'pending' ? '' : i.status, note: i.note || '' }; });
        render();
    } catch(e) { renderError('เกิดข้อผิดพลาด', 'ไม่สามารถโหลดข้อมูลได้'); }
}

function render() {
    const d = _data;
    const deadline = d.deadline ? new Date(d.deadline).toLocaleDateString('th-TH',{year:'numeric',month:'long',day:'numeric'}) : null;
    document.getElementById('app').innerHTML = `
        <div class="round-banner">
            <div class="round-name"><i class="fas fa-clipboard-check" style="margin-right:8px"></i>${esc(d.round_name)}</div>
            <div class="round-meta">สวัสดีคุณ <b>${esc(d.employee_name)}</b>${deadline ? ` · กรุณายืนยันภายใน <b>${deadline}</b>` : ''}</div>
        </div>
        <div class="prog-label" id="prog-label">ยืนยันแล้ว 0 / ${d.items.length} รายการ</div>
        <div class="progress-bar-wrap"><div id="prog-bar"></div></div>
        <div class="card">
            <div class="card-hd"><i class="fas fa-laptop" style="color:#2563eb"></i> รายการทรัพย์สินของคุณ</div>
            ${d.items.map(item => renderItem(item)).join('')}
        </div>
        <button class="submit-btn" id="submit-btn" onclick="submitAll()" disabled>
            <i class="fas fa-check-circle"></i> ยืนยันทั้งหมด
        </button>
        <div style="text-align:center;font-size:11.5px;color:#94a3b8;margin-top:12px">เมื่อกดยืนยันแล้วจะไม่สามารถแก้ไขได้</div>`;
    updateProgress();
}

function renderItem(item) {
    const id   = item.asset_db_id;
    const eqId = esc(item.equipment_id || '');
    const name = esc(item.asset_name || 'ไม่ระบุชื่อ');
    const meta = [item.brand, item.model, item.serial_number ? 'S/N: '+item.serial_number : '', item.location].filter(Boolean).map(esc).join(' · ');
    const imgEl = item.image_url
        ? `<img class="asset-img" src="${esc(item.image_url)}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
        : '';
    const placeholder = `<div class="asset-img-placeholder" ${item.image_url ? 'style="display:none"' : ''}><i class="fas fa-laptop"></i></div>`;
    return `<div class="asset-row" id="row-${id}">
        <div class="asset-top">
            ${imgEl}${placeholder}
            <div class="asset-info">
                <div><span class="asset-id">${eqId}</span></div>
                <div class="asset-name">${name}</div>
                ${meta ? `<div class="asset-meta">${meta}</div>` : ''}
            </div>
        </div>
        <div class="status-btns">
            <button class="sbtn" id="btn-ok-${id}"   onclick="setStatus(${id},'confirmed')"><i class="fas fa-check"></i> มีอยู่</button>
            <button class="sbtn" id="btn-miss-${id}" onclick="setStatus(${id},'missing')"><i class="fas fa-times"></i> หาไม่พบ</button>
            <button class="sbtn" id="btn-move-${id}" onclick="setStatus(${id},'transferred')"><i class="fas fa-arrow-right"></i> ถ่ายโอนแล้ว</button>
        </div>
        <textarea class="note-area" id="note-${id}" rows="2" placeholder="หมายเหตุ (ไม่บังคับ)..." oninput="setNote(${id},this.value)"></textarea>
    </div>`;
}

function setStatus(id, status) {
    _states[id].status = status;
    const row  = document.getElementById('row-'+id);
    const btnOk   = document.getElementById('btn-ok-'+id);
    const btnMiss = document.getElementById('btn-miss-'+id);
    const btnMove = document.getElementById('btn-move-'+id);
    const note = document.getElementById('note-'+id);
    row.className = 'asset-row ' + (status === 'confirmed' ? 'confirmed' : status === 'missing' ? 'missing' : 'transferred');
    btnOk.className   = 'sbtn' + (status === 'confirmed'   ? ' active-ok'   : '');
    btnMiss.className = 'sbtn' + (status === 'missing'     ? ' active-miss'  : '');
    btnMove.className = 'sbtn' + (status === 'transferred' ? ' active-move'  : '');
    note.style.display = status !== 'confirmed' ? 'block' : 'none';
    updateProgress();
}

function setNote(id, val) { _states[id].note = val; }

function updateProgress() {
    const total = Object.keys(_states).length;
    const done  = Object.values(_states).filter(s => s.status !== '').length;
    const pct   = total > 0 ? Math.round(done / total * 100) : 0;
    const bar = document.getElementById('prog-bar');
    const lbl = document.getElementById('prog-label');
    const btn = document.getElementById('submit-btn');
    if (bar) bar.style.width = pct + '%';
    if (lbl) lbl.textContent = `ยืนยันแล้ว ${done} / ${total} รายการ`;
    if (btn) btn.disabled = done < total;
}

async function submitAll() {
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
    const items = Object.entries(_states).map(([id, s]) => ({ asset_db_id: parseInt(id), status: s.status, note: s.note }));
    try {
        const fd = new FormData();
        fd.append('action', 'submit_audit_confirm');
        fd.append('token', TOKEN);
        fd.append('items', JSON.stringify(items));
        const res  = await fetch(BASE, { method: 'POST', body: fd });
        const json = await res.json();
        if (json.status === 'success') renderDone();
        else { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-circle"></i> ยืนยันทั้งหมด'; alert('เกิดข้อผิดพลาด: ' + (json.message||'')); }
    } catch(e) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-circle"></i> ยืนยันทั้งหมด'; alert('ไม่สามารถส่งข้อมูลได้'); }
}

function renderDone() {
    document.getElementById('app').innerHTML = `
        <div class="msg">
            <div class="msg-icon">✅</div>
            <div class="msg-title">ยืนยันเรียบร้อยแล้ว</div>
            <div class="msg-sub">ขอบคุณที่ยืนยันรายการทรัพย์สิน<br>ทีม IT ได้รับข้อมูลแล้วครับ</div>
        </div>`;
}

function renderError(title, msg) {
    document.getElementById('app').innerHTML = `
        <div class="msg">
            <div class="msg-icon">❌</div>
            <div class="msg-title">${esc(title)}</div>
            <div class="msg-sub">${esc(msg)}</div>
        </div>`;
}

function esc(s) { const d = document.createElement('div'); d.textContent = String(s??''); return d.innerHTML; }

init();
</script>
</body>
</html>
