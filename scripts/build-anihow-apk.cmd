@echo off
title AniHow - Build Phone App
cd /d "%~dp0.."
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0build-anihow-apk.ps1" %*
if errorlevel 1 (
  echo.
  echo AniHow APK build failed. Read the messages above.
  pause
)
