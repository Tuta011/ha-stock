@echo off
title HA Stock - Servidor

echo ========================================
echo          INICIANDO HA STOCK
echo ========================================
echo.

cd /d "C:\laragon\www\HA-Stock"

start "" "http://127.0.0.1:8000/HA-Stock/public/"

echo Servidor iniciado!
echo.
echo HA Stock:
echo http://127.0.0.1:8000/HA-Stock/public/
echo.
echo Nao feche esta janela enquanto estiver usando o sistema.
echo.

"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" -S 127.0.0.1:8000 -t "C:\laragon\www"

pause