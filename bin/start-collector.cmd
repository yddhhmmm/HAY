@echo off
setlocal DisableDelayedExpansion
title HAY Collector
call "%~dp0find-php.cmd"
if errorlevel 1 goto failed
echo PHP: "%HAY_PHP%"
echo Collector berjalan. Biarkan jendela ini terbuka. Ctrl+C untuk berhenti.
"%HAY_PHP%" "%~dp0collector.php" --loop
if errorlevel 1 goto failed
pause
exit /b 0
:failed
echo.
echo Collector gagal. Salin pesan error di atas untuk diagnosis.
pause
exit /b 1
