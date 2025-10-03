# SYNC_MD_DOCUMENTATION_FINALE.ps1
# Objectif: Copier automatiquement et en continu tous les fichiers .md du repo COURSIER_LOCAL
#           vers C:\xampp\htdocs\COURSIER_LOCAL\DOCUMENTATION_FINALE, sans en oublier un seul.
#           - Scan initial complet (tous les .md existants)
#           - Surveillance temps réel (création, modification, renommage) avec réactivité ~1 seconde
#           - Réplication de l'arborescence pour éviter les collisions de noms
#           - Ignore la destination elle-même pour éviter les boucles
#           - Journalisation minimale

param()
$ErrorActionPreference = 'Continue'

$Root = 'C:\xampp\htdocs\COURSIER_LOCAL'
$Dest = Join-Path $Root 'DOCUMENTATION_FINALE'
$Log  = Join-Path $Dest 'sync_docs.log'

function Write-Log {
    param([string]$msg)
    $ts = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
    try { Add-Content -LiteralPath $Log -Value "[$ts] $msg" -Encoding UTF8 } catch {}
}

# S'assure que le dossier destination existe
if (-not (Test-Path -LiteralPath $Dest)) { New-Item -ItemType Directory -Path $Dest -Force | Out-Null }
Write-Log "Démarrage synchronisation .md -> $Dest"

# Aide: construit le chemin destination en répliquant l'arborescence relative
function Get-DestinationPath {
    param([string]$sourcePath)
    $rel = $sourcePath.Substring($Root.Length).TrimStart('\\')
    $destPath = Join-Path $Dest $rel
    return $destPath
}

# Copie robuste avec retry (fichiers en cours d'écriture)
function Copy-WithRetry {
    param(
        [string]$src,
        [string]$dst
    )
    $dir = Split-Path -Parent $dst
    if (-not (Test-Path -LiteralPath $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }

    $attempts = 0
    $max = 6 # ~1.8s max
    while ($attempts -lt $max) {
        try {
            Copy-Item -LiteralPath $src -Destination $dst -Force
            return $true
        } catch {
            Start-Sleep -Milliseconds 300
            $attempts++
        }
    }
    return $false
}

# Filtre: ignorer chemin destination et tout ce qui n'est pas .md
function Should-Handle {
    param([string]$path)
    if (-not $path) { return $false }
    $lower = $path.ToLowerInvariant()
    if ($lower.StartsWith($Dest.ToLowerInvariant())) { return $false }
    if (-not $lower.EndsWith('.md')) { return $false }
    if (-not $lower.StartsWith($Root.ToLowerInvariant())) { return $false }
    return $true
}

# Traitement central
function Handle-Path {
    param([string]$path,[string]$reason='event')
    if (-not (Should-Handle $path)) { return }
    $dst = Get-DestinationPath -sourcePath $path
    $ok = Copy-WithRetry -src $path -dst $dst
    if ($ok) { Write-Log "SYNC [$reason] $path -> $dst" } else { Write-Log "ERREUR COPY [$reason] $path" }
}

# Scan initial (sans oublier un seul)
Write-Log 'Scan initial en cours...'
Get-ChildItem -LiteralPath $Root -Recurse -File -Filter *.md | Where-Object { $_.FullName -notlike "$Dest*" } | ForEach-Object {
    Handle-Path -path $_.FullName -reason 'initial'
}
Write-Log 'Scan initial terminé.'

# Surveillance temps réel
$fsw = New-Object System.IO.FileSystemWatcher
$fsw.Path = $Root
$fsw.IncludeSubdirectories = $true
$fsw.Filter = '*.md'
$fsw.NotifyFilter = [IO.NotifyFilters]'FileName, LastWrite, CreationTime'

$created = Register-ObjectEvent -InputObject $fsw -EventName Created -Action {
    $p = $Event.SourceEventArgs.FullPath
    Handle-Path -path $p -reason 'created'
}
$changed = Register-ObjectEvent -InputObject $fsw -EventName Changed -Action {
    $p = $Event.SourceEventArgs.FullPath
    Handle-Path -path $p -reason 'changed'
}
$renamed = Register-ObjectEvent -InputObject $fsw -EventName Renamed -Action {
    $p = $Event.SourceEventArgs.FullPath
    Handle-Path -path $p -reason 'renamed'
}
$fsw.EnableRaisingEvents = $true

Write-Host "Synchronisation .md ACTIVE (Ctrl+C pour arrêter)" -ForegroundColor Green
Write-Log 'Surveillance active.'

# Boucle permanente
try {
    while ($true) { Start-Sleep -Seconds 1 }
} finally {
    Write-Log 'Arrêt de la surveillance.'
    if ($created) { Unregister-Event -SourceIdentifier $created.Name -ErrorAction SilentlyContinue }
    if ($changed) { Unregister-Event -SourceIdentifier $changed.Name -ErrorAction SilentlyContinue }
    if ($renamed) { Unregister-Event -SourceIdentifier $renamed.Name -ErrorAction SilentlyContinue }
    $fsw.EnableRaisingEvents = $false
    $fsw.Dispose()
}