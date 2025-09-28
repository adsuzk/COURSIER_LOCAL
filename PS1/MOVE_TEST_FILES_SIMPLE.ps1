# MOVE_TEST_FILES_TO_TESTS_FOLDER.ps1
# Script pour déplacer tous les fichiers de test vers le dossier Tests/
# Date : 27 Septembre 2025

Write-Host "🧹 NETTOYAGE DES FICHIERS DE TEST - DEPLACEMENT VERS Tests/" -ForegroundColor Yellow
Write-Host "=" * 60

$baseDir = "C:\xampp\htdocs\COURSIER_LOCAL"
$testsDir = "$baseDir\Tests"

# Créer le dossier Tests s'il n'existe pas
if (-not (Test-Path $testsDir)) {
    New-Item -Path $testsDir -ItemType Directory -Force
    Write-Host "✅ Dossier Tests/ créé" -ForegroundColor Green
}

$movedCount = 0

# Déplacer les fichiers CLI
Write-Host "🔧 Déplacement des fichiers CLI..." -ForegroundColor Cyan
$cliFiles = Get-ChildItem -Path $baseDir -Name "cli_*.php" -ErrorAction SilentlyContinue
foreach ($file in $cliFiles) {
    $sourcePath = Join-Path $baseDir $file
    $destPath = Join-Path $testsDir $file
    
    if ((Test-Path $sourcePath) -and (-not (Test-Path $destPath))) {
        Move-Item -Path $sourcePath -Destination $destPath -Force
        Write-Host "✅ Déplacé: $file" -ForegroundColor Green
        $movedCount++
    }
}

# Déplacer les fichiers spécifiques mentionnés
Write-Host "🧪 Déplacement des fichiers de test spécifiques..." -ForegroundColor Cyan
$specificFiles = @(
    "restore_clients_table_lws.php",
    "check_pre_upload.php", 
    "post_deploy_email.php",
    "check_email_system.php",
    "check_test_accounts.php",
    "assignation_smoketest.php",
    "setup_database.php",
    "configure_xampp_local.php",
    "create_missing_databases.php",
    "create_interface_files.php",
    "create_email_system.php",
    "integrate_email_admin.php",
    "fix_email_paths.php",
    "update_device_tokens_table_lws.php",
    "MEGA_EMAIL_INSTALL.php",
    "auto_create_files.php"
)

foreach ($file in $specificFiles) {
    $sourcePath = Join-Path $baseDir $file
    $destPath = Join-Path $testsDir $file
    
    if ((Test-Path $sourcePath) -and (-not (Test-Path $destPath))) {
        Move-Item -Path $sourcePath -Destination $destPath -Force
        Write-Host "✅ Déplacé: $file" -ForegroundColor Green
        $movedCount++
    }
}

# Déplacer les fichiers de test dans api/
Write-Host "📡 Déplacement des fichiers de test API..." -ForegroundColor Cyan
$apiDir = "$baseDir\api"
if (Test-Path $apiDir) {
    $apiTestFiles = Get-ChildItem -Path $apiDir -Name "*test*.php", "*debug*.php" -ErrorAction SilentlyContinue
    foreach ($file in $apiTestFiles) {
        $sourcePath = "$apiDir\$file"
        $destPath = "$testsDir\api_$file"
        
        if ((Test-Path $sourcePath) -and (-not (Test-Path $destPath))) {
            Move-Item -Path $sourcePath -Destination $destPath -Force
            Write-Host "✅ Déplacé: api/$file → api_$file" -ForegroundColor Green
            $movedCount++
        }
    }
}

# Déplacer les fichiers de test dans admin/
Write-Host "👨‍💼 Déplacement des fichiers de test Admin..." -ForegroundColor Cyan
$adminDir = "$baseDir\admin"
if (Test-Path $adminDir) {
    $adminTestFiles = Get-ChildItem -Path $adminDir -Name "*test*.php", "*debug*.php" -ErrorAction SilentlyContinue
    foreach ($file in $adminTestFiles) {
        $sourcePath = "$adminDir\$file"
        $destPath = "$testsDir\admin_$file"
        
        if ((Test-Path $sourcePath) -and (-not (Test-Path $destPath))) {
            Move-Item -Path $sourcePath -Destination $destPath -Force
            Write-Host "✅ Déplacé: admin/$file → admin_$file" -ForegroundColor Green
            $movedCount++
        }
    }
}

Write-Host "`n" + "=" * 60
Write-Host "📊 RÉSUMÉ DU NETTOYAGE:" -ForegroundColor Cyan
Write-Host "✅ Fichiers déplacés: $movedCount" -ForegroundColor Green

# Lister le contenu du dossier Tests/ 
Write-Host "`n📁 CONTENU DU DOSSIER Tests/:" -ForegroundColor Cyan
$testsContent = Get-ChildItem -Path $testsDir -Name "*.php" -ErrorAction SilentlyContinue | Sort-Object
if ($testsContent) {
    foreach ($file in $testsContent) {
        Write-Host "   ✓ $file" -ForegroundColor Gray
    }
    Write-Host "📄 Total: $($testsContent.Count) fichiers dans Tests/" -ForegroundColor Cyan
} else {
    Write-Host "   (Aucun fichier .php trouvé)" -ForegroundColor Gray
}

Write-Host "`n🎯 NETTOYAGE TERMINÉ!" -ForegroundColor Green
Write-Host "Prêt pour la synchronisation vers coursier_prod!" -ForegroundColor Green