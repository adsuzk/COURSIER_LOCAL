@echo off
title SUZOSKY - Synchronisation PROD LWS
color 0B
echo.
echo ===============================================
echo    SUZOSKY - SYNCHRONISATION LOCAL vers PROD
echo ===============================================
echo.
echo  ATTENTION: Script ultra robuste de synchronisation
echo  Source: C:\xampp\htdocs\COURSIER_LOCAL
echo  Destination: C:\xampp\htdocs\coursier_prod
echo  Configuration: Serveur LWS automatique
echo.
echo  Ce script va:
echo  - Nettoyer coursier_prod
echo  - Copier tous les fichiers (sauf tests/scripts)
echo  - Configurer automatiquement pour LWS
echo  - Surveiller en permanence les changements
echo.
echo  GARDEZ CETTE FENETRE OUVERTE
echo.
echo Appuyez sur ENTREE pour demarrer la synchronisation...
pause > nul

cd /d "C:\xampp\htdocs\COURSIER_LOCAL"
powershell -ExecutionPolicy Bypass -File "scripts\SYNC_TO_PROD_LWS.ps1"

echo.
echo Synchronisation arretee. Appuyez sur une touche pour fermer.
pause > nul