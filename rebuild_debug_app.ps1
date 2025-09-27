# Script pour rebuild l'app avec debug FCM renforcé
cd CoursierAppV7

Write-Host "=== REBUILD APP AVEC DEBUG FCM ===" -ForegroundColor Green

# Nettoyer
./gradlew clean

# Rebuild
./gradlew assembleDebug

Write-Host "=== APK GÉNÉRÉE ===" -ForegroundColor Green
Write-Host "Fichier: CoursierAppV7\app\build\outputs\apk\debug\app-debug.apk" -ForegroundColor Yellow
Write-Host ""
Write-Host "ÉTAPES SUIVANTES:" -ForegroundColor Cyan
Write-Host "1. Désinstaller ancienne app du téléphone" -ForegroundColor White
Write-Host "2. Installer cette nouvelle APK" -ForegroundColor White
Write-Host "3. Se connecter avec CM20250001/g4mKU" -ForegroundColor White
Write-Host "4. Surveiller le terminal de monitoring" -ForegroundColor White