@echo off
REM Adds a Task Scheduler job that runs the Laravel scheduler every minute (run as Administrator)

set PHP=C:\xampp\php\php.exe
set APP=C:\xampp\htdocs\employee-tracker

schtasks /Create /F /SC MINUTE /MO 1 /RU SYSTEM /TN "Tracker Laravel Scheduler" /TR "\"%PHP%\" \"%APP%\artisan\" schedule:run"

if %ERRORLEVEL%==0 (
  echo Task added: Tracker Laravel Scheduler
) else (
  echo Failed - run this file as Administrator
)
pause
