@echo off
title RECUPERATION DE VERSIONS - SUZOSKY
color 0F
echo.
echo ==========================================
echo         RECUPERATION DE VERSIONS
echo           Fichiers Precedents
echo ==========================================
echo.
echo Ce script vous permet de recuperer facilement
echo n'importe quel fichier dans l'etat ou il
echo fonctionnait precedemment.
echo.
echo EXEMPLES D'UTILISATION :
echo.
echo 1. admin.php (quand CinetPay marchait)
echo    Tapez : admin.php cinetpay
echo.
echo 2. index.php (initialisation commandes OK)  
echo    Tapez : index.php commandes
echo.
echo 3. config.php (version stable)
echo    Tapez : config.php stable
echo.
echo 4. Voir toutes les versions d'un fichier
echo    Tapez : admin.php all
echo.
echo ==========================================

set /p "fichier=Nom du fichier (ex: admin.php) : "
set /p "version=Version/mot-cle (ex: cinetpay, commandes, stable, all) : "

echo.
echo Recherche des versions de "%fichier%" avec mot-cle "%version%"...
echo.

if "%version%"=="all" (
    echo === TOUTES LES VERSIONS DE %fichier% ===
    git log --oneline --follow -- "%fichier%" 2>nul
    if errorlevel 1 (
        echo Fichier non trouve dans l'historique Git
    )
) else (
    echo === VERSIONS AVEC MOT-CLE "%version%" ===
    git log --oneline --grep="%version%" --follow -- "%fichier%" 2>nul
    if errorlevel 1 (
        echo Aucune version trouvee avec ce mot-cle
    )
)

echo.
echo ==========================================
echo Pour recuperer une version specifique :
echo   git show COMMIT_HASH:%fichier% ^> %fichier%.backup
echo.
echo Ou utilisez : git checkout COMMIT_HASH -- %fichier%
echo ==========================================
echo.

pause