Write-Host "=== TEST E2E SUZOSKY AUTOMATIQUE ===" -ForegroundColor Yellow
Write-Host "Coursier ID: 5 (ZALLE avec token FCM reel)" -ForegroundColor Green

# Nettoyage logs ADB
Write-Host "1. Nettoyage logs ADB..." -ForegroundColor Cyan
adb logcat -c

# Test E2E via HTTP
Write-Host "2. Lancement test E2E..." -ForegroundColor Cyan
$postData = "coursier_id=5&token=&run_all=1"

try {
    $response = Invoke-RestMethod -Uri "http://localhost/COURSIER_LOCAL/Tests/e2e_fullstack_runner.php" -Method POST -Body $postData -ContentType "application/x-www-form-urlencoded"
    Write-Host "Test E2E complete" -ForegroundColor Green
} catch {
    Write-Host "Erreur test E2E: $($_.Exception.Message)" -ForegroundColor Red
}

# Verification logs ADB
Write-Host "3. Verification logs ADB..." -ForegroundColor Cyan
Start-Sleep -Seconds 3
$logs = adb logcat -d | Select-String -Pattern "suzosky|fcm|notification" -CaseSensitive:$false | Select-Object -Last 5

if ($logs) {
    Write-Host "LOGS ADB RECENTS:" -ForegroundColor Yellow
    $logs | ForEach-Object { Write-Host $_.Line -ForegroundColor White }
} else {
    Write-Host "Aucun log ADB recent" -ForegroundColor Red
}

Write-Host "=== FIN DU TEST ===" -ForegroundColor Yellow