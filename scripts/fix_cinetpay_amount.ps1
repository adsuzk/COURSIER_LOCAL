# fix_cinetpay_amount.ps1
# PowerShell script to update price handling in index.html for CinetPay payment integration.
# This script replaces the incorrect price property in the JavaScript proceedToPayment function
# to use the dynamically calculated price instead of a default or undefined value.

# Path to index.html (adjust if needed)
$indexFile = Join-Path $PSScriptRoot '..\index.html'

if (Test-Path $indexFile) {
    Write-Host "Processing file: $indexFile"
    $text = Get-Content $indexFile -Raw

    # Replace the price line in the orderData object
    $pattern = 'price:\s*window\.currentPrice[^,]*,'
    $replacement = 'price: window.currentPriceData?.totalPrice || 0,'
    $newText = $text -replace $pattern, $replacement

    if ($newText -ne $text) {
        Set-Content -Path $indexFile -Value $newText -Encoding UTF8
        Write-Host "✔ Updated price line in index.html"
    } else {
        Write-Host "ℹ️ No matching price line found. Nothing changed."
    }
} else {
    Write-Error "File not found: $indexFile"
}
