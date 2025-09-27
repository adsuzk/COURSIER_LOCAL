<?php
/**
 * API NETWORK - TEST ENDPOINT COURSIER
 * Test micro-service pour les données coursier
 */

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');

function testCoursierEndpoint() {
    try {
        $pdo = getDBConnection();
        
        // Test connexion base
        $pdo->query('SELECT 1');
        
        // Compter coursiers
        $stmt = $pdo->query('SELECT COUNT(*) FROM agents_suzosky');
        $totalCoursiers = $stmt->fetchColumn();
        
        // Test avec un coursier sample
        $stmt = $pdo->query('SELECT id, nom, prenoms, solde_wallet FROM agents_suzosky LIMIT 1');
        $sampleCoursier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'service' => 'coursier_endpoint',
            'status' => 'online',
            'data' => [
                'total_coursiers' => $totalCoursiers,
                'sample_coursier' => $sampleCoursier,
                'database_connection' => 'ok',
                'endpoint_url' => '/api/get_coursier_data.php'
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'service' => 'coursier_endpoint', 
            'status' => 'error',
            'error' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

echo json_encode(testCoursierEndpoint(), JSON_PRETTY_PRINT);
?>