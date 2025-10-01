# CONFIGURATION CLÉ FCM - INSTRUCTIONS

## ⚠️ PROBLÈME ACTUEL
Les notifications FCM sont en **MODE FALLBACK** (simulation).
Elles sont "envoyées" dans les logs mais N'ATTEIGNENT PAS le téléphone.

## 🔑 SOLUTION 1: Configurer la clé FCM (RECOMMANDÉ)

### Étape 1: Obtenir la clé serveur
1. Allez sur https://console.firebase.google.com
2. Sélectionnez votre projet: **coursier-suzosky**
3. ⚙️ Paramètres du projet > Cloud Messaging
4. Copiez la **"Clé de serveur"** (Server key)

### Étape 2: Configurer la clé

**Option A - Variable d'environnement (PowerShell):**
```powershell
$env:FCM_SERVER_KEY="VOTRE_CLE_ICI"
```

**Option B - Fichier .env (à créer à la racine):**
```
FCM_SERVER_KEY=VOTRE_CLE_ICI
```

**Option C - Éditer fcm_manager.php directement:**
Ligne 22, remplacer:
```php
return getenv('FCM_SERVER_KEY') ?: 'LEGACY_KEY_NOT_CONFIGURED';
```
par:
```php
return 'VOTRE_CLE_FCM_ICI';
```

### Étape 3: Redémarrer et tester
```bash
php test_new_order_flow.php
```

---

## 📱 SOLUTION 2: Activer le polling dans l'app mobile

Si vous ne pouvez pas configurer FCM immédiatement, l'app mobile DOIT faire du polling.

### Code à ajouter dans l'application Flutter:

```dart
import 'dart:async';
import 'package:http/http.dart' as http;
import 'dart:convert';

class OrderPollingService {
  static const String BASE_URL = 'http://VOTRE_IP/COURSIER_LOCAL';
  Timer? _pollingTimer;
  int coursierId;
  
  OrderPollingService(this.coursierId);
  
  void startPolling() {
    // Polling toutes les 10 secondes
    _pollingTimer = Timer.periodic(Duration(seconds: 10), (timer) async {
      await checkForNewOrders();
    });
  }
  
  Future<void> checkForNewOrders() async {
    try {
      final response = await http.get(
        Uri.parse('$BASE_URL/mobile_sync_api.php?action=get_commandes&coursier_id=$coursierId')
      );
      
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        
        if (data['success'] && data['commandes'] != null && data['commandes'].isNotEmpty) {
          // AFFICHER LA NOTIFICATION/DIALOG
          _showNewOrderDialog(data['commandes']);
        }
      }
    } catch (e) {
      print('Erreur polling: $e');
    }
  }
  
  void _showNewOrderDialog(List commandes) {
    // Afficher dialog avec accepter/refuser
    for (var commande in commandes) {
      if (commande['statut'] == 'nouvelle') {
        // VOTRE CODE POUR AFFICHER LE DIALOG
        print('Nouvelle commande: ${commande['code_commande']}');
      }
    }
  }
  
  void stopPolling() {
    _pollingTimer?.cancel();
  }
}

// UTILISATION:
// Dans votre écran principal coursier:
final pollingService = OrderPollingService(coursierId);
pollingService.startPolling();  // Démarrer au login
```

---

## 🎯 QUELLE SOLUTION CHOISIR ?

### Si vous avez accès à Firebase Console:
✅ **SOLUTION 1** - Configurer FCM (5 minutes)
- Notifications instantanées
- Moins de consommation batterie
- Professionnel

### Si vous n'avez pas accès immédiatement:
✅ **SOLUTION 2** - Polling (déjà prêt côté serveur)
- Fonctionne MAINTENANT
- API déjà corrigée et testée
- Léger délai (10 secondes max)

---

## ✅ VÉRIFICATION

### Test API (fonctionne déjà):
```bash
php test_api_mobile.php
```
Devrait retourner toutes les commandes en attente.

### Test avec polling simulé:
```bash
# PowerShell - Simuler le polling
while ($true) { 
    curl http://localhost/COURSIER_LOCAL/mobile_sync_api.php?action=get_commandes&coursier_id=5 
    Start-Sleep -Seconds 10 
}
```

---

## 📊 ÉTAT ACTUEL (2025-10-01 13:15)

- ✅ API mobile fonctionne (colonnes corrigées)
- ✅ Coursier #5 a un token FCM actif
- ✅ Commandes créées et attribuées (#148, #149, #150, #151)
- ❌ FCM en mode fallback (clé non configurée)
- ❌ App mobile ne reçoit rien (pas de polling configuré)

**ACTION REQUISE:** Choisir Solution 1 OU 2
