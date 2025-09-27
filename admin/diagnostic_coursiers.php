<?php
require_once __DIR__ . '/../config.php';
$pdo = getPDO();

$agents = $pdo->query("SELECT id, nom, prenoms, type_poste FROM agents_suzosky WHERE type_poste IN ('coursier','coursier_moto','coursier_velo')")->fetchAll();
$comptes = $pdo->query("SELECT coursier_id FROM comptes_coursiers")->fetchAll(PDO::FETCH_COLUMN);

echo "<h2>Diagnostic Synchronisation Coursiers</h2>";
echo "<table border=1 cellpadding=6><tr><th>ID</th><th>Nom</th><th>Prénoms</th><th>Type</th><th>Compte Financier</th></tr>";
foreach ($agents as $a) {
    $hasCompte = in_array($a['id'], $comptes) ? '✅' : '❌';
    echo "<tr><td>{$a['id']}</td><td>{$a['nom']}</td><td>{$a['prenoms']}</td><td>{$a['type_poste']}</td><td style='font-size:1.5em;'>$hasCompte</td></tr>";
}
echo "</table>";
