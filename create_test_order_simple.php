<?php
// Créer rapidement une commande de test
$pdo = new PDO('mysql:host=127.0.0.1;dbname=coursier_local;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$code = 'TEST-' . time();
$orderNumber = 'ORD-' . time() . '-' . rand(1000, 9999);

$sql = "INSERT INTO commandes (
    order_number, code_commande, client_nom, client_telephone, telephone_expediteur,
    adresse_depart, adresse_arrivee, description_colis, prix_total, prix_estime,
    statut, coursier_id, mode_paiement, distance_estimee, created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    $orderNumber,
    $code,
    'Client Test Polling',
    '+225 07 00 00 00 00',
    '+225 07 00 00 00 00',
    'Cocody Angré 7ème Tranche',
    'Plateau Rue du Commerce',
    'COLIS TEST POLLING - Attendre 10 secondes',
    5000,
    5000,
    'nouvelle',
    5,
    'especes',
    12.5
]);

$id = $pdo->lastInsertId();
echo "✅ Commande créée : ID=$id, Code=$code, Statut=nouvelle, Coursier=5\n";
echo "⏳ Attendre 10-15 secondes pour voir le polling dans logcat...\n";
