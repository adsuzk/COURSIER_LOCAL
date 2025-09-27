# SUZOSKY - SYNCHRONISATION ULTRA ROBUSTE LOCAL → PROD
# Script de synchronisation permanente avec configuration automatique LWS

param()

$ErrorActionPreference = "Stop"
$HOST.UI.RawUI.WindowTitle = "SUZOSKY - SYNC LOCAL → PROD LWS"

# CONFIGURATION CRITIQUE
$SOURCE_PATH = "C:\xampp\htdocs\COURSIER_LOCAL"
$PROD_PATH = "C:\xampp\htdocs\coursier_prod"

# CONFIGURATION SERVEUR LWS - NE PAS MODIFIER
$LWS_DB_HOST = "185.98.131.214"
$LWS_DB_NAME = "conci2547642_1m4twb"
$LWS_DB_USER = "conci2547642_1m4twb"
$LWS_DB_PASS = "wN1!_TT!yHsK6Y6"

# DOSSIERS/FICHIERS À EXCLURE DE LA SYNCHRONISATION
$EXCLUDED_PATTERNS = @(
    "scripts\*",
    "Tests\*", 
    "test_*",
    "tmp_*",
    "debug_*",
    "cli_*",
    "diagnostic_logs\*",
    ".git\*",
    ".github\*",
    "vendor\*",
    "composer.*",
    "*.log",
    "*.bat",
    "*.ps1",
    "PROTECTION_*",
    "CONFIGURE_*",
    "CREATE_*",
    "SWITCH_*",
    "DEMARRER_*",
    "*.token",
    "cookies.txt",
    "mobile_connection_log.txt",
    "debug_requests.log",
    "lockout.json"
)

# COULEURS POUR LOGS
function Write-ColorLog {
    param([string]$Message, [string]$Color = "Green", [string]$Prefix = "INFO")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-Host "[$timestamp] [$Prefix] $Message" -ForegroundColor $Color
}

function Write-Success { param([string]$msg) Write-ColorLog $msg "Green" "SUCCESS" }
function Write-Warning { param([string]$msg) Write-ColorLog $msg "Yellow" "WARNING" }
function Write-Error { param([string]$msg) Write-ColorLog $msg "Red" "ERROR" }
function Write-Info { param([string]$msg) Write-ColorLog $msg "Cyan" "INFO" }

# FONCTION DE VÉRIFICATION DES CHEMINS
function Test-CriticalPaths {
    Write-Info "Vérification des chemins critiques..."
    
    if (!(Test-Path $SOURCE_PATH)) {
        Write-Error "ÉCHEC CRITIQUE: SOURCE $SOURCE_PATH introuvable"
        throw "Chemin source manquant"
    }
    
    if (!(Test-Path $PROD_PATH)) {
        Write-Warning "Création du dossier PROD: $PROD_PATH"
        New-Item -Path $PROD_PATH -ItemType Directory -Force | Out-Null
    }
    
    Write-Success "Chemins validés"
}

# FONCTION DE NETTOYAGE DU DOSSIER PROD
function Clear-ProdDirectory {
    Write-Info "Nettoyage du dossier PROD..."
    
    try {
        if (Test-Path $PROD_PATH) {
            Get-ChildItem -Path $PROD_PATH -Recurse -Force | Remove-Item -Force -Recurse
        }
        Write-Success "Dossier PROD nettoyé"
    }
    catch {
        Write-Error "Erreur nettoyage PROD: $_"
        throw
    }
}

# FONCTION DE FILTRAGE DES FICHIERS
function Test-ShouldExclude {
    param([string]$RelativePath)
    
    foreach ($pattern in $EXCLUDED_PATTERNS) {
        if ($RelativePath -like $pattern) {
            return $true
        }
    }
    return $false
}

# FONCTION DE REMPLACEMENT DES CONFIGURATIONS
function Update-FileForLWS {
    param([string]$FilePath)
    
    $extension = [System.IO.Path]::GetExtension($FilePath).ToLower()
    
    # Traiter seulement les fichiers PHP et JS
    if ($extension -notin @(".php", ".js")) {
        return
    }
    
    try {
        $content = Get-Content -Path $FilePath -Raw -Encoding UTF8
        $originalContent = $content
        
        # REMPLACEMENT BASE DE DONNÉES
        $content = $content -replace "localhost", $LWS_DB_HOST
        $content = $content -replace "127\.0\.0\.1", $LWS_DB_HOST
        $content = $content -replace "coursier_local", $LWS_DB_NAME
        $content = $content -replace "coursier_prod", $LWS_DB_NAME
        $content = $content -replace "root", $LWS_DB_USER
        
        # REMPLACEMENT MOTS DE PASSE (patterns courants)
        $content = $content -replace "password\s*=>\s*`"`"", "password => `"$LWS_DB_PASS`""
        $content = $content -replace "password\s*:\s*`"`"", "password: `"$LWS_DB_PASS`""
        $content = $content -replace "password\s*=>\s*''", "password => '$LWS_DB_PASS'"
        
        # REMPLACEMENT URLS LOCALES
        $content = $content -replace "http://localhost/COURSIER_LOCAL", "https://suzosky.com"
        $content = $content -replace "localhost/COURSIER_LOCAL", "suzosky.com"
        $content = $content -replace "/COURSIER_LOCAL/", "/"
        
        # REMPLACEMENT CHEMINS ABSOLUS
        $content = $content -replace "C:\\xampp\\htdocs\\COURSIER_LOCAL", "/home/conci2547642/public_html"
        $content = $content -replace "C:/xampp/htdocs/COURSIER_LOCAL", "/home/conci2547642/public_html"
        
        # CONFIGURATIONS SPÉCIFIQUES PHP
        if ($extension -eq ".php") {
            # Base href
            $content = $content -replace '<base href="/COURSIER_LOCAL/">', '<base href="/">'
            
            # Configuration PDO/MySQLi
            $content = $content -replace "new PDO\(`"mysql:host=localhost", "new PDO(`"mysql:host=$LWS_DB_HOST"
            $content = $content -replace "mysqli_connect\(`"localhost`"", "mysqli_connect(`"$LWS_DB_HOST`""
            $content = $content -replace "mysqli_connect\('localhost'", "mysqli_connect('$LWS_DB_HOST'"
        }
        
        # Sauvegarder seulement si modifié
        if ($content -ne $originalContent) {
            Set-Content -Path $FilePath -Value $content -Encoding UTF8 -NoNewline
            Write-Info "Configuré pour LWS: $(Split-Path $FilePath -Leaf)"
        }
        
    }
    catch {
        Write-Error "Erreur configuration LWS pour $FilePath : $_"
    }
}

# FONCTION DE SYNCHRONISATION ROBUSTE
function Sync-ToProduction {
    Write-Info "=== DÉBUT SYNCHRONISATION ROBUSTE ==="
    
    $syncCount = 0
    $errorCount = 0
    
    try {
        # Parcourir tous les fichiers source
        Get-ChildItem -Path $SOURCE_PATH -Recurse -File | ForEach-Object {
            $sourceFile = $_.FullName
            $relativePath = $sourceFile.Substring($SOURCE_PATH.Length + 1)
            
            # Vérifier exclusions
            if (Test-ShouldExclude $relativePath) {
                Write-Warning "EXCLU: $relativePath"
                return
            }
            
            $destFile = Join-Path $PROD_PATH $relativePath
            $destDir = Split-Path $destFile -Parent
            
            try {
                # Créer dossier destination si nécessaire
                if (!(Test-Path $destDir)) {
                    New-Item -Path $destDir -ItemType Directory -Force | Out-Null
                }
                
                # Copier le fichier
                Copy-Item -Path $sourceFile -Destination $destFile -Force
                
                # Configurer pour LWS
                Update-FileForLWS $destFile
                
                $syncCount++
                Write-Host "." -NoNewline -ForegroundColor Green
                
            }
            catch {
                $errorCount++
                Write-Error "Erreur sync $relativePath : $_"
            }
        }
        
        Write-Host ""
        Write-Success "Synchronisation terminée: $syncCount fichiers, $errorCount erreurs"
        
    }
    catch {
        Write-Error "ÉCHEC CRITIQUE synchronisation: $_"
        throw
    }
}

# FONCTION PRINCIPALE DE SURVEILLANCE
function Start-ProductionSync {
    Write-ColorLog "=== SYNCHRONISATION PERMANENTE SUZOSKY ===" "Magenta" "INIT"
    Write-Info "Source: $SOURCE_PATH"
    Write-Info "Production: $PROD_PATH"
    Write-Info "Serveur LWS: $LWS_DB_HOST"
    Write-Info "Base: $LWS_DB_NAME"
    Write-Host ""
    
    Test-CriticalPaths
    
    Write-ColorLog "=== SURVEILLANCE ACTIVE ===" "Green" "READY"
    Write-Host "Appuyez sur Ctrl+C pour arrêter" -ForegroundColor Yellow
    Write-Host ""
    
    $lastSyncTime = [DateTime]::MinValue
    $iteration = 0
    
    while ($true) {
        try {
            $iteration++
            
            # Vérifier s'il y a des changements
            $latestChange = Get-ChildItem -Path $SOURCE_PATH -Recurse -File | 
                           Where-Object { 
                               $relativePath = $_.FullName.Substring($SOURCE_PATH.Length + 1)
                               !(Test-ShouldExclude $relativePath)
                           } | 
                           Sort-Object LastWriteTime -Descending | 
                           Select-Object -First 1
            
            if ($latestChange -and $latestChange.LastWriteTime -gt $lastSyncTime) {
                $time = Get-Date -Format "HH:mm:ss"
                Write-Info "Changements détectés - Synchronisation #$iteration"
                
                Clear-ProdDirectory
                Sync-ToProduction
                
                $lastSyncTime = Get-Date
                Write-Success "Synchronisation #$iteration terminée à $time"
            } else {
                Write-Host "." -NoNewline -ForegroundColor Gray
            }
            
            Start-Sleep -Seconds 3
            
        }
        catch {
            Write-Error "Erreur dans la boucle: $_"
            Start-Sleep -Seconds 10
        }
    }
}

# DÉMARRAGE ULTRA ROBUSTE
try {
    Start-ProductionSync
}
catch {
    Write-Error "ÉCHEC CRITIQUE DU SCRIPT: $_"
    Write-Host "Appuyez sur une touche pour fermer..." -ForegroundColor Red
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
}