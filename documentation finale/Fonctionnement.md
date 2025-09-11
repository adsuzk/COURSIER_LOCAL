# Fonctionnement du système Suzosky Coursier

Ce document décrit la logique et le fonctionnement détaillé des principales fonctionnalités du projet, afin d’éviter toute régression et pour référence future.

## 1. Authentification requise avant commande
- La fonction `onDOMContentLoaded` ajoute un gestionnaire sur le bouton Commander.
- Au clic, le script vérifie `window.currentClient` :
  - Si **non connecté**, ouvre le modal de connexion (`connexionModal`) et **arrête** le traitement.
  - Si **connecté**, continue la validation du formulaire.
- Au rendu du formulaire : si `$_SESSION['client_id']` existe, le champ `senderPhone` est prérempli avec la valeur de session (`$_SESSION['client_telephone']`) et mis en `readonly`. Sinon, le champ reste vide.
  - Dans l’en-tête (`sections/index/header.php`) :
    - Si `$_SESSION['client_id']` existe, on affiche le nom du client (`$_SESSION['client_nom']`) et le lien “Déconnexion” (vers `logout.php`).
    - Sinon, on affiche le lien “Connexion Particulier” (bouton avec id `openConnexionLink`) et “Espace Business”.
  - Le script `assets/js/connexion_modal.js` est inclus dans `index.php` juste après les modals :
    - Au clic sur “Connexion Particulier” (lien avec id `openConnexionLink`), il fait un fetch de `/COURSIER_LOCAL/sections index/connexion.php` pour charger dynamiquement le formulaire.
    - Sur `submit` du formulaire (`#loginForm`), il POSTe `action=login` à `/COURSIER_LOCAL/api/auth.php?action=login`, et en cas de `data.success`, recharge la page pour appliquer la session PHP.

## 2. Formatage des numéros de téléphone
 - Fonction `formatPhone(v)` :
   - Supprime tous les caractères non numériques.
   - Retire l’indicatif `225` si présent.
   - Limite la saisie à 10 chiffres locaux.
   - Insère des espaces toutes les 2 chiffres.
   - Préfixe la valeur par `+225 `.
- Appliqué aux champs `senderPhone` et `receiverPhone` pour cohérence.

## 3. Validation du formulaire
- Champs obligatoires :
  - Départ (`departure`), Destination (`destination`), Téléphone Expéditeur (`senderPhone`), Téléphone Destinataire (`receiverPhone`), Priorité.
- Si un champ vide ou invalide, alert et retour.

## 4. Estimation du prix (Price Calculation)
- Inclus via `js_price_calculation.php`.
 Fonction `setupPriceCalc()` :
   - Recherche la disponibilité de `google.maps.DistanceMatrixService`, et retente après 1000 ms si non chargée.
   - Attache les événements `input` et `blur` sur `#departure` et `#destination`, et `change` sur les radios priorité.
   - À chaque appel de `calculate()` :
     - Vide l’état précédent (`price-visible`, `price-error`).
     - Si l’un des champs est vide, masque la section.
     - Sinon, interroge Distance Matrix et, au retour :
       - Parse `distance.text` et `duration.text`, calcule `kmVal`.
       - Détermine la priorité choisie (`normale`, `urgente`, `express`).
       - Applique la grille tarifaire, calcule `cost = base + ceil(kmVal * perKm)`.
       - Met à jour :
         - `#distance-info` → `📏 ${distance.text}`
         - `#time-info`     → `⏱️ ${duration.text}`
         - `#price-breakdown` : deux lignes (base + km × tarif)
         - `#total-price` : `💰 ${cost} FCFA`, couleur `borderColor = cfg.color`
       - Affiche la section (`style.display = 'block'`) et ajoute la classe `price-visible` pour l’animation.
   - Exécution initiale dès DOM prêt pour gérer les formulaires préremplis.
- Le champ `paymentMethod` détermine la suite du traitement.

## 6. Traitement du paiement (js_form_handling.js)
 - Au clic sur Commander, si **mode ≠ cash** :
   - Récupère toutes les données du formulaire via `FormData`, y compris `order_number` et `amount`.
   - Appelle `/api/initiate_order_payment.php` en POST.
   - Si succès, ouvre le modal CinetPay avec l’URL de paiement.
- Si **mode = cash** :
  - Soumet directement le formulaire à `/api/submit_order.php`.

## 7. Intégration CinetPay (payment_return.php)
- Le client paye via le modal iFrame.
- À retour (`payment_return.php`), la fonction :
  - Vérifie statut via `checkPaymentStatus(transaction_id)`.
  - Si `completed`, mise à jour DB :
    - `order_payments.status = completed`
    - `commandes.paiement_confirme = 1`
  - Assignation d’un coursier disponible (`assignNearestCourier`) selon géoloc.

---
*Document généré automatiquement le 11/09/2025.*
