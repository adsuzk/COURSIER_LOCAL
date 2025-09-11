@echo off
title SUZOSKY - Protection Automatique GitHub
echo.
echo ===============================================
echo    SUZOSKY - PROTECTION AUTOMATIQUE GITHUB
echo ===============================================
echo.
echo 🛡️  Demarrage de la surveillance permanente...
echo 📁 Dossiers surveilles: COURSIER_LOCAL + coursier_prod  
echo 💾 Sauvegarde automatique sur GitHub
echo ⏱️  Verification toutes les 30 secondes
echo.
echo ⚠️  GARDEZ CETTE FENETRE OUVERTE ⚠️
echo.
echo Appuyez sur une touche pour demarrer...
pause > nul

cd /d "C:\xampp\htdocs\COURSIER_LOCAL"
powershell -ExecutionPolicy Bypass -File "START_PROTECTION.ps1"

echo.
echo Protection arretee. Appuyez sur une touche pour fermer.
pause > nul
