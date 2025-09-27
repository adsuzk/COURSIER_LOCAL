@echo off
title SUZOSKY - Configuration Token GitHub DEFINITIF
color 0E
echo.
echo ===============================================
echo    CONFIGURATION TOKEN GITHUB DEFINITIF
echo ===============================================
echo.
echo  Ce script va configurer le token GitHub
echo  de maniere PERMANENTE pour eviter TOUT popup
echo.

set /p TOKEN="Entrez votre token GitHub (ghp_xxx): "

if "%TOKEN%"=="" (
    echo ERREUR: Token vide!
    pause
    exit /b 1
)

echo.
echo Configuration du token: %TOKEN%
echo.

REM Variables d'environnement pour PowerShell et Git
setx GIT_ASKPASS "echo"
setx GIT_USERNAME "adsuzk"
setx GIT_PASSWORD "%TOKEN%"
setx GCM_INTERACTIVE "never"
setx GIT_TERMINAL_PROMPT "0"

REM Configuration Git globale
git config --global user.email "suzosky@github.com"
git config --global user.name "Suzosky Protection"
git config --global credential.helper ""
git config --global core.askPass ""
git config --global gui.askpass ""
git config --global credential.modalPrompt false
git config --global credential.guiPrompt false
git config --global http.sslVerify false

REM URL avec token intégré
git config --global url."https://adsuzk:%TOKEN%@github.com/".insteadOf "https://github.com/"
git config --global url."https://adsuzk:%TOKEN%@github.com/".insteadOf "git@github.com:"

REM Créer le fichier credentials avec le nouveau token
echo https://adsuzk:%TOKEN%@github.com > "%USERPROFILE%\.git-credentials"

REM Configurer le repo local avec le nouveau token
cd /d "%~dp0.."
git remote set-url origin "https://adsuzk:%TOKEN%@github.com/adsuzk/COURSIER_LOCAL.git"

REM Mettre à jour le script PowerShell avec le nouveau token
powershell -Command "(Get-Content 'scripts\START_PROTECTION_FIXED.ps1') -replace 'ghp_[A-Za-z0-9_]{36}', '%TOKEN%' | Set-Content 'scripts\START_PROTECTION_FIXED.ps1'"

echo.
echo ===============================================
echo    TOKEN CONFIGURE AVEC SUCCES !
echo ===============================================
echo.
echo  Token: %TOKEN%
echo  Le script de protection utilisera ce token
echo  AUCUN popup ne sera jamais affiche !
echo.
pause