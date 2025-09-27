<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/finances_sync.php';
// Ne traiter les requêtes HTTP que si ce fichier est appelé directement
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
/**
 * SYSTÈME DE FACTURATION COURSIERS SUZOSKY
 * Gestion caution 3000 FCFA + Tarification intelligente
 */
    // Support GET for usage info
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    echo json_encode([
        'usage' => 'POST parameters: action (calculer_prix, verifier_compte, recharger_compte, get_solde, get_historique)',
        'example' => ['action' => 'calculer_prix', 'zone_depart'=>'cocody', 'zone_arrivee'=>'plateau', 'distance_km'=>5]
    ]);
    exit;
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
        exit;
    }
}

class SuzoskyBilling {
    private $pdo;
    
    // Tarifs de base (moins cher que Yango)
    private $tarifs_base = [
        'intra_commune' => [
            'base' => 700,        // Prix de base 700 FCFA
            'par_km' => 120,      // 120 FCFA par km
            'temps_min' => 15     // 15 min minimum
        ],
        'inter_commune' => [
            'base' => 1000,       // Prix de base 1000 FCFA
            'par_km' => 100,      // 100 FCFA par km
            'temps_min' => 25     // 25 min minimum
        ],
        'longue_distance' => [
            'base' => 1500,       // Prix de base 1500 FCFA
            'par_km' => 80,       // 80 FCFA par km
            'temps_min' => 45     // 45 min minimum
        ]
    ];
    
    // Commission Suzosky
    private $commission_percent = 15.0; // 15%
    
    // Caution obligatoire
    private $caution_obligatoire = 3000; // 3000 FCFA
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    /**
     * Calculer le prix d'une livraison
     */
    public function calculerPrixLivraison($zone_depart, $zone_arrivee, $distance_km, $temps_estime = null) {
        try {
            // Vérifier si tarif spécifique existe dans la base
            $stmt = $this->pdo->prepare("
                SELECT prix_base, prix_par_km, commission_suzosky_percent, temps_estime_min
                FROM tarification_livraisons 
                WHERE zone_depart = ? AND zone_arrivee = ? 
                AND distance_min_km <= ? AND distance_max_km >= ?
                AND is_active = TRUE
                LIMIT 1
            ");
            $stmt->execute([$zone_depart, $zone_arrivee, $distance_km, $distance_km]);
            $tarif_db = $stmt->fetch();
            
            if ($tarif_db) {
                // Utiliser tarif de la base de données
                $prix_base = $tarif_db['prix_base'];
                $prix_par_km = $tarif_db['prix_par_km'];
                $commission = $tarif_db['commission_suzosky_percent'];
                $temps_min = $tarif_db['temps_estime_min'];
            } else {
                // Utiliser tarif par défaut selon la distance
                if ($distance_km <= 5) {
                    $tarif = $this->tarifs_base['intra_commune'];
                } elseif ($distance_km <= 15) {
                    $tarif = $this->tarifs_base['inter_commune'];
                } else {
                    $tarif = $this->tarifs_base['longue_distance'];
                }
                
                $prix_base = $tarif['base'];
                $prix_par_km = $tarif['par_km'];
                $commission = $this->commission_percent;
                $temps_min = $tarif['temps_min'];
            }
            
            // Calcul prix total
            $prix_total = $prix_base + ($distance_km * $prix_par_km);
            
            // Ajustements selon le temps (si fourni)
            if ($temps_estime && $temps_estime > $temps_min) {
                $supplement_temps = ($temps_estime - $temps_min) * 10; // 10 FCFA par minute supplémentaire
                $prix_total += $supplement_temps;
            }
            
            // Calcul commission Suzosky
            $commission_montant = ($prix_total * $commission) / 100;
            $montant_coursier = $prix_total - $commission_montant;
            
            return [
                'prix_total' => round($prix_total),
                'commission_suzosky' => round($commission_montant),
                'montant_coursier' => round($montant_coursier),
                'distance_km' => $distance_km,
                'temps_estime' => $temps_estime ?? $temps_min,
                'details' => [
                    'prix_base' => $prix_base,
                    'prix_distance' => $distance_km * $prix_par_km,
                    'supplement_temps' => isset($supplement_temps) ? $supplement_temps : 0
                ]
            ];
            
        } catch (Exception $e) {
            throw new Exception("Erreur calcul prix: " . $e->getMessage());
        }
    }
    
    /**
     * Vérifier si coursier peut recevoir des commandes
     */
    public function peutRecevoirCommandes($coursier_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT solde_total, caution_obligatoire, solde_disponible, 
                       statut_compte, can_receive_orders
                FROM coursier_accounts 
                WHERE coursier_id = ?
            ");
            $stmt->execute([$coursier_id]);
            $compte = $stmt->fetch();
            
            if (!$compte) {
                return [
                    'peut_recevoir' => false,
                    'raison' => 'Compte non créé',
                    'action_requise' => 'creer_compte'
                ];
            }
            
            if ($compte['statut_compte'] !== 'actif') {
                return [
                    'peut_recevoir' => false,
                    'raison' => 'Compte suspendu ou inactif',
                    'action_requise' => 'contacter_support'
                ];
            }
            
            if ($compte['solde_total'] < $this->caution_obligatoire) {
                $manquant = $this->caution_obligatoire - $compte['solde_total'];
                return [
                    'peut_recevoir' => false,
                    'raison' => "Caution insuffisante. Il manque $manquant FCFA",
                    'action_requise' => 'recharger_compte',
                    'montant_requis' => $manquant
                ];
            }
            
            if ($compte['solde_disponible'] <= 0) {
                return [
                    'peut_recevoir' => false,
                    'raison' => 'Solde disponible épuisé. Rechargez pour continuer à recevoir des commandes',
                    'action_requise' => 'recharger_compte',
                    'montant_requis' => 1000 // Minimum de recharge
                ];
            }
            
            return [
                'peut_recevoir' => true,
                'solde_disponible' => $compte['solde_disponible'],
                'caution' => $compte['caution_obligatoire']
            ];
            
        } catch (Exception $e) {
            return [
                'peut_recevoir' => false,
                'raison' => 'Erreur vérification compte: ' . $e->getMessage(),
                'action_requise' => 'contacter_support'
            ];
        }
    }
    
    /**
     * Traiter une livraison et facturer le coursier
     */
    public function traiterFacturationLivraison($livraison_id, $coursier_id, $prix_calcule, $commission_suzosky) {
        try {
            $this->pdo->beginTransaction();
            
            // Vérifier solde disponible
            $verification = $this->peutRecevoirCommandes($coursier_id);
            if (!$verification['peut_recevoir']) {
                throw new Exception("Coursier ne peut pas prendre cette livraison: " . $verification['raison']);
            }
            
            // Créer transaction de prélèvement
            $transaction_id = 'TXN_' . date('YmdHis') . '_' . substr(md5($livraison_id), 0, 6);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO transactions_coursiers 
                (transaction_id, coursier_id, type_transaction, montant, commission_suzosky, 
                 livraison_id, statut, description) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $transaction_id,
                $coursier_id,
                'prelevement',
                $commission_suzosky,
                $commission_suzosky,
                $livraison_id,
                'completed',
                'Commission Suzosky pour livraison ' . $livraison_id
            ]);
            
            // Mettre à jour le compte coursier
            $stmt = $this->pdo->prepare("
                UPDATE coursier_accounts 
                SET solde_disponible = solde_disponible - ?,
                    total_preleve = total_preleve + ?,
                    total_courses = total_courses + 1,
                    total_gains = total_gains + ?,
                    updated_at = NOW(),
                    can_receive_orders = CASE 
                        WHEN (solde_disponible - ?) > 0 THEN TRUE 
                        ELSE FALSE 
                    END
                WHERE coursier_id = ?
            ");
            $stmt->execute([
                $commission_suzosky, 
                $commission_suzosky,
                ($prix_calcule - $commission_suzosky), // Gains coursier
                $commission_suzosky,
                $coursier_id
            ]);
            
            // Mettre à jour la livraison avec info facturation
            $stmt = $this->pdo->prepare("
                UPDATE business_livraisons 
                SET prix_calcule = ?, 
                    commission_suzosky = ?, 
                    montant_coursier = ?,
                    transaction_id = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $prix_calcule,
                $commission_suzosky,
                ($prix_calcule - $commission_suzosky),
                $transaction_id,
                $livraison_id
            ]);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'transaction_id' => $transaction_id,
                'montant_preleve' => $commission_suzosky,
                'gains_coursier' => ($prix_calcule - $commission_suzosky)
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Erreur facturation: " . $e->getMessage());
        }
    }
    
    /**
     * Recharger compte coursier
     */
    public function rechargerCompte($coursier_id, $montant, $methode_paiement, $reference_paiement = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Vérifier montant minimum
            if ($montant < 500) {
                throw new Exception("Montant minimum de recharge: 500 FCFA");
            }
            
            // Créer compte si n'existe pas
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO coursier_accounts 
                (coursier_id, solde_total, caution_obligatoire, solde_disponible, statut_compte) 
                VALUES (?, 0, ?, 0, 'inactif')
            ");
            $stmt->execute([$coursier_id, $this->caution_obligatoire]);
            
            // Créer transaction de recharge
            $transaction_id = 'RCH_' . date('YmdHis') . '_' . substr(md5($coursier_id), 0, 6);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO transactions_coursiers 
                (transaction_id, coursier_id, type_transaction, montant, 
                 methode_paiement, reference_paiement, statut, description) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $transaction_id,
                $coursier_id,
                'recharge',
                $montant,
                $methode_paiement,
                $reference_paiement,
                'completed',
                'Recharge compte via ' . $methode_paiement
            ]);
            
            // Mettre à jour le compte
            $stmt = $this->pdo->prepare("
                UPDATE coursier_accounts 
                SET solde_total = solde_total + ?,
                    solde_disponible = CASE 
                        WHEN solde_total < caution_obligatoire THEN 
                            GREATEST(0, (solde_total + ?) - caution_obligatoire)
                        ELSE 
                            solde_disponible + ?
                    END,
                    statut_compte = CASE 
                        WHEN (solde_total + ?) >= caution_obligatoire THEN 'actif'
                        ELSE 'inactif'
                    END,
                    can_receive_orders = CASE 
                        WHEN (solde_total + ?) >= caution_obligatoire THEN TRUE
                        ELSE FALSE
                    END,
                    last_recharge = NOW(),
                    updated_at = NOW()
                WHERE coursier_id = ?
            ");
            $stmt->execute([$montant, $montant, $montant, $montant, $montant, $coursier_id]);

            try {
                ensureCourierAccount($this->pdo, $coursier_id);
                $legacy = $this->pdo->prepare("UPDATE comptes_coursiers SET solde = solde + ? WHERE coursier_id = ?");
                $legacy->execute([$montant, $coursier_id]);
            } catch (Throwable $syncLegacy) {
                // toléré en environnement où comptes_coursiers n'existe pas
            }

            adjustCoursierRechargeBalance($this->pdo, (int)$coursier_id, (float)$montant, [
                'reason' => 'recharge',
                'affect_total' => true,
            ]);
            
            // Mettre à jour table coursiers
            $stmt = $this->pdo->prepare("
                UPDATE coursiers 
                SET has_active_account = CASE 
                    WHEN (SELECT solde_total FROM coursier_accounts WHERE coursier_id = ?) >= ? THEN TRUE
                    ELSE FALSE
                END,
                can_receive_orders = CASE 
                    WHEN (SELECT solde_total FROM coursier_accounts WHERE coursier_id = ?) >= ? THEN TRUE
                    ELSE FALSE
                END
                WHERE id_coursier = ?
            ");
            $stmt->execute([$coursier_id, $this->caution_obligatoire, $coursier_id, $this->caution_obligatoire, $coursier_id]);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'transaction_id' => $transaction_id,
                'nouveau_solde' => $this->getSoldeCoursier($coursier_id)
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Erreur recharge: " . $e->getMessage());
        }
    }
    
    /**
     * Obtenir solde coursier
     */
    public function getSoldeCoursier($coursier_id) {
        $stmt = $this->pdo->prepare("
            SELECT solde_total, solde_disponible, caution_obligatoire, 
                   statut_compte, can_receive_orders, total_courses, total_gains
            FROM coursier_accounts 
            WHERE coursier_id = ?
        ");
        $stmt->execute([$coursier_id]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Historique transactions coursier
     */
    public function getHistoriqueTransactions($coursier_id, $limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM transactions_coursiers 
            WHERE coursier_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$coursier_id, $limit]);
        return $stmt->fetchAll();
    }
}

// === API ENDPOINTS ===

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $billing = new SuzoskyBilling();
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'calculer_prix':
                $zone_depart = $_POST['zone_depart'] ?? '';
                $zone_arrivee = $_POST['zone_arrivee'] ?? '';
                $distance = floatval($_POST['distance'] ?? 0);
                $temps = intval($_POST['temps'] ?? 0);
                
                $prix = $billing->calculerPrixLivraison($zone_depart, $zone_arrivee, $distance, $temps);
                echo json_encode(['success' => true, 'pricing' => $prix]);
                break;
                
            case 'verifier_compte':
                $coursier_id = $_POST['coursier_id'] ?? '';
                $verification = $billing->peutRecevoirCommandes($coursier_id);
                echo json_encode(['success' => true, 'verification' => $verification]);
                break;
                
            case 'recharger_compte':
                $coursier_id = $_POST['coursier_id'] ?? '';
                $montant = floatval($_POST['montant'] ?? 0);
                $methode = $_POST['methode_paiement'] ?? '';
                $reference = $_POST['reference'] ?? null;
                
                $result = $billing->rechargerCompte($coursier_id, $montant, $methode, $reference);
                echo json_encode($result);
                break;
                
            case 'get_solde':
                $coursier_id = $_POST['coursier_id'] ?? '';
                $solde = $billing->getSoldeCoursier($coursier_id);
                echo json_encode(['success' => true, 'solde' => $solde]);
                break;
                
            case 'get_historique':
                $coursier_id = $_POST['coursier_id'] ?? '';
                $limit = intval($_POST['limit'] ?? 50);
                $historique = $billing->getHistoriqueTransactions($coursier_id, $limit);
                echo json_encode(['success' => true, 'transactions' => $historique]);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
