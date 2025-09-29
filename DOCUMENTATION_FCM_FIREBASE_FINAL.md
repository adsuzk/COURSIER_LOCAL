# 🔥 DOCUMENTATION FCM FIREBASE - SYSTÈME SUZOSKY COURSIER

## 📋 ÉTAT ACTUEL DU SYSTÈME (Septembre 2025)

### ✅ COMPOSANTS FONCTIONNELS
- **Application Android** : Génère des tokens FCM réels depuis la recompilation avec `google-services.json` correct
- **Base de données** : Table `device_tokens` avec tokens authentiques 
- **API FCM v1** : Implémentée avec service account OAuth2
- **Interface de test** : `test_fcm_direct_interface.html` pour diagnostic complet

### 🚨 PROBLÈMES RÉSOLUS

#### 1. **Fichier google-services.json corrompu** ✅ RÉSOLU
**Problème** : Le fichier `google-services.json` était incomplet et manquait la section `firebase_messaging`
```json
// AVANT (CASSÉ)
"services": {
  "appinvite_service": {
    "other_platform_oauth_client": []
  }
}

// APRÈS (FONCTIONNEL) 
"services": {
  "appinvite_service": {
    "other_platform_oauth_client": []
  },
  "firebase_messaging": {
    "enabled": true,
    "sender_id": "55677959036"
  }
}
```

#### 2. **Configuration réseau Android** ✅ RÉSOLU
**Problème** : L'application Android n'arrivait pas à contacter le serveur local
- **IP mise à jour** : `192.168.1.4` (était 192.168.1.5)
- **Fichier** : `CoursierAppV7/local.properties`
```properties
debug.localHost=http://192.168.1.4
```

#### 3. **Initialisation Firebase dans l'application** ✅ RÉSOLU
**Problème** : Firebase n'était pas initialisé dans l'Application class
- **Fichier modifié** : `SuzoskyCoursierApplication.kt`
- **Ajout** : `FirebaseApp.initializeApp(this)` avec logs détaillés

## 🏗️ ARCHITECTURE TECHNIQUE

### 📱 Application Android
```kotlin
// SuzoskyCoursierApplication.kt - Initialisation Firebase
override fun onCreate() {
    super.onCreate()
    
    try {
        FirebaseApp.initializeApp(this)
        Log.d("SuzoskyApp", "✅ Firebase initialisé avec succès")
        
        // Forcer génération token FCM
        FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
            if (task.isSuccessful) {
                val token = task.result
                Log.d("SuzoskyApp", "🎫 Token FCM généré: ${token.substring(0, 20)}...")
                // Envoi au serveur via ApiService
            }
        }
    } catch (e: Exception) {
        Log.e("SuzoskyApp", "❌ Erreur Firebase: ${e.message}")
    }
}
```

### 🗄️ Base de Données
```sql
-- Table device_tokens (structure actuelle)
CREATE TABLE device_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token TEXT NOT NULL,
    coursier_id INT NULL,
    agent_id INT NULL,
    device_type VARCHAR(20) DEFAULT 'mobile',
    platform VARCHAR(20) DEFAULT 'android',
    app_version VARCHAR(20) DEFAULT '1.0',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_used TIMESTAMP NULL,
    device_info JSON NULL,
    last_ping TIMESTAMP NULL,
    token_hash VARCHAR(64) NULL
);

-- Exemple token réel généré
INSERT INTO device_tokens VALUES (
    12, 
    'c0oBBQQET2emLeC3LFuqj1:APA91bG7CN3O0OKstnGBr...', 
    5, 5, 'mobile', 'android', '1.1.0', 1, 
    '2025-09-28 12:40:31', '2025-09-28 12:42:36', 
    '2025-09-28 12:42:36', 1, NULL, NULL
);

### Message affiché quand aucun coursier n'est disponible

"Nos coursiers sont actuellement très sollicités. Restez sur cette page — des coursiers se libèrent dans un instant et le formulaire se rouvrira automatiquement pour vous permettre de commander immédiatement. Merci pour votre patience !"

### Nouveaux composants (Sept 2025)

- `lib/fcm_helper.php` : centralise la logique d'obtention d'un access token OAuth2 via le service account et l'envoi d'un message FCM v1 (fonction `sendFCMNotificationV1`). Réutilisé par `test_fcm_direct_sender.php` et par les scripts de maintenance.
- `Scripts/Scripts cron/fcm_validate_tokens.php` : parcourt les tokens `is_active = 1`, envoie un ping léger via FCM et désactive (`is_active = 0`) les tokens qui renvoient des erreurs permanentes (NOT_REGISTERED / INVALID_REGISTRATION). Le script est en dry-run par défaut ; passez `--apply` pour écrire en base.

**Exécution et planification :**

- `cron_master.php` a été mis à jour pour exécuter `fcm_validate_tokens.php` en mode dry‑run chaque minute (mais il n'applique pas les désactivations automatiquement à chaque exécution).
- Pour appliquer les désactivations automatiquement une fois par jour, planifiez un appel `php Scripts/Scripts\ cron/fcm_validate_tokens.php --apply` (ou activez l'appel `--apply` dans `cron_master.php` si vous le souhaitez).

**Exécution récente :**

- Backup avant application : `scripts/../logs/device_tokens_backup_20250929_230953.json`.
- Run `--apply` exécuté manuellement : tokens vérifiés = 1, tokens désactivés = 0 (aucune suppression effectuée lors de ce run).

### Détection de disponibilité (rappel et configuration)

La logique de disponibilité côté serveur utilise désormais une combinaison `is_active = 1` + fraîcheur de `last_ping` (par défaut 120 secondes). Vous pouvez :

- Changer la fenêtre via `FCM_AVAILABILITY_THRESHOLD_SECONDS` (secondes)
- Forcer la détection immédiate (ignorer la fraîcheur) via `FCM_IMMEDIATE_DETECTION=true`

Le script de validation active (`fcm_validate_tokens.php`) aide à garder la table `device_tokens` propre en désactivant automatiquement les tokens définitivement invalides.
```

### 🔧 API FCM Backend
```php
// test_fcm_direct_sender.php - Envoi FCM v1 avec OAuth2
function sendFCMNotificationV1($token, $message, $data = []) {
    // 1. Charger service account
    $serviceAccount = json_decode(file_get_contents(
        'coursier-suzosky-firebase-adminsdk-fbsvc-3605815057.json'
    ), true);
    
    // 2. Générer access token OAuth2
    $accessToken = getOAuth2AccessToken($serviceAccount);
    
    // 3. Construire payload FCM v1
    $payload = [
        'message' => [
            'token' => $token,
            'notification' => [
                'title' => '🚚 Suzosky Coursier',
                'body' => $message
            ],
            'data' => array_merge($data, [
                'sound' => 'suzosky_notification.mp3'
            ]),
            'android' => [
                'notification' => [
                    'channel_id' => 'commandes_channel',
                    'sound' => 'suzosky_notification.mp3'
                ]
            ]
        ]
    ];
    
    // 4. Envoyer via API v1
    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
    // ... envoi cURL avec Bearer token
}
```

## 🧪 TESTS ET DIAGNOSTIC

### Interface de Test Complète
- **URL** : `http://192.168.1.4/COURSIER_LOCAL/test_fcm_direct_interface.html`
- **Fonctions** :
  - ✅ Vérification tokens actifs en base
  - ✅ Test envoi FCM direct
  - ✅ Création commande + notification
  - ✅ Logs FCM détaillés
  - ✅ Diagnostic configuration Firebase

### Test E2E Complet  
- **URL** : `http://192.168.1.4/COURSIER_LOCAL/Tests/e2e_fullstack_runner.php`
- **Couverture** :
  - ✅ Récupération token réel via `latestTokenForCoursier()`
  - ✅ Envoi notification avec Suzosky ringtone
  - ✅ Simulation acceptation course sur mobile
  - ✅ Mise à jour timeline en temps réel

## 📂 FICHIERS CLÉS

### Configuration Firebase
```
📁 CoursierAppV7/app/
├── google-services.json                    ← CORRIGÉ avec firebase_messaging
└── local.properties                        ← IP mise à jour : 192.168.1.4

📁 Racine/
├── coursier-suzosky-firebase-adminsdk-*.json ← Service account OAuth2
└── test_fcm_direct_sender.php             ← API FCM v1 avec authentification
```

### Code Android
```
📁 CoursierAppV7/app/src/main/java/com/suzosky/coursier/
├── SuzoskyCoursierApplication.kt           ← Firebase.initializeApp() ajouté
├── MainActivity.kt                         ← Token registration forcée
└── messaging/FCMService.kt                 ← Service FCM (existant)
```

### Backend PHP
```
📁 Racine/
├── mobile_sync_api.php                     ← Actions register_token, get_tokens
├── test_fcm_direct_interface.html          ← Interface diagnostic complète
├── test_fcm_direct_sender.php              ← Backend test FCM v1
└── fcm_token_security.php                  ← Gestion sécurité tokens (fallback)
```

## 🚀 PROCÉDURE DÉPLOIEMENT

### 1. Application Android
```bash
# Vérifier configuration
cat CoursierAppV7/local.properties
# debug.localHost=http://192.168.1.4

# Compiler et installer
# Android Studio: Build > Clean Project > Rebuild Project
# Installer sur appareil via USB debugging
```

### 2. Serveur Backend
```bash
# Vérifier service account Firebase
ls -la coursier-suzosky-firebase-adminsdk-*.json

# Tester connectivité
curl http://192.168.1.4/COURSIER_LOCAL/test_fcm_direct_interface.html

# Vérifier base de données
mysql -u root coursier_local -e "SELECT COUNT(*) FROM device_tokens WHERE is_active=1;"
```

### 3. Tests de Validation
```bash
# 1. Test génération token Android
# Lancer l'app → Vérifier logs ADB → Token en base

# 2. Test envoi FCM
# Interface web → "ENVOYER NOTIFICATION DIRECTE"

# 3. Test E2E complet
# Interface E2E → "Lancer Test Complet"
```

## 🔧 DÉPANNAGE COURANT

### Problème : Pas de token généré
```bash
# Vérifications
1. google-services.json contient firebase_messaging ✅
2. Firebase.initializeApp() dans Application.onCreate() ✅  
3. IP correcte dans local.properties ✅
4. Application recompilée après modifications ✅
```

### Problème : Notification non reçue
```bash
# Tests séquentiels  
1. Token présent en base ? SELECT * FROM device_tokens;
2. Notification envoyée ? SELECT * FROM notifications_log_fcm;
3. Réponse FCM OK ? Vérifier response_data
4. Canal notification Android configuré ?
```

### Problème : Erreur OAuth2
```bash
# Vérifications service account
1. Fichier coursier-suzosky-firebase-adminsdk-*.json présent
2. project_id = "coursier-suzosky"  
3. private_key format valide
4. Permissions IAM Firebase Messaging
```

## 📊 MÉTRIQUES DE SUCCÈS

### ✅ Indicators de Fonctionnement
- **Tokens générés** : > 0 en table device_tokens
- **Notifications envoyées** : Status 'sent' en notifications_log_fcm  
- **Réponse FCM** : HTTP 200 avec message ID
- **Timeline mise à jour** : Temps réel < 2 secondes

### 🚨 Alerts à Surveiller
- **Tokens expirés** : is_active = 0
- **Échecs FCM** : Status 'failed' > 10%
- **Latence réseau** : > 5 secondes
- **Erreurs OAuth2** : Access token invalide

---

## 📝 CHANGELOG

### 2025-09-28 - Corrections Majeures
- ✅ **Fichier google-services.json** : Ajout section firebase_messaging manquante
- ✅ **Application Android** : Firebase.initializeApp() dans SuzoskyCoursierApplication  
- ✅ **Configuration réseau** : IP mise à jour 192.168.1.4
- ✅ **API FCM v1** : Implémentation complète avec OAuth2
- ✅ **Interface diagnostic** : test_fcm_direct_interface.html
- ❌ **API Legacy supprimée** : Plus d'utilisation de l'ancienne clé serveur

### Composants Obsolètes Supprimés
- ❌ `FCMManager` avec clé serveur legacy  
- ❌ Tokens factices/debug générés côté serveur
- ❌ Configuration IP hardcodée 192.168.1.5
- ❌ google-services.json sans firebase_messaging

---

## 🎯 PROCHAINES ÉTAPES

1. **Test Production** : Déploiement sur serveur LWS avec domaine
2. **Optimisation** : Réduction latence notification < 1 seconde  
3. **Monitoring** : Dashboard métriques FCM temps réel
4. **Sécurité** : Rotation automatique tokens expirés

**📞 Support** : Documentation mise à jour - Système FCM 100% fonctionnel avec tokens réels Android !

---

## ⚠️ SUPPRESSION ÉLÉMENTS OBSOLÈTES

### ❌ Fichiers/Méthodes supprimés ou dépréciés :
- ❌ **FCMManager avec server key legacy** : Remplacé par API v1 OAuth2
- ❌ **Tokens factices générés côté serveur** : Seuls tokens Android réels acceptés
- ❌ **register_device_token.php** : Remplacé par mobile_sync_api.php
- ❌ **Configuration IP hardcodée 192.168.1.5** : Mise à jour dynamique 192.168.1.4
- ❌ **google-services.json sans firebase_messaging** : Fichier corrigé obligatoire

### ✅ Architecture finale validée :
- ✅ **Application Android** : Génère tokens FCM authentiques
- ✅ **Backend PHP** : API FCM v1 avec service account OAuth2  
- ✅ **Base de données** : Tokens réels dans device_tokens
- ✅ **Tests complets** : Interface diagnostic + E2E runner
- ✅ **Notifications livrées** : Téléphone reçoit avec Suzosky ringtone

**🎯 RÉSULTAT : Système FCM production-ready avec 0% tokens factices !**