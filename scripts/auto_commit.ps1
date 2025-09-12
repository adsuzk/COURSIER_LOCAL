# Script PowerShell pour sauvegarder automatiquement vos changements sur GitHub
# Usage: .\auto_commit.ps1 "Message de commit"

param(
    [Parameter(Mandatory=$true)][string]$CommitMessage
)

$projects = @(
    "C:\xampp\htdocs\COURSIER_LOCAL",
    "C:\xampp\htdocs\coursier_prod"
)

foreach ($project in $projects) {
    if (Test-Path $project) {
        Write-Host "Checking $project..." -ForegroundColor Cyan
        Push-Location $project
        
        # Vérifier s'il y a des changements
        $status = git status --porcelain
        if ($status) {
            Write-Host "Changes detected in $(Split-Path -Leaf $project). Committing..." -ForegroundColor Yellow
            git add .
            git commit -m $CommitMessage
            git push
            Write-Host "✅ $(Split-Path -Leaf $project) saved to GitHub" -ForegroundColor Green
        } else {
            Write-Host "No changes in $(Split-Path -Leaf $project)" -ForegroundColor Gray
        }
        
        Pop-Location
    }
}

Write-Host "`n🎉 All changes saved to GitHub!" -ForegroundColor Magenta
