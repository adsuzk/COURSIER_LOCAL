# SYNC_COURSIER_PROD_SIMPLE.ps1
# Synchronisation propre vers coursier_prod 
# Date : 27 Septembre 2025

param([switch]$Verbose, [switch]$Force)

$ErrorActionPreference = "Continue"
$HOST.UI.RawUI.WindowTitle = "Sync Coursier Prod Simple"

Clear-Host
Write-Host "Synchronisation COURSIER_PROD - Structure Propre" -ForegroundColor Cyan
Write-Host "=" * 60

$sourceDir = "C:\xampp\htdocs\COURSIER_LOCAL"
$targetDir = "C:\xampp\htdocs\coursier_prod"

Write-Host "Source: $sourceDir" -ForegroundColor Yellow
Write-Host "Target: $targetDir" -ForegroundColor Yellow
Write-Host ""

# Vérifications
if (-not (Test-Path $sourceDir)) {
    Write-Host "ERREUR: Source introuvable" -ForegroundColor Red
    exit 1
}

if (-not (Test-Path $targetDir)) {
    Write-Host "Creation du repertoire target..." -ForegroundColor Yellow
    New-Item -Path $targetDir -ItemType Directory -Force | Out-Null
}

Write-Host "Demarrage synchronisation avec exclusions..." -ForegroundColor Cyan

# Synchronisation avec robocopy et exclusions
$result = robocopy $sourceDir $targetDir /MIR `
    /XD ".git" "node_modules" "vendor\phpunit" "Tests" "diagnostic_logs" ".vscode" `
    /XF "*.log" "*.tmp" "*.bak" "*debug*" "*test*" "*cli_*" "*check_*" "*restore_*" "*post_deploy*" "*setup_*" "*diagnostic*" "*temp*" "TEST_*" "*smoketest*" `
    /R:1 /W:1 /NFL /NDL /NP /NS /NC

$exitCode = $LASTEXITCODE

Write-Host ""
Write-Host "Resultats de la synchronisation:" -ForegroundColor Cyan

if ($exitCode -lt 8) {
    Write-Host "Synchronisation reussie (Code: $exitCode)" -ForegroundColor Green
} else {
    Write-Host "Erreur synchronisation (Code: $exitCode)" -ForegroundColor Red
}

# Verification post-sync
Write-Host ""
Write-Host "Verification structure propre..." -ForegroundColor Yellow

$testFiles = Get-ChildItem $targetDir -Name "*.php" -ErrorAction SilentlyContinue | Where-Object { 
    $_ -like "*test*" -or $_ -like "*debug*" -or $_ -like "*cli_*" 
}

if ($testFiles) {
    Write-Host "Fichiers problematiques detectes:" -ForegroundColor Yellow
    foreach ($file in $testFiles) {
        Write-Host "  - $file" -ForegroundColor Red
        if ($Force) {
            Remove-Item "$targetDir\$file" -Force -ErrorAction SilentlyContinue
            Write-Host "    Supprime automatiquement" -ForegroundColor Green
        }
    }
} else {
    Write-Host "Structure parfaitement propre !" -ForegroundColor Green
}

Write-Host ""
Write-Host "Synchronisation terminee" -ForegroundColor Green

# Retourner le bon code de sortie
if ($exitCode -lt 8) {
    exit 0  # Succès
} else {
    exit 1  # Échec
}