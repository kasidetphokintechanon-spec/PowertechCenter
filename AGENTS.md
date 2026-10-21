# PowertechCenter — Agent Team Contract

## Roles

| Role | System | Responsibility |
|------|--------|----------------|
| **User** | Human | Approves phases; business direction; production deploy |
| **Main Orchestrator** | Claude in Cursor | Code, architecture, PRs, Cursor hooks/automations |
| **Co-Orchestrator** | Hermes Agent | Long-term memory, cron, skills, delegated research |
| **Workers** | Cursor Task subagents, Hermes delegates | Focused tasks (explore, shell, CI, reports) |
| **Inspector** | Claude Code agent `.claude/agents/inspector.md` | ลูกน้องตรวจ — แนะนำหัวหน้า ไม่แก้โค้ดเอง · เรียก `[INSPECTOR]` |

## Project layout

```
PowertechCenter/
├── api.php                 # JSON API router
├── config.php              # DB + session (powertech_system)
├── api/actions/            # Domain handlers (stock, ticket, loan, auth, …)
├── *.html                  # Front-end pages
├── deploy.bat              # Production sync (human-approved only)
├── orchestration/          # Claude ↔ Hermes handoff
├── .cursor/rules/          # Cursor agent rules
└── .hermes.md              # Hermes-specific instructions
```

## Rules (all agents)

1. **Confirm gate:** Present Methodology + Expected Outcomes before install, deploy, schema change, or force git operations.
2. **Single owner:** Check `orchestration/.lock` — do not edit conflicting files while another agent holds the lock.
3. **No secrets in git:** API keys only in `~/.hermes/.env` or local env — never in MEMORY.md or this repo.
4. **Commits:** Only when the user explicitly asks.
5. **Deploy:** Only when inbox contains an approved deploy directive from the user.
6. **PHP:** Use `C:\xampp\php\php.exe`; database `powertech_system` on local XAMPP.

## Stock module (active development)

- UI: `stock_management.html`, `stationery.html`, `maid_stock.html`, `my_stock_history.html`
- API: `api/actions/stock/*.php` (26 actions)
- Helpers: `seed_stock.php`, `migrate.php` (run only with user approval)

## Handoff

- Claude → Hermes: `orchestration/inbox.md`
- Hermes → Claude: `orchestration/outbox.md`
- Session summary (Cursor hook): `orchestration/last_session.md` (gitignored)

See [orchestration/HANDOFF_PROTOCOL.md](orchestration/HANDOFF_PROTOCOL.md).

## Inspector (ลูกน้องตรวจ)

- **ชื่อเรียก:** Inspector / น้อง Inspector
- **คำสั่ง:** `[INSPECTOR] ตรวจ log.html` (alias: `[REVIEW]`, `ให้ QC ตรวจ`)
- Agent: `.claude/agents/inspector.md` (อ่านอย่างเดียว · เช็ก theme ด้วย)
- รายงานให้ User — ถ้าต้องแก้ ส่ง `[TO-DEV]` กลับ Orchestrator
