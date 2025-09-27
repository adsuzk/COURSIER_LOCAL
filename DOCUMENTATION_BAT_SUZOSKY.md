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
`scripts\PROTECTION_GITHUB_SIMPLE.ps1`

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
- ✅ **Déplacement des scripts** vers dossier scripts/
- ✅ **Racine propre** sans fichiers de développement
- ✅ **Configuration LWS** appliquée automatiquement

### Utilisation :
```batch
# Exécution ponctuelle (pas en continu) :
C:\xampp\htdocs\COURSIER_LOCAL\BAT\SYNC_COURSIER_PROD.bat
```

### Script PowerShell associé :
`scripts\SYNC_COURSIER_PROD_LWS.ps1`

### Structure finale dans coursier_prod :
```
coursier_prod/
├── 📁 Tests/          ← Tous les fichiers de test/debug
├── 📁 scripts/        ← Scripts PowerShell et utilitaires
├── 📄 index.php       ← Fichiers de production à la racine
├── 📄 config.php      ← Configuration LWS appliquée
├── 📄 coursier.php    ← Interface coursier
├── 📄 admin.php       ← Interface admin
└── ... (autres fichiers de production)
```

### Exclusions automatiques :
- **Fichiers :** `*.md`, `*.ps1`, `*.log`, `*debug*`, `*test*`
- **Dossiers :** `Applications/`, `CoursierAppV7/`, `BAT/`, `DOCUMENTATION_FINALE/`

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
3. **Vérifier** la structure dans coursier_prod
4. **Redémarrer** PROTECTION_GITHUB.bat

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

### Version 27 Septembre 2025 :
- ✅ Séparation complète des deux scripts
- ✅ Correction confusion protection + sync
- ✅ Structure LWS optimisée
- ✅ Documentation complète mise à jour

**Fichiers supprimés** : `PROTECTION_AUTO.bat` (créait confusion)
**Nouveaux fichiers** : `PROTECTION_GITHUB.bat` + `SYNC_COURSIER_PROD.bat`