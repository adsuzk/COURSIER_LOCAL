<?php
/**
 * Script de test pour vérifier la configuration Google Maps
 */
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$googleMapsKey = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : (getenv('GOOGLE_MAPS_API_KEY') ?: '');

echo json_encode([
    'success' => true,
    'google_maps_key' => $googleMapsKey ?: null,
    'has_key' => !empty($googleMapsKey),
    'config_file' => __DIR__ . '/config.php'
]);
?>
