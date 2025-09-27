<?php
/**
 * API pour récupérer les informations et statut d'un coursier
 * 
 * GET /api/get_coursier_info.php?coursier_id=123
 * POST /api/get_coursier_info.php avec JSON: {"coursier_id": 123}
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../logger.php';
require_once __DIR__ . '/lib/tracking_helpers.php';

// Récupérer l'ID du coursier
$coursier_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $coursier_id = isset($_GET['coursier_id']) ? intval($_GET['coursier_id']) : null;
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $coursier_id = isset($data['coursier_id']) ? intval($data['coursier_id']) : null;
}

if (!$coursier_id || $coursier_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'MISSING_COURSIER_ID',
        'message' => 'ID coursier manquant ou invalide'
    ]);
    exit;
}

try {
    $pdo = getPDO();

    // Récupération des informations de base (coursiers + agents_suzosky)
    $infoSql = "
        SELECT 
            c.id,
            c.nom AS coursier_nom,
            c.telephone AS coursier_telephone,
            c.email AS coursier_email,
            c.statut AS coursier_statut,
            c.created_at AS date_inscription,
            c.derniere_connexion AS derniere_activite,
            c.vehicule_type AS coursier_vehicule_type,
            a.nom AS agent_nom,
            a.prenoms AS agent_prenoms,
            a.telephone AS agent_telephone,
            a.email AS agent_email,
            a.statut_connexion AS agent_statut_connexion,
            a.type_poste AS agent_vehicule_type,
            a.solde_wallet AS agent_solde_wallet,
            a.last_login_at AS agent_last_login_at,
            a.matricule AS agent_matricule
        FROM coursiers c
        LEFT JOIN agents_suzosky a ON a.id = c.id
        WHERE c.id = ?
        LIMIT 1
    ";
    $stmt = $pdo->prepare($infoSql);
    $stmt->execute([$coursier_id]);
    $coursierRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coursierRow) {
        // Fallback si le pont coursiers n'est pas synchronisé
        $fallback = $pdo->prepare("
            SELECT 
                id,
                nom AS agent_nom,
                prenoms AS agent_prenoms,
                telephone AS agent_telephone,
                email AS agent_email,
                statut_connexion AS agent_statut_connexion,
                type_poste AS agent_vehicule_type,
                COALESCE(solde_wallet, 0) AS agent_solde_wallet,
                last_login_at AS agent_last_login_at,
                matricule AS agent_matricule
            FROM agents_suzosky
            WHERE id = ?
            LIMIT 1
        ");
        $fallback->execute([$coursier_id]);
        $agentRow = $fallback->fetch(PDO::FETCH_ASSOC);

        if ($agentRow) {
            $coursierRow = [
                'id' => (int)$agentRow['id'],
                'coursier_nom' => trim(($agentRow['agent_prenoms'] ?? '') . ' ' . ($agentRow['agent_nom'] ?? '')),
                'coursier_telephone' => $agentRow['agent_telephone'] ?? null,
                'coursier_email' => $agentRow['agent_email'] ?? null,
                'coursier_statut' => $agentRow['agent_statut_connexion'] ?? 'actif',
                'date_inscription' => null,
                'derniere_activite' => $agentRow['agent_last_login_at'] ?? null,
                'coursier_zone_travail' => null,
                'coursier_vehicule_type' => $agentRow['agent_vehicule_type'] ?? null,
                'agent_nom' => $agentRow['agent_nom'] ?? null,
                'agent_prenoms' => $agentRow['agent_prenoms'] ?? null,
                'agent_telephone' => $agentRow['agent_telephone'] ?? null,
                'agent_email' => $agentRow['agent_email'] ?? null,
                'agent_statut_connexion' => $agentRow['agent_statut_connexion'] ?? null,
                'agent_zone_travail' => null,
                'agent_vehicule_type' => $agentRow['agent_vehicule_type'] ?? null,
                'agent_solde_wallet' => $agentRow['agent_solde_wallet'] ?? 0,
                'agent_last_login_at' => $agentRow['agent_last_login_at'] ?? null,
                'agent_matricule' => $agentRow['agent_matricule'] ?? null
            ];
        }
    }

    if (!$coursierRow) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'COURSIER_NOT_FOUND',
            'message' => 'Coursier non trouvé'
        ]);
        exit;
    }

    // Jeu de statuts (nouvelle taxonomie unifiée)
    $completedSet = [ 'livree', 'termine' ]; // 'termine' legacy
    $activeSet    = [ 'assignee','attribuee','acceptee','en_cours','picked_up','recupere','nouvelle' ];

    // Statistiques de commandes consolidées
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_commandes,
            SUM(CASE WHEN statut IN ('livree','termine') THEN 1 ELSE 0 END) AS commandes_terminees,
            SUM(CASE WHEN statut IN ('livree','termine') AND DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS commandes_jour,
            SUM(CASE WHEN statut IN ('livree','termine') AND DATE(created_at) = CURDATE() THEN COALESCE(prix_total, prix_estime, 0) ELSE 0 END) AS gains_jour,
            SUM(CASE WHEN statut IN ('livree','termine') THEN COALESCE(prix_total, prix_estime, 0) ELSE 0 END) AS gains_total,
            MAX(created_at) AS derniere_commande
        FROM commandes
        WHERE coursier_id = ?
    ");
    $statsStmt->execute([$coursier_id]);
    $statsRow = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Déterminer le solde wallet prioritaire
    $soldeWallet = null;
    $walletSource = null;
    if (array_key_exists('agent_solde_wallet', $coursierRow) && $coursierRow['agent_solde_wallet'] !== null) {
        $soldeWallet = (float)$coursierRow['agent_solde_wallet'];
        $walletSource = 'agents_suzosky';
    }

    if ($soldeWallet === null) {
        try {
            $stmtWallet = $pdo->prepare("SELECT solde_disponible, solde_total FROM coursier_accounts WHERE coursier_id = ? LIMIT 1");
            $stmtWallet->execute([$coursier_id]);
            if ($row = $stmtWallet->fetch(PDO::FETCH_ASSOC)) {
                if (isset($row['solde_disponible']) && $row['solde_disponible'] !== null) {
                    $soldeWallet = (float)$row['solde_disponible'];
                    $walletSource = 'coursier_accounts.solde_disponible';
                } elseif (isset($row['solde_total']) && $row['solde_total'] !== null) {
                    $soldeWallet = (float)$row['solde_total'];
                    $walletSource = 'coursier_accounts.solde_total';
                }
            }
        } catch (Throwable $e) {
            // table absente ou erreur non bloquante
        }
    }

    if ($soldeWallet === null) {
        try {
            $stmtWallet = $pdo->prepare("SELECT solde FROM comptes_coursiers WHERE coursier_id = ? LIMIT 1");
            $stmtWallet->execute([$coursier_id]);
            if ($row = $stmtWallet->fetch(PDO::FETCH_ASSOC)) {
                if (isset($row['solde']) && $row['solde'] !== null) {
                    $soldeWallet = (float)$row['solde'];
                    $walletSource = 'comptes_coursiers.solde';
                }
            }
        } catch (Throwable $e) {
            // table absente ou erreur non bloquante
        }
    }

    if ($soldeWallet === null) {
        try {
            $stmtWallet = $pdo->prepare("SELECT balance FROM clients_particuliers WHERE id = ? AND type_client = 'coursier' LIMIT 1");
            $stmtWallet->execute([$coursier_id]);
            if ($row = $stmtWallet->fetch(PDO::FETCH_ASSOC)) {
                if (isset($row['balance']) && $row['balance'] !== null) {
                    $soldeWallet = (float)$row['balance'];
                    $walletSource = 'clients_particuliers.balance';
                }
            }
        } catch (Throwable $e) {
            // table absente ou erreur non bloquante
        }
    }

    if ($soldeWallet === null) {
        $soldeWallet = 0.0;
        $walletSource = $walletSource ?? 'default_zero';
    }

    // Fusion des informations d'identité (agents vs coursiers)
    $agentFullName = trim(trim($coursierRow['agent_prenoms'] ?? '') . ' ' . trim($coursierRow['agent_nom'] ?? ''));
    $nom = $agentFullName !== '' ? $agentFullName : ($coursierRow['coursier_nom'] ?? '');
    $telephone = $coursierRow['agent_telephone'] ?? $coursierRow['coursier_telephone'] ?? null;
    $email = $coursierRow['agent_email'] ?? $coursierRow['coursier_email'] ?? null;
    $statutBase = $coursierRow['coursier_statut'] ?? null;
    $statutConnexion = $coursierRow['agent_statut_connexion'] ?? null;
    $statut = $statutBase ?? $statutConnexion ?? 'inconnu';
    $zoneTravail = $coursierRow['agent_zone_travail'] ?? $coursierRow['coursier_zone_travail'] ?? null;
    $vehiculeType = $coursierRow['agent_vehicule_type'] ?? $coursierRow['coursier_vehicule_type'] ?? null;
    $derniereActivite = $coursierRow['derniere_activite'] ?? $coursierRow['agent_last_login_at'] ?? null;
    $matricule = $coursierRow['agent_matricule'] ?? null;

    $totalCommandes = (int)($statsRow['total_commandes'] ?? 0);
    $commandesTerminees = (int)($statsRow['commandes_terminees'] ?? 0);
    $commandesJour = (int)($statsRow['commandes_jour'] ?? 0);
    $gainsJour = (float)($statsRow['gains_jour'] ?? 0);
    $gainsTotal = (float)($statsRow['gains_total'] ?? 0);
    $derniereCommande = $statsRow['derniere_commande'] ?? null;

    // Latest position via helper
    $pos = tracking_select_positions_for_courier($pdo, $coursier_id, 1);
    $derLat = null; $derLng = null; $derTs = null;
    if ($pos && isset($pos[0])) { $derLat = (float)$pos[0]['latitude']; $derLng = (float)$pos[0]['longitude']; $derTs = $pos[0]['created_at']; }
    
    // Récupérer les commandes actives (inclure nouveaux statuts + alias legacy)
    $placeholders = implode(',', array_fill(0, count($activeSet), '?'));
    $commandes_sql = "
        SELECT 
            id,
            COALESCE(client_nom, 'Client') AS client_nom,
            COALESCE(adresse_depart, adresse_retrait) AS adresse_depart,
            COALESCE(adresse_arrivee, adresse_livraison) AS adresse_arrivee,
            statut,
            COALESCE(prix_total, prix_estime, 0) AS montant_calcule,
            created_at AS date_creation
        FROM commandes 
        WHERE coursier_id = ? AND statut IN ($placeholders)
        ORDER BY created_at DESC
        LIMIT 20
    ";
    $params = array_merge([$coursier_id], $activeSet);
    $stmt_commandes = $pdo->prepare($commandes_sql);
    $stmt_commandes->execute($params);
    $commandes_raw = $stmt_commandes->fetchAll(PDO::FETCH_ASSOC);

    // Normalisation des statuts pour sortie (expose alias legacy + canonique)
    $normalized = [];
    foreach ($commandes_raw as $row) {
        $s = $row['statut'];
        $canon = $s;
        if ($s === 'attribuee') { $canon = 'assignee'; }
        if ($s === 'recupere') { $canon = 'picked_up'; }
        $row['statut_canon'] = $canon;
        // Harmoniser clé montant_total pour sortie même si colonne physique absente
        if (!isset($row['montant_total'])) { $row['montant_total'] = $row['montant_calcule']; }
        // Alias pour ancienne app
        $row['statut_alias'] = ($canon === 'assignee') ? 'attribuee' : $canon;
        $normalized[] = $row;
    }
    $commandes_actives = $normalized;
    
    // Calculer le taux de réussite
    $taux_reussite = $totalCommandes > 0 
        ? round(($commandesTerminees / $totalCommandes) * 100, 1) 
        : 0;

    $availabilityStatus = strtolower($statutConnexion ?? $statut);
    $isDisponible = in_array($availabilityStatus, ['actif','active','en_ligne','disponible'], true) && count($commandes_actives) < 3;
    
    // Formater la réponse
    $response = [
        'success' => true,
        'data' => [
            'coursier_id' => (int)$coursierRow['id'],
            'nom' => $nom,
            'telephone' => $telephone,
            'email' => $email,
            'statut' => $statut,
            'statut_connexion' => $statutConnexion,
            'matricule' => $matricule,
            'date_inscription' => $coursierRow['date_inscription'],
            'derniere_activite' => $derniereActivite,
            'zone_travail' => $zoneTravail,
            'vehicule_type' => $vehiculeType,
            'solde_wallet' => $soldeWallet,
            'solde_wallet_formatted' => number_format($soldeWallet, 0, ',', ' ') . ' F',
            'solde_wallet_source' => $walletSource,
            
            // Statistiques
            'statistiques' => [
                'total_commandes' => $totalCommandes,
                'commandes_terminees' => $commandesTerminees,
                'commandes_jour' => $commandesJour,
                'gains_jour' => $gainsJour,
                'gains_total' => $gainsTotal,
                'taux_reussite' => $taux_reussite,
                'derniere_commande' => $derniereCommande,
                'note_moyenne' => null
            ],
            
            // Position actuelle
            'position' => [
                'latitude' => $derLat,
                'longitude' => $derLng,
                'timestamp' => $derTs
            ],
            
            // Commandes actives
            'commandes_actives' => $commandes_actives,
            'commandes_actives_count' => count($commandes_actives),
            // Statut de disponibilité (ex: libre si moins de 3 actives en file)
            'disponible' => $isDisponible,
            // Jeu de statuts actif exposé pour debug app
            'active_status_set' => $activeSet,
            'completed_status_set' => $completedSet
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    if (function_exists('logError')) { logError("GET_COURSIER_INFO_ERROR", $e->getMessage()); }
    
    echo json_encode([
        'success' => false,
        'error' => 'SYSTEM_ERROR',
        'message' => 'Erreur lors de la récupération des informations',
        'debug' => [ 'err' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile() ]
    ]);
}
?>