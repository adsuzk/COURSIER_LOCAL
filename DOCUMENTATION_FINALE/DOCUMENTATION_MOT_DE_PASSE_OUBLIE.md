# 🔐 SYSTÈME "MOT DE PASSE OUBLIÉ" - DOCUMENTATION COMPLÈTE

## 🎯 Vue d'ensemble
Ce système permet aux clients de réinitialiser leur mot de passe via email de façon entièrement automatique et sécurisée.

## 📦 Fichiers déployés

### ✅ Fichiers principaux
- `deploy_reset_password.php` - Script de déploiement automatique
- `github_deploy.php` - Déploiement automatique via GitHub
- `coursier_reset_password_deploy.zip` - Package complet prêt à uploader

### ✅ Fichiers modifiés
- `composer.json` - Ajout de PHPMailer
- `api/index.php` - Nouveaux endpoints reset password
- `sections_index/reset_password.php` - Page de réinitialisation
- `assets/js/reset_password.js` - Script de gestion du formulaire
- `assets/js/connexion_modal.js` - Mise à jour du modal de connexion

## 🚀 MÉTHODES DE DÉPLOIEMENT

### 📘 MÉTHODE 1: Upload manuel du ZIP (RECOMMANDÉE)

1. **Téléchargez le ZIP**
   ```
   coursier_reset_password_deploy.zip (créé automatiquement)
   ```

2. **Uploadez sur votre serveur**
   - Via FileZilla/WinSCP vers la racine web
   - Via le gestionnaire de fichiers LWS
   - Décompressez sur le serveur

3. **Lancez l'installation**
   ```
   https://coursier.conciergerie-privee-suzosky.com/deploy_reset_password.php
   ```

### 📗 MÉTHODE 2: Déploiement GitHub automatique

1. **Uploadez UNIQUEMENT ce fichier**
   ```
   github_deploy.php
   ```

2. **Accédez au déploiement automatique**
   ```
   https://coursier.conciergerie-privee-suzosky.com/github_deploy.php
   ```

3. **Suivez le lien vers la configuration**
   Le script vous redirigera vers `deploy_reset_password.php`

### 📙 MÉTHODE 3: Upload fichier par fichier

Si vous préférez l'upload manuel, voici l'ordre recommandé :

```
1. deploy_reset_password.php         (en premier)
2. composer.json                     
3. api/index.php                     
4. sections_index/reset_password.php 
5. assets/js/reset_password.js       
6. assets/js/connexion_modal.js      
7. vendor/ (si disponible localement)
```

## ⚙️ CONFIGURATION AUTOMATIQUE

Le script `deploy_reset_password.php` fait automatiquement :

### 🔍 Détection intelligente
- ✅ **Hébergeur**: LWS détecté automatiquement
- ✅ **Domaine**: conciergerie-privee-suzosky.com
- ✅ **SMTP Host**: mail.conciergerie-privee-suzosky.com  
- ✅ **Email**: no-reply@conciergerie-privee-suzosky.com

### 🛠️ Installations automatiques
- ✅ **PHPMailer**: Via Composer
- ✅ **Base de données**: Colonnes `reset_token` et `reset_expires_at`
- ✅ **Permissions**: Fichiers et dossiers
- ✅ **Tests**: Endpoints et pages

### 📧 Configuration SMTP générée
```php
// Généré automatiquement dans env_override.php
putenv('SMTP_HOST=mail.conciergerie-privee-suzosky.com');
putenv('SMTP_PORT=587');
putenv('SMTP_USER=no-reply@conciergerie-privee-suzosky.com');
putenv('SMTP_PASS=VOTRE_MOT_DE_PASSE_EMAIL'); // ← À remplir
```

## 📋 ÉTAPES POST-DÉPLOIEMENT

### 1️⃣ Créer l'adresse email (LWS)
```
Panneau LWS → Emails → Nouvelle adresse
- Email: no-reply@conciergerie-privee-suzosky.com
- Mot de passe: [Choisissez un mot de passe fort]
```

### 2️⃣ Configurer le mot de passe SMTP
Éditez le fichier généré `env_override.php` :
```php
putenv('SMTP_PASS=VotreMotDePasseEmailIci');
```

### 3️⃣ Tester le système
Via le bouton "Tester l'envoi" sur la page de déploiement

### 4️⃣ Sécurité finale
```bash
# Supprimer les scripts de déploiement
rm deploy_reset_password.php
rm github_deploy.php
```

## 🧪 TESTS INTÉGRÉS

Le système inclut des tests automatiques :

### ✅ Tests API
- Endpoint `particulier_reset_password`
- Endpoint `particulier_do_reset_password`  
- Génération et validation des tokens
- Expiration des tokens (1 heure)

### ✅ Tests interface
- Page `reset_password.php` accessible
- Formulaire fonctionnel
- Validation JavaScript active

### ✅ Tests SMTP  
- Configuration détectée
- Envoi simulé (mode local)
- Envoi réel (mode production)

## 🔐 SÉCURITÉ

### 🛡️ Tokens sécurisés
- Générés avec `bin2hex(random_bytes(16))`
- Expiration automatique (1 heure)
- Suppression après utilisation

### 🛡️ Validation stricte
- Mots de passe de 5 caractères exactement
- Hash avec `PASSWORD_DEFAULT`
- Protection contre les attaques par force brute

### 🛡️ Configuration sécurisée
- Variables d'environnement pour SMTP
- Pas de mots de passe en dur dans le code
- Nettoyage automatique des scripts

## 🎯 UTILISATION FINALE

### Pour l'utilisateur :
1. Clic sur "Mot de passe oublié" 
2. Saisie de l'email ou téléphone
3. Réception de l'email avec le lien
4. Clic sur le lien et saisie du nouveau mot de passe
5. Connexion avec le nouveau mot de passe

### Pour l'admin :
- Logs automatiques dans `api/logs/`
- Monitoring des envois d'emails
- Statistiques d'utilisation disponibles

## 🆘 DÉPANNAGE

### Problème: Pas d'email reçu
```
1. Vérifier env_override.php (mot de passe SMTP)
2. Vérifier que l'adresse email existe sur LWS
3. Consulter les logs: api/logs/api_*.log
4. Tester avec le bouton de test intégré
```

### Problème: Erreur 500
```
1. Vérifier les permissions (755 pour dossiers, 644 pour fichiers)
2. Vérifier que Composer est installé
3. Vérifier les logs d'erreur du serveur
```

### Problème: Token invalide
```
1. Les tokens expirent en 1 heure
2. Chaque token ne peut être utilisé qu'une fois
3. Demander un nouveau lien si nécessaire
```

## 📞 SUPPORT

En cas de problème, les logs détaillés sont disponibles dans :
- `api/logs/api_*.log` - Logs de l'API
- Logs d'erreur du serveur web
- Console de débogage du navigateur

---

**🎉 Système prêt pour la production !**

Dernière mise à jour: 25 septembre 2025
Version: 1.0.0