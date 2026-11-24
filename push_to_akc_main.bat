@echo off
title Push AKC Updates to GitHub (mamatha1212/AKC-MAIN)
cd /d "%~dp0"

echo ========================================================
echo        Pushing Updates to github.com/mamatha1212/AKC-MAIN
echo ========================================================
echo.

echo Current Working Directory: %CD%
echo.

:: Stage all changed and new files
echo [1/4] Staging changes...
git status --short
git add -A

:: Commit changes if any are pending
echo.
echo [2/4] Committing updates...
git commit -m "Fix Vercel payload limit by stripping heavy base64 photos from dashboard payloads"

:: Ensure the remote for AKC-MAIN exists
echo.
echo [3/4] Configuring remote repository...
git remote remove akc-main >nul 2>&1
git remote add akc-main https://github.com/mamatha1212/AKC-MAIN.git
git remote -v

:: Push to main branch with force
echo.
echo [4/4] Pushing to AKC-MAIN (main branch)...
echo Command: git push -u akc-main main --force
git push -u akc-main main --force 2>&1

echo.
echo ========================================================
echo Execution complete. If you see any error above, please share it.
echo ========================================================
echo.
pause

