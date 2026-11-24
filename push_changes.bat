@echo off
title Push Changes and Deploy to Vercel
cd /d "%~dp0"

echo ========================================================
echo  [1/2] Pushing Clean Diff Parser to GitHub (AKC-MAIN)
echo ========================================================
echo.

git add -A
git commit -m "Add dual PHP & JS un-escaper for Activity Logs to cleanly format before/after attributes"
git push -u akc-main main --force

echo.
echo ========================================================
echo  [2/2] Deploying directly to Vercel Production
echo ========================================================
echo.

call npx --yes vercel --prod --yes

echo.
echo ========================================================
echo  DEPLOYMENT COMPLETE!
echo  Please refresh https://akc-beta.vercel.app/super_admin/activity_logs.php
echo ========================================================
echo.
pause
