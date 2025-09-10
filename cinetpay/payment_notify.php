<?php
require_once __DIR__ . '/lib/util.php';
/**
 * CINETPAY PAYMENT NOTIFICATION HANDLER - SUZOSKY COURSIER V2.1
 * =============================================================
 * Gestion des notifications de paiement CINETPAY (IPN)
 */

// Charger la configuration et la connexion DB
// Charger l'intégration CinetPay et les fonctions de paiement
// Charger la configuration, la connexion DB et l'intégration CinetPay
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cinetpay_integration.php';

// Désactiver l'affichage des erreurs pour cette API
error_reporting(0);
ini_set('display_errors', 0);

// Headers pour API
header('Content-Type: application/json');
// Log notifications for debugging
$rawInput = file_get_contents('php://input');
logMessage('cinetpay_notification.log', 'CINETPAY Notification Raw Input: ' . $rawInput);
$postData = $_POST;
logMessage('cinetpay_notification.log', 'CINETPAY Notification POST Data: ' . json_encode($postData));

// Log des données reçues
$rawInput = file_get_contents('php://input');
$postData = $_POST;

// Existing rawInput and postData are already logged

try {
    // Vérifier que nous avons des données
    if (empty($postData)) {
        // Tenter de parser le raw input
        parse_str($rawInput, $postData);
    }
    
    if (empty($postData)) {
        throw new Exception('Aucune donnée de notification reçue');
    }
    
    // Vérifier les champs requis
    $requiredFields = ['cpm_site_id', 'cpm_trans_id', 'cpm_trans_date', 'cpm_amount', 'cpm_currency', 'cpm_result'];
    foreach ($requiredFields as $field) {
        if (!isset($postData[$field])) {
            throw new Exception("Champ requis manquant: {$field}");
        }
    }
    
    // Initialiser les tables si nécessaire
    createPaymentTables();
    
    // Traiter la notification
    $cinetpay = new SuzoskyCinetPayIntegration();
    $result = $cinetpay->handlePaymentNotification($postData);
    
    // Réponse selon le résultat
            if ($result['status'] === 'success') {
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Notification traitée avec succès'
        ]);
        
    // Log du succès
    logMessage('cinetpay_notification.log', 'Notification traitée avec succès - Transaction: ' . $postData['cpm_trans_id']);
        
    } else {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $result['message']
        ]);
        
    // Log de l'erreur
    logMessage('cinetpay_notification.log', 'Notification échouée - Transaction: ' . ($postData['cpm_trans_id'] ?? 'Inconnue') . ', Erreur: ' . $result['message']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    
    // Log de l'erreur critique
    logMessage('cinetpay_notification.log', 'Erreur critique: ' . $e->getMessage());
    logMessage('cinetpay_notification.log', 'Données: ' . json_encode($postData));
}

// Nettoyer les anciennes transactions échouées (30 jours)
try {
    $pdo = getDBConnection();
    $pdo->exec("DELETE FROM payment_transactions WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND status IN ('failed','cancelled')");
} catch (Exception $e) {
    error_log("Erreur nettoyage anciennes transactions: " . $e->getMessage());
}

exit;
?>
