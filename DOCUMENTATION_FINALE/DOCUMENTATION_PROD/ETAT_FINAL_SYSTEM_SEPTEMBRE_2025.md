# 📱 SYSTÈME SUZOSKY COURSIER - DOCUMENTATION FINALE SEPTEMBRE 2025

> **État du système :** ✅ **PRODUCTION READY + TÉLÉMÉTRIE AVANCÉE**
> **Date de mise à jour :** 18 septembre 2025 - **HOTFIX:** 26 septembre 2025
> **Version app :** 1.1+ (Télémétrie intégrée)
> **Status CinetPay :** ✅ Intégration complète
> **Télémétrie :** ✅ Monitoring intelligent actif
> **API Submit Order :** ✅ **CORRIGÉ** - Table clients restaurée et mapping priorité fixé

---

## 🎯 **RÉSUMÉ EXÉCUTIF**

### ✅ **Confirmations des questions clés étendues :**

1. **🚀 Mises à jour à distance : OUI - AVANCÉ**
   - Service `AutoUpdateService.kt` actif
   - API `/api/app_updates.php` + `/api/telemetry.php`
   - Upload APK = création automatique version avec rotation intelligente
   - **NOUVEAU** : Détection multi-répertoires (/admin/uploads/ + /Applications APK/Coursiers APK/release/)
   - **NOUVEAU** : Interface admin dual-version avec historique
   - **NOUVEAU** : Extraction automatique métadonnées Android (output-metadata.json)

2. **🔔 Notifications automatiques : OUI**
   - Détection temps réel des nouvelles commandes
   - Son continu + vibration jusqu'à action coursier
   - Integration `NotificationSoundService.kt`
   - API polling via `get_coursier_data.php`

3. **💰 Gestionnaire financier temps réel : OUI**
   - Solde affiché en temps réel dans l'app
   - Recharges CinetPay instantanées
   - Mise à jour automatique après paiement
   - Dashboard web avec actualisation live

4. **📊 TÉLÉMÉTRIE & MONITORING : NOUVEAU**
   - **Reconnaissance automatique** appareils et versions
   - **Tracking bugs** avec stack traces complètes
   - **Sessions utilisateur** et analytics comportementales
   - **Dashboard admin** avec stats temps réel
   - **Auto-détection mises à jour** par appareil

---

## 🏗️ **ARCHITECTURE TECHNIQUE FINALE MISE À JOUR**

### 📱 **APPLICATION ANDROID (Kotlin/Compose) - V1.1+**

#### **Services Principaux Étendus**
```kotlin
// AutoUpdateService.kt - Mises à jour automatiques
class AutoUpdateService : Service() {
    - Vérification périodique des versions
    - Téléchargement APK silencieux
    - Installation automatique avec permissions
    - Rapport de statut au serveur
}

// NotificationSoundService.kt - Notifications sonores
class NotificationSoundService(context: Context) {
    - Son continu pour nouvelles commandes
    - Vibration persistante
    - Sons de confirmation d'actions
}

// NOUVEAU : TelemetrySDK.kt - Monitoring intelligent
class TelemetrySDK {
    - Enregistrement automatique des appareils
    - Remontée crashes avec contexte complet
    - Tracking sessions et événements utilisateur
    - Heartbeat périodique pour vérifications MAJ
    - API sécurisée avec authentification
}
```
    - Vibration synchronisée
    - Arrêt sur acceptation/refus commande
    - MediaPlayer + Vibrator integration
}
```

#### **Écrans Principaux**
- `CoursierScreenNew.kt` : Interface principal avec notifications
- `WalletScreen.kt` : Gestionnaire financier temps réel
- `CoursesScreen.kt` : Gestion commandes avec sons
- `MainActivity.kt` : Point d'entrée avec auto-recharge

### 🌐 **BACKEND PHP/MySQL**

#### **APIs Essentielles**
```php
/api/get_coursier_data.php     // Données coursier + commandes temps réel
/api/init_recharge.php         // Initiation paiement CinetPay
/api/app_updates.php           // Gestion versions APK
/api/add_test_order.php        // Commandes test pour notifications
/api/cinetpay_callback.php     // Traitement retours paiement
/api/submit_order.php          // Soumission commandes [CORRIGÉ 2025-09-26]
```

#### **CORRECTIONS CRITIQUES 2025-09-26:**
- ✅ **Table clients restaurée** via `restore_clients_table_lws.php`
- ✅ **Mapping priorité fixé** : `'normal'` → `'normale'` (compatibilité ENUM)
- ✅ **Vérification tables robuste** via `information_schema`
- ✅ **Attribution coursiers réactivée** et fonctionnelle

#### **Base de Données**
```sql
-- Tables principales
clients_particuliers       // Coursiers avec balance
commandes_coursier         // Commandes avec notifications
recharges                  // Transactions recharges
app_versions              // Gestion versions APK
```

---

## 💳 **SYSTÈME PAIEMENT CINETPAY**

### **Credentials Production**
```php
// config.php - getCinetPayConfig()
'apikey'     => '8338609805877a8eaac7eb6.01734650'
'site_id'    => '219503' 
'secret_key' => '17153003105e7ca6606cc157.46703056'
'endpoint'   => 'https://api-checkout.cinetpay.com/v2/payment'
```

### **Flux de Recharge**
1. **App Android** : Utilisateur clique "Recharger"
2. **API init_recharge** : Génère transaction CinetPay
3. **Modal CinetPay** : S'ouvre automatiquement 
4. **Callback** : Met à jour `balance` en base
5. **App** : Récupère nouveau solde en temps réel

---

## 🔔 **SYSTÈME NOTIFICATIONS**

### **Détection Nouvelles Commandes**
```kotlin
// CoursierScreenNew.kt
LaunchedEffect(commandes.size) {
    if (commandes.size > previousCommandesCount) {
        hasNewOrder = true
        notificationService.startNotificationSound() // SON CONTINU
        notificationService.startVibration()         // VIBRATION
    }
    previousCommandesCount = commandes.size
}
```

### **Arrêt Notifications**
```kotlin
// Acceptation commande
onAcceptOrder = {
    notificationService.stopNotificationSound()
    notificationService.playActionSound() // Son confirmation
    hasNewOrder = false
}
```

---

## � **SYSTÈME TÉLÉMÉTRIE AVANCÉ - NOUVEAU**

### **🔍 Monitoring Intelligent des Appareils**

#### **Base de Données Télémétrie**
```sql
-- Tables principales
app_devices         -- Registre des appareils Android
app_versions        -- Versions disponibles avec auto-tracking
app_crashes         -- Crashes groupés intelligemment 
app_sessions        -- Sessions utilisateur détaillées
app_events          -- Événements et actions trackées
app_notifications   -- Système de notifications push
```

#### **API Télémétrie :** `/api/telemetry.php`
- **8 endpoints** spécialisés
- **Authentification** par API key sécurisée
- **Heartbeat** automatique pour vérification MAJ
- **Crash reporting** avec stack traces complètes
- **Session tracking** automatique
- **Event logging** personnalisé

#### **SDK Android Intégré**
```kotlin
// Initialisation automatique dans MainActivity
TelemetrySDK.initialize(this, baseUrl, apiKey)

// Fonctionnalités automatiques :
- Enregistrement appareil au 1er lancement
- Détection crashes avec handler global  
- Sessions start/stop automatiques
- Heartbeat périodique (vérif MAJ)
- Screen tracking via trackScreenView()
```

### **📱 Dashboard Admin Télémétrie**

#### **Interface :** `admin.php?section=app_updates`
- **📊 Stats temps réel** : Appareils totaux, actifs, avec bugs
- **📱 Répartition versions** : % adoption par version
- **🐛 Top bugs** : Crashes les plus fréquents avec contexte
- **⚠️ MAJ nécessaires** : Appareils avec versions obsolètes  
- **🕒 Activité récente** : Sessions des 24h avec détails

#### **Reconnaissance Automatique APK - MISE À JOUR 18/09/2025**
✅ **Nouvelle Architecture Upload :**
1. **Détection multi-répertoires** : `/admin/uploads/` + `/Applications APK/Coursiers APK/release/`
2. **Scan automatique** à chaque accès admin
3. **Métadonnées Android** extraites depuis `output-metadata.json`
4. **Rotation versions** : Nouvelle → Actuelle, Actuelle → Précédente
5. **Suppression automatique** anciennes versions (max 2 conservées)
6. **Interface admin unifiée** avec dual-version display
7. **URLs encodées** pour compatibilité caractères spéciaux

✅ **Cycle de Vie Versions :**
- **Upload nouveau APK** → Devient version actuelle automatiquement
- **Ancienne version actuelle** → Devient version précédente
- **Ancienne version précédente** → Supprimée de l'historique
- **Téléchargements** : Les 2 dernières versions disponibles
- **Interface** : Affichage dual avec boutons téléchargement

#### **Intelligence Bugs**
- **Groupement automatique** par `crash_hash`
- **Contexte complet** : écran, action utilisateur, mémoire
- **Compteurs occurrence** par appareil
- **Stack traces** complètes pour debugging
- **Résolution tracking** des bugs corrigés

---

## �🚀 **SYSTÈME MISE À JOUR AUTOMATIQUE ÉTENDU**

### **Vérification Périodique Intelligente**
```kotlin
// AutoUpdateService.kt + TelemetrySDK.kt
private suspend fun checkForUpdates(forceCheck: Boolean = false) {
    // Heartbeat télémétrie avec vérification MAJ
    val updateInfo = telemetrySDK.checkForUpdates()
    
    if (updateInfo?.updateAvailable == true) {
        if (updateInfo.isMandatory) {
            downloadAndInstallUpdate(updateInfo.downloadUrl)
        } else {
            // Proposer mise à jour optionnelle
            showUpdateDialog(updateInfo)
        }
    }
}
```

### **Upload APK = Version Automatique**
```php
// admin.php - Système automatisé
if (uploadAPK) {
    // 1. Sauvegarder fichier avec timestamp
    $safeBase = 'suzosky-coursier-' . date('Ymd-His') . '.apk';
    
    // 2. Créer entrée app_versions automatiquement
    $newVersionCode = $currentMaxVersion + 1;
    $versionName = "1." . ($newVersionCode - 1);
    
    // 3. Détection version depuis nom fichier
    if (preg_match('/v?(\d+\.\d+)/', $fileName, $matches)) {
        $versionName = $matches[1]; // Ex: "v1.2" -> "1.2"
    }
    
    // 4. Notification automatique aux appareils via heartbeat
    // 5. Dashboard admin mis à jour instantanément
}
```

### **Intelligence Version par Appareil**
```sql
-- Requête automatique dans dashboard admin
SELECT 
    d.device_id,
    d.device_model, 
    d.app_version_name as current_version,
    v.version_name as latest_version,
    CASE 
        WHEN v.version_code > d.app_version_code THEN 'UPDATE_NEEDED'
        ELSE 'UP_TO_DATE' 
    END as update_status
FROM app_devices d
CROSS JOIN (SELECT MAX(version_code) as version_code, version_name 
            FROM app_versions WHERE is_active = 1) v
```
```

### **Installation Silencieuse**
```kotlin
private suspend fun installApkSilently(apkFile: File) {
    val intent = Intent(Intent.ACTION_VIEW).apply {
        val apkUri = FileProvider.getUriForFile(context, "$packageName.fileprovider", apkFile)
        setDataAndType(apkUri, "application/vnd.android.package-archive")
        flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_GRANT_READ_URI_PERMISSION
    }
    startActivity(intent)
}
```

---

## 💰 **GESTIONNAIRE FINANCIER TEMPS RÉEL**

### **Affichage Solde App**
```kotlin
// CoursierScreenNew.kt - Données RÉELLES
CoursierScreenNew(
    balance = soldeReel.toInt(),           // Via get_coursier_data.php
    gainsDuJour = gainsDuJour.toInt(),     // Calculé en temps réel
    onRecharge = { amount -> /* CinetPay */ }
)
```

### **Backend Temps Réel**
```php
// get_coursier_data.php
$stmt = $pdo->prepare("SELECT balance FROM clients_particuliers WHERE id = ? AND type_client = 'coursier'");
$balance = floatval($coursier['balance'] ?? 0);

// Gains du jour calculés dynamiquement
$stmt = $pdo->prepare("SELECT SUM(prix_livraison) as gains FROM commandes_coursier WHERE coursier_id = ? AND DATE(date_commande) = CURDATE() AND statut = 'livree'");
```

### **Interface Web Coursier**
```javascript
// coursier.php - Actualisation auto
setInterval(loadStats, 30000);     // Stats toutes les 30s
setInterval(loadCommandes, 60000); // Commandes toutes les 60s

function loadStats() {
    // Met à jour solde, gains, commandes en temps réel
    fetch('coursier.php', {body: 'ajax=true&action=get_stats'})
}
```

---

## 🔧 **CORRECTIONS CRITIQUES APPORTÉES - MISE À JOUR**

### **1. Crash Application Android (MainActivity.kt)**
```kotlin
// ❌ AVANT : Crash au lancement
lateinit var apiService: ApiService

// ✅ APRÈS : Initialisation sécurisée + télémétrie
private val apiService by lazy { ApiService.create() }

// ✅ NOUVEAU : Crash reporting automatique
TelemetrySDK.getInstance()?.reportCrash(
    throwable = e,
    screenName = "MainActivity", 
    userAction = "App startup"
)
```

### **2. Erreurs Upload Admin (Headers déjà envoyés)**
```php
// ❌ AVANT : Warning headers already sent
// functions.php ligne 516 envoyait HTML avant redirect

// ✅ APRÈS : Logique upload déplacée AVANT tout HTML
// admin.php - traitement upload AVANT require functions.php
if ($_POST['action'] === 'upload_apk') {
    // Traitement complet ici
    header('Location: admin.php?section=applications&uploaded=1');
    exit; // AVANT tout HTML
}
require_once __DIR__ . '/functions.php';
```

### **3. Compilation Android (Redéclarations)**
```kotlin
// ❌ AVANT : Erreurs redeclaration classes
// Packages com/example/clonecoursierapp/ et com/suzosky/coursier/

// ✅ APRÈS : Nettoyage complet sources
// Suppression/neutralisation fichiers clone
// Résolution conflits CommandeModels, LocationService, etc.
// Build réussi avec télémétrie intégrée
```

### **4. Google Maps Android (Dépendances)**
```kotlin
// ❌ AVANT : Versions incompatibles maps/location/places
// ✅ APRÈS : Versions unifiées + documentation
implementation("com.google.android.gms:play-services-maps:19.0.0")
implementation("com.google.android.gms:play-services-location:21.3.0") 
implementation("com.google.maps.android:maps-compose:4.3.3")
implementation("com.google.android.libraries.places:places:3.5.0")

// + Documentation ANDROID_MAPS.md pour clé API Android-restricted
```

### **5. Système Finances (Synchronisation Automatique)**
```php
// ✅ NOUVEAU : Backfill automatique des comptes coursiers
// admin.php - À chaque ouverture admin
try {
    require_once __DIR__ . '/lib/finances_sync.php';
    $pdo_backfill = getDBConnection();
    backfillCourierAccounts($pdo_backfill); // Création auto comptes manquants
} catch (Throwable $e) {
    // Silencieux - ne bloque pas l'admin
}
```

### **6. Base de Données (Schéma Télémétrie)**
```sql
-- ✅ NOUVEAU : 6 tables télémétrie ajoutées
CREATE TABLE app_devices (
    device_id VARCHAR(128) PRIMARY KEY,
    courier_id INT(11) REFERENCES agents_coursiers(id),
    app_version_code INT(11) NOT NULL,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    -- ...
);

-- Vue analytique temps réel
CREATE VIEW view_device_stats AS
SELECT device_id, update_status, activity_status, crash_count
FROM app_devices d
-- Logique classification automatique
```

---

## 🎯 **ARCHITECTURE FICHIERS MISE À JOUR**

### **📱 Télémétrie Android**
```
CoursierAppV7/app/src/main/java/com/suzosky/coursier/
├── telemetry/
│   ├── TelemetrySDK.kt           # SDK principal (monitoring)
│   └── TelemetryInitializer.kt   # Auto-initialisation startup
├── MainActivity.kt               # Intégration télémétrie
└── services/
    └── AutoUpdateService.kt      # Service MAJ (existant)
```

### **🗄️ Backend Télémétrie**
```
coursier_prod/
├── database_telemetry_setup.sql # Script création tables
├── setup_telemetry.php          # Installation automatique  
├── api/
│   └── telemetry.php            # 8 endpoints API télémétrie
└── admin/
    ├── app_updates.php          # Interface dashboard
    ├── app_monitoring.php       # Dashboard complet télémétrie
    └── admin.php                # Upload APK automatisé
```

### **📚 Documentation Étendue**
```
DOCUMENTATION_FINALE/
├── ETAT_FINAL_SYSTEM_SEPTEMBRE_2025.md      # Ce fichier (mis à jour)
├── TELEMETRY_SYSTEM_COMPLETE.md             # Documentation télémétrie
├── ANDROID_MAPS.md                          # Guide Google Maps Android
├── CHANGelog_FINANCES_AUTOMATION_2025-09-18.md  # Changelog finances
└── ARCHITECTURE_TECHNIQUE_COMPLETE_V7.md    # Architecture technique
```

### **2. SSL/Réseau (NetworkSecurityConfig)**
```xml
<!-- network_security_config.xml -->
<network-security-config>
    <domain-config cleartextTrafficPermitted="true">
        <domain includeSubdomains="true">10.0.2.2</domain>
        <domain includeSubdomains="true">localhost</domain>
    </domain-config>
</network-security-config>
```

### **3. Base de Données (Schema)**
```sql
-- Colonnes manquantes ajoutées
ALTER TABLE clients_particuliers ADD COLUMN balance DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE clients_particuliers ADD COLUMN type_client ENUM('client','coursier') DEFAULT 'client';

-- Table commandes coursier créée
CREATE TABLE commandes_coursier (
    id int(11) NOT NULL AUTO_INCREMENT,
    coursier_id int(11) NOT NULL,
    -- ... autres colonnes
);
```

### **4. Apache Redirections (.htaccess)**
```apache
# Éviter redirections POST vers GET
RewriteCond %{THE_REQUEST} ^[A-Z]{3,}\s/+coursier_prod/api/([^\s?]*) [NC]
RewriteRule ^ /api/%1? [R=301,L]
```

### **5. CinetPay Integration**
```php
// API key corrigée (était dupliquée)
'apikey' => '8338609805877a8eaac7eb6.01734650', // ✅ Correcte
```

---

## 📋 **TESTS DE VALIDATION**

### **✅ Tests Réussis**
1. **App Android** : Connexion sans crash
2. **Notifications** : Son + vibration sur nouvelle commande  
3. **CinetPay** : Modal s'ouvre, transaction créée (Code 201)
4. **Mise à jour** : Service actif, vérification périodique
5. **Base données** : Toutes tables créées, données persistées
6. **APIs** : Toutes fonctionnelles avec authentification

### **🧪 Commandes de Test**
 ```bash
 # Test API recharge
 c:\xampp\php\php.exe Test/test_recharge_api.php

 # Test credentials CinetPay  
 c:\xampp\php\php.exe Test/test_cinetpay_simple.php

 # Test notification sonore
 http://localhost/coursier_prod/Test/test_notification_sound.html
 ```

---

## 🚦 **ÉTAT FINAL DU SYSTÈME**

### **🟢 FONCTIONNEL**
- ✅ Application Android stable
- ✅ Mises à jour automatiques à distance
- ✅ Notifications sonores nouvelles commandes  
- ✅ Système paiement CinetPay complet
- ✅ Gestionnaire financier temps réel
- ✅ Base de données complète
- ✅ APIs backend opérationnelles

### **📱 READY FOR PRODUCTION**
L'application est maintenant **entièrement fonctionnelle** et prête pour une utilisation en production. Tous les bugs critiques ont été corrigés et les fonctionnalités demandées sont implémentées.

### **🔄 MAINTENANCE**
- **Mises à jour** : Via `AutoUpdateService` - pas besoin de redistribuer APK
- **Monitoring** : Logs disponibles dans `/diagnostic_logs/`
- **Support** : Interface admin pour gestion finances et commandes

---

**📞 Support technique disponible 24/7**
**🎯 Mission accomplie - Système opérationnel**