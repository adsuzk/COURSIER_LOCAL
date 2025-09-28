# 🛡️ SCRIPTS DE PROTECTION ET SYNCHRONISATION PROPRE
**Date : 28 Septembre 2025 | Version : 3.0 - Architecture PS1**

---

## 📋 NOUVEAUX SCRIPTS DISPONIBLES

### 1. **PROTECTION_GITHUB_AVEC_SYNC_PROPRE.ps1**
**Protection GitHub + Synchronisation automatique propre**

#### Fonctionnalités :
- ✅ Protection GitHub automatique (Git Credential Manager)
- ✅ Synchronisation vers `coursier_prod` toutes les 60 secondes
- ✅ Exclusion automatique des fichiers de test/debug
- ✅ Structure de production toujours propre
- ✅ Surveillance continue en arrière-plan

#### Usage :
```powershell
# Via script PowerShell (nouvelle localisation PS1/)
.\PS1\PROTECTION_GITHUB_AVEC_SYNC_PROPRE.ps1

# Via fichier BAT (recommandé)
.\BAT\PROTECTION_GITHUB.bat
```

### 2. **SYNC_COURSIER_PROD_SIMPLE.ps1**
**Synchronisation manuelle propre**

#### Fonctionnalités :
- 🔄 Synchronisation immédiate vers `coursier_prod`
- 🚫 Exclusions complètes des fichiers de développement
- 🔍 Vérification post-synchronisation
- 🧹 Nettoyage automatique avec `-Force`

#### Usage :
```powershell
# Synchronisation simple (nouvelle localisation PS1/)
.\PS1\SYNC_COURSIER_PROD_LWS.ps1

# Via fichier BAT (recommandé - inclut auto-migration)
.\BAT\SYNC_COURSIER_PROD.bat
```

---

## 🚫 EXCLUSIONS AUTOMATIQUES

### Dossiers exclus :
- `PS1/` - **TOUS les scripts PowerShell (sécurité maximale)**
- `.git` - Repository Git
- `.vscode` - Configuration VS Code
- `Tests/` - Tous les fichiers de test
- `BAT/` - Scripts batch locaux
- `Applications/` - Apps mobiles
- `DOCUMENTATION_FINALE/` - Documentation développement
- `node_modules/` - Dépendances Node.js

### Patterns de fichiers exclus :
```
# Logs et temporaires
*.log, *.tmp, *.bak, *.lock

# Fichiers de test
*test*, *Test*, *TEST*

# Fichiers de debug  
*debug*, *Debug*, *DEBUG*

# Fichiers CLI
*cli_*, *CLI_*

# Fichiers de vérification
*check_*, *Check_*

# Fichiers de restauration/déploiement
*restore_*, *post_deploy*, *setup_*

# Fichiers de diagnostic
*diagnostic*, *smoketest*

# Fichiers temporaires
*temp*, *tmp*, TEST_*, Debug_*, Rebuild_*
```

---

## 🎯 AVANTAGES DE LA NOUVELLE APPROCHE

### ✅ **Révolution architecture Version 3.0 :**
1. **Isolation PS1/** : Aucun script PowerShell déployé en production
2. **Auto-migrations** : Détection automatique changements DB + génération sans code
3. **Structure optimale** : Production 100% propre automatiquement
4. **Sécurité renforcée** : Séparation complète développement/production

### ✅ **Sécurité renforcée :**
- Utilisation de Git Credential Manager (pas de tokens exposés)
- Authentification sécurisée sans secrets dans le code
- Gestion d'erreur robuste

### ✅ **Performance optimisée :**
- Synchronisation rapide avec robocopy multi-thread
- Exclusions au niveau système (plus efficace)
- Pas de traitement post-copie nécessaire

---

## 🚀 MIGRATION ET UTILISATION

### Remplacement des anciens scripts :
```
ANCIEN                           → NOUVEAU
PROTECTION_GITHUB_FINAL.ps1     → PROTECTION_GITHUB_AVEC_SYNC_PROPRE.ps1
Synchronisation manuelle        → SYNC_COURSIER_PROD_SIMPLE.ps1
```

### Commandes recommandées :

#### Pour la protection continue :
```bat
# Lancer la protection avec synchronisation
.\BAT\PROTECTION_AUTO.bat
```

#### Pour synchronisation ponctuelle :
```bat
# Synchronisation unique avec nettoyage
.\BAT\SYNC_COURSIER_PROD.bat
```

### Vérification de la structure propre :
```powershell
# Vérifier qu'aucun fichier de test existe dans coursier_prod
Get-ChildItem "C:\xampp\htdocs\coursier_prod" -Recurse -Name "*test*", "*debug*", "*cli_*" | Where-Object { $_ -notlike "*vendor*" }
```

### 🌐 Spécificités LWS (MàJ 28/09/2025)
- Suppression automatique de `default_index.html` dans `coursier_prod` afin que `index.php` soit immédiatement servi après upload.
- Génération/actualisation de `FORCE_PRODUCTION_DB` pour forcer la configuration MySQL de production lors des exécutions CLI/CRON sur LWS.
- Préservation du dossier `Scripts/` (cron PHP) : les scripts critiques (`fcm_token_security.php`, `secure_order_assignment.php`, `fcm_auto_cleanup.php`, `automated_db_migration.php`) sont désormais regroupés dans `Scripts/Scripts cron/` ; les anciens points d'entrée à la racine ne sont plus que des shims de compatibilité.
- Lors du transfert FTP/SFTP, **uploader uniquement le contenu de `coursier_prod`** (fichiers + sous-dossiers) directement dans la racine du site LWS.

---

## 🔧 CONFIGURATION ET PERSONNALISATION

### Variables configurables :
```powershell
# Dans PROTECTION_GITHUB_AVEC_SYNC_PROPRE.ps1
$scanCount = 0                    # Compteur de scans
$lastSyncTime = Get-Date         # Dernière sync
$syncInterval = 60               # Interval sync (secondes)

# Dossiers source et target
$sourceDir = "C:\xampp\htdocs\COURSIER_LOCAL"
$targetDir = "C:\xampp\htdocs\coursier_prod"
```

### Personnalisation des exclusions :
Modifier les tableaux `$excludedDirs` et `$excludedFiles` dans les scripts pour ajouter d'autres patterns d'exclusion.

---

## 📊 MONITORING ET LOGS

### Informations affichées en temps réel :
- 🔍 Statut de la surveillance GitHub
- 🔄 Progression des synchronisations
- ✅ Confirmations de structure propre
- ⚠️ Alertes de fichiers problématiques détectés
- 📈 Compteurs de scans et statistiques

### Codes de sortie robocopy :
- `0-7` : Synchronisation réussie
- `8+` : Erreurs critiques

---

## 🎯 RÉSULTAT FINAL - ARCHITECTURE PS1

Avec l'architecture PS1 Version 3.0 :

- ✅ **SÉCURITÉ MAXIMALE** : Aucun script PowerShell ne peut être déployé en production
- ✅ **AUTO-MIGRATIONS** : Base de données se met à jour automatiquement sans intervention
- ✅ **STRUCTURE PARFAITE** : Production optimale garantie à 100%
- ✅ **WORKFLOW SIMPLIFIÉ** : Développez localement → Lancez BAT → Uploadez sur LWS

**Status :** ✅ **PRODUCTION READY - SYSTÈME AUTO-PILOTÉ + SÉCURITÉ RENFORCÉE**