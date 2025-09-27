@echo off
echo ====================================
echo    TESTEUR ADB - SUZOSKY COURSIER
echo ====================================
echo.

REM 1. Vérifier connexion ADB
echo [1/8] Verification connexion ADB...
adb devices
if %errorlevel% neq 0 (
    echo ERREUR: ADB non disponible ou appareil non connecte
    pause
    exit /b 1
)
echo ✓ ADB connecte
echo.

REM 2. Vérifier installation app
echo [2/8] Verification installation app...
adb shell pm list packages | findstr suzosky
if %errorlevel% neq 0 (
    echo ⚠️ App Suzosky Coursier non trouvee
    echo Packages contenant "coursier":
    adb shell pm list packages | findstr -i coursier
) else (
    echo ✓ App Suzosky trouvee
)
echo.

REM 3. Informations système
echo [3/8] Informations systeme...
echo Version Android:
adb shell getprop ro.build.version.release
echo Modèle:
adb shell getprop ro.product.model
echo Résolution:
adb shell wm size
echo.

REM 4. Test connectivité réseau
echo [4/8] Test connectivite reseau...
echo Test ping Google DNS:
adb shell ping -c 3 8.8.8.8
echo.
echo Test connectivite serveur local:
adb shell "curl -s -o /dev/null -w '%%{http_code}' http://10.0.2.2/COURSIER_LOCAL/mobile_sync_api.php?action=ping || echo 'Erreur connexion'"
echo.

REM 5. Forcer démarrage app
echo [5/8] Demarrage application...
echo Tentative demarrage app Suzosky:
adb shell am start -n com.suzosky.coursier/.MainActivity
if %errorlevel% neq 0 (
    echo ⚠️ Echec demarrage avec package com.suzosky.coursier
    echo Tentative avec d'autres noms possibles:
    adb shell am start -n com.coursier.suzosky/.MainActivity
    adb shell am start -n com.suzosky/.MainActivity
)
echo.

REM 6. Logs en temps réel (5 secondes)
echo [6/8] Capture logs application (5 secondes)...
timeout /t 2 /nobreak > nul
echo Logs Firebase/FCM:
timeout /t 3 /nobreak > nul | adb logcat -s FirebaseMessaging:* FCM:* *Coursier*:* -t 5
echo.

REM 7. Test API mobile
echo [7/8] Test API mobile...
echo Test ping API:
curl -s "http://localhost/COURSIER_LOCAL/mobile_sync_api.php?action=ping"
echo.
echo.

echo Test profil coursier ID 3:
curl -s "http://localhost/COURSIER_LOCAL/mobile_sync_api.php?action=get_profile&coursier_id=3"
echo.
echo.

echo Test commandes coursier:
curl -s "http://localhost/COURSIER_LOCAL/mobile_sync_api.php?action=get_commandes&coursier_id=3"
echo.
echo.

REM 8. Instructions finales
echo [8/8] Instructions finales
echo ====================================
echo.
echo 📱 ACTIONS A EFFECTUER SUR LE TELEPHONE:
echo 1. Ouvrir l'application Suzosky Coursier
echo 2. Se connecter avec matricule: CM20250001
echo 3. Verifier reception notifications
echo 4. Verifier affichage commandes
echo 5. Tester acceptation d'une commande
echo.
echo 🖥️  MONITORING SERVEUR:
echo - Logs API: tail -f mobile_sync_debug.log
echo - BDD commandes: SELECT * FROM commandes ORDER BY id DESC LIMIT 5;
echo - BDD notifications: SELECT * FROM notifications_log_fcm ORDER BY id DESC LIMIT 5;
echo.
echo 🔧 TESTS SUPPLEMENTAIRES:
echo - Test notification: http://localhost/COURSIER_LOCAL/mobile_sync_api.php?action=test_notification^&coursier_id=3
echo - Accepter commande: http://localhost/COURSIER_LOCAL/mobile_sync_api.php?action=accept_commande^&coursier_id=3^&commande_id=118
echo.
echo ====================================

pause