@echo off
echo ========================================
echo   WebDaddy - Starting Application
echo ========================================
echo.

echo Checking Docker...
docker --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Docker is not installed or not running!
    echo Please install Docker Desktop from: https://www.docker.com/products/docker-desktop
    echo.
    pause
    exit /b 1
)

echo Docker found! Starting containers...
echo.

docker-compose up -d --build

if errorlevel 1 (
    echo.
    echo ERROR: Failed to start containers!
    echo Check the error messages above.
    pause
    exit /b 1
)

echo.
echo ========================================
echo   SUCCESS! Application is running!
echo ========================================
echo.
echo Your site is available at:
echo   http://localhost:8080
echo.
echo To stop the application, run: stop.bat
echo Or use: docker-compose down
echo.
pause
