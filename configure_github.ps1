# Configuration GitHub pour COURSIER_LOCAL
# Ce script configure l'accès GitHub avec le token

# Configuration de l'authentification Git
Write-Host "Configuration de l'authentification GitHub..." -ForegroundColor Green

# Le token est déjà configuré via GitHub CLI
$token = "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"

# Configuration de Git pour utiliser GitHub CLI
git config --global credential.helper ""
git config --global credential.helper "!gh auth git-credential"

# Test de l'accès
Write-Host "Test de l'accès au dépôt..." -ForegroundColor Yellow
try {
    gh repo view adsuzk/COURSIER_LOCAL
    Write-Host "✓ Accès GitHub configuré avec succès !" -ForegroundColor Green
} catch {
    Write-Host "✗ Erreur d'accès GitHub" -ForegroundColor Red
}

Write-Host "Configuration terminée. L'IA peut maintenant accéder à votre dépôt GitHub." -ForegroundColor Cyan
