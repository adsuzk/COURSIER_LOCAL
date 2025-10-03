@echo off
setlocal
cd /d "C:\xampp\htdocs\COURSIER_LOCAL"
echo ==============================================
echo   SUZOSKY - SYNC .MD -> DOCUMENTATION_FINALE
echo ==============================================
echo Dossier source : C:\xampp\htdocs\COURSIER_LOCAL
echo Dossier cible  : C:\xampp\htdocs\COURSIER_LOCAL\DOCUMENTATION_FINALE
echo Mode           : Continu, temps reel (~1s)
echo.
echo Appuyez sur ENTREE pour demarrer ...
pause > nul
powershell -ExecutionPolicy Bypass -File "PS1\SYNC_MD_DOCUMENTATION_FINALE.ps1"
echo.
echo Synchronisation arretee. Appuyez sur une touche pour fermer.
pause > nul
endlocal