<?php
// load_map_once.php - ensure Google Maps API loaded only once
static $mapLoaded = false;
if (!$mapLoaded) {
    $key = getenv('GOOGLE_MAPS_API_KEY');
    if (!$key && defined('GOOGLE_MAPS_API_KEY')) { $key = GOOGLE_MAPS_API_KEY; }
    echo '<script async defer src="https://maps.googleapis.com/maps/api/js?key=' . htmlspecialchars($key, ENT_QUOTES) . '&libraries=places&callback=initMap"></script>';
    $mapLoaded = true;
}
