# DOCUMENTATION - SCRIPTS BAT SUZOSKY
**Date de mise à jour : 27 Septembre 2025**

## Architecture Corrigée - Deux Scripts Distincts

Le système de protection et synchronisation SUZOSKY a été restructuré en **deux scripts BAT distincts** pour éviter toute confusion et permettre une utilisation modulaire :

---

## 1. PROTECTION_GITHUB.bat 🛡️

**Objectif :** Protection automatique et sauvegarde continue vers GitHub uniquement

### Fonctionnalités :
- ✅ **Sauvegarde automatique** de COURSIER_LOCAL vers GitHub toutes les 5 secondes
- ✅ **Commits automatiques** avec timestamps pour traçabilité
- ✅ **Git Credential Manager** sécurisé (pas de tokens exposés)
- ✅ **Surveillance continue** des modifications de fichiers
- ✅ **Push automatique** vers le repository GitHub principal

### Utilisation :
```batch
# Double-cliquer sur le fichier ou exécuter :
C:\xampp\htdocs\COURSIER_LOCAL\BAT\PROTECTION_GITHUB.bat
```

### Script PowerShell associé :
`PS1\PROTECTION_GITHUB_SIMPLE.ps1`

### Comportement :
- **Mode continu** : Reste actif jusqu'à CTRL+C
- **Détection intelligente** : Ne commit que s'il y a des changements
- **Affichage minimal** : Points pour indiquer l'activité sans encombrer
- **Protection pure** : N'affecte PAS coursier_prod

---

## 2. SYNC_COURSIER_PROD.bat 🔄

**Objectif :** Synchronisation COURSIER_LOCAL → coursier_prod avec structure LWS optimisée

### Fonctionnalités :
- ✅ **Synchronisation complète** avec exclusions intelligentes
- ✅ **Réorganisation automatique** pour structure LWS
- ✅ **Exclusion des fichiers dev** (*.md, *.ps1, debug, tests)
- ✅ **Déplacement automatique** des tests vers dossier Tests/
- ✅ **Déplacement des scripts PowerShell** vers dossier `scripts/`
- ✅ **Préservation du dossier `Scripts/` (cron PHP)** contenant la stack automatisée (`Scripts/Scripts cron/...`).
- ✅ **Racine propre** sans fichiers de développement
- ✅ **Configuration LWS** appliquée automatiquement
- ✅ **Fichiers critiques `diagnostic_logs/*.php`** conservés pour `index.php`
- ✅ **Suppression automatique de `default_index.html`** (la page blanche LWS) afin que `index.php` soit servi immédiatement
- ✅ **Création/actualisation de `FORCE_PRODUCTION_DB`** pour forcer la configuration MySQL de production (CLI & CRON LWS)

### Utilisation :
```batch
# Exécution ponctuelle (pas en continu) :
C:\xampp\htdocs\COURSIER_LOCAL\BAT\SYNC_COURSIER_PROD.bat
```

### Script PowerShell associé :
`PS1\SYNC_COURSIER_PROD_LWS.ps1`

### Structure finale dans coursier_prod :
```
coursier_prod/
├── 📁 Tests/          ← Tous les fichiers de test/debug
├── 📁 Scripts/        ← Scripts PHP d'automatisation UNIQUEMENT (cron, migrations, sécurité)
├── 📄 index.php       ← Fichiers de production à la racine
├── 📄 config.php      ← Configuration LWS appliquée
├── 📄 FORCE_PRODUCTION_DB ← Flag généré automatiquement pour LWS
├── 📄 coursier.php    ← Interface coursier
├── 📄 admin.php       ← Interface admin
└── ... (autres fichiers de production)

❌ EXCLUS : PS1/ (tous les .ps1 isolés pour sécurité)

> 📦 **Déploiement LWS :** transférer ces éléments individuellement (contenu du dossier `coursier_prod`, pas le dossier parent) vers le répertoire web distant.
```

### Exclusions automatiques :
- **Fichiers :** `*.md`, `*.ps1`, `*.log`, `*debug*`, `*test*`
- **Dossiers :** `PS1/`, `Applications/`, `CoursierAppV7/`, `BAT/`, `DOCUMENTATION_FINALE/`, `Tests/`
- **Sécurité :** Dossier `PS1/` complètement exclu - aucun script PowerShell sur LWS

---

## Différences Clés Entre Les Deux Scripts

| Aspect | PROTECTION_GITHUB.bat | SYNC_COURSIER_PROD.bat |
|--------|----------------------|------------------------|
| **Cible** | GitHub Repository | Dossier coursier_prod local |
| **Mode** | Continu (5 secondes) | Ponctuel (à la demande) |
| **Exclusions** | Aucune (sauvegarde complète) | Nombreuses (production seulement) |
| **Structure** | Conservée identique | Réorganisée pour LWS |
| **Usage** | Protection quotidienne | Déploiement production |

---

## Workflow Recommandé

### Développement quotidien :
1. **Lancer PROTECTION_GITHUB.bat** au début de la journée
2. **Travailler normalement** - sauvegarde automatique
3. **Laisser tourner** - protection continue

### Déploiement production :
1. **Arrêter** la protection GitHub (CTRL+C)
2. **Exécuter SYNC_COURSIER_PROD.bat** pour synchroniser
3. **Lancer** la migration automatique :
	```powershell
	C:\xampp\php\php.exe Scripts\Scripts cron\automated_db_migration.php
	```
4. **Vérifier** la structure dans coursier_prod puis **uploader uniquement le contenu interne** (fichiers + sous-dossiers) vers la racine du site LWS
5. **Redémarrer** PROTECTION_GITHUB.bat

---

## Codes de Sortie et Diagnostics

### PROTECTION_GITHUB.bat :
- **Code 0** : Protection arrêtée normalement
- **Code 1** : Erreur de connexion GitHub
- **Affichage** : Messages colorés avec timestamps

### SYNC_COURSIER_PROD.bat :
- **Code 0** : Synchronisation réussie
- **Code 1** : Erreur de synchronisation
- **Vérification** : Structure finale validée automatiquement

---

## Maintenance et Troubleshooting

### Problème fréquent - Git Credential Manager :
Si erreur de connexion GitHub :
1. Ouvrir une invite PowerShell
2. Exécuter : `git config --global credential.helper manager-core`
3. Redémarrer PROTECTION_GITHUB.bat

### Vérification structure LWS :
Après SYNC_COURSIER_PROD.bat, vérifier :
- ✅ Aucun fichier .md à la racine de coursier_prod
- ✅ Dossier Tests/ contient les fichiers debug
- ✅ Dossier scripts/ contient les .ps1

---

## Historique des Versions

### Version 28 Septembre 2025 :
- ✅ **Dossier PS1/** : Isolation complète des scripts PowerShell
- ✅ **Migrations automatiques** : Détection + génération sans intervention
- ✅ **Sécurité renforcée** : Aucun .ps1 déployé en production
- ✅ **Structure optimisée** : Scripts PHP cron séparés des utilitaires PowerShell

### Version 27 Septembre 2025 :
- ✅ Séparation complète des deux scripts BAT
- ✅ Correction confusion protection + sync
- ✅ Structure LWS optimisée

**Évolution architecture** : `scripts/*.ps1` → `PS1/*.ps1` (isolation sécurisée)
**Nouveaux systèmes** : Auto-migration + génération intelligente