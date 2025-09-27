# SYNC_COURSIER_PROD_PROPRE.ps1
# Synchronisation propre vers coursier_prod avec exclusions automatiques
# Date : 27 Septembre 2025

param(
    [switch]$Verbose,
    [switch]$Force
)

$ErrorActionPreference = "Continue"
$HOST.UI.RawUI.WindowTitle = "Sync Coursier Prod - Structure Propre"

Clear-Host
Write-Host "🔄 SYNCHRONISATION COURSIER_PROD - STRUCTURE PROPRE" -ForegroundColor Cyan
Write-Host "=" * 60
Write-Host "Source: C:\xampp\htdocs\COURSIER_LOCAL" -ForegroundColor Yellow
Write-Host "Target: C:\xampp\htdocs\coursier_prod" -ForegroundColor Yellow
Write-Host "Mode  : EXCLUSION AUTOMATIQUE des fichiers test/debug" -ForegroundColor Green
Write-Host ""

$sourceDir = "C:\xampp\htdocs\COURSIER_LOCAL"
$targetDir = "C:\xampp\htdocs\coursier_prod"

# Vérification des répertoires
if (-not (Test-Path $sourceDir)) {
    Write-Host "❌ ERREUR: Répertoire source introuvable: $sourceDir" -ForegroundColor Red
    exit 1
}

if (-not (Test-Path $targetDir)) {
    Write-Host "📁 Création du répertoire target: $targetDir" -ForegroundColor Yellow
    New-Item -Path $targetDir -ItemType Directory -Force | Out-Null
}

# Configuration robocopy avec exclusions complètes
$excludedDirs = @(
    ".git",           # Git repository
    ".vscode",        # VS Code settings
    "node_modules",   # Node.js dependencies
    "vendor\phpunit", # PHPUnit testing
    "Tests",          # Dossier de tests
    "diagnostic_logs" # Logs de diagnostic
)

$excludedFiles = @(
    # Logs et temporaires
    "*.log", "*.tmp", "*.bak", "*.lock",
    
    # Fichiers de test
    "*test*", "*Test*", "*TEST*",
    
    # Fichiers de debug
    "*debug*", "*Debug*", "*DEBUG*",
    
    # Fichiers CLI
    "*cli_*", "*CLI_*",
    
    # Fichiers de vérification
    "*check_*", "*Check_*",
    
    # Fichiers de restauration/déploiement
    "*restore_*", "*Restore_*",
    "*post_deploy*", "*Post_Deploy*",
    
    # Fichiers de configuration/setup
    "*setup_*", "*Setup_*",
    "*configure_*", "*Configure_*",
    
    # Fichiers de diagnostic
    "*diagnostic*", "*Diagnostic*",
    
    # Fichiers temporaires
    "*temp*", "*Temp*", "*tmp*",
    "TEST_*", "Debug_*", "Rebuild_*",
    
    # Fichiers smoketest/validation
    "*smoketest*", "*Smoketest*",
    "*validation*", "*Validation*"
)

Write-Host "🚫 EXCLUSIONS APPLIQUÉES:" -ForegroundColor Yellow
Write-Host "   Dossiers exclus: $($excludedDirs -join ', ')" -ForegroundColor Gray
Write-Host "   Patterns exclus: $($excludedFiles.Count) patterns de fichiers" -ForegroundColor Gray

if ($Verbose) {
    Write-Host "   Détail patterns fichiers:" -ForegroundColor Gray
    foreach ($pattern in $excludedFiles) {
        Write-Host "     - $pattern" -ForegroundColor DarkGray
    }
}

Write-Host ""

# Construction des arguments robocopy
$robocopyArgs = @(
    $sourceDir,
    $targetDir,
    "/MIR"  # Mirror : synchronisation exacte avec suppression
)

# Ajout des exclusions de dossiers
foreach ($dir in $excludedDirs) {
    $robocopyArgs += "/XD"
    $robocopyArgs += $dir
}

# Ajout des exclusions de fichiers
foreach ($file in $excludedFiles) {
    $robocopyArgs += "/XF"
    $robocopyArgs += $file
}

# Options robocopy
$robocopyArgs += @(
    "/R:1",    # 1 retry seulement
    "/W:1",    # 1 seconde entre les retries
    "/MT:4"    # Multi-thread pour la performance
)

# Options d'affichage selon le mode verbose
if (-not $Verbose) {
    $robocopyArgs += @("/NFL", "/NDL", "/NP", "/NS", "/NC")
}

Write-Host "🚀 DÉMARRAGE DE LA SYNCHRONISATION..." -ForegroundColor Cyan
$startTime = Get-Date

# Exécution robocopy
Write-Host "Commande: robocopy $sourceDir $targetDir [avec exclusions]" -ForegroundColor Gray
$result = & robocopy @robocopyArgs 2>&1
$exitCode = $LASTEXITCODE
$endTime = Get-Date
$duration = $endTime - $startTime

Write-Host ""
Write-Host "📊 RÉSULTATS DE LA SYNCHRONISATION:" -ForegroundColor Cyan

# Interprétation des codes de sortie robocopy
switch ($exitCode) {
    0 { Write-Host "✅ Aucun fichier copié - Déjà synchronisé" -ForegroundColor Green }
    1 { Write-Host "✅ Fichiers copiés avec succès" -ForegroundColor Green }
    2 { Write-Host "✅ Dossiers supplémentaires ou fichiers supprimés" -ForegroundColor Green }
    3 { Write-Host "✅ Fichiers copiés et dossiers créés" -ForegroundColor Green }
    4 { Write-Host "⚠️ Quelques fichiers mal placés ou renommés" -ForegroundColor Yellow }
    5 { Write-Host "⚠️ Fichiers copiés + quelques problèmes mineurs" -ForegroundColor Yellow }
    6 { Write-Host "⚠️ Dossiers et fichiers supplémentaires + problèmes mineurs" -ForegroundColor Yellow }
    7 { Write-Host "⚠️ Fichiers copiés, créés et problèmes mineurs" -ForegroundColor Yellow }
    { $_ -ge 8 } { 
        Write-Host "❌ Erreurs critiques (Code: $exitCode)" -ForegroundColor Red
        if ($Verbose) {
            Write-Host "Détails de l'erreur:" -ForegroundColor Red
            Write-Host $result -ForegroundColor DarkRed
        }
    }
}

Write-Host "⏱️ Durée: $($duration.TotalSeconds.ToString('F2')) secondes" -ForegroundColor Gray

# Vérification post-synchronisation
Write-Host ""
Write-Host "🔍 VÉRIFICATION POST-SYNCHRONISATION..." -ForegroundColor Yellow

$problematicFiles = @()
$testPatterns = @("*test*", "*debug*", "*cli_*", "*check_*", "*restore_*")

foreach ($pattern in $testPatterns) {
    $foundFiles = Get-ChildItem $targetDir -Name $pattern -Recurse -ErrorAction SilentlyContinue | 
                  Where-Object { $_ -notlike "*vendor*" -and $_ -notlike "*node_modules*" -and $_ -notlike "*CoursierApp*" }
    
    if ($foundFiles) {
        $problematicFiles += $foundFiles
    }
}

if ($problematicFiles.Count -gt 0) {
    Write-Host "⚠️ FICHIERS PROBLÉMATIQUES DÉTECTÉS:" -ForegroundColor Yellow
    foreach ($file in $problematicFiles) {
        Write-Host "   🗑️ $file" -ForegroundColor Red
        
        if ($Force) {
            $fullPath = Join-Path $targetDir $file
            Remove-Item $fullPath -Force -ErrorAction SilentlyContinue
            Write-Host "   ✅ Supprimé automatiquement" -ForegroundColor Green
        }
    }
    
    if (-not $Force) {
        Write-Host ""
        Write-Host "💡 Utilisez -Force pour supprimer automatiquement ces fichiers" -ForegroundColor Cyan
    }
} else {
    Write-Host "✅ STRUCTURE PARFAITEMENT PROPRE !" -ForegroundColor Green
    Write-Host "   Aucun fichier de test/debug détecté dans coursier_prod" -ForegroundColor Green
}

# Vérification de la documentation consolidée
$docFile = Join-Path $targetDir "DOCUMENTATION_FINALE\DOCUMENTATION_COMPLETE_SUZOSKY_COURSIER.md"
if (Test-Path $docFile) {
    Write-Host "✅ Documentation consolidée présente" -ForegroundColor Green
} else {
    Write-Host "⚠️ Documentation consolidée manquante" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "🎯 SYNCHRONISATION TERMINÉE" -ForegroundColor Green
Write-Host "Structure coursier_prod optimisée pour la production" -ForegroundColor Cyan

if ($exitCode -lt 8) {
    exit 0
} else {
    exit 1
}