<?php
// Test API de paiement avec simulation complète
echo "=== TEST API PAIEMENT COMPLET ===\n";

// Simulation environnement HTTP
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'off';

$_POST = [
    'adresse_depart' => 'Test Depart Abidjan',
    'adresse_arrivee' => 'Test Arrivee Cocody',
    'nom_expediteur' => 'John',
    'prenoms_expediteur' => 'Doe',
    'telephone_expediteur' => '0123456789',
    'nom_destinataire' => 'Jane',
    'prenoms_destinataire' => 'Smith',
    'telephone_destinataire' => '0987654321',
    'description_colis' => 'Test package important',
    'prix_estime' => '1500',
    'mode_paiement' => 'card'
];

echo "Données simulées : POST + Serveur ✓\n";
echo "Prix estimé : " . $_POST['prix_estime'] . " FCFA\n";
echo "Mode paiement : " . $_POST['mode_paiement'] . "\n";

try {
    // Capture la sortie de l'API
    ob_start();
    include 'api/initiate_order_payment.php';
    $apiOutput = ob_get_clean();
    
    echo "\n=== RÉPONSE API ===\n";
    echo $apiOutput . "\n";
    
    // Vérifier si c'est du JSON valide
    $jsonResponse = json_decode($apiOutput, true);
    if ($jsonResponse) {
        echo "\n=== ANALYSE RÉPONSE ===\n";
        echo "Success: " . ($jsonResponse['success'] ? 'OUI' : 'NON') . "\n";
        if (isset($jsonResponse['payment_url'])) {
            echo "URL de paiement générée ✓\n";
            echo "URL: " . $jsonResponse['payment_url'] . "\n";
        }
        if (isset($jsonResponse['message'])) {
            echo "Message: " . $jsonResponse['message'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>