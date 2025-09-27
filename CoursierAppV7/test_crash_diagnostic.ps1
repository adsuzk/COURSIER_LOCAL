# Script de Test et Diagnostic des Crashes - Suzosky Coursier App
Write-Host "=== DIAGNOSTIC CRASHES SUZOSKY COURSIER APP ===" -ForegroundColor Green

Write-Host "`n1. Nettoyage des logs précédents..." -ForegroundColor Yellow
& adb logcat -c

Write-Host "`n2. Build APK en mode debug (sans lint)..." -ForegroundColor Yellow
& ./gradlew clean assembleDebug -x lintDebug

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Build réussi!" -ForegroundColor Green
    
    Write-Host "`n3. Installation de l'APK..." -ForegroundColor Yellow
    & adb install -r "app/build/outputs/apk/debug/app-debug.apk"
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Installation réussie!" -ForegroundColor Green
        
        Write-Host "`n4. Démarrage du monitoring des logs..." -ForegroundColor Yellow
        Write-Host "📱 Maintenant, lancez l'application sur votre téléphone/émulateur"
        Write-Host "   Les logs de crash s'afficheront ici en temps réel"
        Write-Host "   Appuyez sur Ctrl+C pour arrêter le monitoring"
        
        # Filtrer les logs pour Suzosky Coursier
        & adb logcat -s "System.out:I" AndroidRuntime:E
        
    } else {
        Write-Host "❌ Échec de l'installation" -ForegroundColor Red
    }
} else {
    Write-Host "❌ Échec du build" -ForegroundColor Red
}

Write-Host "`n=== FIN DIAGNOSTIC ===" -ForegroundColor Green