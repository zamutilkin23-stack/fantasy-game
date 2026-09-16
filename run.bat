@echo off
chcp 65001 >nul
title Local Server
echo.
echo Site:    http://localhost:8000
echo Admin:   http://localhost:8000/admin/
echo Game:    http://localhost:8000/start
echo.
echo Close this window to stop the server
echo.

C:\php\php.exe -S 0.0.0.0:8000 -t C:\fantasy\public_html C:\fantasy\public_html\_router.php

pause