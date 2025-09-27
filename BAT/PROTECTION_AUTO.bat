@echo off
title SUZOSKY - Protection Automatique GitHub - UNIFIÉE
color 0A
echo.
echo ===============================================
echo    SUZOSKY - PROTECTION AUTOMATIQUE GITHUB
echo          VERSION UNIFIÉE ET CORRIGÉE
echo ===============================================
echo.
echo  ✓ Token GitHub testé et fonctionnel
echo  ✓ Authentification automatique sécurisée  
echo  ✓ Sauvegarde automatique toutes les 5 secondes
echo  ✓ Gestion d'erreur avancée
echo.
echo  GARDEZ CETTE FENETRE OUVERTE
echo.
echo Appuyez sur ENTREE pour demarrer...
pause > nul

cd /d "C:\xampp\htdocs\COURSIER_LOCAL"
powershell -ExecutionPolicy Bypass -File "scripts\START_PROTECTION_SIMPLE.ps1"

echo.
echo Protection arretee. Appuyez sur une touche pour fermer.
pause > nul
