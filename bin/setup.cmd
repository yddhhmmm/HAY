@echo off
setlocal DisableDelayedExpansion
title Setup akun HAY
call "%~dp0find-php.cmd"
if errorlevel 1 goto failed
echo PHP: "%HAY_PHP%"
echo Lokasi data mengikuti src\settings.php atau deteksi XAMPP otomatis.
if "%~1"=="" goto initial
if /I "%~1"=="reset-password" goto reset
if /I "%~1"=="set-viewer" goto viewer
echo Pilihan: setup.cmd, setup.cmd reset-password, setup.cmd set-viewer
goto failed
:initial
"%HAY_PHP%" "%~dp0setup-console.php" init
goto result
:reset
"%HAY_PHP%" "%~dp0setup-console.php" reset-password
goto result
:viewer
"%HAY_PHP%" "%~dp0setup-console.php" set-viewer
:result
if errorlevel 1 goto failed
echo.
echo Setup selesai.
pause
exit /b 0
:failed
echo.
echo Setup gagal. Pesan di atas dapat disalin untuk diagnosis. Jangan bagikan URL setup atau password.
pause
exit /b 1
