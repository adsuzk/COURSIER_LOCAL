# TEST_SYNC_FUNCTION.ps1
# Test de la fonction de synchronisation

# Importer la fonction du script principal
$mainScript = Get-Content "C:\xampp\htdocs\COURSIER_LOCAL\scripts\PROTECTION_GITHUB_AVEC_SYNC_PROPRE.ps1" -Raw

# Extraire et définir la fonction Sync-ToCoursierProd
$functionStart = $mainScript.IndexOf("function Sync-ToCoursierProd")
$functionEnd = $mainScript.IndexOf("}", $functionStart) + 1
$functionCode = $mainScript.Substring($functionStart, $functionEnd - $functionStart)

# Exécuter la fonction
Invoke-Expression $functionCode

# Tester la fonction
$timestamp = Get-Date -Format "HH:mm:ss"
Write-Host "Test de la fonction de synchronisation..." -ForegroundColor Yellow

$result = Sync-ToCoursierProd $timestamp
Write-Host "Résultat de la fonction: $result" -ForegroundColor $(if ($result) { "Green" } else { "Red" })

if ($result) {
    Write-Host "✅ La fonction retourne TRUE - Synchronisation considérée comme réussie" -ForegroundColor Green
} else {
    Write-Host "❌ La fonction retourne FALSE - Synchronisation considérée comme échouée" -ForegroundColor Red
}