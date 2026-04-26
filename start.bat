@echo off
title QuizLAN Server
color 0A

echo.
echo  ========================================
echo     QuizLAN (By Ruhaan Pathan)
echo  ========================================
echo.

:: Detect LAN IP (skip localhost, loopback, and 169.x addresses)
set "LAN_IP="
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /i "IPv4"') do (
    set "IP=%%a"
    call :trimIP
)

if "%LAN_IP%"=="" (
    echo  [!] Could not detect LAN IP. Using localhost.
    set "LAN_IP=localhost"
)

echo  ----------------------------------------
echo.
echo   Teacher (this PC):
echo     http://localhost:8000
echo.
echo   Students (phone/other devices):
echo     http://%LAN_IP%:8000
echo.
echo  ----------------------------------------
echo.
echo   Press Ctrl+C to stop the server.
echo.
echo  ========================================
echo.

:: Start PHP server on all interfaces
php -S 0.0.0.0:8000 -t "%~dp0"

pause
exit /b

:trimIP
set "IP=%IP: =%"
:: Skip localhost and link-local addresses
echo %IP% | findstr /b "127." >nul && exit /b
echo %IP% | findstr /b "169." >nul && exit /b
set "LAN_IP=%IP%"
exit /b
