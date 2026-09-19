@echo off
title AniHow Mobile App
cd /d "%~dp0.."
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0start-anihow.ps1" -Mode mobile
if errorlevel 1 (
  echo.
  echo AniHow failed to start. Read the messages above.
  pause
)
