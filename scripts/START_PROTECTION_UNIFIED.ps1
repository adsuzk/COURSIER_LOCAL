# SCRIPT DE PROTECTION AUTOMATIQUE SUZOSKY - VERSION UNIFIÉE
# Token testé et fonctionnel: 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

param()
$ErrorActionPreference = "Continue"
$HOST.UI.RawUI.WindowTitle = "SUZOSKY - Protection GitHub Auto - UNIFIÉE"

Clear-Host
Write-Host "================================================================" -ForegroundColor Magenta
Write-Host "    SUZOSKY - PROTECTION AUTOMATIQUE GITHUB - VERSION UNIFIÉE" -ForegroundColor Magenta  
Write-Host "================================================================" -ForegroundColor Magenta
Write-Host "Repository: https://github.com/adsuzk/COURSIER_LOCAL" -ForegroundColor Cyan
Write-Host "Token utilisé: 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 (testé OK)" -ForegroundColor Green
Write-Host "Fréquence: 5 secondes" -ForegroundColor Cyan
Write-Host ""

# Configuration du répertoire de travail
Set-Location "C:\xampp\htdocs\COURSIER_LOCAL"

# Variables d'environnement pour éviter les popups Git
$env:GIT_ASKPASS = "echo"
$env:GCM_INTERACTIVE = "never" 
$env:GIT_TERMINAL_PROMPT = "0"

# Token testé et fonctionnel
$WORKING_TOKEN = "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"
$GIT_USERNAME = "adsuzk"

# Configuration Git globale
git config --global user.email "suzosky@github.com"
git config --global user.name "Suzosky Protection"
git config --global credential.useHttpPath true
git config --global core.askPass ""
git config --global gui.askpass ""
git config --global credential.modalPrompt false
git config --global credential.guiPrompt false
git config --global http.sslVerify false

# Fichier credentials avec le bon token
$credFile = "$env:USERPROFILE\.git-credentials"
$tokenLine = "https://${GIT_USERNAME}:${WORKING_TOKEN}@github.com"
Set-Content -Path $credFile -Value $tokenLine -Encoding UTF8 -Force

# Configuration helper credentials
git config --global credential.helper "store --file=$credFile"

# URL insteadOf pour forcer l'utilisation du token
git config --global url."https://${GIT_USERNAME}:${WORKING_TOKEN}@github.com/".insteadOf "https://github.com/"

# Forcer l'URL remote avec le bon token
git remote set-url origin "https://${GIT_USERNAME}:${WORKING_TOKEN}@github.com/adsuzk/COURSIER_LOCAL.git"

# Test de connexion
Write-Host "Test de connexion GitHub..." -ForegroundColor Yellow
$testResult = git ls-remote origin HEAD 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Connexion GitHub réussie !" -ForegroundColor Green
    Write-Host "Dernier commit: $($testResult.Split()[0].Substring(0,7))" -ForegroundColor Green
} else {
    Write-Host "✗ Erreur de connexion GitHub !" -ForegroundColor Red
    Write-Host "Détails: $testResult" -ForegroundColor Red
    Write-Host "Script arrêté." -ForegroundColor Red
    Read-Host "Appuyez sur Entrée pour fermer"
    exit 1
}

Write-Host ""
Write-Host "=== SURVEILLANCE ACTIVE ===" -ForegroundColor Green
Write-Host "Appuyez sur Ctrl+C pour arrêter" -ForegroundColor Yellow
Write-Host ""

$scanCount = 0
$lastCommitTime = Get-Date

while ($true) {
    $scanCount++
    $timestamp = Get-Date -Format "HH:mm:ss"
    
    # Vérifier les changements
    $status = git status --porcelain 2>&1
    if ($status -and $status -notlike "*fatal*" -and $status -notlike "*error*") {
        Write-Host "[$timestamp] Changements détectés, sauvegarde..." -ForegroundColor Cyan
        
        # Ajout des fichiers
        git add . 2>&1 | Out-Null
        
        # Commit avec timestamp détaillé
        $commitMsg = "Auto-backup $timestamp (scan #$scanCount)"
        git commit -m "$commitMsg" 2>&1 | Out-Null
        
        if ($LASTEXITCODE -eq 0) {
            # Push avec gestion d'erreur améliorée
            Write-Host "[$timestamp] Push vers GitHub..." -ForegroundColor Yellow
            $pushResult = git push origin main 2>&1
            
            if ($LASTEXITCODE -eq 0) {
                Write-Host "[$timestamp] ✓ Sauvegarde GitHub terminée" -ForegroundColor Green
                $lastCommitTime = Get-Date
            } else {
                Write-Host "[$timestamp] ✗ Erreur push GitHub" -ForegroundColor Red
                Write-Host "Détails: $pushResult" -ForegroundColor Red
                
                # Test de connectivité pour diagnostiquer
                $diagResult = git ls-remote origin HEAD 2>&1
                if ($LASTEXITCODE -eq 0) {
                    Write-Host "Auth OK - Erreur temporaire de push" -ForegroundColor Yellow
                } else {
                    Write-Host "Problème d'authentification détecté !" -ForegroundColor Red
                    Write-Host "Redémarrage recommandé du script." -ForegroundColor Yellow
                }
            }
        } else {
            Write-Host "[$timestamp] ✗ Erreur lors du commit" -ForegroundColor Red
        }
    } else {
        # Pas de changements
        $dots = "." * (($scanCount % 10) + 1)
        Write-Host "[$timestamp] Surveillance$dots (scan #$scanCount)" -ForegroundColor Gray
        
        # Test périodique de connexion (toutes les 20 scans)
        if (($scanCount % 20) -eq 0) {
            $quickTest = git ls-remote origin HEAD 2>&1
            if ($LASTEXITCODE -ne 0) {
                Write-Host "[$timestamp] ⚠ Problème de connexion GitHub détecté" -ForegroundColor Yellow
            }
        }
    }
    
    Start-Sleep -Seconds 5
}