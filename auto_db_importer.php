<?php
// auto_db_importer.php
// Script d'automatisation pour importer et configurer toute nouvelle base SQL dans COURSIER_LOCAL
// Usage : php auto_db_importer.php [--force]

$baseDir = __DIR__;
$sqlDir = $baseDir . DIRECTORY_SEPARATOR . '_sql';
$logFile = $baseDir . DIRECTORY_SEPARATOR . 'diagnostic_logs' . DIRECTORY_SEPARATOR . 'auto_db_importer.log';
$envOverride = $baseDir . DIRECTORY_SEPARATOR . 'env_override.php';
$configFile = $baseDir . DIRECTORY_SEPARATOR . 'config.php';

function logmsg($msg) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
    echo $msg . "\n";
}

// 1. Détecter le dernier dump SQL
$sqlFiles = glob($sqlDir . DIRECTORY_SEPARATOR . '*.sql');
if (!$sqlFiles) {
    logmsg('Aucun fichier SQL trouvé dans _sql/.');
    exit(1);
}
usort($sqlFiles, function($a, $b) { return filemtime($b) - filemtime($a); });
$latestSql = $sqlFiles[0];
$basename = pathinfo($latestSql, PATHINFO_FILENAME);
$newDbName = 'coursier_' . date('Ymd') . '_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $basename));

logmsg("Fichier SQL détecté : $latestSql");
logmsg("Nom de la nouvelle base : $newDbName");

// 2. Créer la base de données
$mysqli = new mysqli('127.0.0.1', 'root', '');
if ($mysqli->connect_errno) {
    logmsg('Erreur connexion MySQL: ' . $mysqli->connect_error);
    exit(2);
}
$mysqli->query("DROP DATABASE IF EXISTS `$newDbName`");
if (!$mysqli->query("CREATE DATABASE `$newDbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    logmsg('Erreur création base: ' . $mysqli->error);
    exit(3);
}
logmsg("Base $newDbName créée.");

// 3. Importer le dump
$importCmd = "C:/xampp/mysql/bin/mysql.exe -u root $newDbName < \"$latestSql\"";
logmsg("Import en cours: $importCmd");
exec($importCmd, $out, $ret);
if ($ret !== 0) {
    logmsg('Erreur import SQL. Code: ' . $ret);
    exit(4);
}
logmsg("Import terminé.");

// 4. Normalisation colonnes (exemple: ajout colonne 'statut' si manquante dans clients)
$mysqli->select_db($newDbName);
$res = $mysqli->query("SHOW COLUMNS FROM clients LIKE 'statut'");
if ($res && $res->num_rows === 0) {
    $mysqli->query("ALTER TABLE clients ADD statut VARCHAR(32) DEFAULT 'actif'");
    logmsg("Colonne 'statut' ajoutée à clients.");
}

// 5. Mise à jour env_override.php
$env = file_get_contents($envOverride);
$env = preg_replace("/putenv\('DB_NAME=[^']*'\);/", "putenv('DB_NAME=$newDbName');", $env);
file_put_contents($envOverride, $env);
logmsg("env_override.php mis à jour pour DB_NAME=$newDbName");

// 6. Mise à jour config.php (optionnel, si besoin)
$config = file_get_contents($configFile);
$config = preg_replace("/'name'\s*=>\s*'[^']*',/", "'name'     => '$newDbName',", $config);
file_put_contents($configFile, $config);
logmsg("config.php mis à jour pour DB_NAME=$newDbName");

logmsg("✅ Nouvelle base importée et configurée automatiquement !");
?>