<?php
require_once 'config.php';

$pdo = getPDO();

echo "=== DIAGNOSTIC NOTIFICATION FCM RECHARGEMENT ===\n\n";

// 1. Dernières transactions de rechargement
echo "1. DERNIERS RECHARGEMENTS :\n";
$stmt = $pdo->query("
    SELECT 
        t.id, t.coursier_id, t.montant, t.motif, t.created_at,
        a.nom, a.prenoms, a.matricule
    FROM transactions_financieres t
    LEFT JOIN agents_suzosky a ON a.id = t.coursier_id
    WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY t.created_at DESC
    LIMIT 5
");

while($row = $stmt->fetch()) {
    echo "Transaction #{$row['id']} - {$row['nom']} {$row['prenoms']} ({$row['matricule']})\n";
    echo "  Montant: {$row['montant']} FCFA, Motif: {$row['motif']}\n";
    echo "  Date: {$row['created_at']}\n---\n";
}

// 2. Notifications FCM correspondantes
echo "\n2. NOTIFICATIONS FCM RÉCENTES :\n";
$stmt2 = $pdo->query("
    SELECT 
        n.id, n.coursier_id, n.message, n.status, n.created_at, n.token_used,
        a.nom, a.prenoms, a.matricule
    FROM notifications_log_fcm n
    LEFT JOIN agents_suzosky a ON a.id = n.coursier_id
    WHERE n.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY n.created_at DESC
    LIMIT 5
");

while($row = $stmt2->fetch()) {
    echo "Notification #{$row['id']} - {$row['nom']} {$row['prenoms']} ({$row['matricule']})\n";
    echo "  Message: {$row['message']}\n";
    echo "  Statut: {$row['status']}, Token: " . substr($row['token_used'] ?? 'N/A', 0, 20) . "...\n";
    echo "  Date: {$row['created_at']}\n---\n";
}

// 3. Tokens FCM actifs pour ZALLE
echo "\n3. TOKENS FCM COURSIER ZALLE (ID: 5) :\n";
$stmt3 = $pdo->query("
    SELECT 
        dt.id, dt.token, dt.is_active, dt.platform, dt.updated_at
    FROM device_tokens dt
    WHERE dt.coursier_id = 5
    ORDER BY dt.updated_at DESC
");

while($row = $stmt3->fetch()) {
    echo "Token #{$row['id']} - Platform: {$row['platform']}\n";
    echo "  Actif: " . ($row['is_active'] ? 'OUI' : 'NON') . "\n";
    echo "  Token: " . substr($row['token'], 0, 30) . "...\n";
    echo "  MAJ: {$row['updated_at']}\n---\n";
}