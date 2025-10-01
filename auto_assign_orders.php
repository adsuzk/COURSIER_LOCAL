<?php
// Processus d'attribution automatique simple

require_once 'config.php';

$pdo = getDBConnection();

// Récupération des paramètres de connexion depuis la config (comme getDBConnection)
$db_conf = [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'user' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
    'name' => getenv('DB_NAME') ?: 'coursier_local',
];
$db = new mysqli($db_conf['host'], $db_conf['user'], $db_conf['password'], $db_conf['name']);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Récupérer tous les coursiers actifs (agents_suzosky)
$coursiers = [];
$result = $db->query("SELECT id FROM agents_suzosky WHERE statut_connexion = 'en_ligne' AND status = 'actif'");
while ($row = $result->fetch_assoc()) {
    $coursiers[] = $row['id'];
}

// Récupérer toutes les commandes non attribuées
$commandes = [];
$result = $db->query("SELECT id FROM commandes WHERE statut = 'nouvelle' ORDER BY id ASC");
while ($row = $result->fetch_assoc()) {
    $commandes[] = $row['id'];
}

// Pour chaque coursier, construire sa file d'attente de commandes et notifier
require_once __DIR__ . '/api/lib/fcm_enhanced.php';
foreach ($coursiers as $coursier_id) {
    // Récupérer les commandes déjà attribuées à ce coursier
    $res = $db->query("SELECT id FROM commandes WHERE coursier_id = $coursier_id AND (statut = 'assignee' OR statut = 'en_attente') ORDER BY id ASC");
    $queue = [];
    while ($row = $res->fetch_assoc()) {
        $queue[] = $row['id'];
    }

    // Remplir la file d'attente avec de nouvelles commandes si besoin
    while (count($commandes) > 0) {
        $commande_id = array_shift($commandes);
        $queue[] = $commande_id;
    }

    // Mettre à jour le statut de la première commande de la file (active), les autres en attente
    foreach ($queue as $i => $cmd_id) {
        if ($i == 0) {
            $db->query("UPDATE commandes SET coursier_id = $coursier_id, statut = 'assignee' WHERE id = $cmd_id");
            // Notifier le coursier de la nouvelle commande
            $token_row = $db->query("SELECT token FROM device_tokens WHERE (agent_id = $coursier_id OR coursier_id = $coursier_id) AND is_active = 1 ORDER BY updated_at DESC LIMIT 1")->fetch_assoc();
            if ($token_row && !empty($token_row['token'])) {
                fcm_send_with_log([
                    $token_row['token']
                ], "🚨 Nouvelle commande !", "Ouvrez l'app pour voir vos commandes", [
                    'type' => 'new_orders',
                    'commande_id' => $cmd_id,
                    '_data_only' => true
                ], $coursier_id, 'AUTO_ASSIGN');
            }
        } else {
            $db->query("UPDATE commandes SET coursier_id = $coursier_id, statut = 'en_attente' WHERE id = $cmd_id");
        }
    }
}
?>
