<?php
// setup_database.php - Script pour initialiser la base de données
require_once 'config.php';

echo "🚀 Initialisation de la base de données Suzosky Coursier...\n\n";

try {
    // Connexion à la base de données
    $pdo = getDBConnection();
    echo "✅ Connexion à la base de données réussie\n";
    
    // Lire le fichier SQL
    $sqlFile = __DIR__ . '/database_setup.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Le fichier database_setup.sql n'existe pas");
    }
    
    $sql = file_get_contents($sqlFile);
    echo "✅ Fichier SQL chargé\n";
    
    // Diviser en requêtes individuelles
    $queries = array_filter(
        array_map('trim', explode(';', $sql)),
        function($query) {
            return !empty($query) && !preg_match('/^\s*--/', $query);
        }
    );
    
    echo "📊 " . count($queries) . " requêtes SQL à exécuter\n\n";
    
    // Exécuter chaque requête
    $pdo->beginTransaction();
    
    foreach ($queries as $index => $query) {
        if (trim($query)) {
            try {
                $pdo->exec($query);
                echo "✅ Requête " . ($index + 1) . " exécutée avec succès\n";
            } catch (PDOException $e) {
                echo "⚠️  Requête " . ($index + 1) . " - " . $e->getMessage() . "\n";
            }
        }
    }
    
    $pdo->commit();
    echo "\n🎉 Base de données initialisée avec succès !\n\n";
    
    // Vérifier les tables créées
    echo "📋 Vérification des tables créées :\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        if (in_array($table, ['clients_particuliers', 'commandes', 'logs_activites', 'business_clients'])) {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "   ✅ $table ($count enregistrements)\n";
        }
    }
    
    echo "\n💡 La base de données est prête pour recevoir les commandes depuis index.html\n";
    echo "💡 Les clients seront visibles dans admin.php > Gestion des clients > Clients Particuliers\n\n";
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
?>
