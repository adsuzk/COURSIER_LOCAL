@echo off
title SUZOSKY - Protection Automatique GitHub
color 0A
echo.
echo ===============================================
echo    SUZOSKY - PROTECTION AUTOMATIQUE GITHUB
echo ===============================================
echo.
echo  Demarrage de la surveillance permanente...
echo  Dossiers surveilles: COURSIER_LOCAL + coursier_prod  
echo  Sauvegarde automatique sur GitHub
echo  Verification toutes les 5 secondes
echo.
echo  GARDEZ CETTE FENETRE OUVERTE
echo.
echo Appuyez sur ENTREE pour demarrer...
pause > nul

cd /d "C:\xampp\htdocs\COURSIER_LOCAL"
powershell -ExecutionPolicy Bypass -File "scripts\START_PROTECTION_FIXED.ps1"

echo.
echo Protection arretee. Appuyez sur une touche pour fermer.
pause > nul
