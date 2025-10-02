<?php
/**
 * Script d'initialisation de la table config_tarification
 * Permet de stocker l'historique des taux de commission et frais
 */

require_once __DIR__ . '/config.php';

$pdo = getPDO();

try {
    // Créer la table si elle n'existe pas
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS config_tarification (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date_application DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            taux_commission DECIMAL(5,2) NOT NULL DEFAULT 15.00 COMMENT 'Commission Suzosky en %',
            frais_plateforme DECIMAL(5,2) NOT NULL DEFAULT 5.00 COMMENT 'Frais de plateforme en %',
            frais_publicitaires DECIMAL(5,2) NOT NULL DEFAULT 3.00 COMMENT 'Frais publicitaires en %',
            prix_kilometre INT NOT NULL DEFAULT 100 COMMENT 'Prix par kilomètre en FCFA',
            frais_base INT NOT NULL DEFAULT 500 COMMENT 'Frais de base en FCFA',
            supp_km_rate INT NOT NULL DEFAULT 100 COMMENT 'Supplément par km après destination en FCFA',
            supp_km_free_allowance DECIMAL(5,2) NOT NULL DEFAULT 0.5 COMMENT 'Km gratuits après destination',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_date_application (date_application)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Historique des configurations de tarification'
    ");
    
    // Vérifier si la table est vide
    $count = $pdo->query("SELECT COUNT(*) FROM config_tarification")->fetchColumn();
    
    if ($count == 0) {
        // Insérer la configuration initiale par défaut
        $pdo->exec("
            INSERT INTO config_tarification (
                date_application,
                taux_commission,
                frais_plateforme,
                frais_publicitaires,
                prix_kilometre,
                frais_base,
                supp_km_rate,
                supp_km_free_allowance
            ) VALUES (
                NOW(),
                15.00,
                5.00,
                3.00,
                100,
                500,
                100,
                0.5
            )
        ");
        
        echo "✅ Table config_tarification créée et initialisée avec succès !\n";
    } else {
        echo "✅ Table config_tarification existe déjà avec $count entrées.\n";
    }
    
    // Vérifier que la table tarification existe et contient des données
    $tarificationExists = $pdo->query("SHOW TABLES LIKE 'tarification'")->rowCount() > 0;
    
    if ($tarificationExists) {
        // Synchroniser les valeurs actuelles de la table tarification si elles diffèrent
        $currentTarif = $pdo->query("SELECT * FROM tarification LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $latestConfig = $pdo->query("SELECT * FROM config_tarification ORDER BY date_application DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        
        if ($currentTarif && $latestConfig) {
            $needsUpdate = false;
            
            // Comparer les valeurs
            if ((float)$currentTarif['taux_commission'] != (float)$latestConfig['taux_commission'] ||
                (float)$currentTarif['frais_plateforme'] != (float)$latestConfig['frais_plateforme'] ||
                (float)($currentTarif['frais_publicitaires'] ?? 0) != (float)$latestConfig['frais_publicitaires'] ||
                (int)$currentTarif['prix_kilometre'] != (int)$latestConfig['prix_kilometre']) {
                $needsUpdate = true;
            }
            
            if ($needsUpdate) {
                // Insérer une nouvelle entrée avec les valeurs actuelles
                $stmt = $pdo->prepare("
                    INSERT INTO config_tarification (
                        date_application,
                        taux_commission,
                        frais_plateforme,
                        frais_publicitaires,
                        prix_kilometre,
                        frais_base,
                        supp_km_rate,
                        supp_km_free_allowance
                    ) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    (float)$currentTarif['taux_commission'],
                    (float)$currentTarif['frais_plateforme'],
                    (float)($currentTarif['frais_publicitaires'] ?? 3),
                    (int)$currentTarif['prix_kilometre'],
                    (int)$currentTarif['frais_base'],
                    (int)$currentTarif['supp_km_rate'],
                    (float)$currentTarif['supp_km_free_allowance']
                ]);
                
                echo "✅ Configuration synchronisée avec la table tarification\n";
            }
        }
    }
    
    echo "\n📊 Historique des configurations :\n";
    echo str_repeat("-", 80) . "\n";
    
    $configs = $pdo->query("
        SELECT 
            DATE_FORMAT(date_application, '%d/%m/%Y %H:%i') as date,
            taux_commission,
            frais_plateforme,
            frais_publicitaires,
            prix_kilometre
        FROM config_tarification
        ORDER BY date_application DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($configs as $cfg) {
        echo sprintf(
            "%s | Commission: %s%% | Plateforme: %s%% | Pub: %s%% | Prix/km: %s FCFA\n",
            $cfg['date'],
            $cfg['taux_commission'],
            $cfg['frais_plateforme'],
            $cfg['frais_publicitaires'],
            $cfg['prix_kilometre']
        );
    }
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✨ Initialisation terminée avec succès !\n";
?>
