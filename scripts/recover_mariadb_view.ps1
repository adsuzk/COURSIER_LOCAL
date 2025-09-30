$now = Get-Date -Format "yyyyMMdd_HHmmss"
$src = "C:\xampp\mysql\data"
$dst = "C:\xampp\mysql\data_backup_$now"
Write-Host "Backing up data directory from $src to $dst (robocopy)..."
robocopy $src $dst /MIR /COPYALL /R:3 /W:5 | Out-Null
if ($LASTEXITCODE -le 3) { Write-Host "robocopy finished (exit $LASTEXITCODE)" } else { Write-Host "robocopy reported non-zero exit code $LASTEXITCODE" }

$ini = "C:\xampp\mysql\bin\my.ini"
$bak = "$ini.bak_$now"
Write-Host "Backing up my.ini -> $bak"
Copy-Item -Path $ini -Destination $bak -Force

$block = "`r`n# BEGIN AUTO-ADDED FOR RECOVERY $now`r`n[mysqld]`r`ninnodb_force_recovery=1`r`n# END AUTO-ADDED`r`n"
Write-Host "Appending innodb_force_recovery=1 to my.ini"
Add-Content -Path $ini -Value $block

# Kill any running mysqld
$proc = Get-Process mysqld -ErrorAction SilentlyContinue
if ($proc) {
    Write-Host "Found mysqld process (PID $($proc.Id)). Stopping..."
    Stop-Process -Id $proc.Id -Force
    Start-Sleep -Seconds 3
} else {
    Write-Host "No mysqld process found"
}

# Start mysqld
Write-Host "Starting mysqld with modified my.ini..."
$start = Start-Process -FilePath 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini' -PassThru
Write-Host "mysqld start attempted (PID $($start.Id)). Waiting 10s for startup..."
Start-Sleep -Seconds 10

# Show last lines of error log
Write-Host "--- Last 60 lines of EYPC.err ---"
Get-Content 'C:\xampp\mysql\data\EYPC.err' -Tail 60 | ForEach-Object { Write-Host $_ }
Write-Host "--- End log tail ---"

# Try to run the view creation script
Write-Host "Attempting to create view using SQL file..."
& 'C:\xampp\mysql\bin\mysql.exe' -u root -e "SOURCE C:/xampp/htdocs/COURSIER_LOCAL/_sql/create_view_device_stats.sql;" coursier_local
$code = $LASTEXITCODE
Write-Host "mysql.exe exit code: $code"

# List views
Write-Host "Listing views in database after attempt:"
& 'C:\xampp\mysql\bin\mysql.exe' -u root -e "SHOW FULL TABLES WHERE Table_type = 'VIEW';" coursier_local

if ($code -eq 0) {
    Write-Host "View creation succeeded. Restoring original my.ini and restarting mysqld normally."
    Copy-Item -Path $bak -Destination $ini -Force
    # Restart mysqld
    $p = Get-Process mysqld -ErrorAction SilentlyContinue
    if ($p) { Stop-Process -Id $p.Id -Force; Start-Sleep -Seconds 2 }
    Start-Process -FilePath 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini' -PassThru
    Start-Sleep -Seconds 8
    Write-Host "--- Last 60 lines of EYPC.err after normal restart ---"
    Get-Content 'C:\xampp\mysql\data\EYPC.err' -Tail 60 | ForEach-Object { Write-Host $_ }
    Write-Host "--- End log tail ---"
    Write-Host "Final view list:"
    & 'C:\xampp\mysql\bin\mysql.exe' -u root -e "SHOW FULL TABLES WHERE Table_type = 'VIEW';" coursier_local
} else {
    Write-Host "View creation failed (exit $code). Leaving innodb_force_recovery in place. Showing last 120 lines of error log for diagnosis."
    Get-Content 'C:\xampp\mysql\data\EYPC.err' -Tail 120 | ForEach-Object { Write-Host $_ }
}

Write-Host "Script finished."