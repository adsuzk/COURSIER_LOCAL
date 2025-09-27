@echo off
echo === REPARATION MYSQL XAMPP ===
echo.
echo 1. Arret des processus MySQL...
taskkill /F /IM mysqld.exe 2>nul

echo 2. Sauvegarde my.ini...
copy "C:\xampp\mysql\bin\my.ini" "C:\xampp\mysql\bin\my.ini.backup"

echo 3. Ajout du mode de recuperation InnoDB...
echo innodb_force_recovery = 1 >> "C:\xampp\mysql\bin\my.ini"

echo 4. Tentative de demarrage MySQL...
"C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone --console

pause