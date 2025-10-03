# 🚀 Guide de Démarrage Rapide - Suzosky Client App

## ⚡ Installation Rapide

### 1. Prérequis
```bash
# Vérifier Android Studio
Android Studio Ladybug ou supérieur

# Vérifier Kotlin
Kotlin 2.1.0+

# SDK Android
Min SDK: 24
Target SDK: 36
```

### 2. Cloner et Ouvrir
```bash
cd "C:\xampp\htdocs\COURSIER_LOCAL\CoursierSuzoskyApp Clt"
# Ouvrir dans Android Studio
```

### 3. Configuration Google API

#### a) Obtenir les clés
1. Aller sur [Google Cloud Console](https://console.cloud.google.com/)
2. Créer/sélectionner projet "Suzosky Client"
3. Activer les APIs :
   - Maps SDK for Android
   - Places API

#### b) Configurer les clés
Éditer `app/src/main/res/values/strings.xml` :
```xml
<resources>
    <string name="app_name">Suzosky Client</string>
    <string name="google_maps_key">AIzaSy...</string>
    <string name="google_places_key">AIzaSy...</string>
</resources>
```

### 4. Configuration Backend

#### a) Pour Émulateur Android
Dans `gradle.properties` :
```properties
LOCAL_LAN_IP=10.0.2.2
```

#### b) Pour Appareil Physique
1. Trouver votre IP locale :
```powershell
ipconfig
# Chercher "Adresse IPv4" sur votre réseau WiFi
# Exemple : 192.168.1.100
```

2. Mettre à jour `gradle.properties` :
```properties
LOCAL_LAN_IP=192.168.1.100
```

3. S'assurer que XAMPP est accessible :
```powershell
# Tester depuis le navigateur de votre téléphone
http://192.168.1.100/COURSIER_LOCAL/
```

### 5. Build et Run

#### Option A : Via Android Studio
1. Clic sur **Sync Project with Gradle Files**
2. Sélectionner votre device/émulateur
3. Clic sur **Run** ▶️

#### Option B : Via Ligne de Commande
```bash
# Build
.\gradlew assembleDebug

# Installer
.\gradlew installDebug

# Build + Install + Launch
.\gradlew installDebug
adb shell am start -n com.example.coursiersuzosky/.MainActivity
```

## 🎯 Première Utilisation

### 1. Lancer l'Application
L'app s'ouvre sur l'écran de connexion.

### 2. Se Connecter
- **Email :** Votre email client
- **Mot de passe :** Votre mot de passe

Ou créer un compte via le web d'abord.

### 3. Navigation
Une fois connecté, vous verrez 4 onglets :
- **🏠 Accueil** : Vue d'ensemble
- **🚛 Services** : Détails des services
- **📦 Commander** : Passer une commande
- **👤 Profil** : Votre compte

## 📱 Test de l'Application

### Test Commande Complète

1. **Aller sur "Commander"**
2. **Remplir le formulaire :**
   - Adresse départ : "Cocody Riviera" (autocomplete)
   - Adresse arrivée : "Plateau Abidjan" (autocomplete)
   - Téléphone expéditeur : +225 07 07 07 07 07
   - Téléphone destinataire : +225 07 07 07 07 08
   - Description : "Test commande app"
   - Priorité : Normale
   - Paiement : Espèces

3. **Observer :**
   - Carte interactive avec 2 marqueurs
   - Distance calculée automatiquement
   - Prix estimé affiché
   - Ligne de trajet sur la carte

4. **Soumettre la commande**
   - Message de confirmation
   - Commande créée dans la base

### Test Navigation

1. **Depuis Accueil :**
   - Cliquer sur "Commander Maintenant" → va vers l'onglet Commander
   - Cliquer sur "Voir tous les services" → va vers l'onglet Services

2. **Depuis Services :**
   - Scroller pour voir les 6 cartes de services
   - Voir les prix indicatifs en bas

3. **Depuis Profil :**
   - Tester les différents menus (affichent "Fonctionnalité en cours")
   - Cliquer sur "À propos" pour voir la version
   - Se déconnecter

## 🔧 Résolution de Problèmes

### Problème : "Google Play Services non disponible"
**Solution :**
1. Sur émulateur : Choisir une image avec Google APIs
2. Sur appareil : Mettre à jour Google Play Services

### Problème : "Places API error"
**Solutions :**
1. Vérifier que la clé API est correcte dans `strings.xml`
2. Vérifier que Places API est activée dans Google Cloud
3. Vérifier les restrictions de la clé (doit autoriser l'app)

### Problème : "Network error / Cannot connect"
**Solutions :**
1. **Émulateur :** Vérifier que `LOCAL_LAN_IP=10.0.2.2`
2. **Appareil physique :**
   - Vérifier IP locale : `ipconfig`
   - Appareil et PC sur même réseau WiFi
   - XAMPP en cours d'exécution
   - Firewall Windows autorise XAMPP
   - Tester dans navigateur mobile : `http://VOTRE_IP/COURSIER_LOCAL/`

### Problème : "Carte ne s'affiche pas"
**Solutions :**
1. Vérifier `google_maps_key` dans `strings.xml`
2. Vérifier Maps SDK activé dans Google Cloud
3. Vérifier SHA-1 enregistré dans Google Cloud :
   ```bash
   # Debug SHA-1
   keytool -list -v -keystore %USERPROFILE%\.android\debug.keystore -alias androiddebugkey -storepass android
   ```
4. Ajouter SHA-1 dans Google Cloud Console

### Problème : "Build error / Gradle sync failed"
**Solutions :**
1. File → Invalidate Caches and Restart
2. Supprimer `.gradle` et rebuild
3. Vérifier connexion internet (téléchargement dépendances)
4. Android Studio à jour

## 📊 Logs de Debug

### Voir les logs en temps réel
```bash
# Tous les logs de l'app
adb logcat -s MainActivity OrderScreen ApiClient

# Filtre par tag
adb logcat | findstr "Suzosky"

# Logs réseau
adb logcat | findstr "OkHttp"
```

### Diagnostics intégrés
En mode DEBUG, cliquer sur l'icône 🐛 en haut à droite pour voir :
- Package info
- SHA-1 signature
- Google Play Services status
- API keys (preview)

## 🎨 Personnalisation du Design

### Modifier les Couleurs
Éditer `ui/theme/Color.kt` :
```kotlin
val Gold = Color(0xFFD4A853)  // Changer ici
val Dark = Color(0xFF1A1A2E)  // Et ici
```

### Modifier le Logo
Remplacer dans `res/drawable/` ou utiliser Icon composable

### Ajouter des Services
Éditer `ui/ServicesScreen.kt` :
```kotlin
ServiceDetailCard(
    icon = "🆕",
    title = "Nouveau Service",
    description = "Description...",
    features = listOf("Feature 1", "Feature 2")
)
```

## 🚀 Build pour Production

### 1. Créer Keystore
```bash
keytool -genkey -v -keystore suzosky-release.keystore -alias suzosky -keyalg RSA -keysize 2048 -validity 10000
```

### 2. Configurer signing
Dans `app/build.gradle.kts` :
```kotlin
android {
    signingConfigs {
        create("release") {
            storeFile = file("../suzosky-release.keystore")
            storePassword = "VOTRE_PASSWORD"
            keyAlias = "suzosky"
            keyPassword = "VOTRE_PASSWORD"
        }
    }
    buildTypes {
        release {
            signingConfig = signingConfigs.getByName("release")
            // ...
        }
    }
}
```

### 3. Build Release APK
```bash
.\gradlew assembleRelease
```

APK généré dans : `app/build/outputs/apk/release/`

### 4. Mettre à jour l'URL de production
Dans `app/build.gradle.kts` :
```kotlin
release {
    buildConfigField("String", "BASE_URL", "\"https://coursier.conciergerie-privee-suzosky.com/api/\"")
}
```

## 📱 Distribution

### Google Play Store
1. Créer compte développeur Google Play
2. Générer App Bundle : `.\gradlew bundleRelease`
3. Upload sur Play Console
4. Remplir les métadonnées
5. Soumettre pour review

### Distribution Directe (APK)
1. Partager le fichier `app-release.apk`
2. L'utilisateur doit autoriser "Sources inconnues"
3. Installer l'APK

## 💡 Tips & Astuces

### Performance
- Éviter les opérations lourdes sur le thread principal
- Utiliser `LaunchedEffect` pour les coroutines
- Précharger les données fréquentes

### UX
- Toujours afficher des loading states
- Gérer les erreurs gracieusement
- Donner du feedback utilisateur

### Sécurité
- Ne jamais stocker de mots de passe en clair
- Utiliser HTTPS en production
- Valider toutes les entrées

## 📞 Support

**Problème technique ?**
- Vérifier ce guide en premier
- Consulter les logs (`adb logcat`)
- Vérifier la configuration réseau

**Besoin d'aide ?**
- Email : dev@conciergerie-privee-suzosky.com
- Documentation complète : `README_CLIENT_APP.md`

---

**Bon développement ! 🚀**
