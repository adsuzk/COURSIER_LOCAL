<?php
// Marquer une commande comme "active" pour un coursier (contrôle du suivi en direct côté client)
// POST JSON: { coursier_id: int, commande_id: int, active: bool }
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'METHOD_NOT_ALLOWED']);
        exit;
    }

    // Accept JSON body or classic form POST
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !is_array($input)) { $input = $_POST ?: []; }
    $coursierId = isset($input['coursier_id']) ? (int)$input['coursier_id'] : 0;
    $commandeId = isset($input['commande_id']) ? (int)$input['commande_id'] : 0;
    $active = isset($input['active']) ? (bool)$input['active'] : true;
    if ($coursierId <= 0 || $commandeId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'MISSING_FIELDS']);
        exit;
    }

    // Support both helper names from config.php
    $pdo = null;
    if (function_exists('getPDO')) {
        $pdo = getPDO();
    } elseif (function_exists('getDBConnection')) {
        $pdo = getDBConnection();
    }
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'DB connection unavailable']);
        exit;
    }

    // Vérifier existence de la table de liaison
    $hasLinkTable = $pdo->query("SHOW TABLES LIKE 'commandes_coursiers'")->rowCount() > 0;
    if (!$hasLinkTable) {
        // Créer table minimale si absente
        $pdo->exec("CREATE TABLE IF NOT EXISTS commandes_coursiers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            commande_id INT NOT NULL,
            coursier_id INT NOT NULL,
            statut VARCHAR(32) DEFAULT 'assignee',
            active TINYINT(1) DEFAULT 0,
            date_attribution DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_coursier (coursier_id),
            UNIQUE KEY uniq_pair (commande_id, coursier_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // S'assurer de la colonne active
    $hasActiveCol = $pdo->query("SHOW COLUMNS FROM commandes_coursiers LIKE 'active'")->rowCount() > 0;
    if (!$hasActiveCol) {
        try { $pdo->exec("ALTER TABLE commandes_coursiers ADD COLUMN active TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
    }

    // Désactiver toutes les autres commandes actives de ce coursier
    $pdo->prepare("UPDATE commandes_coursiers SET active = 0 WHERE coursier_id = ?")
        ->execute([$coursierId]);

    // Activer (ou créer) la ligne de liaison pour cette commande
    $stmt = $pdo->prepare("SELECT id FROM commandes_coursiers WHERE commande_id = ? AND coursier_id = ? LIMIT 1");
    $stmt->execute([$commandeId, $coursierId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $pdo->prepare("UPDATE commandes_coursiers SET active = ?, statut = IF(statut IN ('assignee','acceptee','en_cours'), statut, 'acceptee') WHERE id = ?")
            ->execute([$active ? 1 : 0, $row['id']]);
    } else {
        $pdo->prepare("INSERT INTO commandes_coursiers (commande_id, coursier_id, statut, active, date_attribution) VALUES (?,?,?,?, NOW())")
            ->execute([$commandeId, $coursierId, 'acceptee', $active ? 1 : 0]);
    }

    echo json_encode(['success' => true, 'data' => [
        'commande_id' => $commandeId,
        'coursier_id' => $coursierId,
        'active' => (bool)$active
    ]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
