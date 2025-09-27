<?php
// Script pour créer automatiquement les enregistrements financiers lors d'une nouvelle commande
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

function createFinancialRecords($orderId, $orderNumber, $coursierId, $amount, $paymentMethod) {
    try {
        $pdo = getDBConnection();
        
        // Récupérer taux dynamiques
        $commissionRate = 15.0;
        $feeRate = 5.0;
        try {
            $st = $pdo->query("SELECT parametre, valeur FROM parametres_tarification");
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($row['parametre'] === 'commission_suzosky') { $commissionRate = max(0.0, min(50.0, (float)$row['valeur'])); }
                if ($row['parametre'] === 'frais_plateforme') { $feeRate = max(0.0, min(50.0, (float)$row['valeur'])); }
            }
        } catch (Throwable $e) { /* defaults */ }

        // Calculer les montants
        $commission = round($amount * ($commissionRate/100.0), 2);
        $platformFee = round($amount * ($feeRate/100.0), 2);
        $netAmount = $commission - $platformFee; // Montant net pour le coursier
        
        // Transaction 1: Crédit commission coursier
        if ($commission > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO transactions_financieres 
                (type, montant, compte_type, compte_id, reference, description, statut) 
                VALUES ('credit', ?, 'coursier', ?, ?, ?, 'reussi')
            ");
            $stmt->execute([
                $commission,
                $coursierId,
                'DELIV_' . $orderNumber,
                "Commission livraison - Commande $orderNumber"
            ]);
        }
        
        // Transaction 2: Débit frais plateforme
        if ($platformFee > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO transactions_financieres 
                (type, montant, compte_type, compte_id, reference, description, statut) 
                VALUES ('debit', ?, 'coursier', ?, ?, ?, 'reussi')
            ");
            $stmt->execute([
                $platformFee,
                $coursierId,
                'DELIV_' . $orderNumber . '_FEE',
                "Frais plateforme - Commande $orderNumber"
            ]);
        }
        
        // Mettre à jour le solde du coursier
        $stmt = $pdo->prepare("
            UPDATE comptes_coursiers 
            SET solde = solde + ?, 
                date_modification = NOW() 
            WHERE coursier_id = ?
        ");
        $stmt->execute([$netAmount, $coursierId]);
        
        return [
            'success' => true,
            'commission' => $commission,
            'platform_fee' => $platformFee,
            'net_amount' => $netAmount
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Test avec notre commande existante
if ($_GET['test'] ?? false) {
    $result = createFinancialRecords(7, 'SZK20250922e4f52a', 1, 2000.00, 'cash');
    echo json_encode($result);
} else {
    echo json_encode(['message' => 'Utilisez ?test=1 pour tester']);
}
?>