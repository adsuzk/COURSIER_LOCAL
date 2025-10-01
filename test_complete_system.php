<?php
/**
 * 🧪 TEST COMPLET DU SYSTÈME DE COMMANDE
 * Simule une vraie commande client et vérifie la notification FCM
 */

session_start();

// Simuler une session client
$_SESSION['client_id'] = 999;
$_SESSION['client_email'] = 'test@suzosky.com';
$_SESSION['client_telephone'] = '+2250759123456';

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   🧪 TEST COMPLET: CRÉATION COMMANDE + NOTIFICATION FCM  ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Préparer les données de commande
$testCommande = [
    'adresse_depart' => 'Cocody Riviera Palmeraie, Abidjan',
    'adresse_arrivee' => 'Yopougon Siporex, Abidjan',
    'telephone_expediteur' => '+2250759123456',
    'telephone_destinataire' => '+2250777654321',
    'description_colis' => 'Test système FCM v1 - Colis de vérification',
    'priorite' => 'normal',
    'mode_paiement' => 'especes',
    'prix_estime' => 2500,
    'latitude_depart' => '5.3599517',
    'longitude_depart' => '-4.0082563',
    'latitude_arrivee' => '5.3367395',
    'longitude_arrivee' => '-4.0897324',
    'distance_estimee' => 8.5,
    'dimensions' => 'Petit colis (30x20x10 cm)',
    'poids_estime' => 2.5,
    'fragile' => 0
];

echo "📦 Données de la commande:\n";
echo "   Départ: {$testCommande['adresse_depart']}\n";
echo "   Arrivée: {$testCommande['adresse_arrivee']}\n";
echo "   Prix: {$testCommande['prix_estime']} FCFA\n";
echo "   Distance: {$testCommande['distance_estimee']} km\n\n";

// Simuler l'envoi via l'API
$_POST = $testCommande;
$_SERVER['REQUEST_METHOD'] = 'POST';

// Capturer la sortie de l'API
ob_start();
require __DIR__ . '/api/submit_order.php';
$apiResponse = ob_get_clean();

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   📡 RÉPONSE DE L'API                                     ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";

$response = json_decode($apiResponse, true);

if ($response && $response['success']) {
    echo "✅ Commande créée avec succès!\n";
    echo "   ID: {$response['commande_id']}\n";
    echo "   Code: {$response['commande']['code_commande']}\n\n";
    
    if (isset($response['assignation'])) {
        $assignation = $response['assignation'];
        
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║   📱 RÉSULTAT DE L'ATTRIBUTION                            ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n";
        
        if ($assignation['coursier_assigne'] ?? false) {
            echo "✅ Coursier assigné:\n";
            echo "   ID: {$assignation['coursier_id']}\n";
            echo "   Nom: {$assignation['coursier_nom']}\n";
            echo "   Matricule: {$assignation['matricule']}\n";
            echo "   Statut: {$assignation['statut_connexion']}\n";
            echo "   Notifications envoyées: {$assignation['notifications_envoyees']}\n\n";
            
            echo "🎉 SUCCÈS TOTAL!\n";
            echo "Le coursier devrait recevoir la notification maintenant!\n";
        } else {
            echo "⚠️  Commande créée mais aucun coursier assigné\n";
            echo "   Message: {$assignation['message']}\n";
            echo "   Coursiers trouvés: {$assignation['coursiers_trouves']}\n";
        }
        
        if (isset($assignation['diagnostic'])) {
            echo "\n📊 Diagnostic:\n";
            print_r($assignation['diagnostic']);
        }
    }
} else {
    echo "❌ Erreur lors de la création de la commande\n";
    echo $apiResponse . "\n";
}

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║   🔍 VÉRIFICATION DANS LA BASE DE DONNÉES                ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";

require_once __DIR__ . '/config.php';
$pdo = getDBConnection();

if (isset($response['commande_id'])) {
    $stmt = $pdo->prepare("
        SELECT c.*, a.nom as coursier_nom, a.telephone as coursier_tel
        FROM commandes c
        LEFT JOIN agents_suzosky a ON c.coursier_id = a.id
        WHERE c.id = ?
    ");
    $stmt->execute([$response['commande_id']]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($commande) {
        echo "✅ Commande trouvée dans la DB:\n";
        echo "   Statut: {$commande['statut']}\n";
        echo "   Coursier ID: " . ($commande['coursier_id'] ?? 'Non assigné') . "\n";
        echo "   Coursier: " . ($commande['coursier_nom'] ?? 'Aucun') . "\n";
        echo "   Créée: {$commande['created_at']}\n";
    }
}

// Vérifier les logs FCM récents
echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║   📝 DERNIERS LOGS FCM                                    ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";

$logFile = __DIR__ . '/diagnostic_logs/diagnostics_errors.log';
if (file_exists($logFile)) {
    $logs = file($logFile);
    $recentLogs = array_slice($logs, -10); // 10 dernières lignes
    
    foreach ($recentLogs as $log) {
        if (strpos($log, 'FCM') !== false || strpos($log, 'Notification') !== false) {
            echo $log;
        }
    }
} else {
    echo "⚠️  Fichier de logs non trouvé\n";
}

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║   ✅ TEST TERMINÉ                                         ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "🔔 Instructions finales:\n";
echo "1. Vérifiez l'application mobile du coursier\n";
echo "2. Une notification devrait apparaître avec les détails de la commande\n";
echo "3. Ouvrez le simulateur: http://localhost/COURSIER_LOCAL/simulateur_app_mobile.html\n";
echo "4. Entrez l'ID du coursier (5) pour voir les commandes en attente\n\n";
?>
