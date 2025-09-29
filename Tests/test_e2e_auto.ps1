# Test E2E automatique avec surveillance ADB
Write-Host "=== TEST E2E SUZOSKY AUTOMATIQUE ===" -ForegroundColor Yellow
Write-Host "Coursier ID: 3 (YAPO)" -ForegroundColor Green
Write-Host "Device ADB: 12334454CF015507" -ForegroundColor Green

# 1. Nettoyage des logs ADB
Write-Host "`n1. Nettoyage logs ADB..." -ForegroundColor Cyan
adb logcat -c

# 2. Démarrage surveillance ADB en arrière-plan
Write-Host "2. Démarrage surveillance ADB..." -ForegroundColor Cyan
$adbJob = Start-Job -ScriptBlock {
    adb logcat | Select-String -Pattern "suzosky|fcm|notification|firebase" -CaseSensitive:$false
}

# 3. Lancement test E2E via curl PowerShell
Write-Host "3. Lancement test E2E..." -ForegroundColor Cyan
$postData = @{
    'coursier_id' = '3'
    'token' = ''
    'run_all' = '1'
}

try {
    $response = Invoke-WebRequest -Uri "http://localhost/COURSIER_LOCAL/Tests/e2e_fullstack_runner.php" -Method POST -Body $postData -ContentType "application/x-www-form-urlencoded"
    
    Write-Host "4. Résultat test E2E:" -ForegroundColor Cyan
    if ($response.StatusCode -eq 200) {
        Write-Host "✅ Test lancé avec succès" -ForegroundColor Green
        # Extraire les résultats
        if ($response.Content -match 'PASS.*?FCM.*?sonnerie') {
            Write-Host "✅ FCM et sonnerie: OK" -ForegroundColor Green
        }
        if ($response.Content -match 'FAIL') {
            Write-Host "❌ Certains tests ont échoué" -ForegroundColor Red
        }
    } else {
        Write-Host "❌ Erreur HTTP: $($response.StatusCode)" -ForegroundColor Red
    }
} catch {
    Write-Host "❌ Erreur test E2E: $($_.Exception.Message)" -ForegroundColor Red
}

# 4. Attendre et récupérer logs ADB
Write-Host "`n5. Surveillance logs ADB (10 secondes)..." -ForegroundColor Cyan
Start-Sleep -Seconds 10

$adbLogs = Receive-Job -Job $adbJob -Keep
if ($adbLogs) {
    Write-Host "`n📱 LOGS ADB CAPTÉS:" -ForegroundColor Yellow
    $adbLogs | ForEach-Object { Write-Host $_ -ForegroundColor White }
} else {
    Write-Host "❌ Aucun log ADB capté" -ForegroundColor Red
}

# Nettoyage
Stop-Job -Job $adbJob
Remove-Job -Job $adbJob

Write-Host "`n=== FIN DU TEST ===" -ForegroundColor Yellow