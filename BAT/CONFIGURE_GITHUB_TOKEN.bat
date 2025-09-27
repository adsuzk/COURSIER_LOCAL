@echo off
title SUZOSKY - Configuration GitHub Token
color 0B
echo.
echo ===============================================
echo    SUZOSKY - CONFIGURATION GITHUB TOKEN
echo ===============================================
echo.
echo  Ce script configure l'authentification GitHub
echo  pour eliminer les demandes d'autorisation
echo.
echo  IMPORTANT: Vous devez avoir un Personal Access Token
echo  depuis GitHub.com ^> Settings ^> Developer settings
echo.
echo Appuyez sur ENTREE pour continuer...
pause > nul

cd /d "C:\xampp\htdocs\COURSIER_LOCAL"
powershell -ExecutionPolicy Bypass -File "scripts\configure_auto_auth.ps1"

echo.
echo Configuration terminee !
echo.
echo REDEMARREZ MAINTENANT PROTECTION_AUTO.bat
echo Les sauvegardes seront 100%% automatiques.
echo.
pause