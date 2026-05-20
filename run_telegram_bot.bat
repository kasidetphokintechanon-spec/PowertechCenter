@echo off
REM Powertech Server Status – Telegram Bot Launcher
setlocal

REM === Edit these values ===
set "TELEGRAM_BOT_TOKEN=8309013674:AAEhf-8kfGhozoesmz86HN2VKAJT7Emazao"
set "TELEGRAM_CHAT_ID=7391488473"
REM Optional: polling interval (ms)
set "TELEGRAM_POLL_INTERVAL_MS=2000"
set "TELEGRAM_ALERT_ENABLED=1"
set "TELEGRAM_ALERT_INTERVAL_MIN=1"
set "TELEGRAM_REPORT_PERIOD=month"
set "TELEGRAM_REPORT_TIME=08:00"

REM Basic checks
where node >nul 2>&1
if errorlevel 1 (
  echo [ERROR] Node.js not found in PATH. Please install Node.js and try again.
  pause
  exit /b 1
)
if "%TELEGRAM_BOT_TOKEN%"=="PUT_YOUR_TOKEN_HERE" (
  echo [ERROR] Please edit run_telegram_bot.bat and set TELEGRAM_BOT_TOKEN.
  pause
  exit /b 1
)
if "%TELEGRAM_CHAT_ID%"=="PUT_YOUR_CHAT_ID_HERE" (
  echo [ERROR] Please edit run_telegram_bot.bat and set TELEGRAM_CHAT_ID.
  pause
  exit /b 1
)

REM Switch to project root and start bot
cd /d "%~dp0"
echo Starting Telegram bot...
node "server_status\telegram_bot.js"

echo.
echo Bot exited. Press any key to close...
pause >nul
