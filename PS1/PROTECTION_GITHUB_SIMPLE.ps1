# PROTECTION_GITHUB_SIMPLE.ps1
# Protection GitHub automatique pour COURSIER_LOCAL UNIQUEMENT
# Date : 27 Septembre 2025

param()
$ErrorActionPreference = "Continue"
$HOST.UI.RawUI.WindowTitle = "SUZOSKY Protection GitHub Simple"

Clear-Host
Write-Host "SUZOSKY - PROTECTION GITHUB AUTOMATIQUE" -ForegroundColor Magenta
Write-Host "Repository: https://github.com/adsuzk/COURSIER_LOCAL" -ForegroundColor Cyan
Write-Host "Mode: Protection GitHub uniquement (pas de sync coursier_prod)" -ForegroundColor Yellow
Write-Host "Utilise Git Credential Manager (securise)" -ForegroundColor Green
Write-Host ""

Set-Location "C:\xampp\htdocs\COURSIER_LOCAL"

# Variables d'environnement pour eviter les popups
$env:GIT_ASKPASS = "echo"
$env:GCM_INTERACTIVE = "never"
$env:GIT_TERMINAL_PROMPT = "0"

# Configuration Git globale (sans token expose)
git config --global user.email "suzosky@github.com"
git config --global user.name "Suzosky Protection"
git config --global credential.useHttpPath true
git config --global core.askPass ""
git config --global gui.askpass ""
git config --global credential.modalPrompt false
git config --global credential.guiPrompt false

Write-Host "Configuration: Git Credential Manager actif" -ForegroundColor Yellow

# Test de connexion GitHub
Write-Host "Test de connexion GitHub..." -ForegroundColor Yellow
$testResult = git ls-remote origin HEAD 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "Connexion GitHub reussie !" -ForegroundColor Green
} else {
    Write-Host "Erreur de connexion GitHub !" -ForegroundColor Red
    Write-Host "Details: $testResult" -ForegroundColor Red
    Write-Host "Veuillez configurer Git Credential Manager" -ForegroundColor Yellow
    Read-Host "Appuyez sur Entree pour fermer"
    exit 1
}

Write-Host ""
Write-Host "PROTECTION GITHUB ACTIVE - MODE SIMPLE" -ForegroundColor Green
Write-Host "- Sauvegarde GitHub automatique toutes les 5 secondes" -ForegroundColor Cyan
Write-Host "- Commits automatiques avec timestamps" -ForegroundColor Cyan
Write-Host "- Protection continue du code source" -ForegroundColor Cyan
Write-Host ""
Write-Host "Appuyez sur Ctrl+C pour arreter" -ForegroundColor Yellow
Write-Host ""

$scanCount = 0

while ($true) {
    $scanCount++
    $timestamp = Get-Date -Format "HH:mm:ss"
    
    $status = git status --porcelain 2>&1
    if ($status -and $status -notlike "*fatal*" -and $status -notlike "*error*") {
        Write-Host "[$timestamp] Sauvegarde changements..." -ForegroundColor Cyan
        
        git add . 2>&1 | Out-Null
        
        $commitMsg = "Auto-backup $timestamp scan $scanCount"
        git commit -m $commitMsg 2>&1 | Out-Null
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "[$timestamp] Push vers GitHub..." -ForegroundColor Yellow
            $pushResult = git push origin main 2>&1
            
            if ($LASTEXITCODE -eq 0) {
                Write-Host "[$timestamp] Sauvegarde GitHub terminee" -ForegroundColor Green
            } else {
                Write-Host "[$timestamp] Erreur push GitHub" -ForegroundColor Red
                Write-Host "Details: $pushResult" -ForegroundColor Red
            }
        } else {
            Write-Host "[$timestamp] Erreur lors du commit" -ForegroundColor Red
        }
    } else {
        # Affichage minimal pour ne pas encombrer
        if (($scanCount % 12) -eq 0) {
            Write-Host "[$timestamp] Protection active scan $scanCount - GitHub OK" -ForegroundColor Gray
        } else {
            Write-Host "." -NoNewline -ForegroundColor Gray
        }
    }
    
    Start-Sleep -Seconds 5
}