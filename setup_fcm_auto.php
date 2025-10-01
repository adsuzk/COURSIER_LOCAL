<?php
/**
 * SCRIPT DE CONFIGURATION AUTOMATIQUE FCM
 * Configure la clé serveur FCM depuis Firebase Console
 */

echo "=== CONFIGURATION AUTOMATIQUE FCM ===\n\n";

// Étape 1: Récupérer la clé serveur depuis Firebase
echo "📋 Instructions pour obtenir la clé FCM:\n";
echo "1. Allez sur https://console.firebase.google.com\n";
echo "2. Sélectionnez le projet: coursier-suzosky\n";
echo "3. ⚙️ Paramètres du projet > Cloud Messaging\n";
echo "4. Dans la section 'API Cloud Messaging (héritée)', copiez la 'Clé de serveur'\n\n";

echo "🔑 Entrez la clé serveur FCM (ou appuyez sur Entrée pour utiliser le polling): ";
$fcmKey = trim(fgets(STDIN));

if (empty($fcmKey)) {
    echo "\n⚠️ Aucune clé fournie. Le système utilisera le POLLING (1 seconde).\n";
    echo "L'application mobile doit implémenter le polling.\n";
    exit(0);
}

// Étape 2: Écrire la clé dans fcm_manager.php
$fcmManagerPath = __DIR__ . '/fcm_manager.php';
$content = file_get_contents($fcmManagerPath);

// Remplacer la ligne de retour dans getServerKey
$pattern = '/return getenv\(\'FCM_SERVER_KEY\'\) \?: \'LEGACY_KEY_NOT_CONFIGURED\';/';
$replacement = "return '$fcmKey'; // Clé configurée automatiquement le " . date('Y-m-d H:i:s');

$newContent = preg_replace($pattern, $replacement, $content);

if ($newContent === null || $newContent === $content) {
    echo "❌ Erreur: Impossible de modifier fcm_manager.php\n";
    echo "Modifiez manuellement la ligne 22 avec votre clé.\n";
    exit(1);
}

file_put_contents($fcmManagerPath, $newContent);

echo "✅ Clé FCM configurée dans fcm_manager.php\n\n";

// Étape 3: Tester l'envoi
echo "🧪 Test d'envoi de notification...\n";

require_once 'config.php';
require_once 'fcm_manager.php';

$pdo = getDBConnection();

// Récupérer le token du coursier 5
$stmt = $pdo->query("SELECT token FROM device_tokens WHERE coursier_id = 5 AND is_active = 1 LIMIT 1");
$tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tokenData) {
    echo "❌ Aucun token actif trouvé pour le coursier #5\n";
    exit(1);
}

$fcm = new FCMManager();
$result = $fcm->envoyerNotification(
    $tokenData['token'],
    '🎉 Test FCM Configuré!',
    'Si vous recevez ce message, FCM fonctionne correctement!',
    ['type' => 'test', 'timestamp' => time()]
);

if ($result['success']) {
    echo "✅ NOTIFICATION ENVOYÉE AVEC SUCCÈS!\n";
    echo "📱 Vérifiez le téléphone du coursier.\n\n";
} else {
    echo "❌ ÉCHEC: {$result['message']}\n";
    echo "HTTP Code: {$result['http_code']}\n";
    if (isset($result['response'])) {
        echo "Réponse: {$result['response']}\n";
    }
}

echo "\n✅ CONFIGURATION TERMINÉE\n";
?>
