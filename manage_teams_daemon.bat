@echo off
REM Teams Connection Daemon Manager for Windows
REM Usage: manage_teams_daemon.bat [start|stop|restart|status]

set SCRIPT_DIR=%~dp0
set PHP_PATH=php
set DAEMON_SCRIPT=%SCRIPT_DIR%teams_connection_daemon.php

if "%1"=="start" goto start
if "%1"=="stop" goto stop
if "%1"=="restart" goto restart
if "%1"=="status" goto status
if "%1"=="install" goto install
if "%1"=="uninstall" goto uninstall

echo Usage: %0 [start^|stop^|restart^|status^|install^|uninstall]
echo.
echo Commands:
echo   start     - Start the Teams daemon
echo   stop      - Stop the Teams daemon
echo   restart   - Restart the Teams daemon
echo   status    - Check daemon status
echo   install   - Install as Windows service (requires NSSM)
echo   uninstall - Remove Windows service
exit /b 1

:start
echo Starting Teams Connection Daemon...
start "Teams Daemon" /MIN %PHP_PATH% "%DAEMON_SCRIPT%" start
goto end

:stop
echo Stopping Teams Connection Daemon...
%PHP_PATH% "%DAEMON_SCRIPT%" stop
goto end

:restart
echo Restarting Teams Connection Daemon...
%PHP_PATH% "%DAEMON_SCRIPT%" restart
goto end

:status
echo Checking Teams Connection Daemon status...
%PHP_PATH% "%DAEMON_SCRIPT%" status
goto end

:install
echo Installing Teams Daemon as Windows Service...
echo This requires NSSM (Non-Sucking Service Manager)
echo Download from: https://nssm.cc/download
echo.
nssm install TeamsConnectionDaemon "%PHP_PATH%" "\"%DAEMON_SCRIPT%\" start"
if %errorlevel% == 0 (
    echo Service installed successfully!
    echo Use 'net start TeamsConnectionDaemon' to start the service
) else (
    echo Failed to install service. Make sure NSSM is installed and in PATH.
)
goto end

:uninstall
echo Uninstalling Teams Daemon Windows Service...
net stop TeamsConnectionDaemon 2>nul
nssm remove TeamsConnectionDaemon confirm
echo Service uninstalled
goto end

:end
pause