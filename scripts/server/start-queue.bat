@echo off
REM HygienePro queue worker.
REM Restarts itself if the worker exits, so a crash or a --max-time recycle
REM does not silently leave queued mail/notifications sitting in the jobs table.
REM
REM Runs headless under the SYSTEM account, so everything is teed to
REM storage\logs\queue-worker.log - otherwise a worker crash-looping every 5
REM seconds would be invisible. Check that file first when mail stops arriving.
cd /d "%~dp0..\.."

set LOG=storage\logs\queue-worker.log

echo. >> "%LOG%"
echo ==================================================== >> "%LOG%"
echo [%date% %time%] worker task started >> "%LOG%"

:loop
echo [%date% %time%] starting queue:work >> "%LOG%"
"C:\PHP\php.exe" artisan queue:work --sleep=3 --tries=3 --max-time=3600 >> "%LOG%" 2>&1
echo [%date% %time%] queue:work exited with code %ERRORLEVEL%, restarting in 5s >> "%LOG%"
timeout /t 5 /nobreak >nul
goto loop
