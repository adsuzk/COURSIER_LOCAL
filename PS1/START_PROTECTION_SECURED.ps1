# SCRIPT DE PROTECTION AUTOMATIQUE SECURISE - SUZOSKY
# Version sécurisée sans token exposé dans le code

Write-Host "=== PROTECTION AUTOMATIQUE SUZOSKY ===" -ForegroundColor Green
Write-Host "Repository: https://github.com/adsuzk/COURSIER_LOCAL" -ForegroundColor Yellow
Write-Host "Frequence: 5 secondes" -ForegroundColor Yellow
Write-Host ""

# Configuration Git avec variables d'environnement
$env:GIT_ASKPASS = "echo"
git config --global credential.helper "store --file=C:\Users\manud\.git-credentials"
$gitUrl = "https://$($env:GIT_USERNAME):$($env:GIT_PASSWORD)@github.com/"
git config --global url."$gitUrl".insteadOf "https://github.com/"

Write-Host "=== SURVEILLANCE ACTIVE ===" -ForegroundColor Cyan
Write-Host "Appuyez sur Ctrl+C pour arreter" -ForegroundColor Red
Write-Host ""

$scanCount = 0
while ($true) {
    $scanCount++
    $timestamp = Get-Date -Format "HH:mm:ss"
    
    # Vérifier les changements
    $status = git status --porcelain
    if ($status) {
        Write-Host "[$timestamp] Sauvegarde changements..." -ForegroundColor Yellow
        
        git add .
        git commit -m "Auto-backup $timestamp (scan #$scanCount)"
        
        # Push avec gestion d'erreur
        $pushResult = git push 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Host "[$timestamp] ✓ Sauvegarde GitHub terminée" -ForegroundColor Green
        } else {
            Write-Host "[$timestamp] ✗ Erreur push GitHub" -ForegroundColor Red
            Write-Host $pushResult -ForegroundColor Red
        }
    } else {
        Write-Host "[$timestamp] Aucun changement détecté (scan #$scanCount)" -ForegroundColor Gray
    }
    
    Start-Sleep 5
}