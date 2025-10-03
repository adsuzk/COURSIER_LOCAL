# Removes legacy EMAIL_SYSTEM files except templates and logs
$base = "C:\xampp\htdocs\COURSIER_LOCAL\EMAIL_SYSTEM"
$toDelete = @(
    "admin_api.php",
    "admin_panel.php",
    "admin_script.js",
    "admin_styles.css",
    "api.php",
    "dashboard.php",
    "EmailManager.php",
    "integration_guide.php",
    "RobustEmailSystem.php",
    "track.php"
)
foreach ($f in $toDelete) {
    $p = Join-Path $base $f
    if (Test-Path $p) {
        try { Remove-Item -Path $p -Force } catch {}
    }
}
Write-Host "Legacy EMAIL_SYSTEM cleaned (templates/ & logs/ preserved)"