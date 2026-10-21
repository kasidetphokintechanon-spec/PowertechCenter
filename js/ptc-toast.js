/**
 * ptc-toast.js — native bottom-right toast notifications
 * Usage: ptcToast.success('title', 'optional msg')
 *        ptcToast.error / .warning / .info
 */
(function () {
    'use strict';
    var CONTAINER_ID = 'ptc-toast-root';

    function getContainer() {
        var el = document.getElementById(CONTAINER_ID);
        if (!el) {
            el = document.createElement('div');
            el.id = CONTAINER_ID;
            el.setAttribute('aria-live', 'polite');
            el.setAttribute('aria-atomic', 'false');
            el.style.cssText = [
                'position:fixed',
                'bottom:20px',
                'right:20px',
                'z-index:99999',
                'display:flex',
                'flex-direction:column-reverse',
                'gap:8px',
                'pointer-events:none',
                'max-width:360px',
                'width:calc(100vw - 40px)',
            ].join(';');
            document.body.appendChild(el);
        }
        return el;
    }

    var CFG = {
        success: { icon: 'fa-check-circle',       color: '#16a34a', bg: '#f0fdf4', border: '#bbf7d0', darkBg: '#052e16', darkBorder: '#166534' },
        error:   { icon: 'fa-times-circle',        color: '#dc2626', bg: '#fef2f2', border: '#fecaca', darkBg: '#450a0a', darkBorder: '#991b1b' },
        warning: { icon: 'fa-exclamation-triangle', color: '#d97706', bg: '#fffbeb', border: '#fde68a', darkBg: '#422006', darkBorder: '#92400e' },
        info:    { icon: 'fa-info-circle',          color: '#2563eb', bg: '#eff6ff', border: '#bfdbfe', darkBg: '#172554', darkBorder: '#1e40af' },
    };

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function show(type, title, msg, duration) {
        if (duration == null) duration = 3000;
        var cfg = CFG[type] || CFG.info;
        var isDark = document.documentElement.classList.contains('dark');
        var bg     = isDark ? cfg.darkBg     : cfg.bg;
        var border = isDark ? cfg.darkBorder  : cfg.border;
        var textColor = isDark ? '#f1f5f9' : '#0f172a';
        var subColor  = isDark ? '#94a3b8' : '#64748b';

        var toast = document.createElement('div');
        toast.setAttribute('role', 'alert');
        toast.setAttribute('data-ptc-toast', '1');
        toast.style.cssText = [
            'pointer-events:auto',
            'background:' + bg,
            'border:1px solid ' + border,
            'border-radius:10px',
            'padding:11px 14px',
            'display:flex',
            'align-items:flex-start',
            'gap:10px',
            'box-shadow:0 4px 20px rgba(0,0,0,.13)',
            'opacity:0',
            'transform:translateX(14px)',
            'transition:opacity .18s ease,transform .18s ease',
        ].join(';');

        toast.innerHTML =
            '<i class="fa ' + cfg.icon + '" style="color:' + cfg.color + ';font-size:15px;margin-top:1px;flex-shrink:0"></i>' +
            '<div style="flex:1;min-width:0">' +
                '<div style="font-size:13px;font-weight:600;color:' + textColor + ';line-height:1.35">' + esc(title) + '</div>' +
                (msg ? '<div style="font-size:12px;color:' + subColor + ';margin-top:2px;line-height:1.4">' + esc(msg) + '</div>' : '') +
            '</div>' +
            '<button onclick="var t=this.closest(\'[data-ptc-toast]\');if(t)t.remove();" ' +
            'style="background:none;border:none;cursor:pointer;color:' + subColor + ';font-size:17px;line-height:1;padding:0 0 0 4px;flex-shrink:0" aria-label="close">×</button>';

        getContainer().appendChild(toast);

        // double rAF forces reflow before transition
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                toast.style.opacity = '1';
                toast.style.transform = 'translateX(0)';
            });
        });

        if (duration > 0) {
            setTimeout(function () {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(14px)';
                setTimeout(function () { if (toast.parentNode) toast.remove(); }, 220);
            }, duration);
        }
    }

    window.ptcToast = {
        show:    show,
        success: function (t, m, d) { show('success', t, m, d); },
        error:   function (t, m, d) { show('error',   t, m, d); },
        warning: function (t, m, d) { show('warning', t, m, d); },
        info:    function (t, m, d) { show('info',    t, m, d); },
    };
})();
