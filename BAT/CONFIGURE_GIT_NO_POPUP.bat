@echo off
title SUZOSKY - Configuration Git Anti-Popup
color 0C
echo.
echo ===============================================
echo    CONFIGURATION GIT ANTI-POPUP DEFINITIVE
echo ===============================================
echo.

REM Variables d'environnement USER pour désactiver TOUS les popups Git
setx GIT_ASKPASS "echo"
setx GIT_USERNAME "adsuzk"
setx GIT_PASSWORD "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"
setx GCM_INTERACTIVE "never"
setx GIT_TERMINAL_PROMPT "0"

REM Configuration Git globale ultra agressive
git config --global user.email "suzosky@github.com"
git config --global user.name "Suzosky Protection"
git config --global credential.helper ""
git config --global credential.useHttpPath true
git config --global core.askPass ""
git config --global gui.askpass ""
git config --global credential.modalPrompt false
git config --global credential.guiPrompt false
git config --global http.sslVerify false

REM URL de substitution avec token intégré
git config --global url."https://adsuzk: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@github.com/".insteadOf "https://github.com/"
git config --global url."https://adsuzk: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@github.com/".insteadOf "git@github.com:"

REM Créer le fichier credentials
echo https://adsuzk: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@github.com > "%USERPROFILE%\.git-credentials"

echo.
echo ===============================================
echo    CONFIGURATION TERMINEE - ZERO POPUP !
echo ===============================================
echo.
echo  Redemarrez TOUS les terminaux/programmes Git
echo  Le token sera TOUJOURS utilisé automatiquement
echo.
pause