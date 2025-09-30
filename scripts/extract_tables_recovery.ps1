# extract_tables_recovery.ps1
# Start server if needed, then extract SHOW CREATE, COUNT and mysqldump for three tables
$out = 'C:\xampp\htdocs\COURSIER_LOCAL\_sql'
$tables = @('app_devices','app_crashes','app_versions')
Function Show-ErrTail {
    if(Test-Path 'C:\xampp\mysql\data\EYPC.err'){
        Get-Content 'C:\xampp\mysql\data\EYPC.err' -Tail 80
    }
}
Write-Host "Checking server availability (mysqladmin ping)..."
& 'C:\xampp\mysql\bin\mysqladmin.exe' -u root ping
if($LASTEXITCODE -ne 0){
    Write-Host "Server not responding. Attempting to start mysqld..."
    Start-Process -FilePath 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini' -WindowStyle Hidden
    Start-Sleep -Seconds 8
}
Write-Host "Pinging server again..."
& 'C:\xampp\mysql\bin\mysqladmin.exe' -u root ping
if($LASTEXITCODE -ne 0){
    Write-Host "Server did not start. Showing last lines of EYPC.err for diagnosis:"; Tail-Err; exit 2
}
Write-Host "Server is up. Extracting tables to $out"
foreach($t in $tables){
    Write-Host "--- Processing $t ---"
    try{
        (& 'C:\xampp\mysql\bin\mysql.exe' -u root -D coursier_local -e "SHOW CREATE TABLE $t\G") | Out-File -FilePath (Join-Path $out "show_create_$t.txt") -Encoding utf8 -Force
    } catch { Write-Host ('SHOW CREATE failed for {0}: {1}' -f $t, $Error[0].ToString()) }
    try{
        (& 'C:\xampp\mysql\bin\mysql.exe' -u root -D coursier_local -e "SELECT COUNT(*) AS cnt FROM $t\G") | Out-File -FilePath (Join-Path $out "count_$t.txt") -Encoding utf8 -Force
    } catch { Write-Host ('COUNT failed for {0}: {1}' -f $t, $Error[0].ToString()) }
    try{
        (& 'C:\xampp\mysql\bin\mysqldump.exe' -u root --skip-lock-tables --single-transaction --quick coursier_local $t) | Out-File -FilePath (Join-Path $out "recovered_$t.sql") -Encoding utf8 -Force
        Write-Host "Dumped $t"
    } catch { Write-Host ('mysqldump failed for {0}: {1}' -f $t, $Error[0].ToString()) }
}
Write-Host "Extraction complete." 
