# AUDIT SYSTÈME DE COMMANDES - NETTOYAGE REQUIS

## Problème identifié
Multiples systèmes qui se chevauchent sans coordination claire.

## Fichiers trouvés gérant les commandes:

### APIs principales
1. **api/submit_order.php** - Création de commandes (✅ GARDER - système principal)
2. **mobile_sync_api.php** - API mobile avec `get_commandes` (✅ GARDER - utilisé par l'app)

### Systèmes d'attribution
3. **auto_assign_orders.php** - Attribution automatique (❌ REDONDANT avec submit_order.php)
4. **attribution_intelligente.php** - Autre système d'attribution (❌ REDONDANT)
5. **find_coursier.php** - Recherche de coursiers (❓ À vérifier)
6. **assign_last_order.php** - Attribution manuelle (❓ À vérifier)

### Système FCM
7. **fcm_manager.php** - Gestionnaire FCM (✅ GARDER)
8. **status_fcm.php** - Status FCM (❓ Utilité?)

### Tests (peuvent être supprimés en production)
9. test_new_order_flow.php
10. test_fcm_direct_sender.php
11. test_commande_complete.php
12. test_commande_directe.php
13. Tests/api_test_push_new_order.php
14. Tests/simulateur_fcm_test.php

## DÉCISION ARCHITECTURALE SIMPLE

### Flux unique à conserver:

```
CLIENT SOUMET COMMANDE
  ↓
api/submit_order.php (SEUL POINT D'ENTRÉE)
  ↓
1. Insère commande en BDD (statut: 'nouvelle')
2. Trouve coursier avec token FCM actif
3. Envoie notification FCM (ou fallback simulé)
4. Attribue commande (coursier_id assigné, statut reste 'nouvelle')
  ↓
APP MOBILE RÉCUPÈRE
  ↓
mobile_sync_api.php?action=get_commandes&coursier_id=X
  ↓
Retourne TOUTES les commandes statut='nouvelle' du coursier
  ↓
COURSIER ACCEPTE/REFUSE
  ↓
mobile_sync_api.php?action=accept_commande
mobile_sync_api.php?action=refuse_commande
```

## Actions à prendre:

1. ✅ Désactiver/supprimer `auto_assign_orders.php`
2. ✅ Désactiver/supprimer `attribution_intelligente.php`
3. ✅ Nettoyer les fichiers de test en production
4. ✅ S'assurer qu'UN SEUL système gère l'attribution
5. ✅ Documenter le flux unique
