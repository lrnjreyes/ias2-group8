@echo off
setlocal
title Reset MFA Lockouts

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
echo Clearing the application cache to reset all login and MFA lockouts...
"%RESET_PHP%" artisan cache:clear --no-ansi
if errorlevel 1 goto failed

echo.
echo Done. Login and MFA lockouts have been reset, including the 15-minute wait.
echo Authenticator setup, accounts, and database records have not been changed.
echo Refresh the login or MFA page before trying again.
popd
echo.
pause
exit /b 0

:failed
echo.
echo The lockouts could not be reset. Check the message above.
popd
pause
exit /b 1

:folder_error
echo The project folder could not be opened.
pause
exit /b 1
