# 🚀 GUIDE DÉPLOIEMENT RAPIDE - MOT DE PASSE OUBLIÉ

## ⏱️ Déploiement en 3 minutes

### 📥 ÉTAPE 1: Upload (30 secondes)
1. Prenez le fichier `coursier_reset_password_deploy.zip` 
2. Uploadez-le sur votre serveur via FTP ou gestionnaire de fichiers LWS
3. Décompressez dans la racine web

### ⚙️ ÉTAPE 2: Configuration automatique (1 minute)
1. Allez sur `https://coursier.conciergerie-privee-suzosky.com/deploy_reset_password.php`
2. Le script fait TOUT automatiquement :
   - Installe PHPMailer
   - Modifie la base de données
   - Configure SMTP pour LWS
   - Lance tous les tests
3. Cliquez sur "Déployer le système" 

### 📧 ÉTAPE 3: Email (1 minute)  
1. Panneau LWS → Emails → Créer `no-reply@conciergerie-privee-suzosky.com`
2. Éditez `env_override.php` → Ajoutez votre mot de passe email
3. Testez avec le bouton "Tester l'envoi"

### 🧹 ÉTAPE 4: Nettoyage (30 secondes)
Supprimez les fichiers de déploiement :
- `deploy_reset_password.php`
- `github_deploy.php`

## ✅ C'EST TERMINÉ !

Le système "Mot de passe oublié" est maintenant actif sur votre site.

## 🎯 Vérification rapide
- [ ] Upload du ZIP ✅
- [ ] Configuration automatique ✅  
- [ ] Création email ✅
- [ ] Test fonctionnel ✅
- [ ] Nettoyage sécurisé ✅

**Votre système est prêt en production ! 🎉**