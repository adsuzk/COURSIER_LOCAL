<?php
/**
 * Script d'initialisation automatique des tables emails
 * Exécuté automatiquement au premier chargement de la section emails
 */

function initEmailTables($pdo) {
    try {
        // Vérifier si les tables existent déjà
        $tablesExist = $pdo->query("SHOW TABLES LIKE 'email_logs'")->rowCount() > 0;
        
        if ($tablesExist) {
            return ['success' => true, 'message' => 'Tables déjà initialisées'];
        }
        
        // Lire le fichier SQL
        $sqlFile = __DIR__ . '/sql/create_email_tables.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("Fichier SQL introuvable: $sqlFile");
        }
        
        $sql = file_get_contents($sqlFile);
        
        // Séparer les requêtes SQL (par point-virgule)
        $queries = array_filter(
            array_map('trim', explode(';', $sql)),
            function($query) {
                // Ignorer les commentaires et lignes vides
                return !empty($query) && 
                       !preg_match('/^--/', $query) && 
                       !preg_match('/^\/\*/', $query);
            }
        );
        
        // Exécuter chaque requête
        $pdo->beginTransaction();
        
        foreach ($queries as $query) {
            if (!empty(trim($query))) {
                $pdo->exec($query);
            }
        }
        
        $pdo->commit();
        
        return [
            'success' => true, 
            'message' => 'Tables créées avec succès (' . count($queries) . ' requêtes exécutées)'
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        return [
            'success' => false,
            'message' => 'Erreur lors de l\'initialisation: ' . $e->getMessage()
        ];
    }
}

// Auto-initialisation si appelé depuis emails_v2.php
if (!function_exists('getPDO')) {
    require_once __DIR__ . '/../config.php';
}

try {
    $pdo = getPDO();
    $result = initEmailTables($pdo);
    
    if (!$result['success']) {
        error_log('[EMAIL INIT] ' . $result['message']);
    }
} catch (Exception $e) {
    error_log('[EMAIL INIT ERROR] ' . $e->getMessage());
}
