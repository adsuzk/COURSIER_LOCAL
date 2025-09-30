$content = Get-Content 'C:\xampp\htdocs\COURSIER_LOCAL\_sql\conci2547642_1m4twb.sql' -Raw
$newContent = $content -replace 'CREATE ALGORITHM=UNDEFINED DEFINER=`conci2547642_1m4twb`@`%` SQL SECURITY DEFINER VIEW `view_device_stats`', 'CREATE OR REPLACE VIEW `view_device_stats`'
Set-Content 'C:\xampp\htdocs\COURSIER_LOCAL\_sql\conci2547642_1m4twb_fixed.sql' -Value $newContent -Encoding UTF8
Write-Host 'Created fixed dump without DEFINER.'