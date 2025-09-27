# Guide UI Web – Connexion, Profil, Commander et Google Maps (Sept 2025)

Ce guide décrit le comportement actuel de l'interface web (page `index.php`) concernant le modal de connexion/profil, l'auto-remplissage du téléphone expéditeur, le flux "Commander" et le chargement de Google Maps.

## 1) Modal Connexion / Profil

- JS principal: `assets/js/connexion_modal.js`
- Conteneur modal: `sections_index/modals.php` (id `connexionModal`, `connexionModalBody`)
- Déclencheurs: lien `#openConnexionLink` (desktop) et variantes mobiles, et la fonction globale `window.openAccountModal()`

Fonctionnement:
- Au clic sur "Se connecter" ou lors de `openAccountModal()`, le script charge dynamiquement le fragment HTML `sections_index/connexion.php` dans le body du modal, puis affiche le modal.
- Navigation interne (inscription / mot de passe oublié) chargée via AJAX:
  - `sections_index/inscription.php`
  - `sections_index/forgot_password.php`
- Soumission des formulaires:
  - Login: `POST (multipart)` vers `api/auth.php?action=login`
  - Inscription: `POST` vers `api/auth.php` avec `action=register`
  - Mot de passe oublié: `POST` vers `api/auth.php` avec `action=forgot`
  - Validation front: contrôle des 5 caractères mot de passe, numéro ivoirien et email obligatoire avant l'appel API
- Vérification session initiale: `GET api/auth.php?action=check_session` pour initialiser l'UI si l'utilisateur est déjà connecté.

Sécurité et Base URL:
- `window.ROOT_PATH` est défini côté `index.php` sans slash final et basé sur `routePath('')`. Tous les fetch du modal utilisent `(window.ROOT_PATH || '') + '/api/...` pour éviter les chemins relatifs fragiles.

UI Profil:
- `openAccountModal()` appelle `api/auth.php?action=check_session`; si connecté, le contenu Profil s'affiche via `renderProfile(client)` avec:
  - Nom, Prénoms, Téléphone, Email
  - Bouton "Modifier le profil" → formulaire `editProfileForm` (email, téléphone, password 5 caractères)
  - Enregistrement: `POST api/auth.php` avec `action=updateProfile`
- Bouton "Se déconnecter" appelle `api/auth.php?action=logout` puis met à jour l'UI.

## 2) Auto‑remplissage du téléphone expéditeur

- Lors de la vérification de session réussie, `updateUIForLoggedInUser(client)` est appelé.
- Il masque le menu invité, affiche le menu utilisateur, et pré-remplit le champ `#senderPhone` avec `client.telephone`, puis le met en lecture seule.
- Fichier: `assets/js/connexion_modal.js` (fonctions `updateUIForLoggedInUser` et initialisation sur check_session).

## 3) Flux "Commander"

- JS: `sections_index/js_form_handling.php`
- Formulaire: `sections_index/order_form.php` (id `orderForm` et `.submit-btn`)

Comportement:
- `processOrder(e)` est attachée au submit et au clic du bouton. Elle:
  1) Empêche le défaut et vérifie `window.currentClient` (défini depuis la session PHP en haut du script)
     - Si non connecté: tente successivement d'ouvrir la modale (clic sur `#openConnexionLink`, puis `openConnexionModal()`, puis `showModal('connexionModal')`, sinon `alert`)
  2) Valide les champs `#departure` et `#destination`
  3) Selon la méthode de paiement:
     - Cash: soumet le formulaire (ou passe par un flux amélioré si `window.__cashFlowEnhanced` est actif)
     - Mobile: `POST` vers `api/initiate_order_payment.php` et ouvre un modal de paiement en iframe via `window.showPaymentModal(url)`

- Le numéro expéditeur étant prérempli et verrouillé si connecté, on évite les erreurs de saisie et accélère la commande.

Backend & assignation:
- `api/submit_order.php` crée la commande et, si `departure_lat/lng` sont fournis, déclenche l'attribution automatique via `appUrl('api/assign_nearest_coursier.php')`.
- L'endpoint d'attribution met à jour `commandes.coursier_id` et (si présent) `commandes.statut='assignee'`; notification FCM si des tokens existent.
- `api/order_status.php` dérive l'état "assignee" côté client si `coursier_id` est présent même si `statut` est vide.

## 4) Google Maps – Chargement et intégration

- La page charge UNE seule fois le script Google Maps:
  ```html
  <script src="https://maps.googleapis.com/maps/api/js?v=weekly&libraries=places&key=...&callback=initMap" async defer></script>
  ```
- Le callback `window.initMap` est défini dans `sections_index/js_google_maps.php`.
- L’autocomplétion est initialisée après chargement de l’API (via `setupAutocomplete()` déclenché dans `initMap`).
- Des fallbacks/erreurs sont gérés:
  - `gm_authFailure` → affiche une erreur explicite
  - Timeout si `google` non défini → overlay d’information (en prod uniquement)
- Nous avons uniformisé la base des chemins pour éviter les erreurs de type `ERR_NAME_NOT_RESOLVED`.

## 5) Références de fichiers

- `index.php`: définit `window.ROOT_PATH`, inclut les sections JS, et insère le script `connexion_modal.js` par chemin absolu stable
- `assets/js/connexion_modal.js`: logique modale (connexion, profil, session, déconnexion), préremplissage téléphone expéditeur
- `sections_index/js_form_handling.php`: gestion du formulaire Commander et modal de paiement iframe
- `sections_index/js_google_maps.php`: initialisation carte, markers, autocomplétion, gestion erreurs

## 6) Bonnes pratiques & diagnostics

- Toujours vérifier que `ROOT_PATH` est défini (console) et que `connexion_modal.js` charge sans 404
- En prod, s’assurer qu’une seule inclusion de Maps est présente et que `initMap` est appelée une fois
- Si `DistanceMatrix`/`Directions` renvoie `ZERO_RESULTS`, préférer passer des latLng (géocodage préalable) et retenter

---
Dernière mise à jour: 25 septembre 2025
