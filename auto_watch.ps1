# Script de surveillance automatique et commit intelligent
# Place ce script dans le dossier racine et lance-le en arrière-plan

param(
    [int]$IntervalMinutes = 5  # Vérifier toutes les 5 minutes
)

$projects = @(
    "C:\xampp\htdocs\COURSIER_LOCAL",
    "C:\xampp\htdocs\coursier_prod"
)

Write-Host "🔍 Surveillance automatique démarrée (toutes les $IntervalMinutes minutes)" -ForegroundColor Green
Write-Host "Appuyez sur Ctrl+C pour arrêter" -ForegroundColor Yellow

while ($true) {
    foreach ($project in $projects) {
        if (Test-Path $project) {
            Push-Location $project
            
            # Vérifier les changements
            $status = git status --porcelain
            if ($status) {
                $projectName = Split-Path -Leaf $project
                $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
                
                Write-Host "⚡ Changements détectés dans $projectName à $timestamp" -ForegroundColor Yellow
                
                # Afficher les fichiers modifiés
                Write-Host "Fichiers modifiés:" -ForegroundColor Cyan
                $status | ForEach-Object { Write-Host "  $_" -ForegroundColor White }
                
                # Commit automatique avec timestamp
                git add .
                git commit -m "Auto-commit: Changements détectés le $timestamp"
                git push
                
                Write-Host "✅ $projectName sauvegardé automatiquement sur GitHub" -ForegroundColor Green
                Write-Host "---" -ForegroundColor Gray
            }
            
            Pop-Location
        }
    }
    
    Start-Sleep -Seconds ($IntervalMinutes * 60)
}
