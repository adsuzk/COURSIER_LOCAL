param()
$ErrorActionPreference = "Continue"
$HOST.UI.RawUI.WindowTitle = "SUZOSKY Protection GitHub SECURISEE"

Clear-Host
Write-Host "SUZOSKY - PROTECTION AUTOMATIQUE GITHUB SECURISEE" -ForegroundColor Magenta
Write-Host "Repository: https://github.com/adsuzk/COURSIER_LOCAL" -ForegroundColor Cyan
Write-Host "Utilise Git Credential Manager (pas de token expose)" -ForegroundColor Green
Write-Host ""

Set-Location "C:\xampp\htdocs\COURSIER_LOCAL"

# Variables d'environnement pour éviter les popups
$env:GIT_ASKPASS = "echo"
$env:GCM_INTERACTIVE = "never" 
$env:GIT_TERMINAL_PROMPT = "0"

# Configuration Git globale (sans token exposé)
git config --global user.email "suzosky@github.com"
git config --global user.name "Suzosky Protection"
git config --global credential.useHttpPath true
git config --global core.askPass ""
git config --global gui.askpass ""
git config --global credential.modalPrompt false
git config --global credential.guiPrompt false

# Utiliser Git Credential Manager pour l'authentification sécurisée
Write-Host "Configuration: utilisation de Git Credential Manager" -ForegroundColor Yellow

# Test de connexion avec les credentials gérés par GCM
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
Write-Host "SURVEILLANCE ACTIVE - MODE SECURISE" -ForegroundColor Green
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
        if (($scanCount % 5) -eq 0) {
            Write-Host "[$timestamp] Surveillance active scan $scanCount" -ForegroundColor Gray
        } else {
            Write-Host "." -NoNewline -ForegroundColor Gray
        }
    }
    
    Start-Sleep -Seconds 5
}