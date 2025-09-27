# Guide de Test - Application Coursier

## Identifiants de Test
**Email:** test@test.com
**Mot de passe:** abcde

## Étapes de Test

### 1. Installation et Lancement
1. Dans Android Studio : Run > Run 'app' (ou Shift+F10)
2. Sélectionnez votre appareil physique
3. L'APK sera installé automatiquement

### 2. Test de Connexion
1. Ouvrez l'application
2. Sur l'écran de connexion, saisissez :
   - **Email/Téléphone:** test@test.com
   - **Mot de passe:** abcde
3. Appuyez sur "Se connecter"

### 3. Résolution des Problèmes

#### Si "Erreur de connexion" :
- Vérifiez que votre appareil est sur le même Wi-Fi que votre PC (192.168.1.25)
- Vérifiez que XAMPP Apache est démarré

#### Si "Email/téléphone ou mot de passe incorrect" :
- Utilisez exactement : test@test.com / abcde

#### Si "Erreur inconnue" :
- Redémarrez XAMPP Apache
- Vérifiez l'URL dans les logs : doit pointer vers 192.168.1.25

## Configuration Réseau
- **Adresse IP locale:** 192.168.1.25
- **URL de base (debug):** http://192.168.1.25/coursier_prod/api/
- **Protocole:** HTTP (autorisé en mode debug)

## Tests Supplémentaires
Une fois connecté :
1. Test d'estimation de prix (Cocody vers Yopougon par exemple)
2. Test de création de commande
3. Test de déconnexion

## En Cas de Problème
1. Vérifiez les logs Android Studio (Logcat)
2. Testez l'API depuis le navigateur : http://192.168.1.25/coursier_prod/api/auth.php
3. Assurez-vous que l'appareil et le PC sont sur le même réseau Wi-Fi