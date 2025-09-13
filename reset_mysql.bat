@echo off
echo === RESET COMPLET MYSQL XAMPP ===
echo.
echo ATTENTION: Cette operation va supprimer TOUTES les bases de donnees !
echo Appuyez sur CTRL+C pour annuler, ou une touche pour continuer...
pause

echo 1. Arret complet MySQL...
taskkill /F /IM mysqld.exe 2>nul

echo 2. Sauvegarde des donnees actuelles...
if not exist "C:\xampp\mysql\data_backup" mkdir "C:\xampp\mysql\data_backup"
xcopy "C:\xampp\mysql\data\*.*" "C:\xampp\mysql\data_backup\" /E /I /Y

echo 3. Suppression des fichiers InnoDB corrompus...
del "C:\xampp\mysql\data\ib_logfile*" 2>nul
del "C:\xampp\mysql\data\ibdata1" 2>nul
del "C:\xampp\mysql\data\ibtmp1" 2>nul

echo 4. Restauration my.ini propre...
copy "C:\xampp\mysql\bin\my.ini.backup" "C:\xampp\mysql\bin\my.ini" 2>nul

echo 5. Reinitialisation MySQL...
"C:\xampp\mysql\bin\mysqld.exe" --initialize-insecure --basedir="C:\xampp\mysql" --datadir="C:\xampp\mysql\data"

echo 6. Demarrage MySQL...
"C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini"

pause