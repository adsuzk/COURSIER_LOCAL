# TEST_SYNC_SIMPLE.ps1
# Test simple de la synchronisation

Write-Host "Test de la synchronisation COURSIER_PROD..." -ForegroundColor Cyan

# Test du script de synchronisation
Write-Host "1. Test du script SYNC_COURSIER_PROD_SIMPLE.ps1" -ForegroundColor Yellow
& "C:\xampp\htdocs\COURSIER_LOCAL\scripts\SYNC_COURSIER_PROD_SIMPLE.ps1" -Force
$syncCode = $LASTEXITCODE

Write-Host ""
Write-Host "Code de sortie du script de synchronisation: $syncCode" -ForegroundColor Yellow

# Test de la logique de vérification
if ($syncCode -le 3) {
    Write-Host "✅ SUCCÈS - Code $syncCode est considéré comme un succès (≤ 3)" -ForegroundColor Green
    Write-Host "   La synchronisation devrait être acceptée par le script principal." -ForegroundColor Green
} else {
    Write-Host "❌ ÉCHEC - Code $syncCode est considéré comme un échec (> 3)" -ForegroundColor Red
    Write-Host "   La synchronisation sera rejetée par le script principal." -ForegroundColor Red
}

Write-Host ""
Write-Host "🎯 Résumé des codes Robocopy:" -ForegroundColor Cyan
Write-Host "   0 = Aucun fichier copié" -ForegroundColor Green
Write-Host "   1 = Un ou plusieurs fichiers ont été copiés avec succès" -ForegroundColor Green
Write-Host "   2 = Fichiers supplémentaires ou répertoires détectés" -ForegroundColor Green  
Write-Host "   3 = Fichiers copiés + fichiers supplémentaires" -ForegroundColor Green
Write-Host "   4+ = Erreurs" -ForegroundColor Red