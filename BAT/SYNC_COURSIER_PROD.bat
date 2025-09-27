@echo off
title SUZOSKY - Synchronisation Propre vers coursier_prod
color 0B
echo.
echo ===============================================
echo      SUZOSKY - SYNCHRONISATION PROPRE
echo        vers coursier_prod (PRODUCTION)
echo ===============================================
echo.
echo  🔄 Synchronisation intelligente avec exclusions
echo  🚫 Exclusion automatique : tests, debug, CLI, logs
echo  ✅ Structure production optimisée
echo  📁 Target: C:\xampp\htdocs\coursier_prod
echo.
echo  Cette opération va synchroniser le projet vers
echo  coursier_prod en excluant tous les fichiers
echo  de développement et de test.
echo.
echo Appuyez sur ENTREE pour continuer...
pause > nul

cd /d "C:\xampp\htdocs\COURSIER_LOCAL"
powershell -ExecutionPolicy Bypass -File "scripts\SYNC_COURSIER_PROD_SIMPLE.ps1" -Verbose

echo.
echo Synchronisation terminée. Appuyez sur une touche pour fermer.
pause > nul