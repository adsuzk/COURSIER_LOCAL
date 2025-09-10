<?php
// api/assign_courier.php - Assigne le coursier le plus proche pour une commande
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../coursier.php'; // contient assignNearestCourier()
// Démarrer session pour récupérer user session si besoin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
// Récupérer le JSON envoyé
$input = json_decode(file_get_contents('php://input'), true);
$pickup = $input['pickup'] ?? null;
if (!$pickup || !isset($pickup['lat'], $pickup['lng'])) {
    echo json_encode(['success' => false, 'error' => 'Coordonnées de départ manquantes']);
    exit;
}
// Appeler la fonction d'assignation
$courier = assignNearestCourier($pickup['lat'], $pickup['lng']);
if ($courier) {
    echo json_encode(['success' => true, 'courier' => $courier]);
} else {
    echo json_encode(['success' => false, 'error' => 'Aucun coursier disponible']);
}
