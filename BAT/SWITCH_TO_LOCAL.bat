@echo off
setlocal
set SRC=C:\xampp\htdocs\COURSIER_LOCAL
powershell -ExecutionPolicy Bypass -File "%SRC%\scripts\deploy_to_local.ps1" -TargetPath "%SRC%"
endlocal
