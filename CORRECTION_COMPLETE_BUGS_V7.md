# 🔧 CORRECTION COMPLÈTE - BUGS COURSIERAPPV7

**Date**: 2 octobre 2025  
**Version**: CoursierAppV7  
**Problèmes identifiés**: 2 bugs critiques

---

## ❌ **PROBLÈME 1: Timeline disparaît après acceptation**

### 🔍 **Diagnostic**

1. **Workflow actuel**:
   ```
   User clique "Accepter"
   → order_response.php change statut "nouvelle" → "acceptee"
   → MainActivity.polling vérifie nouvelles commandes
   → get_coursier_data.php filtre: WHERE statut IN ('nouvelle', 'acceptee', 'en_cours')
   → Ligne 169 exclut 'recuperee' et 'picked_up'
   → Après progression, commande disparaît de la liste !
   ```

2. **Race condition**:
   ```kotlin
   // Dans MainActivity polling (ligne 865-937)
   if (nbCommandesRecues > nbCommandesActuelles) {
       // Notification se déclenche
   }
   ```
   **MAIS** après acceptation, l'API continue de renvoyer la commande "acceptee", donc `nbCommandesRecues == nbCommandesActuelles` → **Aucune notification !**

3. **Impact sur CoursierScreenNew.kt**:
   ```kotlin
   // LaunchedEffect(commandes) ligne 118-155
   // Si commande absente de l'API, elle est retirée de localCommandes
   // currentOrder devient null → retour à l'écran PENDING
   ```

---

## ❌ **PROBLÈME 2: Notification ne s'affiche pas quand l'app est ouverte**

### 🔍 **Diagnostic**

1. **Polling détecte mal les nouvelles commandes**:
   ```kotlin
   // MainActivity ligne 887-891
   if (nbCommandesRecues > nbCommandesActuelles) {
       Log.d("MainActivity", "🆕 NOUVELLE COMMANDE DÉTECTÉE !")
       // Vibration + Son + Voix
   }
   ```
   **Problème**: Compare uniquement le NOMBRE de commandes, pas les IDs !
   
   Si une commande est acceptée (disparaît) et une nouvelle arrive (apparaît), le nombre reste identique → **Pas de notification !**

2. **FCMService ne gère pas l'app au premier plan**:
   ```kotlin
   // FCMService.kt ligne 95-115
   override fun onMessageReceived(message: RemoteMessage) {
       // Toujours affiche une notification système Android
       // Ne vérifie PAS si l'app est au premier plan
   }
   ```
   **Résultat**: Notification dans la barre système, mais rien dans l'app !

---

## ✅ **SOLUTIONS**

### **1. Corriger get_coursier_data.php**

**Fichier**: `api/get_coursier_data.php` ligne 168-200

**Changements**:
- ✅ Inclure TOUS les statuts actifs dans la requête SQL
- ✅ Ajouter 'recuperee', 'picked_up', 'en_livraison'  
- ✅ Trier par date décroissante pour avoir les plus récentes en premier

### **2. Corriger le polling dans MainActivity.kt**

**Fichier**: `MainActivity.kt` ligne 865-937

**Changements**:
- ✅ Comparer les IDs des commandes, pas uniquement le nombre
- ✅ Détecter les nouvelles commandes par ID manquant dans la liste actuelle
- ✅ Déclencher notification même si le nombre reste identique

### **3. Ajouter notification in-app dans CoursierScreenNew.kt**

**Changements**:
- ✅ Ajouter un Dialog modal "Nouvelle commande" quand l'app est au premier plan
- ✅ Afficher les détails: client, destination, prix
- ✅ Boutons "Voir" (scroll vers commande) ou "OK" (ferme le dialog)
- ✅ Animation d'entrée avec vibration

---

## 🚀 **PLAN D'EXÉCUTION**

1. **Étape 1**: Corriger `get_coursier_data.php` (Backend)
2. **Étape 2**: Corriger `MainActivity.kt` polling (Android - Détection)
3. **Étape 3**: Ajouter notification in-app (Android - UI)
4. **Étape 4**: Tester workflow complet
5. **Étape 5**: Valider avec utilisateur réel

---

## 📊 **TESTS À EFFECTUER**

### Test 1: Timeline persiste après acceptation
```
1. Lancer commande depuis index.php
2. Accepter dans l'app
3. ✅ Vérifier: Timeline reste affichée
4. ✅ Vérifier: deliveryStep = ACCEPTED
5. Marquer "Récupéré"
6. ✅ Vérifier: Timeline reste affichée
7. ✅ Vérifier: deliveryStep = PICKED_UP
8. Terminer livraison
9. ✅ Vérifier: Navigation vers commande suivante
```

### Test 2: Notification app ouverte
```
1. Ouvrir l'app CoursierAppV7
2. Lancer commande depuis index.php
3. ✅ Vérifier: Dialog "Nouvelle commande" s'affiche
4. ✅ Vérifier: Vibration + Son
5. ✅ Vérifier: Détails affichés correctement
6. Cliquer "Voir"
7. ✅ Vérifier: Scroll vers la commande
```

### Test 3: Notification app fermée
```
1. Fermer l'app (swipe recent apps)
2. Lancer commande depuis index.php
3. ✅ Vérifier: Notification système Android
4. Cliquer sur la notification
5. ✅ Vérifier: App s'ouvre sur la commande
6. ✅ Vérifier: Boutons Accepter/Refuser affichés
```

### Test 4: Rotation écran
```
1. Accepter commande
2. Timeline affichée
3. Tourner téléphone (portrait → paysage)
4. ✅ Vérifier: Timeline toujours affichée
5. ✅ Vérifier: deliveryStep préservé
6. Tourner téléphone (paysage → portrait)
7. ✅ Vérifier: État toujours correct
```

---

## 📝 **NOTES TECHNIQUES**

### Statuts de commande

| Statut | Description | Inclus dans API? |
|--------|-------------|------------------|
| `nouvelle` | Commande créée, en attente attribution | ✅ OUI |
| `assignee` | Attribuée à un coursier spécifique | ✅ OUI |
| `acceptee` | Coursier a accepté | ✅ OUI |
| `en_cours` | Coursier en route vers récupération | ✅ OUI |
| `picked_up` | Colis récupéré | ✅ **AJOUTÉ** |
| `recuperee` | Colis récupéré (alias) | ✅ **AJOUTÉ** |
| `en_livraison` | En route vers destination | ✅ **AJOUTÉ** |
| `livree` | Livrée avec succès | ❌ NON (terminée) |
| `annulee` | Annulée | ❌ NON (terminée) |
| `refusee` | Refusée par coursier | ❌ NON (terminée) |

---

## 🔐 **SÉCURITÉ**

- ✅ Validation ID coursier dans toutes les requêtes
- ✅ Vérification ownership (coursier_id = ?)
- ✅ Échappement SQL (PreparedStatements)
- ✅ Logs d'audit pour debugging

---

## 🎯 **RÉSULTAT ATTENDU**

Après corrections :
1. ✅ Timeline reste affichée pendant tout le workflow
2. ✅ Notifications in-app quand l'application est ouverte
3. ✅ Notifications système quand l'application est fermée
4. ✅ États préservés lors de la rotation d'écran
5. ✅ Synchronisation API robuste et complète

---

**Statut**: 🔄 EN COURS D'IMPLÉMENTATION  
**Prochaine étape**: Modifier get_coursier_data.php
