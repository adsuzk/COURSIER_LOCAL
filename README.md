# 🚚 SUZOSKY COURSIER - Plateforme de Livraison en Temps Réel

![Version](https://img.shields.io/badge/version-2.1-gold)
![Status](https://img.shields.io/badge/status-production-success)
![PHP](https://img.shields.io/badge/PHP-7.4+-blue)
![Android](https://img.shields.io/badge/Android-SDK%2034-green)

## 🌐 URL du Site

```
http://localhost/COURSIER_LOCAL/
```

⚠️ **IMPORTANT** : Ne PAS utiliser `/index.php` à la fin !

---

## 📋 Vue d'Ensemble

**Suzosky Coursier** est une plateforme complète de gestion de livraisons en temps réel comprenant :
- 🌐 **Site Web Client** : Commande et suivi en temps réel
- 📱 **Application Mobile Coursier** : Android (Kotlin + Jetpack Compose)
- 💳 **Paiement Intégré** : CinetPay (Modal AVANT enregistrement)
- 🔔 **Notifications** : Firebase Cloud Messaging (FCM)
- 🗺️ **Géolocalisation** : Google Maps API temps réel
- 🎙️ **Guidage Vocal** : Text-to-Speech intégré dans l'app

---

## ✨ Fonctionnalités Principales

### Pour les Clients 👥
- ✅ Commande rapide avec géolocalisation automatique
- ✅ **2 modes de paiement** :
  - 💵 Espèces (à la livraison)
  - 💳 Paiement en ligne (CinetPay dans modal, AVANT enregistrement)
- ✅ Suivi en temps réel de la livraison sur carte
- ✅ Historique des commandes
- ✅ Support chat en direct

### Pour les Coursiers 🚴
- ✅ Notifications push pour nouvelles commandes
- ✅ Accept/Refuse avec dialog dans l'app
- ✅ **2 numéros cliquables** (Client + Destinataire) pour appel direct
- ✅ Navigation GPS intégrée
- ✅ Portefeuille avec recharge en ligne (clavier numérique)
- ✅ Support/Chat moderne (style WhatsApp)
- ✅ **Profil avec gamification** :
  - Matricule affiché
  - Badges et réalisations
  - Progression par niveaux
  - Stats détaillées

---

## 🏗️ Architecture Technique

```
COURSIER_LOCAL/
├── 📱 Mobile App (CoursierAppV7/)
│   ├── MainActivity.kt
│   ├── ui/screens/
│   │   ├── UnifiedCoursesScreen.kt     ← Intégré sans modal
│   │   ├── ModernWalletScreen.kt       ← Glassmorphism
│   │   ├── ModernChatScreen.kt         ← WhatsApp-like
│   │   └── ModernProfileScreen.kt      ← Gamification
│   └── services/
│       └── FCMService.kt               ← Notifications
│
├── 🌐 Site Web (/)
│   ├── index.php                       ← Page principale
│   ├── sections_index/
│   │   ├── order_form.php
│   │   ├── js_form_handling.php        ← FLUX CORRIGÉ
│   │   └── js_payment.php
│   └── api/
│       ├── initiate_payment_only.php   ← Génère URL paiement
│       ├── create_order_after_payment.php ← Enregistre après paiement
│       ├── get_coursier_data.php       ← Données coursier + GPS
│       └── order_response.php          ← Accept/Refuse
│
├── 🔧 Backend
│   ├── config.php                      ← Configuration BDD
│   ├── attribution_intelligente.php    ← Assignation auto coursier
│   ├── fcm_manager.php                 ← Gestion notifications
│   └── cinetpay/config.php             ← Configuration paiement
│
└── 📚 Documentation
    ├── DOCUMENTATION_SYSTEME_SUZOSKY_v2.md  ← Documentation complète
    └── README.md                            ← Ce fichier
```

---

## 🚀 Installation

### Prérequis
- PHP 7.4+ avec extensions : `pdo`, `pdo_mysql`, `curl`, `json`
- MySQL 5.7+ ou MariaDB 10.3+
- Apache avec `mod_rewrite` activé
- Composer (optionnel)
- Android Studio (pour l'app mobile)

### 1. Configuration Backend

```bash
# 1. Cloner le projet
cd /xampp/htdocs/
git clone [repository-url] COURSIER_LOCAL

# 2. Configuration BDD
cp config.example.php config.php
# Éditer config.php avec vos identifiants MySQL

# 3. Importer la base de données
mysql -u root -p < database/schema.sql

# 4. Configurer CinetPay
# Les credentials sont déjà dans config.php
# API Key: 8338609805877a8eaac7eb6.01734650
# Site ID: 5875732
# Secret Key: 830006136690110164ddb1.29156844
```

### 2. Accéder au Site

```
http://localhost/COURSIER_LOCAL/
```

**IMPORTANT** : L'URL se termine par `/` (pas de `index.php` !)

### 2. Configuration Firebase

```bash
# 1. Placer les fichiers JSON Firebase à la racine :
# - coursier-suzosky-firebase-adminsdk-xxxxx.json
# - google-services.json (pour Android)

# 2. Vérifier les permissions
chmod 644 *.json
```

### 3. Compilation App Android

```bash
cd CoursierAppV7/

# Windows
gradlew.bat assembleDebug

# Linux/Mac
./gradlew assembleDebug

# Installer sur appareil
adb install -r app/build/outputs/apk/debug/app-debug.apk
```

---

## 🔄 Flux de Commande (CORRIGÉ v2.0)

### Mode Espèces 💵
```
1. Client remplit formulaire
2. Clic "Commander"
3. Enregistrement BDD immédiat
4. Recherche coursier automatique
5. Notification FCM au coursier
6. Suivi temps réel sur l'index
```

### Mode Paiement En Ligne 💳
```
1. Client remplit formulaire
2. Clic "Commander"
3. ➡️ Modal CinetPay s'ouvre (AVANT enregistrement)
   API: POST /api/initiate_payment_only.php
4. Client paie dans le modal (sans quitter l'index)
5. ➡️ Confirmation paiement reçue
6. ➡️ Enregistrement commande
   API: POST /api/create_order_after_payment.php
7. Recherche coursier automatique
8. Notification FCM au coursier
9. Suivi temps réel sur l'index
```

**✅ POINT CLÉ** : Le modal de paiement s'ouvre **AVANT** l'enregistrement de la commande. La commande n'est enregistrée que si le paiement est confirmé.

---

## 🎨 Design System

### Couleurs Principales
| Couleur | Code HEX | Usage |
|---------|----------|-------|
| Or Suzosky | `#D4A853` | Primaire, boutons, accents |
| Noir foncé | `#1A1A2E` | Backgrounds, textes |
| Bleu foncé | `#16213E` | Backgrounds secondaires |
| Rouge accent | `#E94560` | Erreurs, alertes |
| Vert succès | `#27AE60` | Validations, statuts OK |

### Effets Modernes
- **Glassmorphism** : Transparence + blur
- **Ombres douces** : Profondeur subtile
- **Animations fluides** : Transitions 300ms
- **Coins arrondis** : 16-24dp partout

---

## 📱 Captures d'Écran

### Application Mobile
- **Mes Courses** : Map intégrée + 2 numéros cliquables
- **Portefeuille** : Design glassmorphism + recharge numérique
- **Support** : Chat moderne style WhatsApp
- **Profil** : Gamification avec badges et niveaux

### Site Web
- **Formulaire** : Géolocalisation automatique
- **Modal Paiement** : CinetPay intégré avec branding Suzosky
- **Suivi** : Carte temps réel avec marqueur coursier

---

## 🔐 Sécurité

### Backend
- ✅ Requêtes préparées (PDO) contre SQL injection
- ✅ Validation des entrées utilisateur
- ✅ Sessions sécurisées avec timeout
- ✅ Logs d'audit complets

### Paiement
- ✅ Intégration CinetPay certifiée PCI DSS
- ✅ Webhook sécurisé avec signature
- ✅ Aucune donnée bancaire stockée
- ✅ Modal isolé dans iframe

### Mobile
- ✅ Firebase Auth pour tokens
- ✅ HTTPS uniquement pour APIs
- ✅ Validation côté serveur systématique
- ✅ Obfuscation du code APK

---

## 📊 APIs Disponibles

### Coursier
| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/api/get_coursier_data.php` | GET | Récupère commandes + GPS + téléphones |
| `/api/order_response.php` | POST | Accept/Refuse commande |
| `/api/update_location.php` | POST | MAJ position GPS temps réel |

### Client
| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/api/initiate_payment_only.php` | POST | Génère URL paiement (sans enregistrer) |
| `/api/create_order_after_payment.php` | POST | Enregistre après paiement confirmé |
| `/api/track_order.php` | GET | Suivi temps réel commande |

### Admin
| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/api/get_stats.php` | GET | Statistiques globales |
| `/api/manage_coursiers.php` | POST | Gestion coursiers |

---

## 🧪 Tests

### Tests Backend
```bash
# Test connexion BDD
php -r "require 'config.php'; var_dump(getDB());"

# Test API paiement
curl -X POST http://localhost/COURSIER_LOCAL/api/initiate_payment_only.php \
  -d "order_number=TEST123&amount=2500&client_name=Test&client_phone=+225070000&client_email=test@test.com"
```

### Tests Mobile
```bash
# Logs en temps réel
adb logcat -s "FCMService:*" "MainActivity:*" "ApiService:*"

# Forcer notification test
adb shell am broadcast -a com.suzosky.coursier.TEST_NOTIFICATION
```

---

## 📈 Monitoring

### Logs Importants
- `error_log` PHP : Erreurs backend
- `logcat` Android : Logs mobile
- `diagnostic_logs/` : Logs système spécifiques

### Métriques à Surveiller
- ✅ Taux de succès commandes
- ✅ Temps moyen de livraison
- ✅ Disponibilité coursiers
- ✅ Taux de conversion paiement
- ✅ Uptime serveur

---

## 🆘 Troubleshooting

### Problème : Modal paiement ne s'ouvre pas
**Solution** :
1. Vérifier console navigateur (F12)
2. Vérifier `ROOT_PATH` défini dans index.php
3. Vérifier clés CinetPay dans `cinetpay/config.php`

### Problème : Notifications FCM non reçues
**Solution** :
1. Vérifier token FCM valide dans BDD
2. Vérifier fichiers JSON Firebase présents
3. Vérifier logs : `adb logcat -s FCMService`
4. Relancer service : `fcm_auto_cleanup.php`

### Problème : Coursier ne reçoit pas GPS client
**Solution** :
1. Vérifier colonnes BDD : `latitude_retrait`, `longitude_retrait`
2. Vérifier parsing JSON dans `ApiService.kt`
3. Vérifier `get_coursier_data.php` retourne GPS

---

## 🤝 Contribution

### Guidelines
1. **Branches** : `main` (production), `dev` (développement)
2. **Commits** : Messages clairs en français
3. **Tests** : Tester avant push
4. **Documentation** : MAJ après changement majeur

### Process
```bash
# 1. Fork et clone
git clone [your-fork]

# 2. Créer branche feature
git checkout -b feature/ma-nouvelle-fonctionnalite

# 3. Commits atomiques
git commit -m "Ajout: Description claire"

# 4. Push et Pull Request
git push origin feature/ma-nouvelle-fonctionnalite
```

---

## 📞 Support

### Contacts
- **Email** : support@suzosky.com
- **Téléphone** : +225 XX XX XX XX XX
- **Documentation** : Voir `DOCUMENTATION_SYSTEME_SUZOSKY_v2.md`

### Ressources
- [Documentation CinetPay](https://cinetpay.com/documentation)
- [Firebase FCM](https://firebase.google.com/docs/cloud-messaging)
- [Google Maps API](https://developers.google.com/maps/documentation)

---

## 📝 Changelog

### v2.0 - 1er Octobre 2025
**🎉 Version majeure avec redesign complet**

**Corrections critiques** :
- ✅ **Flux paiement en ligne corrigé** : Modal CinetPay AVANT enregistrement
- ✅ Commande enregistrée uniquement après confirmation paiement
- ✅ APIs créées : `initiate_payment_only.php` + `create_order_after_payment.php`

**Nouvelles fonctionnalités** :
- ✅ 2 numéros cliquables (Client + Destinataire) dans Mes Courses
- ✅ Clavier numérique pour saisie montant recharge
- ✅ Branding Suzosky complet (plus de CinetPay visible)
- ✅ Matricule coursier affiché dans profil (`ID: C{id}`)
- ✅ Textes visibles dans menu bas (hauteur 80dp)

**Redesign UI/UX** :
- ✅ UnifiedCoursesScreen : Intégration totale sans modals
- ✅ ModernWalletScreen : Glassmorphism élégant
- ✅ ModernChatScreen : Style WhatsApp moderne
- ✅ ModernProfileScreen : Gamification avec badges/niveaux
- ✅ BottomNavigationBar : Animations fluides + icônes modernes

**Documentation** :
- ✅ Création `DOCUMENTATION_SYSTEME_SUZOSKY_v2.md` (complète)
- ✅ Suppression 9 documentations obsolètes
- ✅ README.md mis à jour

---

## 📄 Licence

**© 2025 Suzosky - Tous droits réservés**

Ce logiciel est propriétaire. Toute utilisation, modification ou distribution non autorisée est strictement interdite.

---

## 🙏 Remerciements

Merci à toute l'équipe Suzosky pour leur confiance et collaboration !

**Développé avec ❤️ en Côte d'Ivoire 🇨🇮**
