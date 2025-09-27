$source = 'c:/xampp/htdocs/coursier_prod/diagnostic_logs/diagnostics_errors.log'
$target = 'c:/xampp/htdocs/coursier_prod/diagnostic_logs/diagnostics_errors_utf8.log'

if (-not (Test-Path $source)) {
    Write-Error "Source log not found: $source"
    exit 1
}

[byte[]]$data = [System.IO.File]::ReadAllBytes($source)
if (-not $data -or $data.Length -eq 0) {
    [System.IO.File]::WriteAllText($target, '', [System.Text.Encoding]::UTF8)
    Write-Output "Source log is empty; created empty UTF-8 file."
    exit 0
}

$start = 0
if ($data.Length -ge 2 -and $data[0] -eq 0xFF -and $data[1] -eq 0xFE) {
    $start = 2
    if ($data.Length -ge 6 -and $data[2] -eq 0x0D -and $data[3] -eq 0x00 -and $data[4] -eq 0x0A -and $data[5] -eq 0x00) {
        $start = 6
    }
}

if ($start -ge $data.Length) {
    [System.IO.File]::WriteAllText($target, '', [System.Text.Encoding]::UTF8)
    Write-Output "No UTF-8 payload detected after BOM; created empty UTF-8 file."
    exit 0
}

$payload = $data[$start..($data.Length - 1)]
$text = [System.Text.Encoding]::UTF8.GetString($payload)
[System.IO.File]::WriteAllText($target, $text, [System.Text.Encoding]::UTF8)
Write-Output "Converted diagnostics log to UTF-8 without UTF-16 artifacts."
