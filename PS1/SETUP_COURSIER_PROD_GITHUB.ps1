# SUZOSKY - Configuration GitHub pour coursier_prod
# Ce script configure le repository coursier_prod avec le bon token

param()

$ErrorActionPreference = "SilentlyContinue"
$TOKEN = "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"
$PROD_PATH = "C:\xampp\htdocs\coursier_prod"

Write-Host "=== CONFIGURATION GITHUB POUR COURSIER_PROD ===" -ForegroundColor Magenta
Write-Host "Token: $TOKEN" -ForegroundColor Cyan
Write-Host "Chemin: $PROD_PATH" -ForegroundColor Cyan
Write-Host ""

# Vérifier que le dossier existe
if (!(Test-Path $PROD_PATH)) {
    Write-Host "ERREUR: Le dossier coursier_prod n'existe pas encore" -ForegroundColor Red
    Write-Host "Lancez d'abord la synchronisation SYNC_PROD_LWS.bat" -ForegroundColor Yellow
    exit 1
}

# Aller dans le dossier coursier_prod
Set-Location $PROD_PATH

# Initialiser Git si nécessaire
if (!(Test-Path ".git")) {
    Write-Host "Initialisation du repository Git..." -ForegroundColor Yellow
    git init
    git branch -M main
}

# Configurer le remote avec le token
Write-Host "Configuration du remote origin..." -ForegroundColor Yellow
git remote remove origin 2>$null
git remote add origin "https://adsuzk:${TOKEN}@github.com/adsuzk/coursier_prod.git"

# Configurer Git localement
git config user.email "suzosky@github.com"
git config user.name "Suzosky Production"

# Ajouter tous les fichiers
Write-Host "Ajout des fichiers..." -ForegroundColor Yellow
git add .

# Premier commit
Write-Host "Premier commit..." -ForegroundColor Yellow
git commit -m "Initial commit - Production sync from COURSIER_LOCAL"

# Push vers GitHub
Write-Host "Push vers GitHub..." -ForegroundColor Yellow
$env:GIT_ASKPASS = "echo"
$env:GIT_USERNAME = "adsuzk"
$env:GIT_PASSWORD = $TOKEN

git push -u origin main

if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Repository coursier_prod configuré avec succès!" -ForegroundColor Green
} else {
    Write-Host "✗ Erreur lors du push" -ForegroundColor Red
}

Write-Host ""
Write-Host "Repository coursier_prod prêt pour la synchronisation automatique!" -ForegroundColor Green