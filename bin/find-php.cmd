@echo off
rem Dipanggil launcher; perubahan variabel harus diteruskan ke pemanggil.
if defined HAY_PHP if exist "%HAY_PHP%" exit /b 0
set "HAY_PHP="
for %%I in ("%~dp0..") do set "HAY_SEARCH=%%~fI"
:search
if exist "%HAY_SEARCH%\php\php.exe" goto found
for %%I in ("%HAY_SEARCH%\..") do set "HAY_NEXT=%%~fI"
if /I "%HAY_NEXT%"=="%HAY_SEARCH%" goto fallback
set "HAY_SEARCH=%HAY_NEXT%"
goto search
:found
set "HAY_PHP=%HAY_SEARCH%\php\php.exe"
exit /b 0
:fallback
if exist "C:\xampp\php\php.exe" set "HAY_PHP=C:\xampp\php\php.exe"
if defined HAY_PHP exit /b 0
for /f "delims=" %%I in ('where php.exe 2^>nul') do if not defined HAY_PHP set "HAY_PHP=%%I"
if defined HAY_PHP exit /b 0
echo PHP tidak ditemukan. Di CMD, atur lokasi PHP lalu ulangi:
echo set "HAY_PHP=D:\lokasi-xampp\php\php.exe"
exit /b 1
