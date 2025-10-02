<?php
require_once 'config.php';

$conn = getDBConnection();

$stmt = $conn->prepare('SELECT id, coursier_id, statut, cash_recupere, mode_paiement FROM commandes WHERE id = ?');
$id = 123;
$stmt->bindValue(1, $id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($result, JSON_PRETTY_PRINT);

