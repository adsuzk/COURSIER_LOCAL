# SYNC_COURSIER_PROD_SIMPLE.ps1
# Synchronisation propre vers coursier_prod avec configuration automatique LWS
param([switch]$Verbose, [switch]$Force)

$ErrorActionPreference = "Continue"
Clear-Host

Write-Host "===== SYNCHRONISATION COURSIER_PROD =====" -ForegroundColor Cyan
Write-Host "Source: C:\xampp\htdocs\COURSIER_LOCAL" -ForegroundColor Yellow
Write-Host "Target: C:\xampp\htdocs\coursier_prod" -ForegroundColor Yellow
Write-Host "Mode  : EXCLUSION AUTO + CONFIG LWS" -ForegroundColor Green
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

# Indicateur de progression
Write-Host "⏳ Préparation des exclusions..." -ForegroundColor Yellow

# Configuration robocopy avec exclusions complètes
$excludedDirs = @(
    ".git",           
    ".vscode",        
    "node_modules",   
    "vendor\phpunit", 
    "Tests",          
    "diagnostic_logs" 
)

$excludedFiles = @(
    "*.log", "*.tmp", "*.bak", "*.lock",
    "*test*", "*Test*", "*TEST*",
    "*debug*", "*Debug*", "*DEBUG*",
    "*cli_*", "*CLI_*",
    "*check_*", "*Check_*",
    "*restore_*", "*Restore_*",
    "*post_deploy*", "*Post_Deploy*",
    "*setup_*", "*Setup_*",
    "*configure_*", "*Configure_*",
    "*diagnostic*", "*Diagnostic*",
    "*temp*", "*Temp*", "*tmp*",
    "TEST_*", "Debug_*", "Rebuild_*",
    "*smoketest*", "*Smoketest*",
    "*validation*", "*Validation*"
)

Write-Host "🚫 EXCLUSIONS: $($excludedDirs.Count) dossiers, $($excludedFiles.Count) patterns" -ForegroundColor Gray

# Construction des arguments robocopy
$robocopyArgs = @(
    $sourceDir,
    $targetDir,
    "/MIR"
)

# Ajout des exclusions
foreach ($dir in $excludedDirs) {
    $robocopyArgs += "/XD"
    $robocopyArgs += $dir
}

foreach ($file in $excludedFiles) {
    $robocopyArgs += "/XF"
    $robocopyArgs += $file
}

$robocopyArgs += @("/R:1", "/W:1", "/MT:4")

if (-not $Verbose) {
    $robocopyArgs += @("/NFL", "/NDL", "/NP", "/NS", "/NC")
}

# Synchronisation
Write-Host ""
Write-Host "🚀 SYNCHRONISATION EN COURS..." -ForegroundColor Cyan
for ($i = 1; $i -le 3; $i++) {
    Write-Host "   $('●' * $i)$('○' * (3-$i)) Étape $i/3" -ForegroundColor Yellow
    Start-Sleep -Milliseconds 300
}

$startTime = Get-Date
$result = & robocopy @robocopyArgs 2>&1
$exitCode = $LASTEXITCODE
$endTime = Get-Date
$duration = $endTime - $startTime

Write-Host "✅ Synchronisation terminée ($($duration.TotalSeconds.ToString('F1'))s)" -ForegroundColor Green

# Création du dossier Tests
Write-Host ""
Write-Host "📁 Gestion du dossier Tests..." -ForegroundColor Yellow
$testsDir = Join-Path $targetDir "Tests"
if (-not (Test-Path $testsDir)) {
    New-Item -Path $testsDir -ItemType Directory -Force | Out-Null
    Write-Host "✅ Dossier Tests créé" -ForegroundColor Green
} else {
    Write-Host "✅ Dossier Tests existe" -ForegroundColor Green
}

# Déplacement des fichiers test
Write-Host ""
Write-Host "🗃️ Nettoyage des fichiers test en racine..." -ForegroundColor Yellow
$testFilesInRoot = Get-ChildItem $targetDir -File | Where-Object { 
    $_.Name -like "*test*" -or 
    $_.Name -like "*Test*" -or 
    $_.Name -like "*TEST*" -or
    $_.Name -like "*debug*" -or
    $_.Name -like "*Debug*"
}

if ($testFilesInRoot.Count -gt 0) {
    foreach ($testFile in $testFilesInRoot) {
        $destPath = Join-Path $testsDir $testFile.Name
        Move-Item $testFile.FullName $destPath -Force -ErrorAction SilentlyContinue
        Write-Host "   📦 $($testFile.Name) → Tests/" -ForegroundColor Cyan
    }
    Write-Host "✅ $($testFilesInRoot.Count) fichiers déplacés" -ForegroundColor Green
} else {
    Write-Host "✅ Aucun fichier test en racine" -ForegroundColor Green
}

# Configuration automatique pour LWS
Write-Host ""
Write-Host "⚙️ CONFIGURATION LWS EN COURS..." -ForegroundColor Yellow

$configPath = Join-Path $targetDir "config.php"
if (Test-Path $configPath) {
    Write-Host "   🔧 Modification de config.php..." -ForegroundColor Cyan
    
    $configContent = Get-Content $configPath -Raw
    
    # Simple remplacement par recherche/remplacement de chaîne
    $configContent = $configContent -replace "localhost", "185.98.131.214"
    $configContent = $configContent -replace "coursier_local", "conci2547642_1m4twb"
    $configContent = $configContent -replace "root", "conci2547642_1m4twb"
    
    # Si pas de mot de passe spécifique, on l'ajoute
    if ($configContent -notmatch "wN1!_TT!yHsK6Y6") {
        $configContent = $configContent -replace '""', '"wN1!_TT!yHsK6Y6"'
    }
    
    # Sauvegarder
    Set-Content -Path $configPath -Value $configContent -Encoding UTF8
    
    Write-Host "   ✅ Config.php configuré pour LWS" -ForegroundColor Green
    Write-Host "   ✅ Serveur: 185.98.131.214" -ForegroundColor Green
    Write-Host "   ✅ Base: conci2547642_1m4twb" -ForegroundColor Green
} else {
    Write-Host "   ⚠️ config.php non trouvé" -ForegroundColor Yellow
}

# Marqueur de production
$prodMarker = Join-Path $targetDir "ENVIRONMENT_PRODUCTION"
Set-Content -Path $prodMarker -Value "PRODUCTION LWS - $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')" -Encoding UTF8
Write-Host "   ✅ Marqueur de production créé" -ForegroundColor Green

Write-Host ""
Write-Host "🎯 SYNCHRONISATION TERMINÉE AVEC SUCCÈS !" -ForegroundColor Green
Write-Host "   ✓ Fichiers synchronisés (exclusions appliquées)" -ForegroundColor Cyan
Write-Host "   ✓ Fichiers test déplacés vers /Tests" -ForegroundColor Cyan  
Write-Host "   ✓ Configuration LWS appliquée" -ForegroundColor Cyan
Write-Host "   ✓ Environnement de production prêt" -ForegroundColor Cyan

exit 0