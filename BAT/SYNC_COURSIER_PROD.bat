@echo off
title SUZOSKY - Synchronisation vers coursier_prod
color 0A
echo.
echo ===============================================
echo      SUZOSKY - SYNCHRONISATION PRODUCTION
echo        vers coursier_prod (LWS)
echo ===============================================
echo.
echo  + Synchronisation intelligente avec exclusions
echo  + Exclusion automatique : tests, debug, logs
echo  + Structure production optimisee
echo  + Configuration LWS automatique
echo  + Target: C:\xampp\htdocs\coursier_prod
echo.
echo  Cette operation va synchroniser le projet vers
echo  coursier_prod en excluant tous les fichiers
echo  de developpement et configurer pour LWS.
echo.
echo Appuyez sur ENTREE pour continuer...
pause > nul

cd /d "C:\xampp\htdocs\COURSIER_LOCAL"
powershell -ExecutionPolicy Bypass -File "scripts\SYNC_SIMPLE_ASCII.ps1"

echo.
echo Synchronisation terminee. Appuyez sur une touche pour fermer.
pause > nul