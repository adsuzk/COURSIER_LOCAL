@echo off
title SUZOSKY - Protection GitHub Automatique
color 0A
echo.
echo ===============================================
echo       SUZOSKY - PROTECTION GITHUB AUTOMATIQUE
echo              VERSION SEPTEMBRE 2025
echo ===============================================
echo.
echo  + Sauvegarde automatique COURSIER_LOCAL vers GitHub
echo  + Protection en temps reel toutes les 5 secondes
echo  + Sauvegarde automatique BASE DE DONNEES (structure + donnees)
echo  + Git Credential Manager securise
echo  + Commits automatiques avec timestamps
echo  + Repository: https://github.com/adsuzk/COURSIER_LOCAL
echo.
echo  IMPORTANT: Cette protection sauvegarde le code ET la base de donnees
echo  vers GitHub (sauvegarde complete dans _sql/auto_backups/).
echo  Pour synchroniser vers coursier_prod, utilisez
echo  SYNC_COURSIER_PROD.bat separement.
echo.
echo  GARDEZ CETTE FENETRE OUVERTE
echo.
echo Appuyez sur ENTREE pour demarrer la protection...
pause > nul

cd /d "C:\xampp\htdocs\COURSIER_LOCAL"
REM Protection complete avec base de donnees
powershell -ExecutionPolicy Bypass -File "PS1\PROTECTION_GITHUB_AVEC_DB.ps1"

echo.
echo Protection GitHub arretee. Appuyez sur une touche pour fermer.
pause > nul