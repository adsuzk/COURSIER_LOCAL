<?php
// Test API de paiement
echo "=== TEST API PAIEMENT ===\n";

// Simulation d'une requête POST
$_POST = [
    'adresse_depart' => 'Test Depart',
    'adresse_arrivee' => 'Test Arrivee',
    'nom_expediteur' => 'John',
    'prenoms_expediteur' => 'Doe',
    'telephone_expediteur' => '0123456789',
    'nom_destinataire' => 'Jane',
    'prenoms_destinataire' => 'Smith',
    'telephone_destinataire' => '0987654321',
    'description_colis' => 'Test package',
    'prix_estime' => '1500',
    'mode_paiement' => 'card'
];

echo "Données POST simulées ✓\n";

try {
    // Include API
    ob_start();
    include 'api/initiate_order_payment.php';
    $output = ob_get_clean();
    
    echo "Sortie API:\n";
    echo $output . "\n";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>