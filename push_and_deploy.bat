@echo off
title Push to GitHub and Deploy to Vercel
cd /d "%~dp0"

echo ========================================================
echo   [1/2] Pushing Activity Logs & Payload Fix to GitHub
echo ========================================================
echo.

git add -A
git commit -m "Fix activity logs diff display, auto-populate attribute changes, and log updates/deletions"
git push -u akc-main main --force

echo.
echo ========================================================
echo   [2/2] Deploying directly to Vercel Production
echo ========================================================
echo.

where npx >nul 2>&1
if %ERRORLEVEL% equ 0 (
    npx --yes vercel --prod --yes
    goto DONE
)

where vercel >nul 2>&1
if %ERRORLEVEL% equ 0 (
    vercel --prod --yes
    goto DONE
)

:DONE
echo.
echo ========================================================
echo   COMPLETE! Refresh https://akc-beta.vercel.app/super_admin/activity_logs.php
echo ========================================================
echo.
pause

