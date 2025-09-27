@echo off
echo === RECREATION COMPLETE BASE COURSIER ===

echo 1. Arret complet MySQL...
taskkill /F /IM mysqld.exe 2>nul

echo 2. Nettoyage complet repertoire data...
rmdir /S /Q "C:\xampp\mysql\data"
mkdir "C:\xampp\mysql\data"

echo 3. Copie des donnees systeme MySQL...
xcopy "C:\xampp\mysql\backup\*.*" "C:\xampp\mysql\data\" /E /I /Y

echo 4. Initialisation nouvelle instance MySQL...
"C:\xampp\mysql\bin\mysqld.exe" --initialize-insecure --basedir="C:\xampp\mysql" --datadir="C:\xampp\mysql\data"

echo 5. Demarrage MySQL en arriere-plan...
start /B "MySQL Server" "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini"

timeout /T 5

echo 6. Creation base coursier_prod...
"C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS coursier_prod;"
"C:\xampp\mysql\bin\mysql.exe" -u root -e "USE coursier_prod; SOURCE C:\xampp\htdocs\COURSIER_LOCAL\database_setup.sql;"

echo 7. Test connexion...
"C:\xampp\mysql\bin\mysql.exe" -u root -e "SHOW DATABASES;"

pause