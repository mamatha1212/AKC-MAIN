@echo off
title Push to GitHub and Deploy to Vercel
cd /d "%~dp0"

echo ========================================================
echo   [1/2] Pushing Payload Fix to GitHub (mamatha1212/AKC-MAIN)
echo ========================================================
echo.

git add -A
git commit -m "Fix Vercel payload limit: strip heavy base64 photos from notifications and database on Vercel"
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
echo   COMPLETE! Please refresh https://akc-beta.vercel.app/super_admin/dashboard.php
echo ========================================================
echo.
pause

