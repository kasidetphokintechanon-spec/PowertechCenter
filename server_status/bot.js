const http = require('http');
const https = require('https');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const ping = require('ping');

const PORT = process.env.PORT ? parseInt(process.env.PORT, 10) : 3001;
const CHANNEL_SECRET = process.env.LINE_CHANNEL_SECRET || '';
const CHANNEL_TOKEN = process.env.LINE_CHANNEL_TOKEN || '';
const BASE_DIR = __dirname;
const CONFIG_PATH = path.join(BASE_DIR, 'data', 'config.json');
const LOG_PATH = path.join(BASE_DIR, 'data', 'logs.json');

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
  const max = 500;
  const trimmed = logs.slice(-max);
  writeJson(LOG_PATH, trimmed);
}

function normalizeGroup(text) {
  const t = String(text || '').trim().toLowerCase();
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

function verifySignature(body, signature) {
  if (!CHANNEL_SECRET) return true;
  const hash = crypto.createHmac('sha256', CHANNEL_SECRET).update(body).digest('base64');
  return hash === signature;
}

function replyMessage(replyToken, message) {
  return new Promise((resolve, reject) => {
    const data = JSON.stringify({
      replyToken,
      messages: [{ type: 'text', text: message }]
    });
    const req = https.request({
      hostname: 'api.line.me',
      path: '/v2/bot/message/reply',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${CHANNEL_TOKEN}`,
        'Content-Length': Buffer.byteLength(data)
      }
    }, (res) => {
      res.on('data', () => {});
      res.on('end', () => resolve());
    });
    req.on('error', reject);
    req.write(data);
    req.end();
  });
}

const server = http.createServer((req, res) => {
  if (req.method === 'GET' && req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('OK');
    return;
  }

  if (req.method !== 'POST' || req.url !== '/webhook') {
    res.writeHead(404);
    res.end();
    return;
  }

  let body = '';
  req.on('data', (chunk) => {
    body += chunk;
  });
  req.on('end', async () => {
    const signature = req.headers['x-line-signature'] || '';
    if (!verifySignature(body, signature)) {
      res.writeHead(401);
      res.end();
      return;
    }
    let payload;
    try {
      payload = JSON.parse(body);
    } catch (e) {
      res.writeHead(400);
      res.end();
      return;
    }

    const events = Array.isArray(payload.events) ? payload.events : [];
    for (const event of events) {
      if (event.type !== 'message') continue;
      if (!event.message || event.message.type !== 'text') continue;
      const cmd = normalizeGroup(event.message.text);
      if (!cmd) continue;
      const statusText = await buildStatus(cmd);
      try {
        await replyMessage(event.replyToken, statusText);
      } catch (e) {}
    }
    res.writeHead(200);
    res.end();
  });
});

server.listen(PORT);
