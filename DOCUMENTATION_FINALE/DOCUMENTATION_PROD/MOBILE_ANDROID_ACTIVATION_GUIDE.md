# Guide Android — Activation commande et suivi live

Ce document décrit l’activation de commande côté coursier, la synchronisation timeline et le déclenchement du suivi live visible côté client.

## Résumé
- Une commande devient « active » dès l’acceptation par le coursier.
- Le client ne voit le déplacement live du coursier que si sa commande est active pour ce coursier.
- La désactivation intervient à la fin de la livraison (cash confirmé si espèces, livré sinon) ou lorsqu’on passe à la prochaine commande.

## Endpoints utilisés
- POST `api/set_active_order.php` — active/désactive une commande pour un coursier.
- GET/POST `api/update_order_status.php` — synchronise l’étape côté serveur.
- GET `api/order_status.php` — expose `live_tracking` (booléen) pour le client.
- GET `api/get_courier_position_for_order.php` — renvoie la position live uniquement si la commande est active.

## Intégration dans l’app Android
Fichiers concernés:
- `CoursierAppV7/app/src/main/java/com/suzosky/coursier/ui/screens/CoursierScreenNew.kt`
- `CoursierAppV7/app/src/main/java/com/suzosky/coursier/network/ApiService.kt`
- `CoursierAppV7/app/src/main/java/com/suzosky/coursier/utils/DeliveryStatusMapper.kt`

### 1) Activation à l’acceptation
Dans `CoursierScreenNew.kt`, lors de l’action `DeliveryStep.ACCEPTED`:
- Arrêt du son de notification.
- Appel `ApiService.setActiveOrder(coursierId, currentOrder.id, active = true)` pour activer la commande.
- `ApiService.updateOrderStatus(..., "acceptee")` pour synchroniser le statut.

### 2) Progression des étapes
- `PICKED_UP` → `updateOrderStatus(..., "picked_up")`, puis passage à `EN_ROUTE_DELIVERY`.
- `DELIVERY_ARRIVED` → mise à jour locale de l’étape.
- `DELIVERED`:
  - Si paiement « espèces »: ouverture du `CashConfirmationDialog`.
  - Sinon: `updateOrderStatus(..., "livree")` puis reset vers la prochaine commande.
- `CASH_CONFIRMED` (espèces): `updateOrderStatusWithCash(..., statut = "livree", cashCollected = true)` puis reset.

### 3) Désactivation en fin de course
La méthode locale `resetToNextOrder()`:
- Appelle `ApiService.setActiveOrder(coursierId, order.id, active = false)` (best-effort).
- Réinitialise l’étape et sélectionne la prochaine commande en attente.

## Côté serveur (rappel)
- `order_status.php` expose `live_tracking` selon la table `commandes_coursiers.active`.
- `get_courier_position_for_order.php` ne renvoie des positions que si la commande est active pour ce coursier.
- `update_order_status.php` peut marquer automatiquement une commande active lors de `picked_up`/`en_cours` (best-effort).

## Messages UI et mapping
- `DeliveryStatusMapper` mappe les étapes UI → statuts serveur et fournit les messages succès/toast.
- Affichage du mode de paiement (Espèces/Non-Espèces) dans la timeline; le cash déclenche le dialogue de confirmation.

## Bonnes pratiques
- Toujours activer à l’acceptation, désactiver lors du reset.
- Ne pas modifier localement le solde après recharge/paiement; recharger depuis le serveur.
- Logguer les erreurs réseau et afficher un toast utilisateur en cas d’échec.
