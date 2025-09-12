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
Write-Host "`n=== Ajustements post-synchronisation pour prod ===" -ForegroundColor Yellow
# Renommer dossier avec espace pour compatibilité serveur
$oldSections = Join-Path $prodPath 'sections index'
$newSections = Join-Path $prodPath 'sections_index'
# Renommer ou supprimer ancien dossier avec espace pour compatibilité serveur
if (Test-Path $oldSections) {
    if (Test-Path $newSections) {
        Write-Host "Suppression de l'ancien dossier 'sections index' (sections_index existe déjà)" -ForegroundColor Yellow
        Remove-Item -Path $oldSections -Recurse -Force
    } else {
        Write-Host "Renommage: 'sections index' -> 'sections_index'" -ForegroundColor Cyan
        Rename-Item -Path $oldSections -NewName 'sections_index'
    }
}
# Mettre à jour les chemins d'inclusion dans index.php
$indexFile = Join-Path $prodPath 'index.php'
if (Test-Path $indexFile) {
    (Get-Content $indexFile) |
        ForEach-Object { $_ -replace 'sections index/', 'sections_index/' } |
        Set-Content $indexFile
    Write-Host "Mise à jour des includes dans index.php" -ForegroundColor Cyan
}

# Adapter les chemins JS dans connexion_modal.js pour prod
$jsFile = Join-Path $prodPath 'assets\js\connexion_modal.js'
if (Test-Path $jsFile) {
        (Get-Content $jsFile) |
            ForEach-Object { $_ -replace '/COURSIER_LOCAL/sections index/', 'sections_index/' } |
            ForEach-Object { $_ -replace '/COURSIER_LOCAL/api/', 'api/' } |
            Set-Content $jsFile
        Write-Host "Adaptation des chemins dans connexion_modal.js" -ForegroundColor Cyan
}

        # Ajouter <base> dynamic pour chemins relatifs
        $headerFile = Join-Path $newSections 'header.php'
        if (Test-Path $headerFile) {
                (Get-Content $headerFile) |
                    ForEach-Object {
                        if ($_ -match '<head>') {
                                $_
                                '<?php echo "<base href=\"" . rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/") . "/\">"; ?>'
                        } else {
                                $_
                        }
                    } | Set-Content $headerFile
                Write-Host "Ajout de <base> dans header.php" -ForegroundColor Cyan
        }

Write-Host ""
Write-Host "✅ SYNCHRONISATION TERMINÉE - coursier_prod mis à jour avec COURSIER_LOCAL" -ForegroundColor Green
