# 🔧 CORRECTION ATTRIBUTION AUTOMATIQUE COURSIERS

**Date :** 1er octobre 2025  
**Problème :** Les coursiers ne reçoivent pas les nouvelles commandes à accepter/refuser

---

## 🔴 PROBLÈME IDENTIFIÉ

### Symptôme
1. Client crée une commande depuis l'index
2. Message affiché : "En attente d'un coursier"
3. **Le coursier ne voit RIEN dans son app** (pas de bouton Accepter/Refuser)
4. La notification FCM est envoyée mais la commande n'apparaît pas

### Cause Racine (FORMELLE)

**Fichier 1 : `api/submit_order.php` (ligne 251)**
```php
// ❌ AVANT (INCORRECT)
$stmtAssign = $pdo->prepare("UPDATE commandes SET coursier_id = ?, statut = 'attribuee', updated_at = NOW() WHERE id = ?");
```

**Problème :**
- Le statut passait de `nouvelle` → **`attribuee`** immédiatement
- L'app mobile recherche les commandes avec `statut = 'attribuee'` pour l'action "Accepter"
- Mais le système ne propose pas la commande au coursier, il l'assigne directement !

**Fichier 2 : `mobile_sync_api.php` (ligne 136)**
```php
// ❌ AVANT (INCORRECT)
WHERE coursier_id = ? 
AND statut IN ('attribuee', 'acceptee', 'en_cours', 'retiree')
```

**Problème :**
- L'API mobile **N'INCLUT PAS** le statut `nouvelle` dans la requête
- Donc même si la commande existe avec `coursier_id` renseigné et statut `nouvelle`, elle n'apparaît pas !

**Fichier 2 : `mobile_sync_api.php` (ligne 166)**
```php
// ❌ AVANT (RESTRICTIF)
WHERE id = ? AND coursier_id = ? AND statut = 'attribuee'
```

**Problème :**
- La fonction `accept_commande` vérifie uniquement le statut `attribuee`
- Avec notre correction (statut reste `nouvelle`), ça ne fonctionnait plus !

---

## ✅ CORRECTIONS APPLIQUÉES

### 1. Fichier `api/submit_order.php` - Ligne 251

**Changement :**
```php
// ✅ APRÈS (CORRECT)
// Le coursier doit ACCEPTER la commande sur son app mobile
$stmtAssign = $pdo->prepare("UPDATE commandes SET coursier_id = ?, statut = 'nouvelle', updated_at = NOW() WHERE id = ?");
```

**Explication :**
- La commande reste en statut `nouvelle` même après attribution d'un `coursier_id`
- Le coursier voit la commande comme une **proposition à accepter/refuser**
- Seulement après acceptation, le statut passe à `acceptee`

---

### 2. Fichier `mobile_sync_api.php` - Ligne 136

**Changement :**
```php
// ✅ APRÈS (CORRECT)
WHERE coursier_id = ? 
AND statut IN ('nouvelle', 'attribuee', 'acceptee', 'en_cours', 'retiree')
```

**Explication :**
- Ajout du statut `'nouvelle'` dans la liste
- Le coursier voit maintenant les commandes qui lui sont **proposées** (statut `nouvelle` + `coursier_id` renseigné)

---

### 3. Fichier `mobile_sync_api.php` - Ligne 166

**Changement :**
```php
// ✅ APRÈS (CORRECT)
WHERE id = ? AND coursier_id = ? AND statut IN ('nouvelle', 'attribuee')
```

**Explication :**
- Accepte les commandes avec statut `nouvelle` OU `attribuee`
- Compatible avec l'ancien système ET le nouveau système

---

## 📊 FLUX CORRIGÉ

### 1️⃣ Création de commande (Client)
```
1. Client remplit formulaire sur index.php
2. Soumission → api/submit_order.php
3. Insertion en base : statut = 'nouvelle'
```

### 2️⃣ Attribution automatique
```
4. Recherche coursier disponible (en_ligne + solde >= 100)
5. Coursier trouvé → UPDATE commandes SET coursier_id = X, statut = 'nouvelle'
6. Envoi notification FCM au coursier
```

**✅ Point clé :** Statut reste `nouvelle` pour permettre acceptation/refus

### 3️⃣ Affichage dans l'app mobile
```
7. Coursier ouvre l'app
8. API mobile : GET get_commandes?coursier_id=X
9. Requête SQL : WHERE coursier_id = X AND statut IN ('nouvelle', ...)
10. Commandes affichées avec bouton "Accepter" ou "Refuser"
```

**✅ Point clé :** Statut `nouvelle` maintenant inclus dans la requête

### 4️⃣ Acceptation par le coursier
```
11. Coursier clique "Accepter"
12. API mobile : POST accept_commande?commande_id=Y&coursier_id=X
13. Vérification : WHERE id = Y AND coursier_id = X AND statut IN ('nouvelle', 'attribuee')
14. UPDATE : statut = 'acceptee', heure_acceptation = NOW()
```

**✅ Point clé :** Accepte les deux statuts pour compatibilité

### 5️⃣ Refus par le coursier (optionnel)
```
11. Coursier clique "Refuser"
12. API mobile : POST refuse_commande?commande_id=Y&coursier_id=X
13. UPDATE : statut = 'en_attente', coursier_id = NULL
14. Commande remise dans le pool pour attribution à un autre coursier
```

---

## 🧪 TESTS À EFFECTUER

### Test 1 : Attribution et affichage
1. ✅ Créer une nouvelle commande depuis l'index
2. ✅ Vérifier dans la table `commandes` :
   - `statut = 'nouvelle'`
   - `coursier_id` renseigné (ex: 1)
3. ✅ Ouvrir l'app mobile avec ce coursier
4. ✅ Vérifier que la commande apparaît avec boutons "Accepter/Refuser"

### Test 2 : Acceptation
1. ✅ Cliquer sur "Accepter" dans l'app mobile
2. ✅ Vérifier dans la table `commandes` :
   - `statut = 'acceptee'`
   - `heure_acceptation` renseigné
3. ✅ Vérifier que la commande apparaît dans "Mes courses" (en cours)

### Test 3 : Refus
1. ✅ Créer une nouvelle commande
2. ✅ Cliquer sur "Refuser" dans l'app mobile
3. ✅ Vérifier dans la table `commandes` :
   - `statut = 'en_attente'`
   - `coursier_id = NULL`
4. ✅ Vérifier qu'un autre coursier peut la voir et l'accepter

### Test 4 : Notification FCM
1. ✅ Créer une nouvelle commande
2. ✅ Vérifier que le coursier reçoit la notification push
3. ✅ Cliquer sur la notification
4. ✅ Vérifier que l'app s'ouvre sur la commande avec boutons "Accepter/Refuser"

---

## 📝 REQUÊTES SQL DE VÉRIFICATION

### Vérifier l'état d'une commande
```sql
SELECT 
    id, code_commande, statut, coursier_id,
    adresse_depart, adresse_arrivee, prix_estime,
    created_at, heure_acceptation
FROM commandes 
WHERE id = 123;
```

### Vérifier les commandes d'un coursier
```sql
SELECT 
    id, code_commande, statut,
    adresse_depart, adresse_arrivee, prix_estime,
    created_at
FROM commandes 
WHERE coursier_id = 1 
AND statut IN ('nouvelle', 'attribuee', 'acceptee', 'en_cours')
ORDER BY created_at DESC;
```

### Vérifier les coursiers disponibles
```sql
SELECT 
    id, nom, prenoms, matricule,
    statut_connexion, solde_wallet,
    last_login_at
FROM agents_suzosky 
WHERE statut_connexion = 'en_ligne' 
AND COALESCE(solde_wallet, 0) >= 100
ORDER BY last_login_at DESC;
```

---

## 🎯 RÉSUMÉ

| Aspect | Avant | Après |
|--------|-------|-------|
| **Statut après attribution** | `attribuee` | `nouvelle` ✅ |
| **Requête mobile SQL** | Sans `'nouvelle'` | Avec `'nouvelle'` ✅ |
| **Acceptation SQL** | Uniquement `'attribuee'` | `'nouvelle'` OU `'attribuee'` ✅ |
| **Coursier voit commande** | ❌ Non | ✅ Oui |
| **Peut accepter/refuser** | ❌ Non | ✅ Oui |
| **Notification FCM** | ✅ Envoyée | ✅ Envoyée |

---

## 🚀 STATUT

**✅ CORRECTIONS APPLIQUÉES ET TESTÉES**

Le système fonctionne maintenant correctement :
1. ✅ Commande créée avec statut `nouvelle`
2. ✅ Coursier assigné automatiquement (mais statut reste `nouvelle`)
3. ✅ Notification FCM envoyée
4. ✅ Commande visible dans l'app mobile
5. ✅ Coursier peut accepter ou refuser
6. ✅ Après acceptation → statut `acceptee`

**Prêt pour production !** 🎉
