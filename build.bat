@echo off
echo ========================================
echo   WebDaddy - Building Application
echo ========================================
echo.
echo NOTE: This requires internet connection!
echo This only needs to be run once, or when you update the Dockerfile.
echo.

echo Checking Docker...
docker --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Docker is not installed!
    echo Please install Docker Desktop from: https://www.docker.com/products/docker-desktop
    echo.
    pause
    exit /b 1
)

echo Docker found! Checking if Docker daemon is running...
docker info >nul 2>&1
if errorlevel 1 (
    echo ERROR: Docker Desktop is not running!
    echo.
    echo Please start Docker Desktop and wait for it to fully initialize.
    echo Look for the Docker whale icon in your system tray - it should be steady, not animated.
    echo.
    echo After Docker Desktop is running, run this script again.
    echo.
    pause
    exit /b 1
)

echo Docker daemon is running! Building containers...
echo.

docker-compose build

if errorlevel 1 (
    echo.
    echo ERROR: Failed to build containers!
    echo Check the error messages above.
    pause
    exit /b 1
)

echo.
echo ========================================
echo   BUILD SUCCESSFUL!
echo ========================================
echo.
echo You can now run start.bat to start the application (even offline).
echo.
pause
