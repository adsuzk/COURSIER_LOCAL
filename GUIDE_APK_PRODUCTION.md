# GUIDE MISE À JOUR APK PRODUCTION

## 🚚 PROBLÈME APK NON INSTALLABLE

### ❌ **Causes possibles :**
1. **Signature différente** : APK signé avec un certificat différent
2. **Version inférieure** : Tentative d'installer une version plus ancienne
3. **Configuration URL** : L'app pointe encore vers localhost au lieu de la production
4. **Permissions modifiées** : Changements dans AndroidManifest.xml

## 🔧 **SOLUTIONS À VÉRIFIER :**

### 1. **Configuration URL Production**
Vérifier dans le code de l'app Android :
```java
// Fichier de configuration (ex: ApiConfig.java ou Constants.java)
public static final String BASE_URL = "https://coursier.conciergerie-privee-suzosky.com/";
// Au lieu de :
// public static final String BASE_URL = "http://localhost/coursier/";
```

### 2. **Endpoints API à mettre à jour :**
```java
// URL de base
private static final String BASE_URL = "https://coursier.conciergerie-privee-suzosky.com/";

// Endpoints principaux
public static final String LOGIN_ENDPOINT = BASE_URL + "api/index.php";
public static final String GET_ORDERS_ENDPOINT = BASE_URL + "api/get_coursier_orders.php";
public static final String GET_DATA_ENDPOINT = BASE_URL + "api/get_coursier_data.php";
public static final String UPDATE_STATUS_ENDPOINT = BASE_URL + "api/update_coursier_status.php";
public static final String FCM_REGISTER_ENDPOINT = BASE_URL + "api/register_device_token.php";
```

### 3. **Test de connectivité :**
Ajouter dans l'app un test de ping pour vérifier la connexion :
```java
// Test si l'API production répond
String testUrl = BASE_URL + "api/index.php?action=ping";
// Doit retourner un JSON avec success=true
```

### 4. **Gestion des certificats SSL :**
```java
// S'assurer que l'app accepte les certificats HTTPS
// Ajouter dans NetworkSecurityConfig si nécessaire
```

## 🏗️ **ÉTAPES REBUILD APK :**

### 1. **Vérifier la configuration :**
```bash
# Dans Android Studio
1. Ouvrir le projet
2. Chercher tous les "localhost" ou "192.168" ou "10.0.2.2"
3. Remplacer par "coursier.conciergerie-privee-suzosky.com"
4. Vérifier AndroidManifest.xml pour les permissions
```

### 2. **Increment version :**
```gradle
// Dans app/build.gradle
android {
    defaultConfig {
        versionCode 3  // Incrémenter
        versionName "1.2"  // Incrémenter
    }
}
```

### 3. **Clean & Rebuild :**
```bash
# Dans Android Studio
Build > Clean Project
Build > Rebuild Project
Build > Generate Signed Bundle/APK
```

### 4. **Test avant publication :**
```bash
# Tester sur un appareil de test
adb install -r app-release.apk
# Vérifier les logs
adb logcat --pid=$(adb shell pidof com.suzosky.coursier) | grep -E "(api|network|error)"
```

## 🔍 **DIAGNOSTIC RAPIDE APK ACTUEL :**

### Test des URLs dans l'APK :
```bash
# Extraire et analyser l'APK
aapt dump badging suzosky-coursier.apk
unzip suzosky-coursier.apk
grep -r "localhost\|192.168\|10.0.2.2" .
```

### Test API production avec curl :
```bash
# Tester les endpoints depuis n'importe où
curl "https://coursier.conciergerie-privee-suzosky.com/api/index.php?action=ping"
curl -X POST -H "Content-Type: application/json" -d '{"action":"test"}' "https://coursier.conciergerie-privee-suzosky.com/api/get_coursier_data.php"
```

## 🎯 **CHECKLIST FINALE :**
- [ ] Toutes les URLs pointent vers production
- [ ] Version incrémentée
- [ ] Certificat de signature identique
- [ ] Permissions AndroidManifest.xml correctes
- [ ] Test sur device physique OK
- [ ] API endpoints répondent en HTTPS

## 📱 **ALTERNATIVE TEMPORAIRE :**
En attendant la correction de l'APK, vous pouvez :
1. Désinstaller complètement l'ancienne version
2. Installer la nouvelle version
3. Ou utiliser un APK avec un nom de package différent pour tests

---
*Créé le 28 septembre 2025*