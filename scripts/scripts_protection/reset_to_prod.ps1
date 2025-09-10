# reset_to_prod.ps1
# RÉINITIALISATION: Remplace COURSIER_LOCAL par coursier_prod quand différent

$prodPath = "C:\xampp\htdocs\coursier_prod"
$localPath = "C:\xampp\htdocs\COURSIER_LOCAL"

if (!(Test-Path $prodPath)) {
    Write-Error "Le dossier coursier_prod n'existe pas : $prodPath"
    exit 1
}

if (!(Test-Path $localPath)) {
    Write-Error "Le dossier COURSIER_LOCAL n'existe pas : $localPath"
    exit 1
}

# Fonction pour calculer le hash d'un fichier
function Get-FileHashMD5 {
    param([string]$FilePath)
    try {
        $hash = Get-FileHash -Path $FilePath -Algorithm MD5
        return $hash.Hash
    }
    catch {
        return $null
    }
}

# Fonction récursive pour réinitialiser
function Reset-Directory {
    param(
        [string]$SourceDir,
        [string]$TargetDir
    )
    
    $items = Get-ChildItem -Path $SourceDir -Force
    
    foreach ($item in $items) {
        $sourcePath = $item.FullName
        $targetPath = Join-Path $TargetDir $item.Name
        
        if ($item.PSIsContainer) {
            # C'est un dossier
            if (!(Test-Path $targetPath)) {
                Write-Host "Création du dossier: $targetPath" -ForegroundColor Yellow
                New-Item -ItemType Directory -Path $targetPath -Force | Out-Null
            }
            Reset-Directory -SourceDir $sourcePath -TargetDir $targetPath
        }
        else {
            # C'est un fichier
            $shouldReplace = $false
            $reason = ""
            
            if (!(Test-Path $targetPath)) {
                $shouldReplace = $true
                $reason = "Fichier absent dans LOCAL"
            }
            else {
                $sourceHash = Get-FileHashMD5 $sourcePath
                $targetHash = Get-FileHashMD5 $targetPath
                
                if ($sourceHash -ne $targetHash) {
                    $shouldReplace = $true
                    $reason = "Fichier différent - REMPLACEMENT par PROD"
                }
            }
            
            if ($shouldReplace) {
                Write-Host "$reason : $targetPath" -ForegroundColor Red
                
                # REMPLACEMENT DIRECT - PAS DE SAUVEGARDE
                try {
                    Copy-Item $sourcePath $targetPath -Force
                    Write-Host "  REMPLACÉ: $sourcePath -> $targetPath" -ForegroundColor Green
                }
                catch {
                    Write-Error "  Échec remplacement: $($_.Exception.Message)"
                }
            }
        }
    }
}

Write-Host "=== RÉINITIALISATION: coursier_prod -> COURSIER_LOCAL ===" -ForegroundColor Red
Write-Host "ATTENTION: Remplacement définitif sans sauvegarde!" -ForegroundColor Red
Write-Host "Source: $prodPath" -ForegroundColor Gray
Write-Host "Cible: $localPath" -ForegroundColor Gray
Write-Host ""

Reset-Directory -SourceDir $prodPath -TargetDir $localPath

Write-Host ""
Write-Host "RÉINITIALISATION TERMINÉE - COURSIER_LOCAL = coursier_prod" -ForegroundColor Red
