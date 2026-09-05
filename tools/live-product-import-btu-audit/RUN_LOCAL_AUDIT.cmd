@echo off
setlocal
cd /d "%~dp0\..\.."
php tools\live-product-import-btu-audit\LIVE_AUDIT.php --local
if errorlevel 1 pause
endlocal
