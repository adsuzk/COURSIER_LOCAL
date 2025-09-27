# Workflow de bout en bout – Commande → Notification coursier (Sept 2025)

Ce document explique le processus complet: saisie d’une commande côté web, enregistrements DB, attribution, notification, et impacts financiers.

## 1) Création de commande (web)
- Interface: index.php / coursier.php (formulaire)
- Endpoint: POST /api/submit_order.php
- Entrées clés: departure, destination, senderPhone, receiverPhone, priority, paymentMethod, price, departure_lat/lng
- Traitements serveur:
  - Normalisation téléphones (digits-only)
  - Génération order_number et/ou code_commande (compat schéma)
  - Création/mirror client (clients_particuliers → clients) pour FK client_id
  - Insert dynamique dans commandes (colonnes détectées)
  - Statut initial: nouvelle (si colonne)
  - Paiement: si != cash, init CinetPay et retourner payment_url
  - Attribution auto: activée si coordonnées fournies (departure_lat/lng) → POST assign_nearest_coursier via appUrl()
- Sortie: { success, order_id, order_number, code_commande?, payment_url? }

Tables affectées:
- commandes (+ champs client_id/expediteur_id/destinataire_id, mode_paiement, prix_estime, …)
- clients_particuliers et clients (création/sync des fiches)

## 2) Attribution d’un coursier (automatique ou manuelle)
- Endpoint auto: POST /api/assign_nearest_coursier.php
- Entrée: { order_id, departure_lat, departure_lng }
- Sélection: positions récentes (≤ 180s) via tracking_helpers; calcul Haversine; coursier le plus proche
- Effets:
  - commandes.coursier_id = {id}
  - commandes.statut = 'assignee' (si colonne)
  - Table device_tokens consultée; si tokens → envoi notification via FCM (bibliothèque lib/fcm.php)
- Variante de test/liaison: table commandes_coursiers (commande_id, coursier_id, statut, date_attribution)
- APIs coursier:
  - get_assigned_orders.php?coursier_id=ID
  - poll_coursier_orders.php?coursier_id=ID

Tables affectées:
- commandes (mise à jour coursier_id, statut)
- device_tokens (enregistrée par l’app mobile)
- commandes_coursiers (si flux avec table de liaison)

## 3) App mobile – réception et affichage
- L’app Android enregistre un token via register_device_token.php
- Sur notification (type=new_order, order_id=...), l’app affiche la commande
- Sinon, l’app peut poller périodiquement get_assigned_orders ou poll_coursier_orders

## 4) Suivi et exécution
- Le coursier met l’app en ligne et envoie régulièrement sa position: POST update_coursier_position.php
- Statuts clichés: nouvelle → acceptee → en_cours → picked_up → livree
- Endpoint statut: POST update_order_status.php
  - Contraintes cash: livraison bloquée si cash non confirmé (cash_collected)
  - Refus côté app (assignWithLock action=release) : le backend libère la commande **et** tente immédiatement une ré-attribution automatique en choisissant le prochain coursier actif (distance si positions dispo, sinon charge la plus faible). Une notification `new_order` est poussée au coursier sélectionné.

Tables affectées:
- Table(s) de commandes (commandes ou commandes_classiques selon schéma)
- Table(s) de tracking positions (via tracking_helpers)

## 5) Enregistrements financiers
- À l’acceptation (`assign_with_lock.php`, action=accept): application immédiate du prélèvement plateforme.
  - Débit idempotent `transactions_financieres` ref `DELIV_<order_number>_FEE` calculé via `frais_plateforme` (%), solde coursier décrémenté.
  - Snapshot des paramètres actifs (financial_context_by_order) créé si absent.
- À la livraison et/ou en job programmé, le backend crée (ou complète si déjà initié):
  - Commission coursier (crédit `DELIV_<order_number>`)
  - Frais plateforme (débit) uniquement si non posé lors de l’acceptation.
- Déclenchement principal: statut `livree` via `update_order_status.php` (idempotent, mêmes références).
- Endpoint utilitaire: GET create_financial_records.php?commande_id=... (tests)
- Résultat: lignes dans transactions_financieres et mise à jour comptes_coursiers.solde; taux dynamiques: `commission_suzosky` (1–50%) et `frais_plateforme` (0–50%) paramétrables dans l’admin (Dashboard & Calcul des prix).

Callbacks paiement (si paiement électronique)
- `cinetpay/payment_notify.php` / `webhook_cinetpay.php` / `cinetpay_callback.php`
  - Réception notification/retour CinetPay et mise à jour de l’état de transaction/commande
  - Journaux: `cinetpay_notification.log`, `cinetpay_api.log`

Tables affectées:
- transactions_financieres: { type: credit|debit, montant, compte_type, compte_id, reference, description, statut, date_creation }
- comptes_coursiers: { coursier_id, solde, date_modification }

## 6) Points de contrôle et diagnostics
- Logs: diagnostics_errors.log, diagnostics_db.log, diagnostics_sql_commands.log
- Vérifications SQL rapides (exemples):
  - SELECT * FROM commandes ORDER BY id DESC LIMIT 5
  - SELECT * FROM commandes_coursiers ORDER BY date_attribution DESC LIMIT 5
  - SELECT * FROM device_tokens WHERE coursier_id=1
  - SELECT * FROM transactions_financieres ORDER BY id DESC LIMIT 10
  - SELECT * FROM comptes_coursiers WHERE coursier_id=1
  - Page admin « Audit livraisons »: `admin.php?section=finances_audit` — liste les commandes livrées, calcule les montants à partir des taux actuels et vérifie la présence des transactions attendues.

## 7) Éléments restants / améliorations
- Réactiver l’attribution automatique dans submit_order.php (supprimer le guard if(false) et résoudre l’erreur 500 interne si appelée en local)
- Intégrer envoi FCM réel dans lib/fcm.php (remplacer test_notification par envoi effectif)
- UI Android: affichage des commandes assignées et flux d’acceptation bout à bout
- Automatiser la création des écritures financières au changement de statut (livree)
 - Raccorder le callback CinetPay à une transition de statut et déclenchement financier automatique
 - Enrichir la vue admin “App Updates” (télémétrie) avec alertes en temps réel

## 8) Synchronisation Timeline Coursier ↔ Client (Activation du suivi live)

Objectif métier:
- Le client ne doit voir la position en temps réel du coursier que lorsque la course du client devient la course active dans l’application du coursier.
- Avant activation, le client voit seulement « Le coursier termine une course et se rend vers vous » (pas de position live).

Implémentation technique:
- Table de liaison `commandes_coursiers` avec colonne `active` (TINYINT). Une seule commande active par coursier.
- Endpoint d’activation: `POST /api/set_active_order.php` avec payload `{ coursier_id, commande_id, active }`.
- Endpoint lecture position gated: `GET /api/get_courier_position_for_order.php?commande_id=...` → position renvoyée uniquement si active=1.
- `GET /api/order_status.php` expose `live_tracking: boolean` pour guider le client.

Côté App Coursier (Android):
- À l’acceptation d’une commande, l’app appelle `setActiveOrder(coursierId, commandeId, true)` pour démarrer le suivi côté client au bon moment.
- À la fin de la course (livree / cash confirmé), l’app appelle `setActiveOrder(..., false)` pour couper le suivi de cette commande.
- Les transitions de statut côté serveur sont mises à jour via `update_order_status`.

Filets serveur:
- `update_order_status.php` marque aussi la commande active quand le statut devient `picked_up` ou `en_cours` (si la table de liaison existe). Ceci assure la cohérence même si l’appel explicite d’activation est manqué.

