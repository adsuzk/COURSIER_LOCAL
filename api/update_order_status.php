<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

try {
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
    // Accept JSON body or classic form POST for maximum compatibility
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !is_array($input)) { $input = $_POST ?: []; }
    $commandeId = intval($input['commande_id'] ?? 0);
    $statut = trim((string)($input['statut'] ?? ''));
    $cashCollectedFlag = isset($input['cash_collected']) ? (bool)$input['cash_collected'] : false;
    $cashAmount = isset($input['cash_amount']) ? floatval($input['cash_amount']) : null;

    if (!$commandeId || !$statut) {
        echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
        exit;
    }

    // Validation transitions simples
    $validStatuts = ['nouvelle','acceptee','en_cours','picked_up','livree'];
    if (!in_array($statut, $validStatuts)) {
        echo json_encode(['success' => false, 'error' => 'Statut invalide']);
        exit;
    }

    // Assurer l'existence de la table commandes_classiques (environnement local/dev tolérant)
    $hasMainTable = false;
    try {
        $hasMainTable = $pdo->query("SHOW TABLES LIKE 'commandes_classiques'")->rowCount() > 0;
    } catch (Throwable $e) { $hasMainTable = false; }

    if (!$hasMainTable) {
        // Tentative de création minimale (best-effort pour éviter erreurs de sync en dev)
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS commandes_classiques (
                id INT AUTO_INCREMENT PRIMARY KEY,
                coursier_id INT NULL,
                statut VARCHAR(32) DEFAULT 'nouvelle',
                mode_paiement VARCHAR(32) NULL,
                prix_estime DECIMAL(10,2) NULL,
                pickup_time DATETIME NULL,
                delivered_time DATETIME NULL,
                cash_collected TINYINT(1) DEFAULT 0,
                cash_amount DECIMAL(10,2) NULL,
                date_acceptation DATETIME NULL,
                date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_coursier (coursier_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $hasMainTable = true;
        } catch (Throwable $e) {
            // Si création impossible, renvoyer un succès idempotent pour ne pas bloquer l'appli
            echo json_encode(['success' => true, 'noop' => true, 'warning' => 'TABLE_MISSING']);
            exit;
        }
    }

    // Colonnes timeline dynamiques (pickup_time, delivered_time)
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'pickup_time'")->rowCount();
        if ($cols === 0) {
            try { $pdo->exec("ALTER TABLE commandes_classiques ADD COLUMN pickup_time DATETIME NULL"); } catch (Exception $e) {}
        }
        $cols2 = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'delivered_time'")->rowCount();
        if ($cols2 === 0) {
            try { $pdo->exec("ALTER TABLE commandes_classiques ADD COLUMN delivered_time DATETIME NULL"); } catch (Exception $e) {}
        }
    } catch (Throwable $e) {
        // Si SHOW COLUMNS échoue pour une raison quelconque, ne pas bloquer : continuer
    }

    // Colonnes cash
    $hasCashMode = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'mode_paiement'")->rowCount() > 0;
    $hasCashCollected = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'cash_collected'")->rowCount() > 0;
    if (!$hasCashCollected) { try { $pdo->exec("ALTER TABLE commandes_classiques ADD COLUMN cash_collected TINYINT(1) DEFAULT 0"); } catch (Exception $e) {} }
    $hasCashAmount = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'cash_amount'")->rowCount() > 0;
    if (!$hasCashAmount) { try { $pdo->exec("ALTER TABLE commandes_classiques ADD COLUMN cash_amount DECIMAL(10,2) NULL"); } catch (Exception $e) {} }

    // Lire la commande pour logique cash et statut courant
    $stmtInfo = $pdo->prepare("SELECT mode_paiement, prix_estime, cash_collected, statut FROM commandes_classiques WHERE id = ?");
    $stmtInfo->execute([$commandeId]);
    $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
    if (!$info) {
        // En environnement partiel, insérer une ligne minimale (best-effort) ou renvoyer succès idempotent
        try {
            $ins = $pdo->prepare("INSERT INTO commandes_classiques (id, statut) VALUES (?, ?) ON DUPLICATE KEY UPDATE statut = VALUES(statut)");
            $ins->execute([$commandeId, $statut]);
            // Recharger info après insertion
            $stmtInfo->execute([$commandeId]);
            $info = $stmtInfo->fetch(PDO::FETCH_ASSOC) ?: ['mode_paiement' => null, 'prix_estime' => null, 'cash_collected' => 0, 'statut' => $statut];
        } catch (Throwable $e) {
            echo json_encode(['success' => true, 'noop' => true, 'warning' => 'ORDER_ROW_MISSING']);
            exit;
        }
    }

    $isCash = $hasCashMode && strtolower($info['mode_paiement']) === 'cash';
    $currentStatut = isset($info['statut']) ? trim(strtolower((string)$info['statut'])) : '';

    // Si tentative de livrer en mode cash sans collecte enregistrée, bloquer
    if ($statut === 'livree' && $isCash && empty($info['cash_collected']) && !$cashCollectedFlag) {
        echo json_encode(['success' => false, 'error' => 'Cash non confirmé']);
        exit;
    }

    $setExtra = '';
    if ($statut === 'picked_up') { $setExtra = ', pickup_time = NOW()'; }
    if ($statut === 'livree') { $setExtra = ', delivered_time = NOW()'; }

    // Mise à jour cash si fourni
    if ($isCash && $cashCollectedFlag) {
        $setExtra .= ', cash_collected = 1';
        if ($cashAmount !== null) {
            $setExtra .= ', cash_amount = ' . $pdo->quote($cashAmount);
        }
    }

    // Si le statut demandé est déjà en place et qu'il n'y a rien d'autre à mettre à jour, considérer comme succès idempotent
    $needsCashUpdate = $isCash && $cashCollectedFlag && empty($info['cash_collected']);
    if ($currentStatut === $statut && $setExtra === '' && !$needsCashUpdate) {
        echo json_encode(['success' => true, 'noop' => true]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE commandes_classiques SET statut = ? $setExtra WHERE id = ?");
    $ok = $stmt->execute([$statut, $commandeId]);

    if ($ok && $stmt->rowCount() > 0) {
        // Historique de statut (best-effort)
        try {
            // Assurer l'existence de la table d'historique
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS order_status_history (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    commande_id INT NOT NULL,
                    old_status VARCHAR(32) NULL,
                    new_status VARCHAR(32) NOT NULL,
                    changed_by VARCHAR(32) NULL,
                    coursier_id INT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_commande (commande_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (Throwable $e) { /* ignore create errors */ }

            $oldStatus = null; // Non disponible facilement ici sans SELECT avant; on enregistre seulement new_status
            $changedBy = 'coursier';
            $coursierIdHist = null;
            $hasCoursierIdCol = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'coursier_id'")->rowCount() > 0;
            if ($hasCoursierIdCol) {
                $stc = $pdo->prepare("SELECT coursier_id FROM commandes_classiques WHERE id = ? LIMIT 1");
                $stc->execute([$commandeId]);
                $cc = $stc->fetch(PDO::FETCH_ASSOC);
                if ($cc && !empty($cc['coursier_id'])) { $coursierIdHist = (int)$cc['coursier_id']; }
            }
            $pdo->prepare("INSERT INTO order_status_history (commande_id, old_status, new_status, changed_by, coursier_id) VALUES (?, ?, ?, ?, ?)")
                ->execute([$commandeId, $oldStatus, $statut, $changedBy, $coursierIdHist]);
        } catch (Throwable $e) { /* ignore */ }
        // Heuristique: si la commande passe en acceptee / picked_up / en_cours, la marquer active pour ce coursier si table de liaison existe
        try {
            $hasLinkTable = $pdo->query("SHOW TABLES LIKE 'commandes_coursiers'")->rowCount() > 0;
            if ($hasLinkTable && in_array($statut, ['picked_up','en_cours'])) {
                // Trouver le coursier affecté via colonne coursier_id s'il existe
                $hasCoursierId = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'coursier_id'")->rowCount() > 0;
                $coursierId = null;
                if ($hasCoursierId) {
                    $st = $pdo->prepare("SELECT coursier_id FROM commandes_classiques WHERE id = ? LIMIT 1");
                    $st->execute([$commandeId]);
                    $c = $st->fetch(PDO::FETCH_ASSOC);
                    if ($c && !empty($c['coursier_id'])) { $coursierId = (int)$c['coursier_id']; }
                }
                if ($coursierId) {
                    // Désactiver autres, activer celle-ci
                    $pdo->prepare("UPDATE commandes_coursiers SET active = 0 WHERE coursier_id = ?")->execute([$coursierId]);
                    $pdo->prepare("UPDATE commandes_coursiers SET active = 1, statut = ? WHERE commande_id = ? AND coursier_id = ?")
                        ->execute([$statut, $commandeId, $coursierId]);
                }
            }
        } catch (Throwable $e) { /* best-effort */ }

        // Création automatique des écritures financières à la livraison (idempotent, best-effort)
        if ($statut === 'livree') {
            try {
                // Charger coursier_id (si colonne existe)
                $coursierId = null;
                $hasCoursierIdCol2 = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'coursier_id'")->rowCount() > 0;
                if ($hasCoursierIdCol2) {
                    $stCi = $pdo->prepare("SELECT coursier_id FROM commandes_classiques WHERE id = ? LIMIT 1");
                    $stCi->execute([$commandeId]);
                    $rowCi = $stCi->fetch(PDO::FETCH_ASSOC);
                    if ($rowCi && !empty($rowCi['coursier_id'])) { $coursierId = (int)$rowCi['coursier_id']; }
                }

                if ($coursierId) {
                    // Assurer l'existence des tables financières (best-effort, sans FK strictes)
                    try {
                        $pdo->exec("CREATE TABLE IF NOT EXISTS comptes_coursiers (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            coursier_id INT NOT NULL UNIQUE,
                            solde DECIMAL(10,2) DEFAULT 0.00,
                            statut VARCHAR(20) DEFAULT 'actif',
                            date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
                            date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    } catch (Throwable $e) {}
                    try {
                        $pdo->exec("CREATE TABLE IF NOT EXISTS transactions_financieres (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            type ENUM('credit','debit') NOT NULL,
                            montant DECIMAL(10,2) NOT NULL,
                            compte_type ENUM('coursier','client') NOT NULL,
                            compte_id INT NOT NULL,
                            reference VARCHAR(100) NOT NULL,
                            description TEXT,
                            statut ENUM('en_attente','reussi','echoue') DEFAULT 'reussi',
                            date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_reference (reference)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    } catch (Throwable $e) {}

                    // Assurer que le compte coursier existe
                    try { require_once __DIR__ . '/../lib/finances_sync.php'; } catch (Throwable $e) {}
                    if (function_exists('ensureCourierAccount')) {
                        try { ensureCourierAccount($pdo, $coursierId); } catch (Throwable $e) {}
                    } else {
                        // Fallback minimal si librairie non chargée
                        try {
                            $stAcc = $pdo->prepare("SELECT id FROM comptes_coursiers WHERE coursier_id = ?");
                            $stAcc->execute([$coursierId]);
                            if (!$stAcc->fetchColumn()) {
                                $pdo->prepare("INSERT INTO comptes_coursiers (coursier_id, solde, statut) VALUES (?, 0, 'actif')")->execute([$coursierId]);
                            }
                        } catch (Throwable $e) {}
                    }

                    // Récupérer order_number (compatible avec schémas variables)
                    $orderNumber = null;
                    try {
                        $hasOrderNum = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'order_number'")->rowCount() > 0;
                        $hasNumero = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'numero_commande'")->rowCount() > 0;
                        $hasCode = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'code_commande'")->rowCount() > 0;
                        if ($hasOrderNum) {
                            $s = $pdo->prepare("SELECT order_number FROM commandes_classiques WHERE id = ?");
                            $s->execute([$commandeId]);
                            $orderNumber = ($s->fetch(PDO::FETCH_ASSOC)['order_number'] ?? null) ?: $orderNumber;
                        }
                        if (!$orderNumber && $hasNumero) {
                            $s = $pdo->prepare("SELECT numero_commande FROM commandes_classiques WHERE id = ?");
                            $s->execute([$commandeId]);
                            $orderNumber = ($s->fetch(PDO::FETCH_ASSOC)['numero_commande'] ?? null) ?: $orderNumber;
                        }
                        if (!$orderNumber && $hasCode) {
                            $s = $pdo->prepare("SELECT code_commande FROM commandes_classiques WHERE id = ?");
                            $s->execute([$commandeId]);
                            $orderNumber = ($s->fetch(PDO::FETCH_ASSOC)['code_commande'] ?? null) ?: $orderNumber;
                        }
                    } catch (Throwable $e) {}

                    // Montant de base pour calculs financiers
                    $amountBase = 0.0;
                    try {
                        $amountBase = isset($info['prix_estime']) ? floatval($info['prix_estime']) : 0.0;
                        if ($amountBase <= 0 && $isCash && !is_null($cashAmount)) {
                            $amountBase = floatval($cashAmount);
                        }
                    } catch (Throwable $e) { $amountBase = 0.0; }

                    if ($amountBase > 0 && $orderNumber) {
                        // Récupérer taux dynamiques
                        $commissionRate = 15.0;
                        $feeRate = 5.0;
                        try {
                            $stpar = $pdo->query("SELECT parametre, valeur FROM parametres_tarification");
                            foreach ($stpar->fetchAll(PDO::FETCH_ASSOC) as $pr) {
                                if ($pr['parametre'] === 'commission_suzosky') { $commissionRate = max(0.0, min(50.0, (float)$pr['valeur'])); }
                                if ($pr['parametre'] === 'frais_plateforme') { $feeRate = max(0.0, min(50.0, (float)$pr['valeur'])); }
                            }
                        } catch (Throwable $e) { /* défauts */ }
                        // Calculs dynamiques
                        $commission = round($amountBase * ($commissionRate/100.0), 2);
                        $platformFee = round($amountBase * ($feeRate/100.0), 2);

                        // Références stables pour idempotence
                        $refCommission = 'DELIV_' . $orderNumber;
                        $refFee = 'DELIV_' . $orderNumber . '_FEE';

                        // Enregistrer un snapshot des paramètres actifs (première fois seulement)
                        try {
                            $pdo->exec("CREATE TABLE IF NOT EXISTS financial_context_by_order (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                order_number VARCHAR(100) NOT NULL UNIQUE,
                                commission_rate DECIMAL(5,2) NULL,
                                fee_rate DECIMAL(5,2) NULL,
                                prix_kilometre DECIMAL(10,2) NULL,
                                frais_base DECIMAL(10,2) NULL,
                                supp_km_rate DECIMAL(10,2) NULL,
                                supp_km_free_allowance DECIMAL(10,2) NULL,
                                captured_at DATETIME DEFAULT CURRENT_TIMESTAMP
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                            // Charger aussi les autres paramètres de tarification
                            $prixKm = null; $fraisBase = null; $suppRate = null; $suppFree = null;
                            try {
                                if (!isset($stpar)) { $stpar = $pdo->query("SELECT parametre, valeur FROM parametres_tarification"); }
                                foreach ($stpar->fetchAll(PDO::FETCH_ASSOC) as $pr2) {
                                    if ($pr2['parametre'] === 'prix_kilometre') { $prixKm = (float)$pr2['valeur']; }
                                    if ($pr2['parametre'] === 'frais_base') { $fraisBase = (float)$pr2['valeur']; }
                                    if ($pr2['parametre'] === 'supp_km_rate') { $suppRate = (float)$pr2['valeur']; }
                                    if ($pr2['parametre'] === 'supp_km_free_allowance') { $suppFree = (float)$pr2['valeur']; }
                                }
                            } catch (Throwable $e) {}

                            // N'insérer que si pas encore présent (conserver la 1ère valeur utilisée)
                            $checkSnap = $pdo->prepare("SELECT COUNT(*) FROM financial_context_by_order WHERE order_number = ?");
                            $checkSnap->execute([$orderNumber]);
                            if ((int)$checkSnap->fetchColumn() === 0) {
                                $insSnap = $pdo->prepare("INSERT INTO financial_context_by_order (order_number, commission_rate, fee_rate, prix_kilometre, frais_base, supp_km_rate, supp_km_free_allowance) VALUES (?,?,?,?,?,?,?)");
                                $insSnap->execute([$orderNumber, $commissionRate, $feeRate, $prixKm, $fraisBase, $suppRate, $suppFree]);
                            }
                        } catch (Throwable $e) { /* snapshot best-effort */ }

                        // Commission (credit) si référence inexistante
                        try {
                            $chk = $pdo->prepare("SELECT COUNT(*) FROM transactions_financieres WHERE reference = ?");
                            $chk->execute([$refCommission]);
                            if ((int)$chk->fetchColumn() === 0 && $commission > 0) {
                                // MAJ solde
                                $pdo->prepare("UPDATE comptes_coursiers SET solde = solde + ? WHERE coursier_id = ?")
                                    ->execute([$commission, $coursierId]);
                                // Transaction
                                $pdo->prepare("INSERT INTO transactions_financieres (type, montant, compte_type, compte_id, reference, description, statut) VALUES ('credit', ?, 'coursier', ?, ?, ?, 'reussi')")
                                    ->execute([$commission, $coursierId, $refCommission, 'Commission livraison - Commande ' . $orderNumber]);
                            }
                        } catch (Throwable $e) {}

                        // Frais plateforme (debit) si référence inexistante
                        try {
                            $chk2 = $pdo->prepare("SELECT COUNT(*) FROM transactions_financieres WHERE reference = ?");
                            $chk2->execute([$refFee]);
                            if ((int)$chk2->fetchColumn() === 0 && $platformFee > 0) {
                                // MAJ solde
                                $pdo->prepare("UPDATE comptes_coursiers SET solde = solde - ? WHERE coursier_id = ?")
                                    ->execute([$platformFee, $coursierId]);
                                // Transaction
                                $pdo->prepare("INSERT INTO transactions_financieres (type, montant, compte_type, compte_id, reference, description, statut) VALUES ('debit', ?, 'coursier', ?, ?, ?, 'reussi')")
                                    ->execute([$platformFee, $coursierId, $refFee, 'Frais plateforme - Commande ' . $orderNumber]);
                            }
                        } catch (Throwable $e) {}
                    }
                }
            } catch (Throwable $e) { /* ne pas bloquer la réponse principale */ }
        }

        echo json_encode(['success' => true, 'cash_required' => $isCash, 'cash_collected' => ($isCash ? ($cashCollectedFlag || !empty($info['cash_collected'])) : null)]);
    } else {
        // Aucun changement SQL, mais si le statut est déjà le bon, renvoyer succès idempotent
        if ($currentStatut === $statut) {
            echo json_encode(['success' => true, 'noop' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Aucune mise à jour']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
