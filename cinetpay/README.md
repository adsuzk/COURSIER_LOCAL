# 💳 DOSSIER CINETPAY - SUZOSKY COURSIER

Ce dossier contient tous les fichiers liés à l'intégration CinetPay pour le système de paiement du service de coursier Suzosky.

## 📁 STRUCTURE DU DOSSIER

```
cinetpay/
├── config.php                    # Configuration centralisée CinetPay
├── cinetpay_integration.php      # Classe d'intégration principale
├── cinetpay_integration_fixed.php # Version corrigée de l'intégration
├── payment_notify.php            # Gestionnaire de notifications IPN
├── payment_return.php            # Gestionnaire de retour de paiement
├── cinetpay_tables.sql           # Structure des tables CinetPay
├── validation_cinetpay.html      # Page de validation du système
├── rapport_final_cinetpay.php    # Rapport final d'intégration
├── diagnostics_cinetpay.log      # Logs de diagnostic
├── logs/                         # Dossier des logs quotidiens
└── README.md                     # Cette documentation
```

## ⚙️ FICHIERS PRINCIPAUX

### **config.php**
- Configuration centralisée de CinetPay
- Constantes API (clés, URLs, timeouts)
- Méthodes de paiement supportées
- Fonctions utilitaires de logging

### **cinetpay_integration.php**
- Classe principale `SuzoskyCinetPayIntegration`
- Méthodes d'initiation de paiement
- Gestion des notifications webhook
- Vérification des transactions

### **payment_notify.php**
- Point d'entrée pour les notifications CinetPay (IPN)
- Traitement des webhooks de statut de paiement
- Mise à jour automatique des commandes

### **payment_return.php**
- Page de retour après paiement
- Vérification finale des transactions
- Redirection vers la confirmation

## 🔧 CONFIGURATION

### **1. Variables d'environnement à configurer :**
```php
define('CINETPAY_API_KEY', 'votre_cle_api');
define('CINETPAY_SITE_ID', 'votre_site_id');
define('CINETPAY_SECRET_KEY', 'votre_cle_secrete');
```

### **2. URLs de notification :**
- **Notify URL** : `https://votre-site.com/cinetpay/payment_notify.php`
- **Return URL** : `https://votre-site.com/cinetpay/payment_return.php`

### **3. Méthodes de paiement supportées :**
- Orange Money Côte d'Ivoire
- MTN Mobile Money
- Moov Money
- Wave
- Cartes Visa/MasterCard

## 🚀 UTILISATION

### **Initier un paiement :**
```php
require_once 'cinetpay/config.php';
require_once 'cinetpay/cinetpay_integration.php';

$cinetpay = new SuzoskyCinetPayIntegration();
$result = $cinetpay->initiateOrderPayment($orderNumber, $amount);
```

### **Vérifier un paiement :**
```php
$verification = $cinetpay->verifyPayment($transactionId);
```

## 📊 LOGS ET MONITORING

### **Logs automatiques :**
- `logs/cinetpay_YYYY-MM-DD.log` : Logs quotidiens
- `diagnostics_cinetpay.log` : Logs de diagnostic
- Niveaux : DEBUG, INFO, WARNING, ERROR

### **Monitoring :**
- Surveillance des timeouts
- Tracking des échecs de paiement
- Alertes de sécurité

## 🔒 SÉCURITÉ

### **Mesures implémentées :**
- Vérification des signatures webhook
- Validation des montants
- Protection contre les rejeux
- Logs de sécurité détaillés

### **Bonnes pratiques :**
- Secrets en variables d'environnement
- HTTPS obligatoire en production
- Validation côté serveur
- Timeouts appropriés

## 🧪 TESTS ET VALIDATION

### **Environnement de test :**
```php
define('CINETPAY_ENV', 'TEST');
```

### **Validation du système :**
- Consultez `validation_cinetpay.html`
- Vérifiez `rapport_final_cinetpay.php`
- Testez les paiements avec de petits montants

## 📞 SUPPORT

### **Documentation CinetPay :**
- API Documentation : https://docs.cinetpay.com
- Support technique : support@cinetpay.com

### **Support Suzosky :**
- Email : dev@conciergerie-privee-suzosky.com
- Logs détaillés disponibles pour diagnostic

---

*📝 Documentation générée le 5 septembre 2025*
*🔄 Organisation modulaire des fichiers CinetPay*
