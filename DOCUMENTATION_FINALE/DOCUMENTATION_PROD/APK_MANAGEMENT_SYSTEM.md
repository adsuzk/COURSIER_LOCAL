# 📱 SYSTÈME DE GESTION APK AUTO-DETECTION
*Mise à jour : 18 septembre 2025 - Documentation complète*

---

## 🎯 **PRÉSENTATION GÉNÉRALE**

Le système de gestion APK automatique détecte, indexe et gère les versions d'applications Android avec un historique des 2 dernières versions. Il permet une administration simplifiée via l'interface web.

### **🔑 Fonctionnalités Clés**
- ✅ **Détection automatique** des fichiers APK uploadés
- ✅ **Gestion de 2 versions** (actuelle + précédente)
- ✅ **Interface admin unifiée** avec téléchargements
- ✅ **Scanning multi-répertoires** pour flexibilité
- ✅ **Métadonnées extraites** automatiquement
- ✅ **Historique simplifié** sans accumulation

---

## 📂 **ARCHITECTURE DES FICHIERS**

### **📋 Fichiers Système**
```
admin/
├── update_apk_metadata.php     # Script de détection et indexation
├── auto_detect_apk.php         # Auto-initialisation admin
├── app_updates.php             # Interface de gestion versions
├── applications.php            # Page publique avec liens
└── uploads/
    ├── latest_apk.json         # Métadonnées des versions
    └── *.apk                   # Fichiers APK (ancien système)

Applications APK/Coursiers APK/release/
├── output-metadata.json        # Métadonnées Android build
└── *.apk                       # Fichiers APK (nouveau système)
```

### **🔧 Scripts de Support**
- **`admin/download_apk.php`** - Téléchargement sécurisé des APK
- **`applis.php`** - Page publique avec infos versions

---

## 🚀 **FONCTIONNEMENT DÉTAILLÉ**

### **1. 📡 Détection Automatique**

Le script `update_apk_metadata.php` scanne **deux répertoires** :
- `/admin/uploads/` (répertoire historique)
- `/Applications APK/Coursiers APK/release/` (nouveau répertoire production)

**Algorithme de détection :**
```php
// Scan des deux répertoires
$uploadDirs = [
    'admin/uploads/' => 'admin_upload',
    'Applications APK/Coursiers APK/release/' => 'release'
];

// Collecte et tri par date de modification
foreach ($uploadDirs as $dir => $source) {
    $apks = glob($baseDir . $dir . '*.apk');
    // Tri par date décroissante
    usort($apks, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
}
```

### **2. 📊 Extraction des Métadonnées**

**Source principale :** `output-metadata.json` (généré par Android Studio)
```json
{
  "version": 7,
  "artifactType": {
    "type": "APK",
    "kind": "Directory"
  },
  "applicationId": "com.example.clonecoursierapp",
  "variantName": "release",
  "elements": [{
    "type": "SINGLE",
    "filters": [],
    "attributes": [],
    "versionCode": 7,
    "versionName": "1.6.0",
    "outputFile": "app-release.apk"
  }]
}
```

**Extraction automatique :**
- `versionCode` : Numéro version interne Android
- `versionName` : Version utilisateur visible  
- `outputFile` : Nom du fichier APK
- `applicationId` : Identifiant unique app

### **3. 🔄 Gestion des Versions**

**Système à 2 versions maximum :**

| État | Description | Action |
|------|-------------|---------|
| **Version Actuelle** | Dernière APK uploadée | Utilisée par défaut |
| **Version Précédente** | Avant-dernière APK | Disponible pour rollback |
| **Anciennes versions** | Plus de 2 versions | **Supprimées** de l'historique |

**Structure JSON résultante :**
```json
{
  "file": "app-release-v1.6.0.apk",
  "url": "Applications%20APK/Coursiers%20APK/release/app-release-v1.6.0.apk",
  "source": "release",
  "version_code": 7,
  "version_name": "1.6.0",
  "apk_size": 15728640,
  "uploaded_at": "2025-09-18T14:30:00+00:00",
  "previous": {
    "file": "app-release-v1.5.2.apk",
    "url": "admin/uploads/app-release-v1.5.2.apk",
    "source": "admin_upload",
    "version_code": 6,
    "version_name": "1.5.2",
    "apk_size": 14521856,
    "uploaded_at": "2025-09-15T10:15:00+00:00"
  }
}
```

---

## 🖥️ **INTERFACES ADMINISTRATEUR**

### **📱 Page Gestion APK (`app_updates.php`)**

**Affichage dual-version :**
- **🟢 Version Actuelle** : Carte avec détails + bouton téléchargement
- **🟡 Version Précédente** : Carte avec détails + bouton téléchargement
- **📝 Formulaire** : Pré-rempli avec données version actuelle

**JavaScript de pré-remplissage :**
```javascript
function fillCurrentVersion() {
    document.getElementById('version_name').value = currentMetadata.version_name;
    document.getElementById('version_description').value = `Version ${currentMetadata.version_name} - Code ${currentMetadata.version_code}`;
}
```

### **🌐 Page Applications Publique (`applications.php`)**

**Liens de téléchargement dynamiques :**
- Version actuelle toujours disponible
- Version précédente si existante
- Texte descriptif automatique avec numéros de version

---

## 🔧 **CONFIGURATION ET UTILISATION**

### **📋 Upload d'une Nouvelle APK**

**Étapes automatiques :**
1. **Upload fichier** dans l'un des répertoires surveillés
2. **Détection** automatique au prochain accès admin
3. **Extraction** des métadonnées depuis `output-metadata.json`
4. **Mise à jour** de `latest_apk.json` 
5. **Rotation** des versions (actuelle → précédente)
6. **Suppression** de l'ancienne version précédente

**Recommandations upload :**
- Utiliser le répertoire `/Applications APK/Coursiers APK/release/`
- Inclure le fichier `output-metadata.json`
- Nommer les APK de façon descriptive
- Tester l'interface admin après upload

### **⚙️ Configuration Avancée**

**Variables de configuration dans `update_apk_metadata.php` :**
```php
$baseDir = '/path/to/website/root/';
$metadataFile = $baseDir . 'admin/uploads/latest_apk.json';
$uploadDirs = [
    'admin/uploads/' => 'admin_upload',
    'Applications APK/Coursiers APK/release/' => 'release'
];
```

**Personnalisation possible :**
- Ajouter d'autres répertoires de scan
- Modifier le nombre de versions conservées
- Changer le format des métadonnées
- Ajouter des validations spécifiques

---

## 🛡️ **SÉCURITÉ ET BONNES PRATIQUES**

### **🔒 Sécurité**
- ✅ **Téléchargements contrôlés** via `download_apk.php`
- ✅ **Validation des chemins** pour éviter directory traversal
- ✅ **Encodage URLs** pour caractères spéciaux
- ✅ **Accès admin protégé** par authentification

### **📋 Bonnes Pratiques**
- ✅ **Backup automatique** de l'ancienne version
- ✅ **Metadata JSON** pour traçabilité
- ✅ **Scanning multi-source** pour flexibilité
- ✅ **Interface unifiée** pour administration
- ✅ **Noms descriptifs** pour les fichiers APK

---

## 🔧 **MAINTENANCE ET DÉPANNAGE**

### **🚨 Problèmes Fréquents**

**APK non détectée :**
- Vérifier l'emplacement du fichier
- S'assurer que `output-metadata.json` existe
- Contrôler les permissions de fichiers
- Vérifier les logs d'erreur PHP

**Versions incorrectes :**
- Valider le contenu de `output-metadata.json`
- Vérifier la cohérence version APK vs metadata
- Contrôler l'ordre chronologique des fichiers

**Interface admin vide :**
- Exécuter manuellement `update_apk_metadata.php`
- Vérifier l'existence de `latest_apk.json`
- Contrôler les chemins de fichiers

### **🔧 Commandes de Diagnostic**

**Test manuel de détection :**
```bash
cd /path/to/website/admin/
php update_apk_metadata.php
```

**Vérification des métadonnées :**
```bash
cat admin/uploads/latest_apk.json | json_pp
```

**Test des liens de téléchargement :**
```bash
curl -I "https://site.com/admin/download_apk.php?file=filename.apk"
```

---

## 📈 **HISTORIQUE ET ÉVOLUTION**

### **🔄 Versions du Système**

| Version | Date | Améliorations |
|---------|------|---------------|
| **v1.0** | Sept 2025 | Détection basique répertoire `/admin/uploads/` |
| **v1.1** | Sept 2025 | Interface admin avec versions duales |
| **v2.0** | 18 Sept 2025 | **Scanning multi-répertoires + auto-detection** |

### **✨ Version 2.0 (Actuelle)**
- ✅ **Scan automatique** des deux répertoires
- ✅ **Priorisation intelligente** par date de modification
- ✅ **Traçabilité source** (admin_upload vs release)
- ✅ **URLs encodées** pour compatibilité espaces
- ✅ **Interface consolidée** avec historique complet

---

## 🎯 **RÉSUMÉ EXÉCUTIF**

Le système APK Auto-Detection offre une **gestion simplifiée et automatisée** des versions d'applications Android :

- 🔄 **Rotation automatique** : Nouvelle APK → Actuelle, Actuelle → Précédente
- 📱 **Interface unifiée** : Administration en un clic avec pré-remplissage
- 🔍 **Détection intelligente** : Scanning multi-répertoires avec priorisation
- 📊 **Métadonnées complètes** : Versions, tailles, dates automatiques
- 🛡️ **Téléchargements sécurisés** : URLs protégées et encodées

**Résultat :** Administration simplifiée avec **maximum 2 versions actives**, suppression automatique des versions anciennes, et interface admin moderne avec toutes les informations nécessaires.

---

*📝 Documentation générée le 18 septembre 2025*  
*🔄 Système intégré et opérationnel en production*