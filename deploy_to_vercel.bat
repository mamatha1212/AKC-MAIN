@echo off
title Deploy AKC to Vercel (Project: akc)
cd /d "%~dp0"

echo ========================================================
echo             Deploying AKC to Vercel
echo ========================================================
echo.
echo Target Account: mamatha2
echo Target Project: akc
echo URL Dashboard:  https://vercel.com/mamatha2/akc/deployments
echo.

where npx >nul 2>&1
if %ERRORLEVEL% equ 0 (
    echo [INFO] Running Vercel deployment...
    echo.
    echo If prompted by Vercel:
    echo  - Set up and deploy? [Y]
    echo  - Which scope? Select 'mamatha2'
    echo  - Link to existing project? [Y]
    echo  - What is the name of the existing project? Enter: akc
    echo.
    npx --yes vercel --prod
    goto DONE
)

where vercel >nul 2>&1
if %ERRORLEVEL% equ 0 (
    echo [INFO] Running Vercel CLI...
    vercel --prod
    goto DONE
)

echo [ERROR] Neither Node.js (npx) nor Vercel CLI was found.
echo.
echo Please run: npx vercel --prod from Command Prompt.
echo.

:DONE
echo.
echo ========================================================
echo Deployment process complete.
echo ========================================================
echo.
pause
pause

