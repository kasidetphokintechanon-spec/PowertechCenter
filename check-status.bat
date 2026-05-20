@echo off
cd /d "c:\xampp\htdocs\PowertechCenter\"
title Device Status Monitor
:loop
node check-device.js
timeout /t 10
goto loop