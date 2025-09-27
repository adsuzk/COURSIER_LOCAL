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
    
    Write-Host "[$timestamp] 🔄 Synchronisation vers coursier_prod..." -ForegroundColor Cyan
    
    # Commande robocopy avec exclusions complètes des fichiers de test/debug
    $robocopyArgs = @(
        "C:\xampp\htdocs\COURSIER_LOCAL",
        "C:\xampp\htdocs\coursier_prod",
        "/MIR",
        "/XD", ".git", "node_modules", "vendor\phpunit", "Tests", "diagnostic_logs", ".vscode",
        "/XF", "*.log", "*.tmp", "*.bak", "*debug*", "*test*", "*cli_*", "*check_*", "*restore_*", "*post_deploy*", "*setup_*", "*diagnostic*", "*temp*", "TEST_*", "*smoketest*", "*_debug.*", "rebuild_*",
        "/R:1", "/W:1", "/NFL", "/NDL", "/NP", "/NS", "/NC"
    )
    
    $syncResult = & robocopy @robocopyArgs 2>&1
    $robocopyExitCode = $LASTEXITCODE
    
    # Codes de sortie Robocopy : 0-7 sont des succès, 8+ sont des erreurs
    if ($robocopyExitCode -lt 8) {
        Write-Host "[$timestamp] ✅ Synchronisation coursier_prod réussie" -ForegroundColor Green
        
        # Vérification que coursier_prod est propre
        $testFiles = Get-ChildItem "$coursierProdPath" -Name "*.php" -ErrorAction SilentlyContinue | Where-Object { 
            $_ -like "*test*" -or $_ -like "*debug*" -or $_ -like "*cli_*" 
        }
        
        if ($testFiles) {
            Write-Host "[$timestamp] ⚠️ Fichiers de test détectés dans coursier_prod !" -ForegroundColor Yellow
            foreach ($file in $testFiles) {
                Remove-Item "$coursierProdPath\$file" -Force -ErrorAction SilentlyContinue
                Write-Host "   🗑️ Supprimé: $file" -ForegroundColor Red
            }
        } else {
            Write-Host "[$timestamp] ✅ Structure coursier_prod PROPRE" -ForegroundColor Green
        }
        
        return $true
    } else {
        Write-Host "[$timestamp] ❌ Erreur synchronisation coursier_prod (Code: $robocopyExitCode)" -ForegroundColor Red
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