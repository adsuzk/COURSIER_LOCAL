# Suzosky Coursier – Référence API (Sept 2025)

Ce do  - 200 → { success: true, order_id, order_number, code_commande?, payment_url?, transaction_id? }
  - **CORRECTIONS 2025-09-26:**
    - ✅ Table `clients` restaurée (fix erreur SQLSTATE[42S02])
    - ✅ Mapping priorité: `'normal'` → `'normale'` (compatibilité ENUM DB)
    - ✅ Vérification table existence via `information_schema` (fix MariaDB)
  - Remarques:
    - Insertion dynamique compatible avec colonnes variables (order_number/code_commande...)
    - Paiement: init CinetPay si paymentMethod != 'cash'
    - Prix: si `price` est manquant ou ≤ 0, le backend recalcule via `parametres_tarification` (frais_base/prix_kilometre) + multiplicateurs priorité, puis log `PRICING_FALLBACK_APPLIED`; surveiller `diagnostic_logs/diagnostics_errors_utf8.log`.
    - Attribution auto: actuellement réactivée et fonctionnelle liste les endpoints REST actifs côté backend PHP, avec formats d’entrée/sortie, exemples, et remarques d’environnement (local/prod).

## Environnements et bases d’URL
- Local (XAMPP): http(s)://localhost/coursier_prod/api/
- Production (LWS): https://<domaine>/api/

Toutes les réponses sont JSON: { success: boolean, ... } et renvoient un code HTTP 2xx en succès, 4xx/5xx en erreur quand pertinent.

## Authentification Coursier
- POST agent_auth.php?action=login
  - Body JSON: { "identifier": "<matricule ou téléphone>", "password": "<plain>" }
  - 200 → { success, agent: { id, matricule, nom, prenoms, telephone, ... } }
  - Notes: Si plain_password présent côté DB, il est migré vers hash au 1er login.
- GET agent_auth.php?action=check_session
- POST agent_auth.php?action=logout

Exemple (request/response)
Requête
{
  "identifier": "C001",
  "password": "123456"
}
Réponse
{
  "success": true,
  "agent": {
    "id": 1,
    "matricule": "C001",
    "nom": "KOUAME",
    "prenoms": "Eric",
    "telephone": "+2250700000000"
  }
}

## Token de notification (FCM)
- POST register_device_token.php
  - form-data: coursier_id, token
  - 200 → { success: true }

## Profil et tableau de bord coursier
- GET get_coursier_data.php?coursier_id={id}
  - 200 → { success, data: { balance, commandes_attente, gains_du_jour, commandes:[...] } }
  - Tolérant à différents schémas (comptes_coursiers, coursier_accounts, etc.).

## Commandes – création côté client web
- POST submit_order.php **[CORRIGÉ 2025-09-26]**
  - JSON: {
      departure, destination,
      senderPhone, receiverPhone,
      priority, paymentMethod,
      price, distance?, duration?,
      departure_lat?, departure_lng?, packageDescription?
    }
  - 200 → { success: true, order_id, order_number, code_commande?, payment_url?, transaction_id? }
  - Remarques:
    - Insertion dynamique compatible avec colonnes variables (order_number/code_commande...)
    - Paiement: init CinetPay si paymentMethod != 'cash'
    - Prix: si `price` est manquant ou ≤ 0, le backend recalcule via `parametres_tarification` (frais_base/prix_kilometre) + multiplicateurs priorité, puis log `PRICING_FALLBACK_APPLIED`; surveiller `diagnostic_logs/diagnostics_errors_utf8.log`.
    - Attribution auto: actuellement désactivée pour debug; endpoint d’attribution disponible (voir ci-dessous)

Exemple (request/response)
Requête
{
  "departure": "Cocody Danga",
  "destination": "Plateau Immeuble A",
  "senderPhone": "+2250700112233",
  "receiverPhone": "+2250700332211",
  "priority": "normale",
  "paymentMethod": "cash",
  "price": 2000,
  "departure_lat": 5.3501,
  "departure_lng": -3.9965,
  "packageDescription": "Dossier scellé"
}
Réponse
{
  "success": true,
  "order_id": 7,
  "order_number": "SZK20250922e4f52a",
  "code_commande": "SZK250922123456"
}

## Attribution du coursier
- POST assign_nearest_coursier.php
  - JSON: { order_id, departure_lat, departure_lng }
  - 200 → { success: true, coursier_id, distance_km }
  - Erreurs fréquentes: { success:false, message: 'Aucun coursier connecté' }
  - Effets:
    - Met à jour commandes.coursier_id (+ statut=assignee si colonne)
    - Envoie une notification push (via tokens FCM enregistrés)

Exemple (request/response)
Requête
{
  "order_id": 7,
  "departure_lat": 5.3501,
  "departure_lng": -3.9965
}
Réponse
{
  "success": true,
  "coursier_id": 1,
  "distance_km": 1.42
}

- GET get_assigned_orders.php?coursier_id={id}
  - 200 → { success, orders:[...], count }
  - Source: table de liaison commandes_coursiers (assignee/acceptee/en_cours)

- GET poll_coursier_orders.php?coursier_id={id}
  - 200 → { success, order|null }
  - Source: commandes.coursier_id + statut in ('nouvelle','en_cours')

## Suivi position temps réel
- POST update_coursier_position.php
  - JSON: { coursier_id, lat, lng, accuracy? }
  - 200 → { success: true }

Exemple
{
  "coursier_id": 1,
  "lat": 5.3478,
  "lng": -3.9999,
  "accuracy": 12.5
}

- GET get_coursiers_positions.php
  - 200 → { success, positions:[ { coursier_id, lat, lng, updated_at } ] }

### Activation du suivi live par commande (client ↔ coursier)

- POST set_active_order.php
  - JSON: { coursier_id: number, commande_id: number, active: boolean }
  - 200 → { success, data: { coursier_id, commande_id, active } }
  - Effets: marque une seule commande comme « active » pour un coursier (désactive les autres). Le client ne voit la position live du coursier que lorsque la commande est active.

- GET get_courier_position_for_order.php?commande_id={id}
  - 200 → { success, data: { live: boolean, position: { lat, lng, updated_at, coursier_id } | null } }
  - Si la commande n’est pas active pour un coursier, live=false et position=null.

- GET order_status.php?order_id={id} | &code_commande=...
  - 200 → { success, data: { order_id, statut, coursier_id, live_tracking: boolean, timeline:[...] } }
  - Le champ live_tracking passe à true uniquement si la commande est marquée active via commandes_coursiers.active = 1.

## Commandes – statuts et flux
- POST assign_with_lock.php
  - JSON: { commande_id, coursier_id, action=accept|release, ttl_seconds? }
  - 200 accept → { success:true, locked:true, statut:"acceptee", finance:{ applied, amount, reference, fee_rate, amount_base } }
    - Applique immédiatement le prélèvement `frais_plateforme` (débit `transactions_financieres` ref `DELIV_<order_number>_FEE`) et assure la création du compte coursier si besoin.
  - 200 release → { success:true, released:true, statut:"nouvelle", reassignment:{ success?, coursier_id?, notified? ... } }
    - Relâche le verrou et tente une ré-attribution automatique à un coursier actif (distance ou charge minimale) avec notification `new_order`.
  - 409 si la commande est déjà verrouillée par un autre coursier.
- POST update_order_status.php
  - JSON: { commande_id, statut, cash_collected?, cash_amount? }
  - 200 → { success, cash_required, cash_collected }
  - Statuts supportés: nouvelle, acceptee, en_cours, picked_up, livree
  - Contraintes cash: livree bloque si cash non confirmé
  - Note: une heuristique côté serveur marque la commande « active » quand le statut devient picked_up ou en_cours (si la table commandes_coursiers existe). Toutefois, l’app coursier peut activer dès l’acceptation via set_active_order.php pour démarrer le suivi côté client au bon moment.

- GET/POST get_coursier_orders.php
  - Query/JSON: { coursier_id, status=all|active|completed|cancelled|<statut>, limit?, offset? }
  - 200 → { success, data: { coursier, commandes:[...], pagination, statistiques, gains, filters } }
  - Note: utilise schémas historiques (coursiers, commandes, gains_coursiers)

## Paiements & finances
- POST initiate_order_payment.php
  - Démarrage paiement CinetPay pour une commande (si applicable)

- GET create_financial_records.php?commande_id={id}
  - Crée transactions (commission, frais plateforme) et met à jour solde coursier
  - 200 → { success, ... }

- POST/GET update_order_status.php (statut 'livree')
  - Déclenche automatiquement et de manière idempotente les écritures financières avec références `DELIV_<order_number>` (commission) et `DELIV_<order_number>_FEE` (frais plateforme).
  - Les taux utilisés sont dynamiques, issus de `parametres_tarification`: `commission_suzosky` (1–50%) et `frais_plateforme` (0–50%).

## Télémétrie et logs
- POST telemetry.php
  - Collecte d’événements, crashes, sessions (SDK Android)

- POST log_js_error.php
  - { msg, stack?, url?, ua? } → logging côté serveur

## Divers
- POST register_device_token.php (déjà listé)
- GET/POST sync_pricing.php, orders.php, order_status.php, get_client.php, submit_client.php, profile.php, etc.

## Erreurs et codes HTTP
- 200: { success: true, ... }
- 400: { success:false, message|error }
- 401/403: accès refusé
- 404: ressource introuvable
- 500: erreur serveur (détails loggés dans diagnostics_*.log)

## Sécurité & CORS
- La plupart des endpoints définissent Access-Control-Allow-Origin: '*'
- Les endpoints sensibles devraient restreindre l’origine en prod et valider les sessions côté admin.

## Compléments – Endpoints ajoutés

### Tarification & prix
- GET/POST sync_pricing.php
  - Synchronise ou récupère la grille tarifaire (admin/outils). Réponse JSON avec tarifs.
  - Paramètres supportés: `prix_kilometre`, `commission_suzosky` (max 50%), `frais_base`, `supp_km_rate`, `supp_km_free_allowance`, `frais_plateforme` (0–50%).
- GET /admin/js_price_calculation_admin.php (page utilitaire, non-API): calcul et tests prix.

### Clients & profils
- GET get_client.php?phone={num}
  - Retourne les infos client par téléphone.
- POST submit_client.php
  - Crée ou met à jour un client (form-data/JSON selon usage).
- GET/POST profile.php
  - Lecture/MAJ d’éléments de profil minimal selon session.

### Commandes (compatibilité/legacy)
- GET/POST orders.php
  - Opérations legacy (listing/filtrage) – préférer les nouveaux endpoints dédiés.
- POST order_status.php
  - Mise à jour status legacy – préférer update_order_status.php.

### Chat (tripartite)
- POST chat/init.php
  - { user_id|coursier_id, peer_id, channel } → initialise thread.
- POST chat/send_message.php
  - { thread_id, sender_id, message } → envoie un message; log dans chat_api.log.
- GET chat/get_messages.php?thread_id=...
  - Récupère messages paginés.

### Mises à jour d’app & télémétrie
- POST app_updates.php (api/)
  - Upsert d’état d’installation/MAJ côté device; télémétrie légère.
- GET check_update.php
  - Vérifie si une version plus récente est disponible pour le device/app.
- POST telemetry.php
  - Collecte d’événements (crash, session, event). Exige en-tête X-API-Key: suzosky_telemetry_2025.

Exemple (telemetry)
Headers: { "X-API-Key": "suzosky_telemetry_2025" }
{
  "endpoint": "log_event",
  "device_id": "abc-123",
  "event": "open_app",
  "meta": {"version": "1.0.3"}
}

### Paiements & callbacks
- POST initiate_order_payment.php
  - Lance un paiement CinetPay (voir plus haut dans Paiements & finances).
- POST/GET cinetpay_callback.php
  - Point de retour CinetPay (selon intégration); met à jour état paiement.
- POST/GET webhook_cinetpay.php
  - Réception webhooks CinetPay; journalise et traite la transaction.
- POST cinetpay/payment_notify.php
  - Réception notification CinetPay (serveur à serveur); trace via cinetpay_notification.log.

### Positions & statut (compléments)
- POST update_coursier_status.php
  - Payload composite { status, position{lat,lng,accuracy}, ... }
  - Peut insérer position et changer disponibilité.
- GET get_coursiers_positions.php
  - { success, positions:[{ coursier_id, lat, lng, updated_at }] }
- GET get_coursier_info.php?coursier_id=ID
  - Infos coursier + dernière position (via tracking_helpers).

### Diagnostics & utilitaires
- POST log_js_error.php
  - { msg, stack?, url?, ua? } → écrit dans diagnostics_js_errors.log.
- GET Test/_root_migrated/test_db_connection.php, Test/_root_migrated/diagnostic_*.php
  - Pages et scripts de vérification environnement.
- GET add_test_order.php
  - Ajoute une commande de test et effectue un push si token disponible.

### Admin – Audit finances
- GET admin.php?section=finances_audit
  - Diagnostic lecture seule des commandes livrées avec calcul des montants (taux dynamiques) et drapeaux ✅/❌ indiquant la présence des transactions `DELIV_...`.

### Admin – APIs lecture (read-only)
- GET api/admin/order_timeline.php?commande_id=...
  - Timeline d’une commande; s’appuie sur tracking_helpers.
- GET api/admin/live_data.php
  - Vue JSON agrégée: positions, commandes récentes.
- GET api/admin/live_data_sse.php
  - Flux SSE temps réel pour dashboards.

