@echo off
title Configuration Complete - COURSIER LOCAL & PROD
color 0A
echo.
echo ========================================
echo    CONFIGURATION FINALE SUZOSKY
echo      COURSIER_LOCAL + coursier_prod
echo ========================================
echo.

echo [1/4] Configuration des variables d'environnement...
setx GIT_USERNAME "adsuzk"
setx GIT_PASSWORD "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"
setx GITHUB_TOKEN "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"

echo.
echo [2/4] Configuration Git globale...
git config --global user.name "adsuzk"
git config --global user.email "your-email@domain.com"
git config --global credential.helper "store --file=C:\Users\manud\.git-credentials"
git config --global push.autoSetupRemote true

echo.
echo [3/4] Configuration des credentials GitHub...
echo https://adsuzk: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@github.com > C:\Users\manud\.git-credentials

echo.
echo [4/4] Test de la configuration...
cd /d "c:\xampp\htdocs\COURSIER_LOCAL"
git remote set-url origin "https://adsuzk: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@github.com/adsuzk/COURSIER_LOCAL.git"

echo.
echo ✓ Configuration terminee !
echo.
echo ===========================================
echo          REPOSITORIES CONFIGURES
echo ===========================================
echo  ● COURSIER_LOCAL (Development)
echo    └─ Path: c:\xampp\htdocs\COURSIER_LOCAL
echo    └─ GitHub: https://github.com/adsuzk/COURSIER_LOCAL
echo    └─ Protection: scripts\START_PROTECTION_CLEAN.ps1
echo.
echo  ● coursier_prod (Production)  
echo    └─ Path: c:\xampp\htdocs\coursier_prod
echo    └─ Sync: scripts\SYNC_TO_PROD_LWS.ps1
echo    └─ GitHub: A creer manuellement
echo.
echo ===========================================
echo            COMMANDES UTILES
echo ===========================================
echo  Protection auto: BAT\PROTECTION_AUTO.bat
echo  Sync production: BAT\SYNC_PROD_LWS.bat
echo  Scripts PowerShell: scripts\*.ps1
echo.

pause