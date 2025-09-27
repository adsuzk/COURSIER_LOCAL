# 📚 DOCUMENTATION COMPLÈTE SUZOSKY COURSIER
**Version Consolidée Finale | Date : 27 Septembre 2025 | Statut : Production Ready**

---

## 📋 TABLE DES MATIÈRES

1. [Présentation Générale](#1-présentation-générale)
2. [Architecture Technique](#2-architecture-technique)
3. [Installation et Déploiement](#3-installation-et-déploiement)
4. [Interface Web](#4-interface-web)
5. [Application Mobile Android](#5-application-mobile-android)
6. [APIs et Intégrations](#6-apis-et-intégrations)
7. [Base de Données](#7-base-de-données)
8. [Corrections et Mises à Jour Récentes](#8-corrections-et-mises-à-jour-récentes)
9. [Guide d'Administration](#9-guide-dadministration)
10. [Sécurité et Protection](#10-sécurité-et-protection)
11. [Tests et Validation](#11-tests-et-validation)
12. [Guides Utilisateur](#12-guides-utilisateur)

---

# 1. PRÉSENTATION GÉNÉRALE

## 🎯 Vue d'ensemble

**Suzosky Coursier V7.0** est une plateforme complète de livraison pour Abidjan, Côte d'Ivoire, comprenant :

- ✅ **Interface Web Responsive** - Commandes clients et administration
- ✅ **Application Android Native** - Pour les coursiers (Jetpack Compose)
- ✅ **APIs REST PHP** - Communication mobile-web sécurisée
- ✅ **Intégration CinetPay** - Paiements Mobile Money
- ✅ **Google Maps SDK** - Géolocalisation et navigation
- ✅ **Système de chat** - Support temps réel
- ✅ **Portefeuille digital** - Gestion financière complète

## 🏢 Environnements

### Production (LWS)
- **URL** : `https://conciergerie-privee-suzosky.com`
- **Serveur** : 185.98.131.214:3306
- **Base** : `conci2547642_1m4twb`
- **PHP** : 8.2+ avec extensions MySQL/GD/Curl

### Développement Local
- **URL** : `http://localhost/COURSIER_LOCAL`
- **Serveur** : XAMPP (Apache + MySQL + PHP 8.2)
- **Base** : `coursier_local`

## 📊 Fonctionnalités Principales

### Pour les Clients
- Commande en ligne avec géolocalisation
- Calcul automatique des prix selon la distance
- Paiement Mobile Money (Orange/MTN) ou espèces
- Suivi en temps réel des livraisons
- Historique des commandes

### Pour les Coursiers
- Application Android dédiée
- Réception des commandes par notification
- Navigation GPS intégrée
- Gestion du portefeuille et des gains
- Chat support intégré

### Pour les Administrateurs
- Dashboard de gestion complète
- Suivi des coursiers en temps réel
- Gestion des commandes et facturation
- Statistiques et rapports
- Système de support client

---

# 2. ARCHITECTURE TECHNIQUE

## 🏗️ Architecture Système

### Stack Technologique

#### Backend
- **PHP 8.2+** avec PDO MySQL
- **Base de données** : MySQL 8.0+
- **Serveur Web** : Apache avec mod_rewrite
- **API REST** : Endpoints sécurisés avec authentification JWT

#### Frontend Web
- **HTML5/CSS3** avec responsive design
- **JavaScript ES6+** avec modules
- **Google Maps JavaScript API** 
- **Material Design** adapté aux couleurs Suzosky

#### Mobile Android
- **Kotlin** avec Jetpack Compose
- **Architecture MVVM** + Repository Pattern
- **Dependency Injection** avec Hilt
- **Base locale** : Room Database
- **Réseau** : Retrofit2 + OkHttp

### Composants Principaux

```
┌─── Interface Web (PHP/JS) ───┐    ┌─── App Android (Kotlin) ───┐
│  • Commandes clients         │    │  • Interface coursiers     │
│  • Administration            │◄──►│  • Géolocalisation         │
│  • Google Maps Web           │    │  • Google Maps Mobile      │
│  • Paiements CinetPay        │    │  • Notifications FCM       │
└───────────────────────────────┘    └─────────────────────────────┘
                │                                    │
                └──────── APIs REST PHP ─────────────┘
                              │
                    ┌─── Base MySQL ───┐
                    │  • Commandes     │
                    │  • Coursiers     │
                    │  • Clients       │
                    │  • Transactions  │
                    └──────────────────┘
```

## 🔧 Structure des Dossiers

```
COURSIER_LOCAL/
├── api/                    # APIs REST dédiées mobile
│   ├── agent_auth.php     # Authentification mobile (matricule/password)
│   ├── auth.php           # Authentification web (email/password)
│   ├── orders.php         # Gestion commandes API
│   └── ...                # Autres endpoints API
├── assets/                 # CSS, JS, images
├── BAT/                   # Scripts Windows automation
├── sections_index/        # Modules PHP interface web
├── CoursierAppV7/         # Application Android
├── admin/                 # Interface administration
├── database/              # Scripts SQL et migrations
├── _sql/                  # Dumps et sauvegardes
├── config.php             # Configuration centrale
├── index.php              # Page d'accueil
├── coursier.php           # Interface coursiers WEB (navigateur)
├── admin.php              # Dashboard admin
└── DOCUMENTATION_FINALE/  # Documentation complète
```

---

# 3. INSTALLATION ET DÉPLOIEMENT

## 🚀 Installation Locale (XAMPP)

### Prérequis
- **XAMPP** avec PHP 8.2+, MySQL, Apache
- **Git** pour la gestion des versions
- **Node.js** (optionnel pour certains outils)

### Étapes d'installation

1. **Cloner le repository**
```bash
cd C:\xampp\htdocs
git clone https://github.com/adsuzk/COURSIER_LOCAL.git
```

2. **Configuration Apache**
```apache
# Dans httpd.conf, activer mod_rewrite
LoadModule rewrite_module modules/mod_rewrite.so

# Ajouter un VirtualHost (optionnel)
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/COURSIER_LOCAL"
    ServerName coursier.local
    <Directory "C:/xampp/htdocs/COURSIER_LOCAL">
        AllowOverride All
    </Directory>
</VirtualHost>
```

3. **Créer la base de données**
```sql
CREATE DATABASE coursier_local;
-- Importer le dump depuis _sql/coursier_local_structure.sql
```

4. **Configuration**
```php
// Dans config.php, vérifier les paramètres locaux
$config['db']['development'] = [
    'host'     => '127.0.0.1',
    'port'     => '3306', 
    'name'     => 'coursier_local',
    'user'     => 'root',
    'password' => '',
];
```

5. **Permissions**
```bash
# Windows : Donner droits lecture/écriture au dossier
icacls "C:\xampp\htdocs\COURSIER_LOCAL" /grant Everyone:(OI)(CI)F
```

## 🌐 Déploiement Production (LWS)

### Configuration Serveur

1. **Upload des fichiers**
```bash
# Via FTP/SFTP vers le répertoire web
# Structure recommandée :
/www/
├── api/
├── assets/ 
├── sections_index/
├── config.php
├── index.php
└── ...
```

2. **Configuration base de données**
```php
// config.php - Production
$config['db']['production'] = [
    'host'     => '185.98.131.214',
    'port'     => '3306',
    'name'     => 'conci2547642_1m4twb',
    'user'     => 'conci2547642_1m4twb', 
    'password' => 'wN1!_TT!yHsK6Y6',
];
```

3. **Variables d'environnement**
```bash
# Via .htaccess ou panel admin
SetEnv ENVIRONMENT production
SetEnv DB_HOST 185.98.131.214
```

4. **Vérification déploiement**
```bash
# Test des APIs
curl https://conciergerie-privee-suzosky.com/api/health.php

# Test interface
curl https://conciergerie-privee-suzosky.com/
```

---

# 4. INTERFACE WEB

## 🖥️ Pages Principales

### Page d'Accueil (`index.php`)

**Fonctionnalités :**
- Formulaire de commande avec Google Maps
- Autocomplétion d'adresses (Google Places)
- Calcul automatique des prix selon la distance
- Gestion des modes de paiement
- Timeline de suivi en temps réel

**Sections modulaires :**
```php
sections_index/
├── header.php              # En-tête avec logo
├── order_form.php          # Formulaire principal 
├── map.php                 # Carte Google Maps
├── services.php            # Présentation services
├── footer_copyright.php    # Pied de page
├── modals.php             # Popups et dialogs
├── chat_support.php       # Widget chat
└── js_*.php               # Modules JavaScript
```

### Interface Coursiers (`coursier.php`)

**Dashboard coursier WEB avec :**
- Liste des commandes disponibles
- Carte avec géolocalisation temps réel
- Gestion du statut (En ligne/Hors ligne)
- Historique des livraisons
- Portefeuille et gains

⚠️ **IMPORTANT** : `coursier.php` est l'interface WEB pour navigateur. 
L'application mobile Android utilise les endpoints API dédiés dans `/api/` (voir section APIs ci-dessous).

### Administration (`admin.php`)

**Fonctionnalités admin :**
- Vue d'ensemble des commandes
- Gestion des coursiers
- Statistiques et rapports
- Configuration système
- Gestion des utilisateurs

## 🎨 Design System

### Couleurs Suzosky
```css
:root {
    --primary-gold: #D4A853;      /* Or principal */
    --primary-dark: #1A1A2E;      /* Bleu marine */
    --secondary-blue: #16213E;     /* Bleu secondaire */
    --accent-blue: #0F3460;       /* Bleu accent */
    --accent-red: #E94560;        /* Rouge accent */
    --success-color: #28a745;     /* Vert succès */
    --glass-bg: rgba(255,255,255,0.08);  /* Effet verre */
}
```

### Composants UI

**Glass Morphism :**
- Cartes semi-transparentes avec effet de flou
- Bordures subtiles avec gradient
- Ombres portées douces

**Responsive Design :**
- Breakpoints : 768px (tablet), 1024px (desktop)
- Navigation mobile avec hamburger menu
- Formulaires adaptatifs

---

# 5. APPLICATION MOBILE ANDROID

## 📱 Architecture MVVM

### Structure Packages
```kotlin
com.suzosky.coursier/
├── data/
│   ├── local/        # Room Database
│   │   ├── dao/      # Data Access Objects
│   │   ├── entities/ # Entités Room
│   │   └── database/ # Configuration DB
│   ├── remote/       # Services API
│   │   ├── dto/      # Data Transfer Objects
│   │   └── api/      # Interfaces Retrofit
│   └── repository/   # Repository Pattern
├── di/               # Dependency Injection (Hilt)
├── ui/
│   ├── screens/      # Écrans Compose
│   │   ├── courses/  # Gestion livraisons
│   │   ├── wallet/   # Portefeuille
│   │   ├── chat/     # Support chat
│   │   └── profile/  # Profil coursier
│   ├── components/   # Composables réutilisables
│   ├── theme/        # Material 3 Theme
│   └── navigation/   # Navigation Compose
├── viewmodel/        # ViewModels avec StateFlow
└── utils/            # Extensions et utilitaires
```

## 🎯 Écrans Principaux

### 1. CoursesScreen - Gestion des Livraisons

**Fonctionnalités :**
- Google Maps intégré (300dp)
- Timeline interactive 6 étapes :
  - `PENDING` → `ACCEPTED` → `PICKUP_ARRIVED` → `PICKED_UP` → `DELIVERY_ARRIVED` → `DELIVERED`
- Badge commandes en attente avec compteur
- Actions contextuelles par étape
- Navigation GPS intégrée

```kotlin
@Composable
fun CoursesScreen(
    viewModel: CoursesViewModel = hiltViewModel(),
    onNavigateToMap: (String) -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()
    
    Column {
        // Google Maps Section
        AndroidView(
            modifier = Modifier.height(300.dp),
            factory = { context ->
                MapView(context).apply {
                    onCreate(Bundle())
                    getMapAsync { googleMap ->
                        // Configuration carte
                    }
                }
            }
        )
        
        // Timeline Section
        LazyColumn {
            items(uiState.commandes) { commande ->
                CommandeCard(
                    commande = commande,
                    onAccept = { viewModel.accepterCommande(it) },
                    onUpdateStatus = { id, status -> 
                        viewModel.updateStatut(id, status) 
                    }
                )
            }
        }
    }
}
```

### 2. WalletScreen - Portefeuille Digital (696 lignes)

**Fonctionnalités complètes :**
- Balance Card avec gradient Suzosky
- Système recharge avec CinetPay :
  - Montants rapides : 2K, 5K, 10K, 20K FCFA
  - Montant personnalisé avec validation
- Suivi gains par période (Daily/Weekly/Monthly)
- Historique transactions avec statuts colorés

```kotlin
@Composable
fun WalletScreen(
    viewModel: WalletViewModel = hiltViewModel()
) {
    val walletState by viewModel.walletState.collectAsState()
    
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // Balance Card
        item {
            BalanceCard(
                balance = walletState.currentBalance,
                onRecharge = { amount -> viewModel.initiateRecharge(amount) }
            )
        }
        
        // Quick Recharge
        item {
            QuickRechargeSection(
                onAmountSelected = { viewModel.initiateRecharge(it) }
            )
        }
        
        // Earnings Period
        item {
            EarningsSection(
                earnings = walletState.earnings,
                selectedPeriod = walletState.selectedPeriod,
                onPeriodChange = { viewModel.selectPeriod(it) }
            )
        }
        
        // Transaction History
        items(walletState.recentTransactions) { transaction ->
            TransactionCard(transaction = transaction)
        }
    }
}
```

### 3. ChatScreen - Support Temps Réel

**Interface moderne avec :**
- Messages différenciés (coursier/admin)
- Bulles de chat avec timestamps
- Auto-scroll vers nouveaux messages
- Input avec validation

### 4. ProfileScreen - Profil Coursier (457 lignes)

**Sections complètes :**
- Photo profil circulaire avec initiales
- Statut modifiable : EN_LIGNE/OCCUPE/HORS_LIGNE
- Statistiques : commandes totales, note globale
- Paramètres : notifications, sécurité, aide
- Déconnexion sécurisée avec confirmation

## 🎨 Design System Mobile

### Thème Material 3
```kotlin
@Composable
fun SuzoskyTheme(content: @Composable () -> Unit) {
    val colorScheme = lightColorScheme(
        primary = PrimaryGold,
        onPrimary = Color.White,
        primaryContainer = PrimaryDark,
        secondary = SecondaryBlue,
        tertiary = AccentRed,
        background = Color(0xFFF8F9FA),
        surface = Color.White
    )
    
    MaterialTheme(
        colorScheme = colorScheme,
        typography = SuzoskyTypography,
        content = content
    )
}
```

### Couleurs Alignées
```kotlin
object SuzoskyColors {
    val PrimaryDark = Color(0xFF1A1A2E)
    val SecondaryBlue = Color(0xFF16213E)  
    val PrimaryGold = Color(0xFFD4A853)
    val AccentRed = Color(0xFFE94560)
    val SuccessGreen = Color(0xFF2ECC71)
    val GlassBg = Color(0x26FFFFFF)
}
```

---

# 6. APIS ET INTÉGRATIONS

## ⚠️ ARCHITECTURE ENDPOINTS - IMPORTANT

### Distinction Web vs Mobile

**🌐 INTERFACE WEB** (navigateur) :
- `coursier.php` - Dashboard web pour coursiers
- `/api/auth.php` - Authentification email/password
- Utilise sessions PHP et formulaires HTML

**📱 APPLICATION MOBILE** (Android) :
- `/api/agent_auth.php` - Authentification matricule/password 
- `/api/orders.php` - Gestion commandes
- Format JSON exclusivement, pas de sessions PHP

⛔ **ERREUR FRÉQUENTE** : Ne pas confondre `coursier.php` (web) avec les APIs mobiles dans `/api/`

## 🔌 Endpoints REST

### Authentification Mobile

⚠️ **ENDPOINT DÉDIÉ** : L'application mobile Android utilise `/api/agent_auth.php` (PAS `/api/auth.php`)

```php
POST /api/agent_auth.php
{
    "matricule": "CM20250003",
    "password": "KOrxI"
}

Response: {
    "success": true,
    "message": "Login successful",
    "data": {
        "agent_id": "123",
        "matricule": "CM20250003",
        "nom": "Nom Coursier",
        "is_active": true,
        "expires_at": "2025-09-28T12:00:00Z"
    }
}
```

### Authentification Web (Différente)
```php
POST /api/auth.php  # Pour interface web uniquement
{
    "action": "login",
    "email": "coursier@example.com", 
    "password": "motdepasse"
}
```

### Gestion Commandes
```php
// Récupérer commandes disponibles
GET /api/orders.php?action=available&coursier_id=123

// Accepter une commande  
POST /api/orders.php
{
    "action": "accept",
    "order_id": "456",
    "coursier_id": "123"
}

// Mettre à jour statut
PUT /api/orders.php
{
    "action": "update_status", 
    "order_id": "456",
    "status": "PICKUP_ARRIVED",
    "latitude": 5.3364,
    "longitude": -4.0267
}
```

### Portefeuille et Paiements
```php
// Initier recharge CinetPay
POST /api/wallet.php
{
    "action": "initiate_recharge",
    "amount": 5000,
    "method": "mobile_money",
    "phone": "+22507070707"  
}

Response: {
    "success": true,
    "data": {
        "payment_url": "https://checkout.cinetpay.com/...",
        "transaction_id": "TXN_123456"
    }
}
```

## 🌍 Intégrations Externes

### Google Maps API
- **Clé Web** : `AIzaSyAf8KhU-K8BrPCIa_KdBgCQ8kHjbC9Y7Qs`
- **Clé Android** : Configurée dans `google-services.json`
- **Bibliothèques** : Places, Geometry, Directions

### CinetPay (Paiements Mobile Money)
```php
// Configuration
$config = [
    'apikey' => '8338609805877a8eaac7eb6.01734650',
    'site_id' => '219503', 
    'secret_key' => '17153003105e7ca6606cc157.46703056',
    'endpoint' => 'https://api-checkout.cinetpay.com/v2/payment'
];
```

### Firebase (Notifications Push)
- **Projet** : `coursier-suzosky`
- **FCM Server Key** : Configurée dans `coursier-suzosky-firebase-adminsdk-*.json`
- **Usage** : Notifications nouvelles commandes, mises à jour statut

---

# 7. BASE DE DONNÉES

## 🗄️ Structure MySQL

### Tables Principales

#### Commandes
```sql
CREATE TABLE commandes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_commande VARCHAR(50) UNIQUE NOT NULL,
    code_commande VARCHAR(50) UNIQUE,
    client_nom VARCHAR(100) NOT NULL,
    client_telephone VARCHAR(20) NOT NULL,
    adresse_recuperation TEXT NOT NULL,
    adresse_livraison TEXT NOT NULL,
    latitude_depart DECIMAL(10,8),
    longitude_depart DECIMAL(11,8), 
    latitude_arrivee DECIMAL(10,8),
    longitude_arrivee DECIMAL(11,8),
    prix DECIMAL(10,2) NOT NULL,
    distance_km DECIMAL(8,2),
    mode_paiement ENUM('espece', 'mobile_money') DEFAULT 'espece',
    statut ENUM('nouvelle', 'attente', 'acceptee', 'en_cours', 'livree', 'annulee') DEFAULT 'nouvelle',
    priorite ENUM('normale', 'urgente', 'express') DEFAULT 'normale',
    coursier_id INT,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_livraison_prevue DATETIME,
    date_livraison_reelle DATETIME,
    FOREIGN KEY (coursier_id) REFERENCES coursiers(id_coursier)
);
```

#### Coursiers
```sql
CREATE TABLE coursiers (
    id_coursier INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    prenoms VARCHAR(100) NOT NULL,
    telephone VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    statut ENUM('actif', 'inactif', 'suspendu') DEFAULT 'actif',
    statut_connexion ENUM('en_ligne', 'occupe', 'hors_ligne') DEFAULT 'hors_ligne',
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    derniere_position TIMESTAMP,
    nombre_commandes_total INT DEFAULT 0,
    note_moyenne DECIMAL(3,2) DEFAULT 0,
    date_inscription TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion TIMESTAMP,
    device_token VARCHAR(255), -- FCM token
    INDEX idx_statut (statut),
    INDEX idx_position (latitude, longitude)
);
```

#### Clients
```sql
CREATE TABLE clients (
    id_client INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100),
    telephone VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE,
    adresse TEXT,
    balance DECIMAL(10,2) DEFAULT 0.00,
    type_client ENUM('particulier', 'professionnel') DEFAULT 'particulier',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    derniere_commande TIMESTAMP,
    INDEX idx_telephone (telephone)
);
```

#### Transactions Portefeuille
```sql
CREATE TABLE wallet_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    coursier_id INT NOT NULL,
    type_transaction ENUM('recharge', 'gain_livraison', 'retrait', 'bonus') NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    commande_id INT NULL, -- Si lié à une livraison
    methode_paiement VARCHAR(50), -- 'mobile_money_orange', 'mobile_money_mtn', etc.
    statut ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    transaction_externe_id VARCHAR(100), -- ID CinetPay/autre
    description TEXT,
    date_transaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coursier_id) REFERENCES coursiers(id_coursier),
    FOREIGN KEY (commande_id) REFERENCES commandes(id),
    INDEX idx_coursier_date (coursier_id, date_transaction)
);
```

## 🔄 Migrations et Scripts

### Scripts d'initialisation
```bash
# Structure complète
_sql/coursier_local_structure.sql

# Données de test  
_sql/sample_data.sql

# Migration production
database/migrate_to_production.php
```

### Procédures stockées utiles
```sql
-- Calcul automatique prix selon distance
DELIMITER $$
CREATE PROCEDURE CalculatePricing(
    IN distance_km DECIMAL(8,2),
    IN priority ENUM('normale', 'urgente', 'express'),
    OUT calculated_price DECIMAL(10,2)
)
BEGIN
    DECLARE base_price DECIMAL(10,2) DEFAULT 800.00;
    DECLARE distance_rate DECIMAL(10,2) DEFAULT 500.00;
    DECLARE priority_multiplier DECIMAL(3,2) DEFAULT 1.0;
    
    CASE priority
        WHEN 'urgente' THEN SET priority_multiplier = 1.5;
        WHEN 'express' THEN SET priority_multiplier = 2.0;
        ELSE SET priority_multiplier = 1.0;
    END CASE;
    
    SET calculated_price = (base_price + (distance_km * distance_rate)) * priority_multiplier;
END$$
DELIMITER ;
```

---

# 8. CORRECTIONS ET MISES À JOUR RÉCENTES

## 🛠️ Corrections du 27 Septembre 2025

### 1. Chargement Google Maps API

**Problème résolu :** Carte et autocomplétion ne se chargeaient pas immédiatement

**Solution implémentée :**
- Script Google Maps unique dans le `<head>` via `index.php`
- Callback `initGoogleMapsEarly` pour initialisation précoce
- Anti-doublons avec `googleMapsInitialized` flag
- Retry automatique pour l'autocomplétion

**Fichiers modifiés :**
- `index.php` : Injection API dans head + clé dynamique
- `sections_index/js_google_maps.php` : Réécriture complète
- `sections_index/js_initialization.php` : Assets via `ROOT_PATH`

### 2. Correction Erreur 404 Commandes

**Problème :** API `submitOrder()` retournait 404 en sous-dossiers

**Solution :**
- Utilisation de `(window.ROOT_PATH || '') + '/api/submit_order.php'`
- Compatibilité développement local `localhost/COURSIER_LOCAL/`

### 3. Protection GitHub Automatique

**Problème résolu :** Erreur d'authentification push automatique
```
remote: Invalid username or token. Password authentication is not supported
```

**Solution sécurisée :**
- Migration vers **Git Credential Manager** (GCM)
- Suppression des tokens hardcodés du code source
- Script `scripts/PROTECTION_GITHUB_FINAL.ps1` sécurisé
- Nettoyage historique Git des secrets exposés

**Fichiers créés :**
- `scripts/PROTECTION_GITHUB_FINAL.ps1` - Protection sans token exposé
- `BAT/PROTECTION_AUTO.bat` - Interface utilisateur mise à jour

## 🔧 Corrections Base de Données (26 Septembre)

### Restauration Table Clients
**Problème :** API `submit_order.php` générait SQLSTATE[42S02] (table inexistante)

**Script de correction :** `restore_clients_table_lws.php`
- ✅ Table `clients` restaurée avec 10 enregistrements
- ✅ Colonnes `balance` (DECIMAL) et `type_client` (ENUM) ajoutées
- ✅ Tests API validés en production

### Fix Mapping Priorité
**Problème :** Formulaire envoyait 'normal' mais ENUM DB attendait 'normale'

**Solution :**
```php
$priorityMap = [
    'normal' => 'normale',
    'urgent' => 'urgente', 
    'express' => 'express'
];
$priority = $priorityMap[strtolower($priority)] ?? 'normale';
```

---

# 9. GUIDE D'ADMINISTRATION

## 👨‍💼 Interface Administrateur

### Accès Administration
- **URL** : `/admin.php` ou `/admin/dashboard.php`
- **Authentification** : Token API sécurisé
- **Permissions** : Niveau admin requis

### Dashboard Principal

**Métriques temps réel :**
- Commandes actives / en attente
- Coursiers connectés / disponibles  
- Chiffre d'affaires journalier / mensuel
- Taux de satisfaction client

**Widgets disponibles :**
- Carte temps réel des coursiers
- Timeline des dernières commandes
- Graphiques de performance
- Alertes et notifications

### Gestion des Commandes

**Liste des commandes avec filtres :**
```php
// Filtres disponibles
$filters = [
    'status' => ['nouvelle', 'en_cours', 'livree'],
    'date_range' => ['today', 'week', 'month'],
    'coursier_id' => $coursier_ids,
    'payment_method' => ['espece', 'mobile_money'],
    'priority' => ['normale', 'urgente', 'express']
];
```

**Actions en lot :**
- Assigner coursier automatiquement
- Modifier statuts multiples
- Exporter données (CSV/PDF)
- Envoyer notifications

### Gestion des Coursiers

**Profils coursiers :**
- Informations personnelles
- Statistiques de performance
- Historique des livraisons
- Gestion du portefeuille
- Status de connexion temps réel

**Outils d'administration :**
- Activation/désactivation comptes
- Modification des informations
- Reset mot de passe
- Gestion des sanctions

## 📊 Rapports et Statistiques

### Rapports Automatisés

**Quotidiens :**
- Résumé des commandes du jour
- Performance des coursiers
- Revenus et paiements
- Incidents et problèmes

**Mensuels :**
- Analyse des tendances
- ROI par zone géographique
- Satisfaction client (NPS)
- Optimisations suggérées

### Exports de Données
```php
// Exemple export CSV commandes
function exportOrdersCSV($filters = []) {
    $orders = getOrdersWithFilters($filters);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="commandes_'.date('Y-m-d').'.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Numéro', 'Client', 'Coursier', 'Statut', 'Prix', 'Date']);
    
    foreach ($orders as $order) {
        fputcsv($output, [
            $order['id'],
            $order['numero_commande'], 
            $order['client_nom'],
            $order['coursier_nom'],
            $order['statut'],
            $order['prix'] . ' FCFA',
            $order['date_creation']
        ]);
    }
}
```

---

# 10. SÉCURITÉ ET PROTECTION

## 🔒 Sécurité Authentification

### Système de Tokens JWT
```php
// Génération token sécurisé
function generateSecureToken($userId, $role = 'coursier') {
    $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    
    $payload = base64url_encode(json_encode([
        'user_id' => $userId,
        'role' => $role,
        'iat' => time(),
        'exp' => time() + (24 * 60 * 60), // 24h
        'iss' => 'suzosky-coursier'
    ]));
    
    $signature = base64url_encode(hash_hmac('sha256', 
        $header . '.' . $payload, 
        JWT_SECRET_KEY, true
    ));
    
    return $header . '.' . $payload . '.' . $signature;
}
```

### Protection CSRF
```php
// Génération token CSRF
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validation
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token);
}
```

### Rate Limiting
```php
// Limitation requêtes API
class RateLimiter {
    private $redis;
    
    public function checkLimit($identifier, $maxRequests = 60, $window = 3600) {
        $key = "rate_limit:$identifier:" . floor(time() / $window);
        $current = $this->redis->incr($key);
        
        if ($current === 1) {
            $this->redis->expire($key, $window);
        }
        
        return $current <= $maxRequests;
    }
}
```

## 🛡️ Protection des Données

### Chiffrement Données Sensibles
```php
// Chiffrement AES-256-GCM
function encryptSensitiveData($data, $key) {
    $iv = random_bytes(12); // GCM recommande 12 bytes
    $encrypted = openssl_encrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $encrypted);
}

function decryptSensitiveData($encryptedData, $key) {
    $data = base64_decode($encryptedData);
    $iv = substr($data, 0, 12);
    $tag = substr($data, 12, 16); 
    $encrypted = substr($data, 28);
    
    return openssl_decrypt($encrypted, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
}
```

### Validation et Sanitisation
```php
// Validation téléphone CI
function validateCIPhone($phone) {
    // Format: +225XXXXXXXXXX ou 225XXXXXXXXXX ou 0XXXXXXXXX
    $pattern = '/^(?:\+225|225|0)?([0-9]{10})$/';
    return preg_match($pattern, $phone, $matches) ? '225' . $matches[1] : false;
}

// Sanitisation SQL
function sanitizeForDB($input, $type = 'string') {
    switch ($type) {
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT);
        case 'float': 
            return filter_var($input, FILTER_VALIDATE_FLOAT);
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL);
        case 'string':
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}
```

## 🔐 Protection Infrastructure

### Headers de Sécurité
```php
// Headers sécurisés automatiques
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY'); 
    header('X-XSS-Protection: 1; mode=block');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' maps.googleapis.com; style-src \'self\' \'unsafe-inline\' fonts.googleapis.com');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
```

### Backup Automatisé GitHub
Le système `scripts/PROTECTION_GITHUB_FINAL.ps1` assure :
- ✅ Sauvegarde automatique toutes les 5 secondes
- ✅ Authentification sécurisée via Git Credential Manager
- ✅ Aucun token exposé dans le code source
- ✅ Gestion d'erreur et retry automatique
- ✅ Compatible avec GitHub Secret Scanning

---

# 11. TESTS ET VALIDATION

## 🧪 Suite de Tests

### Tests Unitaires API

**Test authentification :**
```php
// Tests/test_auth_api.php
function testLoginSuccess() {
    $response = callAPI('POST', '/api/auth.php', [
        'action' => 'login',
        'email' => 'test@suzosky.com',
        'password' => 'testpass123'
    ]);
    
    assert($response['success'] === true);
    assert(isset($response['data']['token']));
    assert(isset($response['data']['user']['id']));
}

function testLoginFailure() {
    $response = callAPI('POST', '/api/auth.php', [
        'action' => 'login', 
        'email' => 'invalid@email.com',
        'password' => 'wrongpass'
    ]);
    
    assert($response['success'] === false);
    assert(isset($response['error']));
}
```

**Test soumission commande :**
```php  
// Tests/test_submit_order.php
function testSubmitOrderSuccess() {
    $orderData = [
        'action' => 'submit_order',
        'client_nom' => 'Test Client',
        'client_telephone' => '+22507070707',
        'adresse_recuperation' => 'Plateau, Abidjan',
        'adresse_livraison' => 'Cocody, Abidjan',
        'mode_paiement' => 'espece',
        'priorite' => 'normale'
    ];
    
    $response = callAPI('POST', '/api/submit_order.php', $orderData);
    
    assert($response['success'] === true);
    assert(isset($response['data']['order_id']));
    assert(isset($response['data']['order_number']));
    assert($response['data']['price'] > 0);
}
```

### Tests d'Intégration

**Test workflow complet commande :**
```php
// Tests/test_order_workflow.php
function testCompleteOrderWorkflow() {
    // 1. Soumission commande client
    $order = submitTestOrder();
    assert($order['success']);
    
    // 2. Attribution automatique coursier
    $assignment = autoAssignCourier($order['data']['order_id']);
    assert($assignment['success']);
    
    // 3. Acceptation par coursier
    $acceptance = acceptOrder($order['data']['order_id'], $assignment['coursier_id']);
    assert($acceptance['success']);
    
    // 4. Mise à jour statuts
    $statuses = ['PICKUP_ARRIVED', 'PICKED_UP', 'DELIVERY_ARRIVED', 'DELIVERED'];
    foreach ($statuses as $status) {
        $update = updateOrderStatus($order['data']['order_id'], $status);
        assert($update['success']);
    }
    
    // 5. Vérification paiement coursier
    $payment = checkCourierPayment($assignment['coursier_id'], $order['data']['order_id']);
    assert($payment['success']);
}
```

### Tests Performance

**Test charge API :**
```bash
# Test avec Apache Bench
ab -n 1000 -c 10 -H "Content-Type: application/json" \
   -p order_payload.json \
   http://localhost/COURSIER_LOCAL/api/submit_order.php

# Test avec curl en parallèle
for i in {1..100}; do
    curl -X POST http://localhost/COURSIER_LOCAL/api/auth.php \
         -H "Content-Type: application/json" \
         -d '{"action":"login","email":"test@test.com","password":"test"}' &
done
wait
```

## ✅ Validation Production

### Checklist Déploiement
- [ ] Tests unitaires passés (100%)
- [ ] Tests d'intégration validés
- [ ] Performance acceptable (< 2s response)
- [ ] Sécurité auditée (pas de failles)
- [ ] Backup automatique configuré
- [ ] Monitoring en place
- [ ] Documentation à jour
- [ ] Formation équipe effectuée

### Monitoring Continu
```php
// api/health.php - Health check automatique
function healthCheck() {
    $checks = [
        'database' => checkDatabase(),
        'apis' => checkAPIsStatus(), 
        'google_maps' => checkGoogleMapsAPI(),
        'cinetpay' => checkCinetPayAPI(),
        'disk_space' => checkDiskSpace(),
        'memory_usage' => checkMemoryUsage()
    ];
    
    $overall = array_reduce($checks, function($carry, $check) {
        return $carry && $check['status'] === 'OK';
    }, true);
    
    return [
        'status' => $overall ? 'OK' : 'ERROR',
        'timestamp' => date('c'),
        'checks' => $checks
    ];
}
```

---

# 12. GUIDES UTILISATEUR

## 👥 Guide Client (Interface Web)

### Passer une Commande

1. **Accéder au site :** `https://conciergerie-privee-suzosky.com`

2. **Remplir le formulaire :**
   - **Adresse de récupération :** Utilisez l'autocomplétion Google Places
   - **Adresse de livraison :** Sélectionnez la destination précise
   - **Informations personnelles :** Nom, téléphone (obligatoires)
   - **Mode de paiement :** Espèces ou Mobile Money
   - **Priorité :** Normale (gratuite), Urgente (+50%), Express (+100%)

3. **Validation et paiement :**
   - Vérifiez le prix calculé automatiquement
   - Confirmez les informations
   - Suivez les instructions de paiement si Mobile Money

4. **Suivi de la commande :**
   - **Code de suivi :** Notez le numéro de commande (SZKyyyymmddxxxx)
   - **Timeline temps réel :** 
     - 🔵 Nouvelle commande
     - 🟡 En attente de coursier
     - 🟢 Acceptée par coursier
     - 🚀 En cours de récupération
     - 📦 Colis récupéré
     - 🏁 En livraison
     - ✅ Livrée

### Statuts de Commande

| Statut | Description | Action Client |
|--------|-------------|---------------|
| **Nouvelle** | Commande reçue | Attendre attribution |
| **En attente** | Recherche coursier | Patientez (max 10min) |
| **Acceptée** | Coursier assigné | Préparer le colis |
| **En cours** | Coursier en route vers récupération | Être disponible |
| **Récupérée** | Colis pris en charge | Suivre la livraison |
| **En livraison** | Vers destination finale | Attendre le coursier |
| **Livrée** | Commande terminée | Évaluer le service |

## 🏍️ Guide Coursier (Application Android)

### Installation et Configuration

1. **Télécharger l'APK :** Depuis le lien fourni par l'administration

2. **Installation :**
   - Autoriser "Sources inconnues" dans Android
   - Installer l'APK téléchargé
   - Ouvrir l'application "Suzosky Coursier"

3. **Première connexion :**
   - Saisir email et mot de passe fournis
   - Autoriser géolocalisation et notifications
   - Tester la réception des commandes

### Utilisation Quotidienne

**Onglet Courses (Livraisons) :**
- Visualiser les commandes disponibles
- Accepter/refuser les livraisons
- Suivre l'itinéraire avec Google Maps
- Mettre à jour le statut à chaque étape :
  1. "J'arrive pour récupérer"
  2. "Colis récupéré" 
  3. "J'arrive chez le destinataire"
  4. "Livraison terminée"

**Onglet Wallet (Portefeuille) :**
- Consulter le solde actuel
- Recharger via Mobile Money (2K, 5K, 10K, 20K FCFA)
- Voir l'historique des gains et transactions
- Suivre les statistiques par période

**Onglet Chat (Support) :**
- Contacter l'administration
- Signaler un problème de livraison
- Demander de l'aide technique

**Onglet Profile (Profil) :**
- Modifier le statut (En ligne/Occupé/Hors ligne)
- Consulter les statistiques personnelles
- Gérer les paramètres de notification
- Se déconnecter de manière sécurisée

### Gestion des Urgences

**Problème technique :**
1. Utiliser le chat support intégré
2. Appeler le numéro d'urgence : +225 07 07 07 07 07
3. Si besoin, redémarrer l'application

**Problème de livraison :**
1. Contacter le client par téléphone
2. Informer l'administration via chat
3. Prendre des photos si nécessaire
4. Marquer comme "Problème" dans l'app

## 👨‍💼 Guide Administration

### Accès Dashboard

1. **Connexion :** `/admin.php` avec identifiants admin
2. **Vérification quotidienne :**
   - Statut des coursiers connectés
   - Commandes en attente d'attribution
   - Performances du jour
   - Alertes et notifications

### Gestion Quotidienne

**Attribution des Commandes :**
- Système automatique basé sur la proximité géographique
- Possibilité d'attribution manuelle si nécessaire
- Surveillance des délais d'acceptation (max 5min)

**Suivi des Coursiers :**
- Position temps réel sur la carte admin
- Statuts de connexion et disponibilité
- Performance individuelle et collective
- Gestion des problèmes et réclamations

**Gestion Financière :**
- Validation des recharges CinetPay
- Calcul automatique des commissions
- Génération des rapports de paiement
- Exportation des données comptables

### Maintenance et Support

**Monitoring Système :**
- Vérification santé des APIs (`/api/health.php`)
- Surveillance des logs d'erreur
- Performance de la base de données
- Espace disque et ressources serveur

**Support Client :**
- Réponse aux demandes chat
- Gestion des réclamations
- Remboursements si nécessaire
- Communication avec les coursiers

---

## 🔗 LIENS UTILES ET CONTACTS

### Environnements de Test
- **Local :** `http://localhost/COURSIER_LOCAL`
- **Staging :** `https://staging.conciergerie-privee-suzosky.com` (si disponible)
- **Production :** `https://conciergerie-privee-suzosky.com`

### Repositories et Ressources
- **GitHub :** `https://github.com/adsuzk/COURSIER_LOCAL`
- **Documentation API :** `/api/docs.php` (si implémentée)
- **Status Page :** `/api/health.php`

### Support Technique
- **Email :** `support@conciergerie-privee-suzosky.com`
- **Téléphone urgence :** `+225 07 07 07 07 07`
- **Chat admin :** Intégré dans le dashboard

---

## 📝 CHANGELOG ET VERSIONS

### Version 7.0 (Septembre 2025) - CURRENT
- ✅ Application Android complète (Jetpack Compose)
- ✅ Interface web optimisée avec Google Maps
- ✅ Intégration CinetPay pour Mobile Money
- ✅ Système de portefeuille digital
- ✅ Chat support temps réel
- ✅ Protection GitHub automatique sécurisée
- ✅ APIs REST complètes et documentées

### Version 6.x (Août 2025)
- Interface PHP basique
- Gestion manuelle des commandes
- Paiement espèces uniquement

### Roadmap Future
- [ ] Application iOS (Swift UI)
- [ ] Système de notation et avis clients
- [ ] Intégration d'autres moyens de paiement
- [ ] Intelligence artificielle pour l'optimisation des routes
- [ ] Programme de fidélité clients

---

**🎯 FIN DE LA DOCUMENTATION COMPLÈTE SUZOSKY COURSIER V7.0**

*Cette documentation est maintenue à jour automatiquement. Dernière révision : 27 Septembre 2025*

**Statut du Projet : ✅ PRODUCTION READY - 100% FONCTIONNEL**