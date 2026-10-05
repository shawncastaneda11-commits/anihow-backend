@echo off
title AniHow Admin Website
cd /d "%~dp0.."
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0start-anihow.ps1" -Mode web
if errorlevel 1 goto :failed
goto :eof

:failed
echo.
echo AniHow failed to start. Read the messages above.
pause
