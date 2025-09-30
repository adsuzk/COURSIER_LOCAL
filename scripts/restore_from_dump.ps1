# restore_from_dump.ps1
# Stop mysqld, backup data dir, create new, initialize normally, then add force_recovery, import dump
Write-Host "Stopping mysqld..."
taskkill /F /IM mysqld.exe /T 2>$null
Start-Sleep -Seconds 2

Write-Host "Backing up current data dir to data_old..."
if(Test-Path 'C:\xampp\mysql\data'){
    Rename-Item 'C:\xampp\mysql\data' 'C:\xampp\mysql\data_old' -Force
}

Write-Host "Creating new data dir..."
New-Item -ItemType Directory -Path 'C:\xampp\mysql\data' -Force

Write-Host "Temporarily removing innodb_force_recovery from my.ini for initialization..."
$content = Get-Content 'C:\xampp\mysql\bin\my.ini' -Raw
$content = $content -replace '(?ms)# AUTO-ADDED FOR RECOVERY 20250930.*?# END AUTO-ADDED',''
Set-Content 'C:\xampp\mysql\bin\my.ini' -Value $content

Write-Host "Starting mysqld to initialize new data dir (normal mode)..."
Start-Process -FilePath 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini' -WindowStyle Hidden
Start-Sleep -Seconds 10

Write-Host "Checking server..."
& 'C:\xampp\mysql\bin\mysqladmin.exe' -u root ping
if($LASTEXITCODE -eq 0){
    Write-Host "Server initialized. Stopping to add force_recovery..."
    taskkill /F /IM mysqld.exe /T 2>$null
    Start-Sleep -Seconds 2

    Write-Host "Adding innodb_force_recovery back..."
    Add-Content 'C:\xampp\mysql\bin\my.ini' -Value @'
# AUTO-ADDED FOR RECOVERY 20250930
[mysqld]
innodb_force_recovery=6
# END AUTO-ADDED
'@

    Write-Host "Starting mysqld with force_recovery..."
    Start-Process -FilePath 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini' -WindowStyle Hidden
    Start-Sleep -Seconds 10

    Write-Host "Checking server again..."
    & 'C:\xampp\mysql\bin\mysqladmin.exe' -u root ping
    if($LASTEXITCODE -eq 0){
        Write-Host "Server up, creating database and importing dump..."
        & 'C:\xampp\mysql\bin\mysql.exe' -u root -e "CREATE DATABASE IF NOT EXISTS coursier_local;"
        Get-Content 'C:\xampp\htdocs\COURSIER_LOCAL\_sql\conci2547642_1m4twb.sql' | & 'C:\xampp\mysql\bin\mysql.exe' -u root coursier_local
        Write-Host "Import exit code: $LASTEXITCODE"
        if($LASTEXITCODE -eq 0){
            Write-Host "Import successful. Testing view creation..."
            Get-Content 'C:\xampp\htdocs\COURSIER_LOCAL\_sql\create_view_device_stats.sql' | & 'C:\xampp\mysql\bin\mysql.exe' -u root coursier_local
            Write-Host "View creation exit: $LASTEXITCODE"
            if($LASTEXITCODE -eq 0){
                Write-Host "View created successfully. Removing force_recovery and restarting normally..."
                $content = Get-Content 'C:\xampp\mysql\bin\my.ini' -Raw
                $content = $content -replace '(?ms)# AUTO-ADDED FOR RECOVERY 20250930.*?# END AUTO-ADDED',''
                Set-Content 'C:\xampp\mysql\bin\my.ini' -Value $content
                taskkill /F /IM mysqld.exe /T 2>$null
                Start-Sleep -Seconds 2
                Start-Process -FilePath 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini' -WindowStyle Hidden
                Start-Sleep -Seconds 10
                & 'C:\xampp\mysql\bin\mysqladmin.exe' -u root ping
                if($LASTEXITCODE -eq 0){
                    Write-Host "Server running normally. Testing admin page simulation..."
                    # Simulate the query that causes the crash
                    & 'C:\xampp\mysql\bin\mysql.exe' -u root coursier_local -e "SELECT * FROM view_device_stats LIMIT 1;"
                    Write-Host "Query exit: $LASTEXITCODE"
                } else {
                    Write-Host "Failed to start normally."
                }
            } else {
                Write-Host "View creation failed."
            }
        } else {
            Write-Host "Import failed."
        }
    } else {
        Write-Host "Server not started with force_recovery."
    }
} else {
    Write-Host "Server not started for initialization, restoring old data dir..."
    Remove-Item 'C:\xampp\mysql\data' -Recurse -Force
    Rename-Item 'C:\xampp\mysql\data_old' 'C:\xampp\mysql\data' -Force
}