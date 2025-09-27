<?php
/**
 * Test rapide des fonctions reseau.php
 */

// Test simple de connectivité
function quickTest() {
    echo "=== TEST RESEAU SUZOSKY ===\n\n";
    
    // Test de quelques URLs de base
    $testUrls = [
        'http://localhost/COURSIER_LOCAL/api/agent_auth.php',
        'http://localhost/COURSIER_LOCAL/config.php',
        'http://localhost/COURSIER_LOCAL/index.php'
    ];
    
    foreach ($testUrls as $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        
        $start = microtime(true);
        $result = curl_exec($ch);
        $time = round((microtime(true) - $start) * 1000, 2);
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $status = ($httpCode == 200 || $httpCode == 302) ? "✅ ONLINE" : "❌ OFFLINE";
        echo "$status - $url (Code: $httpCode, Temps: {$time}ms)\n";
    }
    
    echo "\n=== FIN DU TEST ===\n";
}

quickTest();
?>