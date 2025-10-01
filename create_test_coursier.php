<?php
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=coursier_local;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "=== CRÉATION COURSIER TEST (ID=5) ===\n";
    
    // Créer le coursier manquant avec ID=5 qui correspond aux device_tokens
    $stmt = $pdo->prepare("
        INSERT INTO coursiers (
            id, nom, telephone, email, adresse, type_coursier, statut, 
            disponible, vehicule_type, password_hash, created_at
        ) VALUES (5, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        'ZALLE Ismael',
        '+225 07 08 09 10 11', 
        'ismael.zalle@test.com',
        'Abidjan, Cocody',
        'interne',
        'actif',
        1, // disponible
        'moto',
        password_hash('test123', PASSWORD_DEFAULT)
    ]);
    
    echo "✅ Coursier créé avec ID: 5\n";
    
    // Vérifier la création
    $stmt = $pdo->query("SELECT id, nom, telephone, statut, disponible FROM coursiers WHERE id = 5");
    $coursier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($coursier) {
        echo "✅ Coursier vérifié: {$coursier['nom']} (statut: {$coursier['statut']}, dispo: {$coursier['disponible']})\n";
        
        // Vérifier les device tokens actifs
        echo "\n=== DEVICE TOKENS ACTIFS ===\n";
        $stmt = $pdo->query("SELECT token, last_ping, is_active FROM device_tokens WHERE coursier_id = 5 ORDER BY last_ping DESC LIMIT 3");
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $token_display = substr($row['token'], 0, 30) . "...";
            echo "Token: $token_display - Ping: {$row['last_ping']} - Actif: {$row['is_active']}\n";
        }
        
    } else {
        echo "❌ Erreur lors de la création du coursier\n";
    }

} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>