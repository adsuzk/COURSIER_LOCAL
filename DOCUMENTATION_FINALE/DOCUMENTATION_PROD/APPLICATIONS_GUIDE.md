# Guide des Applications – Web & Android (Sept 2025)

Ce guide décrit la configuration, l’environnement et l’utilisation des applications: web (PHP/XAMPP) et Android.

## 1. Application Web (PHP/XAMPP)

Note
- Des détails historiques et compléments d'interface admin/telemetry migrés depuis la racine sont regroupés dans `ANNEXES_ROOT_MARKDOWNS.md`.

### 1.1. Racine et URLs
- Racine: /coursier_prod
- URL locale: https://localhost/coursier_prod/
- URL prod: https://<domaine>/

### 1.2. Admin & Connexion
- Admin: /coursier_prod/admin.php (fix: action du formulaire en chemin absolu)
- Modal login: index.php contient la modale de connexion réactivée

Plan des interfaces principales
- `index.php` – page d’accueil + modale de connexion
- `coursier.php` – interface principale web (client/ops)
- `admin/admin.php` – hub d’administration avec sections:
  - Applications (upload APK, métadonnées)
  - Finances (transactions, soldes)
  - App Updates (télémétrie + monitoring devices)
  - Clients, Commandes, Chat (selon configuration)
- `admin/app_updates.php` – tableau télémétrie + carte (Leaflet), recherche, détails devices
- `admin/finances.php` – reporting finances
  - Simulateur détaillé: affiche Prix total client, Commission Suzosky (%), Frais plateforme (%), Net coursier (commission - frais).
  - Onglet Transactions:
    - Vue agrégée par commande avec Commission, Frais plateforme, Net coursier, Total client (si dispo), Coursier, Mode de paiement et indicateur Cash/non-cash.
    - Filtres: N° commande, Coursier ID, Limite. Boutons Export CSV et Export XLSX (export global selon les filtres).
    - Bouton “Voir détails”: ouvre une modale listant les écritures (DELIV_<order>, DELIV_<order>_FEE) et le snapshot des paramètres capturé à la livraison (commission_rate, fee_rate, prix_kilometre, frais_base, supp_km_rate, supp_km_free_allowance). Un bouton Export XLSX est disponible dans la modale pour exporter uniquement cette commande.
    - Raccourci depuis “Comptes coursiers” : lien “Voir transactions” avec filtre par Coursier ID.
- `view_logs.php`, `view_logs_fixed.php` – visualisation logs

### 1.3. Google Maps
- La clé API peut être configurée dans admin/dashboard (placeholder géré si absente)

### 1.4. APIs principales
- Réf complète: APIS_REFERENCE.md
- Endpoints clés pour le flux commande → coursier
  - submit_order.php (création)
  - assign_nearest_coursier.php (attribution)
  - get_assigned_orders.php / poll_coursier_orders.php (récup coursier)
  - update_coursier_position.php (tracking)
  - register_device_token.php (notifications)

### 1.5. Finances
- Tables: transactions_financieres, comptes_coursiers
- Endpoint utilitaire: create_financial_records.php (commission, frais plateforme)
- Page admin finances: admin.php?section=finances
  - Dashboard: sliders temps réel pour Commission (jusqu’à 50%) et Frais plateforme (0–50%).
  - Calcul des prix: formulaire avec `prix_kilometre`, `frais_base`, `supp_km_rate`, `supp_km_free_allowance`, `commission_suzosky` (1–50%), `frais_plateforme` (0–50%).
  - Transactions: export CSV/XLSX des écritures de livraison (références `DELIV_<order_number>` et `DELIV_<order_number>_FEE`) agrégées par commande, incluant Commission, Frais, Net, Total client; modale “Voir détails” avec snapshot des paramètres utilisés.
  - Audit livraisons: admin.php?section=finances_audit — vérifie les écritures `DELIV_<order_number>` et `DELIV_<order_number>_FEE`.

### 1.7. Sessions uniques (coursiers)
- À la connexion d’un coursier, un jeton de session unique est généré et sauvegardé dans `agents_suzosky.current_session_token`.
- En cas de nouvelle connexion du même compte sur un autre appareil, le jeton est remplacé, ce qui invalide la session précédente.
- L’endpoint `agent_auth.php?action=check_session` renvoie `SESSION_REVOKED` si la session locale n’est plus valide; le client doit déconnecter l’utilisateur et redemander une connexion.

### 1.8. Healthcheck environnement
- `Test/healthcheck.php` retourne un JSON avec:
  - `php.version`
  - `ziparchive.enabled` et un smoke test de création d’archive
  - `db.connected` et la présence des tables clés: `transactions_financieres`, `parametres_tarification`, `commandes_classiques` (optionnelle selon déploiement), `financial_context_by_order` (créée à la première livraison)
  - Permissions d’écriture: dossier temporaire et `diagnostic_logs`
- Exécutable en CLI: `php Test/healthcheck.php`

### 1.6. Logs & diagnostics
 diagnostics_errors.log, diagnostics_db.log, diagnostics_sql_commands.log, diagnostics_cinetpay.log
 diagnostics_js_errors.log (logs JS)
 cinetpay_notification.log (callback CinetPay)
 chat_api.log (APIs de chat)
 Pages de diagnostic utiles (migrées)
 `Test/_root_migrated/diagnostic_auth.php`, `Test/_root_migrated/diagnostic_payment_endpoint.php`, `Test/_root_migrated/diagnostic_ssl.php`, `Test/_root_migrated/diagnostic_final.php`
 `Test/_root_migrated/test_db_connection.php`, `Test/_root_migrated/test_new_features.php`

PWA/Web app manifest & service worker
- `manifest.json`, `sw.js` – si activés côté navigateur, offrent des capacités basiques PWA

## 2. Application Android

### 2.1. Environnements automatiques
- Debug (physique): base = http://<LAN_IP>/coursier_prod (DEBUG_LOCAL_HOST dans local.properties)
- Debug (émulateur): base = http://10.0.2.2/coursier_prod
- Release: base = prod, fallback si besoin
- BuildConfig:
  - USE_PROD_SERVER (false en debug, true en release)
  - DEBUG_LOCAL_HOST (exposé depuis local.properties)

Exemple local.properties (ne pas commiter):

debug.localHost=192.168.1.8

### 2.2. Réseau & sécurité dev
- OkHttp 4.12.0, cookieJar mémoire
- Cleartext autorisé en debug pour HTTP local
- Logs détaillés: base URL choisie, URLs, réponses HTTP

### 2.3. API Service (résumé)
- Sélection de base URL selon device/émulateur & flags build
- Fallback: primary→secondary (debug local ou prod selon build)
- Méthodes: login (agent_auth), getCoursierData, getCoursierOrders, polling assignations

### 2.4. Notifications
- L’app enregistre le token FCM via register_device_token.php
- Réception push: payload { type: new_order, order_id }
- Note: Envoi FCM réel à intégrer côté serveur (test_notification.php prépare charge utile)

FCM côté serveur (aperçu)
- `api/lib/fcm.php` expose `fcm_send($tokens, $title, $body, $data=[])`
- Utilisation: `assign_nearest_coursier.php`, `add_test_order.php`

### 2.5. Tracking
- Envoi périodique position via update_coursier_position.php
- assign_nearest_coursier s’appuie sur dernières positions pour calculer le plus proche

### 2.6. Tests rapides
- Auth coursier: agent_auth.php?action=login
- Tableau de bord: get_coursier_data.php?coursier_id=1
- Créer commande: submit_order.php (voir APIS_REFERENCE)
- Assigner: assign_nearest_coursier.php
- Vérifier affectations: get_assigned_orders.php

### 2.7. Gestion session révoquée (SESSION_REVOKED)
- L’app appelle périodiquement `agent_auth.php?action=check_session` (toutes les ~15s).
- Si la réponse contient `SESSION_REVOKED` ou `NO_SESSION`, l’app effectue une déconnexion automatique (réinitialise `isLoggedIn`) et invite l’utilisateur à se reconnecter avec un Toast explicatif.

## 3. Déploiement

### 3.1. Backend local (XAMPP)
- PHP 8+, MySQL démarré
- Importer database_setup.sql puis migrations *.sql récentes
- Vérifier config.php (crédentials DB, appUrl)
  - Helpers clés: `appUrl($path)`, `routePath($path)` pour construire des URLs correctes sous `/coursier_prod`
  - `logger.php` → `logMessage($file, $message)` centralise l’écriture des logs

### 3.2. Android
- JDK 17, SDK Android installé
- Définir debug.localHost dans local.properties
- Compiler debug et installer sur appareil physique (LAN)

## 4. Points d’attention
- Attribution automatique dans submit_order est temporairement désactivée (guard if false) – activer après correction de l’erreur 500 côté assignation interne
- S’assurer que des positions récentes existent pour que l’attribution trouve un coursier
- Sécuriser CORS et limiter Access-Control-Allow-Origin en production pour endpoints sensibles
 - Mettre à jour la clé API télémétrie (`X-API-Key`) si déployée en prod
 - Vérifier `webhook_cinetpay.php` et `cinetpay/payment_notify.php` sont accessibles publiquement en HTTPS en prod

