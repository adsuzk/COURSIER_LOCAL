# PROTECTION_GITHUB_AVEC_SYNC_PROPRE.ps1
# Protection GitHub avec synchronisation automatique vers coursier_prod PROPRE
# Date : 27 Septembre 2025

param()
$ErrorActionPreference = "Continue"
$HOST.UI.RawUI.WindowTitle = "SUZOSKY Protection + Sync Propre"

Clear-Host
Write-Host "🛡️ SUZOSKY - PROTECTION + SYNCHRONISATION PROPRE" -ForegroundColor Magenta
Write-Host "Repository: https://github.com/adsuzk/COURSIER_LOCAL" -ForegroundColor Cyan
Write-Host "Sync vers: C:\xampp\htdocs\coursier_prod (STRUCTURE PROPRE)" -ForegroundColor Green
Write-Host "Utilise Git Credential Manager (sécurisé)" -ForegroundColor Green
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

Write-Host "🔧 Configuration: Git Credential Manager actif" -ForegroundColor Yellow

# Test de connexion GitHub
Write-Host "🔗 Test de connexion GitHub..." -ForegroundColor Yellow
$testResult = git ls-remote origin HEAD 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Connexion GitHub réussie !" -ForegroundColor Green
} else {
    Write-Host "❌ Erreur de connexion GitHub !" -ForegroundColor Red
    Write-Host "Détails: $testResult" -ForegroundColor Red
    Write-Host "Veuillez configurer Git Credential Manager" -ForegroundColor Yellow
    Read-Host "Appuyez sur Entrée pour fermer"
    exit 1
}

# Vérification de l'existence du dossier coursier_prod
$coursierProdPath = "C:\xampp\htdocs\coursier_prod"
if (-not (Test-Path $coursierProdPath)) {
    Write-Host "📁 Création du dossier coursier_prod..." -ForegroundColor Yellow
    New-Item -Path $coursierProdPath -ItemType Directory -Force | Out-Null
    Write-Host "✅ Dossier coursier_prod créé" -ForegroundColor Green
}

Write-Host ""
Write-Host "🔄 SURVEILLANCE ACTIVE - MODE SÉCURISÉ AVEC SYNC PROPRE" -ForegroundColor Green
Write-Host "- Protection GitHub automatique toutes les 5 secondes" -ForegroundColor Cyan
Write-Host "- Synchronisation coursier_prod (exclu tests/debug)" -ForegroundColor Cyan
Write-Host "- Structure de production toujours propre" -ForegroundColor Cyan
Write-Host ""
Write-Host "Appuyez sur Ctrl+C pour arrêter" -ForegroundColor Yellow
Write-Host ""

$scanCount = 0
$lastSyncTime = Get-Date

# Fonction de synchronisation propre vers coursier_prod
function Sync-ToCoursierProd {
    param($timestamp)
    
    Write-Host "[$timestamp] Synchronisation vers coursier_prod..." -ForegroundColor Cyan
    
    # Utiliser le script de synchronisation simple
    $syncResult = & "$PSScriptRoot\SYNC_COURSIER_PROD_SIMPLE.ps1" -Force
    $syncExitCode = $LASTEXITCODE
    
    if ($syncExitCode -eq 0) {
        Write-Host "[$timestamp] Synchronisation coursier_prod reussie" -ForegroundColor Green
        return $true
    } else {
        Write-Host "[$timestamp] Erreur synchronisation coursier_prod" -ForegroundColor Red
        return $false
    }
}

while ($true) {
    $scanCount++
    $timestamp = Get-Date -Format "HH:mm:ss"
    $currentTime = Get-Date
    
    # Synchronisation vers coursier_prod toutes les 60 secondes (ou si changements)
    $timeSinceLastSync = ($currentTime - $lastSyncTime).TotalSeconds
    $shouldSync = $timeSinceLastSync -gt 60
    
    $status = git status --porcelain 2>&1
    if ($status -and $status -notlike "*fatal*" -and $status -notlike "*error*") {
        Write-Host "[$timestamp] 💾 Sauvegarde changements..." -ForegroundColor Cyan
        
        git add . 2>&1 | Out-Null
        
        $commitMsg = "Auto-backup $timestamp scan $scanCount"
        git commit -m $commitMsg 2>&1 | Out-Null
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "[$timestamp] ⬆️ Push vers GitHub..." -ForegroundColor Yellow
            $pushResult = git push origin main 2>&1
            
            if ($LASTEXITCODE -eq 0) {
                Write-Host "[$timestamp] ✅ Sauvegarde GitHub terminée" -ForegroundColor Green
                
                # Synchronisation automatique après chaque commit réussi
                Sync-ToCoursierProd $timestamp | Out-Null
                $lastSyncTime = Get-Date
                $shouldSync = $false
                
            } else {
                Write-Host "[$timestamp] ❌ Erreur push GitHub" -ForegroundColor Red
                Write-Host "Détails: $pushResult" -ForegroundColor Red
            }
        } else {
            Write-Host "[$timestamp] ❌ Erreur lors du commit" -ForegroundColor Red
        }
    } else {
        # Synchronisation périodique même sans changements
        if ($shouldSync) {
            Sync-ToCoursierProd $timestamp | Out-Null
            $lastSyncTime = Get-Date
        }
        
        # Affichage minimal pour ne pas encombrer
        if (($scanCount % 12) -eq 0) {
            Write-Host "[$timestamp] 🔍 Surveillance active scan $scanCount - coursier_prod sync OK" -ForegroundColor Gray
        } else {
            Write-Host "." -NoNewline -ForegroundColor Gray
        }
    }
    
    Start-Sleep -Seconds 5
}