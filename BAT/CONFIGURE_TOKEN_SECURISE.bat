@echo off
title Configuration Token GitHub Definitif
echo.
echo ====================================
echo    CONFIGURATION TOKEN GITHUB 
echo         VERSION SECURISEE
echo ====================================
echo.

echo Configuration des variables d'environnement...
setx GIT_USERNAME "adsuzk"
setx GIT_PASSWORD "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"

echo.
echo Configuration Git globale...
git config --global credential.helper "store --file=C:\Users\manud\.git-credentials"
git config --global url."https://adsuzk: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@github.com/".insteadOf "https://github.com/"

echo.
echo ✓ Configuration terminee !
echo.
echo Les repositories configures :
echo  - COURSIER_LOCAL : https://github.com/adsuzk/COURSIER_LOCAL
echo  - coursier_prod  : https://github.com/adsuzk/coursier_prod
echo.
echo Utilisation des scripts securises :
echo  - scripts\START_PROTECTION_SECURED.ps1
echo  - scripts\SETUP_COURSIER_PROD_GITHUB_SECURED.ps1
echo.
pause