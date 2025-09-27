<?php
// lib/geo_utils.php - utilitaires géographiques partagés

if (!function_exists('haversine')) {
    /**
     * Distance en kilomètres entre deux points géographiques
     * (lat, lon) -> (lat, lon) via la formule de Haversine.
     */
    function haversine($lat1, $lon1, $lat2, $lon2) {
        $R = 6371; // Rayon de la Terre en km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $R * $c;
    }
}

?>
