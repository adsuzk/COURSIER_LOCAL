<?php
echo "TEST API: ";
require_once 'config.php'; 
try { 
    $db = getDBConnection(); 
    echo "Connexion DB réussie ✓\n"; 
    
    // Test table commandes
    $stmt = $db->query("SELECT COUNT(*) FROM commandes");
    $count = $stmt->fetchColumn();
    echo "Nombre de commandes: $count\n";
    
    $db = null; 
} catch (Exception $e) { 
    echo "Erreur: " . $e->getMessage() . "\n"; 
}
?>