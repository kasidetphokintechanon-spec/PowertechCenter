/**
 * PowertechCenter — SweetAlert2 enterprise styling v3.1
 * Fix: use captured _fire (nativeFire) for ALL calls to avoid infinite recursion.
 * PtcSwal.mixin() routes through Swal.fire which was overridden → stack overflow.
 * Solution: never call PtcSwal.fire/PtcToast.fire; call _fire directly with merged opts.
 */
(function () {
    if (typeof Swal === 'undefined') return;

    const PTC_SWAL_CLASS = {
        popup: 'ptc-swal-popup',
        title: 'ptc-swal-title',
        htmlContainer: 'ptc-swal-html',
        confirmButton: 'ptc-swal-confirm',
        cancelButton: 'ptc-swal-cancel',
        denyButton: 'ptc-swal-cancel',
        input: 'ptc-swal-input',
        select: 'ptc-swal-select',
        textarea: 'ptc-swal-textarea',
    };

    const PTC_DEFAULTS = {
        buttonsStyling: false,
        confirmButtonText: 'ตกลง',
        cancelButtonText: 'ยกเลิก',
    };

    const PTC_TOAST_DEFAULTS = {
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true,
        buttonsStyling: false,
    };

    function mergeCustomClass(base, extra) {
        if (!extra) return { ...base };
        const out = { ...base };
        Object.keys(extra).forEach((key) => {
            const a = base[key] || '';
            const b = extra[key] || '';
            out[key] = b ? `${a} ${b}`.trim() : a;
        });
        return out;
    }

    function isToastLike(opts) {
        if (opts.toast) return true;
        return (
            opts.timer > 0 &&
            opts.showConfirmButton === false &&
            !opts.input &&
            !opts.preConfirm &&
            !opts.html
        );
    }

    function normalizeOpts(arg1, arg2, arg3) {
        if (typeof arg1 === 'string') {
            return { title: arg1, text: arg2 || undefined, icon: arg3 || undefined };
        }
        return { ...(arg1 || {}) };
    }

    // Capture BEFORE any override — this is the only path that actually fires Swal
    const _fire = Swal.fire.bind(Swal);

    function ptcFire(arg1, arg2, arg3) {
        const opts = normalizeOpts(arg1, arg2, arg3);

        if (isToastLike(opts)) {
            return _fire({
                ...PTC_TOAST_DEFAULTS,
                ...opts,
                customClass: mergeCustomClass({ popup: 'ptc-swal-toast' }, opts.customClass),
            });
        }

        // buttonsStyling:false ทำให้ confirmButtonColor หาย → ปุ่ม "ลบ" สีแดงกลายเป็นสีน้ำเงิน (อันตราย) — ทาสีให้เองตอนเปิด
        if (opts.confirmButtonColor) {
            const color = opts.confirmButtonColor;
            const userDidOpen = opts.didOpen;
            opts.didOpen = (el) => {
                const b = Swal.getConfirmButton && Swal.getConfirmButton();
                if (b) { b.style.setProperty('background', color, 'important'); b.style.setProperty('border-color', color, 'important'); b.style.setProperty('color', '#fff', 'important'); }
                if (typeof userDidOpen === 'function') userDidOpen(el);
            };
        }

        return _fire({
            ...PTC_DEFAULTS,
            ...opts,
            customClass: mergeCustomClass(PTC_SWAL_CLASS, opts.customClass),
        });
    }

    // Replace Swal.fire with the enterprise version
    Swal.fire = ptcFire;

    // Swal.mixin(...) สืบทอด Swal.fire ที่ถูกแทนแล้ว → ค่าของ mixin (toast:true ฯลฯ) หาย กลายเป็นกล่องกลางจอ
    // ให้ .fire ของ mixin รวมค่า mixin เข้าไปก่อนส่งต่อ ptcFire
    const _mixin = Swal.mixin.bind(Swal);
    Swal.mixin = function (mixinParams) {
        const M = _mixin(mixinParams);
        M.fire = (a1, a2, a3) => ptcFire({ ...(mixinParams || {}), ...normalizeOpts(a1, a2, a3) });
        return M;
    };

    // Expose PtcSwal / PtcToast as thin wrappers that call ptcFire (no recursion)
    window.PtcSwal = {
        fire: ptcFire,
        isVisible: () => typeof Swal.isVisible === 'function' ? Swal.isVisible() : false,
        close: () => Swal.close(),
        showLoading: () => Swal.showLoading(),
        showValidationMessage: (msg) => Swal.showValidationMessage(msg),
    };

    window.PtcToast = {
        fire: (opts) => ptcFire({ ...(typeof opts === 'string' ? { title: opts } : opts), toast: true }),
    };
})();
