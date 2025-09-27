@echo off
title RECUPERER FICHIER ANCIEN - SIMPLE
color 0C
echo.
echo ========================================
echo    RECUPERER UN FICHIER ANCIEN
echo          Version Simple
echo ========================================
echo.
echo Vous voulez recuperer un fichier comme il etait
echo quand ca marchait ? Ce script va vous aider !
echo.
echo EXEMPLES :
echo  admin.php
echo  index.php  
echo  config.php
echo  cinetpay_integration.php
echo.

set /p "fichier=Tapez le nom du fichier : "

echo.
echo Recherche des versions de "%fichier%"...
echo.
echo === HISTORIQUE DU FICHIER ===
git log --oneline --follow -- "%fichier%" 2>nul | head -10

echo.
echo === POUR RECUPERER UNE VERSION ===
echo 1. Notez le code a gauche (ex: a1b2c3d)
echo 2. Tapez : git checkout CODE -- %fichier%
echo 3. Votre fichier sera restaure !
echo.
echo === RECHERCHE PAR MOT-CLE ===
set /p "motcle=Tapez un mot-cle (ex: cinetpay, working, stable) : "

if not "%motcle%"=="" (
    echo.
    echo Versions contenant "%motcle%" :
    git log --oneline --all --grep="%motcle%" -- "%fichier%" 2>nul
)

echo.
echo ========================================
echo    COMMANDES UTILES POUR VOUS
echo ========================================
echo.
echo Voir le contenu d'une ancienne version :
echo   git show CODE:%fichier%
echo.
echo Restaurer une ancienne version :
echo   git checkout CODE -- %fichier%
echo.
echo Revenir a la version actuelle :
echo   git checkout HEAD -- %fichier%
echo.
pause