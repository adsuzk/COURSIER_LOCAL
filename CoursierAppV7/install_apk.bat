@echo off
REM Script pour installer l'APK sur le téléphone via ADB
echo ========================================
echo  INSTALLATION APK via ADB
echo ========================================
echo.

cd /d C:\xampp\htdocs\COURSIER_LOCAL\CoursierAppV7

echo [1/2] Verification du telephone connecte...
adb devices
echo.

echo [2/2] Installation de l'APK...
adb install -r app\build\outputs\apk\debug\app-debug.apk

echo.
if %ERRORLEVEL% EQU 0 (
    echo ========================================
    echo  ✅ INSTALLATION REUSSIE !
    echo ========================================
    echo.
    echo L'application a ete mise a jour sur le telephone.
    echo Vous pouvez maintenant la tester.
    echo.
) else (
    echo ========================================
    echo  ❌ ERREUR D'INSTALLATION !
    echo ========================================
    echo.
    echo Verifiez que:
    echo   1. Le telephone est connecte en USB
    echo   2. Le debogage USB est active
    echo   3. ADB est bien installe
    echo.
)

pause
