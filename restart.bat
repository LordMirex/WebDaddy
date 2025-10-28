@echo off
echo ========================================
echo   WebDaddy - Restarting Application
echo ========================================
echo.

echo Restarting containers...
docker-compose restart

if errorlevel 1 (
    echo.
    echo ERROR: Failed to restart containers!
    pause
    exit /b 1
)

echo.
echo ========================================
echo   Application Restarted Successfully!
echo ========================================
echo.
echo Your site is available at:
echo   http://localhost:8080
echo.
pause
