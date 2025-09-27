@echo off
REM Script de création automatique des bases de données manquantes
REM Ce script exécute le script PHP create_missing_databases.php

title Creation des bases de donnees - Coursier Prod

echo.
echo ========================================
echo  CREATION DES BASES DE DONNEES MANQUANTES
echo ========================================
echo.

REM Vérification que XAMPP est installé
if not exist "C:\xampp\php\php.exe" (
    echo ERREUR: PHP n'est pas trouvé dans C:\xampp\php\php.exe
    echo Veuillez vérifier l'installation de XAMPP.
    pause
    exit /b 1
)

REM Vérification que le fichier PHP existe
if not exist "%~dp0create_missing_databases.php" (
    echo ERREUR: Le fichier create_missing_databases.php est introuvable
    echo Chemin attendu: %~dp0create_missing_databases.php
    pause
    exit /b 1
)

echo Dossier de travail: %~dp0
echo Script PHP: create_missing_databases.php
echo.

echo Lancement du processus de création...
echo.

REM Exécution du script PHP
C:\xampp\php\php.exe -d display_errors=1 -f "%~dp0create_missing_databases.php"

REM Vérification du code de retour
if %ERRORLEVEL% neq 0 (
    echo.
    echo ERREUR: Le script PHP a rencontré une erreur (code: %ERRORLEVEL%)
    echo.
) else (
    echo.
    echo SUCCESS: Le processus s'est terminé avec succès
    echo.
)

echo.
echo Appuyez sur une touche pour fermer...
pause >nul