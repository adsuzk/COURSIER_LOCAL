<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db_maintenance.php';
$pdo = getDBConnection();
echo json_encode(["success" => true, "message" => "Connexion DB OK"]);
