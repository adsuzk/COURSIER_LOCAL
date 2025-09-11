# sync_services_footer.ps1
# Script PowerShell pour synchroniser proprement les sections "Services" et "Footer" depuis coursier_prod vers COURSIER_LOCAL

# Chemins (à ajuster si nécessaire)
$prodBase   = "C:\xampp\htdocs\coursier_prod\sections index"
$localBase  = "C:\xampp\htdocs\COURSIER_LOCAL\sections index"
$prodStyle  = "C:\xampp\htdocs\coursier_prod\style.css"
$localStyle = "C:\xampp\htdocs\COURSIER_LOCAL\style.css"

Write-Host "=== Début de la synchronisation Services & Footer ===" -ForegroundColor Cyan

# 1) Vérification des dossiers
if (!(Test-Path $prodBase)) { Write-Error "Le dossier production n'existe pas: $prodBase"; exit 1 }
if (!(Test-Path $localBase)) { Write-Error "Le dossier local n'existe pas: $localBase"; exit 1 }

# 2) Récupération et injection de la section Services
Write-Host "-> Synchronisation de services.php" -ForegroundColor Yellow
$prodServices = Get-Content "$prodBase\services.php" -Raw
$servicesBloc = [regex]::Match($prodServices, '(?s)<section class="services-section".*?</section>').Value
$localServices = Get-Content "$localBase\services.php" -Raw
$updatedServices = [regex]::Replace(
    $localServices,
    '(?s)<section class="services-section".*?</section>',
    $servicesBloc
)
Set-Content "$localBase\services.php" -Value $updatedServices -Encoding UTF8
Write-Host "   services.php mis à jour." -ForegroundColor Green

# 3) Récupération et injection du footer
Write-Host "-> Synchronisation de footer_copyright.php" -ForegroundColor Yellow
$prodFooter = Get-Content "$prodBase\footer_copyright.php" -Raw
$footerBloc = [regex]::Match($prodFooter, '(?s)<footer class="footer-copyright".*?</footer>').Value
$localFooter = Get-Content "$localBase\footer_copyright.php" -Raw
$updatedFooter = [regex]::Replace(
    $localFooter,
    '(?s)<footer class="footer-copyright".*?</footer>',
    $footerBloc
)
Set-Content "$localBase\footer_copyright.php" -Value $updatedFooter -Encoding UTF8
Write-Host "   footer_copyright.php mis à jour." -ForegroundColor Green

# 4) Extraction des règles CSS liées
Write-Host "-> Extraction des styles CSS (services & footer)" -ForegroundColor Yellow
$patterns = @("\.services-section","\.service-card","\.footer-container","\.footer-content","\.footer-logo","\.footer-tagline","\.footer-info","\.footer-copyright")
$cssLines = Select-String -Path $prodStyle -Pattern $patterns -Context 0,10 | ForEach-Object { $_.Line }
$outCssFile = "$localBase\prod-services-footer.css"
$cssLines | Out-File $outCssFile -Encoding UTF8
Write-Host "   Styles extraits dans: $outCssFile" -ForegroundColor Green

Write-Host "=== Synchronisation terminée ===" -ForegroundColor Cyan
Write-Host "⚠️ Vérifiez prod-services-footer.css puis copiez les blocs dans votre style.css local." -ForegroundColor Magenta
