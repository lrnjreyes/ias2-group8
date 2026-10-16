@echo off
setlocal EnableExtensions DisableDelayedExpansion
cd /d "%~dp0"

set "LOCAL_PORT=8000"
if not "%~1"=="" set "LOCAL_PORT=%~1"
echo IAS2 local launcher
echo Project folder: "%CD%"
"%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\run-local.ps1" -Port "%LOCAL_PORT%"
set "LAUNCH_EXIT=%ERRORLEVEL%"
echo.
if not "%LAUNCH_EXIT%"=="0" echo The server stopped with an error. Check the message above.
if "%LAUNCH_EXIT%"=="0" echo The server has stopped.
pause
exit /b %LAUNCH_EXIT%
