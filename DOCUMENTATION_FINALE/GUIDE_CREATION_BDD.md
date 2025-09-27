# 🗄️ Guide d'utilisation - Création automatique des bases de données

## 📋 Vue d'ensemble
Ce script analyse automatiquement tous les fichiers SQL dans le dossier `_sql` et crée les bases de données manquantes sur votre serveur LWS.

## 🚀 Utilisation sur serveur LWS

### Méthode 1: Interface web (Recommandée)
1. **Accédez à l'interface d'administration :**
   ```
   https://votre-domaine.com/admin_tools.php
   ```

2. **Cliquez sur "Création des bases de données"**

3. **Ou accédez directement au script :**
   ```
   https://votre-domaine.com/create_missing_databases.php
   ```

### Méthode 2: Accès direct au fichier
Uploadez le fichier sur votre serveur LWS et accédez-y via votre navigateur.

## 📁 Fichiers analysés
Le script examine automatiquement ces fichiers SQL :
- `database_setup.sql` - Configuration principale
- `database_finances_setup.sql` - Système financier
- `database_telemetry_setup.sql` - Télémétrie
- `create_clients_table.sql` - Table clients
- `create_chat_tables.sql` - Système de chat
- `create_commandes_coursier_table.sql` - Commandes
- `create_reclamations_table.sql` - Réclamations
- Et tous les autres fichiers `.sql` du dossier `_sql`

## 🔧 Configuration automatique

### Détection d'environnement
Le script détecte automatiquement :
- ✅ **Production** : Si exécuté sur serveur LWS
- ✅ **Développement** : Si exécuté localement

### Connexion base de données
- **Production** : Utilise la configuration LWS depuis `config.php`
- **Développement** : Utilise XAMPP local

## 📊 Que fait le script ?

1. **🔍 Analyse** - Scanne tous les fichiers SQL
2. **📋 Inventaire** - Liste les bases requises vs existantes  
3. **🗄️ Création** - Crée uniquement les bases manquantes
4. **✅ Vérification** - Confirme la création réussie
5. **📈 Rapport** - Affiche un résumé complet

## 🛡️ Sécurité

### Protections intégrées
- ✅ Ne crée que les bases manquantes
- ✅ Utilise `CREATE DATABASE IF NOT EXISTS`
- ✅ Encodage UTF8MB4 par défaut
- ✅ Gestion des erreurs complète

### Bases système ignorées
Le script ignore automatiquement :
- `information_schema`
- `mysql`
- `performance_schema`  
- `sys`
- `phpmyadmin`

## 📱 Interface web

L'interface web offre :
- 🎨 **Design moderne** - Interface responsive
- 📊 **Rapport détaillé** - Avec codes couleur
- 🔄 **Réexécution facile** - Bouton de relance
- ⚡ **Temps réel** - Affichage instantané des résultats

## 🚨 En cas de problème

### Erreurs courantes
1. **Connexion MySQL** - Vérifiez `config.php`
2. **Permissions** - Assurez-vous que l'utilisateur MySQL peut créer des bases
3. **Fichiers SQL** - Vérifiez que le dossier `_sql` existe

### Logs et debug
Le script affiche :
- ✅ Succès en vert
- ❌ Erreurs en rouge  
- ⚠️ Avertissements en orange
- 📊 Informations en bleu

## 📞 Support

En cas de problème :
1. Vérifiez les logs d'erreur
2. Consultez la configuration MySQL
3. Testez la connexion base de données

---
**Créé le 27/09/2025 - Système Suzosky**