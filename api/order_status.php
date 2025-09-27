<?php
// Public endpoint to fetch order status and minimal timeline for clients
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit;
    }
    $orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
    $code = isset($_GET['code_commande']) ? trim($_GET['code_commande']) : '';
    if ($orderId <= 0 && $code === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Paramètre order_id ou code_commande requis']);
        exit;
    }

    $pdo = getDBConnection();
    $columns = $pdo->query("SHOW COLUMNS FROM commandes")->fetchAll(PDO::FETCH_COLUMN);
    $hasStatut = in_array('statut', $columns, true);
    $hasCode = in_array('code_commande', $columns, true);
    $hasNumero = in_array('numero_commande', $columns, true);
    $hasCoursier = in_array('coursier_id', $columns, true);

    // Construire la requête de sélection
    $where = '';
    $param = null;
    if ($orderId > 0) {
        $where = 'id = ?';
        $param = $orderId;
    } elseif ($code !== '') {
        if ($hasCode) { $where = 'code_commande = ?'; }
        elseif ($hasNumero) { $where = 'numero_commande = ?'; }
        else { throw new Exception('Aucune colonne de code de commande'); }
        $param = $code;
    }

    $stmt = $pdo->prepare("SELECT id, ".($hasStatut?"statut":"NULL AS statut").", ".($hasCoursier?"coursier_id":"NULL AS coursier_id").", date_creation, date_modification FROM commandes WHERE $where LIMIT 1");
    $stmt->execute([$param]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Commande introuvable']);
        exit;
    }

    // Timeline simple basée sur le statut avec dérivation si ENUM non conforme
    $timeline = [];
    $now = date('c');
    $timeline[] = ['key' => 'nouvelle', 'label' => 'Commande reçue', 'done' => true, 'ts' => $order['date_creation'] ?? $now];
    $rawStatut = $order['statut'] ?? null;
    $statut = $rawStatut ?: 'nouvelle';
    // Si un coursier a été attribué mais que le statut n'est pas reconnu (ENUM vide ou autre), considérer 'assignee' pour l'affichage
    if ($hasCoursier && !empty($order['coursier_id']) && in_array($statut, ['','nouvelle',null], true)) {
        $statut = 'assignee';
    }
    $timeline[] = ['key' => 'assignee', 'label' => 'Recherche d\'un coursier', 'done' => in_array($statut, ['assignee','en_cours','livree'], true), 'ts' => $now];
    $timeline[] = ['key' => 'en_cours', 'label' => 'Coursier en route', 'done' => in_array($statut, ['en_cours','livree'], true), 'ts' => $now];
    $timeline[] = ['key' => 'livree', 'label' => 'Livraison effectuée', 'done' => $statut === 'livree', 'ts' => $now];

    // Déterminer si le suivi live doit être affiché pour ce client
    $liveTracking = false;
    try {
        $hasLinkTable = $pdo->query("SHOW TABLES LIKE 'commandes_coursiers'")->rowCount() > 0;
        if ($hasLinkTable) {
            $hasActiveCol = $pdo->query("SHOW COLUMNS FROM commandes_coursiers LIKE 'active'")->rowCount() > 0;
            $sql = "SELECT coursier_id, ".($hasActiveCol?"active":"0 AS active")." AS active FROM commandes_coursiers WHERE commande_id = ? ORDER BY date_attribution DESC LIMIT 1";
            $st2 = $pdo->prepare($sql);
            $st2->execute([(int)$order['id']]);
            $link = $st2->fetch(PDO::FETCH_ASSOC);
            if ($link && intval($link['active']) === 1) { $liveTracking = true; }
        }
    } catch (Throwable $e) { /* silencieux */ }

    echo json_encode([
        'success' => true,
        'data' => [
            'order_id' => (int)$order['id'],
            'statut' => $statut,
            'raw_statut' => $rawStatut,
            'coursier_id' => $order['coursier_id'] ? (int)$order['coursier_id'] : null,
            'live_tracking' => $liveTracking,
            'timeline' => $timeline
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
