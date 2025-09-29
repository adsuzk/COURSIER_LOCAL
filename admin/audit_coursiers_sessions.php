<?php
require_once __DIR__ . '/../config.php';
$pdo = getDBConnection();
$stmt = $pdo->query('SELECT id, matricule, nom, prenoms, statut_connexion, current_session_token FROM agents_suzosky');
foreach($stmt as $row){
    echo $row['id']." | ".$row['matricule']." | ".$row['nom']." | ".$row['prenoms']." | ".$row['statut_connexion']." | ".$row['current_session_token']."\n";
}
