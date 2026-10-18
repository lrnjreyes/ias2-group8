@echo off
setlocal
title Reset MFA and Lockouts

pushd "%~dp0"
if errorlevel 1 goto folder_error

if not exist "artisan" (
    echo Keep this file inside the project folder next to artisan.
    goto failed
)
if not exist "vendor\autoload.php" (
    echo Run the local server launcher first to install the application packages.
    goto failed
)
if not exist ".env" (
    echo Run the local server launcher first to set up the application.
    goto failed
)

set "RESET_PHP=%~dp0dependencies\php\php.exe"
if exist "%RESET_PHP%" goto reset
set "RESET_PHP=%~dp0..\dependencies\php\php.exe"
if exist "%RESET_PHP%" goto reset
set "RESET_PHP="
for /f "delims=" %%P in ('where php.exe 2^>nul') do if not defined RESET_PHP set "RESET_PHP=%%P"
if defined RESET_PHP goto reset

echo PHP was not found. Run the local server launcher first to install PHP.
goto failed

:reset
echo Resetting authenticator QR setup and clearing all login and MFA lockouts...
"%RESET_PHP%" artisan auth:reset-mfa --no-ansi
if errorlevel 1 goto failed

echo.
echo Done. MFA QR setup and lockouts have been reset for all accounts.
echo Account passwords and employee records have not been changed.
echo Sign in again and scan the new QR code with your authenticator app.
popd
echo.
pause
exit /b 0

:failed
echo.
echo The MFA reset did not finish. Check the message above.
popd
pause
exit /b 1

:folder_error
echo The project folder could not be opened.
pause
exit /b 1
