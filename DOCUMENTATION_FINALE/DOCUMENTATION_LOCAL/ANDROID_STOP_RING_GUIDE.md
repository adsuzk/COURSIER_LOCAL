# Documentation Android - Arrêt de la sonnerie automatique

## ✅ SYSTÈME FONCTIONNEL

Le backend est maintenant configuré pour arrêter automatiquement la sonnerie des notifications quand le coursier répond.

### 🔄 Workflow Accept/Refuse

1. **Notification reçue** → Sonnerie démarre
2. **Coursier clique Accept/Refuse** → API appelée  
3. **API renvoie `stop_ring: true`** → Sonnerie s'arrête
4. **Statut mis à jour** → Interface actualisée

### 📱 APIs pour Android

#### 1. Accepter une commande
```
POST /api/order_response.php
Content-Type: application/json

{
    "order_id": 109,
    "coursier_id": 6, 
    "action": "accept"
}
```

**Réponse:**
```json
{
    "success": true,
    "action": "accepted",
    "order_id": 109,
    "message": "Commande acceptée avec succès",
    "new_status": "acceptee",
    "stop_ring": true  ← SIGNAL D'ARRÊT SONNERIE
}
```

#### 2. Refuser une commande
```
POST /api/order_response.php
Content-Type: application/json

{
    "order_id": 109,
    "coursier_id": 6,
    "action": "refuse"
}
```

**Réponse:**
```json
{
    "success": true,
    "action": "refused", 
    "order_id": 109,
    "message": "Commande refusée",
    "new_status": "refusee",
    "stop_ring": true  ← SIGNAL D'ARRÊT SONNERIE
}
```

### 🛠️ Implémentation Android

```kotlin
// Dans votre gestionnaire de notifications
class NotificationHandler {
    private var currentRingtone: Ringtone? = null
    
    fun handleOrderResponse(response: OrderResponse) {
        if (response.stopRing == true) {
            // ARRÊTER LA SONNERIE IMMÉDIATEMENT
            currentRingtone?.stop()
            currentRingtone = null
            
            // Actualiser l'interface
            updateOrderStatus(response.orderId, response.newStatus)
            
            // Afficher message de confirmation
            showToast(response.message)
        }
    }
}
```

### 🔔 Gestion de la sonnerie

```kotlin
// Démarrer la sonnerie à réception FCM
fun startNotificationSound() {
    val uri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
    currentRingtone = RingtoneManager.getRingtone(context, uri)
    currentRingtone?.play()
}

// Arrêter quand API renvoie stop_ring: true
fun stopNotificationSound() {
    currentRingtone?.stop()
    currentRingtone = null
}
```

### 🧪 Test réussi

- ✅ Notification envoyée au coursier
- ✅ API accept/refuse fonctionnelle  
- ✅ Signal `stop_ring: true` envoyé
- ✅ Statuts mis à jour correctement

### 🚀 Prêt pour production

Le système backend est maintenant complet. L'app Android doit juste :

1. **Écouter les réponses API** pour le champ `stop_ring`
2. **Arrêter la sonnerie** quand `stop_ring: true` 
3. **Actualiser l'interface** avec le nouveau statut

**Plus besoin d'arrêt manuel** - la sonnerie s'arrête automatiquement dès que le coursier répond ! 🎉