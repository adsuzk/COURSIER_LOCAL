<?php
/**
 * SCRIPT DE VALIDATION COMPLETE - Bug Rotation Commande
 * Teste tous les aspects de la correction
 */

require_once __DIR__ . '/config.php';

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  VALIDATION COMPLETE - CORRECTION BUG ROTATION COMMANDE 123  ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$pdo = getDBConnection();
$coursier_id = 5;
$commande_id = 123;

// ========================================
// TEST 1: État actuel de la commande 123
// ========================================
echo "═══ TEST 1: État de la commande 123 ═══\n";

$stmt = $pdo->prepare('SELECT id, statut, cash_recupere, mode_paiement, coursier_id FROM commandes WHERE id = ?');
$stmt->execute([$commande_id]);
$commande = $stmt->fetch(PDO::FETCH_ASSOC);

if ($commande) {
    echo "✅ Commande trouvée:\n";
    echo "   - ID: {$commande['id']}\n";
    echo "   - Coursier: {$commande['coursier_id']}\n";
    echo "   - Statut: {$commande['statut']}\n";
    echo "   - Cash récupéré: {$commande['cash_recupere']}\n";
    echo "   - Mode paiement: " . ($commande['mode_paiement'] ?: '(vide)') . "\n";
    
    if ($commande['statut'] === 'terminee') {
        echo "   ✅ Statut correct: terminee\n";
    } else {
        echo "   ⚠️  Statut: {$commande['statut']} (devrait être 'terminee')\n";
    }
    
    if ($commande['cash_recupere'] > 0) {
        echo "   ✅ Cash marqué comme récupéré\n";
    } else {
        echo "   ❌ Cash non marqué comme récupéré\n";
    }
} else {
    echo "❌ Commande 123 non trouvée en BDD\n";
}

echo "\n";

// ========================================
// TEST 2: API get_coursier_orders (filtrage)
// ========================================
echo "═══ TEST 2: Filtrage des commandes terminées ═══\n";

$url = "http://localhost/COURSIER_LOCAL/api/get_coursier_orders_simple.php?coursier_id=$coursier_id&limit=20";
$response = @file_get_contents($url);

if ($response) {
    $data = json_decode($response, true);
    
    if ($data['success'] ?? false) {
        $commandes = $data['data']['commandes'] ?? [];
        echo "✅ API répond correctement\n";
        echo "   - Commandes retournées: " . count($commandes) . "\n";
        
        // Vérifier si la commande 123 est présente
        $found123 = false;
        foreach ($commandes as $cmd) {
            if ($cmd['id'] == 123) {
                $found123 = true;
                break;
            }
        }
        
        if (!$found123) {
            echo "   ✅ Commande 123 correctement filtrée (non retournée)\n";
        } else {
            echo "   ❌ ERREUR: Commande 123 encore présente dans la liste!\n";
        }
        
        // Afficher les commandes actives
        if (count($commandes) > 0) {
            echo "   \n   Commandes actives:\n";
            foreach ($commandes as $cmd) {
                echo "     - ID: {$cmd['id']} | Statut: {$cmd['statut']}\n";
            }
        } else {
            echo "   ℹ️  Aucune commande active pour le coursier $coursier_id\n";
        }
    } else {
        echo "❌ Erreur API: " . ($data['error'] ?? 'Inconnue') . "\n";
    }
} else {
    echo "❌ Impossible de contacter l'API\n";
}

echo "\n";

// ========================================
// TEST 3: Créer une commande de test
// ========================================
echo "═══ TEST 3: Création d'une commande de test ═══\n";

// Insérer une nouvelle commande en espèces pour tester
$timestamp = time();
$code_commande = 'TEST' . $timestamp;
$order_number = 'ORD' . $timestamp;
$stmt = $pdo->prepare("
    INSERT INTO commandes (
        code_commande,
        order_number,
        client_nom, client_telephone, 
        adresse_depart, adresse_arrivee,
        coursier_id, statut, mode_paiement,
        prix_total, distance_estimee,
        created_at
    ) VALUES (
        ?, ?,
        'Client Test Rotation', '0102030405',
        'Test Départ', 'Test Arrivée',
        ?, 'en_cours', 'especes',
        5000, 2.5,
        NOW()
    )
");

if ($stmt->execute([$code_commande, $order_number, $coursier_id])) {
    $test_commande_id = $pdo->lastInsertId();
    echo "✅ Commande de test créée: ID $test_commande_id\n";
    echo "   - Statut: en_cours\n";
    echo "   - Mode paiement: especes\n";
    
    // Tester l'API de confirmation
    echo "\n═══ TEST 4: Confirmation du cash (API) ═══\n";
    
    $url = "http://localhost/COURSIER_LOCAL/mobile_sync_api.php?action=confirm_cash_received&coursier_id=$coursier_id&commande_id=$test_commande_id";
    $response = @file_get_contents($url);
    
    if ($response) {
        $data = json_decode($response, true);
        
        if ($data['success'] ?? false) {
            echo "✅ API confirm_cash_received fonctionne\n";
            echo "   - Message: {$data['message']}\n";
            
            // Vérifier en BDD
            $stmt = $pdo->prepare('SELECT statut, cash_recupere FROM commandes WHERE id = ?');
            $stmt->execute([$test_commande_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['statut'] === 'terminee' && $result['cash_recupere'] > 0) {
                echo "   ✅ Commande correctement mise à jour en BDD\n";
                echo "      - Statut: {$result['statut']}\n";
                echo "      - Cash récupéré: {$result['cash_recupere']}\n";
            } else {
                echo "   ❌ Mise à jour BDD incorrecte\n";
            }
            
            // Vérifier que la commande n'est plus retournée
            echo "\n═══ TEST 5: Vérification du filtrage après confirmation ═══\n";
            
            $url2 = "http://localhost/COURSIER_LOCAL/api/get_coursier_orders_simple.php?coursier_id=$coursier_id&limit=20";
            $response2 = @file_get_contents($url2);
            
            if ($response2) {
                $data2 = json_decode($response2, true);
                $commandes2 = $data2['data']['commandes'] ?? [];
                
                $foundTest = false;
                foreach ($commandes2 as $cmd) {
                    if ($cmd['id'] == $test_commande_id) {
                        $foundTest = true;
                        break;
                    }
                }
                
                if (!$foundTest) {
                    echo "✅ Commande test $test_commande_id correctement filtrée\n";
                } else {
                    echo "❌ Commande test encore visible après confirmation\n";
                }
            }
            
            // Nettoyer
            echo "\n═══ NETTOYAGE ═══\n";
            $pdo->prepare("DELETE FROM commandes WHERE id = ?")->execute([$test_commande_id]);
            echo "✅ Commande de test supprimée\n";
            
        } else {
            echo "❌ Erreur API: " . ($data['message'] ?? 'Inconnue') . "\n";
        }
    } else {
        echo "❌ Impossible de contacter l'API confirm_cash\n";
    }
    
} else {
    echo "❌ Impossible de créer la commande de test\n";
}

// ========================================
// RÉSUMÉ FINAL
// ========================================
echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                      RÉSUMÉ FINAL                            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "✅ CORRECTIONS BACKEND:\n";
echo "   1. AndroidManifest.xml: configChanges ajouté\n";
echo "   2. get_coursier_orders_simple.php: filtre terminées\n";
echo "   3. mobile_sync_api.php: conditions assouplies\n";
echo "\n";
echo "📱 PROCHAINE ÉTAPE:\n";
echo "   1. Rebuild l'APK:\n";
echo "      cd CoursierAppV7\n";
echo "      .\\rebuild_apk.bat\n";
echo "\n";
echo "   2. Installer sur le téléphone:\n";
echo "      .\\install_apk.bat\n";
echo "\n";
echo "   3. Tester:\n";
echo "      - Accepter une commande\n";
echo "      - Confirmer le cash\n";
echo "      - Tourner l'écran → vérifier qu'elle ne réapparaît pas\n";
echo "\n";
echo "🎉 VALIDATION TERMINÉE !\n";
echo "\n";
