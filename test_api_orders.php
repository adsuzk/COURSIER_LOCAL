<?php
// Test rapide de l'API des commandes coursier
$response = file_get_contents('http://localhost/COURSIER_LOCAL/api/get_coursier_orders.php?coursier_id=5&limit=3');
$data = json_decode($response, true);

echo "=== RÉPONSE API COMMANDES COURSIER #5 ===\n\n";

if ($data['success']) {
    echo "✅ API fonctionne\n";
    echo "Coursier: {$data['data']['coursier']['nom']} (ID: {$data['data']['coursier']['id']})\n";
    echo "Nombre de commandes: " . count($data['data']['commandes']) . "\n\n";
    
    echo "=== COMMANDES ===\n";
    foreach ($data['data']['commandes'] as $commande) {
        echo "#{$commande['id']} - {$commande['client_nom']}\n";
        echo "  📍 {$commande['adresse_depart']} → {$commande['adresse_arrivee']}\n";
        echo "  💰 {$commande['montant_total']} FCFA - Statut: {$commande['statut']}\n";
        echo "  📞 {$commande['client_telephone']}\n";
        echo "  ⏰ {$commande['date_creation']}\n\n";
    }
    
    echo "=== PAGINATION ===\n";
    echo "Total: {$data['data']['pagination']['total']}\n";
    echo "Limit: {$data['data']['pagination']['limit']}\n\n";
    
} else {
    echo "❌ Erreur: " . ($data['error'] ?? 'Unknown') . "\n";
    echo "Message: " . ($data['message'] ?? 'No message') . "\n";
}
?>