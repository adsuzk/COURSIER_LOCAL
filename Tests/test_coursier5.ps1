#!/usr/bin/env powershell
# Test E2E simple avec coursier spécifique
echo "=== TEST E2E SUZOSKY - COURSIER 5 ==="

# Coursier avec token actif
$coursierId = 5

echo "Coursier ID: $coursierId"

# 1) Nettoyer les logs ADB
echo "1. Nettoyage logs ADB..."
adb logcat -c > $null 2>&1

# 2) Lancer le test E2E
echo "2. Lancement test E2E..."
$response = Invoke-RestMethod -Uri "http://localhost/COURSIER_LOCAL/Tests/e2e_fullstack_runner.php" -Method POST -Body @{coursier_id=$coursierId;action='run_test'} -ContentType "application/x-www-form-urlencoded"

echo "Test E2E complete"

# 3) Capturer les logs ADB
echo "3. Verification logs ADB..."
Start-Sleep -Seconds 2
$adbLogs = adb logcat -d -s "FCM:V" "Suzosky:V" "firebase:V" | Select-String -Pattern "FCM|firebase|notification|Suzosky" | Select-Object -Last 10

if ($adbLogs) {
    echo "Logs ADB captures:"
    $adbLogs | ForEach-Object { echo "  $_" }
} else {
    echo "Aucun log ADB recent"
}

# 4) Vérifier les logs PHP
echo "4. Verification logs PHP..."
$phpErrors = Get-Content "c:\xampp\php\logs\php_error_log" -Tail 5 -ErrorAction SilentlyContinue | Where-Object { $_ -match "DEBUG E2E" }
if ($phpErrors) {
    echo "Logs PHP Debug:"
    $phpErrors | ForEach-Object { echo "  $_" }
}

echo "=== FIN DU TEST ==="