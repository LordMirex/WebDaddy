@echo off
echo ========================================
echo WebDaddy Cleanup Script
echo ========================================
echo.
echo This will delete unused files from your project.
echo.

echo Checking files...
echo.

if exist "apply_affiliate.php" (
    echo [FOUND] apply_affiliate.php - Not used anymore
    set /p delete1="Delete apply_affiliate.php? (y/n): "
    if /i "%delete1%"=="y" (
        del /f "apply_affiliate.php"
        echo [DELETED] apply_affiliate.php
    )
    echo.
)

if exist ".replit" (
    echo [FOUND] .replit - Replit config file
    set /p delete2="Delete .replit? (y/n): "
    if /i "%delete2%"=="y" (
        del /f ".replit"
        echo [DELETED] .replit
    )
    echo.
)

if exist "replit.md" (
    echo [FOUND] replit.md - Replit documentation
    set /p delete3="Delete replit.md? (y/n): "
    if /i "%delete3%"=="y" (
        del /f "replit.md"
        echo [DELETED] replit.md
    )
    echo.
)

echo ========================================
echo Cleanup Complete!
echo ========================================
echo.
pause
