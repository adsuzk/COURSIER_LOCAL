# DOCUMENTATION TECHNIQUE COMPLÈTE - APPLICATION COURSIER SUZOSKY
## Guide de Reproduction et Architecture Technique

### SOMMAIRE EXÉCUTIF

Cette documentation fournit un guide exhaustif pour reproduire l'application Coursier Suzosky, incluant l'architecture complète, les détails techniques, et tous les éléments nécessaires à la reproduction par un développeur.

## 1. ARCHITECTURE GÉNÉRALE DU SYSTÈME

### 1.1 Vue d'ensemble de l'architecture
```
┌─────────────────────────────────────────────────────────┐
│                 FRONTEND (ANDROID)                     │
│  ┌─────────────────┐ ┌─────────────────┐ ┌──────────────┐│
│  │   Login/Auth    │ │  Main Interface │ │   Settings   ││
│  └─────────────────┘ └─────────────────┘ └──────────────┘│
│              │                │               │          │
│              └────────────────┴───────────────┘          │
└─────────────────────────┬───────────────────────────────┘
                          │ HTTP/HTTPS API Calls
┌─────────────────────────┴───────────────────────────────┐
│                 BACKEND (PHP/WEB)                      │
│  ┌─────────────────┐ ┌─────────────────┐ ┌──────────────┐│
│  │   coursier.php  │ │    admin.php    │ │   api/       ││
│  └─────────────────┘ └─────────────────┘ └──────────────┘│
│              │                │               │          │
│              └────────────────┼───────────────┘          │
│                               │                          │
│  ┌─────────────────┐ ┌─────────────────┐ ┌──────────────┐│
│  │   CinetPay      │ │ Google Maps API │ │   Database   ││
│  │   Integration   │ │                 │ │   MySQL      ││
│  └─────────────────┘ └─────────────────┘ └──────────────┘│
└─────────────────────────────────────────────────────────┘
```

### 1.2 Technologies utilisées
- **Frontend Mobile**: Jetpack Compose (Android SDK 34)
- **Backend**: PHP 8.1+ avec sessions
- **Base de données**: MySQL 8.0
- **Authentification**: Sessions PHP + tokens sécurisés
- **Paiements**: CinetPay Integration
- **Maps**: Google Maps API + Google Places API
- **UI Framework**: Material Design 3

## 2. STRUCTURE DU PROJET ANDROID

### 2.1 Organisation des modules
```
CoursierAppV7/
├── app/
│   ├── src/main/java/com/suzosky/coursier/
│   │   ├── data/
│   │   │   └── models/           # Modèles de données
│   │   ├── network/             # Services API
│   │   ├── ui/
│   │   │   ├── components/      # Composants réutilisables
│   │   │   ├── screens/         # Écrans principales
│   │   │   └── theme/           # Thème et couleurs
│   │   ├── utils/               # Utilitaires
│   │   └── MainActivity.kt      # Point d'entrée
│   ├── src/main/res/
│   └── build.gradle.kts         # Configuration Gradle
└── gradle/                      # Configuration Gradle
```

### 2.2 Configuration Gradle (build.gradle.kts)
```kotlin
android {
    namespace = "com.suzosky.coursier"
    compileSdk = 34

    defaultConfig {
        applicationId = "com.suzosky.coursier"
        minSdk = 24
        targetSdk = 34
        versionCode = 1
        versionName = "1.0"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
        vectorDrawables {
            useSupportLibrary = true
        }
    }

    buildFeatures {
        compose = true
        buildConfig = true
    }
    
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_1_8
        targetCompatibility = JavaVersion.VERSION_1_8
    }
}

dependencies {
    // Compose BOM
    implementation(platform("androidx.compose:compose-bom:2023.10.01"))
    implementation("androidx.compose.ui:ui")
    implementation("androidx.compose.ui:ui-tooling-preview")
    implementation("androidx.compose.material3:material3")
    
    // Coil pour les images
    implementation("io.coil-kt:coil-compose:2.4.0")
    
    // Navigation
    implementation("androidx.navigation:navigation-compose:2.7.5")
    
    // ViewModel
    implementation("androidx.lifecycle:lifecycle-viewmodel-compose:2.7.0")
    
    // Networking
    implementation("com.squareup.retrofit2:retrofit:2.9.0")
    implementation("com.squareup.retrofit2:converter-gson:2.9.0")
    implementation("com.squareup.okhttp3:logging-interceptor:4.11.0")
    
    // Maps
    implementation("com.google.android.gms:play-services-maps:18.2.0")
    implementation("com.google.android.gms:play-services-location:21.0.1")
}
```

## 3. SYSTÈME DE COULEURS ET DESIGN SYSTEM

### 3.1 Couleurs Suzosky (coursier.php)
```css
:root {
    --primary-gold: #D4A853;
    --primary-dark: #1A1A2E;
    --secondary-blue: #16213E;
    --accent-blue: #0F3460;
    --accent-red: #E94560;
    --success-color: #27AE60;
    --glass-bg: rgba(255,255,255,0.08);
    --glass-border: rgba(255,255,255,0.2);
}
```

### 3.2 Correspondance Android (Color.kt)
```kotlin
val PrimaryGold = Color(0xFFD4A853)
val PrimaryDark = Color(0xFF1A1A2E)
val SecondaryBlue = Color(0xFF16213E)
val AccentBlue = Color(0xFF0F3460)
val AccentRed = Color(0xFFE94560)
val SuccessGreen = Color(0xFF27AE60)
val GlassBg = Color(0x14FFFFFF) // ~8% white
val GlassBorder = Color(0x33FFFFFF) // ~20% white
```

## 4. ARCHITECTURE DE L'INTERFACE UTILISATEUR

### 4.1 Navigation Bottom Bar
```kotlin
enum class NavigationTab(val title: String, val icon: ImageVector) {
    COURSES("Courses", Icons.Default.DirectionsBike),
    WALLET("Portefeuille", Icons.Default.AccountBalanceWallet),
    CHAT("Chat", Icons.Default.Chat),
    PROFILE("Profil", Icons.Default.Person)
}
```

### 4.2 Structure des écrans principaux
- **CoursierScreenNew.kt**: Écran principal avec bottom navigation
- **CoursesScreen.kt**: Liste des commandes + Google Maps
- **WalletScreen.kt**: Gestion des gains et rechargement
- **ChatScreen.kt**: Communication avec l'admin
- **ProfileScreen.kt**: Informations personnelles + déconnexion

## 5. INTÉGRATION API ET SERVICES EXTERNES

### 5.1 Configuration Google Maps
```kotlin
// Dans strings.xml ou local.properties
GOOGLE_MAPS_API_KEY = "AIzaSyBjUgj9KM0SNj847a_bIsf6chWp9L8Hr1A"
GOOGLE_PLACES_API_KEY = "AIzaSyAGKC21fGmY-k6i0dcY8MpBExa5IqqBXbE"
```

### 5.2 Service API Coursier
```kotlin
interface ApiService {
    @GET("api/orders.php")
    suspend fun getOrders(@Query("coursier_id") coursierId: Int): Response<OrdersResponse>
    
    @POST("api/auth.php")
    suspend fun login(@Body credentials: LoginRequest): Response<AuthResponse>
    
    @GET("api/profile.php")
    suspend fun getProfile(@Query("user_id") userId: Int): Response<ProfileResponse>
}
```

## 6. BASE DE DONNÉES ET MODÈLES

### 6.1 Tables principales (MySQL)
```sql
-- Table utilisateurs (coursiers)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    role ENUM('coursier', 'admin') DEFAULT 'coursier',
    statut ENUM('EN_LIGNE', 'HORS_LIGNE', 'EN_COURSE') DEFAULT 'HORS_LIGNE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table commandes
CREATE TABLE commandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_nom VARCHAR(100) NOT NULL,
    client_telephone VARCHAR(20),
    adresse_recuperation TEXT NOT NULL,
    adresse_livraison TEXT NOT NULL,
    statut ENUM('nouvelle', 'acceptee', 'en_cours', 'livree', 'annulee') DEFAULT 'nouvelle',
    coursier_id INT NULL,
    prix DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coursier_id) REFERENCES users(id)
);

-- Table paiements/rechargements
CREATE TABLE payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('recharge', 'gain') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    transaction_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### 6.2 Modèles de données Android
```kotlin
data class Commande(
    val id: Int,
    val clientNom: String,
    val clientTelephone: String,
    val adresseRecuperation: String,
    val adresseLivraison: String,
    val statut: String,
    val prix: Double,
    val createdAt: String
)

data class AuthResponse(
    val success: Boolean,
    val user: User?,
    val token: String?,
    val message: String
)
```

## 7. GUIDE DE REPRODUCTION ÉTAPE PAR ÉTAPE

### 7.1 Prérequis système
- **Android Studio**: Version 2023.1.1+
- **Kotlin**: 1.9.0+
- **PHP**: 8.1+
- **MySQL**: 8.0+
- **Serveur web**: Apache/Nginx

### 7.2 Configuration du projet Android
```bash
# 1. Cloner le projet
git clone [repository-url] CoursierApp

# 2. Ouvrir dans Android Studio
# File > Open > Sélectionner le dossier CoursierAppV7

# 3. Configurer les API Keys
# Dans app/src/main/res/values/strings.xml
<string name="google_maps_key">AIzaSyBjUgj9KM0SNj847a_bIsf6chWp9L8Hr1A</string>
<string name="google_places_key">AIzaSyAGKC21fGmY-k6i0dcY8MpBExa5IqqBXbE</string>
```

### 7.3 Configuration du backend PHP
```php
// config.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'coursier_db');

// CinetPay Configuration
define('CINETPAY_API_KEY', 'votre_api_key');
define('CINETPAY_SITE_ID', 'votre_site_id');

// Google APIs
define('GOOGLE_MAPS_API_KEY', 'AIzaSyBjUgj9KM0SNj847a_bIsf6chWp9L8Hr1A');
define('GOOGLE_PLACES_API_KEY', 'AIzaSyAGKC21fGmY-k6i0dcY8MpBExa5IqqBXbE');
```

### 7.4 Déploiement et tests
```bash
# 1. Démarrer le serveur web
php -S localhost:8000

# 2. Importer la base de données
mysql -u root -p coursier_db < database_setup.sql

# 3. Tester l'API
curl -X POST http://localhost:8000/api/auth.php \
  -H "Content-Type: application/json" \
  -d '{"username":"test","password":"test"}'

# 4. Builder l'application Android
./gradlew assembleDebug
```

## 8. FONCTIONNALITÉS SPÉCIFIQUES

### 8.1 Système de géolocalisation
- Intégration Google Maps pour affichage des courses
- Calcul automatique des trajets optimaux
- Suivi en temps réel de la position du coursier

### 8.2 Système de paiement CinetPay
- Rechargement du compte coursier
- Historique des transactions
- Notifications de paiement en temps réel

### 8.3 Chat en temps réel
- Communication coursier-admin
- Messages persistants
- Notifications push

## 9. SÉCURITÉ ET BONNES PRATIQUES

### 9.1 Authentification
- Tokens JWT pour les sessions
- Hachage bcrypt pour les mots de passe
- Validation côté serveur et client

### 9.2 Protection des données
- HTTPS obligatoire en production
- Sanitisation des entrées utilisateur
- Logs sécurisés des transactions

## 10. MAINTENANCE ET ÉVOLUTION

### 10.1 Monitoring
- Logs centralisés dans diagnostic_logs/
- Métriques de performance
- Alertes automatiques

### 10.2 Déploiement continu
- Tests automatisés avant déploiement
- Sauvegarde automatique de la base de données
- Rollback en cas de problème

---

**Version**: 2.0
**Dernière mise à jour**: 18 septembre 2025
**Auteur**: Équipe développement Suzosky

Cette documentation est maintenue et mise à jour à chaque évolution majeure de l'application.