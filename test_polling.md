# 🧪 TEST DU POLLING AUTOMATIQUE

## Modifications apportées

### MainActivity.kt (lignes 760-795)
Ajout d'un **LaunchedEffect** pour polling automatique :
- **Fréquence** : Toutes les 10 secondes
- **Mécanisme** : Appelle `ApiService.getCoursierData()` en boucle
- **Détection** : 
  - Compare le nombre de commandes (nouvelles commandes)
  - Compare les statuts (changements d'état)
- **Action** : Incrémente `refreshTrigger` pour rafraîchir l'UI

### Logs de debug ajoutés
```kotlin
Log.d("MainActivity", "🔄 Démarrage du polling automatique des commandes")
Log.d("MainActivity", "🔍 Polling: Vérification des nouvelles commandes...")
Log.d("MainActivity", "📊 Polling: ${nbCommandesRecues} commandes (avant: ${nbCommandesActuelles})")
Log.d("MainActivity", "🆕 NOUVELLE COMMANDE DÉTECTÉE ! Refresh automatique...")
Log.d("MainActivity", "🔄 Commande $cmdId: statut changé de ${existante.statut} → $cmdStatut")
```

## Instructions de test

### Étape 1 : Préparer l'environnement
1. ✅ APK installé sur le téléphone (app-debug.apk - 10/01/2025 22:13)
2. ✅ Coursier connecté (ID: 5)
3. ✅ Commandes #156 et #157 en attente (statut: 'nouvelle')

### Étape 2 : Lancer logcat
```powershell
adb logcat -c  # Nettoyer les logs
adb logcat | Select-String "MainActivity"  # Suivre les logs de polling
```

### Étape 3 : Créer une commande depuis l'index
1. Ouvrir http://192.168.1.5/COURSIER_LOCAL/index.php
2. Créer une nouvelle commande
3. Vérifier l'attribution à coursier #5

### Étape 4 : Observer le téléphone
- **Délai attendu** : Maximum 10 secondes
- **Comportement attendu** : 
  - Log "🆕 NOUVELLE COMMANDE DÉTECTÉE !"
  - UI se rafraîchit automatiquement
  - Nouvelle commande apparaît dans la liste

## Résultats attendus

### Backend (get_coursier_data.php)
- ✅ Requête inclut statut 'nouvelle'
- ✅ Retourne les commandes #156, #157, #158 (nouvelle)

### Android (MainActivity.kt)
- ✅ Polling toutes les 10s
- ✅ Détection du changement (nbCommandesRecues = 3, nbCommandesActuelles = 2)
- ✅ Incrémente refreshTrigger
- ✅ LaunchedEffect principal re-déclenche
- ✅ UI affiche la nouvelle commande

## Commandes utiles

### Vérifier le statut du téléphone
```powershell
adb devices
```

### Suivre les logs de l'app
```powershell
adb logcat | Select-String "MainActivity|CoursierScreen|ApiService"
```

### Forcer un refresh de l'app
1. Fermer l'app
2. Relancer depuis l'écran d'accueil

### Créer une commande de test
```powershell
php check_last_orders.php  # Vérifier les commandes existantes
```

## Problèmes potentiels

### Si le polling ne fonctionne pas
1. Vérifier que l'app est en premier plan (LaunchedEffect peut être pausé en arrière-plan)
2. Vérifier la connexion réseau (192.168.1.5 accessible)
3. Vérifier les logs pour "Polling: Erreur API"

### Si la commande n'apparaît pas
1. Vérifier l'attribution (doit être coursier_id=5)
2. Vérifier le statut (doit être 'nouvelle', 'attente', ou 'assignee')
3. Vérifier que get_coursier_data.php retourne la commande

## Prochaines étapes

Une fois le polling validé :
1. ✅ **Phase 1 TERMINÉE** : Timeline + Débit + Polling
2. 🔄 **Phase 2** : Admin SSE (20 min)
3. 🔄 **Phase 3** : Android UX (40 min)
   - VoiceGuidanceService
   - Google Maps auto-launch
   - Notifications sonores
   - État "En attente d'une nouvelle commande"
