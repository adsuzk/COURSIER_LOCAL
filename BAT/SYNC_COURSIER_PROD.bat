@echo off
title SUZOSKY - Synchronisation vers coursier_prod (LWS)
color 0B
echo.
echo ===============================================
echo      SUZOSKY - SYNCHRONISATION PRODUCTION
echo        COURSIER_LOCAL --^> coursier_prod (LWS)
echo ===============================================
echo.
echo  + Synchronisation intelligente avec exclusions LWS
echo  + Exclusion automatique : tests, debug, logs, .md, .ps1
echo  + Tests/debug --^> dossier Tests/
echo  + Scripts --^> dossier scripts/
echo  + Racine propre sans fichiers dev
echo  + Configuration LWS appliquee automatiquement
echo  + Target: C:\xampp\htdocs\coursier_prod
echo.
echo  ATTENTION: Cette operation va:
echo  - Copier COURSIER_LOCAL vers coursier_prod
echo  - Reorganiser la structure pour LWS
echo  - Appliquer les configurations de production
echo  - Exclure tous les fichiers de developpement
echo.
echo Appuyez sur ENTREE pour continuer ou CTRL+C pour annuler...
pause > nul

cd /d "C:\xampp\htdocs\COURSIER_LOCAL"
powershell -ExecutionPolicy Bypass -File "scripts\SYNC_COURSIER_PROD_LWS.ps1"

echo.
echo Synchronisation coursier_prod terminee. Appuyez sur une touche pour fermer.
pause > nul