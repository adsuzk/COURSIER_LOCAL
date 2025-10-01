<?php
// api/get_coursier_data.php - Récupérer les vraies données du coursier
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Gestion des données POST JSON et form-data
    $coursierId = 0;
    
    // 1. GET parameters
    if (isset($_GET['coursier_id'])) {
        $coursierId = intval($_GET['coursier_id']);
    }
    // 2. POST form-data
    elseif (isset($_POST['coursier_id'])) {
        $coursierId = intval($_POST['coursier_id']);
    }
    // 3. POST JSON
    else {
        $input = file_get_contents('php://input');
        if ($input) {
            $data = json_decode($input, true);
            if ($data && isset($data['coursier_id'])) {
                $coursierId = intval($data['coursier_id']);
            }
        }
    }
    
    if ($coursierId <= 0) {
        throw new Exception('ID coursier requis');
    }
    
    $pdo = getDBConnection();
    
    // Récupérer le solde du coursier (solde de rechargement prioritaire)
        $balance = 0.0;
        $balanceFound = false;
        $rechargeBalance = null;
        $legacyComptesBalance = null;

        // 0) TABLE PRINCIPALE selon documentation: agents_suzosky.solde_wallet (PRIORITÉ ABSOLUE)
        try {
            $stmt = $pdo->prepare("SELECT solde_wallet FROM agents_suzosky WHERE id = ? LIMIT 1");
            $stmt->execute([$coursierId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['solde_wallet'])) {
                $balance = (float)$row['solde_wallet'];
                $rechargeBalance = $balance;
                $balanceFound = true;
            }
        } catch (Throwable $e) {
            // Si agents_suzosky indisponible, continuer avec les anciennes tables
        }

        // 1) Système rechargement moderne: coursier_accounts (FALLBACK UNIQUEMENT)
        try {
            $stmt = $pdo->prepare("SELECT solde_disponible, solde_total FROM coursier_accounts WHERE coursier_id = ? LIMIT 1");
            $stmt->execute([$coursierId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if (isset($row['solde_disponible'])) {
                    $rechargeBalance = (float)$row['solde_disponible'];
                    $balance = $rechargeBalance;
                    $balanceFound = true;
                } elseif (isset($row['solde_total'])) {
                    $balance = (float)$row['solde_total'];
                    $balanceFound = true;
                }
            }
        } catch (Throwable $e) {
            // table ou colonnes absentes: on tentera les schémas historiques
        }

        // 2) Ancien système dédié finances: comptes_coursiers
        try {
            $stmt = $pdo->prepare("SELECT solde FROM comptes_coursiers WHERE coursier_id = ? LIMIT 1");
            $stmt->execute([$coursierId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['solde'])) {
                $legacyComptesBalance = (float)$row['solde'];
                if (!$balanceFound) {
                    $balance = $legacyComptesBalance;
                    $balanceFound = true;
                }
            }
        } catch (Throwable $e) {
            // table absente: ignorer
        }

        // 3) Ancien legacy client table
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

        if ($rechargeBalance === null && $balanceFound) {
            $rechargeBalance = $balance;
        }
    
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
                AND DATE(created_at) = CURDATE()
            ");
            $stmt->execute([$coursierId]);
            $gainsDuJour = floatval($stmt->fetchColumn());
        } catch (Throwable $e) {
            $gainsDuJour = 0.0;
        }
    
    // Récupérer les commandes en attente détaillées
    $stmt = $pdo->prepare("
        SELECT 
            id,
            client_nom,
            client_telephone,
            adresse_enlevement,
            adresse_livraison,
            prix_livraison,
            statut,
            date_commande,
            description,
            distance
        FROM commandes_coursier 
        WHERE coursier_id = ? 
        AND statut IN ('nouvelle', 'acceptee', 'en_cours')
        ORDER BY date_commande DESC
        LIMIT 10
    ");
        // Récupérer les commandes actives depuis la table commandes
        $commandes = [];
        try {
            $placeholders = implode(',', array_fill(0, count($activeStatuses), '?'));
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    COALESCE(client_nom, 'Client') as client_nom,
                    COALESCE(client_telephone, telephone_expediteur) as client_telephone,
                    COALESCE(telephone_destinataire, '') as telephone_destinataire,
                    COALESCE(adresse_depart, adresse_retrait) as adresse_enlevement,
                    COALESCE(adresse_arrivee, adresse_livraison) as adresse_livraison,
                    COALESCE(latitude_depart, latitude_retrait) as latitude_enlevement,
                    COALESCE(longitude_depart, longitude_retrait) as longitude_enlevement,
                    COALESCE(latitude_arrivee, latitude_livraison) as latitude_livraison,
                    COALESCE(longitude_arrivee, longitude_livraison) as longitude_livraison,
                    COALESCE(prix_total, prix_estime, 0) as prix_livraison,
                    COALESCE(mode_paiement, 'especes') as mode_paiement,
                    statut,
                    created_at as date_commande,
                    description_colis as description,
                    COALESCE(distance_estimee, 0) as distance
                FROM commandes 
                WHERE coursier_id = ? 
                AND statut IN ($placeholders)
                ORDER BY created_at DESC
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
        
        $commandesFormatees[] = [
            'id' => $cmd['id'],
            'clientNom' => $cmd['client_nom'],
            'clientTelephone' => $cmd['client_telephone'],
            'telephoneDestinataire' => $cmd['telephone_destinataire'],
            'adresseEnlevement' => $cmd['adresse_enlevement'],
            'adresseLivraison' => $cmd['adresse_livraison'],
            'latitudeEnlevement' => floatval($cmd['latitude_enlevement'] ?? 0),
            'longitudeEnlevement' => floatval($cmd['longitude_enlevement'] ?? 0),
            'latitudeLivraison' => floatval($cmd['latitude_livraison'] ?? 0),
            'longitudeLivraison' => floatval($cmd['longitude_livraison'] ?? 0),
            'distance' => floatval($cmd['distance'] ?? 0),
            'tempsEstime' => intval(($cmd['distance'] ?? 0) * 3), // 3 min par km
            'prixTotal' => floatval($cmd['prix_livraison']),
            'prixLivraison' => floatval($cmd['prix_livraison']),
            'methodePaiement' => $cmd['mode_paiement'] ?? 'especes',
            'statut' => $frontStatut,
            'statut_raw' => $statutRaw,
            'dateCommande' => date('Y-m-d', strtotime($cmd['date_commande'])),
            'heureCommande' => date('H:i', strtotime($cmd['date_commande'])),
            'description' => $cmd['description'] ?? '',
            'typeCommande' => 'Standard'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'balance' => $balance,
            'recharge_balance' => $rechargeBalance,
            'legacy_balance' => $legacyComptesBalance,
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