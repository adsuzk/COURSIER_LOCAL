<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) { $input = $_POST; }

    $commandeId = (int)($input['commande_id'] ?? 0);
    $coursierId = (int)($input['coursier_id'] ?? 0);
    $action = strtolower(trim($input['action'] ?? 'accept')); // accept|release
    $ttl = (int)($input['ttl_seconds'] ?? 60);

    if (!$commandeId || !$coursierId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
        exit;
    }

    $pdo = getPDO();

    if ($action === 'release') {
        $pdo->prepare("UPDATE dispatch_locks SET status = 'released' WHERE commande_id = ? AND locked_by = ?")
            ->execute([$commandeId, $coursierId]);
        echo json_encode(['success' => true, 'released' => true]);
        exit;
    }

    // Tentative d'accepter avec lock
    $pdo->beginTransaction();
    try {
        // Nettoyer les locks expirés
        $pdo->exec("UPDATE dispatch_locks SET status = 'expired' WHERE status = 'locked' AND TIMESTAMPDIFF(SECOND, created_at, NOW()) > ttl_seconds");

        // Vérifier existence lock
        $st = $pdo->prepare("SELECT * FROM dispatch_locks WHERE commande_id = ? FOR UPDATE");
        $st->execute([$commandeId]);
        $lock = $st->fetch(PDO::FETCH_ASSOC);
        if ($lock && $lock['status'] === 'locked' && (int)$lock['locked_by'] !== $coursierId) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Commande déjà en cours d\'acceptation']);
            exit;
        }

        if (!$lock) {
            $ins = $pdo->prepare("INSERT INTO dispatch_locks (commande_id, locked_by, status, ttl_seconds) VALUES (?, ?, 'locked', ?)");
            $ins->execute([$commandeId, $coursierId, max(30, min($ttl, 300))]);
        } else {
            $upd = $pdo->prepare("UPDATE dispatch_locks SET locked_by = ?, status = 'locked', ttl_seconds = ?, updated_at = NOW() WHERE commande_id = ?");
            $upd->execute([$coursierId, max(30, min($ttl, 300)), $commandeId]);
        }

        // Marquer la commande acceptée si table dispo et non déjà assignée
        $hasTable = $pdo->query("SHOW TABLES LIKE 'commandes_classiques'")->rowCount() > 0;
        if ($hasTable) {
            // S'assurer que la commande est libre
            $chk = $pdo->prepare("SELECT coursier_id, statut FROM commandes_classiques WHERE id = ? FOR UPDATE");
            $chk->execute([$commandeId]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$row) { throw new RuntimeException('Commande introuvable'); }
            if (!empty($row['coursier_id']) && (int)$row['coursier_id'] !== $coursierId) {
                throw new RuntimeException('Commande déjà assignée');
            }
            if (empty($row['coursier_id'])) {
                $pdo->prepare("UPDATE commandes_classiques SET coursier_id = ?, statut = 'acceptee', date_acceptation = NOW() WHERE id = ?")
                    ->execute([$coursierId, $commandeId]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'locked' => true]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
