<?php
/**
 * Générateur de token FCM réaliste pour tests
 */

// Format typique d'un token FCM: 152-163 caractères, base64-like avec des caractères spéciaux
function generateRealisticFCMToken() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
    $token = '';
    
    // Première partie: ~152 caractères
    for ($i = 0; $i < 152; $i++) {
        $token .= $chars[rand(0, strlen($chars) - 1)];
    }
    
    // Séparateur typique
    $token .= ':APA91b';
    
    // Deuxième partie: ~10 caractères
    for ($i = 0; $i < 10; $i++) {
        $token .= $chars[rand(0, strlen($chars) - 1)];
    }
    
    return $token;
}

$realToken = generateRealisticFCMToken();
echo "Token FCM généré: " . $realToken . "\n";
echo "Longueur: " . strlen($realToken) . " caractères\n";
echo "\nURL d'enregistrement:\n";
echo "http://localhost/COURSIER_LOCAL/Tests/force_fcm_registration.php?coursier_id=5&token=" . urlencode($realToken) . "\n";
?>