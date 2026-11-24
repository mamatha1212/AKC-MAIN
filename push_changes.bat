@echo off
title Push Changes to GitHub
cd /d "%~dp0"

echo ========================================================
echo  Pushing latest Activity Logs changes to GitHub (AKC-MAIN)
echo ========================================================
echo.

git add -A
git commit -m "Fix activity logs diff: fallback gracefully on action & show clear attribute changes"
git push -u akc-main main --force

echo.
echo ========================================================
echo  GitHub push complete! Now deploying to Vercel...
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

