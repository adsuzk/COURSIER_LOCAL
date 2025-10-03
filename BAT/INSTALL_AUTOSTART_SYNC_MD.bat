@echo off
setlocal
cd /d "C:\xampp\htdocs\COURSIER_LOCAL"
echo ==============================================
echo   INSTALLATION AUTOSTART SYNC .MD (Task Scheduler)
echo ==============================================
echo Cette operation ajoute une tache planifiee Windows pour
echo lancer la synchro .md a l'ouverture de session (logon).
echo.
set TASKNAME=SuzoskySyncMdDocumentationFinale
set PS1="%CD%\PS1\SYNC_MD_DOCUMENTATION_FINALE.ps1"

echo Creation/maj de la tache planifiee: %TASKNAME%
schtasks /query /tn %TASKNAME% >nul 2>&1
if %errorlevel%==0 (
  schtasks /delete /tn %TASKNAME% /f >nul 2>&1
)

schtasks /create /sc onlogon /rl LIMITED /tn %TASKNAME% ^
 /tr "powershell.exe -ExecutionPolicy Bypass -WindowStyle Hidden -File %PS1%" ^
 /f

if %errorlevel%==0 (
echo Tache planifiee installee avec succes.
echo Elle demarrera automatiquement a chaque ouverture de session.
) else (
echo Echec d'installation de la tache planifiee (droits admin requis?).
)

echo.
echo Test de lancement immediat...
start "" powershell.exe -ExecutionPolicy Bypass -File %PS1%

echo.
echo Fini. Appuyez sur une touche pour fermer.
pause > nul
endlocal