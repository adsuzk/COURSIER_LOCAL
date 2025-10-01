# 🗑️ FICHIERS OBSOLÈTES - À SUPPRIMER OU ARCHIVER

**Date:** 1er Octobre 2025  
**Raison:** Nettoyage après corrections et mise à jour documentation

---

## ✅ FICHIERS DÉJÀ SUPPRIMÉS

- ✅ `CORRECTION_NOTIFICATIONS_COURSIERS_01OCT2025.md` (remplacé par DOCUMENTATION_CORRECTIONS_COMPLETES_01OCT2025.md)

---

## 📦 FICHIERS À ARCHIVER (optionnel)

Ces fichiers peuvent être déplacés vers un dossier `archives/` s'ils ne sont plus utilisés:

### Sauvegardes (backup)
- `admin_commandes_enhanced_backup.php` - Version backup de admin_commandes_enhanced.php
  - ⚠️ **Action:** Vérifier si toujours nécessaire, sinon archiver

### Fichiers de debug/log
- `debug_connectivity.log` - Logs de connectivité
- `debug_requests.log` - Logs de requêtes
- `mobile_sync_debug.log` - Logs de synchronisation mobile
- `mobile_connection_log.txt` - Logs de connexion mobile
- `latest_logcat.txt` - Logs Android (ancien)
- `latest_logcat2.txt` - Logs Android (ancien)
  - ⚠️ **Action:** Archiver si plus de 30 jours, ou supprimer

### Scripts de debug anciens
- `debug_connectivity.php` - Script debug connectivité
- `debug_index_modules.php` - Script debug modules index
- `search_deep_coursier.php` - Script recherche coursier
- `db_check.php` - Script vérification base de données
- `perf_test.php` - Script test performance
  - ⚠️ **Action:** Garder uniquement si utilisés régulièrement, sinon archiver

### APK et fichiers Android
- `pulled_base_debug.apk` - APK debug (ancien)
- `pulled_base_debug.zip` - APK zippé (ancien)
- `pulled_base_debug2.apk` - APK debug 2 (ancien)
- `pulled_base_debug2.zip` - APK zippé 2 (ancien)
  - ⚠️ **Action:** Supprimer (fichiers volumineux, versions obsolètes)

### Scripts temporaires
- `quick_test_account.php` - Script test compte rapide
- `auto_db_importer.php` - Import automatique base de données
- `consolidate_docs.php` - Consolidation documentation
  - ⚠️ **Action:** Archiver si non utilisés depuis 30 jours

### Fichiers système temporaires
- `lockout.json` - Fichier de verrouillage
- `cookies.txt` - Cookies
- `query` - Fichier requête temporaire
  - ⚠️ **Action:** Supprimer (fichiers temporaires)

---

## 🚫 FICHIERS À NE PAS SUPPRIMER

### Scripts de test actuels (créés récemment)
- ✅ `test_systeme_commandes.php` - Test système complet (créé 01/10/2025)
- ✅ `debug_commandes_coursier.php` - Diagnostic coursier (créé 01/10/2025)

### Documentation à jour
- ✅ `DOCUMENTATION_CORRECTIONS_COMPLETES_01OCT2025.md` - Documentation principale (créé 01/10/2025)
- ✅ `README_DOCUMENTATION.md` - Index documentation (créé 01/10/2025)
- ✅ `SUPPRESSION_INDEX_PHP_01OCT2025.md` - Documentation suppression /index.php
- ✅ `DOCUMENTATION_FINALE.md` - Documentation système complète
- ✅ `DOCUMENTATION_FCM_FIREBASE_FINAL.md` - Documentation FCM
- ✅ `DOCUMENTATION_BAT_SUZOSKY.md` - Documentation Bat Suzosky
- ✅ Tous les autres fichiers DOCUMENTATION_*.md

### Fichiers de configuration essentiels
- ✅ `config.php` - Configuration principale
- ✅ `composer.json` - Dépendances PHP
- ✅ `package.json` - Dépendances Node (si existe)
- ✅ `php.ini` - Configuration PHP
- ✅ `.htaccess` - Configuration Apache (si existe)

### Fichiers Firebase
- ✅ `coursier-suzosky-*.json` - Credentials Firebase
- ✅ `google-services.json` - Services Google

### Scripts de nettoyage/maintenance
- ✅ `coursier_status_cleanup.php` - Nettoyage statuts coursiers
- ✅ `fcm_auto_cleanup.php` - Nettoyage automatique FCM
- ✅ `coursier_update_status.php` - Mise à jour statuts

### Fichiers HTML de test actifs
- ✅ `test_formulaire_coursier.html` - Formulaire test coursier
- ✅ `404.html` - Page erreur 404
- ✅ `default_index.html` - Index par défaut
- ✅ `cgu.html` - Conditions générales d'utilisation

---

## 📋 COMMANDES DE NETTOYAGE

### Pour archiver les fichiers obsolètes:
```powershell
# Créer dossier archives
New-Item -ItemType Directory -Path "c:\xampp\htdocs\COURSIER_LOCAL\archives" -Force

# Archiver les backups
Move-Item "c:\xampp\htdocs\COURSIER_LOCAL\admin_commandes_enhanced_backup.php" "c:\xampp\htdocs\COURSIER_LOCAL\archives\" -Force

# Archiver les logs anciens (plus de 30 jours)
$LogFiles = @(
    "debug_connectivity.log",
    "debug_requests.log",
    "mobile_sync_debug.log",
    "mobile_connection_log.txt",
    "latest_logcat.txt",
    "latest_logcat2.txt"
)
foreach ($log in $LogFiles) {
    $file = "c:\xampp\htdocs\COURSIER_LOCAL\$log"
    if (Test-Path $file) {
        $age = (Get-Date) - (Get-Item $file).LastWriteTime
        if ($age.Days -gt 30) {
            Move-Item $file "c:\xampp\htdocs\COURSIER_LOCAL\archives\" -Force
            Write-Host "✅ Archivé: $log (âge: $($age.Days) jours)"
        }
    }
}
```

### Pour supprimer les APK obsolètes:
```powershell
$ApkFiles = @(
    "pulled_base_debug.apk",
    "pulled_base_debug.zip",
    "pulled_base_debug2.apk",
    "pulled_base_debug2.zip"
)
foreach ($apk in $ApkFiles) {
    $file = "c:\xampp\htdocs\COURSIER_LOCAL\$apk"
    if (Test-Path $file) {
        $size = (Get-Item $file).Length / 1MB
        Remove-Item $file -Force
        Write-Host "✅ Supprimé: $apk ($([math]::Round($size, 2)) MB)"
    }
}
```

### Pour supprimer les fichiers temporaires:
```powershell
$TempFiles = @("lockout.json", "cookies.txt", "query")
foreach ($temp in $TempFiles) {
    $file = "c:\xampp\htdocs\COURSIER_LOCAL\$temp"
    if (Test-Path $file) {
        Remove-Item $file -Force
        Write-Host "✅ Supprimé: $temp"
    }
}
```

---

## ⚠️ AVERTISSEMENT

**AVANT de supprimer ou archiver des fichiers:**

1. ✅ Vérifier qu'ils ne sont pas utilisés par l'application en production
2. ✅ Faire une sauvegarde complète du projet
3. ✅ Tester l'application après suppression
4. ✅ Garder les archives pendant au moins 30 jours

**En cas de doute:** ARCHIVER plutôt que supprimer!

---

**Dernière mise à jour:** 1er Octobre 2025 - 07:15  
**Responsable:** Maintenance système
