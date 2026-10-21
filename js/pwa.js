/**
 * PWA service worker registration.
 * On QNAP HTTPS with self-signed cert, registration throws SecurityError — skip SW; app works normally.
 */
(function () {
    if (!('serviceWorker' in navigator)) return;
    if (document.querySelector('meta[name="ptc-no-sw"]')) return;

    window.addEventListener('load', () => {
        if (!window.isSecureContext) {
            return;
        }

        const swUrl = new URL('app-sw.js', document.baseURI);
        swUrl.searchParams.set('v', '20260530');

        navigator.serviceWorker.register(swUrl.pathname + swUrl.search).catch((err) => {
            const msg = String(err && err.message ? err.message : err);
            const certOrSecurity =
                (err && err.name === 'SecurityError') ||
                /SSL|certificate|secure origin/i.test(msg);

            if (certOrSecurity) {
                console.info('[PWA] Offline install skipped — HTTPS certificate not trusted for Service Worker (QNAP self-signed is OK for normal use).');
                return;
            }
            console.warn('[PWA] Service worker registration failed:', err);
        });
    });
})();
