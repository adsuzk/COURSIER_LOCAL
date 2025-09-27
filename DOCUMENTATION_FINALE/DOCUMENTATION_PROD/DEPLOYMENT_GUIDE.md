# 🚀 DÉPLOIEMENT PRODUCTION SUZOSKY COURSIER
**Date:** 18 septembre 2025 - **MAJ:** 26 septembre 2025
**Version:** 1.0.1 Production (Hotfix API Submit Order)

## 📁 FICHIERS À TRANSFÉRER SUR coursier.conciergerie-privee-suzosky.com

### **Fichiers Principaux**
```
/                                   (racine du sous-dossier)
├── index.php                       # Page d'accueil
├── coursier.php                    # Interface coursier web
├── config.php                      # Configuration (DB LWS)
├── applis.php                      # Liste applications
├── billing_system.php              # Système facturation
├── logger.php                      # Système logs
├── style.css                       # Styles principaux
├── robots.txt                      # SEO
├── sitemap.xml                     # SEO
└── .htaccess                       # Configuration Apache
```

### **Dossier /api/**
```
/api/
├── init_recharge.php              # API recharge CinetPay
├── get_coursier_data.php          # Données coursier temps réel
├── app_updates.php                # Mises à jour automatiques
├── cinetpay_callback.php          # Callbacks paiement
├── add_test_order.php             # Tests notifications (admin)
├── submit_order.php               # Soumission commandes [CORRIGÉ 2025-09-26]
├── auth.php                       # Authentification
├── orders.php                     # Gestion commandes
└── assign_courier.php             # Attribution coursiers
```

### **Dossier /admin/**
```
/admin/
├── admin.php                      # Interface admin
├── uploads/                       # APK et fichiers
│   └── suzosky-coursier-production.apk  # APK final
├── dashboard.php                  # Dashboard admin
├── commandes.php                  # Gestion commandes
├── finances.php                   # Gestion financière
└── functions.php                  # Fonctions communes
```

### **Dossiers Assets et Sections**
```
/assets/                           # Images, icons, favicon
/sections_index/                   # Sections page accueil
/css/                             # Styles additionnels
/lib/                             # Bibliothèques JS
/scripts/                         # Scripts utilitaires
```

## 🔧 **CONFIGURATION REQUISE SUR LE SERVEUR**

### **1. Variables d'environnement**
```bash
# Variables Apache/PHP
DocumentRoot: /coursier/
HTTPS: Activé avec certificat SSL
PHP Version: 7.4+
```

### **2. Configuration MySQL**
```php
// Déjà configuré dans config.php
Host: 185.98.131.214
Database: conci2547642_1m4twb
User: conci2547642_1m4twb
Password: wN1!_TT!yHsK6Y6
```

### **3. Permissions fichiers**
```bash
chmod 755 /api/
chmod 644 *.php
chmod 755 /admin/uploads/
chmod 644 /admin/uploads/*.apk
```

## 📱 **APPLICATION ANDROID**

### **APK Production**
- **Fichier:** `suzosky-coursier-production.apk`
- **Taille:** 18.6 MB
- **URLs:** Toutes configurées pour HTTPS
- **SSL:** Configuration sécurisée, pas de cleartext
- **Auto-update:** Activé vers coursier.conciergerie-privee-suzosky.com

### **Configuration réseau**
```kotlin
// URLs de production dans ApiService.kt
Base URL: "https://coursier.conciergerie-privee-suzosky.com/"
Recharge API: "/api/init_recharge.php" 
Data API: "/api/get_coursier_data.php"
Update API: "/api/app_updates.php"
```

## ✅ **TESTS DE VALIDATION POST-DÉPLOIEMENT**

### **1. Tests API**
```bash
# Test configuration base
curl https://coursier.conciergerie-privee-suzosky.com/api/get_coursier_data.php?coursier_id=1

# Test CinetPay
curl -X POST https://coursier.conciergerie-privee-suzosky.com/api/init_recharge.php \
  -d "coursier_id=1&montant=5000"

# Test mises à jour
curl https://coursier.conciergerie-privee-suzosky.com/api/app_updates.php?device_id=test&version_code=1
```

### **2. Tests Application**
1. **Installation APK** sur device Android
2. **Connexion** - Vérifier pas de crash
3. **Recharge** - Modal CinetPay s'ouvre
4. **Notifications** - Sons fonctionnent pour nouvelles commandes
5. **Auto-update** - Service vérifie les mises à jour

### **3. Tests Interface Web**
1. **coursier.conciergerie-privee-suzosky.com/** - Page d'accueil
2. **coursier.conciergerie-privee-suzosky.com/coursier.php** - Interface coursier
3. **coursier.conciergerie-privee-suzosky.com/admin/admin.php** - Admin panel

## 🚫 **FICHIERS À NE PAS TRANSFÉRER**

```
# Développement seulement
/CoursierAppV7/                    # Code source Android
/CloneCoursierApp/                 # Version obsolète
/Test/                             # Scripts de test
/diagnostic_logs/                  # Logs locaux
/documentation finale/             # Documentation
/DOCUMENTATION_FINALE/             # Documentation
/misc/                            # Divers développement

 # Fichiers de développement et diagnostic
- `Test/_root_migrated/`           # Tous les scripts de test et de diagnostic (anc. root-level)
*.bat                             # Scripts Windows
*.ps1                             # Scripts PowerShell
# (anciens) check_table_structure.php, diagnostic_*.php, temp_*.php   déplacés sous `Test/_root_migrated/`
```

## 🔒 **SÉCURITÉ PRODUCTION**

### **Credentials CinetPay (Configurés)**
```php
API Key: 8338609805877a8eaac7eb6.01734650
Site ID: 219503  
Secret Key: 17153003105e7ca6606cc157.46703056
```

### **Sécurisation**
- ✅ SSL/HTTPS obligatoire
- ✅ Base de données sécurisée (LWS)
- ✅ Pas de cleartext traffic
- ✅ Authentification API
- ✅ Validation des inputs
- ✅ Logs d'activité

---

## � **HOTFIX 2025-09-26 - CORRECTION CRITIQUE**

### **Problème résolu: "Réponse serveur invalide"**

#### **Scripts de maintenance déployés:**
- ✅ `restore_clients_table_lws.php` - Restauration table clients
- ✅ Exécution réussie sur serveur LWS
- ✅ Table clients opérationnelle (10 enregistrements)

#### **Corrections API submit_order.php:**
```php
// Mapping priorité pour compatibilité ENUM
$priorityMap = [
    'normal' => 'normale',     # Fix principal
    'urgent' => 'urgente',
    'express' => 'express'
];
```

#### **Validation post-déploiement:**
- ✅ Formulaire commande fonctionnel
- ✅ Attribution coursiers automatique  
- ✅ Notifications FCM opérationnelles
- ✅ Paiement CinetPay intégré

---

## �🚀 **COMMANDES DE DÉPLOIEMENT**

1. **Uploader tous les fichiers** listés ci-dessus via FTP/SFTP
2. **Configurer Apache** avec .htaccess
3. **Tester les URLs** API
4. **Installer l'APK** sur un device test
5. **Lancer cleanup_production.php** pour nettoyer les données test
6. **Valider** le fonctionnement complet
7. **🆕 HOTFIX:** Uploader `api/submit_order.php` modifié (2025-09-26)

**✅ Système prêt pour production !**