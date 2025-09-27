# Coursier Suzosky Mobile App

## Configuration réseau

### Pour l'émulateur Android
- L'app utilise automatiquement `10.0.2.2` pour accéder à XAMPP local

### Pour un device physique
- Configuré pour utiliser l'IP locale : `192.168.1.25`
- Vérifiez que votre PC et le device sont sur le même réseau Wi-Fi
- Vérifiez que XAMPP Apache fonctionne sur : http://192.168.1.25/coursier_prod/api/

## Endpoints testés
- ✅ `http://192.168.1.25/coursier_prod/api/auth.php` (fonctionne)
- ✅ Build Debug réussie

## Utilisation
1. Branchez votre device Android en USB
2. Activez le debug USB sur le device
3. Dans Android Studio : Run > Run 'app' 
4. Testez la connexion avec vos identifiants

## Dépannage
- Si "Serveur introuvable" : vérifiez l'IP locale avec `ipconfig`
- Si "Connexion refusée" : vérifiez que Apache est démarré
- Pour changer l'IP : modifiez `LOCAL_LAN_IP` dans `gradle.properties`