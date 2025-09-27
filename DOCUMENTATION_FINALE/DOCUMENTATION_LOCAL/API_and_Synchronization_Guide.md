# Guide des API et Synchronisations – Coursier_LOCAL

Ce document liste et décrit l'ensemble des points de connexion (routes web et API), les flux de synchronisation entre tables, et les interactions mobiles/web du projet **Cour sie r_LOCAL**.

---

## 1. Authentification

### 1.1 Route Web
- **`coursier.php`**
  - Point d'entrée HTML principal pour web/mobile.
  - Reçoit `POST` ou `GET` (`action=login`) pour authentification.
  - Bascule interne vers `api/agent_auth.php` pour JSON.

### 1.2 API JSON
- **`api/agent_auth.php`**  
  - Méthode : `POST`  
  - En-têtes : `Content-Type: application/json`  
  - Payload JSON :
  ```json
  { "action":"login", "identifier":"<matricule|téléphone>", "password":"<mot_de_passe>" }
  ```
  - Réponse JSON :
    - Succès : `{ "success": true, "message": "Connexion réussie", "agent": { ... } }`
    - Échec : `{ "success": false, "error": "..." }`
  - Gère bcrypt et `plain_password` pour migration progressive.

### 1.3 Journalisation & Sessions
- PHP sessions stockées avec cookie.
- `agents_suzosky` possède colonnes `session_token`, `last_login_at`.

---

## 2. Endpoints Mobiles (Android)

Dans **`ApiService.kt`** :
- `login(identifier, password)` → `/api/agent_auth.php`
- `testConnectivity()` → `/api/connectivity_test.php`
- Autres :
  - `getCoursierOrders(...)` → `/api/get_coursier_orders_simple.php`
  - Un **paramètre dynamique `coursierId`** est maintenant récupéré après authentification et stocké dans SharedPreferences (plus de valeur par défaut codée `6`).
  - `FCMService.onNewToken` ne tente plus d'enregistrer un token sans session valide : si aucun `coursier_id` n'est stocké, il déclenche `agent_auth.php?action=check_session` puis mémorise l'ID résolu avant de contacter `register_device_token.php`.
  - Historique, stats, etc.
  - `assignWithLock(commandeId, coursierId, action)` → `/api/assign_with_lock.php`
    - Actions `accept` ou `release` (TTL configurable 30–300 s)
    - Le backend auto-crée la table `dispatch_locks` si absente et met à jour directement `commandes` (`coursier_id`, `statut`, `heure_acceptation`).
    - Réponse succès : `{"success":true,"locked":true,"statut":"acceptee"}` (accept) ou `{"success":true,"released":true,"statut":"nouvelle"}` (refus).
    - Échec concurrent : HTTP 409 + `Commande déjà assignée`.
  - **Flux navigation & coordonnées (sept. 2025)**
    - `assign_with_lock.php` et `update_order_status.php` s'appuient désormais sur `api/schema_utils.php` pour corriger automatiquement la structure des tables `commandes`/`commandes_classiques` (colonnes `coursier_id`, `heure_acceptation`, `updated_at`, latitude/longitude). Cela supprime les erreurs SQLSTATE lors des acceptations sur schémas incomplets.
    - `get_coursier_data.php` et `get_coursier_orders_simple.php` renvoient les coordonnées normalisées via `coordonneesEnlevement` / `coordonneesLivraison` (format camelCase & snake_case). Les apps existantes continuent de recevoir les clés historiques.
    - Côté Android (`CoursierScreenNew`), l'acceptation déclenche désormais automatiquement la navigation Google Maps vers le point de retrait. Après confirmation "Colis récupéré", la navigation bascule vers l'adresse de livraison.
    - `update_order_status.php` synchronise systématiquement le statut vers la table `commandes` (y compris timestamps pickup/delivery) pour que l'index web reflète immédiatement "Colis récupéré" et les étapes suivantes.

---

## 3. Gestion Financière

### 3.1 Tables principales
- `agents_suzosky` : agents/coursiers source unique.
- `comptes_coursiers` : solde centralisé, FK vers `agents_suzosky`.
- `recharges_coursiers` : demandes de recharges, FK vers `agents_suzosky`.
- `transactions_financieres` : log des opérations (credit/debit).

### 3.2 Synchronisation Automatique
Au chargement de la page **`admin.php?section=finances`**, script :
```php
// Crée comptes manquants pour chaque agent coursier actif
INSERT INTO comptes_coursiers(coursier_id, solde, statut)
SELECT a.id, 0, 'actif' FROM agents_suzosky a
LEFT JOIN comptes_coursiers cc ON cc.coursier_id = a.id
WHERE a.type_poste IN ('coursier','coursier_moto','coursier_velo')
  AND cc.coursier_id IS NULL;
```  
Ainsi, tout agent nouvellement ajouté obtient un compte financier.

### 3.3 Rechargement & Validation
- **Formulaire** `admin.php?section=finances&tab=rechargement`
  - Liste déroulante : agents actifs (`agents_suzosky`).
  - Action `POST action=recharger_coursier` →
    - Vérifie existence dans `agents_suzosky`.
    - Exécute un **INSERT … ON DUPLICATE KEY UPDATE** sur `comptes_coursiers`.
    - Insère une ligne dans `transactions_financieres`.

- **Validation AJAX** (onglet `recharges`) :
  - Actions `validate_recharge` / `reject_recharge` pour demandes en attente.

---

## 4. Synchronisations CRUD

| Opération     | Table source       | Table cible          | Méthode                                                       |
|---------------|--------------------|----------------------|---------------------------------------------------------------|
| Création agent| `agents_suzosky`   | `comptes_coursiers`  | INSERT IGNORE au login / page finances                        |
| Suppression   | `agents_suzosky`   | `comptes_coursiers`  | FK ON DELETE CASCADE                                          |
| Recharge      | `agents_suzosky`   | `comptes_coursiers`  | INSERT … ON DUPLICATE KEY UPDATE                              |
| Transaction   | -                  | `transactions_financieres` | INSERT direct                                               |

---

## 5. Routes complémentaires

- **Web**
  - `admin.php?section=agents` : CRUD agents (table `agents_suzosky`).
  - `admin.php?section=finances&tab=...` : onglets finances.
  - Scripts de migration : `install_finances.php`, `setup_database.php`.

- **API** sous `api/` :
  - `agent_auth.php` – Authentification JSON
  - `get_coursier_orders_simple.php` – Liste commandes agent
  - `poll_coursier_orders.php` – Dernière commande active (poll rapide)
  - `update_order_status.php` – Transition de statut
  - `assign_nearest_coursier.php` / `assign_nearest_coursier_simple.php` – Attribution géographique
  - `ping_coursier.php?agent_id=ID` – Heartbeat (met à jour `last_heartbeat_at`)
  - `test_push_new_order.php?agent_id=ID&order_id=XXX` – Test manuel notification FCM
  - `test_notification.php`, scripts de diagnostic divers
  - `realtime_order_status.php` & `timeline_sync.php` – Timeline front index

### 5.1 Flux FCM New Order (optimisé)
1. Création commande (`submit_order.php`)
2. Attribution (auto ou script) → statut `assignee`
 - Note (local) : en environnement de développement, `submit_order.php` assigne immédiatement la commande au premier agent actif (création/bridge `coursiers` incluse) et envoie la notification via `fcm_enhanced.php`. Utilisez `test_push_new_order.php` ou relancez `assign_nearest_coursier_simple.php` si vous devez cibler un appareil particulier ou en l’absence de token enregistré. En production, la même création déclenche `assign_nearest_coursier_simple.php` avec les coordonnées fournies pour sélectionner le coursier actif le plus proche.
 3. Envoi FCM via `fcm_enhanced.php`:
  - HTTP v1 si service account détecté (auto-scan fichier `coursier-suzosky-firebase-adminsdk-*.json`)
  - Legacy fallback sinon
  - À partir du 26/09/2025, les réponses HTTP v1 incluent le détail `errorCode/errorStatus/errorMessage` consigné dans `notifications_log_fcm`. Toute réponse `UNREGISTERED` supprime immédiatement le token fautif de `device_tokens` et trace `FCM token supprimé…` dans `diagnostic_logs/application.log`.
  - Pour vérifier que le compte de service est fonctionnel, on peut exécuter ponctuellement `php tmp_check_fcm_token.php` (script temporaire décrit dans le README) : il renverra le préfixe d'un access token si Firebase répond correctement.
  - Si une commande déclenche `code=404` / `errorCode=UNREGISTERED`, demander au coursier concerné de relancer l'application mobile : un nouveau token apparaîtra dans `device_tokens`, et l'envoi sera de nouveau marqué `success=1` au prochain push.
 4. Application reçoit message `data_only` (`type=new_order`), utilise l’**ID de l’agent** récupéré dynamiquement (SharedPreferences) pour les appels suivants (pas de compte test durcodé).
  - Si l’ID n’est pas encore stocké (premier lancement après réinstallation), `FCMService` déclenche `check_session` puis met en cache `coursier_id` avant d’enregistrer le token côté serveur.
 5. Application déclenche immédiatement:
  - Rafraîchissement des commandes via `get_coursier_orders_simple.php?coursier_id=...`
  - Démarrage du service sonnerie `OrderRingService` (boucle 2s) tant qu’au moins une commande est `nouvelle/attente`.
  - Optionnel: heartbeat `ping_coursier.php?agent_id=...`.
 6. Lors de l’acceptation/refus dans l’app, appel `assignWithLock`:
  - Acceptation → statut serveur `acceptee`, `heure_acceptation` renseignée, timeline débloquée.
  - Refus → lock relâché + remise en file (`statut` retour `nouvelle`, `coursier_id` remis à NULL).

Schéma minimal côté Android (pseudo):
```
onMessageReceived(data) {
  if (data.type == 'new_order') {
    api.fetchOrders()
    notifier.playShortAlert()
  }
}
```

---

## 6. Architecture & Flux

1. **Mobile** → **API JSON** → **PHP backend** → **MySQL**
2. **Web admin** → **Pages PHP** → **MySQL**
3. **Synchronisations** rollers via FK et requêtes SQL INSERT … ON DUPLICATE KEY
4. **Audit & journal** : `getJournal()->logMaxDetail()` en PHP

---

*Fin du guide*.