@echo off
title Push Changes and Deploy to Vercel
cd /d "%~dp0"

echo ========================================================
echo  [1/2] Pushing Clean Compact Summary & Modal to GitHub
echo ========================================================
echo.

git add -A
git commit -m "Replace tall raw log list with compact summary pill and View Details modal popup"
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
