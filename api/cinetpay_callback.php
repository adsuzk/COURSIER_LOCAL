<?php
// api/cinetpay_callback.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/finances_sync.php';

// Lire la payload JSON ou POST form
event:
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?: $_POST;

// Vérifier signature si fournie (optionnelle)
$sharedSecret = getenv('CINETPAY_WEBHOOK_SECRET') ?: '';
if (!empty($sharedSecret)) {
    $headerSig = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
    $computed = hash_hmac('sha256', $input, $sharedSecret);
    if (!hash_equals($computed, $headerSig)) {
        // journaliser mais continuer en 200 pour ne pas casser l'ACK si non configuré côté CinetPay
        file_put_contents(__DIR__.'/../cinetpay_sync_errors.txt', date('c')." - Invalid signature for callback: header='$headerSig'\n", FILE_APPEND);
    }
}

$status = $data['status'] ?? '';
$transactionId = $data['transaction_id'] ?? '';
$responseId = $data['cpm_trans_id'] ?? '';
$amount = $data['amount'] ?? 0;

go: try {
    $pdo = getDBConnection();
    // Extraire rechargeId du transactionId
    list($rechargeId, ) = explode('_', $transactionId, 2);
    // Charger enregistrement
    $stmt = $pdo->prepare("SELECT * FROM recharges WHERE id = ?");
    $stmt->execute([$rechargeId]);
    $recharge = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$recharge) exit;

    // Mettre à jour details JSON et créer l'entrée dans payment_transactions si succès
    $details = json_encode($data);
    $statusEnum = in_array($status, ['ACCEPTED','COMPLETED']) ? 'success' : 'failed';
    $upd = $pdo->prepare("UPDATE recharges SET status = ?, details = ?, updated_at = NOW(), cinetpay_transaction_id = ? WHERE id = ?");
    $upd->execute([$statusEnum, $details, $responseId, $rechargeId]);

    // Si succès, créer l'entrée pour admin finances (table payment_transactions)
    if ($statusEnum === 'success' && $amount > 0) {
        $insertTx = $pdo->prepare("INSERT INTO payment_transactions 
            (user_id, user_type, transaction_type, amount, currency, reference, description, status, created_at) 
            VALUES (?, 'coursier', 'recharge', ?, 'XOF', ?, ?, 'completed', NOW())");
        $insertTx->execute([
            $recharge['coursier_id'],
            $amount,
            $responseId,
            "Recharge CinetPay via mobile - Transaction: $transactionId"
        ]);

        // --- Synchronisation avec la banque admin (finances) ---
        try {
            ensureCourierAccount($pdo, (int)$recharge['coursier_id']);

            // 1) Insérer/mettre à jour la recharge validée dans recharges_coursiers (historique back-office)
            $stmt = $pdo->prepare("SELECT id FROM recharges_coursiers WHERE reference_paiement = ?");
            $stmt->execute([$responseId]);
            $rc_id = $stmt->fetchColumn();
            if (!$rc_id) {
                $ins = $pdo->prepare("INSERT INTO recharges_coursiers (coursier_id, montant, reference_paiement, statut, date_demande, date_validation, commentaire_admin) VALUES (?, ?, ?, 'validee', NOW(), NOW(), 'Recharge validée automatiquement via CinetPay')");
                $ins->execute([$recharge['coursier_id'], $amount, $responseId]);
                $rc_id = $pdo->lastInsertId();
            } else {
                $pdo->prepare("UPDATE recharges_coursiers SET statut = 'validee', date_validation = NOW() WHERE id = ?")
                    ->execute([$rc_id]);
            }

            // 2) Créditer les soldes (comptes_coursiers + coursier_accounts) de manière idempotente
            $financeReference = 'RECH_' . ($rc_id ?: $rechargeId);
            $credited = creditCourierIfNewRef(
                $pdo,
                (int)$recharge['coursier_id'],
                (float)$amount,
                $financeReference,
                'Recharge validée automatiquement via CinetPay'
            );

            if (!$credited) {
                // Même référence déjà traitée: mettre à jour les indicateurs côté coursier_accounts
                adjustCoursierRechargeBalance($pdo, (int)$recharge['coursier_id'], (float)$amount, ['affect_total' => true]);
            }
        } catch (Exception $syncEx) {
            // Log interne mais ne bloque pas la réponse
            file_put_contents(__DIR__.'/../cinetpay_sync_errors.txt', date('c')." - Sync error: ".$syncEx->getMessage()."\n", FILE_APPEND);
        }
    }

    // Log interne
    file_put_contents(__DIR__.'/../cinetpay_logs.txt', date('c')." - Callback: $input\n", FILE_APPEND);

    // Réponse 200
    header('HTTP/1.1 200 OK');
    echo 'OK';
    exit;
} catch (Exception $e) {
    http_response_code(500);
    exit;
}