@echo off
title Push AKC Updates to GitHub (mamatha1212/AKC-MAIN)
cd /d "%~dp0"

echo ========================================================
echo        Pushing Updates to github.com/mamatha1212/AKC-MAIN
echo ========================================================
echo.

:: Stage all changed and new files
echo [1/4] Staging changes...
git add -A

:: Commit changes
echo [2/4] Committing updates...
git commit -m "Update login flow, modern catalog, notifications sync, and auto-report cron"

:: Ensure the remote for AKC-MAIN exists
echo [3/4] Checking remote repository...
git remote get-url akc-main >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo Adding remote 'akc-main' pointing to https://github.com/mamatha1212/AKC-MAIN.git ...
    git remote add akc-main https://github.com/mamatha1212/AKC-MAIN.git
)

:: Push to main branch of AKC-MAIN
echo [4/4] Pushing to AKC-MAIN (main branch)...
git push -u akc-main main

if %ERRORLEVEL% equ 0 (
    echo.
    echo ========================================================
    echo    SUCCESS! Changes pushed to mamatha1212/AKC-MAIN
    echo ========================================================
) else (
    echo.
    echo [NOTE] If git requested credentials or rejected the push, please ensure you are logged into GitHub in your browser/Git Credential Manager.
)

echo.
pause

