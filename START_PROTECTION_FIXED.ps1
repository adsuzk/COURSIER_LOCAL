# PROTECTION AUTOMATIQUE SUZOSKY - VERSION CORRIGEE
# Script de demarrage de la surveillance automatique

Write-Host "DEMARRAGE DU SYSTEME DE PROTECTION AUTOMATIQUE" -ForegroundColor Green
Write-Host ""
Write-Host "Vos dossiers COURSIER_LOCAL et coursier_prod seront surveilles 24/7" -ForegroundColor Cyan
Write-Host "Toute modification sera automatiquement sauvegardee sur GitHub" -ForegroundColor Yellow
Write-Host "Verification toutes les 30 secondes" -ForegroundColor White
Write-Host ""
Write-Host "IMPORTANT: Gardez cette fenetre ouverte pour maintenir la protection" -ForegroundColor Red
Write-Host ""

# Configuration Git
try {
    $gitUser = git config --global user.name 2>$null
    $gitEmail = git config --global user.email 2>$null
    
    if (-not $gitUser -or -not $gitEmail) {
        Write-Host "Configuration Git..." -ForegroundColor Yellow
        git config --global user.name "adsuzk"
        git config --global user.email "adsuzk@github.com"
        Write-Host "Git configure" -ForegroundColor Green
    }
} catch {
    Write-Host "Erreur de configuration Git" -ForegroundColor Red
}

Write-Host "Demarrage de la surveillance..." -ForegroundColor Green
Write-Host ""

# Demarrage du gardien
$guardianPath = Join-Path $PSScriptRoot "auto_guardian_fixed.ps1"
& $guardianPath
