

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


// Validation complète des champs attendus
$requiredFields = [
	'departure', 'destination', 'senderPhone', 'receiverPhone', 'packageDescription', 'priority', 'paymentMethod', 'price'
];
$errors = [];
foreach ($requiredFields as $field) {
	if (empty($data[$field])) {
		$errors[] = "Le champ '$field' est obligatoire.";
	}
}

// Optionnels : distance, duration, lat/lng
$fields = [
	'departure' => $data['departure'] ?? '',
	'destination' => $data['destination'] ?? '',
	'senderPhone' => $data['senderPhone'] ?? '',
	'receiverPhone' => $data['receiverPhone'] ?? '',
	'packageDescription' => $data['packageDescription'] ?? '',
	'priority' => $data['priority'] ?? 'normale',
	'paymentMethod' => $data['paymentMethod'] ?? 'cash',
	'price' => $data['price'] ?? 0,
	'distance' => $data['distance'] ?? '',
	'duration' => $data['duration'] ?? '',
	'departure_lat' => $data['departure_lat'] ?? null,
	'departure_lng' => $data['departure_lng'] ?? null,
	'destination_lat' => $data['destination_lat'] ?? null,
	'destination_lng' => $data['destination_lng'] ?? null,
	'date_creation' => date('Y-m-d H:i:s'),
];

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
	$sql = "INSERT INTO commandes (departure, destination, senderPhone, receiverPhone, packageDescription, priority, paymentMethod, price, distance, duration, departure_lat, departure_lng, destination_lat, destination_lng, date_creation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([
		$fields['departure'],
		$fields['destination'],
		$fields['senderPhone'],
		$fields['receiverPhone'],
		$fields['packageDescription'],
		$fields['priority'],
		$fields['paymentMethod'],
		$fields['price'],
		$fields['distance'],
		$fields['duration'],
		$fields['departure_lat'],
		$fields['departure_lng'],
		$fields['destination_lat'],
		$fields['destination_lng'],
		$fields['date_creation']
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
