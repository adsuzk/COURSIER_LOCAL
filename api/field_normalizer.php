<?php
/**
 * Field normalizer: centralise la normalisation des clés entrantes (FR/EN/variantes)
 * Usage: require_once __DIR__ . '/field_normalizer.php'; normalize_input_fields($data);
 */

if (!function_exists('normalize_input_fields')) {
    function normalize_input_fields(array &$data): void
    {
        if (!is_array($data)) return;

        // Mapping: canonical => variants
        $map = [
            // Addresses / coordinates
            'adresse_depart' => ['departure', 'adresse_depart', 'pickup_address', 'pickup', 'from_address'],
            'adresse_arrivee' => ['destination', 'adresse_arrivee', 'dropoff_address', 'to_address'],
            'departure_lat' => ['departure_lat', 'latitude_depart', 'lat_depart', 'latitude_retrait', 'lat_retrait', 'lat_pickup', 'pickup_lat', 'pickup_latitude'],
            'departure_lng' => ['departure_lng', 'longitude_depart', 'lng_depart', 'longitude_retrait', 'lng_retrait', 'lng_pickup', 'pickup_lng', 'pickup_longitude'],
            'destination_lat' => ['destination_lat', 'latitude_arrivee', 'lat_arrivee', 'latitude_livraison', 'lat_livraison', 'drop_lat', 'drop_latitude'],
            'destination_lng' => ['destination_lng', 'longitude_arrivee', 'lng_arrivee', 'longitude_livraison', 'lng_livraison', 'drop_lng', 'drop_longitude'],

            // Phones
            'telephone_expediteur' => ['senderPhone', 'sender_phone', 'telephone_expediteur', 'client_telephone', 'sender'],
            'telephone_destinataire' => ['receiverPhone', 'receiver_phone', 'telephone_destinataire', 'recipient_phone', 'receiver'],

            // Package
            'description_colis' => ['packageDescription', 'packageDesc', 'package_description', 'description_colis', 'packageDesc'],

            // Payment / misc
            'priorite' => ['priority', 'priorite'],
            'mode_paiement' => ['paymentMethod', 'payment_method', 'mode_paiement'],
            'prix_estime' => ['price', 'prix_estime'],
            'distance_estimee' => ['distance', 'distance_estimee'],
            'poids_estime' => ['weight', 'poids_estime', 'poids'],
            'fragile' => ['fragile'],
        ];

        // Reverse lookup: variant lowercased -> canonical
        $lookup = [];
        foreach ($map as $canon => $variants) {
            foreach ($variants as $v) {
                $lookup[strtolower($v)] = $canon;
            }
        }

        // Walk through provided keys and map to canonical
        $new = $data; // start with original, allow overwrites
        foreach ($data as $k => $v) {
            $lk = strtolower($k);
            if (isset($lookup[$lk])) {
                $canon = $lookup[$lk];
                // don't override an already set canonical value unless empty
                if (!isset($new[$canon]) || $new[$canon] === '') {
                    $new[$canon] = $v;
                }
            }
        }

        // Also ensure coordinate canonical names exist as departure_lat/lng
        // If we have adresse_depart coordinates named differently, keep both canonical names
        $data = $new;
    }
}

?>
