<?php
/**
 * 🚨 CRÉER UNE COMMANDE DE TEST RAPIDE
 * Pour tester le modal et le son de notification
 */

session_start();
$_SESSION['client_id'] = 999;
$_SESSION['client_email'] = 'test@suzosky.com';
$_SESSION['client_telephone'] = '+2250759123456';

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   🚨 CRÉATION COMMANDE TEST - MODAL + SON                ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$testCommande = [
    'adresse_depart' => 'Test Départ - Zone Plateau, Abidjan',
    'adresse_arrivee' => 'Test Arrivée - Marcory Zone 4, Abidjan',
    'telephone_expediteur' => '+2250709876543',
    'telephone_destinataire' => '+2250777888999',
    'description_colis' => '🎁 Test notification avec modal popup et son!',
    'priorite' => 'urgent',
    'mode_paiement' => 'especes',
    'prix_estime' => 3500,
    'latitude_depart' => '5.3200',
    'longitude_depart' => '-4.0100',
    'latitude_arrivee' => '5.2800',
    'longitude_arrivee' => '-3.9800',
    'distance_estimee' => 6.2,
    'dimensions' => 'Moyen (40x30x20 cm)',
    'poids_estime' => 3.5,
    'fragile' => 1
];

$_POST = $testCommande;
$_SERVER['REQUEST_METHOD'] = 'POST';

ob_start();
require __DIR__ . '/api/submit_order.php';
$apiResponse = ob_get_clean();

$response = json_decode($apiResponse, true);

if ($response && $response['success']) {
    echo "✅ COMMANDE CRÉÉE!\n";
    echo "   ID: {$response['commande_id']}\n";
    echo "   Code: {$response['commande']['code_commande']}\n\n";
    
    echo "🎯 ACTION REQUISE:\n";
    echo "1. Regardez le simulateur dans votre navigateur\n";
    echo "2. Un MODAL devrait s'afficher avec les détails\n";
    echo "3. Vous devriez entendre 3 BIPS de notification\n";
    echo "4. Le téléphone devrait 'vibrer' visuellement\n";
    echo "5. Une notification Windows devrait apparaître\n\n";
    
    if (isset($response['assignation'])) {
        $assignation = $response['assignation'];
        if ($assignation['coursier_assigne'] ?? false) {
            echo "✅ Assigné au coursier: {$assignation['coursier_nom']}\n";
            echo "📊 Notifications envoyées: {$assignation['notifications_envoyees']}\n";
        }
    }
    
    echo "\n🔊 Si vous n'entendez RIEN:\n";
    echo "   - Vérifiez le volume de votre PC\n";
    echo "   - Cliquez dans la page du simulateur (pour activer l'audio)\n";
    echo "   - Rechargez la page et recréez une commande\n\n";
    
} else {
    echo "❌ ERREUR:\n";
    print_r($response);
}

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   ✅ TEST TERMINÉ                                         ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
?>
