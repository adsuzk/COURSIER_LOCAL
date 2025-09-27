<?php
// load_map_once.php - ensure Google Maps API loaded only once
static $mapLoaded = false;
if (!$mapLoaded) {
    echo '<script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBjUgj9KM0SNj847a_bIsf6chWp9L8Hr1A&libraries=places&callback=initMap"></script>';
    $mapLoaded = true;
}
