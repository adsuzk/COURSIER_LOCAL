# SCRIPT DE PROTECTION AUTOMATIQUE SECURISE - SUZOSKY
# Version securisee sans token expose dans le code

Write-Host "=== PROTECTION AUTOMATIQUE SUZOSKY ===" -ForegroundColor Green
Write-Host "Repository: https://github.com/adsuzk/COURSIER_LOCAL" -ForegroundColor Yellow
Write-Host "Frequence: 5 secondes" -ForegroundColor Yellow
Write-Host ""

# Configuration Git avec credentials stockés
$env:GIT_ASKPASS = "echo"
git config --global credential.helper "store --file=C:\Users\manud\.git-credentials"
# Les credentials sont déjà stockés dans le fichier .git-credentials
Write-Host "Utilisation des credentials stockés pour GitHub" -ForegroundColor Green

Write-Host "=== SURVEILLANCE ACTIVE ===" -ForegroundColor Cyan
Write-Host "Appuyez sur Ctrl+C pour arreter" -ForegroundColor Red
Write-Host ""

$scanCount = 0
while ($true) {
    $scanCount++
    $timestamp = Get-Date -Format "HH:mm:ss"
    
    # Verifier les changements
    $status = git status --porcelain
    if ($status) {
        Write-Host "[$timestamp] Sauvegarde changements..." -ForegroundColor Yellow
        
        git add .
        git commit -m "Auto-backup $timestamp (scan #$scanCount)"
        
        # Push avec gestion d'erreur détaillée
        try {
            $pushResult = git push origin main 2>&1
            if ($LASTEXITCODE -eq 0) {
                Write-Host "[$timestamp] Sauvegarde GitHub terminee" -ForegroundColor Green
            } else {
                Write-Host "[$timestamp] Erreur push GitHub (code: $LASTEXITCODE)" -ForegroundColor Red
                Write-Host "Détails: $pushResult" -ForegroundColor Red
                # Test rapide de connectivité
                $testAuth = git ls-remote origin HEAD 2>&1
                if ($LASTEXITCODE -eq 0) {
                    Write-Host "Auth OK - Problème temporaire de push" -ForegroundColor Yellow
                } else {
                    Write-Host "Problème d'authentification détecté" -ForegroundColor Red
                }
            }
        } catch {
            Write-Host "[$timestamp] Exception lors du push: $($_.Exception.Message)" -ForegroundColor Red
        }
    } else {
        Write-Host "[$timestamp] Aucun changement detecte (scan #$scanCount)" -ForegroundColor Gray
    }
    
    Start-Sleep 5
}