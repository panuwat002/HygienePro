@echo off
REM Launcher for setup-server.ps1 - cmd.exe cannot run a .ps1 directly, and the
REM machine's execution policy would otherwise block the script.
REM
REM   setup-server.bat            install
REM   setup-server.bat -WhatIf    dry run, changes nothing
REM
REM Must be run from an ELEVATED prompt (Run as administrator).

setlocal
cd /d "%~dp0"

net session >nul 2>&1
if errorlevel 1 (
    echo.
    echo   ERROR: not running as administrator.
    echo   Right-click cmd.exe or PowerShell and choose "Run as administrator",
    echo   then run this again.
    echo.
    exit /b 1
)

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-server.ps1" %*
endlocal
