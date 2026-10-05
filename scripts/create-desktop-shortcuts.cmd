@echo off
title AniHow shortcuts
cd /d "%~dp0.."
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0create-desktop-shortcuts.ps1" %*
if errorlevel 1 goto :failed
goto :eof

:failed
echo.
echo Could not create the AniHow shortcuts. Read the messages above.
pause
