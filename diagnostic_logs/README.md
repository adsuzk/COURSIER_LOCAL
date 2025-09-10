# 🔍 DIAGNOSTIC LOGS - SYSTÈME DE LOGGING AVANCÉ

## Vue d'ensemble
Ce dossier contient un système de logging extrêmement précis pour le monitoring et le debugging de la plateforme Coursier Prod.

## Structure des fichiers

### 📋 Fichiers de logging principaux
- `advanced_logger.php` - Système de logging principal avec classe AdvancedLogger
- `log_viewer.php` - Interface web pour visualiser et analyser les logs
- `README.md` - Cette documentation

### 📊 Fichiers de logs générés
- `application.log` - Logs généraux de l'application
- `index.log` - Logs spécifiques à l'interface index.php
- `coursier.log` - Logs de l'interface coursier
- `admin.log` - Logs de l'interface administration
- `concierge.log` - Logs de l'interface concierge
- `recrutement.log` - Logs de l'interface recrutement
- `payments.log` - Logs des transactions et paiements
- `database.log` - Logs des requêtes et opérations base de données
- `user_actions.log` - Logs des actions utilisateurs
- `api.log` - Logs des appels API
- `security.log` - Logs des événements de sécurité
- `performance.log` - Logs de performance et monitoring
- `critical_errors.log` - Alertes pour erreurs critiques

### 📁 Fichiers détaillés (JSON)
- `detailed_*.log` - Versions JSON détaillées de chaque log pour analyse avancée

### 📊 Fichiers de diagnostic existants
1. **cinetpay_api.log** - Logs des interactions avec l'API CinetPay
2. **diagnostics_apache.log** - Diagnostics Apache et serveur web
3. **diagnostics_cinetpay.log** - Diagnostics spécifiques CinetPay
4. **diagnostics_db.log** - Diagnostics base de données
5. **diagnostics_env_config.txt** - Configuration environnement
6. **diagnostics_errors.log** - Logs d'erreurs générales
7. **diagnostics_files_structure.txt** - Structure des fichiers
8. **diagnostics_htaccess.txt** - Configuration .htaccess
9. **diagnostics_permissions.txt** - Permissions fichiers
10. **diagnostics_phpinfo.html** - Informations PHP complètes
11. **diagnostics_sql_commands.log** - Commandes SQL exécutées
12. **duplicate_audit.php** - Script d'audit des doublons

## 🚀 Utilisation

### Intégration dans le code
```php
// Inclure le système de logging
require_once 'diagnostic_logs/advanced_logger.php';

// Logging simple
logInfo("Message d'information", ['user_id' => 123], 'INDEX');
logError("Erreur détectée", ['error' => $e->getMessage()], 'PAYMENT');
logWarning("Tentative suspecte", ['ip' => $_SERVER['REMOTE_ADDR']], 'SECURITY');

// Logging spécialisé
logPayment('payment_success', [
    'amount' => 5000,
    'currency' => 'XOF',
    'transaction_id' => 'TXN123',
    'order_id' => 'ORD456'
]);

logDatabase($query, $params, $execution_time);
logUserAction('login', $user_id, ['ip' => $ip_address]);
logAPI('/api/orders', 'POST', 200, 0.250);
logSecurity('failed_login', 'medium', ['attempts' => 3]);
logPerformance('page_load', $start_time, $end_time);
```

### Interface de visualisation
Accéder à `diagnostic_logs/log_viewer.php` pour :
- 📊 Dashboard avec statistiques en temps réel
- 🔍 Recherche et filtrage avancés
- 📱 Interface responsive
- ⚡ Mode temps réel
- 📥 Téléchargement des logs
- 🗑️ Gestion des fichiers de logs

## 🎯 Fonctionnalités avancées

### Logging automatique
- **Erreurs PHP** : Capture automatique de toutes les erreurs PHP
- **Exceptions** : Gestion des exceptions non capturées
- **Performance** : Monitoring automatique de la mémoire et du temps d'exécution
- **Rotation** : Rotation automatique des logs (10MB max, 5 backups)

### Contexte enrichi
Chaque log contient :
- Timestamp avec microsecondes
- Utilisation mémoire (actuelle et pic)
- Informations de session
- Stack trace
- Détails de la requête HTTP
- Informations système

### Sécurité
- Sanitisation automatique des mots de passe
- Masquage des tokens sensibles
- Logging des tentatives d'intrusion
- Monitoring des actions suspectes

## 📈 Types de logs

### 🔴 CRITICAL / ERROR
Erreurs critiques nécessitant une intervention immédiate
- Échecs de connexion base de données
- Erreurs de paiement
- Exceptions fatales

### 🟡 WARNING
Avertissements à surveiller
- Tentatives de connexion échouées
- Performances dégradées
- Configurations manquantes

### 🔵 INFO
Informations générales
- Actions utilisateurs
- Succès de paiements
- Événements système

### 🟢 DEBUG
Détails pour le développement
- Requêtes SQL
- Variables d'état
- Flux d'exécution

## 🛠️ Configuration

### Paramètres modifiables dans `advanced_logger.php`
```php
private $maxLogSize = 10485760; // 10MB
private $maxBackups = 5;
```

### Variables d'environnement
Le système détecte automatiquement :
- Mode développement/production
- Configuration PHP
- Limites mémoire
- Paramètres de session

## 📋 Monitoring et alertes

### Alertes automatiques
Les erreurs critiques génèrent automatiquement des alertes dans `critical_errors.log` avec :
- Timestamp complet
- Contexte détaillé
- Stack trace
- Informations système

### Métriques de performance
- Temps d'exécution par opération
- Utilisation mémoire
- Requêtes base de données
- Appels API

## 🔧 Maintenance

### Nettoyage automatique
- Rotation des logs basée sur la taille
- Suppression automatique des anciens backups
- Compression optionnelle (configurable)

### Commandes utiles
```bash
# Voir les dernières erreurs
tail -f diagnostic_logs/critical_errors.log

# Analyser les performances
grep "PERF" diagnostic_logs/performance.log

# Surveiller les paiements
tail -f diagnostic_logs/payments.log
```

## 🚨 Dépannage

### Problèmes communs
1. **Logs non générés** : Vérifier les permissions d'écriture
2. **Interface inaccessible** : Vérifier la configuration PHP
3. **Logs trop volumineux** : Ajuster maxLogSize

### Permissions requises
```bash
chmod 755 diagnostic_logs/
chmod 644 diagnostic_logs/*.php
chmod 666 diagnostic_logs/*.log
```

## 📞 Support

Pour toute question ou problème :
1. Consulter les logs d'erreur critiques
2. Vérifier l'interface de monitoring
3. Analyser les métriques de performance
4. Contacter l'équipe de développement

---

**Dernière mise à jour** : 2024-12-19 14:30:00
**Version** : 2.0.0
**Statut** : Production Ready ✅
