# PROTECTION_GITHUB_AVEC_DB.ps1
# Protection GitHub automatique avec sauvegarde automatique de la base de donnees
# Date : 02 Octobre 2025

param()
$ErrorActionPreference = "Continue"
$HOST.UI.RawUI.WindowTitle = "SUZOSKY Protection GitHub + Base de Donnees"

Clear-Host
Write-Host "============================================================" -ForegroundColor Magenta
Write-Host "   SUZOSKY - PROTECTION GITHUB + BASE DE DONNEES" -ForegroundColor Magenta
Write-Host "============================================================" -ForegroundColor Magenta
Write-Host ""
Write-Host "Repository: https://github.com/adsuzk/COURSIER_LOCAL" -ForegroundColor Cyan
Write-Host "Mode: Protection complete (code + base de donnees)" -ForegroundColor Yellow
Write-Host ""
Write-Host "[OK] Sauvegarde automatique du code" -ForegroundColor Green
Write-Host "[OK] Sauvegarde automatique de la structure DB" -ForegroundColor Green
Write-Host "[OK] Export complet des donnees" -ForegroundColor Green
Write-Host "[OK] Historique des modifications de colonnes" -ForegroundColor Green
Write-Host ""

Set-Location "C:\xampp\htdocs\COURSIER_LOCAL"

# Configuration des chemins
$mysqlPath = "C:\xampp\mysql\bin\mysql.exe"
$mysqldumpPath = "C:\xampp\mysql\bin\mysqldump.exe"
$dbName = "coursier_local"
$dbUser = "root"
$dbPass = ""
$sqlFolder = "_sql"
$backupFolder = "$sqlFolder\auto_backups"

# Creer les dossiers si necessaires
if (-not (Test-Path $sqlFolder)) { New-Item -ItemType Directory -Path $sqlFolder -Force | Out-Null }
if (-not (Test-Path $backupFolder)) { New-Item -ItemType Directory -Path $backupFolder -Force | Out-Null }

# Variables d'environnement pour Git
$env:GIT_ASKPASS = "echo"
$env:GCM_INTERACTIVE = "never"
$env:GIT_TERMINAL_PROMPT = "0"

# Configuration Git
git config --global user.email "suzosky@github.com"
git config --global user.name "Suzosky Protection"
git config --global credential.useHttpPath true
git config --global core.askPass ""
git config --global gui.askpass ""
git config --global credential.modalPrompt false
git config --global credential.guiPrompt false

Write-Host "Configuration: Git Credential Manager actif" -ForegroundColor Yellow

# Test de connexion GitHub
Write-Host "Test de connexion GitHub..." -ForegroundColor Yellow
$testResult = git ls-remote origin HEAD 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "[OK] Connexion GitHub reussie !" -ForegroundColor Green
} else {
    Write-Host "[ERREUR] Connexion GitHub impossible !" -ForegroundColor Red
    Write-Host "Details: $testResult" -ForegroundColor Red
    Read-Host "Appuyez sur Entree pour fermer"
    exit 1
}

# Test de connexion MySQL
Write-Host "Test de connexion MySQL..." -ForegroundColor Yellow
$testMySQL = & $mysqlPath -u $dbUser -e "SELECT 1;" 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "[OK] Connexion MySQL reussie !" -ForegroundColor Green
} else {
    Write-Host "[ERREUR] Connexion MySQL impossible !" -ForegroundColor Red
    Write-Host "Details: $testMySQL" -ForegroundColor Red
}

Write-Host ""
Write-Host "============================================================" -ForegroundColor Green
Write-Host "   PROTECTION ACTIVE - MODE COMPLET" -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Green
Write-Host ""
Write-Host "-> Scan automatique toutes les 5 secondes" -ForegroundColor Cyan
Write-Host "-> Sauvegarde DB a chaque changement detecte" -ForegroundColor Cyan
Write-Host "-> Push GitHub automatique" -ForegroundColor Cyan
Write-Host ""
Write-Host "Appuyez sur Ctrl+C pour arreter" -ForegroundColor Yellow
Write-Host ""

$scanCount = 0
$lastDbBackup = Get-Date

# Fonction pour sauvegarder la base de donnees
function Backup-Database {
    param([string]$timestamp)
    
    try {
        $dateStr = Get-Date -Format "yyyyMMdd_HHmmss"
        
        # 1. Export de la STRUCTURE UNIQUEMENT (schema complet)
        Write-Host "  -> Export structure DB..." -ForegroundColor Yellow -NoNewline
        $schemaFile = "$sqlFolder\schema_$dateStr.sql"
        & $mysqldumpPath -u $dbUser --no-data --skip-comments --skip-lock-tables --databases $dbName > $schemaFile 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Host " [OK]" -ForegroundColor Green
        } else {
            Write-Host " [ERREUR]" -ForegroundColor Red
        }
        
        # 2. Export COMPLET (structure + donnees)
        Write-Host "  -> Export donnees completes..." -ForegroundColor Yellow -NoNewline
        $fullBackupFile = "$backupFolder\${dbName}_full_$dateStr.sql"
        & $mysqldumpPath -u $dbUser --databases $dbName --skip-comments --skip-lock-tables > $fullBackupFile 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Host " [OK]" -ForegroundColor Green
        } else {
            Write-Host " [ERREUR]" -ForegroundColor Red
        }
        
        # 3. Export de la LISTE DES TABLES
        Write-Host "  -> Liste des tables..." -ForegroundColor Yellow -NoNewline
        $tablesFile = "$sqlFolder\tables_list_$dateStr.txt"
        & $mysqlPath -u $dbUser $dbName -e "SHOW TABLES;" > $tablesFile 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Host " [OK]" -ForegroundColor Green
        } else {
            Write-Host " [ERREUR]" -ForegroundColor Red
        }
        
        # 4. Export DETAILLE de chaque table (colonnes, types, index)
        Write-Host "  -> Structure detaillee..." -ForegroundColor Yellow -NoNewline
        $detailFile = "$sqlFolder\structure_detaillee_$dateStr.txt"
        $tables = & $mysqlPath -u $dbUser $dbName -e "SHOW TABLES;" 2>&1 | Select-Object -Skip 1
        
        "STRUCTURE DETAILLEE DE LA BASE: $dbName" | Out-File $detailFile -Encoding UTF8
        "Date: $(Get-Date -Format 'dd/MM/yyyy HH:mm:ss')" | Out-File $detailFile -Append -Encoding UTF8
        "=" * 80 | Out-File $detailFile -Append -Encoding UTF8
        "`n" | Out-File $detailFile -Append -Encoding UTF8
        
        foreach ($table in $tables) {
            if ($table -and $table.Trim() -ne "") {
                "`n========== TABLE: $table ==========" | Out-File $detailFile -Append -Encoding UTF8
                & $mysqlPath -u $dbUser $dbName -e "DESCRIBE $table;" 2>&1 | Out-File $detailFile -Append -Encoding UTF8
                "`nINDEX:" | Out-File $detailFile -Append -Encoding UTF8
                & $mysqlPath -u $dbUser $dbName -e "SHOW INDEX FROM $table;" 2>&1 | Out-File $detailFile -Append -Encoding UTF8
            }
        }
        Write-Host " [OK]" -ForegroundColor Green
        
        # 5. Creer un fichier "DERNIER BACKUP" qui pointe vers le plus recent
        Write-Host "  -> Mise a jour lien dernier backup..." -ForegroundColor Yellow -NoNewline
        $latestLink = "$sqlFolder\DERNIER_BACKUP.txt"
        @"
DERNIER BACKUP DE LA BASE DE DONNEES
=====================================
Date: $(Get-Date -Format 'dd/MM/yyyy HH:mm:ss')

Fichiers crees:
- Structure seule: schema_$dateStr.sql
- Backup complet: auto_backups/${dbName}_full_$dateStr.sql
- Liste tables: tables_list_$dateStr.txt
- Structure detaillee: structure_detaillee_$dateStr.txt

Pour restaurer:
mysql -u root coursier_local < "$backupFolder/${dbName}_full_$dateStr.sql"
"@ | Out-File $latestLink -Encoding UTF8 -Force
        Write-Host " [OK]" -ForegroundColor Green
        
        # 6. Nettoyer les anciens backups (garder seulement les 10 derniers)
        $oldBackups = Get-ChildItem "$backupFolder\*.sql" | Sort-Object LastWriteTime -Descending | Select-Object -Skip 10
        if ($oldBackups) {
            Write-Host "  -> Nettoyage anciens backups ($($oldBackups.Count))..." -ForegroundColor Yellow -NoNewline
            $oldBackups | Remove-Item -Force
            Write-Host " [OK]" -ForegroundColor Green
        }
        
        Write-Host "  [OK] Sauvegarde DB terminee" -ForegroundColor Green
        return $true
        
    } catch {
        Write-Host "  [ERREUR] Sauvegarde DB: $_" -ForegroundColor Red
        return $false
    }
}

# Boucle principale
while ($true) {
    $scanCount++
    $timestamp = Get-Date -Format "HH:mm:ss"
    
    # Verifier s'il y a des changements
    $status = git status --porcelain 2>&1
    
    if ($status -and $status -notlike "*fatal*" -and $status -notlike "*error*") {
        Write-Host ""
        Write-Host "[$timestamp] === CHANGEMENTS DETECTES (scan $scanCount) ===" -ForegroundColor Cyan
        
        # 1. Sauvegarder la base de donnees
        $dbBackupSuccess = Backup-Database -timestamp $timestamp
        
        # 2. Ajouter tous les fichiers
        Write-Host "[$timestamp] Ajout des fichiers..." -ForegroundColor Cyan
        git add . 2>&1 | Out-Null
        
        # 3. Commit
        $commitMsg = "Auto-backup $timestamp [scan $scanCount] + DB structure"
        if ($dbBackupSuccess) {
            $commitMsg += " [OK]"
        }
        
        Write-Host "[$timestamp] Creation du commit..." -ForegroundColor Yellow
        git commit -m $commitMsg 2>&1 | Out-Null
        
        if ($LASTEXITCODE -eq 0) {
            # 4. Push vers GitHub
            Write-Host "[$timestamp] Push vers GitHub..." -ForegroundColor Yellow
            $pushResult = git push origin main 2>&1
            
            if ($LASTEXITCODE -eq 0) {
                Write-Host "[$timestamp] [OK] SAUVEGARDE COMPLETE REUSSIE" -ForegroundColor Green
                $lastDbBackup = Get-Date
            } else {
                Write-Host "[$timestamp] [ERREUR] Push GitHub" -ForegroundColor Red
                Write-Host "Details: $pushResult" -ForegroundColor Red
            }
        } else {
            Write-Host "[$timestamp] Pas de changements a commiter" -ForegroundColor Gray
        }
        
    } else {
        # Affichage de statut periodique
        if (($scanCount % 12) -eq 0) {
            $timeSinceBackup = (Get-Date) - $lastDbBackup
            Write-Host "[$timestamp] Protection active [scan $scanCount] - GitHub OK - Dernier backup DB: $([math]::Round($timeSinceBackup.TotalMinutes, 1))m" -ForegroundColor Gray
        } else {
            Write-Host "." -NoNewline -ForegroundColor DarkGray
        }
    }
    
    Start-Sleep -Seconds 5
}
