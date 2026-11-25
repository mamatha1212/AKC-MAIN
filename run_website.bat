@echo off
title AKC Packaging Technology & Research Centre - Local Server
cd /d "%~dp0"

echo ========================================================
echo        AKC PACKAGING TECHNOLOGY & RESEARCH CENTRE
echo                   Local Web Server
echo ========================================================
echo.

where php >nul 2>&1
if %ERRORLEVEL% equ 0 (
    echo [INFO] Starting PHP Built-in Server on http://localhost:8000 ...
    echo [INFO] Press Ctrl+C in this window anytime to stop the server.
    echo.
    start "" "http://localhost:8000"
    php -S localhost:8000 router.php
    goto END
)

where C:\xampp\php\php.exe >nul 2>&1
if %ERRORLEVEL% equ 0 (
    echo [INFO] Starting PHP Server from XAMPP on http://localhost:8000 ...
    start "" "http://localhost:8000"
    C:\xampp\php\php.exe -S localhost:8000 router.php
    goto END
)

echo [WARNING] PHP was not found in system PATH or C:\xampp\php\.
echo.
echo If you use XAMPP, open the XAMPP Control Panel and start Apache.
echo Then open: http://localhost/AKC-Hemachandra
echo.
pause

:END
