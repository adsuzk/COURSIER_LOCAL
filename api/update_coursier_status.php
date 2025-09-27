<?php
/**
 * API pour mettre à jour le statut d'un coursier
 * 
 * POST /api/update_coursier_status.php
 * Content-Type: application/json
 * 
 * Body: {
 *   "coursier_id": 123,
 *   "statut": "actif|inactif|en_pause|hors_service",
 *   "position": {
 *     "lat": 5.3599517,
 *     "lng": -4.0082563
            try {
                tracking_insert_position($pdo, $coursier_id, $lat, $lng, isset($data['position']['accuracy'])? floatval($data['position']['accuracy']) : null);
            } catch (Exception $e) {

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../logger.php';
require_once __DIR__ . '/lib/tracking_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'error' => 'METHOD_NOT_ALLOWED',
        'message' => 'Seule la méthode POST est autorisée'
    ]);
    exit;
}

// Décoder les données JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'INVALID_JSON',
        'message' => 'Données JSON invalides'
    ]);
    exit;
}

// Validation des champs obligatoires
if (!isset($data['coursier_id']) || !isset($data['statut'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'MISSING_FIELDS',
        'message' => 'coursier_id et statut sont requis'
    ]);
    exit;
}

$coursier_id = intval($data['coursier_id']);
$nouveau_statut = trim($data['statut']);

// Validation du statut
$statuts_valides = ['actif', 'inactif', 'en_pause', 'hors_service', 'suspendu'];
if (!in_array($nouveau_statut, $statuts_valides)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'INVALID_STATUS',
        'message' => 'Statut invalide. Valeurs autorisées: ' . implode(', ', $statuts_valides)
    ]);
    exit;
}

// Validation ID coursier
if ($coursier_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'INVALID_COURSIER_ID',
        'message' => 'ID coursier invalide'
    ]);
    exit;
}

try {
    $pdo = getPDO();
    
    // Vérifier si le coursier existe
    $check_sql = "SELECT id, nom, statut FROM coursiers WHERE id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$coursier_id]);
    $coursier = $check_stmt->fetch();
    
    if (!$coursier) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'COURSIER_NOT_FOUND',
            'message' => 'Coursier non trouvé'
        ]);
        exit;
    }
    
    $ancien_statut = $coursier['statut'];
    
    // Mettre à jour le statut
    $update_sql = "
        UPDATE coursiers 
        SET statut = ?, 
            derniere_activite = NOW(),
            date_modification = NOW()
        WHERE id = ?
    ";
    
    $update_stmt = $pdo->prepare($update_sql);
    $result = $update_stmt->execute([$nouveau_statut, $coursier_id]);
    
    if (!$result) {
        throw new Exception("Erreur lors de la mise à jour du statut");
    }
    
    // Si une position est fournie, l'enregistrer aussi
    if (isset($data['position']) && isset($data['position']['lat']) && isset($data['position']['lng'])) {
        $lat = floatval($data['position']['lat']);
        $lng = floatval($data['position']['lng']);
        
        // Validation des coordonnées
        if ($lat >= 4.5 && $lat <= 6.5 && $lng >= -5.5 && $lng <= -3.0) {
            try {
                // Créer la table si nécessaire
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS tracking_coursiers (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        coursier_id INT NOT NULL,
                        latitude DECIMAL(10,8) NOT NULL,
                        longitude DECIMAL(11,8) NOT NULL,
                        accuracy DECIMAL(6,2) NULL,
                        timestamp DATETIME NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_coursier_timestamp (coursier_id, timestamp)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                
                $position_sql = "
                    INSERT INTO tracking_coursiers (coursier_id, latitude, longitude, timestamp) 
                    VALUES (?, ?, ?, NOW())
                ";
                $position_stmt = $pdo->prepare($position_sql);
                $position_stmt->execute([$coursier_id, $lat, $lng]);
            } catch (Exception $e) {
                // Position non critique, on continue
                logError("POSITION_UPDATE_FAILED", "Coursier {$coursier_id}: " . $e->getMessage());
            }
        }
    }
    
    // Si le coursier passe en inactif/hors_service, libérer ses commandes en attente
    if (in_array($nouveau_statut, ['inactif', 'hors_service', 'suspendu']) && $ancien_statut === 'actif') {
        $liberer_commandes_sql = "
            UPDATE commandes 
            SET coursier_id = NULL, 
                statut = 'en_attente', 
                date_modification = NOW() 
            WHERE coursier_id = ? AND statut IN ('attribuee', 'en_cours')
        ";
        $liberer_stmt = $pdo->prepare($liberer_commandes_sql);
        $liberer_stmt->execute([$coursier_id]);
        $commandes_liberees = $liberer_stmt->rowCount();
    }
    
    // Log de l'activité
    $log_message = "Coursier {$coursier['nom']} (ID: {$coursier_id}) - Statut changé: {$ancien_statut} → {$nouveau_statut}";
    if (isset($commandes_liberees) && $commandes_liberees > 0) {
        $log_message .= " | {$commandes_liberees} commande(s) libérée(s)";
    }
    logActivity("STATUS_CHANGE", $log_message);
    
    // Réponse de succès
    $response = [
        'success' => true,
        'data' => [
            'coursier_id' => $coursier_id,
            'coursier_nom' => $coursier['nom'],
            'ancien_statut' => $ancien_statut,
            'nouveau_statut' => $nouveau_statut,
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => 'Statut mis à jour avec succès'
        ]
    ];
    
    if (isset($commandes_liberees)) {
        $response['data']['commandes_liberees'] = $commandes_liberees;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    logError("UPDATE_COURSIER_STATUS_ERROR", "Coursier {$coursier_id}: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'SYSTEM_ERROR',
        'message' => 'Erreur lors de la mise à jour du statut'
    ]);
}
?>