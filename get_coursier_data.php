<?php
// get_coursier_data.php - Récupérer les vraies données du coursier (racine)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api/schema_utils.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $coursierId = intval($_GET['coursier_id'] ?? $_POST['coursier_id'] ?? 0);
    
    if ($coursierId <= 0) {
        throw new Exception('ID coursier requis');
    }
    
    $pdo = getDBConnection();
    ensureCommandesStructure($pdo);
    
    // Récupérer le solde du coursier
        // Récupération robuste du solde selon les schémas réellement présents en BDD
        $balance = 0.0;
        $balanceFound = false;

        // 1) Nouveau système: table comptes_coursiers.solde (recommandé)
        try {
            $stmt = $pdo->prepare("SELECT solde FROM comptes_coursiers WHERE coursier_id = ?");
            $stmt->execute([$coursierId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['solde'])) {
                $balance = (float)$row['solde'];
                $balanceFound = true;
            }
        } catch (Throwable $e) {
            // table ou colonne absente: on tente l'option suivante
        }

        // 2) Système alternatif: table coursier_accounts.solde_disponible ou solde_total
        if (!$balanceFound) {
            try {
                $stmt = $pdo->prepare("SELECT solde_disponible, solde_total FROM coursier_accounts WHERE coursier_id = ?");
                $stmt->execute([$coursierId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    // Prioriser le solde disponible s'il existe
                    if (isset($row['solde_disponible'])) {
                        $balance = (float)$row['solde_disponible'];
                        $balanceFound = true;
                    } elseif (isset($row['solde_total'])) {
                        $balance = (float)$row['solde_total'];
                        $balanceFound = true;
                    }
                }
            } catch (Throwable $e) {
                // table ou colonnes absentes
            }
        }

        // 3) Ancien système: clients_particuliers.balance (type_client='coursier')
        if (!$balanceFound) {
            try {
                $stmt = $pdo->prepare("SELECT balance FROM clients_particuliers WHERE id = ? AND type_client = 'coursier'");
                $stmt->execute([$coursierId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['balance'])) {
                    $balance = (float)$row['balance'];
                    $balanceFound = true;
                }
            } catch (Throwable $e) {
                // toujours pas
            }
        }

        // Si aucun schéma trouvé, on laisse balance=0 sans échec bloquant
    
        // Compter les commandes en attente (utiliser table commandes correcte)
        $commandesAttente = 0;
        $activeStatuses = ['assignee', 'nouvelle', 'acceptee', 'en_cours', 'picked_up'];
        try {
            $placeholders = implode(',', array_fill(0, count($activeStatuses), '?'));
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total_attente
                FROM commandes 
                WHERE coursier_id = ? AND statut IN ($placeholders)
            ");
            $params = array_merge([$coursierId], $activeStatuses);
            $stmt->execute($params);
            $commandesAttente = intval($stmt->fetchColumn());
        } catch (Throwable $e) {
            $commandesAttente = 0;
        }
    
        // Calculer les gains du jour (table commandes)
        $gainsDuJour = 0.0;
        try {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(COALESCE(prix_total, prix_estime, 0)), 0) as gains_jour
                FROM commandes 
                WHERE coursier_id = ? 
                AND statut = 'livree' 
                AND DATE(COALESCE(created_at, date_creation)) = CURDATE()
            ");
            $stmt->execute([$coursierId]);
            $gainsDuJour = floatval($stmt->fetchColumn());
        } catch (Throwable $e) {
            $gainsDuJour = 0.0;
        }
    
        // Récupérer les commandes actives depuis la table commandes
        $commandes = [];
        $coordExpr = commandeCoordinateExpressions($pdo);
        try {
            $placeholders = implode(',', array_fill(0, count($activeStatuses), '?'));
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    COALESCE(client_nom, 'Client') as client_nom,
                    COALESCE(client_telephone, telephone_expediteur) as client_telephone,
                    COALESCE(adresse_depart, adresse_retrait) as adresse_enlevement,
                    COALESCE(adresse_arrivee, adresse_livraison) as adresse_livraison,
                    COALESCE(prix_total, prix_estime, 0) as prix_livraison,
                    statut,
                    COALESCE(created_at, date_creation) as date_commande,
                    COALESCE(description_colis, description, '') as description,
                    COALESCE(distance_estimee, 0) as distance,
                    {$coordExpr['pickup_lat']} AS pickup_latitude,
                    {$coordExpr['pickup_lng']} AS pickup_longitude,
                    {$coordExpr['drop_lat']} AS dropoff_latitude,
                    {$coordExpr['drop_lng']} AS dropoff_longitude
                FROM commandes 
                WHERE coursier_id = ? 
                AND statut IN ($placeholders)
                ORDER BY COALESCE(created_at, date_creation) DESC
                LIMIT 10
            ");
            $params = array_merge([$coursierId], $activeStatuses);
            $stmt->execute($params);
            $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $commandes = [];
        }
    
    // Formater les commandes avec mapping statut (identique à get_coursier_orders_simple)
    $commandesFormatees = [];
    foreach ($commandes as $cmd) {
        $statutRaw = $cmd['statut'];
        // Mapping front-end (l'app compte seulement 'nouvelle' ou 'attente' pour pending)
        $frontStatut = $statutRaw;
        if ($statutRaw === 'assignee') { $frontStatut = 'nouvelle'; }
        elseif ($statutRaw === 'picked_up') { $frontStatut = 'recupere'; }
        
        $formatted = [
            'id' => $cmd['id'],
            'clientNom' => $cmd['client_nom'],
            'clientTelephone' => $cmd['client_telephone'],
            'adresseEnlevement' => $cmd['adresse_enlevement'],
            'adresseLivraison' => $cmd['adresse_livraison'],
            'distance' => floatval($cmd['distance'] ?? 0),
            'tempsEstime' => intval(($cmd['distance'] ?? 0) * 3), // 3 min par km
            'prixTotal' => floatval($cmd['prix_livraison']),
            'prixLivraison' => floatval($cmd['prix_livraison']),
            'statut' => $frontStatut,
            'statut_raw' => $statutRaw,
            'dateCommande' => date('Y-m-d', strtotime($cmd['date_commande'])),
            'heureCommande' => date('H:i', strtotime($cmd['date_commande'])),
            'description' => $cmd['description'] ?? '',
            'typeCommande' => 'Standard'
        ];

        $pickupLatRaw = $cmd['pickup_latitude'] ?? null;
        $pickupLngRaw = $cmd['pickup_longitude'] ?? null;
        if (is_numeric($pickupLatRaw) && is_numeric($pickupLngRaw) && (abs((float)$pickupLatRaw) > 0.0001 || abs((float)$pickupLngRaw) > 0.0001)) {
            $pickupCoords = [
                'latitude' => (float)$pickupLatRaw,
                'longitude' => (float)$pickupLngRaw
            ];
            $formatted['coordonneesEnlevement'] = $pickupCoords;
            $formatted['coordonnees_enlevement'] = $pickupCoords;
        }

        $dropLatRaw = $cmd['dropoff_latitude'] ?? null;
        $dropLngRaw = $cmd['dropoff_longitude'] ?? null;
        if (is_numeric($dropLatRaw) && is_numeric($dropLngRaw) && (abs((float)$dropLatRaw) > 0.0001 || abs((float)$dropLngRaw) > 0.0001)) {
            $dropCoords = [
                'latitude' => (float)$dropLatRaw,
                'longitude' => (float)$dropLngRaw
            ];
            $formatted['coordonneesLivraison'] = $dropCoords;
            $formatted['coordonnees_livraison'] = $dropCoords;
        }

        $commandesFormatees[] = $formatted;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'balance' => $balance,
            'commandes_attente' => $commandesAttente,
            'gains_du_jour' => $gainsDuJour,
            'commandes' => $commandesFormatees
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>