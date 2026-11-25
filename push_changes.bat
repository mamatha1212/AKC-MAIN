@echo off
title Push Changes and Deploy to Vercel
cd /d "%~dp0"

echo ========================================================
echo  [1/2] Pushing Admin Service & Tenure Isolation to GitHub
echo ========================================================
echo.

git add -A
git commit -m "Fix admin dashboard booking visibility so new test bookings appear immediately"
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
