@echo off
chcp 65001 >nul
echo ====================================
echo  Запуск сервера фанты.online
echo ====================================
echo.

:: Проверяем PHP
where php >nul 2>&1
if %errorlevel% neq 0 (
    echo [✗] PHP не найден.
    echo.
    echo Скачай PHP 8.3 (Non Thread Safe, x64) по ссылке:
    echo https://windows.php.net/downloads/releases/php-8.3.13-nts-Win32-vs16-x64.zip
    echo.
    echo Распакуй ZIP в папку C:\php
    echo.
    pause
    exit /b
)

echo [✓] PHP найден
echo.
echo Запускаю сервер на http://localhost:8000
echo Для остановки нажми Ctrl+C
echo.
echo Переход на сайт: http://localhost:8000
echo Админка: http://localhost:8000/admin/
echo Установка: http://localhost:8000/install.php
echo.

php -S localhost:8000 -t public_html
pause