# sync_prod_to_local.ps1
# Synchronise les fichiers de coursier_prod vers COURSIER_LOCAL si ils sont différents

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

# Fonction récursive pour synchroniser
function Sync-Directory {
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
            Sync-Directory -SourceDir $sourcePath -TargetDir $targetPath
        }
        else {
            # C'est un fichier
            $shouldCopy = $false
            $reason = ""
            
            if (!(Test-Path $targetPath)) {
                $shouldCopy = $true
                $reason = "Fichier absent"
            }
            else {
                $sourceHash = Get-FileHashMD5 $sourcePath
                $targetHash = Get-FileHashMD5 $targetPath
                
                if ($sourceHash -ne $targetHash) {
                    $shouldCopy = $true
                    $reason = "Fichier modifié"
                }
            }
            
            if ($shouldCopy) {
                Write-Host "$reason : $targetPath" -ForegroundColor Cyan
                
                # Sauvegarde si le fichier existe
                if (Test-Path $targetPath) {
                    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
                    $backupPath = "$targetPath.bak_$timestamp"
                    try {
                        Copy-Item $targetPath $backupPath
                        Write-Host "  Sauvegarde: $backupPath" -ForegroundColor Green
                    }
                    catch {
                        Write-Warning "  Échec sauvegarde: $($_.Exception.Message)"
                    }
                }
                
                # Copie du fichier
                try {
                    Copy-Item $sourcePath $targetPath -Force
                    Write-Host "  Copié: $sourcePath -> $targetPath" -ForegroundColor Green
                }
                catch {
                    Write-Error "  Échec copie: $($_.Exception.Message)"
                }
            }
        }
    }
}

Write-Host "=== Synchronisation coursier_prod -> COURSIER_LOCAL ===" -ForegroundColor Magenta
Write-Host "Source: $prodPath" -ForegroundColor Gray
Write-Host "Cible: $localPath" -ForegroundColor Gray
Write-Host ""

Sync-Directory -SourceDir $prodPath -TargetDir $localPath

Write-Host ""
Write-Host "Synchronisation terminée." -ForegroundColor Magenta
