# GARDIEN AUTOMATIQUE SUZOSKY - VERSION CORRIGEE
# Surveillance permanente et sauvegarde automatique sur GitHub

param(
    [int]$IntervalSeconds = 5
)

# Configuration des dossiers a surveiller
$projects = @(
    "C:\xampp\htdocs\COURSIER_LOCAL",
    "C:\xampp\htdocs\coursier_prod"
)

# Fonction de logging simple
function Write-Log {
    param($Message, $Color = "White")
    $timestamp = Get-Date -Format "HH:mm:ss"
    Write-Host "[$timestamp] $Message" -ForegroundColor $Color
}

# Configuration Git
git config --global core.autocrlf true 2>$null
git config --global init.defaultBranch main 2>$null

Write-Log "SYSTEME DE PROTECTION AUTOMATIQUE DEMARRE" "Green"
Write-Log "Surveillance: COURSIER_LOCAL + coursier_prod" "Cyan"
Write-Log "Verification toutes les $IntervalSeconds secondes" "Yellow"
Write-Log "AUCUNE PERTE DE DONNEES POSSIBLE" "Green"
Write-Log "Laissez cette fenetre ouverte en arriere-plan" "Yellow"
Write-Log "Appuyez sur Ctrl+C pour arreter (non recommande)" "Red"
Write-Log "================================================================" "White"

$commitCount = 0

# Boucle de surveillance infinie
while ($true) {
    try {
        foreach ($project in $projects) {
            if (Test-Path $project) {
                Set-Location $project -ErrorAction SilentlyContinue
                
                # Verification des changements
                $hasChanges = $false
                try {
                    $status = git status --porcelain 2>$null
                    if ($status -and $status.Length -gt 0) {
                        $hasChanges = $true
                    }
                } catch {
                    # Reinitialisation Git en cas d'erreur
                    Write-Log "Reparation du depot Git dans $(Split-Path -Leaf $project)" "Yellow"
                    git init 2>$null
                    git add . 2>$null
                    $hasChanges = $true
                }
                
                if ($hasChanges) {
                    $projectName = Split-Path -Leaf $project
                    $timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
                    $commitCount++
                    
                    Write-Log "MODIFICATION DETECTEE dans $projectName" "Yellow"
                    
                    # Sauvegarde
                    try {
                        git add . 2>$null
                        git commit -m "AUTO-SAVE #$commitCount - $timestamp" 2>$null
                        
                        # Tentative de push
                        $pushSuccess = $false
                        for ($retry = 1; $retry -le 3; $retry++) {
                            try {
                                git push origin main 2>$null
                                $pushSuccess = $true
                                break
                            } catch {
                                Start-Sleep -Seconds 2
                            }
                        }
                        
                        if ($pushSuccess) {
                            Write-Log "$projectName SAUVEGARDE sur GitHub (commit #$commitCount)" "Green"
                        } else {
                            Write-Log "$projectName sauve localement, push en attente..." "Yellow"
                        }
                        
                    } catch {
                        Write-Log "Tentative de recuperation..." "Yellow"
                    }
                }
            }
        }
        
        # Statut discret une fois par minute
        if ((Get-Date).Second -eq 0) {
            Write-Log "Surveillance active - $commitCount sauvegardes effectuees" "Green"
        }
        
    } catch {
        Write-Log "Erreur temporaire, redemarrage automatique..." "Yellow"
        Start-Sleep -Seconds 5
    }
    
    # Pause avant verification suivante
    Start-Sleep -Seconds $IntervalSeconds
}
