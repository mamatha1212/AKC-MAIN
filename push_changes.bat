@echo off
title Push Changes and Deploy to Vercel
cd /d "%~dp0"

echo ========================================================
echo  [1/2] Pushing Server-Side Notification Render to GitHub
echo ========================================================
echo.

git add -A
git commit -m "Render client notifications directly in PHP server-side so history shows reliably without client JS dependence"
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
echo ========================================================
echo.
pause
