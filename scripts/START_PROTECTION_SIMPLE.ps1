param()
$ErrorActionPreference = "Continue"
$HOST.UI.RawUI.WindowTitle = "SUZOSKY Protection GitHub"

Clear-Host
Write-Host "SUZOSKY - PROTECTION AUTOMATIQUE GITHUB" -ForegroundColor Magenta
Write-Host "Repository: https://github.com/adsuzk/COURSIER_LOCAL" -ForegroundColor Cyan
Write-Host ""

Set-Location "C:\xampp\htdocs\COURSIER_LOCAL"

$env:GIT_ASKPASS = "echo"
$env:GCM_INTERACTIVE = "never" 
$env:GIT_TERMINAL_PROMPT = "0"

$WORKING_TOKEN = "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"
$GIT_USERNAME = "adsuzk"

git config --global user.email "suzosky@github.com"
git config --global user.name "Suzosky Protection"
git config --global credential.useHttpPath true
git config --global core.askPass ""
git config --global gui.askpass ""
git config --global credential.modalPrompt false
git config --global credential.guiPrompt false
git config --global http.sslVerify false

$credFile = "$env:USERPROFILE\.git-credentials"
$tokenLine = "https://$GIT_USERNAME`:$WORKING_TOKEN@github.com"
Set-Content -Path $credFile -Value $tokenLine -Encoding UTF8 -Force

git config --global credential.helper "store --file=$credFile"

$originUrl = "https://$GIT_USERNAME`:$WORKING_TOKEN@github.com/adsuzk/COURSIER_LOCAL.git"
git remote set-url origin $originUrl

Write-Host "Test de connexion GitHub..." -ForegroundColor Yellow
$testResult = git ls-remote origin HEAD 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "Connexion GitHub reussie !" -ForegroundColor Green
} else {
    Write-Host "Erreur de connexion GitHub !" -ForegroundColor Red
    Write-Host "Details: $testResult" -ForegroundColor Red
    Read-Host "Appuyez sur Entree pour fermer"
    exit 1
}

Write-Host ""
Write-Host "SURVEILLANCE ACTIVE" -ForegroundColor Green
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
        Write-Host "[$timestamp] Surveillance actif scan $scanCount" -ForegroundColor Gray
    }
    
    Start-Sleep -Seconds 5
}