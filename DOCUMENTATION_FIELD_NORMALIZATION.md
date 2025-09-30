# Normalisation des champs d'entrée (FR / EN)

But
- Centraliser la normalisation des champs envoyés par le front pour éviter les erreurs d'affectation d'une commande lorsque différentes variantes de noms sont utilisées.

Que contient-on
- Une utilitaire `api/field_normalizer.php` qui mappe un ensemble de variantes FR/EN vers des clés canoniques attendues par l'API (ex: `departure_lat` / `departure_lng`, `adresse_depart`, `telephone_expediteur`).

Pourquoi
- Le front envoyait parfois `departure_lat` / `departure_lng` ou `latitude_depart` (ou d'autres variantes). L'endpoint d'affectation attend `departure_lat`/`departure_lng`, mais `submit_order.php` n'enregistrait pas ces valeurs -> commandes créées sans coords -> pas d'affectation.

Changments réalisés
1. `api/field_normalizer.php` ajouté : mapping centralisé FR/EN/variants -> canonical.
2. `api/submit_order.php` modifié pour appeler `normalize_input_fields($data)` avant toute validation, et maintenant stocke `latitude_depart`/`longitude_depart` dans `commandes` si présents.

Champs normalisés (extrait)
- adresse_depart: departure, pickup_address, adresse_depart
- adresse_arrivee: destination, dropoff_address, adresse_arrivee
- departure_lat: departure_lat, latitude_depart, lat_depart, lat_retrait, pickup_lat
- departure_lng: departure_lng, longitude_depart, lng_depart, lng_retrait, pickup_lng
- destination_lat/lng: destination_lat, latitude_arrivee, lat_arrivee, etc.
- telephone_expediteur: senderPhone, sender_phone, client_telephone
- telephone_destinataire: receiverPhone, receiver_phone

Champs obsolètes / nettoyés
- Nous avons supprimé les duplications dans `submit_order.php` (ancienne table de mapping inline) au profit de l'utilitaire centralisé.

Prochaines étapes recommandées
1. Harmoniser les `name`/`id` des formulaires front (fichier `sections_index/order_form.php` et variantes) pour utiliser les noms canoniques (`departure`, `departure_lat`, `departure_lng`, `senderPhone`, `receiverPhone`, `packageDesc`, etc.).
2. Ajouter des tests end-to-end (script `Tests/e2e_fullstack_runner.php`) pour poster une commande complète et vérifier insertion coords + affectation.

Notes
- Le normalizer ne fait pas d'actions invasives côté DB (il mappe les clés). La migration de colonnes pour les noms différents est gérée par `api/schema_utils.php`.
