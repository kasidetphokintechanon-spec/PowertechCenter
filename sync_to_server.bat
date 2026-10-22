@echo off
REM UTF-8 — ข้อความภาษาไทยแสดงถูก
chcp 65001 >nul
setlocal
title Sync TO QNAP - PowertechCenter
cd /d "%~dp0"

REM ============================================================
REM  ดัน code ขึ้น Production share (QNAP) — ตัวทำงานจริงอยู่ใน sync_to_server.ps1
REM
REM    sync_to_server.bat           -- ดูรายการก่อน แล้วถามยืนยันค่อย sync จริง
REM    sync_to_server.bat dry       -- แสดงรายการไฟล์ที่จะ copy อย่างเดียว
REM
REM  ส่งเฉพาะไฟล์ที่ commit ขึ้น git แล้ว · ไม่ลบไฟล์บน server · ข้าม config.php เสมอ
REM ============================================================

set "PS=powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0sync_to_server.ps1""

%PS% -Mode dry
if errorlevel 1 (
    echo.
    pause
    exit /b 1
)
if /I "%~1"=="dry" (
    echo.
    pause
    exit /b 0
)

echo.
set /p "CONFIRM=  ยืนยัน sync ขึ้นเครื่องจริง? [Y/N]: "
if /I not "%CONFIRM%"=="Y" (
    echo [CANCEL] ยกเลิกการ sync
    pause
    exit /b 0
)
echo.
%PS% -Mode sync
echo.
pause
