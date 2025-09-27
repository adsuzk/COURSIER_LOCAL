# 📱 DOCUMENTATION FINALE - SYSTÈME TÉLÉMÉTRIE & MONITORING ANDROID
*Mise à jour Septembre 2025 - Version 2.0*

## 🎯 SYSTÈME DE TÉLÉMÉTRIE INTELLIGENT

### 📊 **Vue d'ensemble**
Le système de télémétrie Suzosky permet un monitoring en temps réel de tous les appareils Android utilisant l'application Coursier. Il offre une visibilité complète sur :
- Les versions installées par appareil
- Les crashes et bugs en temps réel
- L'activité des utilisateurs
- Les besoins de mise à jour automatique

---

## 🗄️ **ARCHITECTURE BASE DE DONNÉES**

### **Tables Principales**

#### `app_devices` - Registre des Appareils
```sql
- device_id (UUID unique Android)
- courier_id (Lié aux coursiers)
- device_model, device_brand, android_version
- app_version_code, app_version_name (Version installée)
- first_install, last_seen (Tracking temporel)
- total_sessions, is_active
```

#### `app_versions` - Versions Disponibles
```sql
- version_code (1, 2, 3...)
- version_name (1.0, 1.1, 1.2...)
- apk_filename, apk_size
- release_notes, is_mandatory
- uploaded_at, is_active
```

#### `app_crashes` - Monitoring des Bugs
```sql
- device_id, crash_hash (Groupement intelligent)
- exception_class, exception_message, stack_trace
- screen_name, user_action (Contexte)
- occurrence_count, is_resolved
- memory_usage, battery_level, network_type
```

#### `app_sessions` - Sessions Utilisateur
```sql
- device_id, session_id
- started_at, ended_at, duration_seconds
- screens_visited, actions_performed
- crashed (Boolean si session terminée par crash)
```

#### `app_events` - Événements Utilisateur
```sql
- device_id, event_type, event_name
- screen_name, event_data (JSON)
- occurred_at, session_id
```

---

## 🔌 **API TÉLÉMÉTRIE**

### **Endpoint Principal :** `/api/telemetry.php`

#### **Authentification**
```http
X-API-Key: suzosky_telemetry_2025
X-Device-ID: {android_device_id}
X-App-Version: {version_name}
```

### **Endpoints Disponibles**

#### 1. **Enregistrement Appareil**
```http
POST /api/telemetry.php?endpoint=register_device
{
  "device_id": "abc123...",
  "device_model": "Samsung Galaxy S21",
  "device_brand": "Samsung", 
  "android_version": "13",
  "app_version_code": 2,
  "app_version_name": "1.1"
}
```

#### 2. **Heartbeat & Vérification MAJ**
```http
POST /api/telemetry.php?endpoint=heartbeat
Headers: X-Device-ID: abc123...

Response:
{
  "success": true,
  "update_available": true,
  "update_info": {
    "version_name": "1.2",
    "version_code": 3,
    "download_url": "/admin/download_apk.php?latest=1",
    "is_mandatory": false,
    "release_notes": "Corrections bugs Maps"
  }
}
```

#### 3. **Remontée de Crash**
```http
POST /api/telemetry.php?endpoint=report_crash
{
  "device_id": "abc123...",
  "exception_class": "NullPointerException",
  "exception_message": "Attempt to invoke virtual method",
  "stack_trace": "at com.suzosky.coursier...",
  "screen_name": "MapScreen",
  "user_action": "Clic sur ma position",
  "memory_usage": 156,
  "battery_level": 78,
  "network_type": "WIFI"
}
```

#### 4. **Gestion des Sessions**
```http
POST /api/telemetry.php?endpoint=start_session
POST /api/telemetry.php?endpoint=end_session
{
  "device_id": "abc123...",
  "session_id": "sess_unique_id",
  "screens_visited": 5,
  "actions_performed": 23,
  "crashed": 0
}
```

#### 5. **Tracking d'Événements**
```http
POST /api/telemetry.php?endpoint=track_event
{
  "device_id": "abc123...",
  "event_type": "USER_ACTION",
  "event_name": "button_click",
  "screen_name": "OrderScreen",
  "event_data": {"button": "submit_order"},
  "session_id": "sess_123"
}
```

---

## 📱 **SDK ANDROID - TelemetrySDK.kt**

### **Initialisation Automatique**
```kotlin
// Dans MainActivity.onCreate()
val telemetry = TelemetrySDK.initialize(
    context = this,
    baseUrl = "https://coursier.conciergerie-privee-suzosky.com",
    apiKey = "suzosky_telemetry_2025"
)
```

### **Fonctionnalités Automatiques**
- ✅ **Enregistrement appareil** au premier lancement
- ✅ **Sessions tracking** automatique (start/stop app)
- ✅ **Crash reporting** avec handler global
- ✅ **Heartbeat** périodique pour vérifier mises à jour
- ✅ **Screen tracking** via `trackScreenView()`
- ✅ **Events personnalisés** via `trackEvent()`

### **API Publique Simplifiée**
```kotlin
// Tracking manuel
telemetry.trackScreenView("OrderScreen")
telemetry.trackButtonClick("submit_order", "OrderScreen")
telemetry.trackFeatureUsed("maps_navigation", "MapScreen")

// Vérification mise à jour
val updateInfo = telemetry.checkForUpdates()
if (updateInfo?.isMandatory == true) {
    // Déclencher mise à jour obligatoire
}

// Reporting d'erreur manuel
telemetry.reportCrash(exception, "MapScreen", "User clicked location")
```

---

## 💻 **DASHBOARD ADMIN**

### **Interface :** `admin.php?section=app_updates`

#### **📊 Stats Temps Réel**
- **Appareils totaux** enregistrés
- **Actifs aujourd'hui** (dernières 24h)
- **Actifs cette semaine** (7 derniers jours)
- **Appareils avec bugs** (période récente)

#### **📱 Répartition des Versions**
- Tableau versions installées avec pourcentages
- Statut **ACTUEL** vs **OBSOLÈTE**
- Nombre d'appareils par version
- Indicateur visuel de répartition

#### **🐛 Monitoring des Bugs**
- **Top 10 crashes** les plus fréquents
- Groupement automatique par `crash_hash`
- Nombre d'appareils affectés
- Compteur d'occurrences
- Contexte (écran, action utilisateur)

#### **⚠️ Appareils Nécessitant MAJ**
- Liste des appareils avec version obsolète
- Temps depuis dernière connexion
- Nombre de bugs récents par appareil
- Modèle et marque de l'appareil

#### **🕒 Activité Récente**
- Sessions des dernières 24h
- Durée des sessions
- Nombre d'écrans visités
- Actions effectuées
- Indicateur de crash

---

## 🚀 **SYSTÈME D'UPLOAD INTELLIGENT**

### **Upload APK Automatisé**

Quand vous uploadez une APK via `admin.php?section=applications` :

1. **Création automatique** d'une entrée dans `app_versions`
2. **Auto-incrémentation** du `version_code`
3. **Détection version** depuis le nom de fichier
4. **Désactivation** des versions précédentes
5. **Notification** automatique aux appareils

```php
// Code automatique dans admin.php
$newVersionCode = $currentMaxVersion + 1;
$versionName = "1." . ($newVersionCode - 1);

// Détection version depuis nom fichier
if (preg_match('/v?(\d+\.\d+)/', $name, $matches)) {
    $versionName = $matches[1];
}
```

### **Détection Automatique des MAJ**

Chaque heartbeat d'appareil vérifie :
```sql
SELECT d.app_version_code as current_version, 
       v.version_code as latest_version
FROM app_devices d
CROSS JOIN (SELECT MAX(version_code) as version_code 
            FROM app_versions WHERE is_active = 1) v
WHERE d.device_id = ?
```

---

## 🔧 **INSTALLATION & CONFIGURATION**

### **1. Initialisation Base de Données**
```bash
php setup_telemetry.php
```

### **2. Structure des Fichiers**
```
coursier_prod/
├── database_telemetry_setup.sql    # Script SQL complet
├── setup_telemetry.php             # Installation automatique
├── api/
│   └── telemetry.php               # API endpoints
├── admin/
│   ├── app_updates.php            # Interface admin
│   └── app_monitoring.php         # Dashboard complet
└── CoursierAppV7/app/src/main/java/com/suzosky/coursier/
    ├── telemetry/
    │   ├── TelemetrySDK.kt        # SDK principal
    │   └── TelemetryInitializer.kt # Auto-initialisation
    └── MainActivity.kt             # Intégration
```

### **3. Configuration Android**
```kotlin
// build.gradle.kts - Dépendances
implementation("androidx.lifecycle:lifecycle-process:2.7.0")
implementation("com.squareup.okhttp3:okhttp:4.11.0")

// AndroidManifest.xml - Permissions
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
```

---

## 📈 **AVANTAGES SYSTÈME**

### **✅ Pour l'Administration**
- **Visibilité totale** sur l'écosystème mobile
- **Détection précoce** des problèmes
- **Statistiques d'usage** détaillées
- **Gestion versions** centralisée
- **Aide au debug** avec stack traces

### **✅ Pour les Développeurs**
- **Crash reports** automatiques avec contexte
- **Métriques performance** en temps réel
- **Adoption versions** trackée
- **Comportement utilisateurs** analysé

### **✅ Pour les Utilisateurs**
- **Mises à jour transparentes** (via heartbeat)
- **App plus stable** (bugs détectés rapidement)
- **Support technique** amélioré
- **Expérience optimisée** basée sur les données

---

## 🔐 **SÉCURITÉ & CONFIDENTIALITÉ**

### **Protection des Données**
- **Anonymisation** : Seul l'Android ID est collecté
- **Pas de données personnelles** (nom, email, etc.)
- **Chiffrement HTTPS** pour toutes les communications
- **API Key** pour authentifier les requêtes

### **Conformité RGPD**
- Données techniques uniquement
- Pas de tracking publicitaire
- Retention limitée dans le temps
- Option de désactivation possible

---

## 🎯 **CONCLUSION**

Le système de télémétrie Suzosky offre une solution complète de monitoring mobile avec :

1. **👁️ Visibilité totale** sur l'écosystème Android
2. **🔄 Automatisation complète** des processus
3. **📊 Analytics avancées** pour la prise de décision
4. **🛠️ Debugging efficace** avec contexte complet
5. **🚀 Évolutivité** pour de nouvelles fonctionnalités

**Le système fonctionne désormais comme une solution enterprise avec reconnaissance automatique des versions, bugs tracking intelligent et monitoring temps réel des appareils coursiers !**