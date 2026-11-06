@echo off
REM Opens LGU Inventory login in Google Chrome if installed; otherwise your default browser.
set "URL=http://localhost/lgu_invent/login"

set "CHROME="
if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" (
    set "CHROME=%ProgramFiles%\Google\Chrome\Application\chrome.exe"
)
if not defined CHROME if exist "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" (
    set "CHROME=%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
)
if not defined CHROME if exist "%LocalAppData%\Google\Chrome\Application\chrome.exe" (
    set "CHROME=%LocalAppData%\Google\Chrome\Application\chrome.exe"
)

if defined CHROME (
    start "" "%CHROME%" "%URL%"
) else (
    start "" "%URL%"
)
exit /b 0
