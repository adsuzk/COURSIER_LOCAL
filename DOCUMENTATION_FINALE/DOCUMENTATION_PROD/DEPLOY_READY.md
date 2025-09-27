# 🚀 GUIDE DE DÉPLOIEMENT - SERVEUR PRODUCTION
*Mise à jour : 18 septembre 2025 - Inclut système de télémétrie*

## ✅ Configuration Base de Données (PRÊTE)
- **Serveur MySQL**: 185.98.131.214
- **Base de données**: conci2547642_1m4twb  
- **Utilisateur**: conci2547642_1m4twb
- **Mot de passe**: wN1!_TT!yHsK6Y6
- **Port**: 3306

## 🆕 SYSTÈME DE TÉLÉMÉTRIE - ÉTAPES CRITIQUES

### **🔴 OBLIGATOIRE : Déploiement des tables télémétrie**
Avant d'utiliser `admin.php?section=app_updates`, vous DEVEZ créer les tables :

#### **Option 1 : Script automatisé (RECOMMANDÉ)**
```bash
# Accéder à votre site et exécuter
https://votre-domaine.com/deploy_telemetry_production.php
```

#### **Option 2 : Exécution SQL manuelle**
```sql
-- Via phpMyAdmin, exécuter le fichier :
DEPLOY_TELEMETRY_PRODUCTION.sql
```

#### **Option 3 : Script réparé**
```bash
# Si l'ancien script ne marche pas :
https://votre-domaine.com/setup_telemetry.php
```

### **📊 Tables créées (6 tables + 1 vue)**
- `app_devices` - Informations des appareils Android
- `app_versions` - Versions d'application disponibles  
- `app_crashes` - Logs de crashes et erreurs
- `app_events` - Événements d'usage et analytics
- `app_sessions` - Sessions utilisateur
- `app_notifications` - Notifications push
- `view_device_stats` - Vue pour statistiques

### **✅ Vérification déploiement télémétrie**
Après déploiement, vérifier :
```bash
# Tester l'API télémétrie
https://votre-domaine.com/api/telemetry.php?action=get_stats

# Accéder au dashboard
https://votre-domaine.com/admin.php?section=app_updates
```

## ✅ Corrections Appliquées
1. **Chemins hardcodés corrigés** dans :
   - `assets/js/connexion_modal.js` 
   - `sections index/js_authentication.php`

2. **Détection environnement production** mise à jour pour :
   - suzosky
   - conciergerie-privee-suzosky.com
   - coursier.conciergerie-privee-suzosky.com
   - lws-hosting.com
   - lws.fr

3. **ROOT_PATH automatique** : 
   - Local : `/COURSIER_LOCAL`
   - Production : `/` (racine du site)

## 📁 Structure de fichiers à uploader
```
/
├── index.php (point d'entrée)
├── config.php (avec credentials BDD production)
├── .htaccess (redirections et sécurité)
├── sections index/ (tous les modules)
├── api/ (endpoints API + télémétrie)
├── assets/ (CSS, JS, images)
├── admin/ (interface admin + monitoring)
├── diagnostic_logs/ (logs système)
├── cinetpay/ (paiement)
├── deploy_telemetry_production.php (script télémétrie)
└── DEPLOY_TELEMETRY_PRODUCTION.sql (backup SQL)
```

## 🔧 Extensions PHP requises sur le serveur
- ✅ PDO
- ✅ PDO_MySQL  
- ✅ cURL
- ✅ JSON (pour données télémétrie)
- ✅ mbstring
- ✅ OpenSSL

## 🎯 ÉTAPES DE DÉPLOIEMENT

### 1. Upload des fichiers
- Uploader TOUS les fichiers du projet
- **IMPORTANT** : Inclure `deploy_telemetry_production.php`
- **IMPORTANT** : Inclure `DEPLOY_TELEMETRY_PRODUCTION.sql`

### 2. Configuration base de données
- Tables principales déjà créées ✅
- **NOUVEAU** : Créer les tables télémétrie (voir section ci-dessus)

### 3. Test télémétrie
```bash
# Étape 1 : Déployer les tables
https://coursier.conciergerie-privee-suzosky.com/deploy_telemetry_production.php

# Étape 2 : Tester l'API
https://coursier.conciergerie-privee-suzosky.com/api/telemetry.php?action=get_stats

# Étape 3 : Vérifier le dashboard
https://coursier.conciergerie-privee-suzosky.com/admin.php?section=app_updates
```
- Uploader tous les fichiers à la racine du domaine
- Vérifier les permissions (755 pour dossiers, 644 pour fichiers)

### 2. Test de fonctionnement
- Accéder à `https://votre-domaine.com/`
- Vérifier que la page se charge sans erreur
- Tester le formulaire de commande
- Tester la connexion utilisateur

### 3. Vérifications post-déploiement
- Logs d'erreur dans `diagnostic_logs/`
- Connexion à la base de données
- API de paiement CinetPay
- API Google Maps

## ⚠️ Points d'attention
1. **Permissions** : Le serveur doit pouvoir écrire dans `diagnostic_logs/`
2. **PHP Version** : Compatible PHP 7.4+
3. **Variables d'environnement** : `$_SERVER['HTTP_HOST']` doit contenir le bon domaine
4. **HTTPS** : Forcé via .htaccess pour la sécurité

## 🆘 En cas de problème
1. Vérifier les logs d'erreur PHP du serveur
2. Contrôler le fichier `diagnostic_logs/errors.log`
3. Tester la connexion BDD avec un script simple
4. Vérifier que toutes les extensions PHP sont installées

## 🎉 VOTRE APPLICATION EST PRÊTE POUR LA PRODUCTION !

---

## ✅ Check-list de Validation (Automatisation Finances)

1) Tables finances présentes et correctes
- Ouvrir `fix_production.php` → doit afficher « Tables finances créées/vérifiées ».
- Vérifier que les FK pointent vers `agents_suzosky(id)`.

2) Backfill des comptes coursiers
- `fix_production.php` indique le nombre de comptes créés.
- Ouvrir l’admin (`admin.php`) → backfill silencieux s’exécute aussi.

3) Provisionnement automatique
- Créer un nouvel agent coursier via `admin.php?section=agents`.
- Vérifier côté finances que le compte est créé instantanément (solde 0).

4) Recharge CinetPay → crédit automatique
- Lancer une recharge depuis l’app/API.
- Vérifier: solde crédité + entrée unique en `transactions_financieres` (référence idempotente).

5) Sécurité (optionnel)
- Définir la variable d’environnement `CINETPAY_WEBHOOK_SECRET`.
- Vérifier que les callbacks sont journalisés et acceptés.

Référence détaillée: `DOCUMENTATION_FINALE/CHANGelog_FINANCES_AUTOMATION_2025-09-18.md`.