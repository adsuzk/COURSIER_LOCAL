# 📱 Suzosky Client App - Application Cliente Android

## 🎯 Vue d'ensemble

Application Android cliente pour le service de coursier Suzosky, reproduisant fidèlement le design et les fonctionnalités de l'interface web `index.php`.

## ✨ Caractéristiques Principales

### 🏠 Écran d'Accueil (HomeScreen)
- **Hero Section** avec présentation du service
- **Preview des Services** (4 cartes principales)
- **Section Fonctionnalités** (pourquoi choisir Suzosky)
- **Statistiques** (10K+ livraisons, note 4.8⭐)
- **Call-to-Action** vers la commande

### 🚛 Écran Services (ServicesScreen)
Reproduction complète de la section services de l'index.php :
- **Livraison Express** 🚛 - Livraison en 30 minutes
- **Solutions Business** 🏢 - Services entreprises
- **Suivi Temps Réel** 📱 - Tracking GPS
- **Paiement Flexible** 💳 - Mobile Money, espèces, cartes
- **Assurance Colis** 🛡️ - Sécurité maximale
- **Service Premium** ⭐ - Coursiers certifiés, support 24/7

Chaque service dispose de :
- Description détaillée
- Liste de caractéristiques
- Design conforme à la charte Suzosky

### 📦 Écran Commande (OrderScreen)
Formulaire de commande complet avec :
- Autocomplete Google Places pour départ/destination
- Calcul automatique de distance et prix
- Carte interactive avec marqueurs
- Validation des champs
- Intégration API backend

### 👤 Écran Profil (ProfileScreen)
- Informations compte
- Menu complet :
  - Mes commandes
  - Informations personnelles
  - Adresses sauvegardées
  - Modes de paiement
  - Historique paiement
  - Centre d'aide
  - Support client
  - Notifications
  - Confidentialité
  - À propos
- Bouton déconnexion

## 🎨 Design System Suzosky

### Couleurs Officielles
```kotlin
val Gold = Color(0xFFD4A853)           // Or principal
val Dark = Color(0xFF1A1A2E)           // Fond sombre
val SecondaryBlue = Color(0xFF16213E)  // Bleu secondaire
val AccentBlue = Color(0xFF0F3460)     // Bleu accent
val AccentRed = Color(0xFFE94560)      // Rouge accent
val GoldLight = Color(0xFFF4E4B8)      // Or clair
```

### Typographie
- Font family : **System Default** (Material 3)
- Titres : Bold/ExtraBold
- Corps de texte : Regular/Medium

### Composants UI
- Cards avec Glass Morphism effect
- Rounded corners (12dp - 24dp)
- Elevation et shadows subtils
- Gradients verticaux pour les fonds

## 🏗️ Architecture

### Structure des Fichiers
```
app/src/main/java/com/example/coursiersuzosky/
├── MainActivity.kt              # Point d'entrée + Navigation
├── ui/
│   ├── HomeScreen.kt           # Écran d'accueil
│   ├── ServicesScreen.kt       # Écran services
│   ├── OrderScreen.kt          # Écran commande
│   ├── ProfileScreen.kt        # Écran profil
│   ├── LoginScreen.kt          # Écran connexion
│   └── theme/
│       ├── Color.kt            # Couleurs Suzosky
│       ├── Theme.kt            # Thème Material 3
│       └── Type.kt             # Typographie
├── net/
│   ├── ApiClient.kt            # Client HTTP
│   ├── ApiService.kt           # Endpoints API
│   ├── ApiConfig.kt            # Configuration
│   ├── SessionManager.kt       # Gestion session
│   └── *Models.kt              # Models de données
```

### Navigation
Navigation Compose avec Bottom Navigation Bar :
- **Home** (Accueil) - Route : "home"
- **Services** - Route : "services"  
- **Order** (Commander) - Route : "order"
- **Profile** (Profil) - Route : "profile"

### Authentification
- Écran de connexion au lancement
- Gestion session avec DataStore
- Cookies persistants (OkHttp)
- Déconnexion avec nettoyage complet

## 🔧 Configuration

### Prérequis
- Android Studio Ladybug ou supérieur
- Kotlin 2.1.0+
- Min SDK : 24 (Android 7.0)
- Target SDK : 36 (Android 14)

### API Keys
Ajouter dans `app/src/main/res/values/strings.xml` :
```xml
<string name="google_maps_key">VOTRE_CLE_MAPS</string>
<string name="google_places_key">VOTRE_CLE_PLACES</string>
```

### Configuration Backend
Modifier dans `app/build.gradle.kts` :
```kotlin
debug {
    buildConfigField("String", "BASE_URL", "\"http://VOTRE_IP/COURSIER_LOCAL/api/\"")
}
```

## 📦 Dépendances

### Core
- AndroidX Core KTX
- Lifecycle Runtime KTX
- Activity Compose
- Material 3
- Navigation Compose **2.7.7** ✨

### UI
- Compose BOM
- Material Icons Extended
- Animations

### Réseau
- OkHttp 4.x
- Logging Interceptor

### Google Services
- Play Services Maps
- Maps Compose
- Places API

### Autres
- DataStore Preferences
- Browser (Custom Tabs)

## 🚀 Build & Run

### Build Debug
```bash
./gradlew assembleDebug
```

### Installer sur appareil
```bash
./gradlew installDebug
```

### Créer APK Release
```bash
./gradlew assembleRelease
```

## 📱 Fonctionnalités par Écran

### HomeScreen ✅
- [x] Hero section avec branding Suzosky
- [x] Preview services (4 cartes)
- [x] Section fonctionnalités (4 items)
- [x] Statistiques du service
- [x] CTA vers commande
- [x] Navigation fluide

### ServicesScreen ✅
- [x] 6 cartes de services détaillées
- [x] Descriptions complètes
- [x] Listes de caractéristiques
- [x] Section tarifs avec prix indicatifs
- [x] Design fidèle à l'index.php

### OrderScreen ✅
- [x] Autocomplete adresses (Google Places)
- [x] Carte interactive
- [x] Calcul distance et prix automatique
- [x] Validation formulaire
- [x] Sélection mode paiement
- [x] Soumission commande

### ProfileScreen ✅
- [x] En-tête profil avec avatar
- [x] Menu complet (12 options)
- [x] Navigation sections
- [x] Bouton déconnexion
- [x] Infos version app

### LoginScreen ✅
- [x] Formulaire email/mot de passe
- [x] Validation champs
- [x] Gestion erreurs
- [x] Connexion API
- [x] Persistance session

## 🎯 Roadmap / Prochaines Étapes

### Phase 1 - Fonctionnalités de Base ✅
- [x] Navigation complète
- [x] Design Suzosky complet
- [x] Intégration API
- [x] Authentification

### Phase 2 - Améliorations UX (En cours)
- [ ] Animations de transition
- [ ] Loading states améliorés
- [ ] Gestion erreurs réseau
- [ ] Mode hors ligne basique
- [ ] Pull-to-refresh

### Phase 3 - Fonctionnalités Avancées
- [ ] Historique commandes
- [ ] Notifications push (FCM)
- [ ] Tracking en temps réel
- [ ] Paiements intégrés (CinetPay)
- [ ] Chat support
- [ ] Évaluation coursiers

### Phase 4 - Optimisations
- [ ] Cache images
- [ ] Optimisation performance
- [ ] Tests unitaires
- [ ] Tests UI
- [ ] CI/CD

## 📖 Comparaison avec index.php

| Fonctionnalité | index.php | Android App | Status |
|----------------|-----------|-------------|--------|
| Hero Section | ✅ | ✅ | ✅ Identique |
| Services Grid | ✅ | ✅ | ✅ Identique |
| Formulaire Commande | ✅ | ✅ | ✅ Adapté mobile |
| Google Maps | ✅ | ✅ | ✅ Native |
| Autocomplete | ✅ | ✅ | ✅ Places API |
| Calcul Prix | ✅ | ✅ | ✅ API |
| Navigation | Menu web | Bottom Nav | ✅ Adapté mobile |
| Authentification | Modal | Screen | ✅ Adapté mobile |
| Design Suzosky | CSS | Compose | ✅ Identique |

## 🐛 Debug & Diagnostics

En mode DEBUG, un bouton de diagnostics est disponible dans la TopAppBar :
- Package name
- Application ID
- SHA-1 signature
- Google Play Services status
- Places API initialization
- API keys preview

## 📄 Licence

© 2025 Suzosky Conciergerie Privée. Tous droits réservés.

## 👥 Contact

- Email : contact@conciergerie-privee-suzosky.com
- Support : 24/7 via l'application

---

**Version actuelle :** 1.0.0  
**Dernière mise à jour :** 03 Octobre 2025  
**Build minimal :** Android 7.0 (API 24)  
**Build cible :** Android 14 (API 36)
