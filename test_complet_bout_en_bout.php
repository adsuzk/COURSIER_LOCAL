<?php
/**
 * TEST COMPLET DE BOUT EN BOUT
 * Simulation d'une commande depuis l'index jusqu'au coursier
 */

require_once 'config.php';
require_once 'lib/coursier_presence.php';
require_once 'fcm_manager.php';

echo "=== TEST COMPLET DE BOUT EN BOUT ===\n\n";

$pdo = getDBConnection();
$fcm = new FCMManager();

// 1. État initial du système
echo "1. ÉTAT INITIAL DU SYSTÈME:\n";
$coursiersConnectes = getConnectedCouriers($pdo);
echo "   📊 Coursiers connectés: " . count($coursiersConnectes) . "\n";

if (empty($coursiersConnectes)) {
    echo "   ⚠️  AUCUN COURSIER CONNECTÉ - Test impossible\n";
    echo "   📱 Veuillez connecter l'app mobile d'un coursier\n";
    exit;
}

$coursier = $coursiersConnectes[0]; // Premier coursier disponible
echo "   ✅ Coursier sélectionné: {$coursier['nom']} {$coursier['prenoms']}\n";
echo "   💰 Solde wallet: " . number_format($coursier['solde_wallet'], 0) . " FCFA\n\n";

// 2. Simulation création commande depuis l'index
echo "2. CRÉATION COMMANDE DEPUIS INDEX:\n";

$commandeData = [
    'client_nom' => 'Client Test',
    'client_telephone' => '+225 07 12 34 56 78',
    'adresse_retrait' => '123 Rue de la République, Abidjan',
    'adresse_livraison' => '456 Avenue Houphouët-Boigny, Cocody',
    'description_colis' => 'Document important',
    'prix_total' => 2500,
    'code_commande' => 'CMD' . date('YmdHis'),
    'order_number' => 'ORD' . date('YmdHis')
];

try {
    $stmt = $pdo->prepare("
        INSERT INTO commandes (
            order_number, code_commande, client_nom, client_telephone, 
            adresse_retrait, adresse_livraison, description_colis, 
            prix_total, statut, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', NOW(), NOW())
    ");
    
    $stmt->execute([
        $commandeData['order_number'],
        $commandeData['code_commande'],
        $commandeData['client_nom'],
        $commandeData['client_telephone'], 
        $commandeData['adresse_retrait'],
        $commandeData['adresse_livraison'],
        $commandeData['description_colis'],
        $commandeData['prix_total']
    ]);
    
    $commandeId = $pdo->lastInsertId();
    echo "   ✅ Commande créée avec ID: {$commandeId}\n";
    echo "   🏷️  Code: {$commandeData['code_commande']}\n";
    echo "   📍 Enlèvement: {$commandeData['adresse_retrait']}\n";
    echo "   📍 Livraison: {$commandeData['adresse_livraison']}\n";
    echo "   💵 Frais: {$commandeData['prix_total']} FCFA\n\n";
    
} catch (Exception $e) {
    echo "   ❌ Erreur création commande: " . $e->getMessage() . "\n";
    exit;
}

// 3. Attribution automatique au coursier connecté
echo "3. ATTRIBUTION AU COURSIER:\n";

try {
    $stmt = $pdo->prepare("
        UPDATE commandes 
        SET coursier_id = ?, statut = 'assigne', updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$coursier['id'], $commandeId]);
    
    echo "   ✅ Commande attribuée à {$coursier['nom']} {$coursier['prenoms']}\n";
    echo "   🔄 Status: en_attente → assigne\n\n";
    
} catch (Exception $e) {
    echo "   ❌ Erreur attribution: " . $e->getMessage() . "\n";
    exit;
}

// 4. Envoi notification FCM au coursier
echo "4. ENVOI NOTIFICATION FCM:\n";

$notificationData = [
    'title' => '🚛 Nouvelle commande disponible',
    'body' => "Client: {$commandeData['client_nom']} - Frais: {$commandeData['prix_total']} FCFA",
    'data' => [
        'type' => 'new_order',
        'order_id' => $commandeId,
        'code_commande' => $commandeData['code_commande'],
        'pickup_address' => $commandeData['adresse_retrait'],
        'delivery_address' => $commandeData['adresse_livraison'],
        'fee' => $commandeData['prix_total'],
        'client_phone' => $commandeData['client_telephone']
    ]
];

// Utiliser la méthode correcte pour envoyer la notification
$resultFCM = $fcm->envoyerNotificationCommande($coursier['id'], [
    'id' => $commandeId,
    'code_commande' => $commandeData['code_commande'],
    'client_nom' => $commandeData['client_nom'],
    'adresse_retrait' => $commandeData['adresse_retrait'],
    'adresse_livraison' => $commandeData['adresse_livraison'],
    'prix_total' => $commandeData['prix_total'],
    'description_colis' => $commandeData['description_colis']
]);

if ($resultFCM['success']) {
    echo "   ✅ Notification FCM envoyée avec succès\n";
    echo "   📱 Tokens actifs: " . ($resultFCM['tokens_sent'] ?? 'N/A') . "\n";
    echo "   💬 Message: Nouvelle commande {$commandeData['code_commande']}\n\n";
} else {
    echo "   ⚠️  Notification FCM: " . ($resultFCM['error'] ?? 'Erreur inconnue') . "\n\n";
}

// 5. Simulation acceptation par le coursier (via API mobile)
echo "5. SIMULATION ACCEPTATION COURSIER:\n";
echo "   📱 En attente de l'acceptation sur l'app mobile...\n";
echo "   ⏱️  Le coursier doit maintenant ouvrir son app et accepter la commande\n\n";

// 6. Vérification timeline
echo "6. VÉRIFICATION TIMELINE COMMANDE:\n";

$stmt = $pdo->prepare("
    SELECT id, statut, created_at, heure_acceptation, 
           heure_retrait, heure_livraison, updated_at, code_commande
    FROM commandes 
    WHERE id = ?
");
$stmt->execute([$commandeId]);
$commande = $stmt->fetch(PDO::FETCH_ASSOC);

echo "   📋 ID Commande: {$commande['id']}\n";
echo "   🏷️  Code: {$commande['code_commande']}\n";
echo "   � Status actuel: {$commande['statut']}\n";
echo "   � Créée: {$commande['created_at']}\n";
echo "   🕒 Acceptée: " . ($commande['heure_acceptation'] ?? 'En attente...') . "\n";
echo "   🕓 Enlèvement: " . ($commande['heure_retrait'] ?? 'N/A') . "\n";
echo "   🕔 Livrée: " . ($commande['heure_livraison'] ?? 'N/A') . "\n\n";

echo "7. INSTRUCTIONS POUR POURSUIVRE LE TEST:\n";
echo "   📱 1. Ouvrir l'app mobile du coursier {$coursier['nom']}\n";
echo "   ✅ 2. Accepter la commande ID {$commandeId}\n";
echo "   📍 3. Marquer 'En route vers enlèvement'\n";
echo "   📦 4. Marquer 'Colis récupéré'\n";
echo "   🚚 5. Marquer 'En cours de livraison'\n";  
echo "   ✅ 6. Marquer 'Livré'\n\n";

echo "💡 POUR VÉRIFIER LA TIMELINE:\n";
echo "   🌐 Aller sur: https://localhost/COURSIER_LOCAL/index.php\n";
echo "   👁️  Chercher la commande ID {$commandeId}\n";
echo "   📊 Vérifier que les statuts se mettent à jour en temps réel\n\n";

echo "✅ TEST PRÉPARÉ - Le système est prêt pour validation complète!\n";
?>