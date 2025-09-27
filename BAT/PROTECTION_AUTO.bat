@echo off
title SUZOSKY - Protection + Sync Propre - VERSION AVANCÉE
color 0A
echo.
echo ===============================================
echo   SUZOSKY - PROTECTION + SYNCHRONISATION PROPRE
echo           VERSION AVANCÉE - SEPT 2025
echo ===============================================
echo.
echo  ✓ Protection GitHub automatique (Git Credential Manager)
echo  ✓ Synchronisation coursier_prod PROPRE (sans tests/debug)
echo  ✓ Exclusion automatique des fichiers de développement
echo  ✓ Structure de production toujours optimisée
echo  ✓ Sauvegarde sécurisée toutes les 5 secondes
echo.
echo  GARDEZ CETTE FENETRE OUVERTE
echo.
echo Appuyez sur ENTREE pour demarrer...
pause > nul

cd /d "C:\xampp\htdocs\COURSIER_LOCAL"
powershell -ExecutionPolicy Bypass -File "scripts\PROTECTION_GITHUB_FINAL.ps1"

echo.
echo Protection arretee. Appuyez sur une touche pour fermer.
pause > nul
