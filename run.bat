@echo off
chcp 65001 >nul
title Тайная комната
echo ============================================
echo   Тайная комната искушений
echo ============================================
echo.
echo   Сайт:    http://localhost:8000
echo   Админка: http://localhost:8000/admin/
echo   Игра:    http://localhost:8000/start
echo   Дашборд: http://localhost:8000/admin/dashboard.php
echo   Импорт:  http://localhost:8000/admin/import.php
echo   Карточки: http://localhost:8000/admin/cards.php
echo.
echo   Нажми Ctrl+C чтобы остановить сервер
echo ============================================
echo.

start http://localhost:8000
C:\php\php.exe -S 0.0.0.0:8000 -t C:\fantasy\public_html C:\fantasy\public_html\_router.php

pause