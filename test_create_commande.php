<?php
// Script de test : Créer une commande et vérifier la réception dans l'app

// Connexion directe à la BDD
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=coursier_local;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur connexion : " . $e->getMessage());
}

echo "🧪 TEST DE POLLING AUTOMATIQUE\n";
echo "================================\n\n";

// Étape 1 : Vérifier les commandes actuelles du coursier 5
echo "📊 Étape 1 : Commandes actuelles du coursier 5\n";
$stmt = $pdo->prepare("SELECT id, code_commande, statut FROM commandes WHERE coursier_id = 5 ORDER BY created_at DESC LIMIT 3");
$stmt->execute();
$commandesAvant = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Commandes actuelles :\n";
foreach ($commandesAvant as $cmd) {
    echo "  - ID: {$cmd['id']} | Code: {$cmd['code_commande']} | Statut: {$cmd['statut']}\n";
}
echo "Total : " . count($commandesAvant) . " commandes\n\n";

// Étape 2 : Créer une nouvelle commande
echo "🆕 Étape 2 : Création d'une nouvelle commande de test\n";

// Générer un code unique
$code = 'TEST-' . strtoupper(substr(md5(microtime()), 0, 8));
$orderNumber = 'ORDER-' . time() . '-' . rand(1000, 9999);

$insertCmd = $pdo->prepare("
    INSERT INTO commandes (
        order_number,
        code_commande,
        client_nom,
        client_telephone,
        telephone_expediteur,
        adresse_depart,
        adresse_arrivee,
        description_colis,
        prix_total,
        prix_estime,
        statut,
        coursier_id,
        mode_paiement,
        distance_estimee,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

$insertCmd->execute([
    $orderNumber,
    $code,
    'Client Test Polling',
    '+225 07 00 00 00 00',
    '+225 07 00 00 00 00',
    'Cocody Angré 7ème Tranche',
    'Plateau Rue du Commerce',
    'Colis test pour validation polling',
    5000,
    5000,
    'nouvelle',
    5,  // Coursier ID
    'especes',
    12.5,
]);

$newCmdId = $pdo->lastInsertId();

echo "✅ Commande créée :\n";
echo "  - ID: $newCmdId\n";
echo "  - Code: $code\n";
echo "  - Statut: nouvelle\n";
echo "  - Coursier: 5\n\n";

// Étape 3 : Vérifier que la commande est bien assignée
echo "🔍 Étape 3 : Vérification de l'assignation\n";
$stmt = $pdo->prepare("SELECT id, code_commande, statut, coursier_id FROM commandes WHERE id = ?");
$stmt->execute([$newCmdId]);
$verification = $stmt->fetch(PDO::FETCH_ASSOC);

if ($verification && $verification['coursier_id'] == 5) {
    echo "✅ Commande correctement assignée au coursier 5\n";
    echo "   Statut: {$verification['statut']}\n\n";
} else {
    echo "❌ ERREUR : Problème d'assignation\n\n";
}

// Étape 4 : Instructions pour le test
echo "📱 Étape 4 : Instructions de test\n";
echo "==================================\n\n";
echo "1. Ouvrez l'application sur le téléphone\n";
echo "2. Attendez maximum 10 secondes\n";
echo "3. Vérifiez que la nouvelle commande apparaît (Code: $code)\n\n";

echo "Pour suivre les logs Android :\n";
echo "  adb logcat | Select-String \"MainActivity\"\n\n";

echo "Pour vérifier la réception côté serveur :\n";
echo "  php check_last_orders.php\n\n";

// Étape 5 : Attendre 15 secondes et vérifier
echo "⏳ Attente de 15 secondes pour laisser le temps au polling...\n";
sleep(15);

echo "\n🔍 Vérification finale après 15 secondes\n";
$stmt = $pdo->prepare("SELECT id, code_commande, statut FROM commandes WHERE coursier_id = 5 ORDER BY created_at DESC LIMIT 4");
$stmt->execute();
$commandesApres = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Commandes actuelles du coursier 5 :\n";
foreach ($commandesApres as $cmd) {
    $isNew = ($cmd['id'] == $newCmdId) ? " ← NOUVELLE" : "";
    echo "  - ID: {$cmd['id']} | Code: {$cmd['code_commande']} | Statut: {$cmd['statut']}$isNew\n";
}

echo "\n";
echo "Total : " . count($commandesApres) . " commandes\n";
echo "Différence : +" . (count($commandesApres) - count($commandesAvant)) . " commande(s)\n\n";

// Résumé
echo "📊 RÉSUMÉ DU TEST\n";
echo "=================\n";
echo "Commande créée : ID $newCmdId (Code: $code)\n";
echo "Délai de polling : 10 secondes\n";
echo "Temps écoulé : 15 secondes\n\n";

echo "✅ Si la commande apparaît dans l'app : POLLING FONCTIONNE !\n";
echo "❌ Si rien ne change : Vérifier les logs Android\n\n";

echo "🎯 Prochaine étape : Phase 2 (Admin SSE) et Phase 3 (Android UX)\n";
