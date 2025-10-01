<?php
/**
 * GUARD: Prévention des commandes de test en production
 * 
 * Ce script peut être inclus au début des fichiers qui créent des commandes
 * pour bloquer la création de commandes de test
 */

function isTestOrder($codeCommande, $orderNumber = null) {
    // Patterns de commandes de test
    $testPatterns = [
        '/^T[A-Z0-9]{6}$/i',           // T265E67, TB7B307, etc.
        '/^TEST/i',                     // TEST20251001085525
        '/^TST/i',                      // TST085525754
        '/TEST-/i',                     // TEST-20251001040843
    ];
    
    foreach ($testPatterns as $pattern) {
        if (preg_match($pattern, $codeCommande)) {
            return true;
        }
        if ($orderNumber && preg_match($pattern, $orderNumber)) {
            return true;
        }
    }
    
    return false;
}

function isRealOrder($codeCommande) {
    // Les vraies commandes commencent par SZ ou SZK
    return preg_match('/^(SZ|SZK)/i', $codeCommande);
}

function blockTestOrderCreation($codeCommande, $orderNumber = null) {
    if (isTestOrder($codeCommande, $orderNumber)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'PRODUCTION_MODE',
            'message' => 'Les commandes de test sont bloquées en production. Utilisez uniquement l\'index pour créer des commandes réelles.',
            'code' => $codeCommande,
            'orderNumber' => $orderNumber
        ]);
        exit;
    }
}

// Exemple d'utilisation dans un script de création de commande:
/*
require_once __DIR__ . '/test_order_guard.php';

$codeCommande = $_POST['code_commande'] ?? '';
$orderNumber = $_POST['order_number'] ?? '';

// Bloquer si c'est une commande de test
blockTestOrderCreation($codeCommande, $orderNumber);

// Si on arrive ici, c'est une vraie commande
// ... continuer la création
*/
