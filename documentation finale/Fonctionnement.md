# Fonctionnement du système Suzosky Coursier

Ce document décrit la logique et le fonctionnement détaillé des principales fonctionnalités du projet, afin d’éviter toute régression et pour référence future.

## 1. Authentification requise avant commande
- La fonction `onDOMContentLoaded` ajoute un gestionnaire sur le bouton Commander.
- Au clic, le script vérifie `window.currentClient` :
  - Si **non connecté**, ouvre le modal de connexion (`connexionModal`) et **arrête** le traitement.
  - Si **connecté**, continue la validation du formulaire.
- Au rendu du formulaire : si `$_SESSION['client_id']` existe, le champ `senderPhone` est prérempli avec la valeur de session (`$_SESSION['client_telephone']`) et mis en `readonly`. Sinon, le champ reste vide.

## 2. Formatage des numéros de téléphone
- Fonction `fPhoneNumber(v)` :
  - Supprime tous les caractères non numériques.
  - Retire l’indicatif `225` si présent.
  - Ajoute un `0` devant si 8 chiffres.
  - Insère des espaces toutes les 2 digits.
- Appliqué aux champs `senderPhone` et `receiverPhone` pour cohérence.

## 3. Validation du formulaire
- Champs obligatoires :
  - Départ (`departure`), Destination (`destination`), Téléphone Expéditeur (`senderPhone`), Téléphone Destinataire (`receiverPhone`), Priorité.
- Si un champ vide ou invalide, alert et retour.

## 4. Estimation du prix (Price Calculation)
- Inclus via `js_price_calculation.php`.
- Fonction `setupPriceCalc()` :
  - Attache événements `input` et `blur` sur `departure`, `destination` et priorité.
  - Appelle `calculate()` pour récupérer dist/time via Google DistanceMatrix.
  - Affiche la section `.price-calculation-section` et met à jour `#distance-info`, `#time-info`, `#price-breakdown`, `#total-price`.

## 5. Sélection du mode de paiement
- Inclus dans `payment_methods.php`.
- Icônes cliquables, input radio caché.
- Le champ `paymentMethod` détermine la suite du traitement.

## 6. Traitement du paiement (js_form_handling.js)
- Au clic sur Commander, si **mode ≠ cash** :
  - Récupère toutes les données du formulaire via `FormData`.
  - Appelle `/api/initiate_order_payment.php` en POST.
  - Si succès, ouvre le modal CinetPay avec URL de paiement.
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
