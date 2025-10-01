# Script de surveillance du ping FCM en temps réel
Write-Host "🔍 Surveillance du ping FCM - Appuyez sur Ctrl+C pour arrêter" -ForegroundColor Green
Write-Host ""

$lastStatus = ""
$lastCount = 0

while ($true) {
    try {
        # Requête de l'état des tokens
        $result = C:\xampp\mysql\bin\mysql.exe -u root -e "USE coursier_local; SELECT CONCAT('Token ID: ', id, ' | Coursier: ', coursier_id, ' | Active: ', is_active, ' | Last Ping: ', COALESCE(last_ping, 'Never'), ' | Seconds ago: ', TIMESTAMPDIFF(SECOND, COALESCE(last_ping, updated_at), NOW())) as status FROM device_tokens WHERE is_active = 1 ORDER BY last_ping DESC LIMIT 1;" 2>$null
        
        if ($result -and $result.Count -gt 2) {
            $currentStatus = $result[2]
            if ($currentStatus -ne $lastStatus) {
                $timestamp = Get-Date -Format "HH:mm:ss"
                Write-Host "[$timestamp] $currentStatus" -ForegroundColor Yellow
                $lastStatus = $currentStatus
            }
        }
        
        # Test de l'API
        try {
            $api = Invoke-RestMethod "http://localhost/COURSIER_LOCAL/api/get_coursier_availability.php" -ErrorAction SilentlyContinue
            $availability = if ($api.available) { "✅ OUVERT" } else { "❌ FERMÉ" }
            $counts = "Active: $($api.active_count) | Fresh: $($api.fresh_count)"
            
            if ($api.active_count -ne $lastCount) {
                $timestamp = Get-Date -Format "HH:mm:ss"
                Write-Host "[$timestamp] Formulaire: $availability | $counts" -ForegroundColor Cyan
                $lastCount = $api.active_count
            }
        } catch {
            # Ignore API errors
        }
        
        Start-Sleep -Seconds 5
    } catch {
        Write-Host "Erreur: $($_.Exception.Message)" -ForegroundColor Red
        Start-Sleep -Seconds 10
    }
}