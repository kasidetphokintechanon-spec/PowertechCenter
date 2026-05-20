@echo off
cd /d "c:\xampp2\htdocs\PowertechCenter\"
cd /d "c:\xampp\htdocs\PowertechCenter\"
title Powertech Chat Server

echo Starting WebSocket Server...
echo (Keep this window open)

:loop
node websocket-server.js
echo Server stopped/crashed. Restarting in 5 seconds...
timeout /t 5
goto loop
