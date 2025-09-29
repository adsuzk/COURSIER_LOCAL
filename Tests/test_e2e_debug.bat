@echo off
echo =======================================================
echo        TEST E2E SUZOSKY - DEBUGGING AUTOMATIQUE
echo =======================================================
echo.
echo Coursier utilise: ID 3 (YAPO)
echo Telephone en debug ADB: 12334454CF015507
echo.
echo 1. Lancement du test E2E...
start "http://localhost/COURSIER_LOCAL/Tests/e2e_fullstack_runner.php"
echo.
echo 2. Surveillance des logs ADB en temps reel...
echo.
timeout /t 3
adb logcat -c
echo Logs nettoyes. Surveillance active...
echo Appuyez sur Ctrl+C pour arreter la surveillance.
echo.
adb logcat | findstr -i "suzosky\|fcm\|notification\|firebase"