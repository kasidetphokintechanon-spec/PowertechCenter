# Hermes Agent Setup — PowertechCenter



Installed: **2026-05-25** | Hermes Agent **v0.14.0** | Python **3.12.10**



## Quick commands



Add Python to PATH (current session):



```powershell

$env:Path = "$env:LOCALAPPDATA\Programs\Python\Python312\Scripts;$env:LOCALAPPDATA\Programs\Python\Python312;$env:Path"

```



| Command | Purpose |

|---------|---------|

| `hermes doctor` | Health check |

| `hermes setup` | Create `~/.hermes/.env` (API keys) |

| `hermes auth` | Configure provider login |

| `hermes --tui` | Interactive terminal UI |

| `hermes cron list` | List scheduled jobs |



## Paths



| Item | Location |

|------|----------|

| Hermes home | `C:\Users\Kasidet.p\.hermes\` |

| Config | `~/.hermes/config.yaml` |

| Long-term memory | `~/.hermes/memories/MEMORY.md`, `USER.md` |

| Cron jobs | `~/.hermes/cron/jobs.json` |

| Project workspace | `C:\xampp\htdocs\PowertechCenter` |



## Gateway (auto-start on login)



```powershell

hermes gateway status

hermes gateway install   # re-run if needed

```



Installed locations:

- Task script: `C:\Users\Kasidet.p\.hermes\gateway-service\Hermes_Gateway.cmd`

- Startup shortcut: `%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\Hermes_Gateway.cmd`



## Gemini free tier (recommended)



**English:** Use a **Google AI Studio** API key (free tier with rate limits). This is **not** Cursor Pro and **not** necessarily a paid Gemini Advanced / Google One AI subscription. Hermes v0.14 provider name: `gemini`. Recommended cron model: `gemini-3-flash-preview` (Hermes default aux model for Gemini).



**ไทย:** ใช้ API key ฟรีจาก **Google AI Studio** ([สร้าง key](https://aistudio.google.com/app/apikey)) — มีโควต้าและ rate limit ไม่ใช่ Cursor Pro และไม่จำเป็นต้องสมัคร Gemini Advanced แยก แนะนำโมเดล cron: `gemini-3-flash-preview`



OAuth ทาง `google-gemini-cli` มีได้แต่ไม่จำเป็นถ้าใช้ API key



### Setup steps



1. Open https://aistudio.google.com/app/apikey and create an API key (paste only locally).

2. Edit `C:\Users\Kasidet.p\.hermes\.env` — uncomment `GEMINI_API_KEY=` or `GOOGLE_API_KEY=` and paste your key.  

   Or run: `powershell -File orchestration\setup-hermes-api.ps1` and choose **4**.

3. Set default provider (non-interactive):



```powershell

hermes config set model.provider gemini

hermes config set model.default gemini-3-flash-preview

hermes config set timezone Asia/Bangkok

```



4. Enable weekly LLM job: `hermes cron resume 461a7036c740`

5. Verify: `hermes cron list` and `hermes doctor`



### Free tier caveats



| Topic | English | ไทย |

|-------|---------|-----|

| Billing | AI Studio free tier; paid quotas if you enable billing | โควต้าฟรีจาก AI Studio ถ้าเปิด billing อาจคิดเงินเพิ่ม |

| Limits | RPM/RPD limits; cron may fail with 429 if too frequent | มีจำกัดคำขอต่อนาที/วัน cron อาจ error 429 |

| Model | Use `gemini-3-flash-preview` for scheduled jobs | cron ใช้ flash preview ประหยัดโควต้า |

| Keys | `GEMINI_API_KEY` or `GOOGLE_API_KEY` (same Google AI Studio key) | ใส่ key ได้สองชื่อตัวแปร ค่าเดียวกัน |

| No key | Script-only cron (daily git) runs without any API key | งาน script รายวันไม่ต้องมี key |



## Other providers (optional)



Interactive setup supports OpenAI (1), OpenRouter (2), Anthropic (3). Same `.env` file; only one primary LLM key is required for agent cron jobs.



## Bootstrap cron



```powershell

$env:Path = "$env:LOCALAPPDATA\Programs\Python\Python312\Scripts;$env:LOCALAPPDATA\Programs\Python\Python312;$env:Path"

cd C:\xampp\htdocs\PowertechCenter

python orchestration\bootstrap_hermes_cron.py

hermes cron list

```



Safe to re-run: skips jobs that already exist by name; patches weekly job `provider`/`model` if missing.



## Co-Orchestrator integration



See [orchestration/HANDOFF_PROTOCOL.md](orchestration/HANDOFF_PROTOCOL.md) and [AGENTS.md](AGENTS.md).



## Windows notes



- Native Windows support is early beta; use WSL2 if `hermes doctor` reports critical tool failures

- Install **ripgrep** (`winget install BurntSushi.ripgrep.MSVC`) for faster file search

- PHP/MySQL paths are documented in `MEMORY.md`

