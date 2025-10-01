<?php
/**
 * 🚀 MIGRATION FINALE: ACTIVATION FCM v1 DANS TOUT LE SYSTÈME
 * Ce script remplace l'ancien FCM par FCM v1 partout
 */

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   🚀 MIGRATION FCM v1 - SYSTÈME SUZOSKY COURSIER         ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$files = [
    'api/submit_order.php',
    'admin/sections_finances/rechargement_direct.php',
    'mobile_sync_api.php',
    'coursier.php',
    'admin.php'
];

$replacements = [
    "require_once __DIR__ . '/../fcm_manager.php'" => "require_once __DIR__ . '/../fcm_v1_manager.php'",
    "require_once 'fcm_manager.php'" => "require_once 'fcm_v1_manager.php'",
    "require_once __DIR__ . '/../../fcm_manager.php'" => "require_once __DIR__ . '/../../fcm_v1_manager.php'",
    'new FCMManager()' => 'new FCMv1Manager()',
    '$fcm = new FCMManager' => '$fcm = new FCMv1Manager',
    '$fcmManager = new FCMManager' => '$fcmManager = new FCMv1Manager'
];

$filesUpdated = 0;
$totalReplacements = 0;

foreach ($files as $file) {
    $filePath = __DIR__ . '/' . $file;
    
    if (!file_exists($filePath)) {
        echo "⚠️  Fichier non trouvé: $file\n";
        continue;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    $fileReplacements = 0;
    
    foreach ($replacements as $old => $new) {
        $count = 0;
        $content = str_replace($old, $new, $content, $count);
        if ($count > 0) {
            $fileReplacements += $count;
            $totalReplacements += $count;
        }
    }
    
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        echo "✅ Mis à jour: $file ($fileReplacements remplacements)\n";
        $filesUpdated++;
    } else {
        echo "ℹ️  Aucun changement: $file\n";
    }
}

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║   📊 RÉSUMÉ DE LA MIGRATION                               ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "Fichiers mis à jour: $filesUpdated\n";
echo "Remplacements effectués: $totalReplacements\n\n";

// Test de connexion FCM v1
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   🧪 TEST DE CONNEXION FCM v1                             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";

require_once __DIR__ . '/fcm_v1_manager.php';
require_once __DIR__ . '/config.php';

try {
    $fcm = new FCMv1Manager();
    $testResult = $fcm->testerConnexion();
    
    if ($testResult['success']) {
        echo "✅ Connexion Firebase réussie!\n";
        echo "   Project ID: {$testResult['project_id']}\n";
        echo "   Token expire: {$testResult['expiration']}\n\n";
        
        // Test d'envoi réel
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║   📱 TEST D'ENVOI DE NOTIFICATION                         ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n";
        
        $pdo = getDBConnection();
        $stmt = $pdo->query("
            SELECT dt.token, a.id, a.nom, a.telephone
            FROM device_tokens dt
            JOIN agents_suzosky a ON dt.coursier_id = a.id
            WHERE dt.is_active = 1
            ORDER BY a.last_login_at DESC
            LIMIT 1
        ");
        
        $coursier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($coursier) {
            echo "Envoi à: {$coursier['nom']} (ID: {$coursier['id']})\n";
            
            $result = $fcm->envoyerNotification(
                $coursier['token'],
                '🎉 Système FCM v1 Activé!',
                'Les notifications sont maintenant opérationnelles! Vous recevrez toutes les nouvelles commandes en temps réel.',
                [
                    'type' => 'system_upgrade',
                    'version' => 'fcm_v1',
                    'timestamp' => time()
                ]
            );
            
            if ($result['success']) {
                echo "✅ Notification envoyée avec succès!\n";
                echo "   Message ID: " . ($result['response']['name'] ?? 'N/A') . "\n";
            } else {
                echo "❌ Échec de l'envoi: {$result['message']}\n";
            }
        } else {
            echo "⚠️  Aucun coursier avec token FCM trouvé pour le test\n";
        }
        
    } else {
        echo "❌ Erreur de connexion: {$testResult['message']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║   ✅ MIGRATION TERMINÉE                                   ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n🚀 Le système est maintenant prêt à recevoir et distribuer les commandes!\n";
echo "📱 Les coursiers recevront les notifications en temps réel.\n\n";
?>
