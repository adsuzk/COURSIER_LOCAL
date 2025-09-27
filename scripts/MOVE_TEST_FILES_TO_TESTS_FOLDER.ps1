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

# Liste des fichiers à déplacer depuis la racine
$filesToMove = @(
    # Fichiers CLI (Command Line Interface) - Tests
    "cli_*.php",
    
    # Fichiers de test explicites  
    "*test*.php",
    
    # Fichiers de vérification/debug
    "check_pre_upload.php",
    "check_email_system.php", 
    "check_test_accounts.php",
    
    # Fichiers de restauration/déploiement (tests)
    "restore_clients_table_lws.php",
    "post_deploy_email.php",
    
    # Fichiers de diagnostic
    "assignation_smoketest.php",
    
    # Fichiers de setup/configuration temporaires
    "setup_database.php",
    "configure_xampp_local.php",
    "create_missing_databases.php",
    "create_interface_files.php",
    "create_email_system.php",
    "integrate_email_admin.php",
    "fix_email_paths.php",
    "update_device_tokens_table_lws.php",
    
    # Autres fichiers utilitaires de développement
    "MEGA_EMAIL_INSTALL.php",
    "auto_create_files.php"
)

$movedCount = 0
$skippedCount = 0

foreach ($pattern in $filesToMove) {
    $files = Get-ChildItem -Path $baseDir -Name $pattern -ErrorAction SilentlyContinue
    
    foreach ($file in $files) {
        $sourcePath = Join-Path $baseDir $file
        $destPath = Join-Path $testsDir $file
        
        if (Test-Path $sourcePath) {
            try {
                # Vérifier si le fichier existe déjà dans Tests/
                if (Test-Path $destPath) {
                    Write-Host "⚠️  $file existe déjà dans Tests/ - ignoré" -ForegroundColor Yellow
                    $skippedCount++
                } else {
                    Move-Item -Path $sourcePath -Destination $destPath -Force
                    Write-Host "✅ Déplacé: $file → Tests/" -ForegroundColor Green
                    $movedCount++
                }
            } catch {
                Write-Host "❌ Erreur lors du déplacement de $file : $($_.Exception.Message)" -ForegroundColor Red
            }
        }
    }
}

# Déplacer aussi les fichiers de test dans api/ et admin/
$apiTestFiles = Get-ChildItem -Path "$baseDir\api" -Name "*test*.php", "*debug*.php" -ErrorAction SilentlyContinue
foreach ($file in $apiTestFiles) {
    $sourcePath = "$baseDir\api\$file"
    $destPath = "$testsDir\api_$file"
    
    if (Test-Path $sourcePath) {
        try {
            if (-not (Test-Path $destPath)) {
                Move-Item -Path $sourcePath -Destination $destPath -Force
                Write-Host "✅ Déplacé: api/$file → Tests/api_$file" -ForegroundColor Green
                $movedCount++
            } else {
                Write-Host "⚠️  api/$file existe déjà - ignoré" -ForegroundColor Yellow
                $skippedCount++
            }
        } catch {
            Write-Host "❌ Erreur: api/$file : $($_.Exception.Message)" -ForegroundColor Red
        }
    }
}

$adminTestFiles = Get-ChildItem -Path "$baseDir\admin" -Name "*test*.php", "*debug*.php" -ErrorAction SilentlyContinue
foreach ($file in $adminTestFiles) {
    $sourcePath = "$baseDir\admin\$file" 
    $destPath = "$testsDir\admin_$file"
    
    if (Test-Path $sourcePath) {
        try {
            if (-not (Test-Path $destPath)) {
                Move-Item -Path $sourcePath -Destination $destPath -Force
                Write-Host "✅ Déplacé: admin/$file → Tests/admin_$file" -ForegroundColor Green
                $movedCount++
            } else {
                Write-Host "⚠️  admin/$file existe déjà - ignoré" -ForegroundColor Yellow
                $skippedCount++
            }
        } catch {
            Write-Host "❌ Erreur: admin/$file : $($_.Exception.Message)" -ForegroundColor Red
        }
    }
}

Write-Host "`n" + "=" * 60
Write-Host "📊 RÉSUMÉ DU NETTOYAGE:" -ForegroundColor Cyan
Write-Host "✅ Fichiers déplacés: $movedCount" -ForegroundColor Green
Write-Host "⚠️  Fichiers ignorés: $skippedCount" -ForegroundColor Yellow

# Vérifier les fichiers restants à la racine qui pourraient être des tests
Write-Host "`n🔍 VÉRIFICATION DES FICHIERS RESTANTS..." -ForegroundColor Cyan
$remainingTests = Get-ChildItem -Path $baseDir -Name "*.php" | Where-Object { 
    $_ -like "*test*" -or 
    $_ -like "*debug*" -or 
    $_ -like "*cli_*" -or
    $_ -like "*check_*" -or
    $_ -like "*setup_*" -or
    $_ -like "*restore_*" -or
    $_ -like "*post_deploy*"
}

if ($remainingTests.Count -gt 0) {
    Write-Host "⚠️  Fichiers potentiellement de test restants à la racine:" -ForegroundColor Yellow
    foreach ($file in $remainingTests) {
        Write-Host "   - $file" -ForegroundColor Yellow
    }
} else {
    Write-Host "✅ Aucun fichier de test détecté à la racine" -ForegroundColor Green
}

# Lister le contenu du dossier Tests/ pour confirmation
Write-Host "`n📁 CONTENU DU DOSSIER Tests/:" -ForegroundColor Cyan
$testsContent = Get-ChildItem -Path $testsDir -Name "*.php" | Sort-Object
if ($testsContent.Count -gt 0) {
    foreach ($file in $testsContent) {
        Write-Host "   ✓ $file" -ForegroundColor Gray
    }
    Write-Host "📄 Total: $($testsContent.Count) fichiers dans Tests/" -ForegroundColor Cyan
} else {
    Write-Host "   (Aucun fichier .php trouvé)" -ForegroundColor Gray
}

Write-Host "`n🎯 NETTOYAGE TERMINÉ!" -ForegroundColor Green
Write-Host "Maintenant tous les fichiers de test sont dans le dossier Tests/" -ForegroundColor Green
Write-Host "Prêt pour la synchronisation vers coursier_prod sans fichiers de test à la racine!" -ForegroundColor Green