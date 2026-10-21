/**
 * PowertechCenter — shared SweetAlert2 UI helpers (enterprise theme)
 * Requires: sweetalert2 + js/enterprise-swal.js
 */
(function () {
    if (typeof Swal === 'undefined') return;

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function ptcSwal() {
        return window.PtcSwal || Swal;
    }

    function ptcWorkflowHead(opts) {
        const { step, title, ticketId, icon = 'fa-circle-info', tone = 'blue' } = opts || {};
        const tones = {
            blue: '#2563eb',
            amber: '#d97706',
            emerald: '#059669',
            violet: '#7c3aed',
            rose: '#e11d48',
            slate: '#64748b',
        };
        const color = tones[tone] || tones.blue;
        const safeTitle = escapeHtml(title || '');
        const safeId = ticketId ? escapeHtml(ticketId) : '';
        return `<div class="ptc-swal-flow-head mb-3 pb-3 border-b border-slate-100 dark:border-slate-700">
                ${step ? `<div class="text-[10px] font-bold uppercase tracking-wider mb-1.5" style="color:${color}">${escapeHtml(step)}</div>` : ''}
                <div class="flex items-start gap-2.5">
                    <span class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:${color}18;color:${color}"><i class="fas ${icon}"></i></span>
                    <div class="min-w-0">
                        <div class="font-semibold text-slate-800 dark:text-slate-100 text-sm leading-snug">${safeTitle}</div>
                        ${safeId ? `<div class="text-xs text-slate-500 mt-0.5 font-mono truncate">${safeId}</div>` : ''}
                    </div>
                </div>
            </div>`;
    }

    function ptcToast(icon, title, text) {
        const T = window.PtcToast || Swal;
        return T.fire({
            icon: icon || 'success',
            title,
            text: text || undefined,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: text ? 2800 : 2000,
            timerProgressBar: true,
        });
    }

    function ptcAlert(icon, title, text) {
        const iconMap = {
            success: { ico: 'fa-check-circle', tone: 'emerald', step: 'สำเร็จ' },
            error: { ico: 'fa-triangle-exclamation', tone: 'rose', step: 'ข้อผิดพลาด' },
            warning: { ico: 'fa-circle-exclamation', tone: 'amber', step: 'แจ้งเตือน' },
            info: { ico: 'fa-circle-info', tone: 'blue', step: 'ข้อมูล' },
        };
        const cfg = iconMap[icon || 'info'] || iconMap.info;
        const safeTitle = escapeHtml(title || 'แจ้งเตือน');
        const safeText = escapeHtml(text || '');
        return ptcSwal().fire({
            width: 'min(520px, 96vw)',
            html:
                ptcWorkflowHead({ step: cfg.step, title: safeTitle, icon: cfg.ico, tone: cfg.tone }) +
                (safeText ? `<p class="text-sm text-slate-600 dark:text-slate-300 text-left">${safeText}</p>` : ''),
            confirmButtonText: 'ตกลง',
        });
    }

    window.escapeHtml = escapeHtml;
    window.ptcSwal = ptcSwal;
    window.ptcWorkflowHead = ptcWorkflowHead;
    window.ptcToast = ptcToast;
    window.ptcAlert = ptcAlert;
})();
