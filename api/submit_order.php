

<?php
// Log de debug ultra-précoce (toute requête entrante, avant tout traitement)
if (file_exists(__DIR__ . '/../logger.php')) {
	$debugLog = __DIR__ . '/../diagnostic_logs/diagnostics_errors.log';
	$debugHeaders = json_encode(getallheaders() ?: []);
	$debugBody = file_get_contents('php://input');
	$debugMeta = [
		'date' => date('Y-m-d H:i:s'),
		'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
		'method' => $_SERVER['REQUEST_METHOD'] ?? '',
		'uri' => $_SERVER['REQUEST_URI'] ?? '',
		'headers' => $debugHeaders,
		'body' => $debugBody
	];
	file_put_contents($debugLog, "[DEBUG-REQ] " . json_encode($debugMeta) . "\n", FILE_APPEND);
}
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

// --- Rétro-compatibilité: normaliser les clés entrantes (anglais -> français)
$legacyMap = [
	'departure' => 'adresse_depart',
	'destination' => 'adresse_arrivee',
	'senderPhone' => 'telephone_expediteur',
	'receiverPhone' => 'telephone_destinataire',
	'sender_phone' => 'telephone_expediteur',
	'receiver_phone' => 'telephone_destinataire',
	'packageDescription' => 'description_colis',
	'packageDesc' => 'description_colis',
	'package_description' => 'description_colis',
	'priority' => 'priorite',
	'paymentMethod' => 'mode_paiement',
	'payment_method' => 'mode_paiement',
	'price' => 'prix_estime',
	'distance' => 'distance_estimee',
	'duration' => 'distance_estimee',
	'dimensions' => 'dimensions',
	'weight' => 'poids_estime',
	'poids' => 'poids_estime',
	'fragile' => 'fragile'
];
if (is_array($data)) {
	foreach ($legacyMap as $old => $new) {
		if ((isset($data[$old]) || isset($data[$old]))) {
			// Ne pas écraser une valeur déjà normalisée
			if (!isset($data[$new]) || $data[$new] === '') {
				$data[$new] = $data[$old];
			}
		}
	}
}


// Validation complète des champs attendus

// Champs obligatoires selon la structure réelle de la table
$requiredFields = [
	'adresse_depart', 'adresse_arrivee', 'telephone_expediteur', 'telephone_destinataire', 'priorite', 'mode_paiement', 'prix_estime'
];
$errors = [];
foreach ($requiredFields as $field) {
	if (empty($data[$field])) {
		$errors[] = "Le champ '$field' est obligatoire.";
	}
}

// Mapping des champs pour correspondre à la table 'commandes'
$fields = [
	'adresse_depart' => $data['adresse_depart'] ?? '',
	'adresse_arrivee' => $data['adresse_arrivee'] ?? '',
	'telephone_expediteur' => $data['telephone_expediteur'] ?? '',
	'telephone_destinataire' => $data['telephone_destinataire'] ?? '',
	'description_colis' => $data['description_colis'] ?? '', // optionnel
	'priorite' => $data['priorite'] ?? 'normale',
	'mode_paiement' => $data['mode_paiement'] ?? 'especes',
	'prix_estime' => $data['prix_estime'] ?? 0,
	'distance_estimee' => $data['distance_estimee'] ?? null,
	'dimensions' => $data['dimensions'] ?? null,
	'poids_estime' => $data['poids_estime'] ?? null,
	'fragile' => $data['fragile'] ?? 0,
	'created_at' => date('Y-m-d H:i:s'),
];

// Générer un code_commande unique (court, <=20 chars) si la table requiert une valeur unique
try {
	$rand = strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));
} catch (Throwable $e) {
	$rand = strtoupper(substr(md5(uniqid('', true)), 0, 3));
}
$fields['code_commande'] = 'SZ' . date('ymdHis') . $rand; // ex: SZ250930123045A1B

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




// Insertion réelle en base de données (table 'commandes')
try {
	$sql = "INSERT INTO commandes (code_commande, adresse_depart, adresse_arrivee, telephone_expediteur, telephone_destinataire, description_colis, priorite, mode_paiement, prix_estime, distance_estimee, dimensions, poids_estime, fragile, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([
		$fields['code_commande'],
		$fields['adresse_depart'],
		$fields['adresse_arrivee'],
		$fields['telephone_expediteur'],
		$fields['telephone_destinataire'],
		$fields['description_colis'],
		$fields['priorite'],
		$fields['mode_paiement'],
		$fields['prix_estime'],
		$fields['distance_estimee'],
		$fields['dimensions'],
		$fields['poids_estime'],
		$fields['fragile'],
		$fields['created_at']
	]);
	$commande_id = $pdo->lastInsertId();
	if (function_exists('logMessage')) {
		logMessage('diagnostics_errors.log', 'Commande insérée: ' . json_encode($fields) . ' | id=' . $commande_id);
	}
	echo json_encode(["success" => true, "message" => "Commande insérée en base", "commande_id" => $commande_id, "commande" => $fields]);
} catch (Throwable $e) {
	if (function_exists('logMessage')) {
		logMessage('diagnostics_errors.log', 'Erreur insertion commande: ' . $e->getMessage());
	}
	echo json_encode(["success" => false, "message" => "Erreur lors de l'insertion en base", "error" => $e->getMessage(), "commande" => $fields]);
}
