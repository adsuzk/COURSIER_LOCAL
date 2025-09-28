# Script pour basculer entre MainActivity normal et diagnostic
param(
    [Parameter(Mandatory=$true)]
    [ValidateSet("normal", "diagnostic")]
    [string]$Version
)

$manifestPath = "app/src/main/AndroidManifest.xml"
$normalActivity = "com.suzosky.coursier.MainActivity"
$diagnosticActivity = "com.suzosky.coursier.MainActivityDiagnostic"

Write-Host "=== BASCULEMENT VERSION $Version ===" -ForegroundColor Green

if ($Version -eq "diagnostic") {
    Write-Host "`nConfiguration pour tests diagnostiques..." -ForegroundColor Yellow
    $targetActivity = $diagnosticActivity
    $description = "version diagnostic (interface simplifiée)"
} else {
    Write-Host "`nConfiguration pour version normale..." -ForegroundColor Yellow
    $targetActivity = $normalActivity
    $description = "version normale (interface complète)"
}

# Lire le fichier AndroidManifest.xml
$content = Get-Content $manifestPath -Raw

# Remplacer l'activité principale
if ($content -match 'android:name="com\.suzosky\.coursier\.MainActivity') {
    $content = $content -replace 'android:name="com\.suzosky\.coursier\.MainActivity[^"]*"', "android:name=`"$targetActivity`""
    Write-Host "✅ Activité remplacée par: $targetActivity" -ForegroundColor Green
} elseif ($content -match 'android:name="com\.suzosky\.coursier\.MainActivityDiagnostic') {
    $content = $content -replace 'android:name="com\.suzosky\.coursier\.MainActivityDiagnostic[^"]*"', "android:name=`"$targetActivity`""
    Write-Host "✅ Activité remplacée par: $targetActivity" -ForegroundColor Green
} else {
    Write-Host "⚠️ Impossible de trouver l'activité à remplacer" -ForegroundColor Red
    Write-Host "Vérifiez le fichier AndroidManifest.xml" -ForegroundColor Red
    exit 1
}

# Sauvegarder les modifications
Set-Content $manifestPath -Value $content

Write-Host "✅ Configuration basculée vers la $description" -ForegroundColor Green
Write-Host "`nMaintenant vous pouvez:" -ForegroundColor Cyan
Write-Host "  1. Compiler: ./gradlew assembleDebug -x lintDebug" -ForegroundColor White
Write-Host "  2. Installer et tester l'application" -ForegroundColor White

if ($Version -eq "diagnostic") {
    Write-Host "`n🔍 Version DIAGNOSTIC activée:" -ForegroundColor Yellow
    Write-Host "  - Interface minimale" -ForegroundColor White
    Write-Host "  - Logs détaillés" -ForegroundColor White
    Write-Host "  - Tests progressifs" -ForegroundColor White
} else {
    Write-Host "`n🏠 Version NORMALE activée:" -ForegroundColor Yellow
    Write-Host "  - Interface complète" -ForegroundColor White
    Write-Host "  - Fonctionnalités complètes" -ForegroundColor White
    Write-Host "  - Protection contre les crashes" -ForegroundColor White
}

Write-Host "`n=== CONFIGURATION TERMINÉE ===" -ForegroundColor Green