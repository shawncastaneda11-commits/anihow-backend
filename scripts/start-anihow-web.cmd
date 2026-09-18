@echo off
title AniHow Admin Website
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0start-anihow.ps1" -Mode web
