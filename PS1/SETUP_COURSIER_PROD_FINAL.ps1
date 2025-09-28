# SETUP GITHUB REPOSITORY COURSIER_PROD - VERSION SECURISEE
# Script pour initialiser le repository GitHub coursier_prod

Write-Host "=== SETUP COURSIER_PROD GITHUB ===" -ForegroundColor Green
Write-Host ""

# Verifier que le dossier coursier_prod existe
$coursierProdPath = "C:\xampp\htdocs\coursier_prod"
if (!(Test-Path $coursierProdPath)) {
    Write-Host "ERREUR: Le dossier coursier_prod n'existe pas encore" -ForegroundColor Red
    Write-Host "Veuillez d'abord executer le script de synchronisation" -ForegroundColor Yellow
    exit 1
}

# Aller dans le dossier coursier_prod
Set-Location $coursierProdPath
Write-Host "Repertoire de travail: $coursierProdPath" -ForegroundColor Yellow

# Configuration Git avec variables d'environnement
$env:GIT_ASKPASS = "echo"
git config --global credential.helper "store --file=C:\Users\manud\.git-credentials"
$gitUrl = "https://$($env:GIT_USERNAME):$($env:GIT_PASSWORD)@github.com/"
git config --global url."$gitUrl".insteadOf "https://github.com/"

Write-Host "=== INITIALISATION GIT ===" -ForegroundColor Cyan
git init
git branch -M main

Write-Host "=== AJOUT DU REMOTE GITHUB ===" -ForegroundColor Cyan
git remote remove origin 2>$null
git remote add origin https://github.com/adsuzk/coursier_prod.git

Write-Host "=== PREMIER COMMIT ===" -ForegroundColor Cyan
git add .
git commit -m "Initial commit - Production sync from COURSIER_LOCAL"

Write-Host "=== PUSH VERS GITHUB ===" -ForegroundColor Cyan
$pushResult = git push -u origin main 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "Repository coursier_prod configure avec succes !" -ForegroundColor Green
    Write-Host "URL: https://github.com/adsuzk/coursier_prod" -ForegroundColor Yellow
} else {
    Write-Host "Erreur lors du push vers GitHub" -ForegroundColor Red
    Write-Host $pushResult -ForegroundColor Red
}

Write-Host ""
Write-Host "=== SETUP TERMINE ===" -ForegroundColor Green