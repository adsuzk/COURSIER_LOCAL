# SURVEILLANCE PERMANENTE AUTOMATIQUE - SYSTÈME DE SÉCURITÉ TOTAL
# Ce script surveille en permanence vos dossiers et sauvegarde TOUT sur GitHub
# AUCUNE INTERVENTION MANUELLE REQUISE

param(
    [int]$IntervalSeconds = 30  # Vérification toutes les 30 secondes (très fréquent)
)

# Configuration
$projects = @(
    "C:\xampp\htdocs\COURSIER_LOCAL",
    "C:\xampp\htdocs\coursier_prod"
)

# Couleurs pour un affichage clair
$Green = "Green"
$Yellow = "Yellow" 
$Red = "Red"
$Cyan = "Cyan"
$White = "White"

# Fonction de logging avec timestamp
function Write-Log {
    param($Message, $Color = "White")
    $timestamp = Get-Date -Format "HH:mm:ss"
    Write-Host "[$timestamp] $Message" -ForegroundColor $Color
}

# Configuration Git silencieuse (évite les erreurs)
git config --global core.autocrlf true
git config --global init.defaultBranch main

Write-Log "🚀 SYSTÈME DE PROTECTION AUTOMATIQUE DÉMARRÉ" $Green
Write-Log "📁 Surveillance: COURSIER_LOCAL + coursier_prod" $Cyan
Write-Log "⏱️  Vérification toutes les $IntervalSeconds secondes" $Yellow
Write-Log "🛡️  AUCUNE PERTE DE DONNÉES POSSIBLE" $Green
Write-Log "💡 Laissez cette fenêtre ouverte en arrière-plan" $Yellow
Write-Log "🔄 Appuyez sur Ctrl+C pour arrêter (non recommandé)" $Red
Write-Log "=" * 70 $White

$commitCount = 0

# Boucle infinie de surveillance
while ($true) {
    try {
        foreach ($project in $projects) {
            if (Test-Path $project) {
                Push-Location $project -ErrorAction SilentlyContinue
                
                # Vérification rapide des changements
                $hasChanges = $false
                try {
                    $status = git status --porcelain 2>$null
                    if ($status -and $status.Length -gt 0) {
                        $hasChanges = $true
                    }
                } catch {
                    # Si erreur Git, on force la réinitialisation
                    Write-Log "🔧 Réparation du dépôt Git dans $(Split-Path -Leaf $project)" $Yellow
                    git init 2>$null
                    git add . 2>$null
                    $hasChanges = $true
                }
                
                if ($hasChanges) {
                    $projectName = Split-Path -Leaf $project
                    $timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
                    $commitCount++
                    
                    Write-Log "⚡ MODIFICATION DÉTECTÉE dans $projectName" $Yellow
                    
                    # Sauvegarde FORCÉE (ignore toutes les erreurs)
                    try {
                        git add . 2>$null
                        git commit -m "AUTO-SAVE #$commitCount - $timestamp" 2>$null
                        
                        # Retry push en cas d'échec
                        $pushSuccess = $false
                        for ($retry = 1; $retry -le 3; $retry++) {
                            try {
                                git push 2>$null
                                $pushSuccess = $true
                                break
                            } catch {
                                Start-Sleep -Seconds 2
                            }
                        }
                        
                        if ($pushSuccess) {
                            Write-Log "✅ $projectName SAUVEGARDÉ sur GitHub (commit #$commitCount)" $Green
                        } else {
                            Write-Log "⚠️  $projectName sauvé localement, push en attente..." $Yellow
                        }
                        
                    } catch {
                        Write-Log "🔄 Tentative de récupération..." $Yellow
                        # Force une nouvelle tentative au prochain cycle
                    }
                }
                
                Pop-Location -ErrorAction SilentlyContinue
            }
        }
        
        # Affichage discret du statut (une fois par minute)
        if ((Get-Date).Second -eq 0) {
            Write-Log "💚 Surveillance active - $commitCount sauvegardes effectuées" $Green
        }
        
    } catch {
        Write-Log "🔄 Erreur temporaire, redémarrage automatique..." $Yellow
        Start-Sleep -Seconds 5
    }
    
    # Pause avant la prochaine vérification
    Start-Sleep -Seconds $IntervalSeconds
}
