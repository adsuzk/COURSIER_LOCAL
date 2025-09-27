# TEST_VALIDATION_BAT.ps1
# Test de validation des deux scripts BAT Suzosky
# Date : 27 Septembre 2025

Write-Host "=== VALIDATION DES SCRIPTS BAT SUZOSKY ===" -ForegroundColor Magenta
Write-Host ""

# Test 1: Vérification existence des fichiers BAT
Write-Host "TEST 1: Verification existence fichiers BAT..." -ForegroundColor Yellow
$bat1 = "C:\xampp\htdocs\COURSIER_LOCAL\BAT\PROTECTION_GITHUB.bat"
$bat2 = "C:\xampp\htdocs\COURSIER_LOCAL\BAT\SYNC_COURSIER_PROD.bat"

if (Test-Path $bat1) {
    Write-Host "  ✅ PROTECTION_GITHUB.bat existe" -ForegroundColor Green
} else {
    Write-Host "  ❌ PROTECTION_GITHUB.bat manquant" -ForegroundColor Red
}

if (Test-Path $bat2) {
    Write-Host "  ✅ SYNC_COURSIER_PROD.bat existe" -ForegroundColor Green
} else {
    Write-Host "  ❌ SYNC_COURSIER_PROD.bat manquant" -ForegroundColor Red
}

# Test 2: Vérification existence des scripts PowerShell associés
Write-Host "`nTEST 2: Verification scripts PowerShell associes..." -ForegroundColor Yellow
$ps1_1 = "C:\xampp\htdocs\COURSIER_LOCAL\scripts\PROTECTION_GITHUB_SIMPLE.ps1"
$ps1_2 = "C:\xampp\htdocs\COURSIER_LOCAL\scripts\SYNC_COURSIER_PROD_LWS.ps1"

if (Test-Path $ps1_1) {
    Write-Host "  ✅ PROTECTION_GITHUB_SIMPLE.ps1 existe" -ForegroundColor Green
} else {
    Write-Host "  ❌ PROTECTION_GITHUB_SIMPLE.ps1 manquant" -ForegroundColor Red
}

if (Test-Path $ps1_2) {
    Write-Host "  ✅ SYNC_COURSIER_PROD_LWS.ps1 existe" -ForegroundColor Green
} else {
    Write-Host "  ❌ SYNC_COURSIER_PROD_LWS.ps1 manquant" -ForegroundColor Red
}

# Test 3: Vérification structure coursier_prod
Write-Host "`nTEST 3: Verification structure coursier_prod..." -ForegroundColor Yellow
$coursier_prod = "C:\xampp\htdocs\coursier_prod"

if (Test-Path $coursier_prod) {
    Write-Host "  ✅ Dossier coursier_prod existe" -ForegroundColor Green
    
    # Vérification absence fichiers dev à la racine
    $devFiles = Get-ChildItem $coursier_prod -File | Where-Object { 
        $_.Extension -match "\.(md|ps1|log)$" -or 
        $_.Name -match "(test|debug|diagnostic)" 
    }
    
    if ($devFiles.Count -eq 0) {
        Write-Host "  ✅ Aucun fichier de developpement à la racine" -ForegroundColor Green
    } else {
        Write-Host "  ❌ Fichiers de developpement detectes à la racine:" -ForegroundColor Red
        foreach ($file in $devFiles) {
            Write-Host "    - $($file.Name)" -ForegroundColor Red
        }
    }
    
    # Vérification dossiers LWS
    if (Test-Path "$coursier_prod\Tests") {
        Write-Host "  ✅ Dossier Tests/ existe" -ForegroundColor Green
    } else {
        Write-Host "  ❌ Dossier Tests/ manquant" -ForegroundColor Red
    }
    
    if (Test-Path "$coursier_prod\scripts") {
        Write-Host "  ✅ Dossier scripts/ existe" -ForegroundColor Green
    } else {
        Write-Host "  ❌ Dossier scripts/ manquant" -ForegroundColor Red
    }
} else {
    Write-Host "  ❌ Dossier coursier_prod manquant" -ForegroundColor Red
}

# Test 4: Vérification documentation
Write-Host "`nTEST 4: Verification documentation..." -ForegroundColor Yellow
$doc = "C:\xampp\htdocs\COURSIER_LOCAL\DOCUMENTATION_BAT_SUZOSKY.md"
$readme = "C:\xampp\htdocs\COURSIER_LOCAL\BAT\README.md"

if (Test-Path $doc) {
    Write-Host "  ✅ DOCUMENTATION_BAT_SUZOSKY.md existe" -ForegroundColor Green
} else {
    Write-Host "  ❌ DOCUMENTATION_BAT_SUZOSKY.md manquant" -ForegroundColor Red
}

if (Test-Path $readme) {
    Write-Host "  ✅ BAT\README.md existe" -ForegroundColor Green
} else {
    Write-Host "  ❌ BAT\README.md manquant" -ForegroundColor Red
}

Write-Host "`n=== RESULTAT FINAL ===" -ForegroundColor Cyan
Write-Host "Architecture corrigee avec 2 scripts BAT distincts:" -ForegroundColor White
Write-Host "1. PROTECTION_GITHUB.bat - Sauvegarde GitHub continue" -ForegroundColor Green
Write-Host "2. SYNC_COURSIER_PROD.bat - Synchronisation LWS ponctuelle" -ForegroundColor Green
Write-Host "`nDocumentation complete disponible dans:" -ForegroundColor White
Write-Host "- DOCUMENTATION_BAT_SUZOSKY.md" -ForegroundColor Cyan
Write-Host "- BAT\README.md" -ForegroundColor Cyan