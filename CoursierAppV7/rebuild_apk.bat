@echo off
REM Script de rebuild de l'APK avec les corrections du bug de rotation
echo ========================================
echo  REBUILD APK - Correction Bug Rotation
echo ========================================
echo.

cd /d C:\xampp\htdocs\COURSIER_LOCAL\CoursierAppV7

echo [1/3] Nettoyage du projet...
call gradlew.bat clean

echo.
echo [2/3] Build de l'APK Debug...
call gradlew.bat assembleDebug

echo.
echo [3/3] Verification de l'APK...
if exist "app\build\outputs\apk\debug\app-debug.apk" (
    echo.
    echo ========================================
    echo  ✅ BUILD REUSSI !
    echo ========================================
    echo.
    echo APK Location:
    echo   app\build\outputs\apk\debug\app-debug.apk
    echo.
    echo Pour installer sur le telephone:
    echo   adb install -r app\build\outputs\apk\debug\app-debug.apk
    echo.
) else (
    echo.
    echo ========================================
    echo  ❌ ERREUR: APK non trouvé !
    echo ========================================
    echo.
)

pause
