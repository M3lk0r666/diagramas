@echo off
title Queue Worker - Diagramas
cd /d "%~dp0"

echo ============================================
echo   Queue Worker - Sistema de Diagramas
echo ============================================
echo   Procesando jobs en segundo plano...
echo   Presiona Ctrl+C para detener
echo   Workers: 4  Timeout: 120s  Retries: 3
echo ============================================
echo.

REM Usar 4 workers paralelos con --queue=default
REM --sleep=2  espera 2s cuando no hay jobs (ahorra CPU)
REM --tries=3  reintenta 3 veces antes de mandar a failed_jobs
REM --timeout=120 mata jobs colgados despues de 2 minutos

php artisan queue:work ^
    --queue=default ^
    --sleep=2 ^
    --tries=3 ^
    --timeout=120 ^
    --max-jobs=500 ^
    --verbose

echo.
echo Worker detenido.
pause
