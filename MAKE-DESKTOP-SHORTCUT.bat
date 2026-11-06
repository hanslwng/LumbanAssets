@echo off
REM One click: LGU Inventory shortcut appears on Desktop (needs Chrome or Edge).
REM Shortcut name + login URL:
REM   installer\lgue-shortcut-settings.ini

cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "%~dp0installer\Install-LGU-Shortcut.ps1" -Quiet
exit /b %ERRORLEVEL%
