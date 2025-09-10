# LANCEUR AUTOMATIQUE - Double-cliquez sur ce fichier pour démarrer la protection
# Ce script démarre la surveillance automatique en arrière-plan

Write-Host "🚀 DÉMARRAGE DU SYSTÈME DE PROTECTION AUTOMATIQUE" -ForegroundColor Green
Write-Host ""
Write-Host "🛡️  Vos dossiers COURSIER_LOCAL et coursier_prod seront surveillés 24/7" -ForegroundColor Cyan
Write-Host "💾 Toute modification sera automatiquement sauvegardée sur GitHub" -ForegroundColor Yellow
Write-Host "🔄 Vérification toutes les 30 secondes" -ForegroundColor White
Write-Host ""
Write-Host "⚠️  IMPORTANT: Gardez cette fenêtre ouverte pour maintenir la protection" -ForegroundColor Red
Write-Host ""

# Vérification que Git est configuré
try {
    $gitUser = git config --global user.name 2>$null
    $gitEmail = git config --global user.email 2>$null
    
    if (-not $gitUser -or -not $gitEmail) {
        Write-Host "🔧 Configuration Git..." -ForegroundColor Yellow
        git config --global user.name "adsuzk"
        git config --global user.email "adsuzk@github.com"
        Write-Host "✅ Git configuré" -ForegroundColor Green
    }
} catch {
    Write-Host "❌ Erreur de configuration Git" -ForegroundColor Red
}

Write-Host "🚀 Démarrage de la surveillance..." -ForegroundColor Green
Write-Host ""

# Démarrage du gardien automatique
& "$PSScriptRoot\auto_guardian.ps1"
