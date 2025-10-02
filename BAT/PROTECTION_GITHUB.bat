@echo off
title SUZOSKY - Protection GitHub + Base de Donn?es AUTO
color 0A
echo.
echo =====================================================
echo   SUZOSKY - PROTECTION GITHUB + BASE DE DONNEES
echo           VERSION OCTOBRE 2025 - AMELIOREE
echo =====================================================
echo.
echo  + Sauvegarde automatique COURSIER_LOCAL vers GitHub
echo  + Sauvegarde automatique de la BASE DE DONNEES
echo  + Export structure SQL a chaque commit
echo  + Export complet avec donnees
echo  + Historique des modifications de colonnes
echo  + Protection en temps reel toutes les 5 secondes
echo.
echo  AVANTAGES DE CETTE VERSION:
echo  ---------------------------
echo  ^> Structure DB toujours synchronisee
echo  ^> Plus jamais de colonnes manquantes
echo  ^> Restauration facile en cas de probleme
echo  ^> Historique complet des changements
echo.
echo  Repository: https://github.com/adsuzk/COURSIER_LOCAL
echo.
echo  NOTE: L'ancienne version est disponible dans
echo        PROTECTION_GITHUB_OLD.bat si besoin
echo.
echo  GARDEZ CETTE FENETRE OUVERTE
echo.
echo Appuyez sur ENTREE pour demarrer la protection...
pause ^> nul

cd /d "C:\xampp\htdocs\COURSIER_LOCAL"
powershell -ExecutionPolicy Bypass -File "PS1\PROTECTION_GITHUB_AVEC_DB.ps1"

echo.
echo Protection arretee. Appuyez sur une touche pour fermer.
pause ^> nul
