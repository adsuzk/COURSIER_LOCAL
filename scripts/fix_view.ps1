# fix_view.ps1
# Drop and recreate the view safely
Write-Host "Stopping mysqld..."
taskkill /F /IM mysqld.exe /T 2>$null
Start-Sleep -Seconds 2

Write-Host "Starting mysqld with force_recovery..."
Start-Process -FilePath 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini' -WindowStyle Hidden
Start-Sleep -Seconds 10

Write-Host "Checking server..."
& 'C:\xampp\mysql\bin\mysqladmin.exe' -u root ping
if($LASTEXITCODE -eq 0){
    Write-Host "Server up. Dropping and recreating view..."
    & 'C:\xampp\mysql\bin\mysql.exe' -u root coursier_local -e "DROP VIEW IF EXISTS view_device_stats;"
    Get-Content 'C:\xampp\htdocs\COURSIER_LOCAL\_sql\create_view_device_stats.sql' | & 'C:\xampp\mysql\bin\mysql.exe' -u root coursier_local
    Write-Host "View recreation exit: $LASTEXITCODE"
    if($LASTEXITCODE -eq 0){
        Write-Host "View created. Removing force_recovery..."
        $content = Get-Content 'C:\xampp\mysql\bin\my.ini' -Raw
        $content = $content -replace '(?ms)# AUTO-ADDED FOR RECOVERY 20250930.*?# END AUTO-ADDED',''
        Set-Content 'C:\xampp\mysql\bin\my.ini' -Value $content

        Write-Host "Restarting mysqld normally..."
        taskkill /F /IM mysqld.exe /T 2>$null
        Start-Sleep -Seconds 2
        Start-Process -FilePath 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini' -WindowStyle Hidden
        Start-Sleep -Seconds 10

        & 'C:\xampp\mysql\bin\mysqladmin.exe' -u root ping
        if($LASTEXITCODE -eq 0){
            Write-Host "Server running normally. Testing view..."
            & 'C:\xampp\mysql\bin\mysql.exe' -u root coursier_local -e "SELECT COUNT(*) FROM view_device_stats;"
            Write-Host "Test query exit: $LASTEXITCODE"
            if($LASTEXITCODE -eq 0){
                Write-Host "Success! View is working. Now test the admin page."
                # Simulate the admin page query
                & 'C:\xampp\mysql\bin\mysql.exe' -u root coursier_local -e "SHOW TABLES LIKE 'view_device_stats';"
            }
        } else {
            Write-Host "Failed to start normally."
        }
    } else {
        Write-Host "View creation failed."
    }
} else {
    Write-Host "Server not started."
}