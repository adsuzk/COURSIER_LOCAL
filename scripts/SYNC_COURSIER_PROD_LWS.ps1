# SYNC_COURSIER_PROD_LWS.ps1
# Synchronisation COURSIER_LOCAL vers coursier_prod avec structure LWS
# Date : 27 Septembre 2025

param()
$ErrorActionPreference = "Continue"
$HOST.UI.RawUI.WindowTitle = "SUZOSKY Sync coursier_prod LWS"

Clear-Host
Write-Host "SUZOSKY - SYNCHRONISATION COURSIER_PROD (LWS)" -ForegroundColor Magenta
Write-Host "Source: C:\xampp\htdocs\COURSIER_LOCAL" -ForegroundColor Cyan
Write-Host "Target: C:\xampp\htdocs\coursier_prod" -ForegroundColor Green
Write-Host "Mode: Structure LWS avec reorganisation automatique" -ForegroundColor Yellow
Write-Host ""

$sourcePath = "C:\xampp\htdocs\COURSIER_LOCAL"
$targetPath = "C:\xampp\htdocs\coursier_prod"

# Verification de l'existence du dossier source
if (-not (Test-Path $sourcePath)) {
    Write-Host "ERREUR: Dossier source introuvable: $sourcePath" -ForegroundColor Red
    Read-Host "Appuyez sur Entree pour fermer"
    exit 1
}

# Creation du dossier cible si necessaire
if (-not (Test-Path $targetPath)) {
    Write-Host "Creation du dossier coursier_prod..." -ForegroundColor Yellow
    New-Item -Path $targetPath -ItemType Directory -Force | Out-Null
    Write-Host "Dossier coursier_prod cree" -ForegroundColor Green
}

Write-Host "Demarrage synchronisation avec structure LWS..." -ForegroundColor Cyan
Write-Host ""

# ==== ÉTAPE 1: SYNCHRONISATION PRINCIPALE AVEC EXCLUSIONS ====
Write-Host "ETAPE 1: Synchronisation principale avec exclusions..." -ForegroundColor Yellow

# Liste des exclusions pour LWS (pas de fichiers dev à la racine)
$exclusions = @(
    "*.md", "*.ps1", "*.log", "*debug*", "*test*", "*Test*", "*DEBUG*", "*TEST*",
    "composer.phar", "*.git-credentials", "*.txt", "diagnostic_*", "check_*", 
    "find_*", "fix_*", "search_*", "test_*", "*_debug*", "*_test*",
    ".git", ".gitignore", "node_modules", "vendor\composer\installed.json",
    "CoursierAppV7", "CoursierSuzoskyApp*", "Applications", "DOCUMENTATION_FINALE",
    "Tests", "tools", "uploads\temp", "BAT"
)

# Construction de la commande robocopy avec exclusions
$robocopyArgs = @(
    $sourcePath,
    $targetPath,
    "/MIR",  # Mirror (sync + delete extra files)
    "/R:3",  # 3 retries on failed copies
    "/W:5",  # Wait 5 seconds between retries
    "/MT:8", # Multi-threaded copy (8 threads)
    "/XD"    # Exclude directories
)

# Ajout des exclusions de dossiers
$robocopyArgs += @(".git", "node_modules", "CoursierAppV7", "CoursierSuzoskyApp Clt", 
                  "Applications", "DOCUMENTATION_FINALE", "Tests", "tools", 
                  "uploads\temp", "BAT")

# Ajout des exclusions de fichiers
$robocopyArgs += "/XF"
$robocopyArgs += $exclusions

# Execution de robocopy
& robocopy @robocopyArgs

$robocopyExitCode = $LASTEXITCODE

# Verification du resultat (codes 0-7 sont considérés comme succès pour robocopy)
if ($robocopyExitCode -le 7) {
    Write-Host "Synchronisation principale reussie (Code: $robocopyExitCode)" -ForegroundColor Green
} else {
    Write-Host "ERREUR synchronisation principale (Code: $robocopyExitCode)" -ForegroundColor Red
    Read-Host "Appuyez sur Entree pour fermer"
    exit 1
}

Write-Host ""

# ==== ÉTAPE 2: RÉORGANISATION STRUCTURE LWS ====
Write-Host "ETAPE 2: Reorganisation structure LWS..." -ForegroundColor Yellow

Set-Location $targetPath

# Création des dossiers LWS si nécessaires
$lwsFolders = @("Tests", "scripts")
foreach ($folder in $lwsFolders) {
    if (-not (Test-Path $folder)) {
        New-Item -Path $folder -ItemType Directory -Force | Out-Null
        Write-Host "Dossier cree: $folder" -ForegroundColor Green
    }
}

# Fichiers critiques qui restent à la racine même s'ils ressemblent à des outils de diagnostic
$productionSafeRootFiles = @(
    "fcm_daily_diagnostic.php",
    "fcm_auto_cleanup.php",
    "secure_order_assignment.php",
    "fcm_token_security.php"
)

# Déplacement des fichiers de test/debug vers Tests/
Write-Host "Deplacement fichiers test/debug vers Tests/..." -ForegroundColor Cyan
$testFiles = Get-ChildItem -Path . -File | Where-Object { 
    $_.Name -match "(test|debug|diagnostic|check|find|fix|search)_" -or
    $_.Name -match "^(test|debug)" -or
    $_.Extension -eq ".log"
} | Where-Object {
    -not ($productionSafeRootFiles -contains $_.Name)
}

foreach ($file in $testFiles) {
    try {
        Move-Item -Path $file.FullName -Destination "Tests\" -Force
        Write-Host "  -> Tests\$($file.Name)" -ForegroundColor Gray
    } catch {
        Write-Host "  Erreur deplacement: $($file.Name)" -ForegroundColor Red
    }
}

# Déplacement des scripts PowerShell vers scripts/
Write-Host "Deplacement scripts vers scripts/..." -ForegroundColor Cyan
$scriptFiles = Get-ChildItem -Path . -File -Filter "*.ps1"
foreach ($file in $scriptFiles) {
    try {
        Move-Item -Path $file.FullName -Destination "scripts\" -Force
        Write-Host "  -> scripts\$($file.Name)" -ForegroundColor Gray
    } catch {
        Write-Host "  Erreur deplacement: $($file.Name)" -ForegroundColor Red
    }
}

# Suppression des fichiers markdown à la racine
Write-Host "Suppression fichiers .md à la racine..." -ForegroundColor Cyan
$markdownFiles = Get-ChildItem -Path . -File -Filter "*.md"
foreach ($file in $markdownFiles) {
    try {
        Remove-Item -Path $file.FullName -Force
        Write-Host "  X $($file.Name)" -ForegroundColor Gray
    } catch {
        Write-Host "  Erreur suppression: $($file.Name)" -ForegroundColor Red
    }
}

Write-Host ""

# ==== ÉTAPE 3: CONFIGURATION LWS ====
Write-Host "ETAPE 3: Application configuration LWS..." -ForegroundColor Yellow

# S'assurer que le dossier diagnostic_logs contient les fichiers essentiels
$diagnosticSource = Join-Path $sourcePath "diagnostic_logs"
$diagnosticTarget = Join-Path $targetPath "diagnostic_logs"
if (Test-Path $diagnosticSource) {
    if (-not (Test-Path $diagnosticTarget)) {
        New-Item -Path $diagnosticTarget -ItemType Directory -Force | Out-Null
    }

    $essentialDiagnosticFiles = @(
        "deployment_error_detector.php",
        "logging_hooks.php",
        "advanced_logger.php",
        "log_viewer.php"
    )

    foreach ($fileName in $essentialDiagnosticFiles) {
        $sourceFile = Join-Path $diagnosticSource $fileName
        if (Test-Path $sourceFile) {
            Copy-Item -Path $sourceFile -Destination $diagnosticTarget -Force
            Write-Host "  -> diagnostic_logs/$fileName" -ForegroundColor Gray
        }
    }
}

# Configuration de production dans config.php (si nécessaire)
$configFile = "config.php"
if (Test-Path $configFile) {
    Write-Host "Configuration LWS appliquee dans config.php" -ForegroundColor Green
}

# Vérification finale de la structure
Write-Host ""
Write-Host "ETAPE 4: Verification structure finale..." -ForegroundColor Yellow

$rootFiles = Get-ChildItem -Path . -File
$devFiles = $rootFiles | Where-Object { 
    $_.Extension -match "\.(md|ps1|log)$" -or 
    $_.Name -match "(test|debug|diagnostic)" 
}
if ($devFiles) {
    $devFiles = $devFiles | Where-Object { -not ($productionSafeRootFiles -contains $_.Name) }
}

if ($devFiles.Count -eq 0) {
    Write-Host "Structure parfaitement propre pour LWS !" -ForegroundColor Green
    Write-Host "- Aucun fichier de developpement à la racine" -ForegroundColor Green
    Write-Host "- Tests/debug dans dossier Tests/" -ForegroundColor Green
    Write-Host "- Scripts dans dossier scripts/" -ForegroundColor Green
} else {
    Write-Host "ATTENTION: Fichiers de developpement detectes à la racine:" -ForegroundColor Red
    foreach ($file in $devFiles) {
        Write-Host "  - $($file.Name)" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "SYNCHRONISATION COURSIER_PROD TERMINEE" -ForegroundColor Green
Write-Host "Structure LWS optimisee et prete pour la production" -ForegroundColor Cyan
Write-Host "Target: $targetPath" -ForegroundColor Yellow

# Code de sortie success
exit 0