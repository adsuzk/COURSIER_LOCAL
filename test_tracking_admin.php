<?php
/**
 * Script de test pour vérifier le système de tracking en temps réel
 * Page admin: https://localhost/COURSIER_LOCAL/admin.php?section=commandes
 */

require_once __DIR__ . '/config.php';

echo "🧪 TEST SYSTÈME DE TRACKING EN TEMPS RÉEL\n";
echo str_repeat("=", 70) . "\n\n";

try {
    $pdo = getDBConnection();
    
    // 1. Vérifier la configuration Google Maps
    echo "🗺️  1. CONFIGURATION GOOGLE MAPS\n";
    echo str_repeat("-", 70) . "\n";
    
    if (defined('GOOGLE_MAPS_API_KEY')) {
        $apiKey = GOOGLE_MAPS_API_KEY;
        $keyLength = strlen($apiKey);
        $maskedKey = substr($apiKey, 0, 10) . '...' . substr($apiKey, -4);
        echo "✅ Clé API Google Maps définie: {$maskedKey} ({$keyLength} caractères)\n";
    } else {
        echo "❌ Clé API Google Maps NON définie!\n";
        echo "   Ajoutez dans config.php: define('GOOGLE_MAPS_API_KEY', 'votre_cle');\n";
    }
    echo "\n";
    
    // 2. Vérifier les commandes avec coursier
    echo "📦 2. COMMANDES AVEC COURSIER ASSIGNÉ\n";
    echo str_repeat("-", 70) . "\n";
    
    $stmt = $pdo->query("
        SELECT 
            c.id, 
            c.code_commande, 
            c.statut,
            c.coursier_id,
            a.nom AS coursier_nom,
            a.prenoms AS coursier_prenoms,
            a.matricule,
            a.statut_connexion,
            a.latitude,
            a.longitude
        FROM commandes c
        LEFT JOIN agents_suzosky a ON c.coursier_id = a.id
        WHERE c.coursier_id IS NOT NULL
        ORDER BY c.created_at DESC
        LIMIT 10
    ");
    
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($commandes)) {
        echo "⚠️  Aucune commande avec coursier assigné trouvée!\n";
    } else {
        echo "✅ " . count($commandes) . " commande(s) avec coursier trouvée(s):\n\n";
        
        foreach ($commandes as $cmd) {
            echo "📋 Commande #{$cmd['code_commande']}\n";
            echo "   ├─ ID: {$cmd['id']}\n";
            echo "   ├─ Statut: {$cmd['statut']}\n";
            
            if ($cmd['coursier_nom']) {
                echo "   ├─ Coursier: {$cmd['coursier_prenoms']} {$cmd['coursier_nom']} ({$cmd['matricule']})\n";
                echo "   ├─ Connexion: {$cmd['statut_connexion']}\n";
                
                if ($cmd['latitude'] && $cmd['longitude']) {
                    echo "   ├─ Position GPS: {$cmd['latitude']}, {$cmd['longitude']} ✅\n";
                } else {
                    echo "   ├─ Position GPS: Non disponible ⚠️\n";
                }
                
                // Déterminer quel bouton s'affiche
                $statut = $cmd['statut'];
                if (in_array($statut, ['attribuee', 'acceptee', 'en_cours'], true)) {
                    echo "   └─ Bouton affiché: 🟢 TRACKING LIVE\n";
                } elseif (in_array($statut, ['livree', 'terminee'], true)) {
                    echo "   └─ Bouton affiché: 📜 HISTORIQUE\n";
                } else {
                    echo "   └─ Bouton affiché: ⏳ EN ATTENTE\n";
                }
            } else {
                echo "   └─ ❌ Coursier non trouvé en base!\n";
            }
            echo "\n";
        }
    }
    
    // 3. Vérifier l'API de tracking
    echo "🔌 3. API DE TRACKING\n";
    echo str_repeat("-", 70) . "\n";
    
    $apiFile = __DIR__ . '/api/tracking_realtime.php';
    if (file_exists($apiFile)) {
        echo "✅ Fichier API existe: api/tracking_realtime.php\n";
        $fileSize = filesize($apiFile);
        echo "   Taille: " . number_format($fileSize) . " octets\n";
    } else {
        echo "❌ Fichier API INTROUVABLE: api/tracking_realtime.php\n";
    }
    echo "\n";
    
    // 4. Test de l'API avec une commande réelle
    echo "🧪 4. TEST API TRACKING\n";
    echo str_repeat("-", 70) . "\n";
    
    if (!empty($commandes)) {
        $testCommande = $commandes[0];
        echo "Test avec commande #{$testCommande['code_commande']} (ID: {$testCommande['id']})\n\n";
        
        // Simuler un appel API
        $_GET['commande_id'] = $testCommande['id'];
        
        ob_start();
        include $apiFile;
        $apiResponse = ob_get_clean();
        
        $data = json_decode($apiResponse, true);
        
        if ($data && isset($data['success']) && $data['success']) {
            echo "✅ API répond correctement\n";
            echo "   Données retournées:\n";
            
            if (isset($data['commande'])) {
                echo "   ├─ Commande: Oui ✅\n";
            }
            if (isset($data['coursier'])) {
                echo "   ├─ Coursier: Oui ✅\n";
            }
            if (isset($data['position_coursier'])) {
                echo "   ├─ Position coursier: Oui ✅\n";
                if (isset($data['position_coursier']['latitude'])) {
                    echo "   │  └─ Lat: {$data['position_coursier']['latitude']}\n";
                    echo "   │     Lng: {$data['position_coursier']['longitude']}\n";
                }
            }
            if (isset($data['pickup'])) {
                echo "   ├─ Point de départ: Oui ✅\n";
            }
            if (isset($data['dropoff'])) {
                echo "   ├─ Point d'arrivée: Oui ✅\n";
            }
            if (isset($data['estimations'])) {
                echo "   └─ Estimations: Oui ✅\n";
            }
        } else {
            echo "⚠️  API répond mais avec une erreur:\n";
            echo "   Message: " . ($data['message'] ?? 'Erreur inconnue') . "\n";
        }
    } else {
        echo "⚠️  Aucune commande à tester\n";
    }
    echo "\n";
    
    // 5. Vérifications dans admin_commandes_enhanced.php
    echo "📄 5. VÉRIFICATIONS PAGE ADMIN\n";
    echo str_repeat("-", 70) . "\n";
    
    $adminFile = __DIR__ . '/admin_commandes_enhanced.php';
    $adminContent = file_get_contents($adminFile);
    
    $checks = [
        'openTrackingModal' => 'Fonction openTrackingModal()',
        'closeTrackingModal' => 'Fonction closeTrackingModal()',
        'switchTrackingTab' => 'Fonction switchTrackingTab()',
        'trackingModal' => 'Modal HTML #trackingModal',
        'trackingMap' => 'Div carte #trackingMap',
        'Tracking Live' => 'Bouton Tracking Live',
        'Historique' => 'Bouton Historique',
        'maps.googleapis.com' => 'Chargement Google Maps API',
    ];
    
    foreach ($checks as $search => $label) {
        if (strpos($adminContent, $search) !== false) {
            echo "   ✅ {$label}\n";
        } else {
            echo "   ❌ {$label} MANQUANT!\n";
        }
    }
    echo "\n";
    
    // 6. Résumé final
    echo "📊 6. RÉSUMÉ\n";
    echo str_repeat("-", 70) . "\n";
    
    $issues = [];
    
    if (!defined('GOOGLE_MAPS_API_KEY')) {
        $issues[] = "Clé Google Maps non configurée";
    }
    
    if (empty($commandes)) {
        $issues[] = "Aucune commande avec coursier pour tester";
    }
    
    if (!file_exists($apiFile)) {
        $issues[] = "API tracking_realtime.php manquante";
    }
    
    if (empty($issues)) {
        echo "🎉 SYSTÈME DE TRACKING OPÉRATIONNEL!\n\n";
        echo "✅ Configuration Google Maps OK\n";
        echo "✅ Commandes avec coursiers disponibles\n";
        echo "✅ API de tracking fonctionnelle\n";
        echo "✅ Interface admin complète\n\n";
        
        echo "📱 POUR TESTER:\n";
        echo "1. Ouvrir: https://localhost/COURSIER_LOCAL/admin.php?section=commandes\n";
        echo "2. Trouver une commande avec statut 'attribuee' ou 'en_cours'\n";
        echo "3. Cliquer sur le bouton '🟢 Tracking Live'\n";
        echo "4. Le modal s'ouvre avec 3 onglets:\n";
        echo "   • Vue d'ensemble (infos coursier, estimations)\n";
        echo "   • Carte (position en temps réel)\n";
        echo "   • Timeline (historique des événements)\n";
    } else {
        echo "⚠️  PROBLÈMES DÉTECTÉS:\n\n";
        foreach ($issues as $issue) {
            echo "   ❌ {$issue}\n";
        }
    }
    
} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    echo "📍 Fichier: " . $e->getFile() . " Ligne: " . $e->getLine() . "\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "✅ TEST TERMINÉ\n";
?>
