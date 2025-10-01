# SYSTÈME UNIFIÉ DE COMMANDES - DOCUMENTATION FINALE

## ✅ Architecture simplifiée (2025-10-01)

### 1. CRÉATION DE COMMANDE (Client → Serveur)

**Point d'entrée unique:** `api/submit_order.php`

```
POST /api/submit_order.php
{
  "adresse_depart": "...",
  "adresse_arrivee": "...",
  "telephone_expediteur": "...",
  "telephone_destinataire": "...",
  "prix_estime": 3500,
  ...
}
```

**Flux interne:**
1. Validation des données
2. Insertion en BDD (statut = 'nouvelle')
3. **NOTIFICATION FCM** → Envoie notification à 5 coursiers max avec tokens FCM actifs
4. **DIAGNOSTIC** → Log détaillé (coursiers trouvés, notifications envoyées/échouées)
5. **ATTRIBUTION** → Assigne au premier coursier notifié avec succès (coursier_id assigné, statut reste 'nouvelle')

### 2. RÉCUPÉRATION COMMANDES (App Mobile → Serveur)

**Point d'entrée unique:** `mobile_sync_api.php`

```
GET /mobile_sync_api.php?action=get_commandes&coursier_id=5
```

**Retourne:**
- Toutes les commandes avec `coursier_id = 5` ET `statut = 'nouvelle'`
- Format JSON avec détails complets

**L'application mobile doit:**
- Appeler cette API toutes les 10-30 secondes (polling)
- Afficher les nouvelles commandes
- Permettre Accepter/Refuser

### 3. ACCEPTATION/REFUS (App Mobile → Serveur)

```
POST /mobile_sync_api.php?action=accept_commande&coursier_id=5&commande_id=148
POST /mobile_sync_api.php?action=refuse_commande&coursier_id=5&commande_id=148&raison=...
```

**Effet:**
- Accept → statut passe à 'acceptee', heure_acceptation = NOW()
- Refuse → coursier_id = NULL, statut reste 'nouvelle' (réattribution possible)

---

## 🔥 Fichiers désactivés (redondants)

❌ `auto_assign_orders.php` - Remplacé par logique dans submit_order.php
❌ `attribution_intelligente.php` - Remplacé par logique dans submit_order.php

Ces fichiers existent toujours mais retournent un message d'erreur si appelés.

---

## 📱 Configuration Application Mobile

### Endpoint principal
```
BASE_URL = http://localhost/COURSIER_LOCAL
ou
BASE_URL = https://votre-domaine.com/COURSIER_LOCAL
```

### Polling des commandes
```dart
// Toutes les 15 secondes
Timer.periodic(Duration(seconds: 15), (timer) async {
  final response = await http.get(
    Uri.parse('$BASE_URL/mobile_sync_api.php?action=get_commandes&coursier_id=$coursierId')
  );
  
  if (response.statusCode == 200) {
    final data = jsonDecode(response.body);
    if (data['success'] && data['commandes'].isNotEmpty) {
      // Afficher les nouvelles commandes
      showNewOrdersDialog(data['commandes']);
    }
  }
});
```

---

## 🔧 FCM (Firebase Cloud Messaging)

### Mode actuel: FALLBACK
Les notifications FCM sont **simulées** car la clé serveur n'est pas configurée.

**Pour activer les vraies notifications:**
1. Obtenir la clé serveur depuis Firebase Console
2. Définir la variable d'environnement: `FCM_SERVER_KEY=VOTRE_CLE`
3. Redémarrer le serveur

**Même sans FCM configuré:**
- Les commandes sont quand même créées
- Les commandes sont quand même attribuées aux coursiers
- L'app mobile récupère les commandes via polling

---

## 📊 Diagnostic

### Vérifier l'état du système
```bash
php debug_coursier_status.php
```

### Tester une nouvelle commande
```bash
php test_new_order_flow.php
```

### Logs
- `diagnostic_logs/diagnostics_errors.log` - Tous les événements
- `mobile_sync_debug.log` - Requêtes API mobile

---

## ⚡ Points clés

1. **UN SEUL point d'entrée** pour créer une commande: `api/submit_order.php`
2. **UN SEUL point d'entrée** pour l'app mobile: `mobile_sync_api.php`
3. **Statut 'nouvelle'** = commande en attente d'acceptation par le coursier
4. **Le coursier DOIT accepter** la commande sur son app pour qu'elle passe en 'acceptee'
5. **Polling obligatoire** côté app mobile (toutes les 10-30 sec)

---

## 🐛 Troubleshooting

### Problème: Coursier ne voit pas les commandes
**Vérifier:**
1. Le coursier est-il actif? (`status = 'actif'` dans agents_suzosky)
2. A-t-il un token FCM? (`SELECT * FROM device_tokens WHERE coursier_id = X`)
3. La commande est-elle attribuée? (`SELECT coursier_id FROM commandes WHERE id = X`)
4. L'app appelle-t-elle l'API? (vérifier `mobile_sync_debug.log`)

### Problème: Commande reste "En attente d'un coursier"
**Causes possibles:**
1. Aucun coursier avec token FCM actif
2. Toutes les notifications FCM ont échoué
3. Attribution n'a pas eu lieu

**Solution:**
```sql
-- Attribuer manuellement
UPDATE commandes SET coursier_id = 5, statut = 'nouvelle' WHERE id = 148;
```

---

Date: 2025-10-01
Version: 1.0 (Système unifié)
