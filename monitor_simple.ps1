Write-Host "=== SURVEILLANCE PING FCM TEMPS REEL ===" -ForegroundColor Green
Write-Host "Appuyez sur Ctrl+C pour arreter" -ForegroundColor Yellow
Write-Host ""

$lastStatus = ""

while ($true) {
    try {
        # Vérifier l'état des tokens
        $query = "USE coursier_local; SELECT CONCAT('ID:', id, ' Coursier:', coursier_id, ' Active:', is_active, ' SecAgo:', TIMESTAMPDIFF(SECOND, COALESCE(last_ping, updated_at), NOW())) as status FROM device_tokens WHERE is_active = 1 ORDER BY last_ping DESC LIMIT 1;"
        $result = & "C:\xampp\mysql\bin\mysql.exe" -u root -e $query 2>$null
        
        if ($result) {
            $currentStatus = $result | Select-Object -Last 1
            if ($currentStatus -and $currentStatus -ne $lastStatus) {
                $timestamp = Get-Date -Format "HH:mm:ss"
                Write-Host "[$timestamp] $currentStatus" -ForegroundColor Cyan
                $lastStatus = $currentStatus
            }
        }
        
        Start-Sleep -Seconds 3
        
    } catch {
        Write-Host "Erreur: $($_.Exception.Message)" -ForegroundColor Red
        Start-Sleep -Seconds 5
    }
}