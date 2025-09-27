<?php
// API endpoint pour synchroniser les paramètres de tarification en temps réel
// Appelée par le dashboard finances lors des ajustements des sliders

require_once __DIR__ . '/../config.php';

// Sécurité: vérifier que l'utilisateur est admin (session) OU token d'admin valide
session_start();
$authorized = false;

// 1) Session admin classique (navigateur)
if (!empty($_SESSION['admin_logged_in'])) {
    $authorized = true;
}

// 2) Token admin via header (X-Admin-Token), utilisable pour scripts/tests
if (!$authorized) {
    $expectedToken = getenv('ADMIN_API_TOKEN') ?: ($config['admin']['api_token'] ?? '');
    $providedToken = '';
    if (isset($_SERVER['HTTP_X_ADMIN_TOKEN'])) {
        $providedToken = trim((string)$_SERVER['HTTP_X_ADMIN_TOKEN']);
    } else {
        // getallheaders peut ne pas exister selon SAPI
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (!empty($headers['X-Admin-Token'])) {
                $providedToken = trim((string)$headers['X-Admin-Token']);
            }
        }
    }
    if ($providedToken !== '' && hash_equals($expectedToken, $providedToken)) {
        $authorized = true;
    }
}

// 3) Optionnel: en DEV uniquement, autoriser ?admin_token=... ou POST admin_token
if (!$authorized && !isProductionEnvironment()) {
    $expectedToken = getenv('ADMIN_API_TOKEN') ?: ($config['admin']['api_token'] ?? '');
    $providedToken = $_REQUEST['admin_token'] ?? '';
    if (!is_array($providedToken)) {
        $providedToken = trim((string)$providedToken);
        if ($providedToken !== '' && hash_equals($expectedToken, $providedToken)) {
            $authorized = true;
        }
    }
}

if (!$authorized) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Lire les paramètres envoyés
    $prixKm = isset($_POST['prix_km']) ? (float)$_POST['prix_km'] : null;
    $commissionSuzosky = isset($_POST['commission_suzosky']) ? (float)$_POST['commission_suzosky'] : null;
    // Nouveaux champs du modèle de tarification
    $fraisBase = isset($_POST['frais_base']) ? (float)$_POST['frais_base'] : null;
    $suppKmRate = isset($_POST['supp_km_rate']) ? (int)$_POST['supp_km_rate'] : null;
    $suppKmFree = isset($_POST['supp_km_free']) ? (float)$_POST['supp_km_free'] : (isset($_POST['supp_km_free_allowance']) ? (float)$_POST['supp_km_free_allowance'] : null);
    $fraisPlateforme = isset($_POST['frais_plateforme']) ? (float)$_POST['frais_plateforme'] : null;
    $fraisPublicitaires = isset($_POST['frais_publicitaires']) ? (float)$_POST['frais_publicitaires'] : null;
    
    $updates = [];
    
    // Valider et mettre à jour le prix par kilomètre
    if ($prixKm !== null && $prixKm >= 50 && $prixKm <= 2000) {
        $stmt = $pdo->prepare("INSERT INTO parametres_tarification (parametre, valeur) VALUES ('prix_kilometre', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)");
        $stmt->execute([$prixKm]);
        $updates['prix_kilometre'] = $prixKm;
    }
    
    // Valider et mettre à jour la commission Suzosky
    if ($commissionSuzosky !== null && $commissionSuzosky >= 1 && $commissionSuzosky <= 50) {
        $stmt = $pdo->prepare("INSERT INTO parametres_tarification (parametre, valeur) VALUES ('commission_suzosky', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)");
        $stmt->execute([$commissionSuzosky]);
        $updates['commission_suzosky'] = $commissionSuzosky;
    }

    // Valider et mettre à jour le frais plateforme (%)
    if ($fraisPlateforme !== null && $fraisPlateforme >= 0 && $fraisPlateforme <= 50) {
        $stmt = $pdo->prepare("INSERT INTO parametres_tarification (parametre, valeur) VALUES ('frais_plateforme', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)");
        $stmt->execute([$fraisPlateforme]);
        $updates['frais_plateforme'] = $fraisPlateforme;
    }
    // Valider et mettre à jour les frais publicitaires (%)
    if ($fraisPublicitaires !== null && $fraisPublicitaires >= 0 && $fraisPublicitaires <= 50) {
        $stmt = $pdo->prepare("INSERT INTO parametres_tarification (parametre, valeur) VALUES ('frais_publicitaires', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)");
        $stmt->execute([$fraisPublicitaires]);
        $updates['frais_publicitaires'] = $fraisPublicitaires;
    }
    
    // Valider et mettre à jour les frais de base
    if ($fraisBase !== null && $fraisBase >= 0 && $fraisBase <= 100000) {
        $stmt = $pdo->prepare("INSERT INTO parametres_tarification (parametre, valeur) VALUES ('frais_base', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)");
        $stmt->execute([$fraisBase]);
        $updates['frais_base'] = $fraisBase;
    }

    // Valider et mettre à jour le tarif des km supplémentaires
    if ($suppKmRate !== null && $suppKmRate >= 0 && $suppKmRate <= 10000) {
        $stmt = $pdo->prepare("INSERT INTO parametres_tarification (parametre, valeur) VALUES ('supp_km_rate', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)");
        $stmt->execute([$suppKmRate]);
        $updates['supp_km_rate'] = $suppKmRate;
    }

    // Valider et mettre à jour l'allocation de km gratuits après destination
    if ($suppKmFree !== null && $suppKmFree >= 0 && $suppKmFree <= 50) {
        $stmt = $pdo->prepare("INSERT INTO parametres_tarification (parametre, valeur) VALUES ('supp_km_free_allowance', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)");
        $stmt->execute([$suppKmFree]);
        // On accepte les deux noms de champs en entrée mais on renvoie la clé canonique
        $updates['supp_km_free_allowance'] = $suppKmFree;
    }

    // Journalisation des changements
    if (!empty($updates)) {
        getJournal()->logMaxDetail(
            'TARIFICATION_SYNC',
            'Synchronisation temps réel des paramètres de tarification',
            $updates
        );
    }
    
    // Purger les buffers et renvoyer la réponse JSON
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true, 
        'message' => 'Paramètres synchronisés avec succès',
        'updates' => $updates,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur de synchronisation: ' . $e->getMessage()
    ]);
}