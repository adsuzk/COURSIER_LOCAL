# 👨‍💻 GUIDE NOUVEAU DÉVELOPPEUR - SUZOSKY COURSIER
*Guide d'intégration pour développeurs rejoignant le projet*

## 🎯 **APERÇU DU PROJET**

Suzosky Coursier est une **plateforme complète de livraison** comprenant :
- 🌐 **Interface Web** (PHP/MySQL) - Gestion des commandes et administration
- 📱 **Application Android** (Kotlin/Compose) - Interface mobile pour coursiers
- 🔌 **APIs REST** - Communication entre web et mobile
- 📊 **Système de télémétrie** - Monitoring avancé et analytics

---

## 🏗️ **ARCHITECTURE GÉNÉRALE**

### **Stack Technique**
```
Frontend Web    : PHP 8.0+, HTML5, CSS3, JavaScript
Backend API     : PHP 8.0+, MySQL 8.0+
Mobile          : Android (Kotlin), Jetpack Compose, Material 3
Monitoring      : Système télémétrie custom avec dashboard admin
Paiements       : CinetPay Integration
Maps            : Google Maps SDK, Distance Matrix API
```

### **Structure des Dossiers**
```
coursier_prod/
├── 📁 admin/              # Interface administration
├── 📁 api/                # APIs REST + télémétrie
├── 📁 assets/             # Ressources statiques
├── 📁 DOCUMENTATION_FINALE/ # Documentation complète
├── 📄 coursier.php        # Interface principale
├── 📄 config.php          # Configuration BDD
└── 📄 index.php           # Page d'accueil
```

---

## 🚀 **PREMIER SETUP - ÉTAPES ESSENTIELLES**

### **1. Environnement Local**
```bash
# Prérequis système
- XAMPP (Apache + PHP 8.0+ + MySQL 8.0+)
- Git pour versionning
- Android Studio (pour développement mobile)
- Postman (pour tests APIs)
```

### **2. Configuration Base de Données**
```sql
# Importer les structures
- Exécuter : database_setup.sql (tables principales)
- Exécuter : api/db/create_telemetry_tables.sql (télémétrie)
- Configurer : config.php avec vos credentials
```

### **3. APIs Clés à Configurer**
```php
// config.php - APIs externes
GOOGLE_MAPS_API_KEY = "votre_clé_google"
CINETPAY_API_KEY = "votre_clé_cinetpay"
CINETPAY_SECRET = "votre_secret_cinetpay"
```

---

## 📚 **DOCUMENTATION ESSENTIELLE**

### **🎯 Documents à lire EN PRIORITÉ**

1. **`README.md`** - Vue d'ensemble et statut projet
2. **`ETAT_FINAL_SYSTEM_SEPTEMBRE_2025.md`** - État complet du système
3. **`ARCHITECTURE_TECHNIQUE_COMPLETE_V7.md`** - Architecture détaillée
4. **`TELEMETRY_SYSTEM_COMPLETE.md`** - Système de monitoring

### **🔧 Documents Techniques Spécialisés**

- `IMPLEMENTATION_CALCUL_PRIX.md` - Algorithme de calcul des prix
- `RAPPORT_AUTHENTIFICATION.md` - Système de sécurité
- `MOBILE_ANDROID_INTEGRATION_V2.md` - Application Android
- `CHANGelog_TELEMETRY_2025-09-18.md` - Dernières améliorations

---

## 🎮 **ZONES D'INTERVENTION TYPIQUES**

### **🌐 Développement Web**
**Fichiers principaux :**
- `coursier.php` - Interface utilisateur principale
- `admin/*.php` - Interface d'administration
- `api/*.php` - Endpoints REST

**Fonctionnalités :**
- Gestion des commandes (CRUD)
- Calcul automatique des prix
- Intégration paiement CinetPay
- Dashboard télémétrie

### **📱 Développement Mobile**
**Localisation :** Voir `MOBILE_ANDROID_INTEGRATION_V2.md`
**Écrans principaux :**
- CoursesScreen - Gestion des livraisons
- WalletScreen - Portefeuille utilisateur  
- ChatScreen - Support client
- ProfileScreen - Profil utilisateur

**SDK Spécialisé :**
- `TelemetrySDK.kt` - Monitoring automatique

### **📊 Système de Télémétrie**
**APIs spécialisées :**
```
POST /api/telemetry.php?action=register_device
POST /api/telemetry.php?action=log_crash
POST /api/telemetry.php?action=start_session
GET  /api/telemetry.php?action=get_stats
```

**Tables de données :**
- `app_devices` - Informations devices
- `app_crashes` - Logs de crashes
- `app_sessions` - Sessions utilisateur
- `app_events` - Événements tracking

---

## 🐛 **DEBUGGING ET TESTS**

### **Outils de Diagnostic**
- `Test/_root_migrated/diagnostic_*.php` - Scripts de diagnostic système
- `Test/_root_migrated/test_*.php` - Scripts de test des fonctionnalités
- `Test/_root_migrated/view_logs.php` - Consultation des logs

### **Endpoints de Test**
```
GET /api/test_endpoint.php - Test connectivité API
 GET /Test/_root_migrated/diagnostic_auth.php - Test authentification  
 GET /Test/_root_migrated/test_distance_api.php - Test Google Distance Matrix
```

### **Logs et Monitoring**
- **Application** : Logs dans `logger.php`
- **Télémétrie** : Dashboard admin section "Mises à jour App"
- **Erreurs PHP** : Logs Apache (`xampp/apache/logs/`)

---

## 🔐 **SÉCURITÉ - POINTS D'ATTENTION**

### **Authentication System**
- Sessions PHP sécurisées
- Protection CSRF sur formulaires
- Validation côté serveur pour toutes les entrées
- Rate limiting sur APIs sensibles

### **Base de Données**
- Requêtes préparées (PDO) obligatoires
- Validation des types de données
- Echappement des sorties HTML

### **APIs**
- Authentification par token
- Validation des permissions
- Logs des accès sensibles

---

## 📈 **ROADMAP ET ÉVOLUTIONS**

### **Fonctionnalités Actuelles (100%)**
✅ Interface web complète
✅ Application mobile 4 écrans
✅ Système de paiement CinetPay
✅ APIs REST complètes
✅ Télémétrie avancée

### **Améliorations Futures Possibles**
🔄 Notifications push (partiellement implémenté)
🔄 Chat temps réel WebSocket
🔄 Géolocalisation temps réel coursiers
🔄 Analytics avancées télémétrie
🔄 Tests automatisés (CI/CD)

---

## 🤝 **CONTRIBUTION AU PROJET**

### **Workflow Git Recommandé**
```bash
# Créer une branche pour nouvelle fonctionnalité
git checkout -b feature/nouvelle-fonctionnalite

# Développer et tester
# Toujours tester avant commit

# Commit avec message explicite
git commit -m "feat: ajout fonctionnalité X avec tests"

# Push et créer Pull Request
git push origin feature/nouvelle-fonctionnalite
```

### **Standards de Code**
- **PHP** : PSR-12, commentaires PHPDoc
- **Android** : Kotlin conventions, Clean Architecture
- **SQL** : Noms explicites, requêtes optimisées
- **Documentation** : Markdown, mises à jour obligatoires

---

## 📞 **SUPPORT ET RESSOURCES**

### **Contacts Équipe**
- **Tech Lead** : Documentation dans `DOCUMENTATION_FINALE/`
- **Backend** : Voir `api/` et `RAPPORT_AUTHENTIFICATION.md`
- **Mobile** : Voir `MOBILE_ANDROID_INTEGRATION_V2.md`
- **DevOps** : Voir `DEPLOY_READY.md`

### **Ressources Externes**
- [CinetPay Documentation](https://docs.cinetpay.com)
- [Google Maps Platform](https://developers.google.com/maps)
- [Android Jetpack Compose](https://developer.android.com/jetpack/compose)
- [PHP Official Documentation](https://www.php.net/docs.php)

---

## ✅ **CHECKLIST NOUVEAU DÉVELOPPEUR**

### **Jour 1-2 : Setup**
- [ ] Cloner le repository
- [ ] Installer XAMPP + Android Studio
- [ ] Configurer base de données locale
- [ ] Tester interface web (`http://localhost/coursier_prod`)
- [ ] Lire `README.md` et `ETAT_FINAL_SYSTEM_SEPTEMBRE_2025.md`

### **Jour 3-5 : Compréhension**
- [ ] Explorer interface admin (`/admin`)
- [ ] Tester APIs avec Postman (`/api`)
- [ ] Comprendre architecture télémétrie
- [ ] Lire documentation technique spécialisée
- [ ] Identifier zone d'intervention principale

### **Semaine 2 : Contribution**
- [ ] Choisir première tâche/amélioration
- [ ] Créer branche Git dédiée
- [ ] Développer avec tests
- [ ] Mettre à jour documentation si nécessaire
- [ ] Soumettre première contribution

---

**🎉 Bienvenue dans l'équipe Suzosky Coursier !**

*Ce guide sera mis à jour régulièrement. N'hésitez pas à suggérer des améliorations.*

---

*Guide créé le : 18 septembre 2025*  
*Dernière mise à jour : 18 septembre 2025*