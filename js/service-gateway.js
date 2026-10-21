/**
 * Shared client-first service link opener (Primary → Backup → optional server probe).
 * Used by index.html and admin.html.
 */
(function (global) {
    'use strict';

    function escapeJsString(text) {
        return String(text || '')
            .replace(/\\/g, '\\\\')
            .replace(/'/g, "\\'")
            .replace(/\r/g, '\\r')
            .replace(/\n/g, '\\n')
            .replace(/\u2028/g, '\\u2028')
            .replace(/\u2029/g, '\\u2029');
    }

    function normalizeServiceUrlInput(raw, required = false) {
        let v = String(raw || '').trim();
        v = v.replace(/\\\//g, '/').replace(/\\/g, '/').trim();
        if (!v) {
            if (required) throw new Error('กรุณาระบุ Primary URL');
            return '';
        }
        if (!/^https?:\/\//i.test(v)) return v;
        let u;
        try {
            u = new URL(v);
        } catch (_) {
            // Keep flexible inputs (program/path style) as-is.
            return v;
        }
        if (!['http:', 'https:'].includes(u.protocol)) throw new Error('อนุญาตเฉพาะ URL ที่ขึ้นต้นด้วย http/https');
        return u.toString();
    }

    function waitMs(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }

    function isClientFetchProbeBlockedByContext(targetUrl) {
        try {
            const parsed = new URL(targetUrl);
            return global.isSecureContext && global.location.protocol === 'https:' && parsed.protocol === 'http:';
        } catch (_) {
            return false;
        }
    }

    async function probeUrlFromClient(url, options = {}) {
        const { timeoutMs = 1200, retries = 1, label = 'service' } = options || {};
        const target = normalizeServiceUrlInput(url, true);

        if (isClientFetchProbeBlockedByContext(target)) {
            return { reachable: false, uncertain: true, reason: 'mixed-content-restriction', attempts: 0, target };
        }

        let lastReason = 'fetch-error';
        for (let i = 0; i <= retries; i++) {
            const controller = new AbortController();
            const timeoutForAttempt = timeoutMs + i * 700;
            const timeoutId = setTimeout(() => controller.abort(), timeoutForAttempt);
            try {
                const probeUrl = new URL(target);
                probeUrl.searchParams.set('_ptc_probe', `${Date.now()}_${label}_${i}`);
                await fetch(probeUrl.toString(), {
                    mode: 'no-cors',
                    cache: 'no-store',
                    redirect: 'follow',
                    credentials: 'omit',
                    referrerPolicy: 'no-referrer',
                    signal: controller.signal
                });
                clearTimeout(timeoutId);
                return { reachable: true, uncertain: false, reason: 'ok', attempts: i + 1, target };
            } catch (err) {
                clearTimeout(timeoutId);
                lastReason = err?.name === 'AbortError' ? 'timeout' : 'fetch-error';
                if (i < retries) await waitMs(80 + i * 120);
            }
        }
        return { reachable: false, uncertain: false, reason: lastReason, attempts: retries + 1, target };
    }

    function renderServiceGatewayWindow(targetWin, html) {
        if (!targetWin || targetWin.closed) return;
        targetWin.document.open();
        targetWin.document.write(html);
        targetWin.document.close();
    }

    function notifyHealth(options, state, source) {
        if (typeof options?.onHealthState === 'function') {
            options.onHealthState(state, { source });
        }
    }

    function serviceGatewayShellTemplate({ statusText = '', subtitleText = '', stageHtml = '', stageTone = 'primary', showSpinner = false }) {
        return `<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Powertech Service Gateway</title><style>
            :root{color-scheme:light dark;--gw-bg:#eef3fb;--gw-card:#ffffff;--gw-text:#0f172a;--gw-sub:#475569;--gw-border:#dbe6f4;--gw-brand:#0f62fe;--gw-brand-2:#4f46e5;--gw-warn:#d97706;--gw-danger:#dc2626;--gw-soft:#f8fafc;--gw-ring:rgba(15,98,254,.3);--gw-shadow:0 22px 48px rgba(15,23,42,.16)}
            @media (prefers-color-scheme: dark){:root{--gw-bg:#030712;--gw-card:#0f172a;--gw-text:#e5e7eb;--gw-sub:#94a3b8;--gw-border:#1f2937;--gw-brand:#60a5fa;--gw-brand-2:#818cf8;--gw-warn:#f59e0b;--gw-danger:#f87171;--gw-soft:#111827;--gw-ring:rgba(129,140,248,.42);--gw-shadow:0 28px 70px rgba(0,0,0,.55)}}
            *{box-sizing:border-box} html,body{height:100%} body{margin:0;font-family:Inter,'Segoe UI',Tahoma,sans-serif;color:var(--gw-text);background:radial-gradient(1200px 520px at 50% -180px,rgba(96,165,250,.28),transparent),var(--gw-bg);display:grid;place-items:center;padding:20px}
            .gw-screen{width:min(94vw,640px);background:linear-gradient(180deg,rgba(255,255,255,.1),transparent),var(--gw-card);border:1px solid var(--gw-border);border-radius:20px;box-shadow:var(--gw-shadow);overflow:hidden}
            .gw-brand{display:flex;gap:14px;align-items:center;padding:18px 20px;background:linear-gradient(135deg,var(--gw-brand),var(--gw-brand-2));color:#fff}
            .gw-logo{width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,.15);display:grid;place-items:center;font-weight:800;font-size:20px;letter-spacing:.02em}
            .gw-brand-title{margin:0;font-size:16px;font-weight:800;line-height:1.2}
            .gw-brand-sub{margin:4px 0 0;font-size:12px;opacity:.93}
            .gw-main{padding:24px}
            .gw-status{display:inline-flex;align-items:center;gap:8px;padding:6px 12px;border-radius:999px;background:var(--gw-soft);border:1px solid var(--gw-border);color:var(--gw-sub);font-weight:700;font-size:12px}
            .gw-dot{width:9px;height:9px;border-radius:50%;background:var(--gw-brand)}
            .gw-dot.warn{background:var(--gw-warn)} .gw-dot.danger{background:var(--gw-danger)}
            .gw-stage{margin-top:14px}
            .gw-title{margin:0 0 8px;font-size:28px;line-height:1.15;letter-spacing:-.01em}
            .gw-subtitle{margin:0 0 14px;font-size:14px;line-height:1.5;color:var(--gw-sub)}
            .gw-intent{margin:0 0 16px;padding:12px 14px;border-radius:12px;background:var(--gw-soft);border:1px solid var(--gw-border);font-size:13px;line-height:1.45}
            .gw-intent b{font-weight:800}
            .gw-spinner{width:44px;height:44px;border:4px solid color-mix(in srgb,var(--gw-brand) 20%,transparent);border-top-color:var(--gw-brand);border-radius:50%;animation:gw-spin 1s linear infinite;margin-bottom:12px}
            .gw-spinner.warn{border-top-color:var(--gw-warn)}
            .gw-actions{display:flex;flex-wrap:wrap;gap:10px}
            .gw-btn{appearance:none;border:1px solid transparent;border-radius:11px;padding:10px 16px;font-size:13px;font-weight:800;cursor:pointer;transition:transform .16s ease,filter .16s ease,box-shadow .16s ease}
            .gw-btn:hover{transform:translateY(-1px);filter:brightness(1.02)}
            .gw-btn:focus-visible{outline:none;box-shadow:0 0 0 3px var(--gw-ring)}
            .gw-btn.primary{background:var(--gw-brand);color:#fff}
            .gw-btn.warn{background:var(--gw-warn);color:#fff}
            .gw-btn.ghost{background:transparent;border-color:var(--gw-border);color:var(--gw-sub)}
            .gw-count{font-size:13px;font-weight:700;color:var(--gw-sub);margin:0 0 14px}
            .gw-count strong{font-size:18px;color:var(--gw-text)}
            @media (prefers-reduced-motion: reduce){*,*::before,*::after{animation:none!important;transition:none!important}.gw-btn:hover{transform:none}}
            @keyframes gw-spin{to{transform:rotate(360deg)}}
        </style></head><body><main class="gw-screen" role="main" aria-live="polite"><section class="gw-brand"><div class="gw-logo" aria-hidden="true">P</div><div><h1 class="gw-brand-title">Powertech Service Gateway</h1><p class="gw-brand-sub">ระบบกำลังเลือกเส้นทางที่พร้อมใช้งานที่สุดให้อัตโนมัติ</p></div></section><section class="gw-main"><div class="gw-status"><span class="gw-dot ${stageTone}" aria-hidden="true"></span>${statusText}</div><div class="gw-stage">${showSpinner ? `<div class="gw-spinner ${stageTone}" aria-hidden="true"></div>` : ''}<h2 class="gw-title">${subtitleText}</h2>${stageHtml}</div></section></main></body></html>`;
    }

    function serviceGatewayConnectingTemplate(withServerProbe) {
        const steps = withServerProbe
            ? '<b>ขั้นตอน:</b> ตรวจ Primary จากผู้ใช้ → ตรวจ Backup จากผู้ใช้ → ตรวจ Server Probe'
            : '<b>ขั้นตอน:</b> ตรวจ Primary → ตรวจ Backup (ถ้ามี)';
        return serviceGatewayShellTemplate({
            statusText: 'กำลังตรวจสอบปลายทาง',
            subtitleText: 'กำลังเชื่อมต่อบริการของคุณ',
            showSpinner: true,
            stageTone: 'primary',
            stageHtml: `<p class="gw-subtitle">กำลังทดสอบเส้นทางจากเครื่องผู้ใช้ก่อน เพื่อเลือกปลายทางที่ตอบสนองเร็วที่สุด</p><p class="gw-intent">${steps}</p>`
        });
    }

    function serviceGatewayBackupTemplate(backupUrlJs) {
        return serviceGatewayShellTemplate({
            statusText: 'กำลังทำ Failover',
            subtitleText: 'สลับไปยังลิงก์สำรอง',
            showSpinner: true,
            stageTone: 'warn',
            stageHtml: `<p class="gw-subtitle">Primary ยังไม่ตอบสนองจากเครือข่ายนี้ ระบบจึงพาคุณไป Backup โดยอัตโนมัติ</p><p class="gw-intent"><b>สถานะ:</b> พบ Backup พร้อมใช้งานแล้ว กำลังเปลี่ยนปลายทางอย่างปลอดภัย</p><p class="gw-count">นำทางในอีก <strong id="countdown">1</strong> วินาที</p><div class="gw-actions"><button type="button" onclick="location.href='${backupUrlJs}'" class="gw-btn primary">ไปที่ Backup ทันที</button><button type="button" onclick="window.close()" class="gw-btn ghost">ปิดหน้าต่าง</button></div>`
        });
    }

    function serviceGatewayUnavailableTemplate(primaryUrlJs, backupUrlJs, withServerProbe) {
        const backupAction = backupUrlJs
            ? `<button type="button" onclick="location.href='${backupUrlJs}'" class="gw-btn warn">ลอง Backup</button>`
            : '';
        const hint = withServerProbe
            ? 'ไม่พบเส้นทางที่ตอบสนอง ทั้งจากการทดสอบฝั่งผู้ใช้และ Server Probe'
            : 'ไม่พบเส้นทางที่ตอบสนองจากเครือข่ายปัจจุบัน';
        return serviceGatewayShellTemplate({
            statusText: 'ไม่พบบริการที่ใช้งานได้',
            subtitleText: 'Service Unavailable',
            stageTone: 'danger',
            stageHtml: `<p class="gw-subtitle">${hint}</p><p class="gw-intent"><b>คำแนะนำ:</b> ตรวจสอบเครือข่ายภายในหรือสถานะเซิร์ฟเวอร์ แล้วลองอีกครั้ง</p><div class="gw-actions"><button type="button" onclick="location.href='${primaryUrlJs}'" class="gw-btn primary">ลอง Primary</button>${backupAction}<button type="button" onclick="window.close()" class="gw-btn ghost">ปิดหน้าต่าง</button></div>`
        });
    }

    /**
     * @param {string} primary
     * @param {string} [backup]
     * @param {{
     *   serverProbe?: () => Promise<{active_link?:string,primary?:{url?:string},backup?:{url?:string}}|null>,
     *   onHealthState?: (state: string, meta: {source?: string}) => void
     * }} [options]
     */
    async function openServiceLink(primary, backup, options) {
        if (!primary) return;

        const hasServerProbe = typeof options?.serverProbe === 'function';
        const newWin = global.open('', '_blank');
        if (!newWin) {
            if (global.Swal) global.Swal.fire('Popup Blocked', 'กรุณาอนุญาต Popups สำหรับเว็บไซต์นี้', 'error');
            return;
        }

        renderServiceGatewayWindow(newWin, serviceGatewayConnectingTemplate(hasServerProbe));

        try {
            const normalizedPrimary = normalizeServiceUrlInput(primary, true);
            const normalizedBackup = backup ? normalizeServiceUrlInput(backup, false) : '';
            const normalizedPrimaryJs = escapeJsString(normalizedPrimary);
            const normalizedBackupJs = escapeJsString(normalizedBackup);

            const primaryProbe = await probeUrlFromClient(normalizedPrimary, { timeoutMs: 1200, retries: 1, label: 'primary' });
            if (primaryProbe.reachable) {
                notifyHealth(options, 'primary', 'client');
                newWin.location.href = normalizedPrimary;
                return;
            }

            if (primaryProbe.uncertain) {
                console.info('[ServiceGateway] Primary probe uncertain; preferring primary URL', { reason: primaryProbe.reason });
                notifyHealth(options, 'primary', 'client-uncertain');
                newWin.location.href = normalizedPrimary;
                return;
            }

            if (!normalizedBackup) {
                notifyHealth(options, 'primary', 'client-fallback');
                newWin.location.href = normalizedPrimary;
                return;
            }

            const backupProbe = await probeUrlFromClient(normalizedBackup, { timeoutMs: 1000, retries: 0, label: 'backup' });
            if (backupProbe.reachable) {
                notifyHealth(options, 'backup', 'client');
                renderServiceGatewayWindow(newWin, serviceGatewayBackupTemplate(normalizedBackupJs));
                const script = newWin.document.createElement('script');
                script.textContent = `const nextUrl='${normalizedBackupJs}';let c=1;const t=setInterval(()=>{c--;const el=document.getElementById('countdown');if(el)el.textContent=c;if(c<=0){clearInterval(t);location.href=nextUrl}},1000);`;
                newWin.document.body.appendChild(script);
                return;
            }

            if (backupProbe.uncertain) {
                console.info('[ServiceGateway] Backup probe uncertain; preferring backup URL', { reason: backupProbe.reason });
                notifyHealth(options, 'backup', 'client-uncertain');
                renderServiceGatewayWindow(newWin, serviceGatewayBackupTemplate(normalizedBackupJs));
                const script = newWin.document.createElement('script');
                script.textContent = `const nextUrl='${normalizedBackupJs}';let c=1;const t=setInterval(()=>{c--;const el=document.getElementById('countdown');if(el)el.textContent=c;if(c<=0){clearInterval(t);location.href=nextUrl}},1000);`;
                newWin.document.body.appendChild(script);
                return;
            }

            if (hasServerProbe) {
                console.info('[ServiceGateway] Client probes failed; using server probe fallback', {
                    primaryReason: primaryProbe.reason,
                    backupReason: backupProbe.reason || 'not-tested'
                });
                const result = await options.serverProbe();
                if (result?.active_link === 'primary' && result.primary?.url) {
                    notifyHealth(options, 'primary', 'server');
                    newWin.location.href = result.primary.url;
                    return;
                }
                if (result?.active_link === 'backup' && result.backup?.url) {
                    notifyHealth(options, 'backup', 'server');
                    newWin.location.href = result.backup.url;
                    return;
                }
            }

            notifyHealth(options, 'down', 'client');
            renderServiceGatewayWindow(
                newWin,
                serviceGatewayUnavailableTemplate(normalizedPrimaryJs, normalizedBackupJs, hasServerProbe)
            );
        } catch (error) {
            console.error('[ServiceGateway] unexpected error', error);
            notifyHealth(options, 'down', 'gateway-error');
            const safePrimaryJs = escapeJsString(primary || '');
            const safeBackupJs = backup ? escapeJsString(backup) : '';
            renderServiceGatewayWindow(
                newWin,
                serviceGatewayUnavailableTemplate(safePrimaryJs, safeBackupJs, hasServerProbe)
            );
        }
    }

    global.ptcServiceGateway = {
        openServiceLink,
        normalizeServiceUrlInput,
        probeUrlFromClient
    };
})(window);
