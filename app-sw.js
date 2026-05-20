const CACHE_NAME = 'powertech-app-v7';
const APP_SHELL = [
  'index.html',
  'login.html',
  'directory.html',
  'request.html',
  'log.html',
  'admin.html',
  'dashboard.html',
  'my_tickets.html',
  'borrow_management.html',
  'loan.html',
  'job_ticket.html',
  'task_center.html',
  'job_plan.html',
  'kb.html',
  'smart_booking.html',
  'audit_log.html',
  'settings_line.html',
  'settings_telegram.html',
  'admin_logo.html',
  'supplier.html',
  'my_borrow_history.html',
  'monthly_report.html',
  'room_kiosk.html',
  'room_schedule_a4.html',
  'start.html',
  'reg_generator.html',
  'forgot_password.html',
  'reset_password.html',
  'directory.css',
  'directory_New/public/xampp/styles.css',
  'directory_New/public/xampp/directory.js',
  'js/directory.js',
  'js/pwa.js',
  'app-manifest.json',
  'directory-manifest.json',
  'pwa-icon.svg',
  'uploads/logos/pta_logo.png',
  'uploads/logos/pt4_logo.png',
  'uploads/logos/pte_logo.png',
  'uploads/logos/powertech_app_icon.png',
  'uploads/logos/directory_app_icon.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) =>
      Promise.all(
        APP_SHELL.map((url) =>
          cache.add(url).catch(() => null)
        )
      )
    ).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(async () => {
        const cachedRequest = await caches.match(request);
        if (cachedRequest) return cachedRequest;
        const urlPath = new URL(request.url).pathname.toLowerCase();
        if (urlPath.includes('directory')) {
          return (await caches.match('directory.html')) || (await caches.match('index.html'));
        }
        return caches.match('index.html');
      })
    );
    return;
  }

  if (url.pathname.includes('api.php')) {
    event.respondWith(
      fetch(request)
        .then((res) => {
          const copy = res.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          return res;
        })
        .catch(() => caches.match(request))
    );
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => cached || fetch(request))
  );
});
