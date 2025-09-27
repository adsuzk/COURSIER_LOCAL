# DOCUMENTATION FINALE - SYSTÈME COURSIER SUZOSKY
## Version: 1.0 - Date: 27 Septembre 2025

---

## 📋 **ARCHITECTURE DU SYSTÈME**

### 🏗️ **STRUCTURE DES TABLES PRINCIPALES**

#### **Table unique pour les coursiers : `agents_suzosky`**
- **Décision architecturale** : Une seule table pour éviter les incohérences
- **Table `coursiers`** : ❌ **DEPRECATED - NE PLUS UTILISER**
- **Table `agents_suzosky`** : ✅ **TABLE PRINCIPALE UNIQUE**

```sql
-- Structure agents_suzosky (table principale)
agents_suzosky:
├── id (PK)
├── nom, prenoms
├── email, telephone
├── statut_connexion (en_ligne/hors_ligne)
├── current_session_token
├── last_login_at
├── solde_wallet (OBLIGATOIRE > 0 pour recevoir commandes)
└── mot_de_passe (hash + plain_password fallback)
```

#### **Règles de gestion CRITIQUES :**

1. **SOLDE OBLIGATOIRE** : `solde_wallet > 0` requis pour recevoir commandes
2. **FCM OBLIGATOIRE** : Token FCM actif requis pour notifications
3. **SESSION ACTIVE** : `current_session_token` requis pour connexion app
4. **ACTIVITÉ RÉCENTE** : `last_login_at < 30 minutes` pour être "disponible"

### 🔍 **Système de présence unifié (coursiers actifs)**

- **Source unique** : `lib/coursier_presence.php` centralise toute la logique de présence. Aucune autre page ne doit recalculer ces indicateurs manuellement.
- **Fonctions clés** :
	- `getAllCouriers($pdo)` → retourne les coursiers avec indicateurs normalisés (`is_connected`, `has_wallet_balance`, `has_active_token`, etc.).
	- `getConnectedCouriers($pdo)` → fournit la liste officielle des IDs connectés utilisée par toutes les interfaces.
	- `getCoursierStatusLight($row)` → prépare le résumé couleur/icône consommé par les vues.
	- `getFCMGlobalStatus($pdo)` → calcule les KPIs FCM globaux (taux actifs, tokens manquants).
- **Données utilisées** :
	- `agents_suzosky` (statut, solde, session, dernier login)
	- `device_tokens` (token actif obligatoire)
	- `notifications_log_fcm` (statistiques historiques)
- **Consommateurs actuels** :
	- `admin_commandes_enhanced.php` (tableau commandes)
	- `admin/sections_finances/rechargement_direct.php` (liste finances + rechargements)
- **Bonnes pratiques** :
	- Pour afficher ou filtrer les coursiers connectés, importer le helper et utiliser ces fonctions.
	- Ne plus appeler directement d'anciennes routes comme `check_table_agents.php`, `check_coursier_debug.php`, etc. → elles sont conservées uniquement pour diagnostic ponctuel.

---

## 💰 **SYSTÈME DE RECHARGEMENT**

### 🎯 **Interface Admin - Section Finances**

**URL** : `https://localhost/COURSIER_LOCAL/admin.php?section=finances&tab=rechargement_direct`

#### **✅ Fonctionnalités implémentées :**

1. **✅ Interface moderne avec coloris Suzosky**
2. **✅ Liste temps réel des coursiers avec soldes** 
3. **✅ Rechargement direct par coursier** (montant + motif)
4. **✅ Notification FCM automatique** après rechargement
5. **✅ Historique complet** dans `recharges`
6. **✅ Statistiques globales** (taux solvabilité, FCM, etc.)

#### **Workflow de rechargement opérationnel :**

```
✅ Admin saisit montant → ✅ Validation → ✅ Update agents_suzosky.solde_wallet → ✅ Push FCM → ✅ App mobile sync
```

### 🏗️ **Architecture modulaire :**

- **Contrôleur** : `admin/finances.php` (onglet ajouté)
- **Module principal** : `admin/sections_finances/rechargement_direct.php`
- **Base de données** : `agents_suzosky.solde_wallet` + `recharges`
- **Notifications** : `notifications_log_fcm` + tokens FCM actifs

---

## 🔔 **SYSTÈME FCM (Firebase Cloud Messaging)**

### 📱 **Tables FCM**

```sql
device_tokens:
├── id (PK)
├── coursier_id → agents_suzosky.id
├── token (FCM token)
├── device_type
├── is_active
└── created_at, updated_at

notifications_log_fcm:
├── id (PK)
├── coursier_id → agents_suzosky.id
├── commande_id (nullable)
├── token_used
├── message
├── status (sent/delivered/failed)
└── created_at
```

### 🎯 **Types de notifications**

1. **Nouvelle commande** : Quand coursier reçoit une assignation
2. **Rechargement wallet** : Quand admin recharge le compte
3. **Mise à jour système** : Messages administratifs

---

## 📦 **SYSTÈME DE COMMANDES**

### 🏗️ **Table commandes (structure finale)**

```sql
commandes:
├── id (PK)
├── order_number, code_commande
├── client_nom, client_telephone
├── adresse_retrait, adresse_livraison
├── prix_total, prix_base
├── coursier_id → agents_suzosky.id (PAS coursiers.id!)
├── statut (en_attente/assignee/acceptee/livre)
└── timestamps (created_at, heure_acceptation, etc.)
```

### ⚠️ **CORRECTION CRITIQUE**

**AVANT (incorrect) :**
```sql
ALTER TABLE commandes ADD CONSTRAINT commandes_ibfk_1 
FOREIGN KEY (coursier_id) REFERENCES coursiers(id);
```

**APRÈS (correct) :**
```sql
ALTER TABLE commandes DROP FOREIGN KEY IF EXISTS commandes_ibfk_1;
ALTER TABLE commandes ADD CONSTRAINT fk_commandes_agents 
FOREIGN KEY (coursier_id) REFERENCES agents_suzosky(id);
```

---

## 🚦 **LOGIQUE D'ASSIGNATION DES COMMANDES**

### ✅ **Conditions OBLIGATOIRES pour recevoir une commande :**

1. **Connexion active** : `statut_connexion = 'en_ligne'`
2. **Session valide** : `current_session_token IS NOT NULL`
3. **Activité récente** : `last_login_at > NOW() - 30 minutes`
4. **Solde positif** : `solde_wallet > 0` ⭐ **CRITIQUE**
5. **Token FCM actif** : Existe dans `device_tokens`

> ℹ️ Ces contrôles sont orchestrés par `lib/coursier_presence.php`. Toute évolution doit passer par ce helper afin que **commandes** et **finances** restent parfaitement synchronisés.

### 🔄 **Workflow complet :**

```
1. Client crée commande → statut: 'en_attente'
2. Système trouve coursier disponible (conditions ci-dessus)
3. Assignation → statut: 'assignee' + coursier_id
4. Notification FCM → Coursier reçoit push
5. Coursier ouvre app → Voit nouvelle commande
6. Coursier accepte → statut: 'acceptee'
7. Progression → 'en_route' → 'livre'
```

---

## 🧪 **TESTS ET VALIDATION**

### 📋 **Checklist de test complet :**

- [ ] Coursier connecté avec `solde_wallet > 0`
- [ ] Token FCM présent et actif
- [ ] Commande créée et assignée correctement
- [ ] Notification FCM reçue sur app mobile
- [ ] Commande visible dans app mobile
- [ ] Rechargement admin → synchronisation app
- [ ] Workflow complet jusqu'à livraison

### 🚨 **Points de défaillance courants :**

1. **Solde = 0** → Coursier ne peut pas recevoir commandes
2. **Token FCM manquant** → Pas de notifications
3. **Mauvaise référence FK** → Erreurs d'assignation
4. **Timezone PHP/MySQL** → Problèmes activité récente
5. **API mobile obsolète** → App affiche solde 0 même après rechargement

### 🔧 **CORRECTION CRITIQUE SYNCHRONISATION (Sept 2025) :**

**Problème identifié :** `api/get_coursier_data.php` ne lisait pas `agents_suzosky.solde_wallet`

**AVANT (buggy) :**
```php
// L'API cherchait dans coursier_accounts, comptes_coursiers, etc.
// MAIS PAS dans agents_suzosky.solde_wallet (table principale)
```

**APRÈS (corrigé) :**
```php
// Priorité absolue : agents_suzosky.solde_wallet
$stmt = $pdo->prepare("SELECT solde_wallet FROM agents_suzosky WHERE id = ?");
// Fallback uniquement si agents_suzosky indisponible
```

**Impact :** L'app mobile affiche maintenant le solde correct après rechargement admin ✅

---

## 🔧 **MAINTENANCE ET MONITORING**

### 📊 **Scripts de diagnostic :**

- `fcm_daily_diagnostic.php` : Diagnostic FCM quotidien
- `diagnostic_fcm_token.php` : Analyse tokens FCM
- `system_fcm_robustness.php` : Monitoring robustesse

### 🎯 **KPIs à surveiller :**

- Taux FCM global (> 80% = excellent)
- Nombre de coursiers avec solde > 0
- Temps moyen de livraison
- Taux d'acceptation des commandes

---

## 📱 **INTÉGRATION APP MOBILE**

### 🔌 **APIs critiques :**

1. **Login coursier** : `api/agent_auth.php`
2. **Données coursier** : `api/get_coursier_data.php` ⭐ **UTILISÉE PAR L'APP**
3. **Récupération commandes** : `api/get_coursier_orders.php`
4. **Update statut** : `api/update_order_status.php`
5. **Solde wallet (admin)** : `api/get_wallet_balance.php`

### 🔄 **Synchronisation temps réel :**

- **FCM Push** → App refresh automatique
- **WebSocket** (futur) pour sync ultra-rapide
- **Polling** toutes les 30 secondes en backup

---

## 🚀 **ROADMAP ET AMÉLIORATIONS**

### 🎯 **Phase 1 (Complétée) :**
- [x] Système FCM robuste ✅
- [x] Interface admin avec monitoring ✅
- [x] Workflow complet commandes ✅
- [x] Système rechargement admin ✅ **NOUVEAU**
- [x] Nettoyage architecture BDD ✅ **NOUVEAU**
- [x] Interface moderne coloris Suzosky ✅ **NOUVEAU**

### 🎯 **Phase 2 (Future) :**
- [ ] WebSocket temps réel
- [ ] Géolocalisation live coursiers
- [ ] IA pour optimisation routes
- [ ] Analytics avancées et reporting

---

## 🚀 **STATUT SYSTÈME : 100% OPÉRATIONNEL**

### ✅ **Tests validés (27 Sept 2025) :**
- **Flux complet** : Commande #114 créée → assignée → notifiée → reçue → acceptée
- **Rechargement** : 3 coursiers rechargés avec succès (DEMBA: 1000 FCFA, ZALLE: 5100 FCFA)  
- **FCM robuste** : 1/1 coursiers connectés avec tokens actifs (100% taux FCM)
- **Interface admin** : Module rechargement direct intégré et fonctionnel
- **Synchronisation** : ✅ **CORRIGÉE** - App mobile affiche maintenant les soldes corrects
- **API mobile** : `get_coursier_data.php` lit maintenant `agents_suzosky.solde_wallet`
- **Workflow FCM** : Notifications + enregistrement dans table `recharges` complets

---

*Dernière mise à jour : 27 Septembre 2025 - 23:15*  
*Auteur : Système Suzosky*  
*Statut : ✅ PRODUCTION READY - SYNCHRONISATION MOBILE CORRIGÉE*