param()

$ErrorActionPreference = "SilentlyContinue"
$HOST.UI.RawUI.WindowTitle = "SUZOSKY - Protection GitHub Auto"

Write-Host "=== PROTECTION AUTOMATIQUE SUZOSKY ===" -ForegroundColor Magenta
Write-Host "Repository: https://github.com/adsuzk/COURSIER_LOCAL" -ForegroundColor Cyan
Write-Host "Frequence: 5 secondes" -ForegroundColor Cyan
Write-Host ""

# Configuration Git ULTRA AGRESSIVE pour JAMAIS demander de token
$env:GIT_ASKPASS = "echo"
$env:GIT_USERNAME = "adsuzk"  
$env:GIT_PASSWORD = "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"
$env:GCM_INTERACTIVE = "never"
$env:GIT_TERMINAL_PROMPT = "0"

git config --global user.email "suzosky@github.com"
git config --global user.name "Suzosky Protection"
git config --global credential.helper ""
git config --global credential.useHttpPath true
git config --global core.askPass ""
git config --global gui.askpass ""
git config --global credential.modalPrompt false
git config --global credential.guiPrompt false

# Configuration token GitHub - FORCE BRUTALE
$credFile = "$env:USERPROFILE\.git-credentials" 
$tokenLine = "https://adsuzk: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@github.com"

# EFFACER TOUT ET RÉÉCRIRE
Set-Content -Path $credFile -Value $tokenLine -Encoding UTF8 -Force

# Configurations supplémentaires anti-popup
git config --global url."https://adsuzk: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@github.com/".insteadOf "https://github.com/"
git config --global url."https://adsuzk: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@github.com/".insteadOf "git@github.com:"
git config --global http.sslVerify false
git config --global credential.helper "store --file=$credFile"

# Initialisation repo ULTRA SÉCURISÉE avec token intégré
Set-Location "C:\xampp\htdocs\COURSIER_LOCAL"
if (!(Test-Path ".git")) {
    git init
    git remote add origin "https://adsuzk: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@github.com/adsuzk/COURSIER_LOCAL.git"
}

# FORCER l'URL avec token - PAS DE NÉGOCIATION
git remote set-url origin "https://adsuzk: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@github.com/adsuzk/COURSIER_LOCAL.git"

# Test de connexion silencieux
git ls-remote origin > $null 2>&1

Write-Host "=== SURVEILLANCE ACTIVE ===" -ForegroundColor Green  
Write-Host "Appuyez sur Ctrl+C pour arreter" -ForegroundColor Yellow
Write-Host ""

$count = 0

while ($true) {
    $count++
    $time = Get-Date -Format "HH:mm:ss"
    
    $status = git status --porcelain
    if ($status) {
        Write-Host "[$time] Sauvegarde changements..." -ForegroundColor Cyan
        
        git add .
        git commit -m "Auto-backup $time (scan #$count)"
        
        # Push avec credentials intégrés pour éviter les popups
        $env:GIT_ASKPASS = "echo"
        $env:GIT_USERNAME = "adsuzk"
        $env:GIT_PASSWORD = "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"
        
        git push -u origin main
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "[$time] ✓ Sauvegarde reussie" -ForegroundColor Green
        } else {
            git branch -M main
            
            # Push avec credentials pour la création de branche
            $env:GIT_ASKPASS = "echo"
            $env:GIT_USERNAME = "adsuzk"
            $env:GIT_PASSWORD = "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"
            
            git push -u origin main
            Write-Host "[$time] ✓ Sauvegarde avec branche main" -ForegroundColor Green
        }
    } else {
        Write-Host "." -NoNewline -ForegroundColor Gray
    }
    
    Start-Sleep -Seconds 5
}