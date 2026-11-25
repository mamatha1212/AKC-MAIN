@echo off
title Push Changes and Deploy to Vercel
cd /d "%~dp0"

echo ========================================================
echo  [1/2] Pushing Client Notification Isolation to GitHub
echo ========================================================
echo.

git add -A
git commit -m "Isolate client notifications strictly to logged-in user email and their own bookings"
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
