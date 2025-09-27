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

if (-not (Test-Path $sourceDir)) {
    Write-Host "ERREUR: Source introuvable" -ForegroundColor Red
    exit 1
}

if (-not (Test-Path $targetDir)) {
    Write-Host "Creation du repertoire target..." -ForegroundColor Yellow
    New-Item -Path $targetDir -ItemType Directory -Force | Out-Null
}

Write-Host "Preparation des exclusions..." -ForegroundColor Yellow

$excludedDirs = @(".git", ".vscode", "node_modules", "Tests", "diagnostic_logs")
$excludedFiles = @("*.log", "*.tmp", "*.bak", "*test*", "*Test*", "*debug*", "*Debug*")

$robocopyArgs = @($sourceDir, $targetDir, "/MIR")

foreach ($dir in $excludedDirs) {
    $robocopyArgs += "/XD"
    $robocopyArgs += $dir
}

foreach ($file in $excludedFiles) {
    $robocopyArgs += "/XF"
    $robocopyArgs += $file
}

$robocopyArgs += @("/R:1", "/W:1", "/MT:4", "/NFL", "/NDL", "/NP")

Write-Host ""
Write-Host "SYNCHRONISATION EN COURS..." -ForegroundColor Cyan

$startTime = Get-Date
$result = & robocopy @robocopyArgs 2>&1
$exitCode = $LASTEXITCODE
$endTime = Get-Date
$duration = $endTime - $startTime

Write-Host "Synchronisation terminee" -ForegroundColor Green

Write-Host ""
Write-Host "Creation dossier Tests..." -ForegroundColor Yellow
$testsDir = Join-Path $targetDir "Tests"
if (-not (Test-Path $testsDir)) {
    New-Item -Path $testsDir -ItemType Directory -Force | Out-Null
}

$testFilesInRoot = Get-ChildItem $targetDir -File | Where-Object { 
    $_.Name -like "*test*" -or $_.Name -like "*debug*"
}

if ($testFilesInRoot.Count -gt 0) {
    foreach ($testFile in $testFilesInRoot) {
        $destPath = Join-Path $testsDir $testFile.Name
        Move-Item $testFile.FullName $destPath -Force -ErrorAction SilentlyContinue
        Write-Host "Deplace: $($testFile.Name)" -ForegroundColor Cyan
    }
}

Write-Host ""
Write-Host "CONFIGURATION LWS..." -ForegroundColor Yellow

$configPath = Join-Path $targetDir "config.php"
if (Test-Path $configPath) {
    $configContent = Get-Content $configPath -Raw
    
    $configContent = $configContent -replace "localhost", "185.98.131.214"
    $configContent = $configContent -replace "coursier_local", "conci2547642_1m4twb"
    $configContent = $configContent -replace 'root', 'conci2547642_1m4twb'
    
    if ($configContent -notmatch "wN1") {
        $configContent = $configContent -replace '""', '"wN1!_TT!yHsK6Y6"'
    }
    
    Set-Content -Path $configPath -Value $configContent -Encoding ASCII
    Write-Host "Config.php configure pour LWS" -ForegroundColor Green
}

$prodMarker = Join-Path $targetDir "ENVIRONMENT_PRODUCTION"
Set-Content -Path $prodMarker -Value "PRODUCTION LWS" -Encoding ASCII

Write-Host ""
Write-Host "========== TERMINE AVEC SUCCES ==========" -ForegroundColor Green
Write-Host "- Fichiers synchronises" -ForegroundColor Cyan
Write-Host "- Configuration LWS appliquee" -ForegroundColor Cyan
Write-Host "- Environnement production pret" -ForegroundColor Cyan

exit 0