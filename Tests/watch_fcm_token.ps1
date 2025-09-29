# Surveillance en continu de l'enregistrement du token FCM réel par l'application

Write-Output "=== SURVEILLANCE TOKEN FCM REEL ==="
Write-Output "Attente de l'enregistrement du token par l'application Android..."

$maxWait = 60  # 60 secondes max
$elapsed = 0

while ($elapsed -lt $maxWait) {
    # Vérifier la base de données
    $result = & "c:\xampp\mysql\bin\mysql.exe" -u root coursier_local -e "SELECT COUNT(*) as count FROM device_tokens;" 2>$null
    
    if ($result -match "(\d+)") {
        $tokenCount = [int]$matches[1]
        if ($tokenCount -gt 0) {
            Write-Output "TOKEN DETECTE ! Récupération des détails..."
            
            # Récupérer les détails du token
            $tokenDetails = & "c:\xampp\mysql\bin\mysql.exe" -u root coursier_local -e "SELECT id, coursier_id, LEFT(token, 50) as token_preview, platform, is_active, created_at FROM device_tokens ORDER BY created_at DESC LIMIT 1;"
            
            Write-Output $tokenDetails
            Write-Output ""
            Write-Output "=== TOKEN FCM REEL ENREGISTRE ==="
            
            # Lancer immédiatement le test E2E
            Write-Output "Lancement du test E2E avec le token réel..."
            & ".\Tests\test_simple.ps1"
            
            break
        }
    }
    
    Write-Output "." -NoNewline
    Start-Sleep -Seconds 2
    $elapsed += 2
}

if ($elapsed -ge $maxWait) {
    Write-Output ""
    Write-Output "Timeout: Aucun token détecté après $maxWait secondes"
    Write-Output "L'application n'a pas enregistré de token FCM automatiquement"
    Write-Output ""
    Write-Output "Vérification manuelle des logs ADB..."
    adb logcat -d | Select-String -Pattern "token" | Select-Object -Last 5
}