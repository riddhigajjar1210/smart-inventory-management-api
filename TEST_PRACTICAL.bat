@echo off
title Test Practical Task
cd /d "%~dp0"

echo.
echo ============================================
echo  STEP 1: Starting Redis
echo ============================================
"%TEMP%\redis-portable\redis-cli.exe" ping >nul 2>&1
if errorlevel 1 start "Redis" "%TEMP%\redis-portable\redis-server.exe" --port 6379 --appendonly no
timeout /t 2 >nul

echo.
echo ============================================
echo  STEP 2: Creating tables and sample data
echo ============================================
php artisan migrate:fresh --seed --force
if errorlevel 1 goto :error

echo.
echo ============================================
echo  STEP 3: Warming cache
echo ============================================
php artisan cache:warm

echo.
echo ============================================
echo  STEP 4: Running automated tests
echo  (Look for: Tests: 16 passed)
echo ============================================
php artisan test
if errorlevel 1 goto :error

echo.
echo ============================================
echo  STEP 5: Starting the API and opening browser
echo ============================================
start "Laravel API" cmd /k php artisan serve --host=127.0.0.1 --port=8000
timeout /t 4 >nul
start http://127.0.0.1:8000/api/categories/tree

echo.
echo DONE!
echo Browser opened with the category tree.
echo Other URLs are listed in HOW_TO_TEST.md
echo.
pause
exit /b 0

:error
echo.
echo SOMETHING FAILED. Read the red text above.
pause
exit /b 1
