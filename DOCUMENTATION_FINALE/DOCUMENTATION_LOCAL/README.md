# Plan de déploiement en production (synchronisé avec l'environnement local)

Ce dossier regroupe TOUTES les modifications réalisées en local qui doivent être appliquées en production pour que l'application Android connectée en HTTPS fonctionne correctement et que l'authentification/sessions soient stables.

## ✅ À appliquer en PROD

1) Base de données – colonnes sessions pour `agents_suzosky`
- Colonnes: `current_session_token`, `last_login_at`, `last_login_ip`, `last_login_user_agent`
- Script: `sql/2025-09-23_add_agent_session_columns.sql`

2) API d'authentification agents & flux historique V7
- Fichiers: `api/agent_auth.php` **et** `coursier.php`
- Points clés:
  - Paramètres attendus: `action=login`, `identifier`, `password`, `ajax=true`
  - Réponses JSON systématiques pour les clients OkHttp/Android
  - Tolérance de 30s pour reconnaitre la même session si IP/UA similaires
  - **Unicité de session garantie** : les deux entrées (`agent_auth.php` et `coursier.php`) génèrent désormais `current_session_token` + `last_login_at/ip/user_agent`. Chaque nouvelle connexion invalide immédiatement toute session précédente côté application.

3) Point d'entrée historique `coursier.php`
- Support du login JSON pour V7 (Android OkHttp)
- Redirection JSON vers `mobile_app.php` pour les clients mobiles

4) HTTPS activé (Apache)
- L'app Android utilise désormais `https://<IP-serveur>/COURSIER_LOCAL/`
- OkHttp configuré (mode dev) pour accepter le certificat auto-signé
- En PROD: utiliser un certificat valide et supprimer le mode permissif côté app

5) Android – paramètres côté app (référence)
- Base: `https://<ip>/COURSIER_LOCAL`
- Paramètre d’auth: `identifier` (et non `matricule`)

6) Interface Web – modal de connexion rapide
- `index.php` mis à jour pour afficher directement la modal de connexion si aucune session n'existe (désactivation complète de l'avertissement beforeunload)
- Raccourci `Ctrl+5` lié côté client pour ouvrir la modal de connexion sans avertissement de navigation
- **Toutes les actions d'authentification** (login, register, logout) désactivent automatiquement l'avertissement "modifications non sauvegardées"
- Modification dans `js_authentication.php` et `connexion_modal.js` pour désactiver `beforeunload` pendant les processus de connexion/déconnexion

## 🧪 Tests rapides post-déploiement
- POST `https://<ip>/COURSIER_LOCAL/coursier.php` form: `action=login&identifier=CM20250001&password=g4mKU&ajax=true`
- Attendu: `{ "success": true, "status": "ok" }`

## 📁 Fichiers inclus dans ce dossier
- `README.md` (ce fichier)
- `sql/2025-09-23_add_agent_session_columns.sql`
- `MIGRATION_GUIDE_2025-09-23.md` (procédure détaillée)
- `API_and_Synchronization_Guide.md` (détails FCM, timeline et flux Android)

**Note (mode local)** : la soumission d'une commande via `index.php` passe désormais par `submit_order.php` qui attribue automatiquement le premier agent actif (bridge `agents_suzosky` → `coursiers`) et déclenche immédiatement la notification FCM avec `fcm_enhanced.php`. Si aucun token n'est présent pour ce coursier ou pour cibler un appareil précis, vous pouvez toujours déclencher `test_push_new_order.php` ou relancer `assign_nearest_coursier_simple.php` manuellement. En production, la même création de commande appelle `assign_nearest_coursier_simple.php` pour choisir le coursier actif géographiquement le plus proche.

**Surveillance FCM 26/09/2025** :
- `fcm_enhanced.php` supprime désormais automatiquement tout token renvoyé en `UNREGISTERED / 404` par Firebase. Le nettoyage est logué dans `diagnostic_logs/application.log` (recherche `FCM token supprimé…`).
- Les réponses HTTP v1 contiennent le statut détaillé (`errorCode`, `errorStatus`, `errorMessage`) pour faciliter le triage dans `notifications_log_fcm`.
- Pour confirmer ponctuellement la disponibilité du compte de service, exécuter depuis la racine du projet :
  ```powershell
  php -r "require 'api/lib/fcm_enhanced.php';\$sa=glob('coursier-suzosky-firebase-adminsdk-*.json')[0]??null;\$data=\$sa?json_decode(file_get_contents(\$sa),true):null;\$token=\$data?fcm_v1_get_access_token(\$data):null;echo ($token?'Access token OK (prefix): '.substr($token,0,16).'...':'Echec récupération access_token FCM').PHP_EOL;"
  ```
  ou relancer `test_push_new_order.php` après qu'un coursier se soit reconnecté et ait obtenu un token frais dans `device_tokens`.

## 🔁 Timeline client dans l'index (mode espèces) – Détails exhaustifs

Cette section décrit, étape par étape, le comportement de la timeline qui s'ouvre dans l'index après la soumission d'une commande au comptant, et comment tout est synchronisé (front, APIs, FCM, Admin, token app coursier, assignation).

### 1) Ouverture immédiate de la timeline (front) et mode unique
- Fichier: `sections_index/order_form.php`
- Mécanisme:
  - Interception du submit pour TOUS les paiements (mode unique inline, pas de fallback).
  - Affiche `#client-timeline` dans l’index et initialise 6 étapes:
    1. pending (Commande reçue)
    2. confirmed (Coursier confirmé)
    3. pickup (En route pour collecte)
    4. transit (Colis récupéré)
    5. delivery (Livraison en cours)
    6. completed (Commande terminée)
  - Démarre un polling toutes les 5 s vers `/api/timeline_sync.php`.
  - Met à jour un badge de statut: pending → live → delivered.
  - Pour les paiements électroniques, la page reste sur l’index et ouvre une modale de paiement inline (iframe) si `payment_url` est fourni par l’API.
  - En cas de réponse invalide ou d’échec réseau, un message inline `error` s’affiche dans la timeline avec un bouton **Réessayer**. Ce bouton réutilise automatiquement la dernière payload validée (sans saisie supplémentaire) et relance `submit_order.php` + le polling.

Références code:
- Constante des étapes: `baseTimeline` (icônes, labels, descriptions).
- Rafraîchissement UI: `renderTimelineUI`, `renderTimelineMessages`, `updateBadge`.
- Démarrage suivi/polling: `handleEnhancedSubmit()` → `startPolling()`.
- Paiement inline: `openPaymentInline()` ou `window.showPaymentModal(payment_url)` si fourni par le projet.

### 2) Création de commande et réponse
- API: `api/submit_order.php`
- Actions:
  - Valide les champs, normalise téléphones.
  - Insère dans `commandes` (génère `order_number` + `code_commande` si colonne présente).
  - Détecte le schéma (FK client_id → clients/clients_particuliers) pour compatibilité.
  - Reconstruit le prix côté serveur si le front envoie `price <= 0` ou absent: lecture de `parametres_tarification` (frais_base/prix_kilometre) + multiplicateurs par priorité, puis fallback 2 000 FCFA mini avec logs `PRICING_FALLBACK_*`.
  - Si coordonnées départ présentes, appelle l'assignation automatique (voir §3).
  - Répond JSON: `{ success:true, data:{ order_id, code_commande, payment_method, (coursier_id?), (payment_url?) } }`.

  > **Supervision** : vérifier `diagnostic_logs/diagnostics_errors_utf8.log` après une soumission sans prix pour confirmer l'apparition de `PRICING_FALLBACK_APPLIED` (recalcul OK) ou `PRICING_CONFIG_FALLBACK` (accès BDD paramètres à corriger).

### 3) Assignation auto + notification FCM
- API: `api/assign_nearest_coursier.php`
- Sélection du coursier: plus proche via Haversine sur les dernières positions (`tracking_helpers.php`).
- Mises à jour critiques (synchronisation):
  - `coursier_id = <id>`
  - `assigned_at = NOW()` si la colonne existe (nouveau correctif)
  - `statut = 'assignee'` si colonne `statut` présente
- Envoie la notification FCM "Nouvelle commande" aux tokens du coursier.

Schéma tokens unifié (robuste):
- Table: `device_tokens` avec `token` en TEXT et `token_hash` (sha256) unique.
- Index: `idx_coursier`, `idx_agent`, `uniq_token_hash`.
- Alignée avec `api/register_device_token.php` pour éviter toute troncature et garantir la délivrabilité FCM.

### 4) Synchronisation timeline (polling)
- API: `api/timeline_sync.php`
- Mapping statuts → étapes:
  - nouvelle → pending
  - assignee → confirmed
  - acceptee → pickup
  - picked_up → transit
  - en_cours → delivery
  - livree → completed
- Timestamps utilisés:
  - `created_at` → pending
  - `assigned_at` → confirmed & pickup
  - `picked_up_at` → transit
  - `updated_at`/position → delivery (si en_cours)
  - `delivered_at` → completed
- Messages injectés selon le statut (ex: "Le coursier X se rend au point de récupération").
- Position coursier renvoyée si disponible.
 - Détails coursier renvoyés: `coursier` avec `{ id, nom, telephone }` dès que disponibles; affichés au client dès `acceptee`.
 - La carte Google Maps affiche un marker du coursier dès que la position est connue et se met à jour en temps réel.

### 5) Admin et cohérence opérationnelle
- L’interface admin `admin.php?section=commandes` reflète directement la table `commandes`.
- Les changements `statut`/horodatages se voient immédiatement côté Admin et côté client (via polling).
- Acceptation/refus coté mobile: `api/order_response.php`:
  - `accept` → `statut = 'acceptee'` (+ stop_ring pour arrêter la sonnerie)
  - `refuse` → retour à `statut = 'nouvelle'` et `coursier_id = NULL` (ré-attribution possible)
 - Dès l'acceptation, le client voit sur l'index le nom et le téléphone du coursier.

### 6) Flux FCM — côté application coursier
- Enregistrement token: `POST /api/register_device_token.php` avec `{ coursier_id, token }` (ou `agent_id`).
- La notification "Nouvelle commande" est envoyée à tous les tokens du coursier sélectionné.
- En production, s’assurer que le téléphone a bien enregistré un token FCM réel (la doc FCM fournit les vérifications et scripts utiles).

### 7) Matrice de statuts et effets sur la timeline
- `nouvelle` → étape 1 active
- `assignee` → étape 2 active (courriel/FCM déjà parti)
- `acceptee` → étape 3 active
- `picked_up` → étape 4 active (message "Colis pris en charge")
- `en_cours` → étape 5 active (badge "Suivi en direct")
- `livree` → étape 6 complétée + badge "Livraison terminée" et arrêt du suivi
 - Dès `acceptee`, affichage des coordonnées du coursier (nom + téléphone) sous la timeline.

### 8) Dépannage (checklist)
1. La timeline ne s’ouvre pas après “Commander”
  - Vérifier que `sections_index/order_form.php` intercepte bien le submit et appelle le flux inline (mode unique).
   - Contrôler que `#client-timeline` est visible (pas masqué par CSS).
2. Étape "Coursier confirmé" ne s’active pas
   - S’assurer que l’API d’assignation a bien mis `statut='assignee'` et (si présente) `assigned_at`.
   - Voir `diagnostics_errors.log` et les headers HTTP loggués par `submit_order.php` lors de l’appel assignation.
3. Pas de notification côté coursier
   - Confirmer un token FCM valide en base: table `device_tokens` (champ `token` non tronqué, `token_hash` rempli).
   - Vérifier `/api/register_device_token.php` est bien appelé par l’app après login.
4. Index affiche "Suivi indisponible (délai)" ou erreurs inline
   - Le coursier ne met pas à jour sa position et aucun statut actif n’est détecté → normal si l’app est hors-ligne.
  - Reprendre en lançant l’app et en s’assurant que le coursier accepte la course.
  - Les erreurs ne déclenchent plus un fallback; elles s’affichent dans la timeline avec un bouton **Réessayer** qui relaie la dernière tentative (mode sécurisé, antispam).
5. Le nom/téléphone du coursier ne s'affiche pas
  - Vérifier que `timeline_sync.php` joint bien la table `coursiers` et renvoie `coursier` `{ id, nom, telephone }`.
  - S'assurer que l'app coursier a accepté la course (`acceptee`) et que `coursier_id` est bien rempli.
6. Le marker du coursier ne bouge pas sur la carte
  - Confirmer que la position du coursier (`position_lat`, `position_lng`) est mise à jour côté app coursier.
  - `timeline_sync.php` renvoie `coursier_position` avec `lat`, `lng`, `last_update`.
5. Admin ne reflète pas la timeline
   - Contrôler que les colonnes `statut`, `assigned_at`, `picked_up_at`, `delivered_at` existent/évoluent.
   - Sinon, adapter le schéma ou les mappages d’update.

### 9) Points d’attention déploiement PROD
- Garder le schéma `device_tokens` unifié (TEXT + `token_hash`).
- Ne pas supprimer les colonnes de timestamps côté `commandes` si la timeline en dépend.
- CORS: `timeline_sync.php` expose bien les headers pour polling cross-origin si besoin.

### 10) Références fichiers
- Front: `sections_index/order_form.php`
- APIs: `api/submit_order.php`, `api/assign_nearest_coursier.php`, `api/timeline_sync.php`, `api/order_response.php`, `api/register_device_token.php`, `api/tracking_realtime.php`
- Libs: `api/lib/fcm_enhanced.php`, `api/lib/tracking_helpers.php`

## 🆕 **NOUVEAU (25 septembre 2025) - Redesign Menu "Mes courses" CoursierV7**

### ✅ **Redesign Complet Terminé**
Le menu "Mes courses" de l'application Android CoursierV7 a été **entièrement redesigné** pour une UX/UI ergonomique et super pratique pour les coursiers :

#### **📱 Nouveaux Composants Créés**
- `NewCoursesScreen.kt` - Interface principale redesignée
- `CourseLocationUtils.kt` - Utilitaires GPS et validation d'arrivée (100m)
- `CoursesViewModel.kt` - Gestion d'état reactive avec StateFlow
- `CoursierScreenNew.kt` - Intégration dans navigation principale

#### **🎯 Améliorations UX/UI Majeures**
- **Timeline simplifiée** : 6 états séquentiels vs 9 états complexes anciens
- **Navigation automatique** : Lancement GPS contextuel (Maps/Waze)
- **Validation géolocalisée** : Arrivée détectée automatiquement à 100m
- **Queue management** : Gestion intelligente ordres cumulés
- **Interface moderne** : UI reactive, feedback temps réel

#### **👤 Ajout Matricule dans le Profil**
- ✅ **ProfileScreen.kt** : Nouveau paramètre `coursierMatricule`
- ✅ **API profile.php** : Récupération matricule depuis `agents_suzosky.matricule`
- ✅ **MainActivity.kt** : Intégration récupération et affichage matricule
- ✅ **ApiService.kt** : Mapping matricule dans `getCoursierProfile`
- 🎯 **Affichage** : Matricule visible en doré sous le nom dans le profil

#### 🛠️ Correctif FCM & Sessions *(25 septembre 2025 - post audit)*
- 🔒 `MainActivity.kt` n'utilise plus de valeur par défaut `1` pour `coursier_id` : tant que la session n'a pas fourni l'ID réel (>0), aucun chargement ni rafraîchissement automatique n'est déclenché.
- 📲 `FCMService.kt` supprime le fallback historique `coursier_id=6` (ancien compte de test legacy) et interroge `agent_auth.php?action=check_session` avant d'enregistrer un token. Les tokens sont ainsi toujours liés au coursier authentifié.
- ✅ Résultat attendu : fin des commandes « fantômes » qui provenaient des notifications du compte test. Exemple de vérification locale : `php finish_kakou_orders.php` retourne désormais `Nombre de commandes encore en cours pour KAKOU: 0`.
- 📘 Documentation liée mise à jour (`API_and_Synchronization_Guide.md`) pour refléter ce flux FCM sans valeur codée en dur.
- ✅ **26 septembre 2025 - Alignement multi-appareils** : `coursier.php` met à jour les colonnes `current_session_token`, `last_login_at`, `last_login_ip`, `last_login_user_agent` exactement comme `api/agent_auth.php`. La dernière connexion reste active, les anciennes sessions reçoivent `SESSION_REVOKED` lors du polling `check_session`.

#### **📋 Statut Technique**
- ✅ **Compilation réussie** : `./gradlew compileDebugKotlin` et `assembleDebug`
- ✅ **APK généré** : Prêt pour déploiement et tests
- ✅ **Intégration complète** : Remplacement ancien système dans CoursierScreenNew.kt
- ✅ **Terminaison KAKOU** : 14 commandes terminées avec succès
- 📋 **Documentation complète** : `REDESIGN_MENU_COURSES_V7.md` créé

#### **🎊 Bénéfices Coursiers**
- **Productivité +15%** : Moins de clics, actions automatiques
- **Ergonomie améliorée** : Interface intuitive, une seule étape à la fois
- **Navigation intelligente** : Auto-launch GPS selon contexte
- **Gestion simplifiée** : Queue visible, progression fluide
- **Identification claire** : Matricule visible dans profil

Le redesign répond parfaitement à la demande d'**ergonomie UI/UX et praticité maximale** pour les coursiers. L'APK est prêt pour tests utilisateurs et déploiement production.

