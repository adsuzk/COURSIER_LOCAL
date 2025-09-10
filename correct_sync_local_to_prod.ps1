# correct_sync_local_to_prod.ps1
# CORRECT: Remplace coursier_prod par COURSIER_LOCAL quand différent

$localPath = "C:\xampp\htdocs\COURSIER_LOCAL"
$prodPath = "C:\xampp\htdocs\coursier_prod"

if (!(Test-Path $localPath)) {
    Write-Error "Le dossier COURSIER_LOCAL n'existe pas : $localPath"
    exit 1
}

if (!(Test-Path $prodPath)) {
    Write-Error "Le dossier coursier_prod n'existe pas : $prodPath"
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

# Fonction récursive pour synchroniser LOCAL vers PROD
function Sync-LocalToProd {
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
                Write-Host "Création du dossier PROD: $targetPath" -ForegroundColor Yellow
                New-Item -ItemType Directory -Path $targetPath -Force | Out-Null
            }
            Sync-LocalToProd -SourceDir $sourcePath -TargetDir $targetPath
        }
        else {
            # C'est un fichier
            $shouldReplace = $false
            $reason = ""
            
            if (!(Test-Path $targetPath)) {
                $shouldReplace = $true
                $reason = "Nouveau fichier pour PROD"
            }
            else {
                $sourceHash = Get-FileHashMD5 $sourcePath
                $targetHash = Get-FileHashMD5 $targetPath
                
                if ($sourceHash -ne $targetHash) {
                    $shouldReplace = $true
                    $reason = "MISE À JOUR PROD avec LOCAL"
                }
            }
            
            if ($shouldReplace) {
                Write-Host "$reason : $targetPath" -ForegroundColor Cyan
                
                # REMPLACEMENT: LOCAL -> PROD
                try {
                    Copy-Item $sourcePath $targetPath -Force
                    Write-Host "  ✅ PROD MIS À JOUR: $sourcePath -> $targetPath" -ForegroundColor Green
                }
                catch {
                    Write-Error "  ❌ Échec: $($_.Exception.Message)"
                }
            }
        }
    }
}

Write-Host "=== SYNCHRONISATION CORRECTE: COURSIER_LOCAL -> coursier_prod ===" -ForegroundColor Green
Write-Host "Source (LOCAL): $localPath" -ForegroundColor Cyan
Write-Host "Cible (PROD): $prodPath" -ForegroundColor Magenta
Write-Host ""

Sync-LocalToProd -SourceDir $localPath -TargetDir $prodPath

Write-Host ""
Write-Host "✅ SYNCHRONISATION TERMINÉE - coursier_prod mis à jour avec COURSIER_LOCAL" -ForegroundColor Green
