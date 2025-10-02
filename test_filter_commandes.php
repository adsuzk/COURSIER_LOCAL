<?php
require_once 'config.php';

echo "=== TEST GET COURSIER ORDERS (filtrage termin\u00e9es) ===\n\n";

$coursier_id = 5;
$url = "http://localhost/COURSIER_LOCAL/api/get_coursier_orders_simple.php?coursier_id=$coursier_id&limit=10";

echo "URL: $url\n\n";

$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['success']) {
    echo "Succ\u00e8s! Commandes retourn\u00e9es: " . count($data['data']['commandes']) . "\n\n";
    
    echo "Liste des commandes:\n";
    foreach ($data['data']['commandes'] as $cmd) {
        echo "  ID: {$cmd['id']} | Statut: {$cmd['statut']} | Client: {$cmd['clientNom']}\n";
    }
    
    // V\u00e9rifier que la commande 123 n'est PAS pr\u00e9sente
    $has123 = false;
    foreach ($data['data']['commandes'] as $cmd) {
        if ($cmd['id'] == 123) {
            $has123 = true;
            break;
        }
    }
    
    echo "\n";
    if ($has123) {
        echo "\u274c ERREUR: La commande 123 (termin\u00e9e) est encore retourn\u00e9e!\n";
    } else {
        echo "\u2705 CORRECT: La commande 123 (termin\u00e9e) n'est plus retourn\u00e9e\n";
    }
} else {
    echo "\u274c Erreur API: " . ($data['error'] ?? 'Inconnue') . "\n";
}
