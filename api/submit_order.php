

<?php
// Réactivation progressive du squelette métier
header('Content-Type: application/json');

// Chargement des dépendances principales
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../logger.php';
require_once __DIR__ . '/../lib/db_maintenance.php';
require_once __DIR__ . '/../cinetpay/cinetpay_integration.php';

// Protection anti-sortie parasite : buffer de sortie
ob_start();

// Gestion d'erreur simple (étape 1)
set_error_handler(function($severity, $message, $file, $line) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => $message, 'debug' => 'Erreur PHP']);
	exit;
});

set_exception_handler(function($e) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => $e->getMessage(), 'debug' => 'Exception PHP']);
	exit;
});

// Log minimal de la requête
if (function_exists('logMessage')) {
	logMessage('diagnostics_errors.log', 'submit_order.php appelé');
}

// Lecture des données POST (JSON ou x-www-form-urlencoded)
$rawInput = file_get_contents('php://input');
$data = null;
if (!empty($rawInput)) {
	$data = json_decode($rawInput, true);
	if (json_last_error() !== JSON_ERROR_NONE) {
		// Si ce n'est pas du JSON, tenter x-www-form-urlencoded
		parse_str($rawInput, $data);
	}
} else {
	$data = $_POST;
}

// Validation minimale (exemple: champ obligatoire 'client_id')
$errors = [];
if (empty($data['client_id'])) {
	$errors[] = "Le champ 'client_id' est obligatoire.";
}

// Log des données reçues
if (function_exists('logMessage')) {
	logMessage('diagnostics_errors.log', 'submit_order.php DATA: ' . json_encode($data));
}

if (!empty($errors)) {
	echo json_encode(["success" => false, "message" => "Erreur de validation", "errors" => $errors, "debug" => $data]);
	exit;
}


// Connexion à la base de données (mode développement)
try {
	$dbConf = $config['db']['development'];
	$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbConf['host'], $dbConf['port'], $dbConf['name']);
	$pdo = new PDO($dsn, $dbConf['user'], $dbConf['password'], [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
	]);
} catch (Throwable $e) {
	if (function_exists('logMessage')) {
		logMessage('diagnostics_errors.log', 'Erreur connexion DB: ' . $e->getMessage());
	}
	echo json_encode(["success" => false, "message" => "Erreur connexion base de données", "error" => $e->getMessage()]);
	exit;
}

// Squelette d'insertion d'une commande (simulation)
// TODO: Adapter les champs selon la structure réelle de la table commandes
$commande = [
	'client_id' => $data['client_id'],
	'date_creation' => date('Y-m-d H:i:s'),
	// Ajouter d'autres champs nécessaires ici
];

// Exemple de requête préparée (simulation, pas d'insert réel)
// $stmt = $pdo->prepare('INSERT INTO commandes (client_id, date_creation) VALUES (?, ?)');
// $stmt->execute([$commande['client_id'], $commande['date_creation']]);

// Log de la commande simulée
if (function_exists('logMessage')) {
	logMessage('diagnostics_errors.log', 'Commande simulée: ' . json_encode($commande));
}

echo json_encode(["success" => true, "message" => "Commande simulée insérée (non réelle)", "commande" => $commande]);
