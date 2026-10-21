@echo off
setlocal enabledelayedexpansion
title Sync TO QNAP - PowertechCenter
color 0B
cd /d "%~dp0"

REM ============================================================
REM  ดัน code ขึ้น Production share (QNAP)
REM  ทิศทาง: โฟลเดอร์นี้  -->  \\192.168.0.2\Web\PowertechCenter
REM
REM  การใช้งาน:
REM    sync_to_server.bat           -- sync จริง (ถามยืนยันก่อน)
REM    sync_to_server.bat dry       -- แสดง list ไฟล์ที่จะ copy โดยไม่ copy จริง
REM    sync_to_server.bat force     -- sync โดยไม่ถาม (ใช้ใน script)
REM
REM  ห้ามลบไฟล์ที่ server โดยอัตโนมัติ (/MIR ไม่ได้ใช้)
REM  config.php ถูก exclude เสมอ — production มี DB credentials ต่างกัน
REM ============================================================

set "SRC=%~dp0"
if "%SRC:~-1%"=="\" set "SRC=%SRC:~0,-1%"

set "DST=\\192.168.0.2\Web\PowertechCenter"
set "LOG=%SRC%\deploy_log.txt"
set "MODE=sync"

if /I "%~1"=="dry"   set "MODE=dry"
if /I "%~1"=="force" set "MODE=force"

echo.
echo  ============================================================
echo   Sync TO server - PowertechCenter
echo   Source: %SRC%
echo   Target: %DST%
if /I "%MODE%"=="dry" (
echo   Mode:   DRY RUN  ^(แสดงรายการเท่านั้น ไม่ copy จริง^)
) else (
echo   Mode:   LIVE SYNC
)
echo   %DATE%  %TIME%
echo  ============================================================
echo.

REM --- ตรวจ VPN / LAN ---
echo [CHECK] ตรวจสอบการเชื่อมต่อ server (%DST%)...
ping -n 1 -w 1500 192.168.0.2 >nul 2>&1
if errorlevel 1 (
    color 0C
    echo.
    echo [ERROR] ไม่สามารถ ping 192.168.0.2 ได้
    echo         กรุณาเชื่อมต่อ VPN ^(FortiClient^) ก่อน แล้วลองใหม่
    echo.
    pause
    exit /b 1
)
echo [OK]    Server reachable
echo.

REM --- ถามยืนยัน (ยกเว้น dry/force) ---
if /I "%MODE%"=="sync" (
    echo  [!] กำลังจะ sync ไฟล์ขึ้น Production server
    echo  [!] config.php จะถูก SKIP เสมอ
    echo.
    set /p "CONFIRM=  ยืนยันการ sync? [Y/N]: "
    if /I not "!CONFIRM!"=="Y" (
        echo.
        echo [CANCEL] ยกเลิกการ sync
        pause
        exit /b 0
    )
    echo.
)

REM --- โฟลเดอร์ที่ไม่ sync ขึ้น server ---
set "XD_EX=node_modules .git .qodo .vscode .cursor .claude"
set "XD_EX=%XD_EX% VDO Stationery_Maid"
set "XD_EX=%XD_EX% directory_New\node_modules"

REM --- ไฟล์ที่ไม่ sync ขึ้น server ---
set "XF_EX=config.php"
set "XF_EX=%XF_EX% deploy_log.txt sync_from_server_log.txt"
set "XF_EX=%XF_EX% backup_powertech*.zip backup_powertech*.zip.*"
set "XF_EX=%XF_EX% .env.local* .hermes.env* line_log.txt"
set "XF_EX=%XF_EX% *.jsonl missing.txt"

echo [INFO] Excluded dirs : %XD_EX%
echo [INFO] Excluded files: %XF_EX%
echo.

REM --- รัน robocopy ---
if /I "%MODE%"=="dry" (
    echo [DRY RUN] รายการไฟล์ที่จะถูก sync:
    echo.
    robocopy "%SRC%" "%DST%" /E /XO /L /NP ^
        /XD %XD_EX% ^
        /XF %XF_EX%
    set "RC=%ERRORLEVEL%"
    echo.
    echo [DRY RUN] เสร็จสิ้น ^(ยังไม่มีการ copy จริง^)
    echo           รัน sync_to_server.bat เพื่อ sync จริง
    echo.
    pause
    exit /b 0
)

echo [RUN] robocopy กำลัง sync... ^(log: deploy_log.txt^)
echo.

robocopy "%SRC%" "%DST%" /E /XO /Z /R:2 /W:3 /NP /TEE ^
    /LOG+:"%LOG%" ^
    /XD %XD_EX% ^
    /XF %XF_EX%

set "RC=%ERRORLEVEL%"
echo.

REM --- แปล exit code ---
REM   0 = ไม่มีอะไรเปลี่ยน (up-to-date)
REM   1 = copy สำเร็จ
REM   2 = มี extra files ที่ dest (ไม่ copy กลับ)
REM   3 = copy สำเร็จ + extra files (ปกติ ไม่ใช่ error)
REM   8+ = มี error จริง

if %RC% GEQ 8 (
    color 0C
    echo [ERROR] Sync ล้มเหลว ^(robocopy code %RC%^)
    echo         ตรวจดู deploy_log.txt สำหรับรายละเอียด
    echo.
    pause
    exit /b %RC%
)

color 0A
if %RC%==0 (
    echo [OK] ทุกไฟล์ up-to-date แล้ว ไม่มีอะไรต้อง sync
) else (
    echo [OK] Sync สำเร็จ ^(robocopy code %RC%^)
)
echo.
echo Log: %LOG%
echo.
echo Tip: เปิด https://192.168.0.2:8081/PowertechCenter/ เพื่อทดสอบ
echo      ^(ถ้า Chrome แจ้ง Not secure = ปกติ self-signed cert^)
echo.
pause
exit /b 0
