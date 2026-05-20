const https = require('https');
const fs = require('fs');
const path = require('path');
const ping = require('ping');

const TOKEN = process.env.TELEGRAM_BOT_TOKEN || '';
const ALLOWED_CHAT_ID = process.env.TELEGRAM_CHAT_ID || '';
const POLL_INTERVAL_MS = process.env.TELEGRAM_POLL_INTERVAL_MS ? parseInt(process.env.TELEGRAM_POLL_INTERVAL_MS, 10) : 2000;
const ALERT_ENABLED = (process.env.TELEGRAM_ALERT_ENABLED || '1') === '1';
const ALERT_INTERVAL_MIN = process.env.TELEGRAM_ALERT_INTERVAL_MIN ? parseInt(process.env.TELEGRAM_ALERT_INTERVAL_MIN, 10) : 1;
const REPORT_PERIOD = (process.env.TELEGRAM_REPORT_PERIOD || 'month').toLowerCase();
const REPORT_TIME = process.env.TELEGRAM_REPORT_TIME || '08:00';

const BASE_DIR = __dirname;
const CONFIG_PATH = path.join(BASE_DIR, 'data', 'config.json');
const LOG_PATH = path.join(BASE_DIR, 'data', 'logs.json');
const STATE_PATH = path.join(BASE_DIR, 'data', 'alert_state.json');
const REPORT_DIR = path.join(BASE_DIR, 'data', 'reports');

function readJson(file, fallback) {
  try {
    if (!fs.existsSync(file)) return fallback;
    const raw = fs.readFileSync(file, 'utf8');
    const data = JSON.parse(raw);
    return data ?? fallback;
  } catch (e) {
    return fallback;
  }
}

function writeJson(file, data) {
  const dir = path.dirname(file);
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(file, JSON.stringify(data, null, 2), 'utf8');
}

function appendLog(entry) {
  const logs = readJson(LOG_PATH, []);
  logs.push(entry);
  const trimmed = logs.slice(-500);
  writeJson(LOG_PATH, trimmed);
}

function getState() {
  return readJson(STATE_PATH, { lastStatus: {}, lastReportKey: '' });
}

function setState(state) {
  writeJson(STATE_PATH, state);
}

function normalizeGroup(text) {
  const t = String(text || '').trim().toLowerCase();
  if (/^\/?test_connection\b/.test(t)) return 'TEST_CONNECTION';
  if (t === 'pta') return 'PTA';
  if (t === 'pt4') return 'PT4';
  if (t === 'pte') return 'PTE';
  if (t === 'all') return 'ALL';
  return '';
}

async function pingHost(host) {
  try {
    const res = await ping.promise.probe(host, { timeout: 2 });
    return { online: res.alive, ms: res.time ? Number(res.time) : null };
  } catch (e) {
    return { online: false, ms: null };
  }
}

async function buildStatus(group) {
  const config = readJson(CONFIG_PATH, { profiles: {} });
  const hasProfiles = config.profiles && typeof config.profiles === 'object';
  const targets = group === 'ALL' ? ['PTA','PT4','PTE'] : [group];
  const lines = [];
  for (const g of targets) {
    let items = [];
    if (hasProfiles && config.profiles[g] && config.profiles[g].groups) {
      items = Array.isArray(config.profiles[g].groups[g]) ? config.profiles[g].groups[g] : [];
    } else if (config.groups) {
      items = Array.isArray(config.groups[g]) ? config.groups[g] : [];
    }
    const results = [];
    for (const item of items) {
      const name = item.name || item.ip || '-';
      const ip = item.ip || '';
      let type = String(item.type || 'server').toLowerCase();
      if (type === 'printer') type = 'print';
      const r = await pingHost(ip);
      results.push({ name, ip, type, online: r.online, ms: r.ms });
    }
    const text = results.map(r => `${r.online ? '✅' : '❌'} ${r.name}: ${r.online ? 'ONLINE' : 'OFFLINE'}`).join(' ');
    lines.push(`Status ${g}: ${text || '-'}`);
    appendLog({ time: new Date().toISOString(), group: g, results, profile: hasProfiles ? g : null });
  }
  return lines.join('\n');
}

async function buildTestConnection() {
  const config = readJson(CONFIG_PATH, { profiles: {} });
  const profiles = getProfiles(config);
  const cctvLines = [];
  const serverLines = [];
  const now = new Date().toISOString();
  for (const profile of profiles) {
    const items = getProfileItems(config, profile);
    const results = [];
    const cctvRows = [];
    const serverRows = [];
    for (const item of items) {
      const name = item.name || item.ip || '-';
      const ip = item.ip || '';
      let type = String(item.type || 'server').toLowerCase();
      if (type === 'printer') type = 'print';
      const r = await pingHost(ip);
      const icon = r.online ? '🟢' : '🔴';
      const line = `${icon} ${name}${ip ? ` (${ip})` : ''}`;
      if (type === 'cctv') cctvRows.push(line);
      else serverRows.push(line);
      results.push({ name, ip, type, online: r.online, ms: r.ms });
    }
    if (cctvRows.length > 0) cctvLines.push(`• ${profile}: ${cctvRows.join(' | ')}`);
    if (serverRows.length > 0) serverLines.push(`• ${profile}: ${serverRows.join(' | ')}`);
    appendLog({ time: now, group: profile, results, profile, event: 'test_connection' });
  }
  const lines = [];
  lines.push('🧪 Test Connection');
  lines.push('');
  lines.push('🎥 CCTV');
  lines.push(cctvLines.length ? cctvLines.join('\n') : '-');
  lines.push('');
  lines.push('🖥️ Server');
  lines.push(serverLines.length ? serverLines.join('\n') : '-');
  return lines.join('\n');
}

function apiRequest(method, payload) {
  return new Promise((resolve, reject) => {
    const data = JSON.stringify(payload || {});
    const req = https.request({
      hostname: 'api.telegram.org',
      path: `/bot${TOKEN}/${method}`,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(data)
      }
    }, (res) => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => {
        try {
          const json = JSON.parse(body);
          resolve(json);
        } catch (e) {
          resolve({ ok: false });
        }
      });
    });
    req.on('error', reject);
    req.write(data);
    req.end();
  });
}

async function sendMessage(chatId, text) {
  return apiRequest('sendMessage', { chat_id: chatId, text });
}

function sendDocument(chatId, filename, content) {
  return new Promise((resolve, reject) => {
    const boundary = `----tg${Date.now()}`;
    const fileBuffer = Buffer.isBuffer(content) ? content : Buffer.from(content);
    const parts = [];
    parts.push(Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="chat_id"\r\n\r\n${chatId}\r\n`));
    parts.push(Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="document"; filename="${filename}"\r\nContent-Type: text/csv\r\n\r\n`));
    parts.push(fileBuffer);
    parts.push(Buffer.from(`\r\n--${boundary}--\r\n`));
    const body = Buffer.concat(parts);
    const req = https.request({
      hostname: 'api.telegram.org',
      path: `/bot${TOKEN}/sendDocument`,
      method: 'POST',
      headers: {
        'Content-Type': `multipart/form-data; boundary=${boundary}`,
        'Content-Length': body.length
      }
    }, (res) => {
      res.on('data', () => {});
      res.on('end', () => resolve());
    });
    req.on('error', reject);
    req.write(body);
    req.end();
  });
}

let offset = 0;
let running = false;
let alertRunning = false;
let reportRunning = false;

async function pollUpdates() {
  if (running) return;
  running = true;
  try {
    const res = await apiRequest('getUpdates', { offset, timeout: 0 });
    if (res && res.ok && Array.isArray(res.result)) {
      for (const update of res.result) {
        offset = update.update_id + 1;
        const msg = update.message;
        if (!msg || !msg.text) continue;
        if (ALLOWED_CHAT_ID && String(msg.chat.id) !== String(ALLOWED_CHAT_ID)) continue;
        const cmd = normalizeGroup(msg.text);
        if (!cmd) continue;
        if (cmd === 'TEST_CONNECTION') {
          const statusText = await buildTestConnection();
          await sendMessage(msg.chat.id, statusText);
        } else {
          const statusText = await buildStatus(cmd);
          await sendMessage(msg.chat.id, statusText);
        }
      }
    }
  } catch (e) {
  } finally {
    running = false;
  }
}

function getProfileItems(config, profile) {
  if (config.profiles && config.profiles[profile] && config.profiles[profile].groups) {
    return Array.isArray(config.profiles[profile].groups[profile]) ? config.profiles[profile].groups[profile] : [];
  }
  if (config.groups) {
    return Array.isArray(config.groups[profile]) ? config.groups[profile] : [];
  }
  return [];
}

function getProfiles(config) {
  if (config.profiles && typeof config.profiles === 'object') return ['PTA','PT4','PTE'];
  if (config.groups) return ['PTA','PT4','PTE'];
  return [];
}

async function runAutoCheck() {
  if (alertRunning || !ALLOWED_CHAT_ID) return;
  alertRunning = true;
  try {
    const config = readJson(CONFIG_PATH, { profiles: {} });
    const profiles = getProfiles(config);
    const state = getState();
    for (const profile of profiles) {
      const items = getProfileItems(config, profile);
      const results = [];
      for (const item of items) {
        const name = item.name || item.ip || '-';
        const ip = item.ip || '';
        let type = String(item.type || 'server').toLowerCase();
        if (type === 'printer') type = 'print';
        const r = await pingHost(ip);
        const key = `${profile}::${name}::${ip}::${type}`;
        const prev = state.lastStatus[key];
        if (prev === undefined || prev === null) {
          state.lastStatus[key] = r.online;
        } else if (prev !== r.online) {
          state.lastStatus[key] = r.online;
          const icon = r.online ? '✅' : '❌';
          const statusText = r.online ? 'ONLINE' : 'OFFLINE';
          await sendMessage(ALLOWED_CHAT_ID, `${icon} ${profile} ${name} ${statusText} (${type})`);
        }
        results.push({ name, ip, type, online: r.online, ms: r.ms });
      }
      appendLog({ time: new Date().toISOString(), group: profile, results, profile });
    }
    setState(state);
  } catch (e) {
  } finally {
    alertRunning = false;
  }
}

function parseReportTime() {
  const parts = REPORT_TIME.split(':').map(v => parseInt(v, 10));
  const hh = Number.isFinite(parts[0]) ? parts[0] : 8;
  const mm = Number.isFinite(parts[1]) ? parts[1] : 0;
  return { hh: Math.min(Math.max(hh, 0), 23), mm: Math.min(Math.max(mm, 0), 59) };
}

function buildReportCsv(start, end, logs) {
  const rows = logs.filter(item => {
    if (!item.time) return false;
    const d = new Date(item.time);
    return d >= start && d < end;
  });
  const stats = {};
  rows.forEach(log => {
    const profile = log.profile || '-';
    const results = Array.isArray(log.results) ? log.results : [];
    results.forEach(r => {
      const type = (r.type || 'server').toLowerCase();
      const key = `${profile}::${type}`;
      if (!stats[key]) stats[key] = { profile, type, total: 0, offline: 0 };
      stats[key].total += 1;
      if (r.online === false) stats[key].offline += 1;
    });
  });
  const entries = Object.values(stats);
  entries.sort((a, b) => a.profile.localeCompare(b.profile) || a.type.localeCompare(b.type));
  const lines = ['Profile,Type,TotalChecks,OfflineChecks'];
  entries.forEach(e => {
    lines.push(`${e.profile},${e.type},${e.total},${e.offline}`);
  });
  return lines.join('\n');
}

async function runReport() {
  if (reportRunning || !ALLOWED_CHAT_ID) return;
  if (!['hour','day','month'].includes(REPORT_PERIOD)) return;
  reportRunning = true;
  try {
    const state = getState();
    const now = new Date();
    const { hh, mm } = parseReportTime();
    let start;
    let end;
    let key = '';
    if (REPORT_PERIOD === 'hour') {
      if (now.getMinutes() < mm) {
        reportRunning = false;
        return;
      }
      end = new Date(now.getFullYear(), now.getMonth(), now.getDate(), now.getHours(), 0, 0, 0);
      start = new Date(end.getTime() - 60 * 60 * 1000);
      key = `${start.getFullYear()}-${start.getMonth() + 1}-${start.getDate()}-${start.getHours()}`;
    } else if (REPORT_PERIOD === 'day') {
      if (now.getHours() < hh || (now.getHours() === hh && now.getMinutes() < mm)) {
        reportRunning = false;
        return;
      }
      end = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0, 0);
      start = new Date(end.getTime() - 24 * 60 * 60 * 1000);
      key = `${start.getFullYear()}-${start.getMonth() + 1}-${start.getDate()}`;
    } else if (REPORT_PERIOD === 'month') {
      if (now.getDate() !== 1) {
        reportRunning = false;
        return;
      }
      if (now.getHours() < hh || (now.getHours() === hh && now.getMinutes() < mm)) {
        reportRunning = false;
        return;
      }
      end = new Date(now.getFullYear(), now.getMonth(), 1, 0, 0, 0, 0);
      start = new Date(now.getFullYear(), now.getMonth() - 1, 1, 0, 0, 0, 0);
      key = `${start.getFullYear()}-${start.getMonth() + 1}`;
    }
    if (state.lastReportKey === key) {
      reportRunning = false;
      return;
    }
    const logs = readJson(LOG_PATH, []);
    const csv = buildReportCsv(start, end, logs);
    if (!fs.existsSync(REPORT_DIR)) fs.mkdirSync(REPORT_DIR, { recursive: true });
    const filename = `report-${key}.csv`;
    const filePath = path.join(REPORT_DIR, filename);
    fs.writeFileSync(filePath, csv, 'utf8');
    await sendDocument(ALLOWED_CHAT_ID, filename, csv);
    state.lastReportKey = key;
    setState(state);
  } catch (e) {
  } finally {
    reportRunning = false;
  }
}

if (!TOKEN) {
  process.exit(1);
}

setInterval(pollUpdates, POLL_INTERVAL_MS);
if (ALERT_ENABLED) {
  setInterval(runAutoCheck, Math.max(ALERT_INTERVAL_MIN, 1) * 60 * 1000);
}
setInterval(runReport, 60 * 1000);
