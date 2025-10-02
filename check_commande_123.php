<?php
require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Erreur de connexion: " . $conn->connect_error);
}

$stmt = $conn->prepare('SELECT id, coursier_id, statut, cash_recupere, mode_paiement FROM commandes WHERE id = ?');
$id = 123;
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode($result, JSON_PRETTY_PRINT);

$conn->close();
