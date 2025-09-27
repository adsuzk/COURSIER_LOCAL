<?php
require __DIR__ . '/config.php';
$pdo = getDBConnection();
$rows = $pdo->query('SELECT * FROM agent_tokens LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
