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
        SET coursier_id = ?, status = 'assigned', assigned_at = NOW(), updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$coursier['id'], $commandeId]);
    
    echo "   ✅ Commande attribuée à {$coursier['nom']} {$coursier['prenoms']}\n";
    echo "   🔄 Status: pending → assigned\n\n";
    
} catch (Exception $e) {
    echo "   ❌ Erreur attribution: " . $e->getMessage() . "\n";
    exit;
}

// 4. Envoi notification FCM au coursier
echo "4. ENVOI NOTIFICATION FCM:\n";

$notificationData = [
    'title' => '🚛 Nouvelle commande disponible',
    'body' => "Client: {$commandeData['client_name']} - Frais: {$commandeData['delivery_fee']} FCFA",
    'data' => [
        'type' => 'new_order',
        'order_id' => $commandeId,
        'pickup_address' => $commandeData['pickup_address'],
        'delivery_address' => $commandeData['delivery_address'],
        'fee' => $commandeData['delivery_fee'],
        'client_phone' => $commandeData['client_phone']
    ]
];

$resultFCM = $fcm->sendToCoursier($coursier['id'], $notificationData);

if ($resultFCM['success']) {
    echo "   ✅ Notification FCM envoyée avec succès\n";
    echo "   📱 Tokens actifs: {$resultFCM['tokens_sent']}\n";
    echo "   💬 Message: {$notificationData['body']}\n\n";
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
    SELECT id, status, created_at, assigned_at, accepted_at, 
           pickup_at, delivered_at, updated_at
    FROM commandes 
    WHERE id = ?
");
$stmt->execute([$commandeId]);
$commande = $stmt->fetch(PDO::FETCH_ASSOC);

echo "   📋 ID Commande: {$commande['id']}\n";
echo "   🔄 Status actuel: {$commande['status']}\n";
echo "   🕐 Créée: {$commande['created_at']}\n";
echo "   🕑 Attribuée: " . ($commande['assigned_at'] ?? 'N/A') . "\n";
echo "   🕒 Acceptée: " . ($commande['accepted_at'] ?? 'En attente...') . "\n";
echo "   🕓 Enlèvement: " . ($commande['pickup_at'] ?? 'N/A') . "\n";
echo "   🕔 Livrée: " . ($commande['delivered_at'] ?? 'N/A') . "\n\n";

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